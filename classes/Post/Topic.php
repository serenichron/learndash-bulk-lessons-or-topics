<?php

namespace TSTPrep\LDImporter\Post;

use Exception;
use TSTPrep\LDImporter\Data;

class Topic extends Post {
  protected string $type = 'topic';
  protected string $wpType = 'sfwd-topic';

  public function updateMeta(Data $data, Posts $posts) {
    if (!$posts->course?->exists() || !$posts->lesson?->exists()) {
      return;
    }

    $courseId = $posts->course->id;
    $lessonId = $posts->lesson->id;

    $res = learndash_course_add_child_to_parent($courseId, $this->id, $lessonId);
    if (!$res) {
      throw new Exception(sprintf(__('Cannot link topic %s to lesson %s'), $this->id, $lessonId));
    }
  }
}
