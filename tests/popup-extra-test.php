<?php
/**
 * Event-popup extension contract checks.
 *
 * Run with:
 * docker exec fm-php wp eval-file \
 *   wp-content/plugins/OnlineSched/tests/popup-extra-test.php \
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

$GLOBALS['post'] = null;
$event_id = 987654321;
$check('no consumer returns no content', '', onlinesched_event_popup_extra_html($event_id));

$first = function ($html, $filtered_event_id) use ($event_id) {
    return $html . '<span class="first">' . ($filtered_event_id === $event_id ? 'One' : 'Wrong') . '</span>';
};
$second = function ($html) {
    return $html . '<a class="second" href="https://example.org/">Two</a>';
};
add_filter('os_event_popup_extra_html', $first, 20, 2);
add_filter('os_event_popup_extra_html', $second, 30, 1);
$html = onlinesched_event_popup_extra_html($event_id);
$check('callbacks append in priority order', 1, preg_match('/first.*second/s', $html));
$check('the event id reaches the consumer', 1, substr_count($html, '>One<'));
remove_filter('os_event_popup_extra_html', $first, 20);
remove_filter('os_event_popup_extra_html', $second, 30);

$unsafe = function () {
    return '<script>alert(1)</script><a href="javascript:alert(2)" onclick="alert(3)">Bad</a>'
        . '<i class="fa fa-paw" aria-hidden="true"></i> Paw';
};
add_filter('os_event_popup_extra_html', $unsafe, 20, 0);
$html = onlinesched_event_popup_extra_html($event_id);
$check('script elements are removed', false, str_contains($html, '<script'));
$check('unsafe URLs are removed', false, str_contains($html, 'javascript:'));
$check('event handlers are removed', false, str_contains($html, 'onclick'));
$check('safe icon markup and text survive', 1, substr_count($html, 'fa fa-paw'));
remove_filter('os_event_popup_extra_html', $unsafe, 20);

$wrong_type = function () {
    return array('not html');
};
add_filter('os_event_popup_extra_html', $wrong_type, 20, 0);
$check('a non-string result becomes empty', '', onlinesched_event_popup_extra_html($event_id));
remove_filter('os_event_popup_extra_html', $wrong_type, 20);

if ($failures > 0) {
    WP_CLI::error("popup extra: $failures failure(s).");
}
WP_CLI::success('Event-popup extension checks passed.');
