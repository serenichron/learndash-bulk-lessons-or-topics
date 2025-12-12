<?php

namespace TSTPrep\LDImporter\Post;

use Exception;
use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuizAffix;
use TSTPrep\LDImporter\Data;
use WpProQuiz_Model_Quiz;
use WpProQuiz_Model_QuizMapper;

class Quiz extends Post {
  protected string $type = 'quiz';
  protected string $wpType = 'sfwd-quiz';

  protected int $proId = 0;
  protected string $quizType = 'base';

  protected function setProps(Data $data) {
    parent::setProps($data);

    $meta = $data->quizMeta();
    if (!empty($meta) && isset($meta['quiz_type'])) {
      $this->quizType = $meta['quiz_type'];
    }
  }

  public function create(Posts $posts) {
    parent::create($posts);

    $this->proId = $this->savePro();
    update_post_meta($this->id, 'quiz_pro_id', $this->proId);
    update_post_meta($this->id, 'quiz_pro_id_' . $this->proId, $this->proId);
    update_post_meta($this->id, 'quiz_pro_primary_' . $this->proId, $this->proId);
    update_post_meta($this->id, '_sfwd-quiz', [
      'sfwd-quiz_quiz_pro' => $this->proId,
      'sfwd-quiz_autostart' => true,
      'sfwd-quiz_quizModus_single_feedback' => 'each',
    ]);
  }

  public function update(Posts $posts) {
    parent::update($posts);
    $this->proId = intval(get_post_meta($this->id, 'quiz_pro_id', true));
    // This is not a typo, we need the proId in order to call savePro()
    $this->proId = $this->savePro();
  }

  public function prev(Posts $posts) {
    parent::prev($posts);
    $this->proId = intval(get_post_meta($this->id, 'quiz_pro_id', true));
    $this->quizType = get_post_meta($this->id, 'quiz_type', true) ?: 'base';
  }

  public function updateMeta(Data $data, Posts $posts) {
    if ($posts->course?->exists()) {
      $parent = $posts->course;

      if ($posts->topic?->exists()) {
        $parent = $posts->topic;
      } elseif ($posts->lesson?->exists()) {
        $parent = $posts->lesson;
      }

      $courseId = $posts->course->id;

      $res = learndash_course_add_child_to_parent($courseId, $this->id, $parent->id);
      if (!$res) {
        throw new Exception(sprintf(__('Cannot link quiz %s to %s %s'), $this->id, $parent->type, $parent->id));
      }
    }

    if ($posts->question?->exists()) {
      $questionId = $posts->question->id;
      $questionProId = $posts->question->getProId();

      if ($questionProId) {
        $questions = get_post_meta($this->id, 'ld_quiz_questions', true);
        if (!is_array($questions)) {
          $questions = [];
        }

        if (($questions[$questionId] ?? null) !== $questionProId) {
          $questions[$questionId] = $questionProId;
          update_post_meta($this->id, 'ld_quiz_questions', $questions);
        }
      }

      $oldQuizId = get_post_meta($questionId, 'quiz_id', true);
      if ($oldQuizId && $oldQuizId !== $this->id) {
        $questions = get_post_meta($oldQuizId, 'ld_quiz_questions', true);

        if (is_array($questions) && isset($questions[$questionId])) {
          unset($questions[$questionId]);
          update_post_meta($oldQuizId, 'ld_quiz_questions', $questions);
        }
      }
    }

    $affixes = $data->quizAffixes();
    if (!empty($affixes)) {
      QuizAffix::saveAffixes($this->id, $affixes);
    }

    // $values = $data->quizMeta();
    // if (!empty($values)) {
    //   if (isset($values['quiz_type'])) {
    //     update_post_meta($this->id, 'quiz_type', $values['quiz_type']);
    //   }
    // }
    update_post_meta($this->id, 'quiz_type', $this->quizType);
  }

  protected function savePro(): int {
    $proFields = [];
    switch ($this->quizType) {
      case 'reading':
      case 'writing':
      case 'listening':
      case 'speaking':
        $proFields = [
          'hideResultCorrectQuestion' => true,
          'hideResultQuizTime' => true,
        ];
        break;
      case 'base':
        break;
    }

    $proQuiz = new WpProQuiz_Model_Quiz(
      array_merge(
        [
          'id' => $this->proId,
          'name' => $this->title,
          'showMaxQuestionValue' => 0,
          'toplistDataAddBlock' => 0,
          'toplistDataShowLimit' => 0,
          'quizModus' => 2,
          'autostart' => true,
        ],
        $proFields,
      ),
    );

    $mapper = new WpProQuiz_Model_QuizMapper();
    $proQuiz = $mapper->save($proQuiz);
    if ($proQuiz === null) {
      global $wpdb;

      throw new Exception(
        sprintf(__('Error creating proQuiz: %s', 'extended-learndash-bulk-create'), $wpdb->last_error),
      );
    }

    return $proQuiz->getId();
  }

  public function getProId(): int {
    return $this->proId;
  }
}
