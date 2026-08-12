<?php

namespace TSTPrep\LDImporter;

use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuestionAffix;
use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuizAffix;
use TSTPrep\LDAdvancedQuizzes\CarbonFields\QuizFields;
use TSTPrep\LDAdvancedQuizzes\Questions;
use WP_Post;

/**
 * Build a starter CSV from content that already exists on the site.
 *
 * Useful for seeing the exact shape the importer expects, or for lifting an
 * existing quiz into a spreadsheet.
 */
class Exporter {
  public static function export() {
    if (!current_user_can('manage_options')) {
      wp_die(
        __('You do not have sufficient permissions to perform this action.', 'extended-learndash-bulk-create'),
        '',
        ['response' => 403],
      );
    }

    check_ajax_referer('eldbc_ajax_nonce', 'nonce');

    $id = absint($_POST['questionId'] ?? 0);
    $question = $id ? get_post($id) : null;

    if (!$question || $question->post_type !== 'sfwd-question') {
      wp_die(
        sprintf(__('There is no question with id %d on this site.', 'extended-learndash-bulk-create'), $id),
        '',
        ['response' => 404],
      );
    }

    $included = static::included(sanitize_key($_POST['include'] ?? 'question'));

    $writer = new TemplateWriter($included);
    static::writeFirstRow($writer, $question, $included);

    if (isset($_POST['siblings'])) {
      static::writeQuestionSiblings($writer, $question);
    }

    $writer->download();
  }

  /**
   * Which levels to include, given the deepest one the user picked.
   *
   * The cases fall through on purpose. Picking course means course and
   * everything under it, down to the question.
   *
   * @return array<string, string> level => 'full' or 'id'
   */
  private static function included(string $upTo): array {
    $included = [];

    switch ($upTo) {
      case 'group':
        $included['group'] = isset($_POST['include-group']) ? 'full' : 'id';
      // no break
      case 'course':
        $included['course'] = isset($_POST['include-course']) ? 'full' : 'id';
      // no break
      case 'lesson':
        $included['lesson'] = isset($_POST['include-lesson']) ? 'full' : 'id';
      // no break
      case 'topic':
        $included['topic'] = isset($_POST['include-topic']) ? 'full' : 'id';
      // no break
      case 'quiz':
        $included['quiz'] = isset($_POST['include-quiz']) ? 'full' : 'id';
    }

    return $included;
  }

  private static function writeFirstRow(TemplateWriter $writer, WP_Post $question, array $included) {
    static::writeQuestion($writer, $question);

    // This used to read a variable that was never passed in, so it was always
    // unset and the quiz columns never made it into the file.
    if (!isset($included['quiz'])) {
      $writer->flush();
      return;
    }

    $quizId = get_post_meta($question->ID, 'quiz_id', true);
    $quiz = $included['quiz'] === 'full' ? get_post($quizId) : null;

    if (!$quiz) {
      $writer->quiz($quizId);
      $writer->flush();
      return;
    }

    $writer->quiz($quizId, $quiz->post_title, $quiz->post_content);
    $writer->quizAffixes(QuizAffix::getAffixes($quiz->ID));
    $writer->quizMeta(static::quizMeta($quiz->ID));

    $writer->flush();
  }

  private static function writeQuestionSiblings(TemplateWriter $writer, WP_Post $question) {
    $quizId = get_post_meta($question->ID, 'quiz_id', true);
    $questions = get_post_meta($quizId, 'ld_quiz_questions', true);

    if (!is_array($questions)) {
      return;
    }

    unset($questions[$question->ID]);

    foreach (array_keys($questions) as $id) {
      $sibling = get_post($id);
      if (!$sibling) {
        continue;
      }

      static::writeQuestion($writer, $sibling);
      $writer->flush();
    }
  }

  private static function writeQuestion(TemplateWriter $writer, WP_Post $question) {
    $writer->question($question->ID, $question->post_title, $question->post_content);
    $type = get_post_meta($question->ID, 'question_type', true);
    $writer->questionType($type);

    $registered = Questions::getQuestion($type);
    if (!$registered) {
      return;
    }

    $writer->questionAnswers($registered->getAnswerFields()->load($question->ID));
    $writer->questionMeta(array_map(static fn($group) => $group->load($question->ID), $registered->getMetaFields()));
    $writer->questionAffixes(QuestionAffix::getAffixes($question->ID));
  }

  /**
   * @return array<string, mixed>
   */
  private static function quizMeta(int $quizId): array {
    if (!class_exists(QuizFields::class)) {
      return [];
    }

    $meta = [];

    foreach (QuizFields::allowedFields() as $key) {
      $value = get_post_meta($quizId, $key, true);
      if ($value !== '' && $value !== false) {
        $meta[$key] = $value;
      }
    }

    return $meta;
  }
}
