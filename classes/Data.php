<?php

namespace TSTPrep\LDImporter;

use Exception;

class Data {
  private array $data;
  private $index;

  public function __construct(array $data, $index) {
    $this->data = $data;
    $this->index = $index;
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

  public function questionField(int $number): ?string {
    return $this->getValue('question_field_' . $number);
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

  private function getJsonValue(string $key) {
    $answers = $this->getValue($key);
    if ($answers !== null) {
      $answers = json_decode($answers, true);

      if ($answers === null) {
        error_log('[IMPORT] Error decoding row ' . $this->index . ', column ' . $key);
        error_log('[IMPORT] ' . json_last_error_msg());
      }
    }

    return $answers;
  }

  public function setId(string $type, ?int $id) {
    $this->data[$type . '_id'] = $id;
  }
}
