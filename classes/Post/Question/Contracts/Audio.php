<?php

namespace TSTPrep\LDImporter\Post\Question\Contracts;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

interface Audio {
  public function updateAudioMeta(Data $data, Posts $posts);

  public function getAudioSettings(): array;
}
