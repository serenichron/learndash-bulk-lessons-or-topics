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
}
