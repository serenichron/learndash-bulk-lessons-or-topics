<?php

namespace TSTPrep\LDImporter\Post;

use Exception;
use TSTPrep\LDImporter\Data;

class Lesson extends Post {
  protected string $type = 'lesson';
  protected string $wpType = 'sfwd-lessons';

  public function updateMeta(Data $data, Posts $posts) {
    if (!$posts->course?->exists()) {
      return;
    }

    $courseId = $posts->course->id;

    $res = learndash_course_add_child_to_parent($courseId, $this->id, $courseId);
    if (!$res) {
      throw new Exception(sprintf(__('Cannot link lesson %s to course %s'), $this->id, $courseId));
    }
  }
}
