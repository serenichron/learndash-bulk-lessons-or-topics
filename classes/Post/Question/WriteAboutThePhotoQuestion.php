<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Image;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasImage;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;

class WriteAboutThePhotoQuestion extends Question implements WordCounter, Image {
  use HasWordCounter, HasImage;

  public function getImageSettings(): array {
    return [
      'is_checkedTimer_image' => 'off',
      'timer_counter_image' => 20,
    ];
  }

  public function getWordCounterSettings(): array {
    return [
      'is_checkedWord' => 'on',
      'word_counter' => 20,
      'is_checkedTimer' => 'on',
      'timer_counter' => 60,
    ];
  }
}
