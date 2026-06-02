<?php
/*
Plugin Name: OnlineSched
Plugin URI: https://github.com/onlinesched/OnlineSched
Description: A flexible event scheduling plugin for conventions and organizations.
Version: 1.3.0
Requires at least: 6.4
Requires PHP: 8.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: BL, BM, AL & Contributors
Text Domain: onlinesched
Domain Path: /languages

Todo List:
- Write deactivation hook (prompt user)
	- Clean roles/caps
	- Remove custom post type data
- Break up into smaller Plug-ins
	- Write add_action() for register_* for cap support
	- Break all current caps to OnlineSched_FM_Security
- Break up remaining plug-in into smaller files
	- Break off help pages
- Write added lib/theme.php features to encasulate some of the hard bits for theming
- Clean up register_* to prefix w/ OnlineSched_
- Check into github.com repo and update "Plugin URI:"
*/

if (!defined('ONLINESCHED_PLUGIN_FILE')) {
	define('ONLINESCHED_PLUGIN_FILE', __FILE__);
}

if (!defined('ONLINESCHED_PLUGIN_DIR')) {
	define('ONLINESCHED_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('ONLINESCHED_PLUGIN_URL')) {
	define('ONLINESCHED_PLUGIN_URL', plugin_dir_url(__FILE__));
}

include_once("lib/config.php");
include_once("lib/theme.php");
include_once("lib/help.php");
require_once("OnlineSchedImportExporter.php");
require_once('OnlineSchedHelp.php');
require_once('lib/install_theme_support.php');
require_once('lib/schedule.php');
require_once('OnlineSchedSettings.php');
require_once('lib/render.php');
require_once('includes/shortcode_schedule.php');
require_once('includes/hours-blocks.php');
require_once('includes/shortcode_schedule_cheat_display.php');
require_once('includes/solo-event-block.php');
require_once('includes/rest-api.php');
require_once('includes/favorites.php');
require_once('includes/privacy.php');
require_once("OnlineSchedBadgeTypes.php");
require_once('OnlineSchedEssentials.php');
require_once('OnlineSchedSocialLogin.php');

// Define Actions
add_action('init', 'OnlineSched_init');
add_action('admin_init', 'onlinesched_ensure_roles', 5);
add_action('admin_init', 'OnlineSched_admin_init');
add_action('save_post', 'OnlineSched_add_timeslot_fields', 10, 2);
add_action('manage_os_event_posts_custom_column', 'OnlineSched_columns_content', 10, 2);
add_action('admin_head', 'OnlineSched_add_help_page');
add_action('admin_head', 'onlinesched_settings_admin_styles');
add_action('admin_enqueue_scripts', 'onlinesched_admin_enqueue_assets');
add_action('edited_os_day', 'custom_edit_day_type', 2, 10);
add_action('admin_menu', 'onlinesched_register_submenus', 10);

// Define Filter
add_filter('manage_os_event_posts_columns', 'OnlineSched_columns_head');
add_filter('post_row_actions', 'OnlineSched_remove_row_actions', 10, 2);

// Define Register
register_activation_hook(__FILE__, 'OnlineSched_plugin_activate');
register_activation_hook(__FILE__, 'onlinesched_create_favorites_table');
register_activation_hook(__FILE__, 'onlinesched_auto_detect_pages');
//
// Changing Taxonomy - If change update all the dates
//

function onlinesched_admin_enqueue_assets($hook) {
    $allowed_hooks = array(
        'os_event_page_onlinesched-settings',
        'os_event_page_onlinesched-essentials',
        'os_event_page_onlinesched-badge-types',
        'os_event_page_event-schedule-help'
    );
    if (!in_array($hook, $allowed_hooks)) {
        return;
    }
    wp_enqueue_style('onlinesched-fa', ONLINESCHED_PLUGIN_URL . 'build/fontawesome.css', array(), '6.0.0');
}

function custom_edit_day_type($term_id, $taxonomy)
{
	// Upodating it all!

	$types = get_terms('os_day', array('term_taxonomy_id' => $taxonomy));

	if (count($types) == 1) {
		$type = $types[0];

		$args = array(
			'post_type' => 'os_event',
			'tax_query' => array(
				array(
					'taxonomy' => 'os_day',
					'field' => 'term_taxonomy_id',
					'terms' => $taxonomy,

				)
			)

		);

		$posts = get_posts($args);
		foreach ($posts as $post) {
			$os_event_id = $post->ID;
			$meta = get_post_meta($os_event_id);
			$convert = $type->description . " " .
				$meta['onlinesched_time_hr'][0] .
				":" .
				$meta['onlinesched_time_min'][0];
			$sorttime = strtotime($convert);


			update_post_meta($os_event_id, 'onlinesched_sorttime', $sorttime);
		}
	}

}

function OnlineSched_remove_row_actions($actions, $post)
{
	global $current_screen;

	if ($current_screen->post_type == 'os_event') {

		// Remove View & Quick Edit
		unset($actions['view']);
		unset($actions['inline hide-if-no-js']);
	}

	return $actions;
}

function onlinesched_capability_map(array $capabilities)
{
	return array_fill_keys($capabilities, true);
}

function onlinesched_editor_capabilities()
{
	return onlinesched_capability_map(array(
		'read',
		'edit_onlinesched_event_schedules',
		'publish_onlinesched_event_schedules',
		'read_onlinesched_event_schedules',
		'delete_onlinesched_event_schedules',
		'assign_os_room',
		'assign_os_tag',
		'assign_os_day',
		'manage_os_panelist',
		'edit_os_panelist',
		'delete_os_panelist',
		'assign_os_panelist',
	));
}

function onlinesched_admin_capabilities()
{
	return onlinesched_capability_map(array(
		'read',
		'edit_onlinesched_event_schedules',
		'publish_onlinesched_event_schedules',
		'read_onlinesched_event_schedules',
		'delete_onlinesched_event_schedules',
		'manage_os_room',
		'edit_os_room',
		'delete_os_room',
		'assign_os_room',
		'manage_os_tag',
		'edit_os_tag',
		'delete_os_tag',
		'assign_os_tag',
		'manage_os_day',
		'edit_os_day',
		'delete_os_day',
		'assign_os_day',
		'manage_os_panelist',
		'edit_os_panelist',
		'delete_os_panelist',
		'assign_os_panelist',
	));
}

function onlinesched_remove_numeric_role_capabilities($role)
{
	foreach ($role->capabilities as $capability => $granted) {
		if (is_int($capability) || ctype_digit((string) $capability)) {
			$role->remove_cap($capability);
		}
	}
}

function onlinesched_is_plugin_role_capability($capability)
{
	return false !== strpos($capability, 'onlinesched')
		|| preg_match('/^(manage|edit|delete|assign)_os_/', $capability);
}

function onlinesched_known_stale_custom_role_capabilities()
{
	return array_fill_keys(array(
		'level_0',
		'edit_pages',
		'edit_others_pages',
		'publish_pages',
		'edit_published_pages',
		'edit_private_pages',
	), true);
}

function onlinesched_remove_stale_role_capabilities($role, array $capabilities)
{
	$known_stale_capabilities = onlinesched_known_stale_custom_role_capabilities();

	foreach ($role->capabilities as $capability => $granted) {
		if (isset($capabilities[$capability])) {
			continue;
		}

		if (onlinesched_is_plugin_role_capability($capability) || isset($known_stale_capabilities[$capability])) {
			$role->remove_cap($capability);
		}
	}
}

function onlinesched_apply_capabilities_to_role($role_name, $display_name, array $capabilities)
{
	$role = get_role($role_name);
	if ($role == NULL) {
		add_role($role_name, $display_name, $capabilities);
		$role = get_role($role_name);
	}

	if ($role == NULL) {
		return;
	}

	onlinesched_remove_numeric_role_capabilities($role);
	onlinesched_remove_stale_role_capabilities($role, $capabilities);
	foreach ($capabilities as $capability => $grant) {
		if ($grant) {
			$role->add_cap($capability);
		}
	}
}

function onlinesched_add_capabilities_to_existing_role($role_name, array $capabilities)
{
	$role = get_role($role_name);
	if ($role == NULL) {
		return;
	}

	foreach ($capabilities as $capability => $grant) {
		if ($grant) {
			$role->add_cap($capability);
		}
	}
}

function onlinesched_ensure_roles()
{
	onlinesched_apply_capabilities_to_role('onlinesched_editor', 'OnlineSched Editor', onlinesched_editor_capabilities());
	onlinesched_apply_capabilities_to_role('onlinesched_admin', 'OnlineSched Admin', onlinesched_admin_capabilities());
	onlinesched_add_capabilities_to_existing_role('administrator', onlinesched_admin_capabilities());
	onlinesched_add_capabilities_to_existing_role('editor', onlinesched_editor_capabilities());
}

function OnlineSched_plugin_activate()
{
	onlinesched_ensure_roles();
}

function onlinesched_create_favorites_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'onlinesched_favorites';
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        provider varchar(64) NOT NULL,
        identifier varchar(255) NOT NULL,
        favorites longtext NULL,
        last_updated datetime DEFAULT NULL,
        last_logged_in datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY provider_identifier (provider, identifier)
    ) $charset_collate;";
    dbDelta($sql);
}

