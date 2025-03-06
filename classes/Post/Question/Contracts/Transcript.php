<?php

namespace TSTPrep\LDImporter\Post\Question\Contracts;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

interface Transcript {
  public function updateTranscriptMeta(Data $data, Posts $posts);

  public function getTranscriptSettings(): array;
}
