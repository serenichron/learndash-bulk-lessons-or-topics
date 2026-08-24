<?php

namespace TSTPrep\LDImporter;

use WP_Query;

/**
 * Make the admin lists come out in the order things were made.
 *
 * A bulk import creates a whole sheet inside one second, so every row it
 * makes carries the same `post_date`. WordPress sorts those lists by date,
 * and when dates tie it hands the question to MySQL, which answers in
 * whatever order suits it. Five quizzes uploaded as Set 1 to Set 5 come back
 * as 5, 3, 4, 1, 2.
 *
 * Sorting by id as well settles it. Ids are handed out in creation order, so
 * a tie on the second resolves to the order the sheet had. Nothing is
 * written and no timestamp is invented: the same rows come back, in an order
 * that stops moving.
 *
 * Only the lists this plugin fills, and only when the list is on its default
 * date sort. Sort by title or anything else and this stays out of the way.
 */
class AdminOrder {
  private const POST_TYPES = ['groups', 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'sfwd-question'];

  public function register(): void {
    add_action('pre_get_posts', [$this, 'breakDateTies']);
  }

  public function breakDateTies(WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
      return;
    }

    // On some screens this is an array rather than one name, so it is read as
    // a list either way.
    $asked = array_map('strval', (array) $query->get('post_type'));

    if (empty(array_intersect($asked, self::POST_TYPES))) {
      return;
    }

    // Empty means the list is on its default, which for these screens is by
    // date. Anything else is a column the user clicked, and theirs to keep.
    $orderby = $query->get('orderby');

    if ($orderby !== '' && $orderby !== 'date') {
      return;
    }

    $order = strtoupper((string) $query->get('order')) === 'ASC' ? 'ASC' : 'DESC';

    $query->set('orderby', ['date' => $order, 'ID' => $order]);
    $query->set('order', $order);
  }
}
