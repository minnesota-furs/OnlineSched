<?php

if ($argc !== 3) {
	fwrite(STDERR, "Usage: php build-import-fixtures.php <source.csv> <output-directory>\n");
	exit(1);
}

$source = $argv[1];
$output_dir = $argv[2];
if (!is_dir($output_dir) && !mkdir($output_dir, 0775, true) && !is_dir($output_dir)) {
	throw new RuntimeException('Could not create fixture output directory.');
}

$handle = fopen($source, 'rb');
if ($handle === false) {
	throw new RuntimeException('Could not open source fixture.');
}
$rows = array();
while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
	$rows[] = $row;
}
fclose($handle);
$header = $rows[0];

$write = static function ($name, $fixture_rows) use ($output_dir) {
	$handle = fopen($output_dir . '/' . $name, 'wb');
	if ($handle === false) {
		throw new RuntimeException('Could not create ' . $name);
	}
	foreach ($fixture_rows as $row) {
		fputcsv($handle, $row, ',', '"', '', "\n");
	}
	fclose($handle);
};

$modified = $rows;
foreach ($modified as &$row) {
	if (isset($row[0]) && $row[0] === '4126') {
		$row = array(
			'4126',
			'Sound Design for Games - CLI Update',
			'2027-07-01',
			'16:00',
			'Updated through the CLI integration fixture.',
			'Dealers Den',
			'Coyote Tester, Brushfox',
			'75',
			'Games, Updated',
		);
	}
}
unset($row);
$write('modified.csv', $modified);

$write('duplicate-id.csv', array($header, $rows[1], $rows[1]));
$empty_id = $rows[1];
$empty_id[0] = '';
$write('empty-id.csv', array($header, $empty_id));
$bad_header = $header;
$bad_header[0] = 'Wrong_ID';
$write('bad-header.csv', array($bad_header, $rows[1]));
$invalid_date = $rows[1];
$invalid_date[2] = 'not-a-date';
$invalid_date[3] = '10:00';
$write('invalid-date.csv', array($header, $invalid_date));
$write('short-row.csv', array($header, array('9999', 'Too short')));
