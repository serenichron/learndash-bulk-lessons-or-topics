<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;
use TSTPrep\LDImporter\Post\Question\Util\CorrectAsContentQuestion;

class WritingSampleQuestion extends CorrectAsContentQuestion implements WordCounter {
  use HasWordCounter;

  public function getWordCounterSettings(): array {
    return [
      'is_checkedWord' => 'on',
      'word_counter' => 100,
      'is_checkedTimer' => 'on',
      'timer_counter' => 300,
    ];
  }
}
