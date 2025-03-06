<?php

namespace TSTPrep\LDImporter\Post\Question\Traits;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

trait HasWordCounter {
  public function updateWordCounterMeta(Data $data, Posts $posts) {
    $settings = $this->getWordCounterSettings();

    update_post_meta($this->id, '_ld_tstprep_word_counter', [
      [
        'is_checkedWord' => $settings['is_checkedWord'],
        'word_counter' => $settings['word_counter'],
        'is_checkedTimer' => $settings['is_checkedTimer'],
        'timer_counter' => $settings['timer_counter'],
      ],
    ]);
  }
}
