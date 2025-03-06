<?php

namespace TSTPrep\LDImporter;

class TemplateWriter {
  private $file;

  private array $data = [];

  private array $columns = [
    'group_id',
    'course_id',
    'course_post_title',
    'course_post_content',
    'lesson_id',
    'lesson_post_title',
    'lesson_post_content',
    'topic_id',
    'topic_post_title',
    'topic_post_content',
    'quiz_id',
    'quiz_post_title',
    'quiz_post_content',
    'question_id',
    'question_post_title',
    'question_post_content',
    'question_type',
    'question_field_1',
    'question_field_2',
  ];

  public function __construct(string $path) {
    $this->file = fopen($path, 'w');
    fputcsv($this->file, $this->columns);
  }

  public function course(int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->set('course', $id, $title, $content);
  }

  public function lesson(int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->set('lesson', $id, $title, $content);
  }

  public function topic(int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->set('topic', $id, $title, $content);
  }

  public function quiz(int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->set('quiz', $id, $title, $content);
  }

  public function question(int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->set('question', $id, $title, $content);
  }

  public function questionType(string $type) {
    $this->data['question_type'] = $type;
  }

  public function questionFields(?string $field1 = null, ?string $field2 = null) {
    $this->data['question_field_1'] = $field1;
    $this->data['question_field_2'] = $field2;
  }

  public function set(string $post, int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->data[$post . '_id'] = $id;
    $this->data[$post . '_post_title'] = $title;
    $this->data[$post . '_post_content'] = $content;
  }

  public function flush() {
    $row = [];

    foreach ($this->columns as $key) {
      $row[] = $this->data[$key] ?? '';
    }

    fputcsv($this->file, $row);
    $this->data = [];
  }

  public function __destruct() {
    fclose($this->file);
  }
}
