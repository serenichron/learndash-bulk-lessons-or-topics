<?php

namespace TSTPrep\LDImporter;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The door the spreadsheet knocks on.
 *
 * Four ways in. ping tells the script which site it is pointing at, which
 * matters when you keep two staging sites and a production one. check reads
 * a sheet and reports. push builds it, and only after the same check has
 * passed. lookup answers what a single post id is, so the spreadsheet can
 * ask before it links a row to something that already exists.
 *
 * Nothing here deletes. A push makes new content or updates existing content
 * by id, and that is the whole of it.
 */
class Api {
  public const NS = 'ldbc/v1';

  /** How many ids one lookup will answer for. The script asks in batches of this. */
  public const LOOKUP_LIMIT = 200;

  public function register(): void {
    add_action('rest_api_init', [$this, 'routes']);

    // After WordPress has had its say about application passwords, priority
    // 90, and cookies, priority 100.
    add_filter('rest_authentication_errors', [$this, 'ignoreFrontDoorLogin'], 101);
  }

  /**
   * The password on a staging site's front door is not a WordPress login.
   *
   * The web server asks for it, then hands the same Authorization header on
   * to PHP, where WordPress reads it as an application password, fails to
   * find a user by that name and turns the request away with "Unknown
   * username" before any route of ours is reached. The header was never
   * addressed to WordPress.
   *
   * So on our own routes, when something carrying a key of ours is knocking,
   * that verdict is dropped. Dropping it grants nothing: the caller stays
   * logged out and still has to get past authorise() on the strength of its
   * key. All it buys is the right to be judged by the correct door.
   *
   * @param true|WP_Error|null $result
   * @return true|WP_Error|null
   */
  public function ignoreFrontDoorLogin($result) {
    if (!is_wp_error($result) || !$this->onOurRoute()) {
      return $result;
    }

    $key = trim((string) ($_SERVER['HTTP_X_LDBC_KEY'] ?? ''));

    return ApiKeys::looksLikeKey($key) ? null : $result;
  }

  /**
   * Which route is being asked for, read before the request is dispatched
   * and there is a WP_REST_Request to ask.
   */
  private function onOurRoute(): bool {
    $wp = $GLOBALS['wp'] ?? null;
    $route = $wp instanceof \WP ? $wp->query_vars['rest_route'] ?? '' : '';

    if (!is_string($route) || $route === '') {
      return false;
    }

    return strpos(ltrim($route, '/'), self::NS . '/') === 0;
  }

  public function routes(): void {
    register_rest_route(self::NS, '/ping', [
      'methods' => 'GET',
      'callback' => [$this, 'ping'],
      'permission_callback' => [$this, 'authorise'],
    ]);

    $rowArgs = [
      'rows' => [
        'required' => true,
        'type' => 'array',
        'description' => 'One entry per sheet row, keyed by column name.',
      ],
      'first_row' => [
        'type' => 'integer',
        'default' => 2,
        'description' => 'Sheet row number of the first entry, so problems name the row you see.',
      ],
    ];

    register_rest_route(self::NS, '/check', [
      'methods' => 'POST',
      'callback' => [$this, 'check'],
      'permission_callback' => [$this, 'authorise'],
      'args' => $rowArgs,
    ]);

    register_rest_route(self::NS, '/push', [
      'methods' => 'POST',
      'callback' => [$this, 'push'],
      'permission_callback' => [$this, 'authorise'],
      'args' => $rowArgs + [
        'detach_missing' => [
          'type' => 'boolean',
          'default' => false,
          'description' => 'Take questions out of a quiz when the sheet no longer lists them.',
        ],
      ],
    ]);

    register_rest_route(self::NS, '/lookup', [
      'methods' => 'GET',
      'callback' => [$this, 'lookup'],
      'permission_callback' => [$this, 'authorise'],
      'args' => [
        'ids' => [
          'required' => true,
          'type' => 'string',
          'description' => 'Post ids the spreadsheet is thinking of taking over, comma separated.',
        ],
      ],
    ]);
  }

