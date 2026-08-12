<?php

namespace TSTPrep\LDImporter;

/**
 * State shared by every row of a single import run.
 *
 * Two jobs. It collects problems so callers can report them, whether that
 * caller is the admin page or the REST route. And it counts questions per
 * quiz, which is what gives each question its position. Ordering used to
 * depend on the creation timestamp, which is only accurate to the second,
 * so importing without a pause between rows scrambled the order.
 */
class ImportContext {
  /** @var array<int, array{row: mixed, column: ?string, message: string}> */
  private array $errors = [];

  /** @var array<int|string, array<int, ?int>> quiz post id => position => question post id */
  private array $questionsByQuiz = [];

  private bool $dryRun = false;

  public function __construct(bool $dryRun = false) {
    $this->dryRun = $dryRun;
  }

  public function isDryRun(): bool {
    return $this->dryRun;
  }

  public function addError(string $message, $row = null, ?string $column = null): void {
    $this->errors[] = [
      'row' => $row,
      'column' => $column,
      'message' => $message,
    ];
  }

  /** @return array<int, array{row: mixed, column: ?string, message: string}> */
  public function errors(): array {
    return $this->errors;
  }

  public function hasErrors(): bool {
    return !empty($this->errors);
  }

  /**
   * Claim the next position for a question inside a quiz.
   *
   * Positions are zero based and follow sheet order, not the clock.
   */
  public function nextQuestionPosition($quizId): int {
    $this->questionsByQuiz[$quizId] ??= [];
    $this->questionsByQuiz[$quizId][] = null;

    return count($this->questionsByQuiz[$quizId]) - 1;
  }

  /**
   * Record which post ended up at a position, once the post exists.
   */
  public function recordQuestion($quizId, int $position, int $questionId): void {
    $this->questionsByQuiz[$quizId][$position] = $questionId;
  }

  /**
   * Question post ids written to a quiz during this run, in sheet order.
   *
   * @return array<int, int>
   */
  public function questionsFor($quizId): array {
    $questions = $this->questionsByQuiz[$quizId] ?? [];

    return array_values(array_filter($questions, static fn($id) => $id !== null));
  }

  /**
   * Every quiz this run touched.
   *
   * @return array<int, int|string>
   */
  public function quizIds(): array {
    return array_keys($this->questionsByQuiz);
  }
}
