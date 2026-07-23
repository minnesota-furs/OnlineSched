<?php
/**
 * Disposable Vanilla-only integration checks for the OnlineSched app feed
 * (json.php sections: meta, schedule, hours, info), the feed-revision
 * invalidation service, CSV import/delete-year batch semantics, durable
 * event_uid identity, and the versioned feed-schema upgrade.
 *
 * Reworked against Kaiser's round-4 storage redesign:
 *   - The public contract is still 3-part: onlinesched_get_feed_change_stamp()
 *     is "schedule.hours.info". The internal 'meta' revision still exists and
 *     surfaces ONLY through the meta ETag:
 *     onlinesched_app_feed_etag('meta') === '"os-{schema}-meta-{stamp}+{metaRev}"'.
 *   - The optimistic-CAS revision store (which measurably lost ~10% of
 *     increments under 8-way concurrency — see the prior round's report) is
 *     gone. Each tracked section now has two plain option rows,
 *     onlinesched_feed_rev_{section} and onlinesched_feed_revtime_{section},
 *     bumped by onlinesched_feed_atomic_bump() — a single atomic SQL
 *     `UPDATE ... SET option_value = CAST(option_value AS UNSIGNED) + 1`.
 *     There is no read-modify-write to race, so the 8x25 concurrency proof
 *     (test-app-feed.sh, real parallel `wp eval` processes) is expected to
 *     land at EXACTLY +200 every time now.
 *   - The legacy serialized 'onlinesched_feed_revisions' blob is retired: it
 *     is only ever read once, by the v3 upgrade's migration step, which
 *     converts it into the per-section rows and deletes it. Section A below
 *     includes a dedicated legacy-migration test for that path.
 *   - onlinesched_feed_output_meta_keys() and onlinesched_feed_option_section_map()
 *     centralize which post-meta keys and options are output-relevant;
 *     onlinesched_event_uid meta and the onlinesched_json_room_groups option
 *     are both wired in.
 *   - event_uid is fail-closed: onlinesched_get_event_uid() returns '' for a
 *     manual event with no persisted UUID yet (no 'wp:{ID}' fallback), and
 *     the schedule builder OMITS such events entirely rather than serve a
 *     provisional identity.
 *   - Single-mutation deltas are asserted as "moved" (>), not locked to an
 *     exact count — coalescing logic in feed-revisions.php is free to change
 *     how many hooks contribute to one logical mutation. Exactly-once
 *     assertions are kept ONLY for the CSV import batch and the delete-year
 *     batch (their whole point is "one logical change, one touch, regardless
 *     of row count").
 *
 * Round-5 additions (Kaiser's round-3 repair ticket):
 *   - onlinesched_app_feed_etag() takes optional $revisions/$content_hash args;
 *     onlinesched_app_feed_send() now hashes the exact response bytes and
 *     passes that hash through, so the ETag changes whenever the
 *     *representation* changes for any reason (payload-hash property), not
 *     only when a revision moves. Pinned at the builder level (format) here;
 *     the "different filter, same revision, different ETag" proof lives in
 *     test-app-feed.sh since it needs a real HTTP round trip.
 *   - UID precedence flipped: a persisted onlinesched_event_uid UUID now
 *     always wins over onlinesched_external_event_id, so a later CSV-export
 *     backfill of an external id onto a manually created event can never
 *     change a uid a client already has. Regression test included.
 *   - New invalidation surfaces: the 'blogname' option (meta's con_name
 *     fallback) now bumps the internal meta revision; trashing or
 *     permanently deleting the configured hours/info page now touches its
 *     section, not just editing its content.
 *   - The save-flow close-out handler moved from save_post_os_event(20) to
 *     the generic save_post(99) so plugin meta/term writes (priority 10)
 *     always coalesce first; no test changes needed beyond keeping the
 *     existing "moved" (>) assertions green.
 *
 * Round-7 additions (Kaiser's five terse findings, independently diagnosed
 * and repaired):
 *   - onlinesched_app_feed_build_consistent(): rebuilds (bounded 3 attempts)
 *     when a revision moves mid-build; test forces exactly one rebuild via a
 *     callback that touches schedule only on its first invocation.
 *   - CSV export now reuses a manual event's persisted uuid as its external
 *     id (a uuid rawurlencodes to itself), so export -> delete-year ->
 *     reimport reproduces a byte-identical uid; destructive roundtrip test
 *     included.
 *   - csv-import / delete-year wrap their batch in an outer catch: partial
 *     writes before a thrown exception still fire exactly one
 *     'csv-import-partial' / 'delete-year-partial' touch after suspension
 *     resumes, then rethrow; both paths are exercised by forcing a real
 *     exception mid-batch via a late-priority hook.
 *   - onlinesched_feed_atomic_bump() falls back to a guaranteed-movement
 *     update_option() path instead of silently no-op'ing when the atomic SQL
 *     genuinely fails; exercised via a real (but fully isolated, throwaway)
 *     SQL syntax error injected through WordPress's 'query' filter.
 *   - onlinesched_feed_advance_revtime() uses SQL GREATEST so an older
 *     concurrent timestamp can never regress the stored revtime; tested
 *     directly.
 *
 * Round-8 additions (Kaiser's final-byte recheck; four required regressions):
 *   a. Persistent SQL failure spanning BOTH the direct UPDATE/INSERT path and
 *      the update_option() fallback: onlinesched_feed_atomic_bump() and
 *      onlinesched_touch_feed() must report false, and onlinesched_feed_touched
 *      must not fire — then recovery once the corruption is removed.
 *   b. Retry exhaustion / torn-pair: a build callback that keeps moving the
 *      revision on every invocation must still get back a coherent (payload,
 *      revisions) pair from onlinesched_app_feed_build_consistent() — stale
 *      relative to a fresh read is fine, torn is not.
 *   c. onlinesched_app_feed_meta() must compose change_stamp/revisions
 *      strictly from a supplied handcrafted snapshot, never a live read.
 *   d. The v3 upgrade's UUID-backfill suspend/resume is try/finally-wrapped:
 *      an exception mid-backfill must still leave suspension depth at 0.
 *
 * The mutation matrix uses only real WordPress mutations
 * (wp_insert_post/wp_update_post/update_post_meta/wp_set_object_terms/real
 * option updates) — never a direct do_action() call to simulate a hook.
 *
 * Run through tests/cli/test-app-feed.sh, which enforces the same
 * vanilla-only container/site-URL safety checks as the other CLI harnesses
 * before invoking this file with `wp eval-file`, and which separately proves
 * the atomic-increment store's concurrency guarantee with real parallel
 * `wp eval` processes.
 *
 * This file only reads/writes disposable fixtures it creates itself
 * (uniquely named per run) and restores every option it touches, so it is
 * safe to run repeatedly against the same disposable site.
 */