  /**
   * @return true|WP_Error
   */
  public function authorise(WP_REST_Request $request) {
    $address = $this->address();

    if (ApiKeys::tooManyFailures($address)) {
      return new WP_Error(
        'ldbc_too_many_attempts',
        __('Too many failed attempts. Wait fifteen minutes.', 'extended-learndash-bulk-create'),
        ['status' => 429],
      );
    }

    $key = $this->keyFrom($request);

    if ($key === '' || ApiKeys::verify($key) === null) {
      ApiKeys::noteFailure($address);

      return new WP_Error(
        'ldbc_bad_key',
        __('That key is not valid for this site.', 'extended-learndash-bulk-create'),
        ['status' => 401],
      );
    }

    return true;
  }

  public function ping(WP_REST_Request $request): WP_REST_Response {
    $key = ApiKeys::verify($this->keyFrom($request));

    return new WP_REST_Response(
      $this->site() + [
        'ok' => true,
        'key_name' => $key['name'] ?? null,
        'learndash' => function_exists('learndash_course_add_child_to_parent'),
        'question_types' => class_exists('TSTPrep\LDAdvancedQuizzes\Questions'),
      ],
    );
  }

  /**
   * What are these posts on this site, if anything.
   *
   * The spreadsheet asks before it takes existing posts under its wing, so
   * ids someone pasted in fail at the moment they are adopted rather than on
   * a push weeks later. Answers in the order it was asked, one entry per id,
   * whether or not the post is there.
   */
  public function lookup(WP_REST_Request $request): WP_REST_Response {
    $asked = preg_split('/[s,]+/', (string) $request->get_param('ids'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ids = array_slice(array_values(array_unique(array_map('intval', $asked))), 0, self::LOOKUP_LIMIT);
    $posts = [];

    foreach ($ids as $id) {
      $type = $id > 0 ? get_post_type($id) : false;

      $posts[] = [
        'id' => $id,
        'found' => (bool) $type,
        'post_type' => $type ?: null,
        'title' => $type ? get_the_title($id) : null,
        'status' => $type ? get_post_status($id) : null,
      ];
    }

    return new WP_REST_Response($this->site() + ['ok' => true, 'posts' => $posts]);
  }

  public function check(WP_REST_Request $request): WP_REST_Response {
    $report = (new Importer())->check($this->rows($request));

    return new WP_REST_Response($this->site() + ['mode' => 'check'] + $report);
  }

  public function push(WP_REST_Request $request): WP_REST_Response {
    // Importing is slow work by nature. Hosts that forbid this ignore it,
    // which is why the script sends a sheet in chunks rather than relying
    // on it.
    if (function_exists('set_time_limit')) {
      @set_time_limit(300);
    }

    $report = (new Importer())->push($this->rows($request), (bool) $request->get_param('detach_missing'));

    return new WP_REST_Response($this->site() + ['mode' => 'push'] + $report);
  }

  /**
   * Key the rows by the row number the user sees in their spreadsheet, so a
   * problem reads "row 14" and they can go straight to it.
   *
   * @return array<int, array<string, mixed>>
   */
  private function rows(WP_REST_Request $request): array {
    $first = max(1, (int) $request->get_param('first_row'));
    $rows = [];

    foreach (array_values((array) $request->get_param('rows')) as $index => $row) {
      $rows[$first + $index] = (array) $row;
    }

    return $rows;
  }

  /**
   * Which site this is. Sent with every reply so the script can say out loud
   * where a push is going before it goes there.
   */
  private function site(): array {
    return [
      'site' => get_bloginfo('name'),
      'url' => home_url(),
    ];
  }

  private function keyFrom(WP_REST_Request $request): string {
    $key = $request->get_header('x_ldbc_key');

    if (!$key) {
      $auth = (string) $request->get_header('authorization');
      if (stripos($auth, 'bearer ') === 0) {
        $key = substr($auth, 7);
      }
    }

    return trim((string) $key);
  }

  private function address(): string {
    $address = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($address) ? $address : '';
  }
}
