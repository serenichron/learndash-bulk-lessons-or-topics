<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Audio;
use TSTPrep\LDImporter\Post\Question\Traits\HasAudio;
use TSTPrep\LDImporter\Post\Question\Util\CorrectAsContentQuestion;

class ReadThenSpeakQuestion extends CorrectAsContentQuestion implements Audio {
  use HasAudio;

  public function getAudioSettings(): array {
    return [
      'timer' => 90,
      'timer_min' => 30,
    ];
  }
}
