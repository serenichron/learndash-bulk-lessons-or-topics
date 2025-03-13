<?php

namespace TSTPrep\LDImporter\Post\Question\Traits;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

trait HasAudio {
  public function updateAudioMeta(Data $data, Posts $posts) {
    if (!$this->correct) {
      return;
    }

    $settings = $this->getAudioSettings();

    update_post_meta($this->id, '_ld_advanced_audio_questions', [
      [
        'is_download' => 'off',
        'timer' => $settings['timer'],
        'timer_min' => $settings['timer_min'],
        'getGradingProgression' => 'not-graded-none',
      ],
    ]);

    update_post_meta($this->id, '_ld_tstprep_read_aloud', [
      [
        'is_download' => 'off',
        'timer' => $settings['timer'],
        'timer_min' => $settings['timer_min'],
        'getGradingProgression' => 'not-graded-none',
        'correct' => esc_attr($this->correct),
      ],
    ]);
  }
}
