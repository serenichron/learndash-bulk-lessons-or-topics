<?php
/**
 * Plugin Name: LearnDash Bulk Lessons Or Topics
 * Description: Adds functionality to bulk create Courses, Lessons, or Topics in LearnDash using a CSV file.
 * Version: 1.0.4
 * Author: Vlad Tudorie
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learndash-bulk-lessons-or-topics
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Enable error reporting
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Log errors
// ini_set('log_errors', 1);
// ini_set('error_log', dirname(__FILE__) . '/error_log.txt');

class LearnDash_Bulk_Create {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_post_handle_csv_upload', array($this, 'handle_csv_upload'));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'learndash-lms',
            __('Bulk Create', 'learndash-bulk-create'),
            __('Bulk Create', 'learndash-bulk-create'),
            'manage_options',
            'learndash-bulk-create',
            array($this, 'bulk_create_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('learndash-lms_page_learndash-bulk-create' !== $hook) {
            return;
        }
        wp_enqueue_script('learndash-bulk-create', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), '1.0', true);
    }

    public function bulk_create_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Extended LearnDash Bulk Create and Update', 'extended-learndash-bulk-create'); ?></h1>
            <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="handle_csv_upload">
                <?php wp_nonce_field('extended_learndash_bulk_create', 'extended_learndash_bulk_create_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="action_type"><?php _e('Action', 'extended-learndash-bulk-create'); ?></label></th>
                        <td>
                            <select name="action_type" id="action_type">
                                <option value="create"><?php _e('Create', 'extended-learndash-bulk-create'); ?></option>
                                <option value="update"><?php _e('Update', 'extended-learndash-bulk-create'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="content_type"><?php _e('Content Type', 'extended-learndash-bulk-create'); ?></label></th>
                        <td>
                            <select name="content_type" id="content_type">
                                <option value="sfwd-courses"><?php _e('Courses', 'extended-learndash-bulk-create'); ?></option>
                                <option value="sfwd-lessons"><?php _e('Lessons', 'extended-learndash-bulk-create'); ?></option>
                                <option value="sfwd-topic"><?php _e('Topics', 'extended-learndash-bulk-create'); ?></option>
                                <option value="sfwd-quiz"><?php _e('Quizzes', 'extended-learndash-bulk-create'); ?></option>
                                <option value="sfwd-question"><?php _e('Questions', 'extended-learndash-bulk-create'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csv_file"><?php _e('Upload CSV File', 'extended-learndash-bulk-create'); ?></label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Process CSV', 'extended-learndash-bulk-create'); ?>">
                </p>
            </form>
            <p><a href="<?php echo plugin_dir_url(__FILE__) . 'templates/bulk_template.csv'; ?>" download><?php _e('Download CSV Template', 'extended-learndash-bulk-create'); ?></a></p>
        </div>
        <?php
    }

    public function handle_csv_upload() {
        // Verify this is our form submission
        if (!isset($_POST['extended_learndash_bulk_create_nonce']) || 
            !wp_verify_nonce($_POST['extended_learndash_bulk_create_nonce'], 'extended_learndash_bulk_create')) {
            wp_die(__('Security check failed', 'extended-learndash-bulk-create'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'extended-learndash-bulk-create'));
        }

        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        $content_type = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : '';

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('CSV file upload failed. Please try again.', 'extended-learndash-bulk-create'));
        }

        $csv_file = $_FILES['csv_file']['tmp_name'];
        $csv_data = array_map('str_getcsv', file($csv_file));
        $headers = array_shift($csv_data);

        if ($action_type === 'create') {
            $this->process_create($content_type, $csv_data, $headers);
        } elseif ($action_type === 'update') {
            $this->process_update($content_type, $csv_data, $headers);
        }

        // Redirect back to our admin page with a status message
        $redirect_url = add_query_arg(
            array(
                'page' => 'extended-learndash-bulk-create',
                'message' => 'success'
            ),
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect_url);
        exit;
    }

    private function create_content($post_data) {
        if (!isset($post_data['post_type']) || !in_array($post_data['post_type'], array('sfwd-courses', 'sfwd-lessons', 'sfwd-topic'))) {
            return sprintf(__('Invalid post type: %s', 'learndash-bulk-create'), esc_html($post_data['post_type']));
        }

        $post_args = array(
            'post_title'   => sanitize_text_field($post_data['post_title']),
            'post_content' => wp_kses_post($post_data['post_content']),
            'post_type'    => $post_data['post_type'],
            'post_status'  => 'publish'
        );

        $post_id = wp_insert_post($post_args, true); // Set second argument to true to return WP_Error on failure

        if (is_wp_error($post_id)) {
            return sprintf(__('Error creating %s: %s', 'learndash-bulk-create'), $post_data['post_type'], $post_id->get_error_message());
        }

        // Set course relationship for lessons and topics
        if (in_array($post_data['post_type'], array('sfwd-lessons', 'sfwd-topic')) && isset($post_data['course_id']) && !empty($post_data['course_id'])) {
            update_post_meta($post_id, 'course_id', absint($post_data['course_id']));
        }

        // Set lesson relationship for topics
        if ($post_data['post_type'] === 'sfwd-topic' && isset($post_data['lesson_id']) && !empty($post_data['lesson_id'])) {
            update_post_meta($post_id, 'lesson_id', absint($post_data['lesson_id']));
        }

        // Add any additional meta fields
        foreach ($post_data as $key => $value) {
            if (!in_array($key, ['post_type', 'post_title', 'post_content', 'course_id', 'lesson_id']) && !empty($value)) {
                update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }

        return true;
    }
}

// Activation hook
function learndash_bulk_create_activate() {
    // Check if LearnDash is active
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    
    if (!is_plugin_active('sfwd-lms/sfwd_lms.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Please install and activate LearnDash before activating this plugin.', 'learndash-bulk-create'), 'Plugin dependency check', array('back_link' => true));
    }

    // You can add more activation tasks here if needed
}
register_activation_hook(__FILE__, 'learndash_bulk_create_activate');

// Initialize the plugin
$learndash_bulk_create = new LearnDash_Bulk_Create();