function OnlineSched_columns_head($defaults)
{

	return array(
		'cb' => '<input type="checkbox" />',
		'title' => 'Event',
		'xdate' => 'Date/Time',
		'length' => 'Length',
		'taxonomy-os_room' => 'Room',
		'taxonomy-os_tag' => 'Tag(s)',
		'taxonomy-os_panelist' => 'Panelist(s)',
	);
}

function OnlineSched_columns_content($column, $post_ID)
{

	if ($column == 'length') {
		echo esc_html(get_post_meta($post_ID, 'onlinesched_timelen', true));
	} else if ($column == 'xdate') {
		$sorttime = get_post_meta($post_ID, 'onlinesched_sorttime', true);
		if ($sorttime == -99) {
			echo "Unknown(1)";
		} else if ($sorttime == 0) {
			echo "Unknown";
		} else {
			echo esc_html(date('D m/d/Y h:iA', $sorttime));
		}
	}
}

function OnlineSched_init()
{
	register_post_type(
		'os_event',
		array(
			'labels' => array(
				'name' => 'Event Scheduling',
				'singular_name' => 'Event Schedule Entry',
				'add_new' => 'Add Event',
				'add_new_item' => 'Add New Event',
				'edit' => 'Edit',
				'edit_item' => 'Edit Event',
				'new_item' => 'New Event',
				'view' => 'View',
				'view_item' => 'View Event',
				'search_items' => 'Search Event',
				'not_found' => 'No Events found',
				'not_found_in_trash' => 'No Events found in Trash',
				'parent' => 'Parent Event'
			),
			'public' => false,
			'show_in_nav_menus' => true,
			'show_ui' => true,
			'menu_position' => 20,
			'supports' => array(
				'title',
				'editor',
			),
			'taxonomies' => array(''),
			//		'menu_icon' => plugins_url('event-16x16.png', __FILE__),	// XXX - Generate an icon some day
			'has_archive' => false,
			'publicly_queryable' => false,
			'capability_type' => 'onlinesched_event_schedule',
			'capabilities' => array(
				'edit_post' => 'edit_onlinesched_event_schedules',
				'read_post' => 'read_onlinesched_event_schedules',
				'delete_post' => 'delete_onlinesched_event_schedules',
				'edit_posts' => 'edit_onlinesched_event_schedules',
				'edit_others_posts' => 'edit_onlinesched_event_schedules',
				'publish_posts' => 'publish_onlinesched_event_schedules',
				'read_private_posts' => 'read_onlinesched_event_schedules',
				'delete_posts' => 'delete_onlinesched_event_schedules',
				'delete_private_posts' => 'delete_onlinesched_event_schedules',
				'delete_published_posts' => 'delete_onlinesched_event_schedules',
				'delete_others_posts' => 'delete_onlinesched_event_schedules',
				'edit_private_posts' => 'edit_onlinesched_event_schedules',
				'edit_published_posts' => 'edit_onlinesched_event_schedules',
				'create_posts' => 'publish_onlinesched_event_schedules',
			),
		)
	);

	register_taxonomy(
		'os_room',
		'os_event',
		array(
			'labels' => array(
				'name' => 'Room Type',
				'add_new_item' => 'Add New Room Type',
				'new_item_name' => "New Room Type Name"
			),
			'show_ui' => true,
			'show_tagcloud' => false,
			'publicly_queryable' => false,
			'hierarchical' => false,
			'show_admin_column' => true,
			'meta_box_cb' => false,
			'capabilities' => array(
				'manage_terms' => 'manage_os_room',
				'edit_terms' => 'edit_os_room',
				'delete_terms' => 'delete_os_room',
				'assign_terms' => 'assign_os_room',
			)
		)
	);

	register_taxonomy(
		'os_tag',
		'os_event',
		array(
			'labels' => array(
				'name' => 'Tag Type',
				'add_new_item' => 'Add New Tag Type',
				'new_item_name' => "New Tag Type Name"
			),
			'show_ui' => true,
			'hierarchical' => false, // Remove parent category field
			'show_admin_column' => true,
			'publicly_queryable' => false,
			'capabilities' => array(
				'manage_terms' => 'manage_os_tag',
				'edit_terms' => 'edit_os_tag',
				'delete_terms' => 'delete_os_tag',
				'assign_terms' => 'assign_os_tag',
			)
		)
	);

	register_taxonomy(
		'os_day',
		'os_event',
		array(
			'labels' => array(
				'name' => 'Day Type',
				'add_new_item' => 'Add Day Tag Type',
				'new_item_name' => "New Day Type Name"
			),
			'show_ui' => true,
			'show_tagcloud' => false,
			'hierarchical' => false,
			'show_admin_column' => true,
			'publicly_queryable' => false,
			'meta_box_cb' => false,
			'capabilities' => array(
				'manage_terms' => 'manage_os_day',
				'edit_terms' => 'edit_os_day',
				'delete_terms' => 'delete_os_day',
				'assign_terms' => 'assign_os_day',
			)
		)
	);

	register_taxonomy(
		'os_panelist',
		'os_event',
		array(
			'labels' => array(
				'name' => 'Panelist Type',
				'add_new_item' => 'Add New Panelist Type',
				'new_item_name' => "New Panelist Type Name"
			),
			'show_ui' => true,
			'show_tagcloud' => false,
			'hierarchical' => false,
			'show_admin_column' => true,
			'publicly_queryable' => false,
			'capabilities' => array(
				'manage_terms' => 'manage_os_panelist',
				'edit_terms' => 'edit_os_panelist',
				'delete_terms' => 'delete_os_panelist',
				'assign_terms' => 'assign_os_panelist',
			)
		)
	);
}

