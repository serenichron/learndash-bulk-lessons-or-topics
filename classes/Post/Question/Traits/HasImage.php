<?php

namespace TSTPrep\LDImporter\Post\Question\Traits;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

trait HasImage {
  public function updateImageMeta(Data $data, Posts $posts) {
    $settings = $this->getImageSettings();

    update_post_meta($this->id, '_ld_tstprep_image_description', [
      'image_description' => esc_attr($this->correct),
      'is_checkedTimer_image' => $settings['is_checkedTimer_image'],
      'timer_counter_image' => $settings['timer_counter_image'],
    ]);
  }
}
