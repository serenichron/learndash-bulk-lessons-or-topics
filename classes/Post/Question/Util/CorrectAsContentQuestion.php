<?php

namespace TSTPrep\LDImporter\Post\Question\Util;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Question\Question;
use WpProQuiz_Model_AnswerTypes;

class CorrectAsContentQuestion extends Question {
  protected function setProps(Data $data) {
    parent::setProps($data);

    $this->correct = strip_tags($this->content);
    $this->correctSameAsText = 1;
    $this->answerData = [
      new WpProQuiz_Model_AnswerTypes([
        'points' => 1,
        'graded' => 1,
        'gradedType' => 'text',
      ]),
    ];
  }
}
