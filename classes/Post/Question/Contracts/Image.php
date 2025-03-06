<?php

namespace TSTPrep\LDImporter\Post\Question\Contracts;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;

interface Image {
  public function updateImageMeta(Data $data, Posts $posts);

  public function getImageSettings(): array;
}
