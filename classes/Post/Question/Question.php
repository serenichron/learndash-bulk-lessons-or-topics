<?php

namespace TSTPrep\LDImporter\Post\Question;

use Exception;
use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Post;
use TSTPrep\LDImporter\Post\Posts;
use TSTPrep\LDImporter\Post\Question\Contracts\Audio;
use TSTPrep\LDImporter\Post\Question\Contracts\Image;
use TSTPrep\LDImporter\Post\Question\Contracts\Transcript;
use TSTPrep\LDImporter\Post\Question\Contracts\WordCounter;
use WpProQuiz_Model_Question;
use WpProQuiz_Model_QuestionMapper;

class Question extends Post {
  protected string $type = 'question';
  protected string $wpType = 'sfwd-question';

  protected string $questionType = '';
  protected array $customFields = [];

  protected int $quizQuestionId = 0;
  protected int $correctSameAsText = 0;
  protected $correct = '';
  protected $answerData = [];

  protected function setProps(Data $data) {
    parent::setProps($data);
    $this->questionType = $data->questionType() ?? '';
    $this->customFields = [$data->questionField(1), $data->questionField(2)];
    $this->correct = $this->customFields[0];
  }

  public function create(Posts $posts) {
    parent::create($posts);

    if (!$posts->quiz?->exists() || !$posts->quiz->getProId()) {
      return;
    }

    $this->quizQuestionId = $this->savePro($posts);
    update_post_meta($this->id, 'question_pro_id', $this->quizQuestionId);
    update_post_meta($this->id, 'question_points', 1);
  }

  public function update(Posts $posts) {
    parent::update($posts);
    $this->quizQuestionId = intval(get_post_meta($this->id, 'question_pro_id', true));

    if ($posts->quiz?->exists() && $posts->quiz->getProId()) {
      $newId = $this->savePro($posts);
      if ($newId !== $this->quizQuestionId) {
        $this->quizQuestionId = $newId;
        update_post_meta($this->id, 'question_pro_id', $this->quizQuestionId);
      }
    }
  }

  public function prev(Posts $posts) {
    parent::prev($posts);
    $this->quizQuestionId = intval(get_post_meta($this->id, 'question_pro_id', true));
  }

  public function updateMeta(Data $data, Posts $posts) {
    if ($posts->quiz?->exists()) {
      update_post_meta($this->id, 'question_type', $this->questionType);
      update_post_meta($this->id, 'quiz_id', $posts->quiz->id);
      update_post_meta($this->id, 'ld_quiz_id', $posts->quiz->getProId());
      update_post_meta($this->id, '_sfwd-question', ['sfwd-question_quiz' => (string) $posts->quiz->id]);
    }

    if ($this instanceof Audio) {
      $this->updateAudioMeta($data, $posts);
    }

    if ($this instanceof Transcript) {
      $this->updateTranscriptMeta($data, $posts);
    }

    if ($this instanceof WordCounter) {
      $this->updateWordCounterMeta($data, $posts);
    }

    if ($this instanceof Image) {
      $this->updateImageMeta($data, $posts);
    }
  }

  protected function savePro(Posts $posts): int {
    $question = new WpProQuiz_Model_Question([
      'id' => $this->quizQuestionId,
      'quizId' => $posts->quiz->getProId(),
      'title' => $this->title,
      'question' => $this->content,
      'answerType' => $this->questionType,
      'sort' => 1,
      'correctSameText' => $this->correctSameAsText,
      'answerData' => $this->answerData,
    ]);

    $mapper = new WpProQuiz_Model_QuestionMapper();
    $question = $mapper->save($question);
    if ($question === null) {
      global $wpdb;

      throw new Exception(
        sprintf(__('Error creating proQuiz question: %s', 'extended-learndash-bulk-create'), $wpdb->last_error),
      );
    }

    return $question->getId();
  }

  public function getProId(): int {
    return $this->quizQuestionId;
  }
}
