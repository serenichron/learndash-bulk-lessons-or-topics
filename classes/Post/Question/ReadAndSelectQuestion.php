<?php

namespace TSTPrep\LDImporter\Post\Question;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use TSTPrep\LDImporter\Post\Question\Traits\HasWordCounter;
use WpProQuiz_Model_AnswerTypes;

class ReadAndSelectQuestion extends Question implements WordCounter {
  use HasWordCounter;

  protected function setProps(Data $data) {
    parent::setProps($data);

    if (!$this->customFields[0] || !$this->customFields[1]) {
      return;
    }

    $options = explode(',', $this->customFields[0]);
    $corrects = explode(',', $this->customFields[1]);

    $correct = [];

    foreach ($options as $option) {
      $correct[] = [
        'question' => "<p>{$option}</p>",
        'points' => '0',
        'correct' => in_array($option, $corrects) || 0,
        'attempted' => '',
      ];
    }

    shuffle($correct);
    $this->correct = $correct;

    $this->content = wp_kses_post($this->content);
    $this->answerData = [
      new WpProQuiz_Model_AnswerTypes([
        'points' => 1,
        'graded' => 1,
        'gradedType' => 'text',
        // TODO: this is probably not used.
        'answer' => 'Array',
        // 'answer' => $this->correct,
      ]),
    ];
  }

  public function updateMeta(Data $data, Posts $posts) {
    parent::updateMeta($data, $posts);

    if ($this->correct) {
      update_post_meta($this->id, '_ld_advanced_swipe_questions', $this->correct);
    }
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
