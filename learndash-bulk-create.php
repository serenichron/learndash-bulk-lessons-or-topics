<?php
/**
 * Plugin Name: LearnDash Bulk Lessons Or Topics
 * Description: Adds functionality to bulk create Courses, Lessons, or Topics in LearnDash using a CSV file.
 * Version: 1.1.5
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

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}

use TSTPrep\LDImporter\Data;
use TSTPrep\LDImporter\Post\Posts;
use TSTPrep\LDImporter\QuestionConverter;

class Extended_LearnDash_Bulk_Create {
  private $supported_post_types = ['sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question'];
  private $changes = [];
  public $url = '';

  public function __construct() {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_init', [$this, 'handle_form_submission']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
      null,
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

  public function handle_form_submission() {
    if (
      !isset($_POST['submit']) ||
      !check_admin_referer('extended_learndash_bulk_create', 'extended_learndash_bulk_create_nonce')
    ) {
      return;
    }

    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to access this page.', 'extended-learndash-bulk-create'));
    }

    $action_type = $_POST['action_type'];

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
      wp_die(__('CSV file upload failed. Please try again.', 'extended-learndash-bulk-create'));
    }

    if ($action_type === 'update-legacy') {
      $this->url = (new QuestionConverter())->create($_FILES['csv_file']['tmp_name']);
      return;
    } elseif ($action_type === 'update') {
      $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
      $headers = fgetcsv($file);

      $oldPosts = null;

      while (($row = fgetcsv($file)) !== false) {
        $data = new Data(array_combine($headers, $row));
        $posts = new Posts();
        $posts->createOrUpdate($data, $oldPosts);
        $posts->updateMeta($data);
        $oldPosts = $posts;
      }
    }

    // $csv_file = $_FILES['csv_file']['tmp_name'];
    // $csv_data = array_map('str_getcsv', file($csv_file));
    // $headers = array_shift($csv_data);

    // if ($action_type === 'create') {
    //   $this->process_create($content_type, $csv_data, $headers);
    // } elseif ($action_type === 'update') {
    //   $this->process_update($content_type, $csv_data, $headers);
    // }
  }

  private function process_create($content_type, $csv_data, $headers) {
    $created_count = 0;
    $errors = [];

    foreach ($csv_data as $row_index => $row) {
      $post_data = array_combine($headers, $row);
      $result = $this->create_content($content_type, $post_data);
      if ($result === true) {
        $created_count++;
      } else {
        $errors[] = 'Row ' . ($row_index + 2) . ': ' . $result;
      }
    }

    $this->display_result_message($created_count, $errors);
  }

  private function process_update($content_type, $csv_data, $headers) {
    $this->generate_backup($content_type);
    $this->analyze_changes($content_type, $csv_data, $headers);
    $this->display_changes_confirmation();
  }

  private function create_content($content_type, $post_data) {
    if (!in_array($content_type, $this->supported_post_types)) {
      return sprintf(__('Invalid content type: %s', 'extended-learndash-bulk-create'), esc_html($content_type));
    }

    $post_args = [
      'post_title' => sanitize_text_field($post_data['post_title']),
      'post_content' => wp_kses_post($post_data['post_content']),
      'post_type' => $content_type,
      'post_status' => 'publish',
    ];

    $post_id = wp_insert_post($post_args, true);

    if (is_wp_error($post_id)) {
      return sprintf(
        __('Error creating %s: %s', 'extended-learndash-bulk-create'),
        $content_type,
        $post_id->get_error_message(),
      );
    }

    $this->update_associations($post_id, $content_type, $post_data);
    $this->update_custom_fields($post_id, $post_data);

    return true;
  }

  private function update_associations($post_id, $content_type, $post_data) {
    switch ($content_type) {
      case 'sfwd-lessons':
      case 'sfwd-topic':
        if (!empty($post_data['course_id'])) {
          update_post_meta($post_id, 'course_id', absint($post_data['course_id']));
        }
        break;
      case 'sfwd-quiz':
        if (!empty($post_data['course_id'])) {
          update_post_meta($post_id, 'course_id', absint($post_data['course_id']));
        }
        if (!empty($post_data['lesson_id'])) {
          update_post_meta($post_id, 'lesson_id', absint($post_data['lesson_id']));
        }
        break;
      case 'sfwd-question':
        if (!empty($post_data['quiz_id'])) {
          update_post_meta($post_id, 'quiz_id', absint($post_data['quiz_id']));
        }
        break;
    }
  }

  private function update_custom_fields($post_id, $post_data) {
    foreach ($post_data as $key => $value) {
      if (!in_array($key, ['post_title', 'post_content', 'course_id', 'lesson_id', 'quiz_id']) && !empty($value)) {
        update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
      }
    }
  }

  private function generate_backup($content_type) {
    $posts = get_posts([
      'post_type' => $content_type,
      'numberposts' => -1,
    ]);

    $backup_data = [];
    foreach ($posts as $post) {
      $backup_data[] = [
        'ID' => $post->ID,
        'post_title' => $post->post_title,
        'post_content' => $post->post_content,
        'meta_data' => get_post_meta($post->ID),
      ];
    }

    $backup_file = plugin_dir_path(__FILE__) . 'backups/' . $content_type . '_backup_' . date('Y-m-d_H-i-s') . '.json';
    file_put_contents($backup_file, json_encode($backup_data));
  }

  private function analyze_changes($content_type, $csv_data, $headers) {
    foreach ($csv_data as $row) {
      $post_data = array_combine($headers, $row);
      $post_id = isset($post_data['ID']) ? absint($post_data['ID']) : 0;

      if ($post_id) {
        $current_post = get_post($post_id);
        if ($current_post && $current_post->post_type === $content_type) {
          $changes = $this->compare_post_data($current_post, $post_data);
          if (!empty($changes)) {
            $this->changes[$post_id] = $changes;
          }
        }
      }
    }
  }

  private function compare_post_data($current_post, $new_data) {
    $changes = [];

    if ($current_post->post_title !== $new_data['post_title']) {
      $changes['post_title'] = [
        'old' => $current_post->post_title,
        'new' => $new_data['post_title'],
      ];
    }

    if ($current_post->post_content !== $new_data['post_content']) {
      $changes['post_content'] = [
        'old' => $current_post->post_content,
        'new' => $new_data['post_content'],
      ];
    }

    $meta_fields = ['course_id', 'lesson_id', 'quiz_id'];
    foreach ($meta_fields as $field) {
      $current_value = get_post_meta($current_post->ID, $field, true);
      if (isset($new_data[$field]) && $current_value !== $new_data[$field]) {
        $changes[$field] = [
          'old' => $current_value,
          'new' => $new_data[$field],
        ];
      }
    }

    return $changes;
  }

  private function display_changes_confirmation() {
    ?>
        <div class="wrap">
            <h2><?php _e('Confirm Changes', 'extended-learndash-bulk-create'); ?></h2>
            <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                <?php wp_nonce_field('confirm_changes', 'confirm_changes_nonce'); ?>
                <input type="hidden" name="action" value="confirm_changes">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all"></th>
                            <th><?php _e('Post ID', 'extended-learndash-bulk-create'); ?></th>
                            <th><?php _e('Field', 'extended-learndash-bulk-create'); ?></th>
                            <th><?php _e('Old Value', 'extended-learndash-bulk-create'); ?></th>
                            <th><?php _e('New Value', 'extended-learndash-bulk-create'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->changes as $post_id => $post_changes): ?>
                            <?php foreach ($post_changes as $field => $change): ?>
                                <tr>
                                    <td><input type="checkbox" name="confirm[]" value="<?php echo $post_id .
                                      '|' .
                                      $field; ?>"></td>
                                    <td><?php echo $post_id; ?></td>
                                    <td><?php echo $field; ?></td>
                                    <td><?php echo esc_html($change['old']); ?></td>
                                    <td><?php echo esc_html($change['new']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <input type="submit" class="button button-primary" value="<?php _e(
                      'Confirm Selected Changes',
                      'extended-learndash-bulk-create',
                    ); ?>">
                </p>
            </form>
        </div>
        <?php
  }

  public function confirm_changes() {
    check_ajax_referer('confirm_changes', 'confirm_changes_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(__('You do not have sufficient permissions to perform this action.', 'extended-learndash-bulk-create'));
    }

    $confirmed_changes = $_POST['confirm'];
    $updated_count = 0;
    $errors = [];

    foreach ($confirmed_changes as $change) {
      [$post_id, $field] = explode('|', $change);
      $post_id = absint($post_id);
      $new_value = $this->changes[$post_id][$field]['new'];

      $result = $this->update_post_field($post_id, $field, $new_value);
      if ($result === true) {
        $updated_count++;
      } else {
        $errors[] = $result;
      }
    }

    $this->display_result_message($updated_count, $errors, 'update');
  }

  private function update_post_field($post_id, $field, $new_value) {
    $post = get_post($post_id);
    if (!$post) {
      return sprintf(__('Post with ID %d not found.', 'extended-learndash-bulk-create'), $post_id);
    }

    switch ($field) {
      case 'post_title':
      case 'post_content':
        $update_args = [
          'ID' => $post_id,
          $field => $new_value,
        ];
        $result = wp_update_post($update_args, true);
        if (is_wp_error($result)) {
          return $result->get_error_message();
        }
        break;
      default:
        update_post_meta($post_id, $field, $new_value);
        break;
    }

    return true;
  }

  private function display_result_message($count, $errors, $action = 'create') {
    $action_text =
      $action === 'create'
        ? __('created', 'extended-learndash-bulk-create')
        : __('updated', 'extended-learndash-bulk-create');
    $message = sprintf(__('%d items %s successfully.', 'extended-learndash-bulk-create'), $count, $action_text);

    if (!empty($errors)) {
      $message .= ' ' . sprintf(__('%d errors occurred:', 'extended-learndash-bulk-create'), count($errors));
      $message .= '<ul>';
      foreach ($errors as $error) {
        $message .= '<li>' . esc_html($error) . '</li>';
      }
      $message .= '</ul>';
    }

    add_settings_error(
      'extended_learndash_bulk_create',
      'extended_learndash_bulk_create_result',
      $message,
      empty($errors) ? 'updated' : 'error',
    );
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

  // Create backup directory
  $backup_dir = plugin_dir_path(__FILE__) . 'backups';
  if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
  }
}
register_activation_hook(__FILE__, 'extended_learndash_bulk_create_activate');

// Initialize the plugin
$extended_learndash_bulk_create = new Extended_LearnDash_Bulk_Create();

// AJAX handler for confirming changes
add_action('wp_ajax_confirm_changes', [$extended_learndash_bulk_create, 'confirm_changes']);