// Remove Description field ONLY from os_tag taxonomy add/edit screens
add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->taxonomy === 'os_tag') {
        echo '<style>
            .term-description-wrap, .form-field.term-description-wrap, #tag-description, label[for="tag-description"], .column-description { display: none !important; }
        </style>';
    }
});
// Optionally remove Excerpt meta box from os_event post type
add_action('admin_init', function() {
    remove_meta_box('postexcerpt', 'os_event', 'normal');
});

function OnlineSched_taxonomy_dropdown($id, $taxonomy)
{
	$assigned = wp_get_post_terms($id, $taxonomy);
	$types = get_terms($taxonomy, array('orderby' => 'name', 'hide_empty' => 0));
	if ($types) {
		echo '<select name="' . $taxonomy . '">';
		foreach ($types as $type) {
			echo '<option value="' . $type->name . '" ' . selected($assigned[0]->term_id, $type->term_id) . '>';
			echo esc_html($type->name);
			echo '</option>';
		}
		echo '</select>';
	}
}

function OnlineSched_select_num($name, $value, $start, $end, $step = 1)
{
	$ret = '<select name="' . $name . '">';
	for ($x = $start; $x <= $end; $x += $step) {
		$dis = ($x < 10 ? "0" . $x : $x);
		$ret .= '<option value="' . $dis . '" ' . ($value == $dis ? "selected" : "") . '>' . $dis;
	}
	$ret .= "</select>";

	return $ret;
}

