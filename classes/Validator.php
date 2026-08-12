<?php

namespace TSTPrep\LDImporter;

use TSTPrep\LDAdvancedQuizzes\Questions;

/**
 * Reads a sheet and reports what is wrong with it, without writing anything.
 *
 * Every push runs this first. If it finds a problem, nothing is written at
 * all, so a bad row can never leave you with half a quiz. The check button
 * in the spreadsheet is this class and nothing else.
 */
class Validator {
  private const LEVELS = ['course', 'lesson', 'topic', 'quiz', 'question'];

  private const POST_TYPES = [
    'group' => 'groups',
    'course' => 'sfwd-courses',
    'lesson' => 'sfwd-lessons',
    'topic' => 'sfwd-topic',
    'quiz' => 'sfwd-quiz',
    'question' => 'sfwd-question',
  ];

  private const JSON_COLUMNS = [
    'quiz_affixes',
    'quiz_meta',
    'quiz_pro_fields',
    'question_answers',
    'question_meta',
    'question_affixes',
    'question_pro_fields',
  ];

  private array $problems = [];
  private array $warnings = [];

  /** @var array<string, array<int, int>> quiz key => existing question ids named by the sheet */
  private array $sheetQuestions = [];

  /** @var array<int, int> quiz post id => how many questions the sheet gives it */
  private array $quizRowCounts = [];

  private array $summary = [
    'rows' => 0,
    'quizzes_new' => 0,
    'quizzes_existing' => 0,
    'questions_new' => 0,
    'questions_existing' => 0,
  ];

  /**
   * @param array<int|string, array<string, mixed>> $rows keyed by the row label the user sees
   * @return array{problems: array, warnings: array, summary: array}
   */
  public function validate(array $rows): array {
    if (empty($rows)) {
      $this->problem(null, null, __('The sheet has no rows in it.', 'extended-learndash-bulk-create'));
      return $this->result();
    }

    $this->checkColumns($rows);

    // What each level resolved to on the previous row. PREV reads from here,
    // and a PREV pointing at a blank is a silent no-op in the importer, so it
    // is worth catching before anything is written.
    $live = array_fill_keys(self::LEVELS, null);

    foreach ($rows as $label => $row) {
      $this->summary['rows']++;
      $resolved = array_fill_keys(self::LEVELS, null);

      foreach (self::LEVELS as $level) {
        $resolved[$level] = $this->checkLevel($label, $level, $row, $live);
      }

      $this->checkGroup($label, $row);
      $this->checkJson($label, $row);

      if ($resolved['question'] !== null) {
        $this->checkQuestion($label, $row, $resolved);
      }

      $live = $resolved;
    }

    $this->checkQuizzesForDroppedQuestions();

    return $this->result();
  }

  /**
   * Is this an upload sheet at all, or has someone pointed us at their notes.
   */
  private function checkColumns(array $rows): void {
    $headers = array_keys(reset($rows));
    $wanted = ['quiz_id', 'question_id'];
    $found = array_intersect($wanted, $headers);

    if (empty($found)) {
      $this->problem(
        null,
        null,
        __(
          'This does not look like an upload sheet. It has no quiz_id or question_id column.',
          'extended-learndash-bulk-create',
        ),
      );
    }
  }

  /**
   * Work out what one level of one row points at.
   *
   * @return int|string|null an existing post id, the string 'new', or null
   */
  private function checkLevel($label, string $level, array $row, array $live) {
    $raw = $this->cell($row, $level . '_id');

    if ($raw === null) {
      return null;
    }

    if ($raw === 'CREATE') {
      $this->countNew($level);
      return 'new';
    }

    if ($raw === 'PREV') {
      if ($level === 'question') {
        $this->problem(
          $label,
          'question_id',
          __('PREV is not allowed on question_id. Every question needs its own row.', 'extended-learndash-bulk-create'),
        );
        return null;
      }

      if ($live[$level] === null) {
        $this->problem(
          $label,
          $level . '_id',
          sprintf(
            __('PREV, but no earlier row set a %s. It would be skipped without warning.', 'extended-learndash-bulk-create'),
            $level,
          ),
        );
        return null;
      }

      if ($level === 'quiz' && is_int($live['quiz'])) {
        $this->quizRowCounts[$live['quiz']] = ($this->quizRowCounts[$live['quiz']] ?? 0) + 0;
      }

      return $live[$level];
    }

    if (!ctype_digit((string) $raw)) {
      $this->problem(
        $label,
        $level . '_id',
        sprintf(
          __('"%s" is not something we understand. Use a number, CREATE, PREV, or leave it empty.', 'extended-learndash-bulk-create'),
          $raw,
        ),
      );
      return null;
    }

    $id = (int) $raw;
    $actual = function_exists('get_post_type') ? get_post_type($id) : self::POST_TYPES[$level];

    if (!$actual) {
      $this->problem(
        $label,
        $level . '_id',
        sprintf(__('There is no post with id %d on this site.', 'extended-learndash-bulk-create'), $id),
      );
      return null;
    }

    if ($actual !== self::POST_TYPES[$level]) {
      $this->problem(
        $label,
        $level . '_id',
        sprintf(
          __('Post %d is a %s, not a %s.', 'extended-learndash-bulk-create'),
          $id,
          $actual,
          self::POST_TYPES[$level],
        ),
      );
      return null;
    }

    $this->countExisting($level);

    return $id;
  }

