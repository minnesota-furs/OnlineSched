<?php
/**
 * Slash/backslash integrity regressions (3.0.2).
 *
 * Backslash preservation through import, export, reimport, and rollback.
 * Run via tests/cli/test-slash-integrity.sh on the disposable Vanilla
 * environment ONLY.
 *
 * Covered permanently:
 * - import insert preserves \u0026, single/doubled backslashes, and
 *   backslash-before-quote in descriptions;
 * - export -> reimport equality for the same hostile content;
 * - snapshot rollback restores slash-bearing post content AND metadata
 *   byte-exactly.
 */

if (!defined('WP_CLI') || !WP_CLI) {
	echo "This test must run through WP-CLI.\n";
	exit(1);
}

$failures = 0;
$check = function ($label, $expected, $actual) use (&$failures) {
	if ($expected === $actual) {
		WP_CLI::log("PASS: $label");
		return;
	}
	$failures++;
	WP_CLI::warning("FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true));
};

$year = '2197';

// The hostile content set: every string a slash bug can eat.
$hostile = array(
	'unicode-escape'       => 'sponsor \\u0026 super sponsor',
	'single-backslash'     => 'path C:\\Users\\fox',
	'double-backslash'     => 'escaped \\\\ pair',
	'backslash-then-quote' => 'tricky \\" combo and \\\' too',
);

$csv = "ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags\n";
$line = 2;
$ids = array();
foreach ($hostile as $key => $value) {
	$id = 'slash-' . $line;
	$ids[$key] = $id;
	$fields = array($id, "Slash Test $key", '2197-09-11', '10:00 AM', $value, 'Main Stage', 'Tester', '60', 'slash-test');
	$encoded = array();
	foreach ($fields as $f) {
		$encoded[] = '"' . str_replace('"', '""', (string) $f) . '"';
	}
	$csv .= implode(',', $encoded) . "\n";
	$line++;
}

$tmp = tempnam(sys_get_temp_dir(), 'onlinesched-slash') . '.csv';
file_put_contents($tmp, $csv);

$result = onlinesched_import_csv($tmp, array('year' => $year));
$check('import inserts all hostile rows', count($hostile), $result['inserted'] + $result['updated']);
$check('import reports zero failures', 0, $result['failed']);

$find_post = function ($external_id) use ($year) {
	$q = new WP_Query(array(
		'post_type'      => 'os_event',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'meta_query'     => array(
			'relation' => 'AND',
			array('key' => 'onlinesched_external_event_id', 'value' => (string) $external_id),
			array('key' => 'onlinesched_year', 'value' => $year),
		),
	));
	return $q->posts ? $q->posts[0] : null;
};

$normalize = function ($content) {
	return trim(wp_strip_all_tags((string) $content));
};

foreach ($hostile as $key => $value) {
	$post = $find_post($ids[$key]);
	if (!$post) {
		$failures++;
		WP_CLI::warning("FAIL: post for $key not found");
		continue;
	}
	$check("stored description preserves $key", $value, $normalize($post->post_content));
}

// Export -> reimport equality: capture the full export, filter to our rows so
// the reimport touches only the hostile fixtures, and apply it back.
$capture = fopen('php://temp', 'r+');
onlinesched_export_csv_rows($capture);
rewind($capture);
$exported = stream_get_contents($capture);
fclose($capture);

$filtered = array();
$stream = fopen('php://temp', 'r+');
fwrite($stream, $exported);
rewind($stream);
$first = true;
while (false !== ($row = fgetcsv($stream, 0, ',', '"', ''))) {
	if ($first) {
		$filtered[] = $row;
		$first = false;
		continue;
	}
	if (isset($row[0]) && in_array($row[0], $ids, true)) {
		$filtered[] = $row;
	}
}
fclose($stream);
$check('export contains every hostile row', count($hostile) + 1, count($filtered));

$export_file = tempnam(sys_get_temp_dir(), 'onlinesched-reimport') . '.csv';
$out = fopen($export_file, 'w');
foreach ($filtered as $row) {
	fputcsv($out, $row, ',', '"', '');
}
fclose($out);

$reimport = onlinesched_import_csv($export_file, array('year' => $year));
$check('reimport applies without failures', 0, $reimport['failed']);
foreach ($hostile as $key => $value) {
	$post = $find_post($ids[$key]);
	$check("post-roundtrip description preserves $key", $value, $post ? $normalize($post->post_content) : null);
}

// The snapshot captures a fixed meta-key list, so the probe rides one of those
// keys with hostile slash content.
$victim = $find_post($ids['backslash-then-quote']);
if ($victim) {
	$victim_id  = $victim->ID;
	$slash_meta = 'ext \\" id with \\\\ slashes';
	update_post_meta($victim_id, 'onlinesched_external_event_id', wp_slash($slash_meta));
	$before_meta    = get_post_meta($victim_id, 'onlinesched_external_event_id', true);
	$before_content = $victim->post_content;
	$check('slash meta stored intact before snapshot', $slash_meta, $before_meta);

	$snapshot = onlinesched_import_snapshot_post($victim_id);
	wp_update_post(array('ID' => $victim_id, 'post_content' => 'vandalized'), true);
	update_post_meta($victim_id, 'onlinesched_external_event_id', 'vandalized');
	$restored = onlinesched_import_restore_post($snapshot);
	$check('rollback returns no error', false, is_wp_error($restored));

	clean_post_cache($victim_id);
	$after = get_post($victim_id);
	$check('rollback restores content byte-exactly', $before_content, $after->post_content);
	$check('rollback restores metadata byte-exactly', $before_meta, get_post_meta($victim_id, 'onlinesched_external_event_id', true));

	// Put the fixture id back so cleanup can find it.
	update_post_meta($victim_id, 'onlinesched_external_event_id', $ids['backslash-then-quote']);
}

// Cleanup: hard-delete the fixture posts.
foreach ($ids as $external_id) {
	$post = $find_post($external_id);
	if ($post) {
		wp_delete_post($post->ID, true);
	}
}
unlink($tmp);
unlink($export_file);

if ($failures > 0) {
	WP_CLI::error("$failures slash-integrity check(s) failed.");
}
WP_CLI::success('All slash-integrity checks passed.');
