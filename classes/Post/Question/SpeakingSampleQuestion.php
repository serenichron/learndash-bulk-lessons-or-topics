<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Audio;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasAudio;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;
use TSTPrep\LDImporter\Post\Question\Util\CorrectAsContentQuestion;

class SpeakingSampleQuestion extends CorrectAsContentQuestion implements Audio, WordCounter {
  use HasAudio, HasWordCounter;

  public function getAudioSettings(): array {
    return [
      'timer' => 300,
      'timer_min' => 60,
    ];
  }

  public function getWordCounterSettings(): array {
    return [
      'is_checkedWord' => 'off',
      'word_counter' => 100,
      'is_checkedTimer' => 'on',
      'timer_counter' => 60,
    ];
  }
}
