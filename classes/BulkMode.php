<?php

namespace TSTPrep\LDImporter;

/**
 * Hold off the expensive per-save work for the length of an import.
 *
 * Saving one post is cheap. Saving two hundred is not, because every save
 * drags a tail of hooks behind it. On this site the worst of those is the
 * cache plugin, which purges and then re-warms pages over HTTP after every
 * single update. Measured on a real question post that was 2.7 seconds a
 * save, against 0.005 seconds for the database write itself.
 *
 * Nothing is skipped. The purge is collected, deduplicated, and done once
 * at the end, which is the same result for a fraction of the time.
 */
class BulkMode {
  private bool $active = false;
  private bool $hadRevisions = false;

  /** @var array<int, string> */
  private array $purgeUrls = [];

  /** @var callable|null */
  private $collector = null;

  public function start(): void {
    if ($this->active) {
      return;
    }

    $this->active = true;
    $this->purgeUrls = [];

    // Term counts are recalculated once on release rather than per post.
    wp_defer_term_counting(true);

    // An import is not an edit history worth keeping.
    $this->hadRevisions = has_action('post_updated', 'wp_save_post_revision') !== false;
    if ($this->hadRevisions) {
      remove_action('post_updated', 'wp_save_post_revision');
    }

    $this->collector = function ($urls) {
      $this->purgeUrls = array_merge($this->purgeUrls, (array) $urls);

      // An empty list makes the purge and the preload that follows it into
      // no-ops, without the cache plugin having to know about any of this.
      return [];
    };

    add_filter('flying_press_auto_purge_urls', $this->collector, 99);
  }

  public function finish(): void {
    if (!$this->active) {
      return;
    }

    $this->active = false;

    if ($this->collector !== null) {
      remove_filter('flying_press_auto_purge_urls', $this->collector, 99);
      $this->collector = null;
    }

    if ($this->hadRevisions) {
      add_action('post_updated', 'wp_save_post_revision', 10, 1);
    }

    wp_defer_term_counting(false);

    $this->purgeCollected();
  }

  private function purgeCollected(): void {
    $urls = array_values(array_unique(array_filter($this->purgeUrls)));
    $this->purgeUrls = [];

    if (empty($urls) || !class_exists('FlyingPress\Purge')) {
      return;
    }

    \FlyingPress\Purge::purge_urls($urls);
  }
}
