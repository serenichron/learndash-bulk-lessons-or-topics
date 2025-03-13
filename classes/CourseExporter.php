<?php

namespace TSTPrep\LDImporter;

class CourseExporter {
  public static function export() {
    $ids = [1276646, 137441, 157448, 140627, 157976, 148764, 159860];
    $writer = new TemplateWriter(__DIR__ . '/course.csv');

    foreach ($ids as $id) {
      $course = get_post($id);
      $lessons = learndash_course_get_lessons($id);

      foreach ($lessons as $lesson) {
        $topics = learndash_course_get_topics($id, $lesson->ID);

        foreach ($topics as $topic) {
          $writer->course($id, $course->post_title, $course->post_content);
          $writer->lesson($lesson->ID, $lesson->post_title, $lesson->post_content);
          $writer->topic($topic->ID, $topic->post_title, $topic->post_content);
          $writer->flush();
        }
      }
    }
  }

  public static function export2() {
    $ids = array_map('str_getcsv', file(__DIR__ . '/quizzes.csv'));
    array_shift($ids);
    $writer = new TemplateWriter(__DIR__ . '/questions.csv');

    foreach ($ids as $id) {
      $id = intval($id[0]);
      $quiz = get_post($id);

      $questions = get_post_meta($id, 'ld_quiz_questions', true);
      $questions = is_array($questions) ? $questions : [];

      foreach ($questions as $questionId => $questionProId) {
        $question = get_post($questionId);
        $questionType = get_post_meta($questionId, 'question_type', true);

        $writer->quiz($id, $quiz->post_title, $quiz->post_content);
        $writer->question($questionId, $question->post_title, $question->post_content);
        $writer->questionType($questionType);
        $writer->flush();
      }
    }
  }
}
