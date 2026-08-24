<?php

/**
 * Run the admin ordering rules without WordPress.
 *
 * WP_Query and is_admin are stood in for, so the branch that decides whether
 * to touch a list can be exercised. What it cannot tell you is whether
 * WordPress hands us the values we expect. That part is only provable on a
 * site.
 *
 * php tests/admin-order.php
 */

class WP_Query {
  public array $vars;
  public bool $main;

  public function __construct(array $vars, bool $main = true) {
    $this->vars = $vars;
    $this->main = $main;
  }

  public function is_main_query(): bool {
    return $this->main;
  }

  public function get(string $key) {
    return $this->vars[$key] ?? '';
  }

  public function set(string $key, $value): void {
    $this->vars[$key] = $value;
  }
}

$GLOBALS['ldbc_is_admin'] = true;

function is_admin(): bool {
  return (bool) $GLOBALS['ldbc_is_admin'];
}

function add_action($hook, $callback): void {
}

require_once __DIR__ . '/../classes/AdminOrder.php';

use TSTPrep\LDImporter\AdminOrder;

$failures = 0;

function ok(string $name, bool $condition, $extra = null): void {
  global $failures;

  if ($condition) {
    echo "  pass  $name\n";
    return;
  }

  $failures++;
  echo "  FAIL  $name" . ($extra === null ? '' : "\n        " . json_encode($extra)) . "\n";
}

function section(string $title): void {
  echo "\n$title\n";
}

function run(array $vars, bool $main = true, bool $admin = true): WP_Query {
  $GLOBALS['ldbc_is_admin'] = $admin;
  $query = new WP_Query($vars, $main);
  (new AdminOrder())->breakDateTies($query);

  return $query;
}

section('A tie on the second falls back to the id');

$q = run(['post_type' => 'sfwd-quiz']);
ok('the default list gets an id tiebreak', $q->get('orderby') === ['date' => 'DESC', 'ID' => 'DESC'], $q->get('orderby'));
ok('newest still first', $q->get('order') === 'DESC');

$q = run(['post_type' => 'sfwd-quiz', 'order' => 'asc']);
ok('flipping the date column flips both', $q->get('orderby') === ['date' => 'ASC', 'ID' => 'ASC'], $q->get('orderby'));

$q = run(['post_type' => 'sfwd-question', 'orderby' => 'date', 'order' => 'DESC']);
ok('an explicit date sort is covered too', $q->get('orderby') === ['date' => 'DESC', 'ID' => 'DESC']);

$q = run(['post_type' => ['sfwd-quiz', 'sfwd-lessons']]);
ok('a list of post types is read as a list', $q->get('orderby') === ['date' => 'DESC', 'ID' => 'DESC']);

$q = run(['post_type' => 'groups']);
ok('every post type this plugin makes is covered', $q->get('orderby') === ['date' => 'DESC', 'ID' => 'DESC']);

section('Everything else is left exactly as it was');

$q = run(['post_type' => 'sfwd-quiz', 'orderby' => 'title', 'order' => 'ASC']);
ok('sorting by title is the users to keep', $q->get('orderby') === 'title', $q->get('orderby'));

$q = run(['post_type' => 'post']);
ok('ordinary posts are not touched', $q->get('orderby') === '');

$q = run(['post_type' => 'sfwd-quiz'], false);
ok('a secondary query is not touched', $q->get('orderby') === '');

$q = run(['post_type' => 'sfwd-quiz'], true, false);
ok('the front of the site is not touched', $q->get('orderby') === '');

$q = run([]);
ok('a query with no post type is not touched', $q->get('orderby') === '');

$q = run(['post_type' => 'sfwd-quiz', 'orderby' => 'meta_value_num']);
ok('a meta sort is not touched', $q->get('orderby') === 'meta_value_num');

echo "\n" . ($failures ? "$failures FAILURES\n" : "all green\n");
exit($failures ? 1 : 0);
