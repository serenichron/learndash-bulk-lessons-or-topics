<?php

namespace TSTPrep\LDImporter\Post\Question;

use Exception;
use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuestionAffix;
use TSTPrep\LDAdvancedQuizzes\Questions;
use TSTPrep\LDAdvancedQuizzes\RegisteredQuestion;
use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Post;
use TSTPrep\LDImporter\Post\Posts;
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
  protected RegisteredQuestion $registered;

  protected function setProps(Data $data) {
    parent::setProps($data);
    $this->questionType = $data->questionType() ?? '';
    $this->customFields = [$data->questionField(1), $data->questionField(2)];
    $this->correct = $this->customFields[0];

    $this->registered = Questions::getQuestion($this->questionType);
    $answers = $this->registered->getAnswerFields()->formatAnswers($data->questionAnswers());
    if (array_is_list($answers)) {
      $this->answerData = $answers;
    } else {
      $this->answerData = $answers['answerData'];
    }
  }

  public function create(Posts $posts) {
    parent::create($posts);

    $this->quizQuestionId = $this->savePro($posts, 0);
    update_post_meta($this->id, 'question_pro_id', $this->quizQuestionId);
    update_post_meta($this->id, 'question_points', 1);
  }

  public function update(Posts $posts) {
    parent::update($posts);

    $proId = intval(get_post_meta($this->id, 'question_pro_id', true));
    $this->quizQuestionId = $this->savePro($posts, $proId);
    if ($this->quizQuestionId !== $proId) {
      update_post_meta($this->id, 'question_pro_id', $this->quizQuestionId);
    }
  }

  public function prev(Posts $posts) {
    throw new Exception(__('Questions don\'t support prev', 'extended-learndash-bulk-create'));
  }

  public function updateMeta(Data $data, Posts $posts) {
    update_post_meta($this->id, 'question_type', $this->questionType);

    if ($posts->quiz?->exists()) {
      update_post_meta($this->id, 'quiz_id', $posts->quiz->id);
      update_post_meta($this->id, 'ld_quiz_id', $posts->quiz->getProId());
      update_post_meta($this->id, '_sfwd-question', ['sfwd-question_quiz' => (string) $posts->quiz->id]);
      $questions = get_post_meta($posts->quiz->id, 'ld_quiz_questions', true);

      if (is_array($questions)) {
        $keys = array_keys($questions);
        $index = array_search($this->id, $keys);
        if ($index === false) {
          $index = 0;
        }

        remove_action('post_updated', 'wp_save_post_revision');
        wp_update_post(['ID' => $this->id, 'menu_order' => $index]);
        add_action('post_updated', 'wp_save_post_revision');
      }
    }

    $values = $data->questionMeta();
    if (!empty($values)) {
      $fields = $this->registered->getMetaFields();
      foreach ($fields as $key => $field) {
        if (!isset($values[$key])) {
          continue;
        }

        $field->saveValue($this->id, $values[$key]);
      }
    }

    $affixes = $data->questionAffixes();
    if (!empty($affixes)) {
      QuestionAffix::saveAffixes($this->id, $affixes);
    }
  }

  protected function savePro(Posts $posts, int $proId): int {
    $question = new WpProQuiz_Model_Question([
      'id' => $proId,
      'questionPostId' => $this->id,
      'quizId' => $posts->quiz->getProId(),
      'sort' => 1,
      'title' => $this->title,
      'question' => $this->content,
      'answerType' => $this->questionType,
      'answerData' => $this->answerData,
      // 'correctSameText' => $this->correctSameAsText,
      // 'correctMsg'                     => $this->getCorrectMsg(),
      // 'incorrectMsg'                   => $this->getIncorrectMsg(),
      // 'correctSameText'                => $this->isCorrectSameText(),
      // 'points'                         => $this->getPoints(),
      // 'showPointsInBox'                => $this->isShowPointsInBox(),
      // 'answerPointsActivated'          => $this->isAnswerPointsActivated(),
      // 'answerPointsDiffModusActivated' => $this->isAnswerPointsDiffModusActivated(),
      // 'disableCorrect'                 => $this->isDisableCorrect(),
      // 'matrixSortAnswerCriteriaWidth'  => $this->getMatrixSortAnswerCriteriaWidth(),
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
