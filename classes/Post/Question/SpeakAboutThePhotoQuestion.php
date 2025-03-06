<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Post\Question\Contracts\Audio;
use TSTPrep\LDImporter\Post\Question\Contracts\Image;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasAudio;
use TSTPrep\LDImporter\Post\Question\Traits\HasImage;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;

class SpeakAboutThePhotoQuestion extends Question implements Audio, WordCounter, Image {
  use HasAudio, HasWordCounter, HasImage;

  public function getAudioSettings(): array {
    return [
      'timer' => 90,
      'timer_min' => 30,
    ];
  }

  public function getImageSettings(): array {
    return [
      'is_checkedTimer_image' => 'on',
      'timer_counter_image' => 20,
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
