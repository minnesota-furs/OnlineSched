<?php
/**
 * Hours-heading extension contract checks.
 *
 * Run with:
 * docker exec fm-php wp eval-file \
 *   wp-content/plugins/OnlineSched/tests/hours-heading-extra-test.php \
 *   --path=/var/www/html --allow-root
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

$render = function () {
    return OnlineSchedHoursRenderer::render_department(
        array('department' => 'Operations', 'location' => '<strong>Second Floor</strong>'),
        '<dt>Friday</dt><dd>9 AM</dd>'
    );
};

$site_callback = function_exists('furry_migration_con_maps_hours_heading_extra')
    ? has_filter('os_hours_heading_extra_html', 'furry_migration_con_maps_hours_heading_extra')
    : false;
if (false !== $site_callback) {
    remove_filter('os_hours_heading_extra_html', 'furry_migration_con_maps_hours_heading_extra', $site_callback);
}
$html = $render();
$check('no consumer preserves the original heading', 1, substr_count($html, '<h3 class="os-hours__name">Operations</h3>'));
$check('no consumer emits no heading wrapper', false, str_contains($html, 'os-hours__heading'));
$check('a department with a location keeps the normal class', true, str_contains($html, '<section class="os-hours__dept">'));
if (false !== $site_callback) {
    add_filter('os_hours_heading_extra_html', 'furry_migration_con_maps_hours_heading_extra', $site_callback, 3);
}

$first = function ($html, $department, $location) {
    return $html . '<span class="first">' . esc_html($department . '|' . $location) . '</span>';
};
$second = function ($html) {
    return $html . '<a class="second" href="https://example.org/">More</a>';
};
add_filter('os_hours_heading_extra_html', $first, 20, 3);
add_filter('os_hours_heading_extra_html', $second, 30, 1);
$html = $render();
$check('callbacks append in priority order', 1, preg_match('/first.*second/s', $html));
$check('department and plain location reach the consumer', 1, substr_count($html, 'Operations|Second Floor'));
$check('consumer content creates one heading wrapper', 1, substr_count($html, 'class="os-hours__heading"'));
remove_filter('os_hours_heading_extra_html', $first, 20);
remove_filter('os_hours_heading_extra_html', $second, 30);

$without_location = OnlineSchedHoursRenderer::render_department(
    array('department' => 'Furry Logic', 'location' => ''),
    '<dt>Friday</dt><dd>1 PM - 10 PM</dd>'
);
$check(
    'a department without a location exposes its layout state',
    true,
    str_contains($without_location, '<section class="os-hours__dept os-hours__dept--without-location">')
);

$unsafe = function () {
    return '<script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">Bad</a>'
        . '<i class="fa fa-map" aria-hidden="true"></i> Map';
};
add_filter('os_hours_heading_extra_html', $unsafe, 20, 0);
$html = $render();
$check('script elements are removed', false, str_contains($html, '<script'));
$check('unsafe URLs are removed', false, str_contains($html, 'javascript:'));
$check('event handlers are removed', false, str_contains($html, 'onclick'));
$check('safe icon markup survives', 1, substr_count($html, 'fa fa-map'));
remove_filter('os_hours_heading_extra_html', $unsafe, 20);

$wrong_type = function () {
    return array('not html');
};
add_filter('os_hours_heading_extra_html', $wrong_type, 20, 0);
$check('a non-string result emits no wrapper', false, str_contains($render(), 'os-hours__heading'));
remove_filter('os_hours_heading_extra_html', $wrong_type, 20);

if ($failures > 0) {
    WP_CLI::error("hours heading extra: $failures failure(s).");
}
WP_CLI::success('Hours-heading extension checks passed.');
