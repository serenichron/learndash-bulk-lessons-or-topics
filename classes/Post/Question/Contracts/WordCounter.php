<?php

namespace TSTPrep\LDImporter\Post\Question\Contracts;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

interface WordCounter {
  public function updateWordCounterMeta(Data $data, Posts $posts);

  public function getWordCounterSettings(): array;
}
