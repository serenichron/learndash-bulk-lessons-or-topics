<?php
if (!defined('ABSPATH')) {
  exit(); // Exit if accessed directly
}

// $types = ['quiz', 'topic', 'lesson', 'course', 'group'];
$types = ['quiz'];
?>

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
  <form method="post" enctype="multipart/form-data" class="gen_template_form" data-url="<?= str_replace(
    ['http:', 'https:'],
    ['', ''],
    admin_url('admin-ajax.php'),
  ) ?>">
    <input type="hidden" name="action" value="ld_import_gen_template">
    <table class="form-table">
      <tr>
        <th scope="row"><label for="include">Include up to</label></th>
        <td>
          <select name="include" id="include">
            <option value="question">Question</option>
            <?php foreach ($types as $type): ?>
              <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <?php for ($i = count($types) - 1; $i >= 0; $i--):

        $type = $types[$i];
        if ($type === 'group') {
          continue;
        }
        $label = ucfirst($type);
        ?>
        <tr class="<?= $type ?>-field" style="display: none;">
          <th scope="row"><label for="include-<?= $type ?>">Full <?= $label ?></label></th>
          <td>
            <input type="checkbox" name="include-<?= $type ?>" id="include-<?= $type ?>" value="1">
          </td>
        </tr>
      <?php
      endfor; ?>
      <tr>
        <th scope="row"><label for="questionId">Question Id</label></th>
        <td>
          <input name="questionId" id="questionId">
        </td>
      </tr>
    </table>
    <input type="submit" name="submit" id="submit" class="button button-primary" value="Generate template">
  </form>
  <?php if ($this->url) { ?>
    <a href="<?= $this->url ?>">Download file</a>
  <?php } ?>
</div>
