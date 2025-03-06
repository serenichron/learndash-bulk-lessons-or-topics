<?php

namespace TSTPrep\LDImporter;

use Exception;

class Data {
  private array $data;

  public function __construct(array $data) {
    $this->data = $data;
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

  public function questionType(): ?string {
    return $this->getValue('question_type');
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

  public function setId(string $type, ?int $id) {
    $this->data[$type . '_id'] = $id;
  }
}
