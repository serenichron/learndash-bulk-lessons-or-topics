<?php

namespace TSTPrep\LDImporter\Post;

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Question\CompleteThePassageQuestion;
use TSTPrep\LDImporter\Post\Question\CompleteTheSentencesQuestion;
use TSTPrep\LDImporter\Post\Question\FillTheBlanksQuestion;
use TSTPrep\LDImporter\Post\Question\ImproveSpeakingResponsesQuestion;
use TSTPrep\LDImporter\Post\Question\ListenAndTypeQuestion;
use TSTPrep\LDImporter\Post\Question\ListenThenSpeakQuestion;
use TSTPrep\LDImporter\Post\Question\Question;
use TSTPrep\LDImporter\Post\Question\ReadAloudQuestion;
use TSTPrep\LDImporter\Post\Question\ReadAndSelectQuestion;
use TSTPrep\LDImporter\Post\Question\ReadThenSpeakQuestion;
use TSTPrep\LDImporter\Post\Question\ReadThenWriteQuestion;
use TSTPrep\LDImporter\Post\Question\SpeakAboutThePhotoQuestion;
use TSTPrep\LDImporter\Post\Question\SpeakingSampleQuestion;
use TSTPrep\LDImporter\Post\Question\WriteAboutThePhotoQuestion;
use TSTPrep\LDImporter\Post\Question\WritingSampleQuestion;

class PostFactory {
  private array $classmap = [
    'course' => Course::class,
    'lesson' => Lesson::class,
    'topic' => Topic::class,
    'quiz' => Quiz::class,
    'question' => Question::class,
  ];

  private array $questionClassmap = [
    /* Individual Question: complete the passage */
    'ld_tstprep_complete_the_passage' => CompleteThePassageQuestion::class,
    'ld_tstprep_identify_the_idea' => CompleteThePassageQuestion::class,
    'ld_tstprep_title_the_passage' => CompleteThePassageQuestion::class,
    'ld_tstprep_interactive_listening_1' => CompleteThePassageQuestion::class,
    'ld_tstprep_interactive_listening_2' => CompleteThePassageQuestion::class,
    'ld_tstprep_interactive_listening_3' => CompleteThePassageQuestion::class,
    'ld_tstprep_interactive_listening_4' => CompleteThePassageQuestion::class,
    'ld_tstprep_interactive_listening_5' => CompleteThePassageQuestion::class,

    /* Individual Question: read then write */
    'ld_tstprep_read_then_write' => ReadThenWriteQuestion::class,
    'ld_tstprep_initial_question' => ReadThenWriteQuestion::class,
    'ld_tstprep_follow_up_question' => ReadThenWriteQuestion::class,

    /* Individual Question: fill in the blanks */
    'ld_tstprep_fill_in_the_blanks' => FillTheBlanksQuestion::class,
    'ld_tstprep_read_and_complete' => FillTheBlanksQuestion::class,

    /* Individual Questions */
    'ld_tstprep_complete_the_sentences' => CompleteTheSentencesQuestion::class,
    'ld_tstprep_improve_speaking_responses' => ImproveSpeakingResponsesQuestion::class,
    'ld_tstprep_listen_and_type' => ListenAndTypeQuestion::class,
    'ld_tstprep_listen_then_speak' => ListenThenSpeakQuestion::class,
    'ld_tstprep_read_aloud' => ReadAloudQuestion::class,
    'ld_tstprep_read_and_select' => ReadAndSelectQuestion::class,
    'ld_tstprep_read_then_speak' => ReadThenSpeakQuestion::class,
    'ld_tstprep_speak_about_the_photo' => SpeakAboutThePhotoQuestion::class,
    'ld_tstprep_speaking_sample' => SpeakingSampleQuestion::class,
    'ld_tstprep_write_about_the_photo' => WriteAboutThePhotoQuestion::class,
    'ld_tstprep_writing_sample' => WritingSampleQuestion::class,
  ];

  public function createOrUpdate(string $type, Data $data, Posts $posts): Post {
    $class = $this->classmap[$type];
    return $class::createOrUpdate($data, $posts);

    // $questionType = $data->questionType();
    // $class = $this->questionClassmap[$questionType] ?? Question::class;
    // return $class::createOrUpdate($data, $posts);
  }
}
