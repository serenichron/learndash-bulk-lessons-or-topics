<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Audio;
use TSTPrep\LDImporter\Post\Question\Traits\HasAudio;

class ReadAloudQuestion extends Question implements Audio {
  use HasAudio;

  public function getAudioSettings(): array {
    return [
      'timer' => 20,
      'timer_min' => 0,
    ];
  }
}
