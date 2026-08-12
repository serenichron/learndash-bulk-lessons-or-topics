<?php

namespace TSTPrep\LDImporter\Post;

use Exception;
use TSTPrep\LDImporter\Data;

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

    // This row has nothing at this level. Read no further: a row with no
    // question in it must not be forced through Question::setProps(), which
    // throws on an empty question type.
    if ($id === null) {
      return $post;
    }

    // Reuse whatever the previous row resolved to. Nothing on the row itself
    // applies, so there are no properties to read.
    if ($id === 'PREV') {
      $post->prev($posts);
      $data->setId($post->type, $post->id);
      return $post;
    }

    $post->setProps($data, $posts);

    if ($id === 'CREATE') {
      $post->create($posts);
      $data->setId($post->type, $post->id);
      return $post;
    }

    $post->id = $id;
    $post->update($posts);

    return $post;
  }

  public function create(Posts $posts) {
    $args = array_merge(
      [
        'post_title' => $this->title ?? '',
        'post_content' => $this->content ?? '',
        'post_type' => $this->wpType,
        'post_status' => 'publish',
      ],
      $this->extraArgs(),
    );

    $id = wp_insert_post($args, true);

    if (is_wp_error($id)) {
      throw new Exception(
        sprintf(__('Error creating %s: %s', 'extended-learndash-bulk-create'), $this->type, $id->get_error_message()),
      );
    }

    $this->id = $id;
  }

  public function update(Posts $posts) {
    $args = [
      'ID' => $this->id,
    ];

    if ($this->title !== null) {
      $args['post_title'] = $this->title;
    }

    if ($this->content !== null) {
      $args['post_content'] = $this->content;
    }

    $args = array_merge($args, $this->extraArgs());

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

  protected function setProps(Data $data, Posts $posts) {
    $this->title = $data->title($this->type);
    $this->content = $data->content($this->type);
  }

  /**
   * Extra post fields to write in the same save as the title and content.
   *
   * A second wp_update_post just to set one column costs a full trip through
   * every hook on the save path, which is the most expensive thing an import
   * does. Anything that can ride along with the first save should.
   *
   * @return array<string, mixed>
   */
  protected function extraArgs(): array {
    return [];
  }

  abstract public function updateMeta(Data $data, Posts $posts);

  public function exists(): bool {
    return $this->id !== null;
  }

  public function isPrev(): bool {
    return $this->isPrev;
  }
}
