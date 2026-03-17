<?php

namespace TSTPrep\LDImporter\Post;

use TSTPrep\LDImporter\Data;

class PostFactory {
  private array $classmap = [
    'course' => Course::class,
    'lesson' => Lesson::class,
    'topic' => Topic::class,
    'quiz' => Quiz::class,
    'question' => Question::class,
  ];

  public function createOrUpdate(string $type, Data $data, Posts $posts): Post {
    $class = $this->classmap[$type];
    return $class::createOrUpdate($data, $posts);
  }
}
