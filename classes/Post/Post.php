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
      $id = $posts->prev?->{$post->type}?->id;
      $post->id = $id;
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

  protected function setProps(Data $data) {
    $this->title = $data->title($this->type);
    $this->content = $data->content($this->type);
  }

  abstract public function updateMeta(Data $data, Posts $posts);

  public function exists(): bool {
    return $this->id !== null;
  }
}
