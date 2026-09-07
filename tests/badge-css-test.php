<?php
/**
 * Badge stylesheet regressions.
 *
 * Run on the FM dev stack after npm run build:
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/badge-css-test.php \
 *     --path=/var/www/html --allow-root
 *
 * The danger badge keeps its own fallback rule. A dangling selector once
 * joined it to the VIP rule, so both compiled to the VIP purple.
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
	WP_CLI::warning("FAIL: $label");
	WP_CLI::log('  expected: ' . var_export($expected, true));
	WP_CLI::log('  actual:   ' . var_export($actual, true));
};

$plugin_dir = dirname(__DIR__);
$scss = (string) file_get_contents($plugin_dir . '/src/scss/_badges.scss');
$check(
	'danger badge has its own source rule',
	1,
	preg_match('/&--danger\s*\{[^}]*\$os-danger/', $scss)
);
$check(
	'danger badge is not a selector list with vip',
	0,
	preg_match('/&--danger\s*,/', $scss)
);

$css_file = $plugin_dir . '/build/main.css';
if (is_readable($css_file)) {
	$css = (string) file_get_contents($css_file);
	preg_match_all('/([^{}]*\.os-badge--danger[^{]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);
	$check('compiled danger rule exists', true, count($rules) > 0);
	foreach ($rules as $rule) {
		$check('compiled danger selector stands alone', false, str_contains($rule[1], '--vip'));
		$check('compiled danger uses the danger colour', true, str_contains($rule[2], 'var(--os-danger)'));
	}
} else {
	WP_CLI::log('SKIP: build/main.css missing, run npm run build');
}

if ($failures) {
	WP_CLI::error("$failures badge stylesheet check(s) failed.");
}
WP_CLI::success('Badge stylesheet checks passed.');
