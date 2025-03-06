<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Transcript;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasTranscript;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;

class ListenAndTypeQuestion extends Question implements Transcript, WordCounter {
  use HasTranscript, HasWordCounter;

  public function getTranscriptSettings(): array {
    return [
      'is_checkedWord' => 'off',
      'word_counter' => 100,
      'is_checkedTimer' => 'on',
      'timer_counter' => 60,
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