function OnlineSched_timeslot_metabox($os_event)
{
    wp_nonce_field('onlinesched_save_timeslot', 'onlinesched_timeslot_nonce');

	$time_hr = esc_html(get_post_meta($os_event->ID, 'onlinesched_time_hr', true));
	$time_min = esc_html(get_post_meta($os_event->ID, 'onlinesched_time_min', true));
	$timelen = esc_html(get_post_meta($os_event->ID, 'onlinesched_timelen', true));
	$year = esc_html(get_post_meta($os_event->ID, 'onlinesched_year', true));
	if (strlen($timelen) == 0) {
		$timelen = "60";
	}

	$sorttime = esc_html(get_post_meta($os_event->ID, 'onlinesched_sorttime', true));
	?>

    <table width="100%">
        <tr>
            <td>Day of Week</td>
            <td>Start Time</td>
            <td>Length (Minutes)</td>
            <td>Room</td>
        </tr>
        <tr>
            <td>
				<?php OnlineSched_taxonomy_dropdown($os_event->ID, 'os_day') ?>
            </td>
            <td>
				<?php
				echo OnlineSched_select_num('os_event_time_hr', $time_hr, 1, 24) .
					":" .
					OnlineSched_select_num('os_event_time_min', $time_min, 0, 59, 30);
				?>
            </td>
            <td>
                <input type="text" size="10" name="os_event_timelen" value="<?php echo $timelen ?>">
            </td>
            <td>
				<?php OnlineSched_taxonomy_dropdown($os_event->ID, 'os_room') ?>
            </td>
        </tr>
    </table>
    Event Year: <?php echo $year ?>
    <style>
        #newos_panelist_parent,
        #newos_tag_parent,
        div#wp-content-media-buttons,
        div#edit-slug-box,
        div#gdd_page_redirect,
        div#message a,
        div#minor-publishing {
            visibility: hidden;
            height: 0px;
            padding: 0px;
        }

    </style>
	<?php
}

