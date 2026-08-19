<?php

namespace TSTPrep\LDImporter;

/**
 * Keys that let a spreadsheet push content to this site.
 *
 * One key per spreadsheet, so a key can be cancelled without disturbing the
 * others. A key opens this one door and nothing else, which is why it is a
 * key of our own rather than a WordPress login: a leaked login would be an
 * admin, a leaked key can only push quizzes.
 *
 * Only the hash is stored. The key itself is shown once, when it is made,
 * and cannot be read back afterwards.
 */
class ApiKeys {
  private const OPTION = 'ldbc_api_keys';
  private const PREFIX = 'ldbc';

  /** @return array<string, array{id: string, name: string, hash: string, created: int, last_used: ?int}> */
  public static function all(): array {
    $keys = get_option(self::OPTION, []);

    return is_array($keys) ? $keys : [];
  }

  /**
   * Make a key. The return value is the only time the key is readable.
   */
  public static function create(string $name): string {
    $id = bin2hex(random_bytes(6));
    $secret = bin2hex(random_bytes(24));

    $keys = self::all();
    $keys[$id] = [
      'id' => $id,
      'name' => $name !== '' ? $name : __('Unnamed', 'extended-learndash-bulk-create'),
      'hash' => password_hash($secret, PASSWORD_DEFAULT),
      'created' => time(),
      'last_used' => null,
    ];

    update_option(self::OPTION, $keys, false);

    return self::PREFIX . '_' . $id . '_' . $secret;
  }

  public static function revoke(string $id): bool {
    $keys = self::all();

    if (!isset($keys[$id])) {
      return false;
    }

    unset($keys[$id]);
    update_option(self::OPTION, $keys, false);

    return true;
  }

  /**
   * Check a key and note that it was used.
   *
   * The id travels in the key itself, so exactly one hash is checked rather
   * than every key on the site.
   *
   * @return array{id: string, name: string}|null
   */
  public static function verify(string $key): ?array {
    if (!preg_match(self::shape(), $key, $parts)) {
      return null;
    }

    [, $id, $secret] = $parts;

    $keys = self::all();
    $record = $keys[$id] ?? null;

    if (!$record || !isset($record['hash'])) {
      return null;
    }

    if (!password_verify($secret, $record['hash'])) {
      return null;
    }

    $keys[$id]['last_used'] = time();
    update_option(self::OPTION, $keys, false);

    return [
      'id' => $record['id'],
      'name' => $record['name'],
    ];
  }

  /**
   * Is this the right shape to be one of our keys?
   *
   * Says nothing whatever about whether the key is real, and is not a way in.
   * It is here so a caller can tell "the spreadsheet is knocking" from "some
   * passer-by" without paying for a hash check.
   */
  public static function looksLikeKey(string $key): bool {
    return (bool) preg_match(self::shape(), $key);
  }

  private static function shape(): string {
    return '/^' . self::PREFIX . '_([0-9a-f]{12})_([0-9a-f]{48})$/';
  }

  /**
   * Slow down anyone guessing. Counted per address, and only failures count.
   */
  public static function tooManyFailures(string $address): bool {
    return (int) get_transient(self::failureKey($address)) >= 10;
  }

  public static function noteFailure(string $address): void {
    $key = self::failureKey($address);
    $count = (int) get_transient($key);
    set_transient($key, $count + 1, 15 * MINUTE_IN_SECONDS);
  }

  private static function failureKey(string $address): string {
    return 'ldbc_fail_' . md5($address);
  }
}
