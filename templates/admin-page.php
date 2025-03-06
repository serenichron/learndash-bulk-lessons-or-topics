<?php
if (!defined('ABSPATH')) {
  exit(); // Exit if accessed directly
} ?>
<div class="wrap">
  <h1><?php _e('Extended LearnDash Bulk Create and Update', 'extended-learndash-bulk-create'); ?></h1>
  <form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('extended_learndash_bulk_create', 'extended_learndash_bulk_create_nonce'); ?>
    <table class="form-table">
      <tr>
        <th scope="row"><label for="action_type"><?php _e('Action', 'extended-learndash-bulk-create'); ?></label></th>
        <td>
          <select name="action_type" id="action_type">
            <option value="update"><?php _e('Update', 'extended-learndash-bulk-create'); ?></option>
            <option value="update-legacy"><?php _e('Update Legacy', 'extended-learndash-bulk-create'); ?></option>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="csv_file"><?php _e(
          'Upload CSV File',
          'extended-learndash-bulk-create',
        ); ?></label></th>
        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e(
        'Process CSV',
        'extended-learndash-bulk-create',
      ); ?>">
    </p>
  </form>
  <p>
    <a href="<?php echo plugin_dir_url(dirname(__FILE__)) . 'templates/bulk_template.csv'; ?>" download>
      <?php _e('Download CSV Template', 'extended-learndash-bulk-create'); ?>
    </a>
  </p>
  <?php if ($this->url) { ?>
    <a href="<?= $this->url ?>">Download file</a>
  <?php } ?>
</div>
