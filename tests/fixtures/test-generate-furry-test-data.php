<?php

declare(strict_types=1);

$fixture_directory = __DIR__;
$generator = $fixture_directory . '/generate-furry-test-data.php';
$expected_hash_file = $fixture_directory . '/expected/furry-test-data.sha256';
$temporary_directory = sys_get_temp_dir() . '/onlinesched-fixture-' . bin2hex(random_bytes(8));

if (!mkdir($temporary_directory, 0700, true) && !is_dir($temporary_directory)) {
	fwrite(STDERR, "Unable to create test directory.\n");
	exit(1);
}

register_shutdown_function(static function () use ($temporary_directory): void {
	foreach (glob($temporary_directory . '/*') ?: array() as $file) {
		@unlink($file);
	}
	@rmdir($temporary_directory);
});

function fixture_test_assert(bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/**
 * @param array<int, string> $arguments
 */
function fixture_test_run(string $generator, array $arguments): int {
	$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($generator);
	foreach ($arguments as $argument) {
		$command .= ' ' . escapeshellarg($argument);
	}
	$command .= ' >/dev/null 2>&1';
	exec($command, $ignored_output, $exit_code);

	return $exit_code;
}

/**
 * @return array<int, array<int, string|null>>
 */
function fixture_test_read_csv(string $file): array {
	$handle = fopen($file, 'rb');
	fixture_test_assert($handle !== false, 'Unable to read generated fixture.');
	$rows = array();
	while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
		$rows[] = $row;
	}
	fclose($handle);

	return $rows;
}

try {
	$first = $temporary_directory . '/first.csv';
	$second = $temporary_directory . '/second.csv';
	$arguments = array('--start-date=2027-06-30', '--days=4');
	fixture_test_assert(fixture_test_run($generator, array_merge($arguments, array('--output=' . $first))) === 0, 'First default fixture generation failed.');
	fixture_test_assert(fixture_test_run($generator, array_merge($arguments, array('--output=' . $second))) === 0, 'Second default fixture generation failed.');
	fixture_test_assert(hash_file('sha256', $first) === hash_file('sha256', $second), 'Fixture generation is not byte-for-byte deterministic.');
	fixture_test_assert(hash_file('sha256', $first) === trim((string) file_get_contents($expected_hash_file)), 'Default fixture does not match the committed golden SHA-256.');

	$rows = fixture_test_read_csv($first);
	fixture_test_assert($rows[0] === array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags'), 'Fixture header differs from the nine-column OnlineSched CSV contract.');
	fixture_test_assert(count($rows) === 151, 'Default fixture must contain 150 data rows.');
	$ids = array_map(static fn(array $row): string => (string) $row[0], array_slice($rows, 1));
	fixture_test_assert(count(array_unique($ids)) === 150 && $ids[0] === '4000' && $ids[149] === '4149', 'Default fixture external ID range is incorrect.');

	$scheduled_dates = array();
	$has_multiline = false;
	$has_multi_speaker = false;
	$has_multi_tag = false;
	$has_cancelled = false;
	$has_unscheduled = false;
	$anchors = array();
	foreach (array_slice($rows, 1) as $row) {
		if ($row[2] !== '') {
			$scheduled_dates[$row[2]] = ($scheduled_dates[$row[2]] ?? 0) + 1;
		}
		$has_multiline = $has_multiline || str_contains((string) $row[4], "\n");
		$has_multi_speaker = $has_multi_speaker || str_contains((string) $row[6], ', ');
		$has_multi_tag = $has_multi_tag || str_contains((string) $row[8], ', ');
		$has_cancelled = $has_cancelled || str_contains((string) $row[1], 'Cancelled Event');
		$has_unscheduled = $has_unscheduled || ($row[2] === '' && $row[3] === '');
		$anchors[(string) $row[0]] = $row;
	}
	ksort($scheduled_dates);
	fixture_test_assert(array_keys($scheduled_dates) === array('2027-06-30', '2027-07-01', '2027-07-02', '2027-07-03'), 'Four-day fixture does not cover the requested June 30 through July 3 range.');
	fixture_test_assert(max($scheduled_dates) - min($scheduled_dates) <= 1, 'Scheduled event distribution differs by more than one event between days.');
	fixture_test_assert($has_multiline && $has_multi_speaker && $has_multi_tag && $has_cancelled && $has_unscheduled, 'Fixture is missing required representative CSV coverage.');
	fixture_test_assert(str_contains((string) $anchors['4126'][1], 'Sound Design for Games') && $anchors['4126'][3] === '17:45:00', 'Timestamp anchor 4126 is missing or unstable.');
	fixture_test_assert(
		str_contains((string) $anchors['4131'][1], 'Sculpting Your Fursona')
		&& $anchors['4131'][3] === '16:00:00'
		&& str_starts_with((string) $anchors['4131'][4], 'Coyote Stuff:')
		&& $anchors['4131'][5] === 'Dealers Den',
		'Update anchor 4131 is missing or unstable.'
	);

	$december = $temporary_directory . '/december.csv';
	fixture_test_assert(fixture_test_run($generator, array('--start-date=2027-12-30', '--days=4', '--count=8', '--output=' . $december)) === 0, 'December boundary fixture generation failed.');
	$december_rows = fixture_test_read_csv($december);
	$december_dates = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) $row[2], array_slice($december_rows, 1)))));
	sort($december_dates);
	fixture_test_assert($december_dates === array('2027-12-30', '2027-12-31', '2028-01-01', '2028-01-02'), 'December fixture does not cross the year boundary correctly.');
	$weekday_labels = array_map(static fn(string $date): string => (new DateTimeImmutable($date, new DateTimeZone('UTC')))->format('l'), $december_dates);
	fixture_test_assert($weekday_labels === array('Thursday', 'Friday', 'Saturday', 'Sunday'), 'Fixture date weekday labels are incorrect.');

	$invalid_output = $temporary_directory . '/invalid.csv';
	foreach (array('--count=0', '--start-date=2027-02-30', '--days=8', '--seed=0', '--unknown=1') as $invalid_argument) {
		fixture_test_assert(fixture_test_run($generator, array('--start-date=2027-06-30', $invalid_argument, '--output=' . $invalid_output)) !== 0, 'Invalid argument unexpectedly succeeded: ' . $invalid_argument);
		fixture_test_assert(!file_exists($invalid_output), 'Invalid arguments left a partial output file.');
	}
	fixture_test_assert(fixture_test_run($generator, array('--start-date=2027-06-30', '--output=' . $temporary_directory)) !== 0, 'Directory output unexpectedly succeeded.');

	fwrite(STDOUT, "Fixture generator tests passed.\n");
} catch (Throwable $exception) {
	fwrite(STDERR, 'Fixture generator test failure: ' . $exception->getMessage() . "\n");
	exit(1);
}
