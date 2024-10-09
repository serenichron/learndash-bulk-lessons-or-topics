<?php
/**
 * Plugin Name: LearnDash Bulk Create
 * Description: Adds functionality to bulk create Courses, Lessons, or Topics in LearnDash using a CSV file.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class LearnDash_Bulk_Create {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_csv_upload'));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'learndash-lms',
            'Bulk Create',
            'Bulk Create',
            'manage_options',
            'learndash-bulk-create',
            array($this, 'bulk_create_page')
        );
    }

    public function bulk_create_page() {
        ?>
        <div class="wrap">
            <h1>LearnDash Bulk Create</h1>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('learndash_bulk_create', 'learndash_bulk_create_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="csv_file">Upload CSV File</label></th>
                        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="content_type">Content Type</label></th>
                        <td>
                            <select name="content_type" id="content_type" required>
                                <option value="course">Course</option>
                                <option value="lesson">Lesson</option>
                                <option value="topic">Topic</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="parent_id_row" style="display: none;">
                        <th scope="row"><label for="parent_id">Parent ID</label></th>
                        <td><input type="number" name="parent_id" id="parent_id"></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Upload and Create">
                </p>
            </form>
            <p><a href="<?php echo plugin_dir_url(__FILE__) . 'templates/bulk_create_template.csv'; ?>" download>Download CSV Template</a></p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#content_type').change(function() {
                    if ($(this).val() == 'lesson' || $(this).val() == 'topic') {
                        $('#parent_id_row').show();
                    } else {
                        $('#parent_id_row').hide();
                    }
                });
            });
        </script>
        <?php
    }

    public function handle_csv_upload() {
        if (isset($_POST['submit']) && check_admin_referer('learndash_bulk_create', 'learndash_bulk_create_nonce')) {
            if (!current_user_can('manage_options')) {
                wp_die('You do not have sufficient permissions to access this page.');
            }

            $content_type = $_POST['content_type'];
            $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;

            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                wp_die('CSV file upload failed. Please try again.');
            }

            $csv_file = $_FILES['csv_file']['tmp_name'];
            $csv_data = array_map('str_getcsv', file($csv_file));
            $headers = array_shift($csv_data);

            foreach ($csv_data as $row) {
                $post_data = array_combine($headers, $row);
                $this->create_content($content_type, $parent_id, $post_data);
            }

            wp_redirect(add_query_arg('message', 'success', wp_get_referer()));
            exit;
        }
    }

    private function create_content($content_type, $parent_id, $post_data) {
        $post_type = $this->get_post_type($content_type);
        
        $post_args = array(
            'post_title'   => $post_data['post_title'],
            'post_content' => $post_data['post_content'],
            'post_type'    => $post_type,
            'post_status'  => 'publish'
        );

        $post_id = wp_insert_post($post_args);

        if ($post_id) {
            if ($content_type === 'lesson') {
                update_post_meta($post_id, 'course_id', $parent_id);
            } elseif ($content_type === 'topic') {
                update_post_meta($post_id, 'lesson_id', $parent_id);
            }

            // Add any additional meta fields here
            foreach ($post_data as $key => $value) {
                if (!in_array($key, ['post_title', 'post_content'])) {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }
    }

    private function get_post_type($content_type) {
        switch ($content_type) {
            case 'course':
                return 'sfwd-courses';
            case 'lesson':
                return 'sfwd-lessons';
            case 'topic':
                return 'sfwd-topic';
            default:
                return '';
        }
    }
}

new LearnDash_Bulk_Create();
