<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;
use WpProQuiz_Model_AnswerTypes;

class CompleteThePassageQuestion extends Question implements WordCounter {
  use HasWordCounter;

  protected function setProps(Data $data) {
    parent::setProps($data);

    if (!$this->customFields[0] || !$this->customFields[1]) {
      return;
    }

    $options = array_map('trim', explode(';', $this->customFields[0]));
    $corrects = array_map('trim', explode(';', $this->customFields[1]));

    $answers = [];

    foreach ($options as $option) {
      $answers[] = new WpProQuiz_Model_AnswerTypes([
        'points' => 0,
        'graded' => 1,
        'gradedType' => 'text',
        'answer' => $option,
        'correct' => in_array($option, $corrects) ? 1 : 0,
      ]);
    }

    shuffle($answers);

    $this->content = wp_kses_post($this->content);
    $this->answerData = $answers;
  }

  public function getWordCounterSettings(): array {
    return [
      'is_checkedWord' => 'off',
      'word_counter' => 100,
      'is_checkedTimer' => 'off',
      'timer_counter' => 60,
    ];
  }
}
