<?php

$csv_path = (string) getenv('ONLINESCHED_TEST_CSV');
$year = (string) getenv('ONLINESCHED_TEST_YEAR');
if ($csv_path === '' || $year === '') {
	throw new RuntimeException('Expected ONLINESCHED_TEST_CSV and ONLINESCHED_TEST_YEAR.');
}
$filter_names = array(
	'wp_sitemaps_enabled',
	'wpseo_enable_metabox_insights',
	'wpseo_use_page_analysis',
	'wpseo_should_index_link',
);
$plugin_action = onlinesched_import_action_state('save_post', 'OnlineSched_add_timeslot_fields');
if ($plugin_action === null) {
	throw new RuntimeException('OnlineSched save_post action was not registered.');
}
remove_action('save_post', 'OnlineSched_add_timeslot_fields', $plugin_action['priority']);
add_action('save_post', 'OnlineSched_add_timeslot_fields', 17, 5);
$before_defer = wp_defer_term_counting();
$before_action = onlinesched_import_action_state('save_post', 'OnlineSched_add_timeslot_fields');
$before_filters = array();
foreach ($filter_names as $filter_name) {
	$before_filters[$filter_name] = has_filter($filter_name, '__return_false');
}

$failure_filter = static function ($term, $taxonomy) {
	if ($taxonomy === 'os_room' && $term === 'Forced Failure Room') {
		return new WP_Error('forced_term_failure', 'Intentional CLI integration-test failure.');
	}
	return $term;
};
add_filter('pre_insert_term', $failure_filter, 10, 2);
$result = onlinesched_import_csv($csv_path, array('year' => $year));
remove_filter('pre_insert_term', $failure_filter, 10);

if ($result['failed'] !== 1 || $result['inserted'] !== 1 || $result['updated'] !== 0) {
	throw new RuntimeException('Incremental import did not report one persisted row and one failed row honestly.');
}
$failed_row_query = new WP_Query(array(
	'post_type'      => 'os_event',
	'post_status'    => onlinesched_event_post_statuses(),
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'meta_query'     => array(
		array('key' => 'onlinesched_year', 'value' => $year),
		array('key' => 'onlinesched_external_event_id', 'value' => '9000'),
	),
));
if (!empty($failed_row_query->posts)) {
	throw new RuntimeException('The row with the forced term failure was written unexpectedly.');
}
if (wp_defer_term_counting() !== $before_defer) {
	throw new RuntimeException('Deferred term-counting state was not restored.');
}
if (onlinesched_import_action_state('save_post', 'OnlineSched_add_timeslot_fields') !== $before_action) {
	throw new RuntimeException('OnlineSched save_post action was not restored.');
}
foreach ($filter_names as $filter_name) {
	if (has_filter($filter_name, '__return_false') !== $before_filters[$filter_name]) {
		throw new RuntimeException($filter_name . ' filter state was not restored.');
	}
}

$second = onlinesched_import_csv($csv_path, array('year' => $year, 'dry_run' => true));
if ($second['failed'] !== 0 || $second['inserted'] !== 1 || $second['updated'] !== 1) {
	throw new RuntimeException('A second import in the same PHP process did not run cleanly.');
}

$cleanup = new WP_Query(array(
	'post_type'      => 'os_event',
	'post_status'    => onlinesched_event_post_statuses(),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'meta_query'     => array(
		array('key' => 'onlinesched_year', 'value' => $year),
		array('key' => 'onlinesched_external_event_id', 'value' => '8999'),
	),
));
foreach ($cleanup->posts as $post_id) {
	wp_delete_post($post_id, true);
}
remove_action('save_post', 'OnlineSched_add_timeslot_fields', $before_action['priority']);
add_action(
	'save_post',
	'OnlineSched_add_timeslot_fields',
	$plugin_action['priority'],
	$plugin_action['accepted_args']
);

echo "service state restored\n";
