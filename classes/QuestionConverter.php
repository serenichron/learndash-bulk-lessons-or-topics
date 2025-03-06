<?php

namespace TSTPrep\LDImporter;

use WP_Post;

class QuestionConverter {
  public function create(string $filePath): string {
    $upload = wp_upload_dir();
    $pathFragment = '/csvuploads/' . pathinfo($filePath, PATHINFO_FILENAME) . '-out.csv';
    $outPath = $upload['basedir'] . $pathFragment;
    $outUrl = $upload['baseurl'] . $pathFragment;

    $writer = new TemplateWriter($outPath);
    $file = fopen($filePath, 'r');

    // Remove headers
    fgetcsv($file);

    while (($row = fgetcsv($file)) !== false) {
      $quiz = $this->findQuiz($row[0]);
      $writer->quiz($quiz?->ID ?? 'CREATE', $row[0], $quiz?->post_content);

      $question = $this->findQuestion($row[2], $row[1]);
      $writer->question($question?->ID ?? 'CREATE', $row[2], $row[3]);
      $writer->questionType($row[1]);
      $writer->questionFields($row[4] ?? null, $row[5] ?? null);

      $writer->flush();
    }

    return $outUrl;
  }

  private function findQuiz(string $title): ?WP_Post {
    $args = [
      'title' => $title,
      'post_type' => 'sfwd-quiz',
    ];
    $quizes = get_posts($args);
    return $quizes[0] ?? null;
  }

  private function findQuestion(string $title, string $type): ?WP_Post {
    $args = [
      'title' => $title,
      'post_type' => 'sfwd-question',
      'meta_key' => 'question_type',
      'meta_value' => $type,
    ];
    $questions = get_posts($args);
    return $questions[0] ?? null;
  }
}
