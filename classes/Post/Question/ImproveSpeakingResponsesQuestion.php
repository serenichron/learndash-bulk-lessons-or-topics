<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Audio;
use TSTPrep\LDImporter\Post\Question\Contracts\Transcript;
use TSTPrep\LDImporter\Post\Question\Traits\HasAudio;
use TSTPrep\LDImporter\Post\Question\Traits\HasTranscript;

class ImproveSpeakingResponsesQuestion extends Question implements Audio, Transcript {
  use HasAudio, HasTranscript;

  public function getAudioSettings(): array {
    return [
      'timer' => 30,
      'timer_min' => 0,
    ];
  }

  public function getTranscriptSettings(): array {
    return [
      'is_checkedWord' => 'off',
      'word_counter' => 100,
      'is_checkedTimer' => 'off',
      'timer_counter' => 60,
    ];
  }
}
