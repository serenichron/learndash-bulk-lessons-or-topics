<?php

namespace TSTPrep\LDImporter;

use Exception;
use Extended_LearnDash_Bulk_Create;

class Data {
  private Extended_LearnDash_Bulk_Create $plugin;
  private array $data;
  private $index;

  public function __construct(array $data, $index, Extended_LearnDash_Bulk_Create $plugin) {
    $this->data = $data;
    $this->index = $index;
    $this->plugin = $plugin;
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

  private function getJsonValue(string $key) {
    $answers = $this->getValue($key);
    if ($answers !== null) {
      $answers = json_decode($answers, true);

      if ($answers === null) {
        $m1 = 'Error decoding row ' . $this->index . ', column ' . $key;
        $m2 = json_last_error_msg();
        error_log('[IMPORT] ' . $m1);
        error_log('[IMPORT] ' . $m2);
        $this->plugin->errorMessages[] = $m1 . '. ' . $m2;
      }
    }

    return $answers;
  }

  public function setId(string $type, ?int $id) {
    $this->data[$type . '_id'] = $id;
  }
}
