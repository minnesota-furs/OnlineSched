<?php
/**
 * Encoding and export-shape checks for imported CSV text.
 */

if (!defined('ABSPATH')) {
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

$check('utf-8 em dash', 'a&mdash;b', schedule_convert_to_utf8("a\xE2\x80\x94b"));
$check('utf-8 en dash', 'a&ndash;b', schedule_convert_to_utf8("a\xE2\x80\x93b"));
$check('utf-8 right quote', 'you&rsquo;re', schedule_convert_to_utf8("you\xE2\x80\x99re"));
$check('utf-8 left quote', '&lsquo;x', schedule_convert_to_utf8("\xE2\x80\x98x"));
$check('utf-8 ellipsis', 'a&hellip;', schedule_convert_to_utf8("a\xE2\x80\xA6"));
$check('utf-8 bullet', '&bull;x', schedule_convert_to_utf8("\xE2\x80\xA2x"));

$check('cp1252 em dash', 'a&mdash;b', schedule_convert_to_utf8("a\x97b"));
$check('cp1252 en dash', 'a&ndash;b', schedule_convert_to_utf8("a\x96b"));
$check('cp1252 right quote', 'you&rsquo;re', schedule_convert_to_utf8("you\x92re"));

$check('plain ascii', 'Hello there', schedule_convert_to_utf8('Hello there'));
$check('accented utf-8', "Ad\xC3\xA1n", schedule_convert_to_utf8("Ad\xC3\xA1n"));
$check('empty string', '', schedule_convert_to_utf8(''));

$mixed = schedule_convert_to_utf8("noises\xE2\x80\x94 and \xE2\x80\x99quotes\xE2\x80\x99");
$check('mixed punctuation stays valid utf-8', true, mb_check_encoding($mixed, 'UTF-8'));
$check('no orphaned lead byte', false, str_contains($mixed, "\xE2"));

$check('null input', '', schedule_convert_to_utf8(null));

$required = array('id', 'name', 'date', 'time', 'description', 'room_type', 'speakers', 'length', 'tags');
$header_line = 'ID,Name,Date,Time,Description,Room_Type,Speakers,Length,Tags';
$data_line = '1,A Panel,9/12/2026,10:00,Body,Regency,Someone,60,Gaming';

$parse = function ($bytes) use ($required) {
	$path = tempnam(sys_get_temp_dir(), 'oscsv');
	file_put_contents($path, $bytes);
	$handle = onlinesched_import_open_normalized($path);
	if ($handle === false) {
		unlink($path);
		return array('header' => false, 'rows' => 0);
	}
	$headers = fgetcsv($handle, 0, ',', '"', '');
	$normalized = array_map(
		static function ($header) {
			return strtolower(trim((string) $header));
		},
		is_array($headers) ? $headers : array()
	);
	$header_ok = array_slice($normalized, 0, count($required)) === $required;
	$rows = 0;
	while (fgetcsv($handle, 0, ',', '"', '') !== false) {
		$rows++;
	}
	fclose($handle);
	unlink($path);
	return array('header' => $header_ok, 'rows' => $rows);
};

$lf = $parse("$header_line\n$data_line\n");
$check('LF headers accepted', true, $lf['header']);
$check('LF row parsed', 1, $lf['rows']);

$crlf = $parse("$header_line\r\n$data_line\r\n");
$check('CRLF headers accepted', true, $crlf['header']);
$check('CRLF row parsed', 1, $crlf['rows']);

$cr = $parse("$header_line\r$data_line\r");
$check('lone CR headers accepted', true, $cr['header']);
$check('lone CR row parsed', 1, $cr['rows']);

$bom = $parse("\xEF\xBB\xBF$header_line\n$data_line\n");
$check('byte order mark headers accepted', true, $bom['header']);
$check('byte order mark row parsed', 1, $bom['rows']);

$both = $parse("\xEF\xBB\xBF$header_line\r$data_line\r");
$check('byte order mark with lone CR accepted', true, $both['header']);
$check('byte order mark with lone CR row parsed', 1, $both['rows']);

if ($failures > 0) {
	WP_CLI::error("$failures check(s) failed.");
}
WP_CLI::success('All import encoding checks passed.');
