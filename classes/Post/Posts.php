<?php

namespace TSTPrep\LDImporter\Post;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Question\Question;

class Posts {
  public ?self $prev = null;
  public ?Course $course = null;
  public ?Lesson $lesson = null;
  public ?Topic $topic = null;
  public ?Quiz $quiz = null;
  public ?Question $question = null;

  public function createOrUpdate(Data $data, ?self $prev) {
    $factory = new PostFactory();
    $this->prev = $prev;
    $this->course = $factory->createOrUpdate('course', $data, $this);
    $this->lesson = $factory->createOrUpdate('lesson', $data, $this);
    $this->topic = $factory->createOrUpdate('topic', $data, $this);
    $this->quiz = $factory->createOrUpdate('quiz', $data, $this);
    $this->question = $factory->createOrUpdate('question', $data, $this);
    $this->prev = null;
  }

  public function updateMeta(Data $data) {
    if ($this->course->exists()) {
      $this->course->updateMeta($data, $this);
    }

    if ($this->lesson->exists()) {
      $this->lesson->updateMeta($data, $this);
    }

    if ($this->topic->exists()) {
      $this->topic->updateMeta($data, $this);
    }

    if ($this->quiz->exists()) {
      $this->quiz->updateMeta($data, $this);
    }

    if ($this->question->exists()) {
      $this->question->updateMeta($data, $this);
    }
  }
}
