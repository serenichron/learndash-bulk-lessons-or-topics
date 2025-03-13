<?php

namespace TSTPrep\LDImporter\Post\Question\Traits;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

trait HasTranscript {
  public function updateTranscriptMeta(Data $data, Posts $posts) {
    if (!$this->correct) {
      return;
    }

    $settings = $this->getTranscriptSettings();

    // Define the allowed symbols
    $allowed_symbols = '!?\'.,;:-+=$()@';

    // Remove any characters that are not in the allowed list
    $this->correct = sanitize_textarea_field(
      preg_replace('/[^\w' . preg_quote($allowed_symbols, '/') . ' ]/', '', $this->correct),
    );

    // TODO
    // $procesio_data[] = [
    //   'post_id' => $this->id,
    //   'to_be_translated' => $this->correct,
    // ];

    update_post_meta($this->id, '_ld_tstprep_transcript', [
      [
        'repetitions' => 3,
        'to_be_translated' => $this->correct,
        'correct' => $this->correct,
        'is_checkedWord' => $settings['is_checkedWord'],
        'word_counter' => $settings['word_counter'],
        'is_checkedTimer' => $settings['is_checkedTimer'],
        'timer_counter' => $settings['timer_counter'],
      ],
    ]);
  }
}
