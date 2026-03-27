<?php

namespace TSTPrep\LDImporter\Post;

use Exception;
use TSTPrep\LDImporter\Data;
use WP_Post;

abstract class Post {
  protected ?int $id;
  protected ?string $title;
  protected ?string $content;
  protected string $type;
  protected string $wpType;
  protected bool $isPrev = false;

  public function __construct(?int $id = null, ?string $title = null, ?string $content = null) {
    $this->id = $id;
    $this->title = $title;
    $this->content = $content;
  }

  public static function createOrUpdate(Data $data, Posts $posts): static {
    $post = new static();

    $id = $data->id($post->type);
    $post->setProps($data);

    if ($id === null) {
      // Do nothing.
      return $post;
    }

    if ($id === 'CREATE') {
      $post->create($posts);
      $data->setId($post->type, $post->id);
      return $post;
    }

    if ($id === 'PREV') {
      $post->prev($posts);
      $data->setId($post->type, $post->id);
      return $post;
    }

    $existing = $post->getExistingPost($id);
    if ($existing === null) {
      $post->create($posts);
      $data->setId($post->type, $post->id);
      return $post;
    }

    $post->id = $id;
    $post->update($posts);

    return $post;
  }

  public function create(Posts $posts) {
    $args = [
      'post_title' => $this->title ?? '',
      'post_content' => $this->content ?? '',
      'post_type' => $this->wpType,
      'post_status' => 'publish',
    ];

    $id = wp_insert_post($args, true);

    if (is_wp_error($id)) {
      throw new Exception(
        sprintf(__('Error creating %s: %s', 'extended-learndash-bulk-create'), $this->type, $id->get_error_message()),
      );
    }

    $this->id = $id;
  }

  public function update(Posts $posts) {
    $existing = $this->getExistingPost($this->id);
    if ($existing === null) {
      throw new Exception(
        sprintf(__('Cannot update %s: post %d does not exist.', 'extended-learndash-bulk-create'), $this->type, $this->id),
      );
    }

    $args = [
      'ID' => $this->id,
    ];

    if ($this->title !== null) {
      $args['post_title'] = $this->title;
    }

    if ($this->content !== null) {
      $args['post_content'] = $this->content;
    }

    if (count($args) === 1) {
      return;
    }

    $res = wp_update_post($args, true);
    if (is_wp_error($res)) {
      throw new Exception(
        sprintf(__('Error updating %s: %s', 'extended-learndash-bulk-create'), $this->type, $res->get_error_message()),
      );
    }
  }

  public function prev(Posts $posts) {
    $id = $posts->prev?->{$this->type}?->id;
    $this->id = $id;
    $this->isPrev = true;
  }

  protected function setProps(Data $data) {
    $this->title = $data->title($this->type);
    $this->content = $data->content($this->type);
  }

  protected function getExistingPost(?int $id): ?WP_Post {
    if ($id === null) {
      return null;
    }

    $post = get_post($id);
    if ($post === null) {
      return null;
    }

    if ($post->post_type !== $this->wpType) {
      throw new Exception(
        sprintf(
          __('Invalid %1$s post ID %2$d: expected %3$s, got %4$s.', 'extended-learndash-bulk-create'),
          $this->type,
          $id,
          $this->wpType,
          $post->post_type,
        ),
      );
    }

    return $post;
  }

  abstract public function updateMeta(Data $data, Posts $posts);

  public function exists(): bool {
    return $this->id !== null;
  }

  public function isPrev(): bool {
    return $this->isPrev;
  }
}
