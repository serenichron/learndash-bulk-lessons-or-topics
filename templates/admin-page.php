<?php
if (!defined('ABSPATH')) {
  exit(); // Exit if accessed directly
}

// $types = ['quiz', 'topic', 'lesson', 'course', 'group'];
$types = ['quiz'];
?>

<div class="wrap">
  <h1><?php _e('Extended LearnDash Bulk Create and Update', 'extended-learndash-bulk-create'); ?></h1>

  <?php if (!empty($this->errorMessages)): ?>
    <div class="notice notice-error">
      <p><strong><?php _e('Problems found', 'extended-learndash-bulk-create'); ?></strong></p>
      <ul style="list-style: disc; margin-left: 2em;">
        <?php foreach ($this->errorMessages as $e): ?>
          <li><?= esc_html($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!empty($this->notices)): ?>
    <div class="notice notice-info">
      <ul style="list-style: disc; margin-left: 2em;">
        <?php foreach ($this->notices as $n): ?>
          <li><?= esc_html($n) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <h2><?php _e('Delete a quiz', 'extended-learndash-bulk-create'); ?></h2>
  <form method="post">
    <?php wp_nonce_field('extended_learndash_bulk_create', 'extended_learndash_bulk_create_nonce'); ?>
    <input type="hidden" name="action_type" value="delete">
    <table class="form-table">
      <tr>
        <th scope="row"><label for="delete_quiz_id">Quiz Id</label></th>
        <td>
          <input name="quizId" id="delete_quiz_id">
          <p class="description"><?php _e(
            'The quiz and all of its questions are removed permanently. There is no undo.',
            'extended-learndash-bulk-create',
          ); ?></p>
        </td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" name="submit" id="delete_submit" class="button button-primary" style="background-color: red;" value="<?php _e(
        'Delete Quiz',
        'extended-learndash-bulk-create',
      ); ?>">
    </p>
  </form>

  <h2><?php _e('Upload a CSV', 'extended-learndash-bulk-create'); ?></h2>
  <form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('extended_learndash_bulk_create', 'extended_learndash_bulk_create_nonce'); ?>
    <input type="hidden" name="action_type" value="update">
    <table class="form-table">
      <tr>
        <th scope="row"><label for="import_quiz_id">Quiz Id</label></th>
        <td>
          <input name="quizId" id="import_quiz_id">
          <p class="description"><?php _e(
            'Optional. Fill this in to wipe an existing quiz before the file is imported. Leave it empty to import without deleting anything.',
            'extended-learndash-bulk-create',
          ); ?></p>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="csv_file"><?php _e(
          'Upload CSV File',
          'extended-learndash-bulk-create',
        ); ?></label></th>
        <td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
      </tr>
      <tr>
        <th scope="row"><?php _e('Options', 'extended-learndash-bulk-create'); ?></th>
        <td>
          <p>
            <label>
              <input type="checkbox" name="check_only" value="1">
              <?php _e('Check only. Report what would happen and write nothing.', 'extended-learndash-bulk-create'); ?>
            </label>
          </p>
          <p>
            <label>
              <input type="checkbox" name="detach_missing" value="1">
              <?php _e(
                'Take questions out of a quiz when the file no longer lists them. The questions stay in WordPress.',
                'extended-learndash-bulk-create',
              ); ?>
            </label>
          </p>
        </td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" name="submit" id="import_submit" class="button button-primary" value="<?php _e(
        'Process CSV',
        'extended-learndash-bulk-create',
      ); ?>">
    </p>
  </form>

  <h2><?php _e('Generate a template', 'extended-learndash-bulk-create'); ?></h2>
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
        <th scope="row"><label for="siblings">Include sibling questions</label></th>
        <td>
          <input type="checkbox" name="siblings" id="siblings" value="1">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="questionId">Question Id</label></th>
        <td>
          <input name="questionId" id="questionId">
        </td>
      </tr>
    </table>
    <input type="submit" name="submit" id="template_submit" class="button button-primary" value="Generate template">
  </form>

  <hr>

  <h2 id="keys"><?php _e('Spreadsheet keys', 'extended-learndash-bulk-create'); ?></h2>
  <p class="description" style="max-width: 40em;">
    <?php _e(
      'A key lets one spreadsheet push content to this site. Make one key per spreadsheet, so you can cancel one without disturbing the others. A key can only push quizzes and questions. It cannot log in, and it cannot delete anything.',
      'extended-learndash-bulk-create',
    ); ?>
  </p>

  <?php $newKey = get_transient('ldbc_new_key_' . get_current_user_id()); ?>
  <?php if ($newKey): ?>
    <?php delete_transient('ldbc_new_key_' . get_current_user_id()); ?>
    <div class="notice notice-success">
      <p><strong><?php _e(
        'Copy this key now. It is not shown again.',
        'extended-learndash-bulk-create',
      ); ?></strong></p>
      <p><input type="text" readonly value="<?= esc_attr($newKey) ?>" style="width: 100%; max-width: 40em; font-family: monospace;" onclick="this.select()"></p>
    </div>
  <?php endif; ?>

  <table class="wp-list-table widefat fixed striped" style="max-width: 60em;">
    <thead>
      <tr>
        <th><?php _e('Name', 'extended-learndash-bulk-create'); ?></th>
        <th><?php _e('Made', 'extended-learndash-bulk-create'); ?></th>
        <th><?php _e('Last used', 'extended-learndash-bulk-create'); ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php $keys = \TSTPrep\LDImporter\ApiKeys::all(); ?>
      <?php if (empty($keys)): ?>
        <tr><td colspan="4"><?php _e('No keys yet.', 'extended-learndash-bulk-create'); ?></td></tr>
      <?php endif; ?>
      <?php foreach ($keys as $key): ?>
        <tr>
          <td><?= esc_html($key['name']) ?></td>
          <td><?= esc_html(date_i18n(get_option('date_format'), $key['created'])) ?></td>
          <td>
            <?= $key['last_used']
              ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $key['last_used']))
              : esc_html__('Never', 'extended-learndash-bulk-create') ?>
          </td>
          <td>
            <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="margin: 0;">
              <?php wp_nonce_field('ldbc_revoke_key'); ?>
              <input type="hidden" name="action" value="ldbc_revoke_key">
              <input type="hidden" name="key_id" value="<?= esc_attr($key['id']) ?>">
              <button type="submit" class="button button-link-delete"><?php _e(
                'Cancel this key',
                'extended-learndash-bulk-create',
              ); ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="margin-top: 1em;">
    <?php wp_nonce_field('ldbc_create_key'); ?>
    <input type="hidden" name="action" value="ldbc_create_key">
    <label for="key_name"><?php _e('Which spreadsheet is this for?', 'extended-learndash-bulk-create'); ?></label>
    <input name="key_name" id="key_name" class="regular-text" placeholder="<?php esc_attr_e(
      'TOEFL Speaking sheet',
      'extended-learndash-bulk-create',
    ); ?>" required>
    <button type="submit" class="button"><?php _e('Make a key', 'extended-learndash-bulk-create'); ?></button>
  </form>

  <h3><?php _e('Address for the spreadsheet', 'extended-learndash-bulk-create'); ?></h3>
  <p><input type="text" readonly value="<?= esc_attr(rest_url(\TSTPrep\LDImporter\Api::NS)) ?>" style="width: 100%; max-width: 40em; font-family: monospace;" onclick="this.select()"></p>
</div>
