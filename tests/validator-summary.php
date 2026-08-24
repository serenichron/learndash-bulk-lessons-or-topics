<?php

/**
 * Check that a push reports every level, not just quizzes and questions.
 *
 * WordPress is not loaded. Validator asks `function_exists` before it uses
 * anything from WordPress and falls back sensibly, so the counting can be
 * exercised on its own. Problems it raises about the missing question types
 * plugin are expected here and ignored.
 *
 * php tests/validator-summary.php
 */

function __($text, $domain = null) {
  return $text;
}

require_once __DIR__ . '/../classes/Validator.php';

use TSTPrep\LDImporter\Validator;

$failures = 0;

function ok(string $name, bool $condition, $extra = null): void {
  global $failures;

  if ($condition) {
    echo "  pass  $name\n";
    return;
  }

  $failures++;
  echo "  FAIL  $name" . ($extra === null ? '' : "\n        " . json_encode($extra)) . "\n";
}

function section(string $title): void {
  echo "\n$title\n";
}

section('Every level is counted, not just quizzes and questions');

$rows = [
  // A row that makes one of everything.
  2 => [
    'course_id' => 'CREATE',
    'lesson_id' => 'CREATE',
    'topic_id' => 'CREATE',
    'quiz_id' => 'CREATE',
    'question_id' => 'CREATE',
    'question_type' => 'single',
  ],
  // A row leaning on the one above for all but its own question.
  3 => [
    'course_id' => 'PREV',
    'lesson_id' => 'PREV',
    'topic_id' => 'PREV',
    'quiz_id' => 'PREV',
    'question_id' => 'CREATE',
    'question_type' => 'single',
  ],
  // A row updating things that already exist, with no topic at all.
  4 => [
    'course_id' => '11',
    'lesson_id' => '21',
    'topic_id' => '',
    'quiz_id' => '31',
    'question_id' => '41',
    'question_type' => 'single',
  ],
];

$summary = (new Validator())->validate($rows)['summary'];

ok('rows are counted', $summary['rows'] === 3, $summary['rows']);

ok('new courses are counted', ($summary['courses_new'] ?? null) === 1, $summary);
ok('new lessons are counted', ($summary['lessons_new'] ?? null) === 1, $summary);
ok('new topics are counted', ($summary['topics_new'] ?? null) === 1, $summary);
ok('new quizzes are counted', $summary['quizzes_new'] === 1, $summary);
ok('new questions are counted', $summary['questions_new'] === 2, $summary);

ok('updated courses are counted', ($summary['courses_existing'] ?? null) === 1, $summary);
ok('updated lessons are counted', ($summary['lessons_existing'] ?? null) === 1, $summary);
ok('updated quizzes are counted', $summary['quizzes_existing'] === 1, $summary);
ok('updated questions are counted', $summary['questions_existing'] === 1, $summary);

ok('a level no row uses stays at zero', ($summary['topics_existing'] ?? null) === 0, $summary);

section('PREV builds nothing of its own');

$prevOnly = [
  2 => ['course_id' => 'CREATE', 'quiz_id' => 'CREATE', 'question_id' => 'CREATE', 'question_type' => 'single'],
  3 => ['course_id' => 'PREV', 'quiz_id' => 'PREV', 'question_id' => 'CREATE', 'question_type' => 'single'],
  4 => ['course_id' => 'PREV', 'quiz_id' => 'PREV', 'question_id' => 'CREATE', 'question_type' => 'single'],
];

$summary = (new Validator())->validate($prevOnly)['summary'];

ok('one course for three rows', $summary['courses_new'] === 1, $summary);
ok('one quiz for three rows', $summary['quizzes_new'] === 1, $summary);
ok('three questions', $summary['questions_new'] === 3, $summary);
ok('nothing counts as updated', $summary['courses_existing'] === 0 && $summary['quizzes_existing'] === 0, $summary);

section('A sheet of nothing but courses says so');

$courses = [
  2 => ['course_id' => 'CREATE'],
  3 => ['course_id' => 'CREATE'],
];

$summary = (new Validator())->validate($courses)['summary'];

ok('two new courses', $summary['courses_new'] === 2, $summary);
ok('and no phantom quizzes', $summary['quizzes_new'] === 0 && $summary['questions_new'] === 0, $summary);

echo "\n" . ($failures ? "$failures FAILURES\n" : "all green\n");
exit($failures ? 1 : 0);
