<?php

namespace TSTPrep\LDImporter\Post\Question;

class CompleteTheSentencesQuestion extends FillTheBlanksQuestion {
  public function getWordCounterSettings(): array {
    return [
      'is_checkedWord' => 'off',
      'word_counter' => 100,
      'is_checkedTimer' => 'off',
      'timer_counter' => 60,
    ];
  }
}
