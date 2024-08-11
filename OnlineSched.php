<?php
/*
Plugin Name: OnlineSched
Plugin URI: 
Description: Online Event Scheduling
Version: 0.4
License: BSD 2-Clause

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

include_once("lib/theme.php");
include_once("lib/help.php");
require_once("online-importer.php");

// Define Actions
add_action('init', 'OnlineSched_init');
add_action('admin_init', 'OnlineSched_admin_init');
add_action('save_post', 'OnlineSched_add_timeslot_fields', 10, 2);
add_action('manage_event_schedule_posts_custom_column', 'OnlineSched_columns_content', 10, 2);
add_action('admin_head', 'OnlineSched_add_help_page');
add_action('edited_event_schedule_day_type', 'custom_edit_day_type', 2,  10);
add_action('admin_menu', 'OnlineSched_register_options_page');

// Define Filter
add_filter('manage_event_schedule_posts_columns', 'OnlineSched_columns_head');
add_filter('post_row_actions', 'OnlineSched_remove_row_actions', 10, 2);

// Define Register
register_activation_hook(__FILE__, 'OnlineSched_plugin_activate');
//register_deactivation_hook(__FILE__, 'OnlineSched_plugin_deactivation'); // XXX - Implement

//
// Changing Taxonomy - If change update all the dates
//

function custom_edit_day_type($term_id, $taxonomy) {
	// Upodating it all!
	
	$types = get_terms('event_schedule_day_type', array('term_taxonomy_id' => $taxonomy));

	if (count($types) == 1) {
		$type = $types[0];
		
		$args = array(
			'post_type' => 'event_schedule',
			'tax_query' => array(
				array(
					'taxonomy' => 'event_schedule_day_type',
					'field' => 'term_taxonomy_id',
					'terms' => $taxonomy,
					
				)
			)
			
		);
		
		$posts = get_posts( $args );
		foreach( $posts as $post){
			$event_schedule_id = $post->ID;
			$meta = get_post_meta($event_schedule_id);
			$convert = $type->description . " " .
				 $meta['onlinesched_time_hr'][0] . 
					 ":" .
				 $meta['onlinesched_time_min'][0];
			$sorttime = strtotime($convert);
			
		
			update_post_meta($event_schedule_id, 'onlinesched_sorttime', $sorttime);
		}
	}
	
}

function OnlineSched_remove_row_actions($actions, $post) {
	global $current_screen;

	if ($current_screen->post_type == 'event_schedule') {

		// Remove View & Quick Edit 
		unset( $actions['view'] );
		unset( $actions['inline hide-if-no-js'] );
	}

	return $actions;
}

function OnlineSched_plugin_activate() {

	// Our custom roles
	$role_sched_editor =& get_role('onlinesched_editor');
	if ($role_sched_editor == NULL) {
		add_role('onlinesched_editor', 'OnlineSched Editor', array(

		    // Core Events
		   'read',

		    // Basic Events
		    'edit_onlinesched_event_schedules',
		    'publish_onlinesched_event_schedules',
		    'read_onlinesched_event_schedules',
		    'delete_onlinesched_event_schedules',

		    // Room Types
		    //'manage_event_schedule_room_type',
		    //'edit_event_schedule_room_type',
		    //'delete_event_schedule_room_type',
		    'assign_event_schedule_room_type',

		    // Tags Types
		    //'manage_event_schedule_tags_type',
		    //'edit_event_schedule_tags_type',
		    //'delete_event_schedule_tags_type',
		    'assign_event_schedule_tags_type',

		    // Manage day Types
		    //'manage_event_schedule_day_type',
		    //'edit_event_schedule_day_type',
		    //'delete_event_schedule_day_type',
		    'assign_event_schedule_day_type',

		    // Manage panelist Types
		    'manage_event_schedule_panelist_type',
		    'edit_event_schedule_panelist_type',
		    'delete_event_schedule_panelist_type',
		    'assign_event_schedule_panelist_type',
		));
	}

	// Native Wordpress roles
        $role_administrator =& get_role('administrator');
	if ($role_administrator != NULL) {

		// Basic Events
		$role_administrator->add_cap('edit_onlinesched_event_schedules');
		$role_administrator->add_cap('publish_onlinesched_event_schedules');
		$role_administrator->add_cap('read_onlinesched_event_schedules');
		$role_administrator->add_cap('delete_onlinesched_event_schedules');

		// Manage room Types
		$role_administrator->add_cap('manage_event_schedule_room_type');
		$role_administrator->add_cap('edit_event_schedule_room_type');
		$role_administrator->add_cap('delete_event_schedule_room_type');
		$role_administrator->add_cap('assign_event_schedule_room_type');

		// Manage tags Types
		$role_administrator->add_cap('manage_event_schedule_tags_type');
		$role_administrator->add_cap('edit_event_schedule_tags_type');
		$role_administrator->add_cap('delete_event_schedule_tags_type');
		$role_administrator->add_cap('assign_event_schedule_tags_type');

		// Manage day Types
		$role_administrator->add_cap('manage_event_schedule_day_type');
		$role_administrator->add_cap('edit_event_schedule_day_type');
		$role_administrator->add_cap('delete_event_schedule_day_type');
		$role_administrator->add_cap('assign_event_schedule_day_type');

		// Manage panelist Types
		$role_administrator->add_cap('manage_event_schedule_panelist_type');
		$role_administrator->add_cap('edit_event_schedule_panelist_type');
		$role_administrator->add_cap('delete_event_schedule_panelist_type');
		$role_administrator->add_cap('assign_event_schedule_panelist_type');
	}
}

function OnlineSched_columns_head($defaults) {

	return array(
	    'cb' => '<input type="checkbox" />',
	    'title' => 'Event',
	    'xdate' => 'Date/Time',
	    'length' => 'Length',
	    'taxonomy-event_schedule_room_type' => 'Room',
	    'taxonomy-event_schedule_tags_type' => 'Tag(s)',
	    'taxonomy-event_schedule_panelist_type' => 'Panelist(s)',
	);
}

function OnlineSched_columns_content($column, $post_ID) {

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


function OnlineSched_init() {
	register_post_type(
		'event_schedule',
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
		'event_schedule_room_type',
		'event_schedule',
		array(
			'labels' => array(
				'name' => 'Room Type',
				'add_new_item' => 'Add New Room Type',
				'new_item_name' => "New Room Type Name"
			),
			'show_ui' => true,
			'show_tagcloud' => false,
			'hierarchical' => false,
			'show_admin_column' => true,
			'meta_box_cb' => false,
			'capabilities' => array(
				'manage_terms' => 'manage_event_schedule_room_type',
				'edit_terms' => 'edit_event_schedule_room_type',
				'delete_terms' => 'delete_event_schedule_room_type',
				'assign_terms' => 'assign_event_schedule_room_type',
			)
		)
	);

        register_taxonomy(
                'event_schedule_tags_type',
                'event_schedule',
                array(
                        'labels' => array(
                        	'name' => 'Tag Type',
                       		'add_new_item' => 'Add New Tag Type',
                        	'new_item_name' => "New Tag Type Name"
                	),
                	'show_ui' => true,
                	'hierarchical' => true,
			'show_admin_column' => true,
			'capabilities' => array(
				'manage_terms' => 'manage_event_schedule_tags_type',
				'edit_terms' => 'edit_event_schedule_tags_type',
				'delete_terms' => 'delete_event_schedule_tags_type',
				'assign_terms' => 'assign_event_schedule_tags_type',
			)
        	)
	);

        register_taxonomy(
                'event_schedule_day_type',
                'event_schedule',
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
			'meta_box_cb' => false,
			'capabilities' => array(
				'manage_terms' => 'manage_event_schedule_day_type',
				'edit_terms' => 'edit_event_schedule_day_type',
				'delete_terms' => 'delete_event_schedule_day_type',
				'assign_terms' => 'assign_event_schedule_day_type',
			)
        	)
	);

        register_taxonomy(
                'event_schedule_panelist_type',
                'event_schedule',
                array(
                        'labels' => array(
                        	'name' => 'Panelist Type',
                        	'add_new_item' => 'Add New Panelist Type',
                        	'new_item_name' => "New Panelist Type Name"
			),
			'show_ui' => true,
			'show_tagcloud' => false,
			'hierarchical' => true,
			'show_admin_column' => true,
			'capabilities' => array(
				'manage_terms' => 'manage_event_schedule_panelist_type',
				'edit_terms' => 'edit_event_schedule_panelist_type',
				'delete_terms' => 'delete_event_schedule_panelist_type',
				'assign_terms' => 'assign_event_schedule_panelist_type',
			)
		)
	);

        register_taxonomy(
                'event_schedule_panelist_type',
                'event_schedule',
                array(
                        'labels' => array(
                        	'name' => 'Panelist Type',
                        	'add_new_item' => 'Add New Panelist Type',
                        	'new_item_name' => "New Panelist Type Name"
			),
			'show_ui' => true,
			'show_tagcloud' => false,
			'hierarchical' => true,
			'show_admin_column' => true,
			'capabilities' => array(
				'manage_terms' => 'manage_event_schedule_panelist_type',
				'edit_terms' => 'edit_event_schedule_panelist_type',
				'delete_terms' => 'delete_event_schedule_panelist_type',
				'assign_terms' => 'assign_event_schedule_panelist_type',
			)
		)
	);
}

function OnlineSched_admin_init() {
	add_meta_box(
		'OnlineSched_timeslot',
		'Event Information',
		'OnlineSched_timeslot_metabox',
		'event_schedule', 
		'normal', 
		'high' );
	add_option('event_schedule_year', 'Event Schedule Year');
	register_setting( 'event_schedule_option_group', 'event_schedule_year', 'OnlineSched_callback' );
}

function OnlineSched_register_options_page() {


  add_options_page('Online Sched', 'OnlineSched ', 'manage_options', 'event_schedule', 'OnlineSched_options_page');
}

function OnlineSched_options_page() {
?>
  <div>
  <h2>OnlineSched Options</h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'event_schedule_option_group' ); ?>
  <table>
  <tr valign="top">
  <th scope="row"><label for="event_schedule_year">OnlineSched Year</label></th>
  <td><input type="text" id="event_schedule_year" name="event_schedule_year" value="<?php echo get_option('event_schedule_year'); ?>" /></td>
  </tr>
  </table>
  <?php  submit_button(); ?>
  </form>
  </div>
<?PHP
}


function OnlineSched_taxonomy_dropdown($id, $taxonomy) {
	$assigned = wp_get_post_terms($id, $taxonomy);
	$types = get_terms($taxonomy, array( 'orderby' => 'name', 'hide_empty' => 0));
	if ($types) {
		echo '<select name="' . $taxonomy . '">';
		foreach ( $types as $type ) {
			echo '<option value="' . $type->name . '" ' . selected($assigned[0]->term_id, $type->term_id) . '>';
			echo esc_html($type->name);
			echo '</option>';
		}
		echo '</select>';
	}
}

function OnlineSched_select_num($name, $value, $start, $end, $step = 1) {
	$ret = '<select name="' . $name . '">';
	for ($x = $start; $x <= $end; $x += $step) {
		$dis = ($x < 10 ? "0" . $x : $x );
		$ret .= '<option value="' . $dis . '" ' . ($value == $dis ? "selected" : "") . '>' . $dis;
	}
	$ret .= "</select>";

	return $ret;
}

function OnlineSched_timeslot_metabox($event_schedule) {
	$time_hr  = esc_html(get_post_meta($event_schedule->ID, 'onlinesched_time_hr', true));
	$time_min  = esc_html(get_post_meta($event_schedule->ID, 'onlinesched_time_min', true));
	$timelen = esc_html(get_post_meta($event_schedule->ID, 'onlinesched_timelen', true));
	$year = esc_html(get_post_meta($event_schedule->ID, 'onlinesched_year', true));
	if (strlen($timelen) == 0) {
		$timelen = "60";
	}
		
	$sorttime = esc_html(get_post_meta($event_schedule->ID, 'onlinesched_sorttime', true));
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
		<?php OnlineSched_taxonomy_dropdown($event_schedule->ID, 'event_schedule_day_type') ?>
	</td>
	<td>
		<?php 
		echo OnlineSched_select_num('event_schedule_time_hr', $time_hr, 1, 24) . 
		     ":" .
		     OnlineSched_select_num('event_schedule_time_min', $time_min, 0, 59, 30);
		?>
	</td>
	<td>
		<input type="text" size="10" name="event_schedule_timelen" value="<?php echo $timelen ?>">
	</td>
	<td>
		<?php OnlineSched_taxonomy_dropdown($event_schedule->ID, 'event_schedule_room_type') ?>
	</td>
</tr>
</table>
Event Year: <?php echo $year ?>
<style>
#newevent_schedule_panelist_type_parent,
#newevent_schedule_tags_type_parent,
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

function OnlineSched_update_post_meta($id, $postname, $name) {

	if ( isset($_POST[$postname]) && $_POST[$postname] != '') {
		update_post_meta($id, $name, $_POST[$postname]);
	}
}

function OnlineSched_update_post_terms($id, $postname, $name) {

	if ( isset($_POST[$postname]) && $_POST[$postname] != '') {
		wp_set_post_terms($id, $_POST[$postname], $name);
	}
}

function OnlineSched_add_timeslot_fields($event_schedule_id, $event_schedule) {

	if ($event_schedule->post_type == 'event_schedule') {
		OnlineSched_update_post_meta($event_schedule_id, 'event_schedule_day', 'onlinesched_day');
		OnlineSched_update_post_meta($event_schedule_id, 'event_schedule_panelists', 'onlinesched_panelists');
		OnlineSched_update_post_terms($event_schedule_id, 'event_schedule_room_type', 'event_schedule_room_type');
		OnlineSched_update_post_terms($event_schedule_id, 'event_schedule_day_type', 'event_schedule_day_type');
		OnlineSched_update_post_meta($event_schedule_id, 'event_schedule_time_hr', 'onlinesched_time_hr');
		OnlineSched_update_post_meta($event_schedule_id, 'event_schedule_time_min', 'onlinesched_time_min');
		OnlineSched_update_post_meta($event_schedule_id, 'event_schedule_timelen', 'onlinesched_timelen');
		update_post_meta($event_schedule_id, 'onlinesched_year', get_option('event_schedule_year'));

		$sorttime = -99;
		$types = get_terms('event_schedule_day_type', array('search' => $_POST['event_schedule_day_type']));
		if (count($types) == 1) {
			$sorttime = strtotime($types[0]->description . " " .
			     $_POST['event_schedule_time_hr'] . 
    			     ":" .
			     $_POST['event_schedule_time_min']);
		}
		update_post_meta($event_schedule_id, 'onlinesched_sorttime', $sorttime);
	}
}

add_filter( 'parse_query', 'OnlineSched_posts_filter' );
function OnlineSched_posts_filter($query){
	global $pagenow;

	$type = 'post';
	if (isset($_GET['post_type'])) {
		$type = $_GET['post_type'];
	}

	if ($type == 'event_schedule' && is_admin() && $pagenow=='edit.php') {
		$query->query_vars['meta_key'] = 'onlinesched_year';
		$query->query_vars['meta_value'] = get_option('event_schedule_year');
	}
}