  private function checkGroup($label, array $row): void {
    $raw = $this->cell($row, 'group_id');

    if ($raw === null) {
      return;
    }

    if (!ctype_digit((string) $raw)) {
      $this->problem(
        $label,
        'group_id',
        sprintf(
          __('"%s" is not a group id. CREATE and PREV do not work on this column.', 'extended-learndash-bulk-create'),
          $raw,
        ),
      );
    }
  }

  private function checkJson($label, array $row): void {
    foreach (self::JSON_COLUMNS as $column) {
      $value = $row[$column] ?? null;

      // Already structured, which is how the spreadsheet sends it.
      if (is_array($value) || $value === null) {
        continue;
      }

      $value = trim((string) $value);
      if ($value === '') {
        continue;
      }

      json_decode($value, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->problem($label, $column, json_last_error_msg());
      }
    }
  }

  private function checkQuestion($label, array $row, array $resolved): void {
    if ($resolved['quiz'] === null) {
      $this->problem(
        $label,
        'quiz_id',
        __('This row has a question but no quiz to put it in.', 'extended-learndash-bulk-create'),
      );
    }

    $type = $this->cell($row, 'question_type');

    if ($type === null) {
      $this->problem(
        $label,
        'question_type',
        __('A question needs a question_type.', 'extended-learndash-bulk-create'),
      );
      return;
    }

    if (!class_exists(Questions::class)) {
      $this->problem(
        $label,
        'question_type',
        __('The advanced quizzes plugin is not active, so question types cannot be checked.', 'extended-learndash-bulk-create'),
      );
      return;
    }

    if (!Questions::getQuestion($type)) {
      $this->problem(
        $label,
        'question_type',
        sprintf(__('There is no question type called "%s".', 'extended-learndash-bulk-create'), $type),
      );
      return;
    }

    if (is_int($resolved['quiz'])) {
      $this->quizRowCounts[$resolved['quiz']] = ($this->quizRowCounts[$resolved['quiz']] ?? 0) + 1;

      if ($resolved['question'] !== 'new' && is_int($resolved['question'])) {
        $this->sheetQuestions[$resolved['quiz']][] = $resolved['question'];
      }
    }
  }

  /**
   * Warn about questions sitting in a quiz that the sheet no longer mentions.
   */
  private function checkQuizzesForDroppedQuestions(): void {
    if (!function_exists('get_post_meta')) {
      return;
    }

    foreach (array_keys($this->quizRowCounts) as $quizId) {
      $existing = get_post_meta($quizId, 'ld_quiz_questions', true);
      if (!is_array($existing) || empty($existing)) {
        continue;
      }

      $named = $this->sheetQuestions[$quizId] ?? [];
      $dropped = array_diff(array_map('intval', array_keys($existing)), $named);

      foreach ($dropped as $questionId) {
        $this->warnings[] = [
          'row' => null,
          'column' => null,
          'message' => sprintf(
            __(
              'Question %1$d is in quiz %2$d on the site but not in your sheet. It stays in WordPress, and is only taken out of the quiz if you ask for that.',
              'extended-learndash-bulk-create',
            ),
            $questionId,
            $quizId,
          ),
          'quiz_id' => $quizId,
          'question_id' => $questionId,
        ];
      }
    }
  }

  private function countNew(string $level): void {
    if ($level === 'quiz') {
      $this->summary['quizzes_new']++;
    } elseif ($level === 'question') {
      $this->summary['questions_new']++;
    }
  }

  private function countExisting(string $level): void {
    if ($level === 'quiz') {
      $this->summary['quizzes_existing']++;
    } elseif ($level === 'question') {
      $this->summary['questions_existing']++;
    }
  }

  private function cell(array $row, string $key): ?string {
    $value = $row[$key] ?? null;

    if ($value === null || is_array($value)) {
      return null;
    }

    $value = trim((string) $value);

    return $value === '' ? null : $value;
  }

  private function problem($row, ?string $column, string $message): void {
    $this->problems[] = [
      'row' => $row,
      'column' => $column,
      'message' => $message,
    ];
  }

  private function result(): array {
    return [
      'problems' => $this->problems,
      'warnings' => $this->warnings,
      'summary' => $this->summary,
    ];
  }
}