if (!defined('ABSPATH')) {
	exit(1);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

$run_id = str_replace('.', '', uniqid('aft', true));
$checks = 0;

$assert = static function ($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};

$pass = static function ($label) use (&$checks) {
	$checks++;
	echo "PASS: {$label}\n";
};

/** Current revision number for one tracked feed section (schedule/hours/info/meta). */
function onlinesched_app_feed_test_rev($section) {
	$revisions = onlinesched_get_feed_revisions();
	return $revisions[$section]['rev'];
}

/** JSON-ish type classifier used by the shape comparator. */
function onlinesched_app_feed_test_type($value) {
	if (is_bool($value)) {
		return 'boolean';
	}
	if (is_int($value) || is_float($value)) {
		return 'number';
	}
	if (is_string($value)) {
		return 'string';
	}
	if (null === $value) {
		return 'null';
	}
	if (is_array($value)) {
		return onlinesched_app_feed_test_is_list($value) ? 'list' : 'object';
	}
	return gettype($value);
}

/** True when $value is a JSON-array-shaped PHP array (sequential 0..n-1 keys, or empty). */
function onlinesched_app_feed_test_is_list($value) {
	if (array() === $value) {
		return true;
	}
	return array_keys($value) === range(0, count($value) - 1);
}

/**
 * Recursively assert that $actual has the same key set and value types as
 * $expected. Arrays-of-objects compare element shape against the first
 * fixture element (only when $actual is non-empty — an empty result array
 * is a valid shape on its own, e.g. an unpublished schedule).
 *
 * @param mixed    $actual
 * @param mixed    $expected
 * @param string   $path
 * @param string[] $errors  Appended by reference.
 */
function onlinesched_app_feed_test_shape($actual, $expected, $path, array &$errors) {
	$expected_type = onlinesched_app_feed_test_type($expected);
	$actual_type = onlinesched_app_feed_test_type($actual);

	if ('list' === $expected_type) {
		if ('list' !== $actual_type) {
			$errors[] = "{$path}: expected a list, got {$actual_type}";
			return;
		}
		if (!empty($expected) && !empty($actual)) {
			onlinesched_app_feed_test_shape($actual[0], $expected[0], "{$path}[0]", $errors);
		}
		return;
	}

	if ('object' === $expected_type) {
		if ('object' !== $actual_type) {
			$errors[] = "{$path}: expected an object, got {$actual_type}";
			return;
		}
		$expected_keys = array_keys($expected);
		$actual_keys = array_keys($actual);
		sort($expected_keys);
		sort($actual_keys);
		if ($expected_keys !== $actual_keys) {
			$errors[] = sprintf(
				'%s: key mismatch. expected [%s] got [%s]',
				$path,
				implode(', ', array_keys($expected)),
				implode(', ', array_keys($actual))
			);
			return;
		}
		foreach ($expected as $key => $value) {
			onlinesched_app_feed_test_shape($actual[$key], $value, "{$path}.{$key}", $errors);
		}
		return;
	}

	if ($actual_type !== $expected_type) {
		$errors[] = "{$path}: expected type {$expected_type}, got {$actual_type}";
	}
}

/** Round-trip a PHP value through JSON the same way wp_send_json() would present it. */
function onlinesched_app_feed_test_normalize($value) {
	return json_decode(wp_json_encode($value), true);
}

$fixtures_dir = dirname(__DIR__) . '/fixtures/app-feed';
$load_fixture = static function ($name) use ($fixtures_dir) {
	$path = $fixtures_dir . '/' . $name;
	$raw = file_get_contents($path);
	if (false === $raw) {
		throw new RuntimeException("Missing contract fixture: {$path}");
	}
	$decoded = json_decode($raw, true);
	if (null === $decoded && JSON_ERROR_NONE !== json_last_error()) {
		throw new RuntimeException("Invalid JSON in contract fixture: {$path}");
	}
	return $decoded;
};

$assert_shape = static function ($actual, $expected, $label) use ($assert) {
	$errors = array();
	onlinesched_app_feed_test_shape(
		onlinesched_app_feed_test_normalize($actual),
		$expected,
		$label,
		$errors
	);
	$assert(empty($errors), "{$label} shape mismatch:\n" . implode("\n", $errors));
};

// Shape assertions are exactly what this suite exists to police, so a shape
// mismatch is recorded rather than aborting the run: every other independent
// check still executes and reports, and the overall run still fails at the
// end if anything was recorded here.
$shape_failures = array();
$check_shape = static function ($label, callable $fn) use (&$shape_failures, &$checks) {
	try {
		$fn();
		$checks++;
		echo "PASS: {$label}\n";
	} catch (RuntimeException $e) {
		$shape_failures[] = $label . ' -- ' . $e->getMessage();
		echo "FAIL: {$label} -- " . $e->getMessage() . "\n";
	}
};

// ---------------------------------------------------------------------------
// State capture (restored in the finally block below)
// ---------------------------------------------------------------------------

$option_names = array(
	'onlinesched_year',
	'onlinesched_app_schedule_published',
	'onlinesched_con_start',
	'onlinesched_con_end',
	'onlinesched_public_date_start',
	'onlinesched_public_date_end',
	'onlinesched_app_info_page_ids',
	'onlinesched_hours_page_id',
	'onlinesched_calendar_name',
	'onlinesched_json_room_groups',
	// A core WP option — captured/restored defensively as a safety net on top
	// of this test's own inline restore around the blogname check below.
	'blogname',
	// Retired legacy serialized blob — captured/restored defensively in case
	// anything (including our own legacy-migration test below) touches it.
	'onlinesched_feed_revisions',
	'onlinesched_feed_schema_installed',
	'timezone_string',
	'gmt_offset',
);
// Per-section revision storage: two plain option rows per tracked section.
foreach (array('schedule', 'hours', 'info', 'meta') as $onlinesched_aft_section) {
	$option_names[] = 'onlinesched_feed_rev_' . $onlinesched_aft_section;
	$option_names[] = 'onlinesched_feed_revtime_' . $onlinesched_aft_section;
}
unset($onlinesched_aft_section);

$missing = '__onlinesched_app_feed_test_missing__';
$original_options = array();
foreach ($option_names as $name) {
	$original_options[$name] = get_option($name, $missing);
}

/**
 * Seed the per-section revision rows directly (test-only convenience — the
 * real code always mutates them through onlinesched_feed_atomic_bump() /
 * update_option(), never a blob write). Absent-row baseline is rev 1.
 *
 * @param array<string, array{rev:int, time:int}> $entries
 */
$seed_feed_revisions = static function (array $entries) {
	foreach ($entries as $section => $entry) {
		update_option('onlinesched_feed_rev_' . $section, (string) (int) $entry['rev'], false);
		update_option('onlinesched_feed_revtime_' . $section, (int) $entry['time'], false);
	}
};

$created_post_ids = array();
$created_term_ids = array(); // taxonomy => [term_id, ...]
$created_files = array();

$restore = static function () use (&$original_options, $missing, &$created_post_ids, &$created_term_ids, &$created_files) {
	foreach ($created_post_ids as $post_id) {
		if ($post_id && get_post($post_id)) {
			wp_delete_post($post_id, true);
		}
	}
	foreach ($created_term_ids as $taxonomy => $ids) {
		foreach ($ids as $term_id) {
			if (get_term($term_id, $taxonomy) && !is_wp_error(get_term($term_id, $taxonomy))) {
				wp_delete_term($term_id, $taxonomy);
			}
		}
	}
	foreach ($created_files as $file) {
		if (is_file($file)) {
			unlink($file);
		}
	}
	foreach ($original_options as $name => $value) {
		if ($missing === $value) {
			delete_option($name);
		} else {
			update_option($name, $value);
		}
	}
};

try {

	// -------------------------------------------------------------------
	// A. Revision service (public stamp is 3-part again; meta rev is internal)
	// -------------------------------------------------------------------

	$seed_feed_revisions(array(
		'schedule' => array('rev' => 5, 'time' => 100),
		'hours'    => array('rev' => 2, 'time' => 100),
		'info'     => array('rev' => 9, 'time' => 100),
		'meta'     => array('rev' => 3, 'time' => 100),
	));
	$assert('5.2.9' === onlinesched_get_feed_change_stamp(), 'The public change stamp must compose as schedule.hours.info (3-part).');
	$pass('public change stamp composes as schedule.hours.info (3-part)');

	$expected_meta_etag = '"os-' . ONLINESCHED_APP_FEED_SCHEMA_VERSION . '-meta-5.2.9+3"';
	$assert(
		$expected_meta_etag === onlinesched_app_feed_etag('meta'),
		"Meta ETag must be '\"os-{schema}-meta-{public_stamp}+{internal_meta_rev}\"'; got " . onlinesched_app_feed_etag('meta')
	);
	$pass('meta ETag format is "os-{schema}-meta-{public_stamp}+{internal_meta_rev}"');

	// onlinesched_app_feed_send() now hashes the EXACT response bytes and
	// passes that hash through as a 4th arg, appended as '-' + the first 20
	// chars of the hash — the payload-hash property (ETag changes iff the
	// representation changes) lives in this format, so pin it directly.
	$sample_body = wp_json_encode(array('a' => 1));
	$sample_hash = md5($sample_body);
	$etag_with_hash = onlinesched_app_feed_etag('schedule', '', null, $sample_hash);
	$etag_without_hash = onlinesched_app_feed_etag('schedule', '', null, '');
	$assert(
		false !== strpos($etag_with_hash, '-' . substr($sample_hash, 0, 20) . '"'),
		"A passed content hash must be appended as '-' + its first 20 chars; got {$etag_with_hash}"
	);
	$assert($etag_with_hash !== $etag_without_hash, 'Passing a content hash must change the ETag relative to not passing one.');
	$pass('onlinesched_app_feed_etag() appends the first 20 chars of a passed content hash');

	$before = onlinesched_get_feed_revisions();
	$bumped = onlinesched_touch_feed('hours', 'unit-test');
	$after = onlinesched_get_feed_revisions();
	$assert(true === $bumped, 'Touching hours must report a bump.');
	$assert($after['hours']['rev'] === $before['hours']['rev'] + 1, 'Hours revision must increment by exactly one.');
	$assert($after['schedule']['rev'] === $before['schedule']['rev'], 'Touching hours must not move schedule.');
	$assert($after['info']['rev'] === $before['info']['rev'], 'Touching hours must not move info.');
	$assert($after['meta']['rev'] === $before['meta']['rev'], 'Touching hours must not move the internal meta revision.');
	$pass('touch_feed bumps only the named section');

	$before_meta_only = onlinesched_get_feed_revisions();
	$meta_etag_before_direct = onlinesched_app_feed_etag('meta');
	$stamp_before_direct = onlinesched_get_feed_change_stamp();
	onlinesched_touch_feed('meta', 'unit-test');
	$after_meta_only = onlinesched_get_feed_revisions();
	$meta_etag_after_direct = onlinesched_app_feed_etag('meta');
	$stamp_after_direct = onlinesched_get_feed_change_stamp();
	$assert($after_meta_only['meta']['rev'] === $before_meta_only['meta']['rev'] + 1, 'Touching the internal meta section directly must bump its internal revision by exactly one.');
	$assert($meta_etag_after_direct !== $meta_etag_before_direct, 'Touching the internal meta section directly must move the meta ETag.');
	$assert($stamp_after_direct === $stamp_before_direct, 'Touching the internal meta section directly must NOT move the public change_stamp.');
	$assert($after_meta_only['schedule']['rev'] === $before_meta_only['schedule']['rev'], 'Touching meta alone must not move schedule.');
	$assert($after_meta_only['hours']['rev'] === $before_meta_only['hours']['rev'], 'Touching meta alone must not move hours.');
	$assert($after_meta_only['info']['rev'] === $before_meta_only['info']['rev'], 'Touching meta alone must not move info.');
	$pass('touch_feed can bump the internal meta section directly: internal rev + meta ETag move, public change_stamp does not');

	$before2 = onlinesched_get_feed_revisions();
	$bumped_none = onlinesched_touch_feed('not-a-real-section', 'unit-test');
	$assert(false === $bumped_none, 'Touching an unknown section must report no bump.');
	$assert(onlinesched_get_feed_revisions() == $before2, 'Touching an unknown section must not change revisions.');
	$pass('touch_feed ignores unknown section names');

	$before3 = onlinesched_get_feed_revisions();
	onlinesched_touch_feed(array('schedule', 'info'), 'unit-test');
	$after3 = onlinesched_get_feed_revisions();
	$assert($after3['schedule']['rev'] === $before3['schedule']['rev'] + 1, 'Multi-section touch must bump schedule.');
	$assert($after3['info']['rev'] === $before3['info']['rev'] + 1, 'Multi-section touch must bump info.');
	$assert($after3['hours']['rev'] === $before3['hours']['rev'], 'Multi-section touch must not bump the section not named.');
	$pass('touch_feed bumps multiple named sections together');

	$touched_events = array();
	$capture_touch = static function ($sections, $reason) use (&$touched_events) {
		$touched_events[] = array($sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_touch, 10, 2);

	onlinesched_feed_touch_suspend();
	$before4 = onlinesched_get_feed_revisions();
	$swallowed = onlinesched_touch_feed('schedule', 'suspended-test');
	$assert(false === $swallowed, 'A touch during suspension must report no bump.');
	$assert(onlinesched_get_feed_revisions() == $before4, 'A touch during suspension must not change revisions.');
	$assert(0 === count($touched_events), 'A touch during suspension must not fire onlinesched_feed_touched.');
	onlinesched_feed_touch_resume();
	$pass('suspension swallows touches and skips the touched action');

	onlinesched_feed_touch_suspend();
	onlinesched_feed_touch_suspend();
	onlinesched_feed_touch_resume();
	$before5 = onlinesched_get_feed_revisions();
	$still_swallowed = onlinesched_touch_feed('schedule', 'nested-test');
	$assert(false === $still_swallowed, 'One resume out of a depth-2 suspension must still swallow touches.');
	$assert(onlinesched_get_feed_revisions() == $before5, 'A depth-1 suspension must still swallow touches.');
	onlinesched_feed_touch_resume();
	$fully_resumed = onlinesched_touch_feed('schedule', 'nested-test-2');
	$assert(true === $fully_resumed, 'Touches must resume once suspension depth returns to zero.');
	remove_action('onlinesched_feed_touched', $capture_touch, 10);
	$pass('nested suspend/resume tracks depth correctly');

	// -------------------------------------------------------------------
	// A1b. Regressing revtime: onlinesched_feed_advance_revtime() uses SQL
	// GREATEST so an older concurrent timestamp can never regress the stored
	// time.
	// -------------------------------------------------------------------

	onlinesched_touch_feed('schedule', 'revtime-baseline');
	$revtime_t1 = onlinesched_get_feed_revisions()['schedule']['time'];
	$assert($revtime_t1 > 0, 'Sanity: schedule revtime must be a real timestamp after a touch.');

	onlinesched_feed_advance_revtime('schedule', $revtime_t1 - 1000);
	$revtime_after_regress_attempt = onlinesched_get_feed_revisions()['schedule']['time'];
	$assert(
		$revtime_after_regress_attempt === $revtime_t1,
		"An older concurrent timestamp must never regress the stored revtime; expected {$revtime_t1}, got {$revtime_after_regress_attempt}."
	);

	onlinesched_feed_advance_revtime('schedule', $revtime_t1 + 5);
	$revtime_after_advance = onlinesched_get_feed_revisions()['schedule']['time'];
	$assert(
		$revtime_after_advance === $revtime_t1 + 5,
		"A newer timestamp must advance the stored revtime; expected " . ($revtime_t1 + 5) . ", got {$revtime_after_advance}."
	);
	$pass('onlinesched_feed_advance_revtime() never regresses (SQL GREATEST) and does advance forward');

	// -------------------------------------------------------------------
	// A1c. Atomic-bump failure fallback: onlinesched_feed_atomic_bump() must
	// never silently no-op when the underlying SQL genuinely fails — it must
	// fall back to a guaranteed-movement update_option() path. Exercised
	// against a throwaway, isolated fake "section" — never a real tracked
	// section — via a real SQL syntax error injected through WordPress's
	// 'query' filter (applied to every $wpdb query), scoped to match only
	// this fake option's name and to fire exactly once, so it cannot affect
	// any other query on the site.
	// -------------------------------------------------------------------

	$atomic_fake_section = 'aft-atomic-test-' . $run_id;
	$atomic_fake_option = 'onlinesched_feed_rev_' . $atomic_fake_section;
	delete_option($atomic_fake_option);

	$before_happy = (int) get_option($atomic_fake_option, 1);
	onlinesched_feed_atomic_bump($atomic_fake_section);
	$after_happy = (int) get_option($atomic_fake_option, 1);
	$assert($after_happy === $before_happy + 1, 'The happy-path atomic bump (fresh/absent row) must move the counter by exactly one.');

	$before_happy_2 = $after_happy;
	onlinesched_feed_atomic_bump($atomic_fake_section);
	$after_happy_2 = (int) get_option($atomic_fake_option, 1);
	$assert($after_happy_2 === $before_happy_2 + 1, 'A second happy-path atomic bump (real UPDATE, row now exists) must also move the counter by exactly one.');
	$pass('onlinesched_feed_atomic_bump() happy path moves the counter by exactly one, both fresh-row and existing-row');

	$sql_break_count = 0;
	$sql_break_filter = static function ($query) use ($atomic_fake_option, &$sql_break_count) {
		if (0 === $sql_break_count && false !== strpos($query, $atomic_fake_option)) {
			$sql_break_count++;
			return 'THIS IS DELIBERATELY INVALID SQL TO FORCE A QUERY ERROR -- ' . $atomic_fake_option;
		}
		return $query;
	};
	add_filter('query', $sql_break_filter);
	$before_failure = (int) get_option($atomic_fake_option, 1);
	onlinesched_feed_atomic_bump($atomic_fake_section);
	remove_filter('query', $sql_break_filter);
	wp_cache_delete($atomic_fake_option, 'options'); // re-read past any cache state left by the corrupted attempt
	$after_failure = (int) get_option($atomic_fake_option, 1);
	$assert(1 === $sql_break_count, 'Sanity: the query-corruption filter must have actually fired exactly once.');
	$assert(
		$after_failure === $before_failure + 1,
		"A real SQL failure on the atomic UPDATE must still guarantee movement via the update_option() fallback, not a silent no-op; before={$before_failure} after={$after_failure}"
	);
	$pass('onlinesched_feed_atomic_bump() falls back to guaranteed movement (not a silent no-op) when the atomic SQL genuinely fails');

	delete_option($atomic_fake_option);

	// -------------------------------------------------------------------
	// A1c2. Persistent SQL failure (round-8 regression a): corrupt BOTH the
	// direct UPDATE/INSERT path AND the update_option() fallback for the
	// WHOLE window (not just once) — atomic_bump() must return false,
	// touch_feed() must return false, and onlinesched_feed_touched must NOT
	// fire. Uses the real 'schedule' section (touch_feed only exercises its
	// real "nothing moved" branch for a genuinely valid tracked section), but
	// it is safe: every write attempt fails, so the stored value never
	// changes — a complete no-op from the DB's perspective — and this test
	// proves recovery immediately afterward.
	// -------------------------------------------------------------------

	$rev_before_persistent_failure = onlinesched_app_feed_test_rev('schedule');

	$persistent_break_filter = static function ($query) {
		if (false !== strpos($query, 'onlinesched_feed_rev_schedule')) {
			return 'THIS IS DELIBERATELY INVALID SQL TO FORCE A PERSISTENT QUERY ERROR ON onlinesched_feed_rev_schedule';
		}
		return $query;
	};
	add_filter('query', $persistent_break_filter);

	$touched_during_failure = array();
	$capture_persistent = static function ($sections, $reason) use (&$touched_during_failure) {
		$touched_during_failure[] = array($sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_persistent, 10, 2);

	$persistent_bump_result = onlinesched_feed_atomic_bump('schedule');
	$persistent_touch_result = onlinesched_touch_feed('schedule', 'unit-test-persistent-failure');

	remove_filter('query', $persistent_break_filter);
	remove_action('onlinesched_feed_touched', $capture_persistent, 10);
	wp_cache_delete('onlinesched_feed_rev_schedule', 'options');

	$assert(false === $persistent_bump_result, 'onlinesched_feed_atomic_bump() must return false under a persistent SQL failure spanning the UPDATE/INSERT path and the update_option fallback.');
	$assert(false === $persistent_touch_result, "onlinesched_touch_feed() must return false when its only requested section fails to move; got " . var_export($persistent_touch_result, true));
	$assert(0 === count($touched_during_failure), 'onlinesched_feed_touched must NOT fire when nothing actually moved.');
	$assert(
		onlinesched_app_feed_test_rev('schedule') === $rev_before_persistent_failure,
		'The schedule revision must be completely unchanged after a persistent failure in which every write attempt failed.'
	);

	$touched_after_recovery = array();
	$capture_recovery = static function ($sections, $reason) use (&$touched_after_recovery) {
		$touched_after_recovery[] = array($sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_recovery, 10, 2);
	$recovered_result = onlinesched_touch_feed('schedule', 'unit-test-recovery');
	remove_action('onlinesched_feed_touched', $capture_recovery, 10);

	$assert(true === $recovered_result, 'A normal touch after removing the corruption must succeed.');
	$assert(
		onlinesched_app_feed_test_rev('schedule') > $rev_before_persistent_failure,
		'The schedule revision must move once the corruption is removed.'
	);
	$assert(
		1 === count($touched_after_recovery) && in_array('schedule', $touched_after_recovery[0][0], true),
		'The recovered touch must fire onlinesched_feed_touched exactly once, reporting schedule as moved.'
	);
	$pass('atomic_bump()/touch_feed() report honest failure under a persistent SQL error spanning the fallback too (no false success, no touched action fired), and recover cleanly once the corruption is removed');

	// -------------------------------------------------------------------
	// A1d. Snapshot mismatch: onlinesched_app_feed_build_consistent() rebuilds
	// when a revision moves mid-build (bounded 3 attempts), and returns a
	// revisions snapshot consistent with the accepted payload.
	// -------------------------------------------------------------------

	$consistent_build_calls = 0;
	list($consistent_payload, $consistent_revisions) = onlinesched_app_feed_build_consistent(
		static function ($revisions) use (&$consistent_build_calls) {
			$consistent_build_calls++;
			if (1 === $consistent_build_calls) {
				// Simulate a mutation landing mid-build: the snapshot the
				// caller already took is now stale.
				onlinesched_touch_feed('schedule', 'unit-test-consistency');
			}
			return array('call' => $consistent_build_calls, 'schedule_rev_seen' => $revisions['schedule']['rev']);
		}
	);
	$assert(2 === $consistent_build_calls, "onlinesched_app_feed_build_consistent() must rebuild once when a revision moves mid-build (2 total calls); got {$consistent_build_calls}.");
	$post_build_revisions = onlinesched_get_feed_revisions();
	$assert($consistent_revisions == $post_build_revisions, 'The returned revisions must match a fresh post-build read (consistency held).');
	$assert(2 === $consistent_payload['call'], 'The accepted payload must come from the rebuild (2nd call), not the stale first attempt.');
	$assert(
		$consistent_payload['schedule_rev_seen'] === $consistent_revisions['schedule']['rev'],
		'The accepted payload must have been built against the exact schedule revision it reports.'
	);
	$pass('onlinesched_app_feed_build_consistent() rebuilds exactly once when a revision moves mid-build, and the returned revisions match a fresh read');

	// -------------------------------------------------------------------
	// A1d2. Retry exhaustion / torn pair (round-8 regression b): when EVERY
	// build invocation keeps moving the revision, build_consistent() must
	// still return a COHERENT (payload, revisions) pair — never torn — even
	// though it is stale relative to a fresh read once retries exhaust.
	// -------------------------------------------------------------------

	$torn_build_calls = 0;
	list($torn_payload, $torn_revisions) = onlinesched_app_feed_build_consistent(
		static function ($revisions) use (&$torn_build_calls) {
			$torn_build_calls++;
			// Keep moving the revision on every single invocation, so the
			// snapshot the caller just took is stale again before it can
			// even finish building.
			onlinesched_touch_feed('schedule', 'unit-test-torn-' . $torn_build_calls);
			return array('call' => $torn_build_calls, 'schedule_rev_seen' => $revisions['schedule']['rev']);
		}
	);
	$assert(3 === $torn_build_calls, "build_consistent() must attempt exactly 3 builds (1 initial + 2 retries) when every build keeps moving the revision; got {$torn_build_calls}.");
	$assert(
		$torn_payload['schedule_rev_seen'] === $torn_revisions['schedule']['rev'],
		'The accepted (possibly stale) payload must still be internally coherent with the returned revisions snapshot — never a torn pair.'
	);
	$fresh_after_torn = onlinesched_get_feed_revisions();
	$assert(
		$fresh_after_torn['schedule']['rev'] > $torn_revisions['schedule']['rev'],
		'Sanity: a fresh read after retry exhaustion must be newer than the accepted (stale) snapshot, proving the pair is genuinely stale, not just coincidentally fresh.'
	);
	$pass('onlinesched_app_feed_build_consistent() returns a coherent (payload, revisions) pair even under continuous churn (retry exhaustion) — stale is acceptable, torn is not');

	// -------------------------------------------------------------------
	// A1e. Snapshot-derived change_stamp (round-8 regression c):
	// onlinesched_app_feed_meta() must compose change_stamp and the
	// revisions map strictly from its supplied snapshot, never from a live
	// onlinesched_get_feed_change_stamp()/onlinesched_get_feed_revisions() read.
	// -------------------------------------------------------------------

	$handcrafted_snapshot = array(
		'schedule' => array('rev' => 101, 'time' => 1700000001),
		'hours'    => array('rev' => 202, 'time' => 1700000002),
		'info'     => array('rev' => 303, 'time' => 1700000003),
		'meta'     => array('rev' => 7, 'time' => 1700000004),
	);
	$handcrafted_meta = onlinesched_app_feed_meta($handcrafted_snapshot);
	$assert(
		'101.202.303' === $handcrafted_meta['change_stamp'],
		"change_stamp must be composed strictly from the supplied snapshot; got {$handcrafted_meta['change_stamp']}."
	);
	$assert(
		array('schedule' => 101, 'hours' => 202, 'info' => 303) === $handcrafted_meta['revisions'],
		'The revisions map must match the supplied snapshot exactly, regardless of live values: got ' . wp_json_encode($handcrafted_meta['revisions'])
	);
	// Sanity: the handcrafted values must not accidentally coincide with live
	// revisions, or this test would pass even if onlinesched_app_feed_meta()
	// silently read through to a live snapshot instead of using the one supplied.
	$live_revisions_for_contrast = onlinesched_get_feed_revisions();
	$assert(
		101 !== $live_revisions_for_contrast['schedule']['rev']
			|| 202 !== $live_revisions_for_contrast['hours']['rev']
			|| 303 !== $live_revisions_for_contrast['info']['rev'],
		'Sanity: the handcrafted snapshot must not accidentally coincide with live revisions (would make this test a false positive).'
	);
	$pass('onlinesched_app_feed_meta() composes change_stamp and revisions strictly from its supplied snapshot, never a live read');

	// -------------------------------------------------------------------
	// A2. Pure validators
	// -------------------------------------------------------------------

	$assert('' === onlinesched_app_sanitize_date('2026-13-45'), "onlinesched_app_sanitize_date('2026-13-45') must reject an invalid calendar date.");
	$assert('2026-09-11' === onlinesched_app_sanitize_date('2026-09-11'), "onlinesched_app_sanitize_date('2026-09-11') must pass through a valid calendar date.");
	$assert('' === onlinesched_app_sanitize_date('not-a-date'), 'onlinesched_app_sanitize_date() must reject a non-date string.');
	$assert('' === onlinesched_app_sanitize_date(''), 'onlinesched_app_sanitize_date() must reject an empty string.');
	$pass('onlinesched_app_sanitize_date() enforces real calendar dates via checkdate()');

	// -------------------------------------------------------------------
	// A3. con_name fallback chain: calendar_name -> site name -> literal default
	// -------------------------------------------------------------------

	$calendar_name_for_fallback = get_option('onlinesched_calendar_name', $missing);
	update_option('onlinesched_calendar_name', '');
	$meta_blank_name = onlinesched_app_feed_meta();
	$site_name = trim((string) get_bloginfo('name'));
	$expected_con_name = ('' !== $site_name) ? $site_name : 'Convention Schedule';
	$assert('' !== trim((string) $meta_blank_name['con_name']), 'con_name must never be blank.');
	$assert($expected_con_name === $meta_blank_name['con_name'], "A blank onlinesched_calendar_name must fall back to the site name (or 'Convention Schedule' when the site name is also blank); got '{$meta_blank_name['con_name']}', expected '{$expected_con_name}'.");
	if ($missing === $calendar_name_for_fallback) {
		delete_option('onlinesched_calendar_name');
	} else {
		update_option('onlinesched_calendar_name', $calendar_name_for_fallback);
	}
	$pass('con_name falls back from a blank calendar_name to the site name');

	// -------------------------------------------------------------------
	// A4. Versioned upgrade: legacy serialized-blob migration (schema v3)
	// -------------------------------------------------------------------

	// Clear the modern per-section rows first so the migration path's
	// add_option() calls (which no-op against an existing row) actually take
	// effect, rather than exercising the "seed missing rows at 1" path.
	foreach (array('schedule', 'hours', 'info', 'meta') as $onlinesched_aft_legacy_section) {
		delete_option('onlinesched_feed_rev_' . $onlinesched_aft_legacy_section);
		delete_option('onlinesched_feed_revtime_' . $onlinesched_aft_legacy_section);
	}
	unset($onlinesched_aft_legacy_section);

	$legacy_blob_time = 1700000000;
	update_option('onlinesched_feed_revisions', array(
		'schedule' => array('rev' => 41, 'time' => $legacy_blob_time),
		'hours'    => array('rev' => 7, 'time' => $legacy_blob_time),
		'info'     => array('rev' => 12, 'time' => $legacy_blob_time),
		'meta'     => array('rev' => 3, 'time' => $legacy_blob_time),
	));
	$assert(false !== get_option('onlinesched_feed_revisions', false), 'Sanity: the legacy blob must actually be present before migration.');

	update_option('onlinesched_feed_schema_installed', 0);
	onlinesched_feed_maybe_upgrade();

	$assert(false === get_option('onlinesched_feed_revisions', false), 'The legacy serialized revisions blob must be deleted after migration.');
	$assert(
		(int) get_option('onlinesched_feed_schema_installed', 0) >= 3,
		'onlinesched_feed_maybe_upgrade() must record schema version >= 3 (ONLINESCHED_FEED_SCHEMA_INSTALL_VERSION) after migrating the legacy blob.'
	);

	// The upgrade's final step touches the three FETCHABLE sections once
	// (onlinesched_touch_feed(onlinesched_feed_sections(), 'feed-schema-upgrade')),
	// so schedule/hours/info land at their migrated value + 1; 'meta' is not a
	// fetchable section, so the final touch does not bump it — it stays at
	// its migrated value.
	$migrated = onlinesched_get_feed_revisions();
	$assert(42 === $migrated['schedule']['rev'], "Migrated schedule rev must be 41+1=42 after the upgrade's final touch; got {$migrated['schedule']['rev']}.");
	$assert(8 === $migrated['hours']['rev'], "Migrated hours rev must be 7+1=8; got {$migrated['hours']['rev']}.");
	$assert(13 === $migrated['info']['rev'], "Migrated info rev must be 12+1=13; got {$migrated['info']['rev']}.");
	$assert(3 === $migrated['meta']['rev'], "Migrated meta rev must stay at 3 (meta is not a fetchable section, so the final touch does not bump it); got {$migrated['meta']['rev']}.");
	$assert(
		'42.8.13' === onlinesched_get_feed_change_stamp(),
		'The public change stamp must reflect the migrated values plus the upgrade\'s final touch; got ' . onlinesched_get_feed_change_stamp()
	);
	$pass('onlinesched_feed_maybe_upgrade() migrates the legacy serialized blob into per-section rows, deletes the blob, and its final touch bumps the three fetchable sections');

	// -------------------------------------------------------------------
	// A4b. Upgrade exception (round-8 regression d): the v3 upgrade's UUID
	// backfill wraps its suspend/resume in try/finally, so a throw from a
	// meta hook mid-backfill must not leave touch handling stuck suspended.
	// -------------------------------------------------------------------

	$upgrade_exception_event_id = wp_insert_post(array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'post_title'  => 'AFT Upgrade Exception Event ' . $run_id,
		'meta_input'  => array('onlinesched_year' => 'aft-upgrade-exception-' . $run_id),
	), true);
	$assert(!is_wp_error($upgrade_exception_event_id), 'Upgrade-exception fixture event must be created.');
	$created_post_ids[] = $upgrade_exception_event_id;
	// The save hook already assigned a uuid; delete it so the upgrade's
	// backfill loop actually has something to write (and hook into) for it.
	delete_post_meta($upgrade_exception_event_id, 'onlinesched_event_uid');

	$throw_on_uuid_backfill = static function ($meta_id, $object_id, $meta_key) use ($upgrade_exception_event_id) {
		if ((int) $object_id === (int) $upgrade_exception_event_id && 'onlinesched_event_uid' === $meta_key) {
			throw new RuntimeException('AFT forced upgrade backfill exception');
		}
	};
	add_action('added_post_meta', $throw_on_uuid_backfill, 1000, 3);

	update_option('onlinesched_feed_schema_installed', 0);
	$upgrade_exception_thrown = null;
	try {
		onlinesched_feed_maybe_upgrade();
	} catch (\Throwable $e) {
		$upgrade_exception_thrown = $e;
	}
	remove_action('added_post_meta', $throw_on_uuid_backfill, 1000);

	$assert(null !== $upgrade_exception_thrown, 'The forced exception must have propagated out of onlinesched_feed_maybe_upgrade().');
	$assert($upgrade_exception_thrown instanceof RuntimeException, 'The propagated exception must be the one this test threw, not something else.');
	$assert(0 === onlinesched_feed_touch_suspension(), 'Suspension depth must return to 0 after the upgrade exception propagates (try/finally around the uuid backfill).');

	$rev_after_upgrade_exception = onlinesched_app_feed_test_rev('schedule');
	$post_upgrade_exception_touch = onlinesched_touch_feed('schedule', 'post-upgrade-exception-sanity');
	$assert(
		true === $post_upgrade_exception_touch && onlinesched_app_feed_test_rev('schedule') > $rev_after_upgrade_exception,
		'A normal touch after the upgrade exception must still work (suspension not stuck).'
	);
	$pass('onlinesched_feed_maybe_upgrade(): an exception during the uuid backfill still leaves suspension depth at 0 (try/finally), and a subsequent touch works normally');

	// -------------------------------------------------------------------
	// B. Mutation-to-section matrix — real mutations only, never do_action().
	// Single-mutation deltas assert "moved" (>), not an exact count: the
	// coalescing logic in feed-revisions.php is free to change how many hooks
	// contribute to one logical mutation. Exactly-once assertions are kept
	// only for the CSV import batch and delete-year batch (section C).
	// -------------------------------------------------------------------

	$b_year = 'aft-matrix-' . $run_id;

	$room = wp_insert_term('AFT Matrix Room ' . $run_id, 'os_room');
	$assert(!is_wp_error($room), 'Matrix room term must be created: ' . (is_wp_error($room) ? $room->get_error_message() : ''));
	$created_term_ids['os_room'][] = $room['term_id'];

	$event_id = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Matrix Event ' . $run_id,
		'post_content' => 'Mutation-matrix fixture event.',
		'meta_input'   => array(
			'onlinesched_year'     => $b_year,
			'onlinesched_sorttime' => time(),
			'onlinesched_timelen'  => '30',
		),
	), true);
	$assert(!is_wp_error($event_id) && $event_id > 0, 'Matrix event must be created.');
	$created_post_ids[] = $event_id;
	wp_set_object_terms($event_id, array($room['term_id']), 'os_room', false);

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_update_post(array('ID' => $event_id, 'post_title' => 'AFT Matrix Event ' . $run_id . ' (retitled)'));
	$assert('publish' === get_post_status($event_id), 'Sanity: the retitle must not change post status.');
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'A same-status title change on a published event must move schedule.');
	$pass('same-status title change on a published event moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_update_post(array('ID' => $event_id, 'post_content' => 'Mutation-matrix fixture event, edited.'));
	$assert('publish' === get_post_status($event_id), 'Sanity: the content edit must not change post status.');
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'A same-status content change on a published event must move schedule.');
	$pass('same-status content change on a published event moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	update_post_meta($event_id, 'onlinesched_sorttime', time() + 3600);
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'update_post_meta(onlinesched_sorttime) on an event must move schedule.');
	$pass('update_post_meta(onlinesched_sorttime) moves schedule');

	// onlinesched_event_uid is now in onlinesched_feed_output_meta_keys(), so
	// changing it on a published event (real mutation) must move schedule too.
	$rev = onlinesched_app_feed_test_rev('schedule');
	update_post_meta($event_id, 'onlinesched_event_uid', wp_generate_uuid4());
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'update_post_meta(onlinesched_event_uid) on a published event must move schedule.');
	$pass('update_post_meta(onlinesched_event_uid) moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_update_post(array('ID' => $event_id, 'post_status' => 'draft'));
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'publish -> draft must move schedule.');
	$pass('status transition publish -> draft moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_update_post(array('ID' => $event_id, 'post_status' => 'publish'));
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'draft -> publish must move schedule.');
	$pass('status transition draft -> publish moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_trash_post($event_id);
	$assert('trash' === get_post_status($event_id), 'Fixture event must actually be trashed.');
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'trash must move schedule.');
	$pass('trash moves schedule');

	// Since WordPress 5.6, wp_untrash_post() restores to 'draft' by default
	// (which correctly does not need a schedule bump — a draft event isn't in
	// the public feed either way). The admin "Restore" action additionally
	// wires wp_untrash_post_set_previous_status() so a previously published
	// event actually comes back as published; mirror that here so this
	// checks the real admin-restore path.
	add_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3);
	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_untrash_post($event_id);
	remove_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10);
	$assert('publish' === get_post_status($event_id), 'Untrash (mirroring the admin Restore action) must restore the prior publish status for this fixture.');
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'untrash back to publish must move schedule.');
	$pass('untrash back to publish moves schedule');

	$room2 = wp_insert_term('AFT Matrix Room Two ' . $run_id, 'os_room');
	$assert(!is_wp_error($room2), 'Second matrix room term must be created.');
	$created_term_ids['os_room'][] = $room2['term_id'];

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_set_object_terms($event_id, array($room2['term_id']), 'os_room', false);
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'Changing room-term assignment must move schedule.');
	$pass('set_object_terms change moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_set_object_terms($event_id, array($room2['term_id']), 'os_room', false);
	$assert(onlinesched_app_feed_test_rev('schedule') === $rev, 'Re-assigning the identical room terms must not move schedule at all.');
	$pass('no-op set_object_terms does not move schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	$renamed = wp_update_term($room2['term_id'], 'os_room', array('name' => 'AFT Matrix Room Two Renamed ' . $run_id));
	$assert(!is_wp_error($renamed), 'Term rename must succeed: ' . (is_wp_error($renamed) ? $renamed->get_error_message() : ''));
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'Renaming an os_room term must move schedule.');
	$pass('term rename moves schedule');

	$rev = onlinesched_app_feed_test_rev('schedule');
	wp_delete_post($event_id, true);
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'Permanent delete must move schedule.');
	$created_post_ids = array_values(array_diff($created_post_ids, array($event_id)));
	$pass('permanent delete moves schedule');

	$distinct_year_value = 'aft-option-year-' . $run_id;
	$distinct_room_groups_value = wp_json_encode(array('aft-test-group-' . $run_id => array('rooms' => array())));
	$option_bump_cases = array(
		'onlinesched_year'                   => array('schedule', $distinct_year_value),
		'onlinesched_app_schedule_published' => array('schedule', '0'),
		'onlinesched_con_start'              => array('schedule', '2031-01-01'),
		'onlinesched_json_room_groups'       => array('schedule', $distinct_room_groups_value),
		'onlinesched_app_info_page_ids'      => array('info', '999999'),
		'onlinesched_hours_page_id'          => array('hours', '999999'),
	);
	foreach ($option_bump_cases as $option_name => $case) {
		list($section, $new_value) = $case;
		$current = get_option($option_name, null);
		if ((string) $current === (string) $new_value) {
			$new_value .= '-x';
		}
		$rev = onlinesched_app_feed_test_rev($section);
		update_option($option_name, $new_value);
		$assert(
			onlinesched_app_feed_test_rev($section) > $rev,
			"Updating {$option_name} must move the {$section} section."
		);
		$pass("option update: {$option_name} moves {$section}");
	}

	// onlinesched_feed_option_section_map() now also wires delete_option_ —
	// onlinesched_con_start currently has the non-default value set above.
	$rev = onlinesched_app_feed_test_rev('schedule');
	delete_option('onlinesched_con_start');
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'Deleting a tracked option (onlinesched_con_start) must move schedule via the delete_option_ hook.');
	$pass('delete_option on a tracked option moves schedule');

	// timezone_string / gmt_offset change every rendered event offset, so they
	// move schedule too. Restored immediately (not just at teardown) since
	// every builder call from here on renders times in the active timezone.
	$timezone_original = get_option('timezone_string', '');
	$new_timezone = ('Europe/London' === $timezone_original) ? 'America/Chicago' : 'Europe/London';
	$rev = onlinesched_app_feed_test_rev('schedule');
	update_option('timezone_string', $new_timezone);
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'Changing timezone_string must move schedule.');
	update_option('timezone_string', $timezone_original);
	$pass('timezone_string change moves schedule');

	$gmt_offset_original = get_option('gmt_offset', 0);
	$new_gmt_offset = ((float) $gmt_offset_original === 5.0) ? '-5' : '5';
	$rev = onlinesched_app_feed_test_rev('schedule');
	update_option('gmt_offset', $new_gmt_offset);
	$assert(onlinesched_app_feed_test_rev('schedule') > $rev, 'Changing gmt_offset must move schedule.');
	update_option('gmt_offset', $gmt_offset_original);
	$pass('gmt_offset change moves schedule');

	// onlinesched_calendar_name only shapes the meta handshake payload: it must
	// move the meta ETag (internal meta revision) but NEVER the public
	// change_stamp or any fetchable section.
	$calendar_name_original = get_option('onlinesched_calendar_name', $missing);
	$new_calendar_name = 'AFT Calendar Name ' . $run_id;
	if ($missing !== $calendar_name_original && (string) $calendar_name_original === $new_calendar_name) {
		$new_calendar_name .= '-x';
	}
	$stamp_before_name_change = onlinesched_get_feed_change_stamp();
	$meta_etag_before_name_change = onlinesched_app_feed_etag('meta');
	$rev_schedule_before = onlinesched_app_feed_test_rev('schedule');
	$rev_hours_before = onlinesched_app_feed_test_rev('hours');
	$rev_info_before = onlinesched_app_feed_test_rev('info');
	update_option('onlinesched_calendar_name', $new_calendar_name);
	$stamp_after_name_change = onlinesched_get_feed_change_stamp();
	$meta_etag_after_name_change = onlinesched_app_feed_etag('meta');
	$assert($stamp_after_name_change === $stamp_before_name_change, 'onlinesched_calendar_name change must NOT move the public change_stamp (schedule.hours.info).');
	$assert($meta_etag_after_name_change !== $meta_etag_before_name_change, 'onlinesched_calendar_name change must move the meta ETag.');
	$assert(onlinesched_app_feed_test_rev('schedule') === $rev_schedule_before, 'onlinesched_calendar_name change must not bump schedule.');
	$assert(onlinesched_app_feed_test_rev('hours') === $rev_hours_before, 'onlinesched_calendar_name change must not bump hours.');
	$assert(onlinesched_app_feed_test_rev('info') === $rev_info_before, 'onlinesched_calendar_name change must not bump info.');
	if ($missing === $calendar_name_original) {
		delete_option('onlinesched_calendar_name');
	} else {
		update_option('onlinesched_calendar_name', $calendar_name_original);
	}
	$pass('onlinesched_calendar_name change moves the meta ETag without moving the public change_stamp or any fetchable section');

	// blogname is the con_name fallback when onlinesched_calendar_name is
	// blank, so it is also a meta-only invalidation surface: same shape as
	// the calendar_name check above.
	$blogname_original = get_option('blogname', $missing);
	$new_blogname = 'AFT Blogname ' . $run_id;
	if ($missing !== $blogname_original && (string) $blogname_original === $new_blogname) {
		$new_blogname .= '-x';
	}
	$stamp_before_blogname_change = onlinesched_get_feed_change_stamp();
	$meta_etag_before_blogname_change = onlinesched_app_feed_etag('meta');
	$rev_schedule_before_bn = onlinesched_app_feed_test_rev('schedule');
	$rev_hours_before_bn = onlinesched_app_feed_test_rev('hours');
	$rev_info_before_bn = onlinesched_app_feed_test_rev('info');
	update_option('blogname', $new_blogname);
	$stamp_after_blogname_change = onlinesched_get_feed_change_stamp();
	$meta_etag_after_blogname_change = onlinesched_app_feed_etag('meta');
	$assert($stamp_after_blogname_change === $stamp_before_blogname_change, 'blogname change must NOT move the public change_stamp.');
	$assert($meta_etag_after_blogname_change !== $meta_etag_before_blogname_change, 'blogname change must move the meta ETag.');
	$assert(onlinesched_app_feed_test_rev('schedule') === $rev_schedule_before_bn, 'blogname change must not bump schedule.');
	$assert(onlinesched_app_feed_test_rev('hours') === $rev_hours_before_bn, 'blogname change must not bump hours.');
	$assert(onlinesched_app_feed_test_rev('info') === $rev_info_before_bn, 'blogname change must not bump info.');
	if ($missing === $blogname_original) {
		delete_option('blogname');
	} else {
		update_option('blogname', $blogname_original);
	}
	$pass('blogname change moves the meta ETag without moving the public change_stamp or any fetchable section');

	$hours_page_id = wp_insert_post(array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Hours Page ' . $run_id,
		'post_content' => '<!-- wp:paragraph --><p>placeholder</p><!-- /wp:paragraph -->',
	), true);
	$assert(!is_wp_error($hours_page_id), 'Hours-page fixture must be created.');
	$created_post_ids[] = $hours_page_id;
	update_option('onlinesched_hours_page_id', $hours_page_id);

	$rev = onlinesched_app_feed_test_rev('hours');
	wp_update_post(array('ID' => $hours_page_id, 'post_content' => '<!-- wp:paragraph --><p>updated</p><!-- /wp:paragraph -->'));
	$assert(onlinesched_app_feed_test_rev('hours') > $rev, 'Saving the configured hours page must move hours.');
	$pass('saving the configured hours page moves hours');

	$info_page_id_for_matrix = wp_insert_post(array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Matrix Info Page ' . $run_id,
		'post_content' => '<p>placeholder</p>',
	), true);
	$assert(!is_wp_error($info_page_id_for_matrix), 'Info-page fixture must be created.');
	$created_post_ids[] = $info_page_id_for_matrix;
	update_option('onlinesched_app_info_page_ids', (string) $info_page_id_for_matrix);

	$rev = onlinesched_app_feed_test_rev('info');
	wp_update_post(array('ID' => $info_page_id_for_matrix, 'post_content' => '<p>updated content</p>'));
	$assert(onlinesched_app_feed_test_rev('info') > $rev, 'Saving a configured app-info page must move info.');
	$pass('saving a configured app-info page moves info');

	// onlinesched_feed_sections_for_page() + the new transition_post_status /
	// before_delete_post handlers: trashing or permanently deleting a
	// configured page must also move its section, not just editing it.
	$trash_hours_page_id = wp_insert_post(array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Trash Hours Page ' . $run_id,
		'post_content' => '<p>placeholder</p>',
	), true);
	$assert(!is_wp_error($trash_hours_page_id), 'Trash-hours-page fixture must be created.');
	$created_post_ids[] = $trash_hours_page_id;
	update_option('onlinesched_hours_page_id', $trash_hours_page_id);

	$rev = onlinesched_app_feed_test_rev('hours');
	wp_trash_post($trash_hours_page_id);
	$assert(onlinesched_app_feed_test_rev('hours') > $rev, 'Trashing the configured hours page must move hours.');
	$pass('trashing the configured hours page moves hours');

	$rev = onlinesched_app_feed_test_rev('hours');
	wp_delete_post($trash_hours_page_id, true);
	$assert(onlinesched_app_feed_test_rev('hours') > $rev, 'Permanently deleting the configured hours page must move hours.');
	$created_post_ids = array_values(array_diff($created_post_ids, array($trash_hours_page_id)));
	$pass('permanently deleting the configured hours page moves hours');

	$trash_info_page_id = wp_insert_post(array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Trash Info Page ' . $run_id,
		'post_content' => '<p>placeholder</p>',
	), true);
	$assert(!is_wp_error($trash_info_page_id), 'Trash-info-page fixture must be created.');
	$created_post_ids[] = $trash_info_page_id;
	update_option('onlinesched_app_info_page_ids', (string) $trash_info_page_id);

	$rev = onlinesched_app_feed_test_rev('info');
	wp_trash_post($trash_info_page_id);
	$assert(onlinesched_app_feed_test_rev('info') > $rev, 'Trashing a configured app-info page must move info.');
	$pass('trashing a configured app-info page moves info');

	// -------------------------------------------------------------------
	// C. CSV import and delete-year batch semantics — EXACTLY-ONCE kept here
	// -------------------------------------------------------------------

	$csv_year = 'aft-csv-' . $run_id;
	$csv_path = sys_get_temp_dir() . '/onlinesched-app-feed-test-' . $run_id . '.csv';
	$created_files[] = $csv_path;
	$csv_room_name = 'AFT CSV Room ' . $run_id;
	$csv_speaker_name = 'AFT CSV Tester ' . $run_id;
	$csv_tag_name = 'AFT CSV General ' . $run_id;
	file_put_contents(
		$csv_path,
		implode("\n", array(
			'ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags',
			'AFT-1001,App Feed CSV Row One,2031-06-01,10:00,First row description,' . $csv_room_name . ',' . $csv_speaker_name . ',30,' . $csv_tag_name,
		)) . "\n"
	);

	$touch_log = array();
	$capture_touch_c = static function ($sections, $reason) use (&$touch_log) {
		$touch_log[] = array((array) $sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_touch_c, 10, 2);

	$before_dry = onlinesched_app_feed_test_rev('schedule');
	$dry_result = onlinesched_import_csv($csv_path, array('year' => $csv_year, 'dry_run' => true));
	$assert(0 === $dry_result['failed'], 'The dry run must not report failures for a clean CSV.');
	$assert(1 === $dry_result['inserted'], 'The dry run must preview one inserted row.');
	$assert(onlinesched_app_feed_test_rev('schedule') === $before_dry, 'A dry run must not move the schedule revision.');
	$assert(0 === count($touch_log), 'A dry run must never fire onlinesched_feed_touched.');
	$pass('csv dry run does not touch the schedule revision');

	$before_real = onlinesched_app_feed_test_rev('schedule');
	$real_result = onlinesched_import_csv($csv_path, array('year' => $csv_year));
	$assert(0 === $real_result['failed'] && 1 === $real_result['inserted'], 'The real import must insert exactly one row.');
	$assert(onlinesched_app_feed_test_rev('schedule') === $before_real + 1, 'A real import must move the schedule revision by exactly one.');
	$schedule_touches = array_values(array_filter($touch_log, static function ($entry) {
		return in_array('schedule', $entry[0], true);
	}));
	$assert(1 === count($schedule_touches), 'A real import must fire onlinesched_feed_touched for schedule exactly once, regardless of row count and regardless of however many save/meta hooks fire per row internally.');
	$pass('csv real import fires exactly one schedule touch');

	remove_action('onlinesched_feed_touched', $capture_touch_c, 10);

	// The import creates room/panelist/tag terms for the fixture row; their names
	// are unique to this run, so it is always safe to queue them for cleanup.
	foreach (array('os_room' => $csv_room_name, 'os_panelist' => $csv_speaker_name, 'os_tag' => $csv_tag_name) as $csv_taxonomy => $csv_term_name) {
		$csv_term = get_term_by('name', $csv_term_name, $csv_taxonomy);
		if ($csv_term) {
			$created_term_ids[$csv_taxonomy][] = $csv_term->term_id;
		}
	}

	$imported_posts = get_posts(array(
		'post_type'      => 'os_event',
		'post_status'    => onlinesched_event_post_statuses(),
		'numberposts'    => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array('key' => 'onlinesched_year', 'value' => $csv_year),
			array('key' => 'onlinesched_external_event_id', 'value' => 'AFT-1001'),
		),
	));
	$assert(1 === count($imported_posts), 'Exactly one imported post must exist for the fixture row.');
	$imported_post_id = $imported_posts[0];
	$created_post_ids[] = $imported_post_id;

	$uid_before_delete = onlinesched_get_event_uid($imported_post_id, $csv_year);

	$touch_log_d = array();
	$capture_touch_d = static function ($sections, $reason) use (&$touch_log_d) {
		$touch_log_d[] = array((array) $sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_touch_d, 10, 2);

	$before_del_dry = onlinesched_app_feed_test_rev('schedule');
	$del_dry = onlinesched_delete_schedule_year($csv_year, array('dry_run' => true));
	$assert(1 === $del_dry['selected'], 'Delete-year dry run must select the one fixture row.');
	$assert(onlinesched_app_feed_test_rev('schedule') === $before_del_dry, 'Delete-year dry run must not move the schedule revision.');
	$assert(0 === count($touch_log_d), 'Delete-year dry run must never fire onlinesched_feed_touched.');
	$pass('delete-year dry run does not touch the schedule revision');

	$before_del_real = onlinesched_app_feed_test_rev('schedule');
	$del_real = onlinesched_delete_schedule_year($csv_year, array());
	$assert(1 === $del_real['deleted'] && 0 === $del_real['failed'], 'The real delete-year run must delete exactly one fixture row.');
	$assert(onlinesched_app_feed_test_rev('schedule') === $before_del_real + 1, 'A real delete-year run must move the schedule revision by exactly one.');
	$assert(1 === count($touch_log_d), 'A real delete-year run must fire onlinesched_feed_touched for schedule exactly once.');
	remove_action('onlinesched_feed_touched', $capture_touch_d, 10);
	$pass('delete-year real run fires exactly one schedule touch');

	$created_post_ids = array_values(array_diff($created_post_ids, array($imported_post_id)));

	// -------------------------------------------------------------------
	// C2. Exception-without-revision: csv-import and delete-year wrap their
	// batch in an outer catch. Partial writes/deletes before a thrown
	// exception must still fire exactly one touch ('csv-import-partial' /
	// 'delete-year-partial') AFTER suspension resumes, then rethrow.
	// -------------------------------------------------------------------

	$exception_year = 'aft-exception-' . $run_id;
	$exception_csv_path = sys_get_temp_dir() . '/onlinesched-app-feed-exception-' . $run_id . '.csv';
	$created_files[] = $exception_csv_path;
	$exception_room_name = 'AFT Exception Room ' . $run_id;
	$exception_speaker_name = 'AFT Exception Speaker ' . $run_id;
	$exception_tag_name = 'AFT Exception Tag ' . $run_id;
	$exception_rows = array('ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags');
	for ($i = 1; $i <= 4; $i++) {
		$exception_rows[] = "AFT-EXC-{$i},App Feed Exception Row {$i},2031-06-01,0{$i}:00,Row {$i} description,{$exception_room_name},{$exception_speaker_name},30,{$exception_tag_name}";
	}
	file_put_contents($exception_csv_path, implode("\n", $exception_rows) . "\n");

	$exception_save_count = 0;
	$throw_after_save = static function ($post_id, $post) use (&$exception_save_count) {
		if (!($post instanceof WP_Post) || 'os_event' !== $post->post_type) {
			return;
		}
		$exception_save_count++;
		if (3 === $exception_save_count) {
			throw new RuntimeException('AFT forced csv-import exception (row 3)');
		}
	};
	add_action('save_post', $throw_after_save, 1000, 2);

	$touch_log_exc = array();
	$capture_touch_exc = static function ($sections, $reason) use (&$touch_log_exc) {
		$touch_log_exc[] = array((array) $sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_touch_exc, 10, 2);

	$before_exception_rev = onlinesched_app_feed_test_rev('schedule');
	$exception_thrown = null;
	try {
		onlinesched_import_csv($exception_csv_path, array('year' => $exception_year));
	} catch (\Throwable $e) {
		$exception_thrown = $e;
	}
	remove_action('save_post', $throw_after_save, 1000);
	remove_action('onlinesched_feed_touched', $capture_touch_exc, 10);

	// Whatever landed (fully or partially) under this year must be cleaned up,
	// including the term set created for the two successful rows.
	$exception_posts = get_posts(array(
		'post_type'   => 'os_event',
		'post_status' => onlinesched_event_post_statuses(),
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(array('key' => 'onlinesched_year', 'value' => $exception_year)),
	));
	foreach ($exception_posts as $exception_post_id) {
		$created_post_ids[] = $exception_post_id;
	}
	foreach (array('os_room' => $exception_room_name, 'os_panelist' => $exception_speaker_name, 'os_tag' => $exception_tag_name) as $exception_taxonomy => $exception_term_name) {
		$exception_term = get_term_by('name', $exception_term_name, $exception_taxonomy);
		if ($exception_term) {
			$created_term_ids[$exception_taxonomy][] = $exception_term->term_id;
		}
	}

	$assert(null !== $exception_thrown, 'The forced exception must have propagated out of onlinesched_import_csv().');
	$assert($exception_thrown instanceof RuntimeException, 'The propagated exception must be the one this test threw, not something else.');
	$assert(onlinesched_app_feed_test_rev('schedule') > $before_exception_rev, 'Partial writes before the exception must still move the schedule revision.');
	$partial_touches = array_values(array_filter($touch_log_exc, static function ($entry) {
		return in_array('schedule', $entry[0], true) && 'csv-import-partial' === $entry[1];
	}));
	$assert(1 === count($partial_touches), "Exactly one 'csv-import-partial' touch must fire after a mid-batch exception; got " . count($partial_touches));
	$assert(0 === onlinesched_feed_touch_suspension(), 'Suspension depth must return to 0 after the csv-import exception propagates (finally ran).');
	$rev_after_exception = onlinesched_app_feed_test_rev('schedule');
	$sanity_touch = onlinesched_touch_feed('schedule', 'post-csv-exception-sanity');
	$assert(true === $sanity_touch && onlinesched_app_feed_test_rev('schedule') > $rev_after_exception, 'A normal touch after the exception must still work (suspension not stuck).');
	$pass('csv-import: a mid-batch exception fires exactly one csv-import-partial touch, then rethrows, and suspension recovers to depth 0');

	// Same guarantee for delete-year: throw on before_delete_post for the 3rd
	// of 4 fixture events, so exactly 2 permanent deletes land before the
	// exception unwinds the batch.
	$delete_exception_year = 'aft-delete-exception-' . $run_id;
	$delete_exception_event_ids = array();
	for ($i = 1; $i <= 4; $i++) {
		$delete_exception_event_id = wp_insert_post(array(
			'post_type'    => 'os_event',
			'post_status'  => 'publish',
			'post_title'   => "AFT Delete Exception Event {$i} " . $run_id,
			'meta_input'   => array(
				'onlinesched_year'     => $delete_exception_year,
				'onlinesched_sorttime' => strtotime("2031-06-0{$i} 08:00:00"),
				'onlinesched_timelen'  => 30,
			),
		), true);
		$assert(!is_wp_error($delete_exception_event_id), "Delete-exception fixture event {$i} must be created.");
		$delete_exception_event_ids[] = $delete_exception_event_id;
		$created_post_ids[] = $delete_exception_event_id;
	}

	$delete_throw_count = 0;
	$throw_after_delete = static function ($post_id, $post) use (&$delete_throw_count, $delete_exception_year) {
		if (!($post instanceof WP_Post) || 'os_event' !== $post->post_type) {
			return;
		}
		if ((string) get_post_meta($post_id, 'onlinesched_year', true) !== $delete_exception_year) {
			return;
		}
		$delete_throw_count++;
		if (3 === $delete_throw_count) {
			throw new RuntimeException('AFT forced delete-year exception (3rd delete)');
		}
	};
	add_action('before_delete_post', $throw_after_delete, 1000, 2);

	$touch_log_delete_exc = array();
	$capture_touch_delete_exc = static function ($sections, $reason) use (&$touch_log_delete_exc) {
		$touch_log_delete_exc[] = array((array) $sections, $reason);
	};
	add_action('onlinesched_feed_touched', $capture_touch_delete_exc, 10, 2);

	$before_delete_exception_rev = onlinesched_app_feed_test_rev('schedule');
	$delete_exception_thrown = null;
	try {
		onlinesched_delete_schedule_year($delete_exception_year, array());
	} catch (\Throwable $e) {
		$delete_exception_thrown = $e;
	}
	remove_action('before_delete_post', $throw_after_delete, 1000);
	remove_action('onlinesched_feed_touched', $capture_touch_delete_exc, 10);

	$assert(null !== $delete_exception_thrown, 'The forced exception must have propagated out of onlinesched_delete_schedule_year().');
	$assert($delete_exception_thrown instanceof RuntimeException, 'The propagated exception must be the one this test threw, not something else.');
	$assert(onlinesched_app_feed_test_rev('schedule') > $before_delete_exception_rev, 'Partial deletions before the exception must still move the schedule revision.');
	$partial_delete_touches = array_values(array_filter($touch_log_delete_exc, static function ($entry) {
		return in_array('schedule', $entry[0], true) && 'delete-year-partial' === $entry[1];
	}));
	$assert(1 === count($partial_delete_touches), "Exactly one 'delete-year-partial' touch must fire after a mid-batch exception; got " . count($partial_delete_touches));
	$assert(0 === onlinesched_feed_touch_suspension(), 'Suspension depth must return to 0 after the delete-year exception propagates (finally ran).');
	$rev_after_delete_exception = onlinesched_app_feed_test_rev('schedule');
	$sanity_touch_2 = onlinesched_touch_feed('schedule', 'post-delete-exception-sanity');
	$assert(true === $sanity_touch_2 && onlinesched_app_feed_test_rev('schedule') > $rev_after_delete_exception, 'A normal touch after the delete-year exception must still work (suspension not stuck).');
	$pass('delete-year: a mid-batch exception fires exactly one delete-year-partial touch, then rethrows, and suspension recovers to depth 0');

	// -------------------------------------------------------------------
	// D. event_uid durability and collision proofs
	// -------------------------------------------------------------------

	$reimport = onlinesched_import_csv($csv_path, array('year' => $csv_year));
	$assert(1 === $reimport['inserted'] && 0 === $reimport['failed'], 'Reimporting the fixture row after delete-year must insert one row.');
	$reimported_posts = get_posts(array(
		'post_type'   => 'os_event',
		'post_status' => onlinesched_event_post_statuses(),
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			array('key' => 'onlinesched_year', 'value' => $csv_year),
			array('key' => 'onlinesched_external_event_id', 'value' => 'AFT-1001'),
		),
	));
	$assert(1 === count($reimported_posts), 'Exactly one reimported post must exist for the fixture row.');
	$reimported_post_id = $reimported_posts[0];
	$created_post_ids[] = $reimported_post_id;
	$assert($reimported_post_id !== $imported_post_id, 'Sanity check: delete-year + reimport must produce a new WP post ID.');

	$uid_after_reimport = onlinesched_get_event_uid($reimported_post_id, $csv_year);
	$assert(
		$uid_after_reimport === $uid_before_delete,
		'event_uid must be durable across delete-year + reimport for the same external ID and year.'
	);
	$assert(
		$uid_before_delete === rawurlencode($csv_year) . ':' . rawurlencode('AFT-1001'),
		'An imported event (external id present, no persisted uuid) must derive its uid from the external id.'
	);
	$pass('imported-row event_uid is durable across delete-year + reimport');

	// A manual event gets its uuid persisted by the save_post_os_event hook at
	// SAVE time (a real mutation, not a read-time side effect); reading the
	// uid must never rewrite it.
	$manual_event_id = wp_insert_post(array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'post_title'  => 'AFT Manual UID Event ' . $run_id,
		'meta_input'  => array('onlinesched_year' => $csv_year),
	), true);
	$assert(!is_wp_error($manual_event_id), 'Manual uid fixture event must be created.');
	$created_post_ids[] = $manual_event_id;

	$persisted_at_save = get_post_meta($manual_event_id, 'onlinesched_event_uid', true);
	$assert('' !== (string) $persisted_at_save, 'save_post_os_event must persist a uuid for a manual event at save time, not on first read.');
	$manual_uid_1 = onlinesched_get_event_uid($manual_event_id, $csv_year);
	$manual_uid_2 = onlinesched_get_event_uid($manual_event_id, $csv_year);
	$assert($manual_uid_1 === $manual_uid_2, 'Calling onlinesched_get_event_uid twice must return the identical uid.');
	$assert(
		get_post_meta($manual_event_id, 'onlinesched_event_uid', true) === $persisted_at_save,
		'Reading the uid must never rewrite the persisted uuid.'
	);
	$assert(
		$manual_uid_1 === rawurlencode($csv_year) . ':' . $persisted_at_save,
		'A manual event uid must be "{year}:{uuid}" using the persisted uuid meta.'
	);
	$pass('manual events get a persisted event_uid at save time, and reads never rewrite it');

	// UID precedence flipped: a persisted UUID always wins over
	// onlinesched_external_event_id. CSV export lazily backfills an external
	// id onto manually created events, and that backfill must never change a
	// uid a client may already have stored against favorites/reminders.
	$precedence_event_id = wp_insert_post(array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'post_title'  => 'AFT Precedence Event ' . $run_id,
		'meta_input'  => array('onlinesched_year' => $csv_year),
	), true);
	$assert(!is_wp_error($precedence_event_id), 'Precedence fixture event must be created.');
	$created_post_ids[] = $precedence_event_id;

	$precedence_uuid = get_post_meta($precedence_event_id, 'onlinesched_event_uid', true);
	$assert('' !== (string) $precedence_uuid, 'Sanity: the save hook must have persisted a uuid for this manual event.');
	$uid_before_backfill = onlinesched_get_event_uid($precedence_event_id, $csv_year);
	$assert(
		$uid_before_backfill === rawurlencode($csv_year) . ':' . $precedence_uuid,
		'Sanity: before any external-id backfill, the uid must use the persisted uuid.'
	);

	// Simulate the CSV-export backfill (OnlineSchedImportExporter lazily
	// assigns an external id to a manually created event during export).
	update_post_meta($precedence_event_id, 'onlinesched_external_event_id', 'AFT-EXPORT-BACKFILL-' . $run_id);

	$uid_after_backfill = onlinesched_get_event_uid($precedence_event_id, $csv_year);
	$assert(
		$uid_after_backfill === $uid_before_backfill,
		'A persisted uuid must always win over a later external_event_id backfill: the uid must not change.'
	);
	$assert(
		get_post_meta($precedence_event_id, 'onlinesched_event_uid', true) === $precedence_uuid,
		'The external-id backfill must not touch the persisted uuid meta itself.'
	);
	$pass('a persisted event_uid uuid always wins over a later external_event_id backfill (uid precedence)');

	// Destructive manual-uid roundtrip: OnlineSchedImportExporter's CSV export
	// now reuses a manual event's persisted uuid as its external id (instead
	// of a random int) — since a UUID rawurlencodes to itself, export ->
	// delete-year -> reimport must reproduce the byte-identical durable uid.
	$roundtrip_year = 'aft-roundtrip-' . $run_id;
	$roundtrip_manual_id = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Roundtrip Manual Event ' . $run_id,
		'meta_input'   => array(
			'onlinesched_year'     => $roundtrip_year,
			'onlinesched_sorttime' => strtotime('2031-06-01 08:00:00'),
			'onlinesched_timelen'  => 30,
		),
	), true);
	$assert(!is_wp_error($roundtrip_manual_id), 'Roundtrip manual fixture event must be created.');
	$created_post_ids[] = $roundtrip_manual_id;

	$roundtrip_uuid = get_post_meta($roundtrip_manual_id, 'onlinesched_event_uid', true);
	$assert('' !== (string) $roundtrip_uuid, 'Sanity: the roundtrip manual event must have a persisted uuid.');
	$uid_before_roundtrip = onlinesched_get_event_uid($roundtrip_manual_id, $roundtrip_year);
	$assert(
		$uid_before_roundtrip === rawurlencode($roundtrip_year) . ':' . $roundtrip_uuid,
		'Sanity: before any export backfill, the uid must use the persisted uuid.'
	);

	// Simulate the CSV-export backfill exactly as OnlineSchedImportExporter
	// does: reuse the persisted uuid as the external id.
	update_post_meta($roundtrip_manual_id, 'onlinesched_external_event_id', $roundtrip_uuid);
	$assert(
		onlinesched_get_event_uid($roundtrip_manual_id, $roundtrip_year) === $uid_before_roundtrip,
		'The export backfill itself must not change the uid (the persisted uuid still wins over the just-set external id).'
	);

	$deleted_roundtrip = onlinesched_delete_schedule_year($roundtrip_year, array());
	$assert(1 === $deleted_roundtrip['deleted'] && 0 === $deleted_roundtrip['failed'], 'The roundtrip fixture year must delete exactly the one manual event.');
	$created_post_ids = array_values(array_diff($created_post_ids, array($roundtrip_manual_id)));

	$roundtrip_csv_path = sys_get_temp_dir() . '/onlinesched-app-feed-roundtrip-' . $run_id . '.csv';
	$created_files[] = $roundtrip_csv_path;
	$roundtrip_room_name = 'AFT Roundtrip Room ' . $run_id;
	$roundtrip_speaker_name = 'AFT Roundtrip Speaker ' . $run_id;
	$roundtrip_tag_name = 'AFT Roundtrip Tag ' . $run_id;
	file_put_contents(
		$roundtrip_csv_path,
		implode("\n", array(
			'ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags',
			$roundtrip_uuid . ',App Feed Roundtrip Row,2031-06-01,08:00,Roundtrip description,' . $roundtrip_room_name . ',' . $roundtrip_speaker_name . ',30,' . $roundtrip_tag_name,
		)) . "\n"
	);
	$roundtrip_import = onlinesched_import_csv($roundtrip_csv_path, array('year' => $roundtrip_year));
	$assert(1 === $roundtrip_import['inserted'] && 0 === $roundtrip_import['failed'], 'Reimporting the roundtrip row (external id = the original uuid) must insert exactly one event.');

	foreach (array('os_room' => $roundtrip_room_name, 'os_panelist' => $roundtrip_speaker_name, 'os_tag' => $roundtrip_tag_name) as $roundtrip_taxonomy => $roundtrip_term_name) {
		$roundtrip_term = get_term_by('name', $roundtrip_term_name, $roundtrip_taxonomy);
		if ($roundtrip_term) {
			$created_term_ids[$roundtrip_taxonomy][] = $roundtrip_term->term_id;
		}
	}

	$roundtrip_posts = get_posts(array(
		'post_type'   => 'os_event',
		'post_status' => onlinesched_event_post_statuses(),
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => array(
			array('key' => 'onlinesched_year', 'value' => $roundtrip_year),
			array('key' => 'onlinesched_external_event_id', 'value' => $roundtrip_uuid),
		),
	));
	$assert(1 === count($roundtrip_posts), 'Exactly one reimported roundtrip post must exist.');
	$roundtrip_reimported_id = $roundtrip_posts[0];
	$created_post_ids[] = $roundtrip_reimported_id;
	$assert($roundtrip_reimported_id !== $roundtrip_manual_id, 'Sanity: the reimported post must be a new WP post ID (the original was permanently deleted).');

	$uid_after_roundtrip = onlinesched_get_event_uid($roundtrip_reimported_id, $roundtrip_year);
	$assert(
		$uid_after_roundtrip === $uid_before_roundtrip,
		"Export -> delete-year -> reimport must reproduce the byte-identical durable uid; before={$uid_before_roundtrip} after={$uid_after_roundtrip}"
	);
	$pass('export backfill (uuid reused as external id) survives delete-year + reimport with a byte-identical durable uid');

	// Collision proofs: components are rawurlencoded and ':'-joined, so ids
	// that differ only by a space/hyphen or contain only punctuation still
	// produce distinct, non-empty uids.
	$collision_year = 'aft-collision-' . $run_id;
	$ev_space = wp_insert_post(array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'post_title'  => 'AFT Collision Space ' . $run_id,
		'meta_input'  => array(
			'onlinesched_year'              => $collision_year,
			'onlinesched_external_event_id' => 'A B',
		),
	), true);
	$assert(!is_wp_error($ev_space), 'Collision fixture event ("A B") must be created.');
	$created_post_ids[] = $ev_space;

	$ev_dash = wp_insert_post(array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'post_title'  => 'AFT Collision Dash ' . $run_id,
		'meta_input'  => array(
			'onlinesched_year'              => $collision_year,
			'onlinesched_external_event_id' => 'A-B',
		),
	), true);
	$assert(!is_wp_error($ev_dash), 'Collision fixture event ("A-B") must be created.');
	$created_post_ids[] = $ev_dash;

	$ev_punct = wp_insert_post(array(
		'post_type'   => 'os_event',
		'post_status' => 'publish',
		'post_title'  => 'AFT Collision Punctuation ' . $run_id,
		'meta_input'  => array(
			'onlinesched_year'              => $collision_year,
			'onlinesched_external_event_id' => '!!',
		),
	), true);
	$assert(!is_wp_error($ev_punct), 'Collision fixture event ("!!") must be created.');
	$created_post_ids[] = $ev_punct;

	$uid_space = onlinesched_get_event_uid($ev_space, $collision_year);
	$uid_dash = onlinesched_get_event_uid($ev_dash, $collision_year);
	$uid_punct = onlinesched_get_event_uid($ev_punct, $collision_year);
	$assert('' !== $uid_space && '' !== $uid_dash, 'Both "A B" and "A-B" must produce non-empty uids.');
	$assert($uid_space !== $uid_dash, 'External ids "A B" and "A-B" (same year) must not collide.');
	$assert('' !== $uid_punct, 'A punctuation-only external id ("!!") must still produce a non-empty uid.');
	$assert($uid_punct !== $uid_space && $uid_punct !== $uid_dash, 'The punctuation-only uid must be distinct from the other two.');
	$pass('event_uid does not collide across ids differing only by punctuation/whitespace');

	// -------------------------------------------------------------------
	// E-G. Builder shapes, publication gating, year scoping, cancelled/adult
	// -------------------------------------------------------------------

	$active_year = 'aft-shapes-' . $run_id;
	$other_year = 'aft-shapes-other-' . $run_id;
	update_option('onlinesched_year', $active_year);
	update_option('onlinesched_app_schedule_published', '1');
	update_option('onlinesched_con_start', '2031-05-30');
	update_option('onlinesched_con_end', '2031-06-03');
	update_option('onlinesched_public_date_start', '2031-05-31');
	update_option('onlinesched_public_date_end', '2031-06-02');

	$shapes_room = wp_insert_term('AFT Shapes Room ' . $run_id, 'os_room');
	$assert(!is_wp_error($shapes_room), 'Shapes room term must be created.');
	$created_term_ids['os_room'][] = $shapes_room['term_id'];

	$tag_general = wp_insert_term('General', 'os_tag');
	$tag_general_id = is_wp_error($tag_general) ? get_term_by('name', 'General', 'os_tag')->term_id : $tag_general['term_id'];
	if (!is_wp_error($tag_general)) {
		$created_term_ids['os_tag'][] = $tag_general['term_id'];
	}

	$tag_cancelled = wp_insert_term('Cancelled', 'os_tag');
	$tag_cancelled_id = is_wp_error($tag_cancelled) ? get_term_by('name', 'Cancelled', 'os_tag')->term_id : $tag_cancelled['term_id'];
	if (!is_wp_error($tag_cancelled)) {
		$created_term_ids['os_tag'][] = $tag_cancelled['term_id'];
	}

	$tag_restricted = wp_insert_term('Restricted', 'os_tag');
	$tag_restricted_id = is_wp_error($tag_restricted) ? get_term_by('name', 'Restricted', 'os_tag')->term_id : $tag_restricted['term_id'];
	if (!is_wp_error($tag_restricted)) {
		$created_term_ids['os_tag'][] = $tag_restricted['term_id'];
	}

	$panelist = wp_insert_term('AFT Shapes Panelist ' . $run_id, 'os_panelist');
	$assert(!is_wp_error($panelist), 'Shapes panelist term must be created.');
	$created_term_ids['os_panelist'][] = $panelist['term_id'];

	$event_normal = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Normal Event ' . $run_id,
		'post_content' => '<p>Normal event body.</p>',
		'meta_input'   => array(
			'onlinesched_year'              => $active_year,
			'onlinesched_sorttime'          => strtotime('2031-06-01 10:00:00'),
			'onlinesched_timelen'           => 60,
			'onlinesched_external_event_id' => 'SHAPE-1-' . $run_id,
		),
	), true);
	$assert(!is_wp_error($event_normal), 'Normal shapes event must be created.');
	$created_post_ids[] = $event_normal;
	wp_set_object_terms($event_normal, array($shapes_room['term_id']), 'os_room', false);
	wp_set_object_terms($event_normal, array($tag_general_id), 'os_tag', false);
	wp_set_object_terms($event_normal, array($panelist['term_id']), 'os_panelist', false);

	$event_cancelled = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Cancelled Event ' . $run_id,
		'post_content' => '<p>Cancelled event body.</p>',
		'meta_input'   => array(
			'onlinesched_year'              => $active_year,
			'onlinesched_sorttime'          => strtotime('2031-06-01 11:00:00'),
			'onlinesched_timelen'           => 30,
			'onlinesched_external_event_id' => 'SHAPE-2-' . $run_id,
		),
	), true);
	$assert(!is_wp_error($event_cancelled), 'Cancelled shapes event must be created.');
	$created_post_ids[] = $event_cancelled;
	wp_set_object_terms($event_cancelled, array($shapes_room['term_id']), 'os_room', false);
	wp_set_object_terms($event_cancelled, array($tag_cancelled_id), 'os_tag', false);

	$event_adult = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Restricted Event ' . $run_id,
		'post_content' => '<p>Restricted event body.</p>',
		'meta_input'   => array(
			'onlinesched_year'              => $active_year,
			'onlinesched_sorttime'          => strtotime('2031-06-01 22:00:00'),
			'onlinesched_timelen'           => 45,
			'onlinesched_external_event_id' => 'SHAPE-3-' . $run_id,
		),
	), true);
	$assert(!is_wp_error($event_adult), 'Restricted shapes event must be created.');
	$created_post_ids[] = $event_adult;
	wp_set_object_terms($event_adult, array($shapes_room['term_id']), 'os_room', false);
	wp_set_object_terms($event_adult, array($tag_restricted_id), 'os_tag', false);

	$event_other_year = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Other-Year Event ' . $run_id,
		'post_content' => '<p>Different year, must be excluded.</p>',
		'meta_input'   => array(
			'onlinesched_year'              => $other_year,
			'onlinesched_sorttime'          => strtotime('2031-06-01 12:00:00'),
			'onlinesched_timelen'           => 30,
			'onlinesched_external_event_id' => 'SHAPE-4-' . $run_id,
		),
	), true);
	$assert(!is_wp_error($event_other_year), 'Other-year shapes event must be created.');
	$created_post_ids[] = $event_other_year;
	wp_set_object_terms($event_other_year, array($shapes_room['term_id']), 'os_room', false);

	// sorttime <= 0 exclusion: same active year, valid publish status, but a
	// zero sorttime (e.g. never scheduled) must never render as a 1970 event.
	$event_zero_sorttime = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Zero Sorttime Event ' . $run_id,
		'post_content' => '<p>Unscheduled, must be excluded.</p>',
		'meta_input'   => array(
			'onlinesched_year'              => $active_year,
			'onlinesched_sorttime'          => 0,
			'onlinesched_timelen'           => 30,
			'onlinesched_external_event_id' => 'SHAPE-5-' . $run_id,
		),
	), true);
	$assert(!is_wp_error($event_zero_sorttime), 'Zero-sorttime shapes event must be created.');
	$created_post_ids[] = $event_zero_sorttime;

	$dept_attrs = wp_json_encode(array('department' => 'AFT Shapes Department', 'location' => 'AFT Shapes Location'));
	$day_attrs = wp_json_encode(array('day' => 'Friday'));
	$time_attrs = wp_json_encode(array('hours' => '10 AM - 6 PM', 'smallText' => 'Sample note'));
	$hours_markup = '<!-- wp:onlinesched/hours-department ' . $dept_attrs . " -->\n"
		. '<!-- wp:onlinesched/hours-day ' . $day_attrs . " -->\n"
		. '<!-- wp:onlinesched/hours-time ' . $time_attrs . " /-->\n"
		. "<!-- /wp:onlinesched/hours-day -->\n"
		. '<!-- /wp:onlinesched/hours-department -->';

	$shapes_hours_page_id = wp_insert_post(array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Hours Page ' . $run_id,
		'post_content' => $hours_markup,
	), true);
	$assert(!is_wp_error($shapes_hours_page_id), 'Shapes hours page must be created.');
	$created_post_ids[] = $shapes_hours_page_id;
	update_option('onlinesched_hours_page_id', $shapes_hours_page_id);

	$shapes_info_page_id = wp_insert_post(array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Shapes Info Page ' . $run_id,
		'post_content' => '<p>Info body.</p><p><img src="https://example.test/aft-image.png" alt="AFT test image"></p>',
	), true);
	$assert(!is_wp_error($shapes_info_page_id), 'Shapes info page must be created.');
	$created_post_ids[] = $shapes_info_page_id;
	update_option('onlinesched_app_info_page_ids', (string) $shapes_info_page_id);
	$shapes_info_slug = get_post($shapes_info_page_id)->post_name;

	// --- E: builder shapes vs. contract fixtures (fixtures are back to 3-part) ---

	$meta = onlinesched_app_feed_meta();
	$check_shape('onlinesched_app_feed_meta() shape matches the contract fixture', static function () use ($assert_shape, $meta, $load_fixture) {
		$assert_shape($meta, $load_fixture('meta.json'), 'meta');
	});

	$schedule = onlinesched_app_feed_schedule();
	$check_shape('onlinesched_app_feed_schedule() shape matches the contract fixture', static function () use ($assert_shape, $schedule, $load_fixture) {
		$assert_shape($schedule, $load_fixture('schedule.json'), 'schedule');
	});

	$hours = onlinesched_app_feed_hours();
	$check_shape('onlinesched_app_feed_hours() shape matches the contract fixture', static function () use ($assert_shape, $hours, $load_fixture) {
		$assert_shape($hours, $load_fixture('hours.json'), 'hours');
	});

	$info_index = onlinesched_app_feed_info('');
	$check_shape('onlinesched_app_feed_info(\'\') shape matches the contract fixture', static function () use ($assert_shape, $info_index, $load_fixture) {
		$assert_shape($info_index, $load_fixture('info-list.json'), 'info-list');
	});

	$info_page = onlinesched_app_feed_info($shapes_info_slug);
	$assert(null !== $info_page, 'onlinesched_app_feed_info() must resolve the configured page slug.');
	$check_shape('onlinesched_app_feed_info($slug) shape matches the contract fixture', static function () use ($assert_shape, $info_page, $load_fixture) {
		$assert_shape($info_page, $load_fixture('info-page.json'), 'info-page');
	});

	// --- F: year scoping + sorttime<=0 exclusion (uses the schedule above) ---

	$scoped_ids = wp_list_pluck($schedule['events'], 'wp_post_id');
	$assert(in_array($event_normal, $scoped_ids, true), 'The active-year event must appear in the schedule.');
	$assert(!in_array($event_other_year, $scoped_ids, true), 'An event tagged with a different onlinesched_year must be excluded from the schedule.');
	$pass('schedule section excludes events from a different onlinesched_year');

	$assert(!in_array($event_zero_sorttime, $scoped_ids, true), 'An event with onlinesched_sorttime <= 0 must be excluded from the schedule.');
	$pass('schedule section excludes events with sorttime <= 0');

	// --- G: cancelled/adult derivation (term name, case-insensitive) ---

	$events_by_id = array();
	foreach ($schedule['events'] as $evt) {
		$events_by_id[$evt['wp_post_id']] = $evt;
	}
	$assert(isset($events_by_id[$event_cancelled]), 'The cancelled fixture event must be present in the schedule.');
	$assert(true === $events_by_id[$event_cancelled]['cancelled'], "An event tagged 'Cancelled' must report cancelled=true.");
	$assert(false === $events_by_id[$event_cancelled]['adult'], 'A cancelled-only event must not be marked adult.');
	$assert(isset($events_by_id[$event_adult]), 'The restricted fixture event must be present in the schedule.');
	$assert(true === $events_by_id[$event_adult]['adult'], "An event tagged 'Restricted' must report adult=true.");
	$assert(false === $events_by_id[$event_adult]['cancelled'], 'A restricted-only event must not be marked cancelled.');
	$assert(isset($events_by_id[$event_normal]), 'The normal fixture event must be present in the schedule.');
	$assert(false === $events_by_id[$event_normal]['cancelled'] && false === $events_by_id[$event_normal]['adult'], 'A normal event must be neither cancelled nor adult.');
	$pass('cancelled/adult flags derive from tag names (Cancelled/Restricted)');

	// --- E continued: schedule ETag carries the resolved publication flag ---

	// json.php builds the schedule ETag variant as
	// wp_json_encode($filters) . '|pub:' . (int) onlinesched_app_schedule_published();
	// replicate that exactly, at an otherwise-identical revision and filter
	// set, to prove the ETag alone distinguishes published vs unpublished.
	$same_filters = array();
	$variant_published = wp_json_encode($same_filters) . '|pub:1';
	$variant_unpublished = wp_json_encode($same_filters) . '|pub:0';
	$etag_pub_1 = onlinesched_app_feed_etag('schedule', $variant_published);
	$etag_pub_0 = onlinesched_app_feed_etag('schedule', $variant_unpublished);
	$assert($etag_pub_1 !== $etag_pub_0, 'The schedule ETag must differ between published and unpublished variants even at an identical revision.');
	$pass('schedule ETag variant distinguishes published vs unpublished state at equal revision');

	// --- E continued: publication gating ---

	update_option('onlinesched_app_schedule_published', '0');
	$gated_schedule = onlinesched_app_feed_schedule();
	$assert(false === $gated_schedule['schedule_published'], 'Disabling publication must report schedule_published=false.');
	$assert(array() === $gated_schedule['events'], 'Disabling publication must return an empty events array.');
	$assert(array() === $gated_schedule['rooms'], 'Disabling publication must return an empty rooms array.');
	$assert(array() === $gated_schedule['tags'], 'Disabling publication must return an empty tags array.');
	$gated_meta = onlinesched_app_feed_meta();
	$assert(isset($gated_meta['schema_version']), 'The meta section must keep serving while the schedule is unpublished.');
	$assert(false === $gated_meta['schedule_published'], 'The meta section must reflect the disabled publication flag.');
	update_option('onlinesched_app_schedule_published', '1');
	$pass('disabling app schedule publication empties the schedule while meta keeps serving');

	// -------------------------------------------------------------------
	// H. Fail-closed event_uid + versioned upgrade backfill (schema v3+)
	// -------------------------------------------------------------------

	$zw_year = 'aft-zerowrite-' . $run_id;
	update_option('onlinesched_year', $zw_year);

	$zw_event_id = wp_insert_post(array(
		'post_type'    => 'os_event',
		'post_status'  => 'publish',
		'post_title'   => 'AFT Zero-Write Event ' . $run_id,
		'post_content' => '<p>Zero-write / fail-closed read proof.</p>',
		'meta_input'   => array(
			'onlinesched_year'     => $zw_year,
			'onlinesched_sorttime' => strtotime('2031-06-01 09:00:00'),
			'onlinesched_timelen'  => 30,
		),
	), true);
	$assert(!is_wp_error($zw_event_id), 'Zero-write fixture event must be created.');
	$created_post_ids[] = $zw_event_id;

	// The save hook already persisted a uuid; delete it to simulate an event
	// that predates onlinesched_ensure_event_uid_meta() and was never re-saved.
	$assert('' !== (string) get_post_meta($zw_event_id, 'onlinesched_event_uid', true), 'Sanity: the save hook must have persisted a uuid before we delete it.');
	delete_post_meta($zw_event_id, 'onlinesched_event_uid');
	$assert('' === (string) get_post_meta($zw_event_id, 'onlinesched_event_uid', true), 'Sanity: the uuid meta must actually be gone before the read proof.');
	$assert('' === onlinesched_get_event_uid($zw_event_id, $zw_year), 'A manual event with no persisted uuid must fail closed: onlinesched_get_event_uid() returns \'\' (no wp:{ID} fallback).');

	$zw_schedule_before_upgrade = onlinesched_app_feed_schedule();
	$zw_ids_before = wp_list_pluck($zw_schedule_before_upgrade['events'], 'wp_post_id');
	$assert(
		!in_array($zw_event_id, $zw_ids_before, true),
		'An event with no durable uid yet must be OMITTED from the schedule (fail closed) rather than served under a fallback id.'
	);
	$assert(
		'' === (string) get_post_meta($zw_event_id, 'onlinesched_event_uid', true),
		'The read path (onlinesched_app_feed_schedule -> onlinesched_get_event_uid) must perform zero writes even while omitting the event.'
	);
	$pass('a manual event with no persisted uid is omitted from the schedule (fail closed); the read never writes the missing uid');

	// Force the versioned upgrade to actually run (it is normally a once-per-
	// schema-version no-op): drop the installed marker below the required
	// version (now 3), then call the real upgrade function directly (WP_CLI
	// context qualifies it to run, same as it would on any admin/CLI request).
	update_option('onlinesched_feed_schema_installed', 0);
	onlinesched_feed_maybe_upgrade();
	$assert(
		(int) get_option('onlinesched_feed_schema_installed', 0) >= 3,
		'onlinesched_feed_maybe_upgrade() must record schema version >= 3 (ONLINESCHED_FEED_SCHEMA_INSTALL_VERSION) after running.'
	);

	$backfilled_uuid_1 = get_post_meta($zw_event_id, 'onlinesched_event_uid', true);
	$assert('' !== (string) $backfilled_uuid_1, 'onlinesched_feed_maybe_upgrade() must backfill the missing uuid for a manual event.');
	$uid_after_first_upgrade = onlinesched_get_event_uid($zw_event_id, $zw_year);
	$assert('' !== $uid_after_first_upgrade, 'The uid must be non-empty after backfill.');
	$assert(
		$uid_after_first_upgrade === rawurlencode($zw_year) . ':' . $backfilled_uuid_1,
		'After the upgrade backfill, the uid must use the persisted uuid form.'
	);

	$zw_schedule_after_upgrade = onlinesched_app_feed_schedule();
	$zw_ids_after = wp_list_pluck($zw_schedule_after_upgrade['events'], 'wp_post_id');
	$assert(in_array($zw_event_id, $zw_ids_after, true), 'After the backfill, the previously-omitted event must now appear in the schedule.');
	$pass('onlinesched_feed_maybe_upgrade() backfills the missing uuid; the event then appears with a durable uid');

	// Stability across a second forced upgrade run: the uuid/uid must not change.
	update_option('onlinesched_feed_schema_installed', 0);
	onlinesched_feed_maybe_upgrade();
	$backfilled_uuid_2 = get_post_meta($zw_event_id, 'onlinesched_event_uid', true);
	$uid_after_second_upgrade = onlinesched_get_event_uid($zw_event_id, $zw_year);
	$assert($backfilled_uuid_2 === $backfilled_uuid_1, 'A second forced upgrade run must not regenerate an already-backfilled uuid.');
	$assert($uid_after_second_upgrade === $uid_after_first_upgrade, 'The event uid must be identical before and after a second forced upgrade run (stability).');
	$pass('the backfilled event_uid is stable across a second upgrade run');

	// -------------------------------------------------------------------
	// I. Fresh-state stability (revision times initialized by H's upgrade run)
	// -------------------------------------------------------------------

	$meta_call_1 = onlinesched_app_feed_meta();
	$meta_call_2 = onlinesched_app_feed_meta();
	$assert(wp_json_encode($meta_call_1) === wp_json_encode($meta_call_2), 'Two consecutive onlinesched_app_feed_meta() calls with no mutation between them must return an identical body.');
	$assert(onlinesched_app_feed_etag('meta') === onlinesched_app_feed_etag('meta'), 'Two consecutive meta ETags with no mutation between them must be identical.');

	$schedule_call_1 = onlinesched_app_feed_schedule();
	$schedule_call_2 = onlinesched_app_feed_schedule();
	$assert(wp_json_encode($schedule_call_1) === wp_json_encode($schedule_call_2), 'Two consecutive onlinesched_app_feed_schedule() calls with no mutation between them must return an identical body.');
	$assert(onlinesched_app_feed_etag('schedule') === onlinesched_app_feed_etag('schedule'), 'Two consecutive schedule ETags with no mutation between them must be identical.');
	$pass('fresh-state builder calls are stable across repeats with no intervening mutation');

	$stable_etag_before = onlinesched_app_feed_etag('meta');
	$stable_body_before = wp_json_encode(onlinesched_app_feed_meta());
	$con_start_original = get_option('onlinesched_con_start', '');
	update_option('onlinesched_con_start', '2031-08-15'); // moves schedule -> moves the public stamp -> moves meta's ETag/body
	$stable_etag_after = onlinesched_app_feed_etag('meta');
	$stable_body_after = wp_json_encode(onlinesched_app_feed_meta());
	update_option('onlinesched_con_start', $con_start_original);
	$assert($stable_etag_after !== $stable_etag_before, 'A real option change must move the meta ETag.');
	$assert($stable_body_after !== $stable_body_before, 'A real option change must move the meta body.');
	$pass('a real option change moves the meta ETag and body away from the stable state');

	if (!empty($shape_failures)) {
		throw new RuntimeException(
			"OnlineSched app feed integration tests found " . count($shape_failures) . " shape mismatch(es):\n"
			. implode("\n", $shape_failures)
		);
	}

	echo "\nOnlineSched app feed integration tests passed ({$checks} checks).\n";
} finally {
	$restore();
}
