<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;
use WpProQuiz_Model_AnswerTypes;

class FillTheBlanksQuestion extends Question implements WordCounter {
  use HasWordCounter;

  protected function setProps(Data $data) {
    parent::setProps($data);

    if (!$this->customFields[0]) {
      return;
    }

    $this->correct = $this->customFields[0];
    $this->answerData = [
      new WpProQuiz_Model_AnswerTypes([
        'points' => 1,
        'graded' => 1,
        'gradedType' => 'text',
        'answer' => $this->correct,
      ]),
    ];
  }

  public function getWordCounterSettings(): array {
    return [
      'is_checkedWord' => 'off',
      'word_counter' => 100,
      'is_checkedTimer' => 'on',
      'timer_counter' => 180,
    ];
  }
}
