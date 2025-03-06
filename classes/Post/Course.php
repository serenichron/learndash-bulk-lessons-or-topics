<?php

namespace TSTPrep\LDImporter\Post;

use TSTPrep\LDImporter\Data;

class Course extends Post {
  protected string $type = 'course';
  protected string $wpType = 'sfwd-courses';

  public function updateMeta(Data $data, Posts $posts) {
    $groupId = $data->id('group', false);

    if ($groupId !== null) {
      ld_update_course_group_access($this->id, $groupId, false);
    }
  }
}
