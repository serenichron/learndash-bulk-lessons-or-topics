<?php

namespace TSTPrep\LDImporter;

use Exception;

class Data {
  private ImportContext $context;
  private array $data;
  private $index;

  public function __construct(array $data, $index, ImportContext $context) {
    $this->data = $data;
    $this->index = $index;
    $this->context = $context;
  }

  public function context(): ImportContext {
    return $this->context;
  }

  /**
   * Row label used in error messages. The caller decides what it means, so
   * the REST route can pass the spreadsheet row number the user actually sees.
   */
  public function row() {
    return $this->index;
  }

  public function id(string $type, bool $includeSpecial = true): string|int|null {
    $rawId = $this->getValue($type . '_id');

    $allowed = $includeSpecial ? [null, 'CREATE', 'PREV'] : [null];
    if (in_array($rawId, $allowed, true)) {
      return $rawId;
    }

    $id = intval($rawId);
    if ($id !== 0) {
      return $id;
    }

    throw new Exception(
      sprintf(__('Unknown format for %s: %s', 'extended-learndash-bulk-create'), $type . '_id', $rawId),
    );
  }

  public function title(string $type): ?string {
    return $this->getValue($type . '_post_title');
  }

  public function content(string $type): ?string {
    return $this->getValue($type . '_post_content');
  }

  public function quizProFields() {
    return $this->getJsonValue('quiz_pro_fields') ?? [];
  }

  public function quizMeta() {
    return $this->getJsonValue('quiz_meta');
  }

  public function quizAffixes() {
    return $this->getJsonValue('quiz_affixes');
  }

  public function questionType(): ?string {
    return $this->getValue('question_type');
  }

  public function questionProFields() {
    return $this->getJsonValue('question_pro_fields') ?? [];
  }

  public function questionAnswers() {
    return $this->getJsonValue('question_answers') ?? [];
  }

  public function questionMeta() {
    return $this->getJsonValue('question_meta');
  }

  public function questionAffixes() {
    return $this->getJsonValue('question_affixes');
  }

  public function dump() {
    error_log('[IMPORT] [DUMP] ' . var_export($this->data, true));
  }

  private function getValue(string $key) {
    $value = $this->data[$key] ?? '';
    if (!is_string($value)) {
      return $value;
    }

    $value = trim($value);
    if ($value === '') {
      return null;
    }

    return $value;
  }

  /**
   * Read a column that holds structured data.
   *
   * A CSV upload delivers these as a JSON string. The REST route delivers
   * them already decoded, because there is no reason to serialise data just
   * to parse it again, and the quoting is where CSV goes wrong most often.
   */
  private function getJsonValue(string $key) {
    $raw = $this->data[$key] ?? null;
    if (is_array($raw)) {
      return $raw;
    }

    $value = $this->getValue($key);
    if ($value === null) {
      return null;
    }

    $decoded = json_decode($value, true);

    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
      $message = json_last_error_msg();
      error_log('[IMPORT] Error decoding row ' . $this->index . ', column ' . $key . '. ' . $message);
      $this->context->addError($message, $this->index, $key);
    }

    return $decoded;
  }

  public function setId(string $type, ?int $id) {
    $this->data[$type . '_id'] = $id;
  }

  /**
   * What each level of this row ended up pointing at, once the row has run.
   *
   * CREATE and PREV are replaced with real ids along the way, so this is what
   * the spreadsheet writes back into its own id cells.
   *
   * @return array<string, ?int>
   */
  public function resolvedIds(): array {
    $ids = [];

    foreach (['group', 'course', 'lesson', 'topic', 'quiz', 'question'] as $type) {
      $value = $this->data[$type . '_id'] ?? null;
      $ids[$type] = is_numeric($value) ? (int) $value : null;
    }

    return $ids;
  }
}
