<?php
/**
 * Role capability regressions.
 *
 * Run on the FM dev stack:
 *   docker exec fm-php wp eval-file \
 *     wp-content/plugins/OnlineSched/tests/roles-test.php \
 *     --path=/var/www/html --allow-root
 *
 * Covers:
 *  1. lead is the editor set plus room, tag and day management;
 *  2. only admin holds manage_onlinesched, which gates the settings, social
 *     login, essentials, badge type and import pages and their saves;
 *  3. the three plugin roles exist with exactly their sets after ensure;
 *  4. the composite WP/Schedule Lead role carries WP editor and lead caps.
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

$editor = onlinesched_editor_capabilities();
$lead   = onlinesched_lead_capabilities();
$admin  = onlinesched_admin_capabilities();

$taxonomy_management = array();
foreach (array('room', 'tag', 'day') as $taxonomy) {
	foreach (array('manage', 'edit', 'delete') as $verb) {
		$taxonomy_management["{$verb}_os_{$taxonomy}"] = true;
	}
}

$expected_lead = $editor + $taxonomy_management;
ksort($expected_lead);
$actual_lead = $lead;
ksort($actual_lead);
$check('lead is editor plus room, tag and day management', $expected_lead, $actual_lead);

$check('editor cannot manage rooms', false, isset($editor['manage_os_room']));
$check('editor cannot manage tags', false, isset($editor['manage_os_tag']));
$check('lead does not hold manage_onlinesched', false, isset($lead['manage_onlinesched']));
$check('admin holds manage_onlinesched', true, isset($admin['manage_onlinesched']));
foreach (array('onlinesched_option_group', 'onlinesched_social_login_group') as $group) {
	$check(
		"$group saves need manage_onlinesched",
		'manage_onlinesched',
		apply_filters("option_page_capability_$group", 'manage_options')
	);
}

$expected_admin = $lead + array('manage_onlinesched' => true);
ksort($expected_admin);
$actual_admin = $admin;
ksort($actual_admin);
$check('admin is lead plus manage_onlinesched', $expected_admin, $actual_admin);

onlinesched_ensure_roles();
foreach (array(
	'onlinesched_editor' => $editor,
	'onlinesched_lead' => $lead,
	'onlinesched_admin' => $admin,
) as $role_name => $expected) {
	$role = get_role($role_name);
	$check("$role_name exists", true, $role instanceof WP_Role);
	if (!$role) {
		continue;
	}
	$granted = array_filter($role->capabilities);
	ksort($granted);
	ksort($expected);
	$check("$role_name holds exactly its set", $expected, $granted);
}

if (function_exists('fm_roles_register_schedule_lead')) {
	fm_roles_register_schedule_lead();
	$composite = get_role('fm_schedule_lead');
	$check('fm_schedule_lead exists', true, $composite instanceof WP_Role);
	if ($composite) {
		$wp_editor = get_role('editor');
		$missing = array();
		foreach (array_keys(array_filter($wp_editor->capabilities)) + array_keys($lead) as $capability) {
			if (empty($composite->capabilities[$capability])) {
				$missing[] = $capability;
			}
		}
		$check('fm_schedule_lead carries WP editor and lead caps', array(), $missing);
		$check('fm_schedule_lead does not hold manage_onlinesched', false, !empty($composite->capabilities['manage_onlinesched']));
	}
} else {
	WP_CLI::log('SKIP: fm-roles mu-plugin not loaded');
}

if ($failures > 0) {
	WP_CLI::error("$failures check(s) failed.");
}
WP_CLI::success('Role capability checks passed.');