function OnlineSched_update_post_meta($id, $postname, $name)
{

	if (isset($_POST[$postname]) && $_POST[$postname] != '') {
		update_post_meta($id, $name, sanitize_text_field(wp_unslash($_POST[$postname])));
	}
}

function OnlineSched_update_post_terms($id, $postname, $name)
{

	if (isset($_POST[$postname]) && $_POST[$postname] != '') {
		wp_set_post_terms($id, sanitize_text_field(wp_unslash($_POST[$postname])), $name);
	}
}

function OnlineSched_add_timeslot_fields($os_event_id, $os_event)
{

	if ($os_event->post_type == 'os_event') {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($os_event_id) || wp_is_post_autosave($os_event_id)) {
            return;
        }

        if (!isset($_POST['onlinesched_timeslot_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['onlinesched_timeslot_nonce'])), 'onlinesched_save_timeslot')) {
            return;
        }

        if (!current_user_can('edit_onlinesched_event_schedules')) {
            return;
        }

		OnlineSched_update_post_meta($os_event_id, 'os_event_day', 'onlinesched_day');
		OnlineSched_update_post_meta($os_event_id, 'os_event_panelists', 'onlinesched_panelists');
		OnlineSched_update_post_terms($os_event_id, 'os_room', 'os_room');
		OnlineSched_update_post_terms($os_event_id, 'os_day', 'os_day');
		OnlineSched_update_post_meta($os_event_id, 'os_event_time_hr', 'onlinesched_time_hr');
		OnlineSched_update_post_meta($os_event_id, 'os_event_time_min', 'onlinesched_time_min');
		OnlineSched_update_post_meta($os_event_id, 'os_event_timelen', 'onlinesched_timelen');
		update_post_meta($os_event_id, 'onlinesched_year', get_option('onlinesched_year'));

		$sorttime = -99;
        $posted_day = isset($_POST['os_day']) ? sanitize_text_field(wp_unslash($_POST['os_day'])) : '';
        $posted_hour = isset($_POST['os_event_time_hr']) ? sanitize_text_field(wp_unslash($_POST['os_event_time_hr'])) : '';
        $posted_min = isset($_POST['os_event_time_min']) ? sanitize_text_field(wp_unslash($_POST['os_event_time_min'])) : '';
		$types = get_terms('os_day', array('search' => $posted_day));
		if (count($types) == 1) {
			$sorttime = strtotime($types[0]->description . " " .
				$posted_hour .
				":" .
				$posted_min);
		}
		update_post_meta($os_event_id, 'onlinesched_sorttime', $sorttime);

		/**
		 * Hook for cache purging and other post-save actions.
		 */
		do_action('onlinesched_event_updated', $os_event_id);
	}
}

add_filter('parse_query', 'OnlineSched_posts_filter');
function OnlineSched_posts_filter($query)
{
	global $pagenow;

	$type = 'post';
	if (isset($_GET['post_type'])) {
		$type = $_GET['post_type'];
	}

	if ($type == 'os_event' && is_admin() && $pagenow == 'edit.php') {
		$query->query_vars['meta_key'] = 'onlinesched_year';
		$query->query_vars['meta_value'] = get_option('onlinesched_year');
	}
}

function warn_before_deleting_os_event() {
	global $post_type;

	// Only apply to os_event post type
	if ($post_type === 'os_event') {
		?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('a.submitdelete').forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        if (!confirm("Are you sure you want to delete this event?\nIf you are canceling it, consider updating tags to be cancelled.\nThis will allow other people to know that it was cancelled just not disappear off their schedules.")) {
                            e.preventDefault();
                        }
                    });
                });
            });
        </script>
		<?php
	}
}
add_action('admin_footer', 'warn_before_deleting_os_event');

function onlinesched_register_submenus() {
    add_submenu_page(
        'edit.php?post_type=os_event',
        'Badge Types',
        'Badge Types',
        'manage_os_tag',
        'onlinesched-badge-types',
        'onlinesched_badge_types_page'
    );
    add_submenu_page(
        'edit.php?post_type=os_event',
        'Essential Tab Settings',
        'Essential Tab Settings',
        'manage_os_tag',
        'onlinesched-essentials',
        'onlinesched_essentials_page'
    );
    add_submenu_page(
        'edit.php?post_type=os_event',
        'CSV Uploader',
        'CSV Uploader',
        'manage_os_room',
        'event-schedule-csv-uploader',
        'os_event_csv_uploader_page'
    );
    OnlineSched_register_options_page(); // Event Settings
    OnlineSched_register_config_status_page(); // Configuration Status
    OnlineSched_register_social_login_page(); // Social Login
    add_submenu_page(
        'edit.php?post_type=os_event',
        'Hints & Help',
        'Hints & Help',
        'manage_os_room',
        'event-schedule-help',
        'os_event_help_page'
    );
}
