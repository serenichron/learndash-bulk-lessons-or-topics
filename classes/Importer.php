<?php

namespace TSTPrep\LDImporter;

use Throwable;
use TSTPrep\LDImporter\Post\Posts;

/**
 * The one way content gets built, whether it came from the admin page or the
 * spreadsheet.
 *
 * Two passes. check() reads the sheet and reports problems without touching
 * the site. push() runs the same check first and refuses to write a single
 * row unless the whole sheet is clean.
 */
class Importer {
  /**
   * Read the sheet and report. Writes nothing.
   *
   * @param array<int|string, array<string, mixed>> $rows keyed by row label
   */
  public function check(array $rows): array {
    return $this->execute($rows, false, false);
  }

  /**
   * Check the sheet, then build it if the check passed.
   *
   * @param array<int|string, array<string, mixed>> $rows keyed by row label
   * @param bool $detachMissing take questions out of a quiz when the sheet no
   *                            longer lists them. They stay in WordPress.
   */
  public function push(array $rows, bool $detachMissing = false): array {
    return $this->execute($rows, true, $detachMissing);
  }

  private function execute(array $rows, bool $write, bool $detachMissing): array {
    $checked = (new Validator())->validate($rows);

    $report = [
      'ok' => empty($checked['problems']),
      'written' => false,
      'summary' => $checked['summary'],
      'problems' => $checked['problems'],
      'warnings' => $checked['warnings'],
      'rows' => [],
      'detached' => [],
    ];

    if (!$write || !$report['ok']) {
      return $report;
    }

    $context = new ImportContext();
    $oldPosts = null;
    $label = null;

    try {
      foreach ($rows as $label => $row) {
        $data = new Data($row, $label, $context);
        $posts = new Posts();
        $posts->createOrUpdate($data, $oldPosts);
        $posts->updateMeta($data);
        $oldPosts = $posts;

        $report['rows'][$label] = $data->resolvedIds();
      }

      $report['written'] = true;
    } catch (Throwable $e) {
      // Something only WordPress could have caught, at the moment it saved.
      // Say which row, and let the caller see which rows did land.
      error_log('[IMPORT] ' . $e);
      $report['ok'] = false;
      $report['written'] = true;
      $report['problems'][] = [
        'row' => $label,
        'column' => null,
        'message' => $e->getMessage(),
      ];
    }

    // Problems the row loop noticed on its way through, such as a value that
    // could not be read. These do not stop the run.
    foreach ($context->errors() as $error) {
      $report['problems'][] = $error;
      $report['ok'] = false;
    }

    $this->reorderQuizzes($context);

    if ($detachMissing) {
      $report['detached'] = $this->detachMissing($context);
    }

    $report['summary']['questions_detached'] = count($report['detached']);

    return $report;
  }

  /**
   * Take questions out of a quiz when the sheet no longer lists them.
   *
   * The question post itself is left alone, so nothing is lost. It simply
   * stops being part of the quiz.
   *
   * @return array<int, array{quiz_id: int, question_id: int}>
   */
  private function detachMissing(ImportContext $context): array {
    $detached = [];

    foreach ($context->quizIds() as $quizId) {
      $existing = get_post_meta($quizId, 'ld_quiz_questions', true);
      if (!is_array($existing) || empty($existing)) {
        continue;
      }

      $keep = $context->questionsFor($quizId);
      $changed = false;

      foreach (array_keys($existing) as $questionId) {
        if (in_array((int) $questionId, $keep, true)) {
          continue;
        }

        unset($existing[$questionId]);
        delete_post_meta($questionId, 'quiz_id');
        $changed = true;

        $detached[] = [
          'quiz_id' => (int) $quizId,
          'question_id' => (int) $questionId,
        ];
      }

      if ($changed) {
        update_post_meta($quizId, 'ld_quiz_questions', $existing);
      }
    }

    return $detached;
  }

  /**
   * Put the questions of every quiz we touched back in sheet order.
   *
   * LearnDash reads this list as well as the position on each post, so both
   * have to agree or a reordered sheet only half takes.
   */
  public function reorderQuizzes(ImportContext $context): void {
    foreach ($context->quizIds() as $quizId) {
      $existing = get_post_meta($quizId, 'ld_quiz_questions', true);
      if (!is_array($existing)) {
        continue;
      }

      $ordered = [];

      foreach ($context->questionsFor($quizId) as $questionId) {
        if (array_key_exists($questionId, $existing)) {
          $ordered[$questionId] = $existing[$questionId];
        }
      }

      // Anything the sheet did not mention keeps its place at the end.
      foreach ($existing as $questionId => $proId) {
        if (!array_key_exists($questionId, $ordered)) {
          $ordered[$questionId] = $proId;
        }
      }

      if ($ordered !== $existing) {
        update_post_meta($quizId, 'ld_quiz_questions', $ordered);
      }
    }
  }
}
