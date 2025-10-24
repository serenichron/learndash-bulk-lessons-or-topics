<?php

namespace TSTPrep\LDImporter;

use League\Csv\Writer;

class TemplateWriter {
  private Writer $writer;

  private array $data = [];

  private array $columns = [];

  public function __construct(array $includedColumns) {
    foreach (['group', 'course', 'lesson', 'topic', 'quiz'] as $type) {
      $included = isset($includedColumns[$type]);
      if ($included && $includedColumns[$type] === 'full') {
        $this->columns[] = $type . '_id';

        if ($type !== 'group') {
          $this->columns[] = $type . '_post_title';
          $this->columns[] = $type . '_post_content';
        }

        if ($type === 'quiz') {
          $this->columns[] = $type . '_affixes';
        }
      } elseif ($included || !empty($this->columns)) {
        $this->columns[] = $type . '_id';
      }
    }

    $this->columns[] = 'question_id';
    $this->columns[] = 'question_post_title';
    $this->columns[] = 'question_post_content';
    $this->columns[] = 'question_type';
    $this->columns[] = 'question_answers';
    $this->columns[] = 'question_meta';
    $this->columns[] = 'question_affixes';

    $this->writer = Writer::createFromString('');
    $this->writer->insertOne($this->columns);
  }

  public function group(int|string|null $id = null) {
    $this->data['group_id'] = $id;
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

  public function quizAffixes($affixes) {
    $this->data['quiz_affixes'] = json_encode($affixes);
  }

  public function question(int|string|null $id = null, ?string $title = null, ?string $content = null) {
    $this->set('question', $id, $title, $content);
  }

  public function questionType(string $type) {
    $this->data['question_type'] = $type;
  }

  public function questionAnswers($answers) {
    $this->data['question_answers'] = json_encode($answers);
  }

  public function questionMeta($meta) {
    $this->data['question_meta'] = json_encode($meta);
  }

  public function questionAffixes($affixes) {
    $this->data['question_affixes'] = json_encode($affixes);
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

    $this->writer->insertOne($row);
    $this->data = [];
  }

  public function download() {
    $this->writer->download('f.csv');
    die();
  }
}
