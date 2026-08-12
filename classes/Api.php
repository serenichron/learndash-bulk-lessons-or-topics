<?php

namespace TSTPrep\LDImporter;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The door the spreadsheet knocks on.
 *
 * Three ways in. ping tells the script which site it is pointing at, which
 * matters when you keep a staging site and a live one. check reads a sheet
 * and reports. push builds it, and only after the same check has passed.
 *
 * Nothing here deletes. A push makes new content or updates existing content
 * by id, and that is the whole of it.
 */
class Api {
  public const NS = 'ldbc/v1';

  public function register(): void {
    add_action('rest_api_init', [$this, 'routes']);
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

    return new WP_REST_Response($this->site() + [
      'ok' => true,
      'key_name' => $key['name'] ?? null,
      'learndash' => function_exists('learndash_course_add_child_to_parent'),
      'question_types' => class_exists('TSTPrep\LDAdvancedQuizzes\Questions'),
    ]);
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
