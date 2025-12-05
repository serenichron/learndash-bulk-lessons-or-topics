<?php

namespace TSTPrep\LDImporter;

use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuestionAffix;
use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuizAffix;
use TSTPrep\LDAdvancedQuizzes\Questions;
use WP_Post;

class Exporter {
  public static function export() {
    $id = $_POST['questionId'];
    $question = get_post($id);
    if (!$question) {
      return;
    }

    $included = [];
    switch ($_POST['include']) {
      case 'group':
        $included['group'] = isset($_POST['include-group']) ? 'full' : 'id';
      case 'course':
        $included['course'] = isset($_POST['include-course']) ? 'full' : 'id';
      case 'lesson':
        $included['lesson'] = isset($_POST['include-lesson']) ? 'full' : 'id';
      case 'topic':
        $included['topic'] = isset($_POST['include-topic']) ? 'full' : 'id';
      case 'quiz':
        $included['quiz'] = isset($_POST['include-quiz']) ? 'full' : 'id';
    }

    $writer = new TemplateWriter($included);
    static::writeFirstRow($writer, $question);

    if (isset($_POST['siblings'])) {
      static::writeQuestionSiblings($writer, $question);
    }

    $writer->download();
  }

  private static function writeFirstRow(TemplateWriter $writer, WP_Post $question) {
    static::writeQuestion($writer, $question);

    if (!isset($included['quiz'])) {
      $writer->flush();
      return;
    }

    $quizId = get_post_meta($question->ID, 'quiz_id', true);
    if ($included['quiz'] === 'full') {
      $quiz = get_post($quizId);
      if (!$quiz) {
        $writer->quiz($quizId);
        $writer->flush();
        $writer->download();
      }

      $writer->quiz($quizId, $quiz->post_title, $quiz->post_content);
      $writer->quizAffixes(QuizAffix::getAffixes($quiz->ID));
    } else {
      $writer->quiz($quizId);
    }

    // if (!isset($included['topic'])) {
    //   $writer->flush();
    //   return;
    // }

    $writer->flush();
  }

  private static function writeQuestionSiblings(TemplateWriter $writer, WP_Post $question) {
    $quizId = get_post_meta($question->ID, 'quiz_id', true);
    $questions = get_post_meta($quizId, 'ld_quiz_questions', true);
    unset($questions[$question->ID]);

    foreach ($questions as $id => $proId) {
      static::writeQuestion($writer, get_post($id));
      $writer->flush();
    }
  }

  private static function writeQuestion(TemplateWriter $writer, WP_Post $question) {
    $writer->question($question->ID, $question->post_title, $question->post_content);
    $type = get_post_meta($question->ID, 'question_type', true);
    $writer->questionType($type);

    $registered = Questions::getQuestion($type);
    $writer->questionAnswers($registered->getAnswerFields()->load($question->ID));
    $writer->questionMeta(array_map(static fn($group) => $group->load($question->ID), $registered->getMetaFields()));
    $writer->questionAffixes(QuestionAffix::getAffixes($question->ID));
  }
}
