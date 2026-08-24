<?php
/**
 * Plugin Name: LearnDash Bulk Lessons Or Topics
 * Description: Adds functionality to bulk create Courses, Lessons, or Topics in LearnDash using a CSV file.
 * Version: 1.5.0
 * Author: Vlad Tudorie
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learndash-bulk-lessons-or-topics
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit();
}

define('ELDBC_VERSION', '1.5.0');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

use League\Csv\Reader;
use TSTPrep\LDImporter\AdminOrder;
use TSTPrep\LDImporter\Api;
use TSTPrep\LDImporter\ApiKeys;
use TSTPrep\LDImporter\Exporter;
use TSTPrep\LDImporter\Importer;

class Extended_LearnDash_Bulk_Create {
  public $errorMessages = [];
  public $notices = [];

  public function __construct() {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'handle_form_submission']);
    add_action('wp_ajax_ld_import_gen_template', [$this, 'gen_template']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    add_action('admin_post_ldbc_create_key', [$this, 'handle_create_key']);
    add_action('admin_post_ldbc_revoke_key', [$this, 'handle_revoke_key']);

    (new Api())->register();
    (new AdminOrder())->register();
  }

  public function handle_create_key() {
    check_admin_referer('ldbc_create_key');

    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to perform this action.', 'extended-learndash-bulk-create'));
    }

    $key = ApiKeys::create(sanitize_text_field(wp_unslash($_POST['key_name'] ?? '')));

    // The key is readable once and only once. Hand it over through a
    // short lived transient rather than the address bar, which would put it
    // in browser history and server logs.
    set_transient('ldbc_new_key_' . get_current_user_id(), $key, 5 * MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('admin.php?page=extended-learndash-bulk-create#keys'));
    exit();
  }

  public function handle_revoke_key() {
    check_admin_referer('ldbc_revoke_key');

    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to perform this action.', 'extended-learndash-bulk-create'));
    }

    ApiKeys::revoke(sanitize_text_field(wp_unslash($_POST['key_id'] ?? '')));

    wp_safe_redirect(admin_url('admin.php?page=extended-learndash-bulk-create#keys'));
    exit();
  }

  public function add_admin_menu() {
    add_submenu_page(
      'learndash-lms',
      __('Bulk Create/Update', 'extended-learndash-bulk-create'),
      __('Bulk Create/Update', 'extended-learndash-bulk-create'),
      'manage_options',
      'extended-learndash-bulk-create',
      [$this, 'admin_page'],
    );
  }

  public function enqueue_admin_scripts($hook) {
    if ('learndash-lms_page_extended-learndash-bulk-create' !== $hook) {
      return;
    }
    wp_enqueue_script(
      'extended-learndash-bulk-create',
      plugin_dir_url(__FILE__) . 'js/admin.js',
      ['jquery'],
      ELDBC_VERSION,
      true,
    );
    wp_localize_script('extended-learndash-bulk-create', 'eldbc_ajax', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('eldbc_ajax_nonce'),
    ]);
  }

  public function admin_page() {
    include __DIR__ . '/templates/admin-page.php';
  }

  public function gen_template() {
    Exporter::export();
    die();
  }

  public function handle_form_submission() {
    if (
      !isset($_POST['extended_learndash_bulk_create_nonce']) ||
      !isset($_POST['submit']) ||
      !check_admin_referer('extended_learndash_bulk_create', 'extended_learndash_bulk_create_nonce')
    ) {
      return;
    }

    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.', 'extended-learndash-bulk-create'));
    }

    $action_type = sanitize_key($_POST['action_type'] ?? '');

    if ($action_type === 'update' && (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK)) {
      wp_die(__('CSV file upload failed. Please try again.', 'extended-learndash-bulk-create'));
    }

    if ($action_type === 'delete') {
      $this->delete_quizzes(explode(',', (string) ($_POST['quizId'] ?? '')));
      return;
    }

    if ($action_type !== 'update') {
      return;
    }

    $checkOnly = !empty($_POST['check_only']);

    try {
      // Read the whole file before anything on the site is touched. The
      // delete below used to run first, so a file that failed to parse cost
      // you the quiz and gave you nothing back.
      $reader = Reader::createFromPath($_FILES['csv_file']['tmp_name']);
      $reader->setHeaderOffset(0);
      $rows = $this->label_rows($reader->getRecords());
    } catch (Exception $e) {
      error_log($e);
      $this->errorMessages[] = $e->getMessage();
      return;
    }

    $importer = new Importer();

    if ($checkOnly) {
      $this->report($importer->check($rows), true);
      return;
    }

    // The check inside push() runs before anything is written, so a sheet
    // with a problem in it never gets as far as this delete.
    $preview = $importer->check($rows);
    if (!$preview['ok']) {
      $this->report($preview, true);
      return;
    }

    $this->delete_quizzes(explode(',', (string) ($_POST['quizId'] ?? '')));

    $this->report($importer->push($rows, !empty($_POST['detach_missing'])), false);
  }

  /**
   * Key the rows by the row number the user sees in their file.
   * The header sits at reader offset 0, so the first row of data is row 2.
   *
   * @param iterable<mixed, array<string, mixed>> $records
   * @return array<int|string, array<string, mixed>>
   */
  private function label_rows(iterable $records): array {
    $rows = [];

    foreach ($records as $index => $record) {
      $label = is_int($index) ? $index + 1 : $index;
      $rows[$label] = $record;
    }

    return $rows;
  }

  private function report(array $report, bool $checkOnly): void {
    foreach ($report['problems'] as $problem) {
      $this->errorMessages[] = $this->format_error($problem);
    }

    foreach ($report['warnings'] as $warning) {
      $this->notices[] = $this->format_error($warning);
    }

    $summary = $report['summary'];

    if (!$report['ok']) {
      $this->notices[] = $report['written']
        ? __('The import stopped part way. Nothing below the failed row was written.', 'extended-learndash-bulk-create')
        : __('Nothing was written. Fix the problems above and try again.', 'extended-learndash-bulk-create');
      return;
    }

    if ($checkOnly) {
      $this->notices[] = sprintf(
        __(
          'Checked %1$d rows and found no problems. This would make %2$d quizzes and %3$d questions, and update %4$d quizzes and %5$d questions.',
          'extended-learndash-bulk-create',
        ),
        $summary['rows'],
        $summary['quizzes_new'],
        $summary['questions_new'],
        $summary['quizzes_existing'],
        $summary['questions_existing'],
      );
      return;
    }

    $this->notices[] = sprintf(
      __(
        'Done. %1$d rows processed. Made %2$d quizzes and %3$d questions, updated %4$d quizzes and %5$d questions, took %6$d questions out of their quiz.',
        'extended-learndash-bulk-create',
      ),
      $summary['rows'],
      $summary['quizzes_new'],
      $summary['questions_new'],
      $summary['quizzes_existing'],
      $summary['questions_existing'],
      $summary['questions_detached'] ?? 0,
    );
  }

  private function format_error(array $error): string {
    $where = [];

    if (($error['row'] ?? null) !== null) {
      $where[] = sprintf(__('row %s', 'extended-learndash-bulk-create'), $error['row']);
    }

    if (($error['column'] ?? null) !== null) {
      $where[] = sprintf(__('column %s', 'extended-learndash-bulk-create'), $error['column']);
    }

    if (empty($where)) {
      return $error['message'];
    }

    return implode(', ', $where) . ': ' . $error['message'];
  }

  /**
   * Permanently remove quizzes and every question attached to them.
   *
   * Only reachable from the admin form. The REST route never deletes.
   *
   * @param array<int, mixed> $ids
   */
  private function delete_quizzes(array $ids): int {
    $deleted = 0;

    foreach ($ids as $id) {
      $id = absint(trim((string) $id));
      if (!$id) {
        continue;
      }

      $questions = get_post_meta($id, 'ld_quiz_questions', true);
      if (!is_array($questions)) {
        continue;
      }

      foreach (array_keys($questions) as $questionId) {
        wp_delete_post($questionId, true);
      }

      wp_delete_post($id, true);
      $deleted++;
    }

    return $deleted;
  }
}

// Activation hook
function extended_learndash_bulk_create_activate() {
  // Check if LearnDash is active
  if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
  }

  if (!is_plugin_active('sfwd-lms/sfwd_lms.php')) {
    deactivate_plugins(plugin_basename(__FILE__));
    wp_die(
      __('Please install and activate LearnDash before activating this plugin.', 'extended-learndash-bulk-create'),
      'Plugin dependency check',
      ['back_link' => true],
    );
  }

}
register_activation_hook(__FILE__, 'extended_learndash_bulk_create_activate');

// Initialize the plugin
$extended_learndash_bulk_create = new Extended_LearnDash_Bulk_Create();
