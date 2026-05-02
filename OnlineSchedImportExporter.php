<?php

include("lib/YoastPauser.php");


function os_event_csv_export_handler()
{
	if (isset($_POST['export_csv'])) {
		export_os_event_csv();
		exit(); // Kit is after
	}
}


function os_event_csv_uploader_page()
{
    remove_filter('parse_query', 'OnlineSched_posts_filter');
    ?>
    <style>
    /* Style for upload-error to match WordPress admin error notice */
    .upload-error {
        background: #fff;
        border-left: 4px solid #dc3232;
        margin: 20px 0 20px 0;
        padding: 12px 12px 12px 16px;
        box-shadow: 0 1px 1px 0 rgba(0,0,0,.04);
        color: #b32d2e;
        font-size: 14px;
        line-height: 1.5;
        border-radius: 2px;
    }
    .upload-error p {
        margin: 0;
    }
    /* Style for schedule-updated to match WordPress updated notice but custom color */
    .schedule-updated {
        background: #f6ffed;
        border-left: 4px solid #46b450;
        margin: 20px 0 20px 0;
        padding: 12px 12px 12px 16px;
        box-shadow: 0 1px 1px 0 rgba(0,0,0,.04);
        color: #1a531b;
        font-size: 14px;
        line-height: 1.5;
        border-radius: 2px;
    }
    .schedule-updated p {
        margin: 0;
    }
    </style>
    <div class="wrap">
        <h2>Upload Event Schedule CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="os_event_csv" accept=".csv" required>
			<?php submit_button('Upload CSV'); ?>
            <br/>
            <p><strong>Note:</strong> All imports will be associated
                with <?php echo get_option('onlinesched_year'); ?> year. Change in settings->Online scheduler.<br/>
                <strong>Note:</strong> If item doesn't exist in the upload set it will still stay there.<br/>
                <strong>Note:</strong> This will take some time to upload. Please be patient.
            </p>
        </form>

        <hr/>
        <h2>Export Current Schedule CSV</h2>
        <form method="post">
			<?php submit_button('Export CSV', 'primary', 'export_csv'); ?>
        </form>

        <hr/>
        <br/>
        <h2>Clean Up Tasks</h2>
        <form method="post">
			<?php submit_button('Delete All Event Schedule Posts', 'delete', 'delete_all_os_event_posts'); ?>
            <br/>This will delete <strong>ALL YEARS</strong> posts even hidden ones.
        </form>
        <form method="post">
			<?php submit_button('Delete Unused Panelists', 'delete', 'delete_unused_panelists'); ?>
        </form>

        <form method="post">
			<?php submit_button('Delete Unused Days', 'delete', 'delete_unused_days'); ?>
        </form>
    </div>
	<?php
	if (isset($_FILES['os_event_csv'])) {

		handle_os_event_csv_upload($_FILES['os_event_csv']);

	}


	if (isset($_POST['delete_all_os_event_posts'])) {
		delete_all_os_event_posts();
	}

	if (isset($_POST['delete_unused_panelists'])) {
		delete_unused_tax('os_panelist', 'Panalists');
	}

	if (isset($_POST['delete_unused_days'])) {
		delete_unused_tax('os_day', "Days");
	}
}

function delete_all_os_event_posts()
{
	$args = array(
		'post_type' => 'os_event',
		'posts_per_page' => -1,
		'post_status' => 'any'
	);
	$events = get_posts($args);

	foreach ($events as $event) {
		wp_delete_post($event->ID, true);
	}

	echo '<div class="schedule-updated"><p>All Event Schedule posts have been deleted.</p></div>';
}

function delete_unused_tax($taxonomy, $name)
{
	$terms = get_terms(array(
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
	));

	foreach ($terms as $term) {
		$term_count = $term->count;
		if ($term_count == 0) {
			wp_delete_term($term->term_id, $taxonomy);
		}
	}

	echo "<div class=\"schedule-updated\"><p>All unused {$name} have been deleted.</p></div>";
}


function handle_os_event_csv_upload($file)
{

	$start_time = microtime(true);

	// prevent writing to innodb until it needs to
	global $wpdb;
	$wpdb->query('START TRANSACTION');

	set_time_limit(1200); // 10 minutes
	ini_set('memory_limit', '6096M');

    // handle the stop button as ignore like a 8 year old just continue to work
	ignore_user_abort(true);
	set_time_limit(0);

	//$pauser = new YoastPauser();
	//$pauser->pause();

	$onlinesched_year = get_option('onlinesched_year');

	if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
		wp_defer_term_counting(true);

		$room_cache = online_sched_grab_all_tags('os_room');
		$panelist_cache = online_sched_grab_all_tags('os_panelist', 'name');
		// Normalize panelist_cache keys for case/whitespace
		$normalized_panelist_cache = array();
		foreach ($panelist_cache as $name => $data) {
			$normalized_panelist_cache[strtolower(trim($name))] = $data;
		}
		$tag_cache = online_sched_grab_all_tags('os_tag');

		// disable sitemaps
		add_filter('wp_sitemaps_enabled', '__return_false');

		// disable some yoast stuff
		add_filter('wpseo_enable_metabox_insights', '__return_false');
		add_filter('wpseo_use_page_analysis', '__return_false');
		add_filter('wpseo_should_index_link', '__return_false');

		// remove action since we are handling the upload
		remove_action('save_post', 'OnlineSched_add_timeslot_fields', 10);

		$headers = fgetcsv($handle, 4000, ',');

		$required_headers = array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags');

		// lower case both
		$headers = array_map('strtolower', array_change_key_case($headers, CASE_LOWER));
		$required_headers = array_map('strtolower', array_change_key_case($required_headers, CASE_LOWER));

		if (array_slice($headers, 0, count($required_headers)) !== $required_headers) {
			echo '<div class="upload-error"><p>CSV file format is incorrect. Expected headers: ID, Name, Date, Time, Description, Room_Type, Speakers, Length, Tags).</p></div>';
			return;
		}

		$unscheduled_term = term_exists('Unscheduled', 'os_day');
		if (!$unscheduled_term) {
			$unscheduled_term = wp_insert_term('Unscheduled', 'os_day', array('description' => '0'));
		}
		$day_tag_cache = online_sched_grab_all_tags('os_day');

		// Disable W3 Total Cache before starting the import
		if (function_exists('w3tc_flush_all')) {
			// Disable page caching
			define('DONOTCACHEPAGE', true);
			// Disable database caching
			//  define('DONOTCACHEDB', true);
			// Disable object caching
			//  define('DONOTCACHEOBJECT', true);
			// Disable minify caching
			define('DONOTMINIFY', true);
		}

		$row = 1;
		while (($data = fgetcsv($handle, 4000, ',')) !== FALSE) {
			$row++;
			$input_line = $row + 1; // Account for header line and 0-based index
            if (count($data) < count($required_headers)) {
                echo "<div class='upload-error'><p>Row $row (input line $input_line) has a mismatched number of fields.</p></div>";
                continue;
            }

			$external_event_id = schedule_convert_to_utf8_and_santize($data[0]);
			$name = schedule_convert_to_utf8_and_santize($data[1]);
			$date = schedule_convert_to_utf8_and_santize($data[2]);
			$time = schedule_convert_to_utf8_and_santize($data[3]);
			$description = schedule_convert_to_utf8_and_kses($data[4]);
			$room_type = schedule_convert_to_utf8_and_santize($data[5]);
			$speakers = schedule_convert_to_utf8_and_santize($data[6]);
			$length = schedule_convert_to_utf8_and_santize($data[7]);
			$tags = schedule_convert_to_utf8_and_santize($data[8]);

			if (empty($name)) {
				$name = trim(wp_kses_post($data[1])); // reduce it a bit just in case
			}

			if (empty($name)) {
				echo "<div class='upload-error'><p>Row $row (input line $input_line) has no name In String In case of filter {$data[1]}, ID {$external_event_id}, description: {$description}.</p></div>";
				continue;
			}


			if (empty($date) || empty($time)) {
				$day_of_week = 'Unscheduled';
				$formatted_date = '0';
				$hour = 0;
				$minutes = 0;
				$mysql_time = 0;
			} else {
				// Check if the time string includes seconds
				if (strpos($time, ':') === false || substr_count($time, ':') == 1) {
					// Time does not include seconds, append ':00'
					$time = "{$time}:00";
				}

				$full_date = DateTime::createFromFormat('Y-m-d H:i:s', "{$date} {$time}");

				if (!$full_date) {
					// Try without leading zeros for day and month
					$full_date = DateTime::createFromFormat('Y-n-j H:i:s', "{$date} {$time}");
				}

				if (!$full_date) {
					// Try without leading zeros for day and month
					$full_date = DateTime::createFromFormat('n/j/Y H:i:s', "{$date} {$time}");
					if (!empty($full_date)) {
						if (intval($full_date->format('Y')) < 1000) {
							$full_date = DateTime::createFromFormat('n/j/y H:i:s', "{$date} {$time}");
						}
					}
				}

				if (!$full_date) {
					echo "<div class='upload-error'><p>Row $row (input line $input_line) has an invalid DateTime format. Expected format: Y-m-d H:i:s or n/j/Y H:i:s. {$date} {$time}</p></div>";
					continue;
				}

				$day_of_week = $full_date->format('l');
				$formatted_date = $full_date->format('n/j/Y');
				$hour = $full_date->format('H');
				$minutes = $full_date->format('i');
				$mysql_time = $full_date->getTimestamp();
			}

			// Check for existing event
			$event_id = get_post_id_by_event_id($external_event_id);


			$post_data = array(
				'post_title' => $name,
				'post_content' => $description,
				'post_status' => 'publish',
				'post_type' => 'os_event',
				'meta_input' => array(
					'onlinesched_time_hr' => $hour,
					'onlinesched_time_min' => $minutes,
					'onlinesched_year' => $onlinesched_year,
					'onlinesched_sorttime' => intval($mysql_time),
					'onlinesched_timelen' => $length,
					'onlinesched_external_event_id' => $external_event_id,
				)
			);

			if ($event_id) {
				$post_data['ID'] = $event_id;
			}

			$room_type_id = null;
			if (!empty($room_type)) {
				$room_type_slug = online_create_custom_slug($room_type);
				if (empty($room_cache[$room_type_slug]['term_id'])) {
					$room_type_term = wp_insert_term($room_type, 'os_room', array('slug' => $room_type_slug));

					if (is_wp_error($room_type_term)) {
						echo "<div class=\"upload-error\"><p>couldn't create a room fatal error {$room_type}</p></div>";
						return;
					}

					$room_cache[$room_type_slug] = array(
						'term_id' => $room_type_term['term_id']

					);

				}
				$room_type_id = array($room_cache[$room_type_slug]['term_id']);
			}

			// Handle room_type taxonomy
//             $room_type_term = term_exists($room_type, 'os_room');
			//          if (!$room_type_term) {
			// print "Creating room";
			//            $room_type_term = wp_insert_term($room_type, 'os_room');
			//       }

			// Ok if this person has the privlidges should be able to do
			// wp_set_post_terms($event_id, $room_type, 'os_room');
			$post_data['tax_input']['os_room'] = $room_type_id;

			// Handle os_day taxonomy
			$day_term_id = null;

			$day_of_week_slug = strtolower($day_of_week);

			if (!empty($day_tag_cache[$day_of_week_slug])) {
				if ($day_tag_cache[$day_of_week_slug]['description'] === $formatted_date) {
					$day_term_id = $day_tag_cache[$day_of_week_slug]['term_id'];
				} else {
					$date = DateTime::createFromFormat('n/j/Y', trim($day_tag_cache[$day_of_week_slug]['description']));
					$year = $date->format('Y');
					$new_name = $day_tag_cache[$day_of_week_slug]['name'] . '-' . $year;
					$new_slug = strtolower(sanitize_title($new_name));
					wp_update_term($day_tag_cache[$day_of_week_slug]['term_id'], 'os_day', array(
						'name' => $new_name,
						'slug' => $new_slug
					));

					// update ecache since that would take time otherwise
					$day_tag_cache[$new_slug] = $day_tag_cache[$day_of_week_slug];
					$day_tag_cache[$new_slug]['name'] = $new_name;
				}
			}


			if (!$day_term_id) {
				$day_term = wp_insert_term($day_of_week, 'os_day', array(
					'description' => $formatted_date,
					'slug' => $day_of_week_slug,
				));
				if (is_wp_error($day_term)) {
					echo "<div class=\"upload-error\"><p>fatal error creating day type $day_of_week slug $day_of_week_slug</p></div>";
					return;
				}
				$day_tag_cache[$day_of_week_slug]['term_id'] = $day_term['term_id'];
				$day_tag_cache[$day_of_week_slug]['slug'] = $day_of_week;
				$day_tag_cache[$day_of_week_slug]['name'] = $day_of_week;
				$day_tag_cache[$day_of_week_slug]['description'] = $formatted_date;
				$day_term_id = $day_term['term_id'];

			}

			$post_data['tax_input']['os_day'] = array($day_term_id);

			/*
			$day_terms = get_terms(array(
				'taxonomy' => 'os_day',
				'name' => $day_of_week,
				'hide_empty' => false,
			));
			$day_term_id = null;
			foreach ($day_terms as $term) {
				print "I am comparing $formatted_date ".date("Y-m-d h:i:sa")."<br />";
				if ($term->description === $formatted_date) {
					$day_term_id = $term->term_id;
					break;
				} else {
					$date = DateTime::createFromFormat('n/j/Y', $term->description);
					$year = $date->format('Y');
					$new_name = $term->name . '-' . $year;
					$new_slug = sanitize_title($new_name);
					wp_update_term($term->term_id, $term->taxonomy, array(
						'name' => $new_name,
						'slug' => $new_slug
					));
				}

			}

			if (!$day_term_id) {
				$day_term = wp_insert_term($day_of_week, 'os_day', array(
					'description' => $formatted_date,
				));
				if (!is_wp_error($day_term)) {
					$day_term_id = $day_term['term_id'];
				}
			}

			if ($day_term_id) {
				$post_data['tax_input']['os_day']  = array($day_term_id);
				// wp_set_post_terms($event_id, array($day_term_id), 'os_day');
			} else {
				$post_data['tax_input']['os_day']  = array($unscheduled_term['term_id']);
				// wp_set_post_terms($event_id, array($unscheduled_term['term_id']), 'os_day');
			}
			*/


			// Handle speakers taxonomy
			$speakers_IDs = array();
			$speakers_list = array_map('trim', explode(',', $speakers));
			foreach ($speakers_list as $speaker) {
				if (!empty($speaker)) {
					$normalized_speaker = strtolower(trim($speaker));
					if (empty($normalized_panelist_cache[$normalized_speaker])) {
						$panelist_term = wp_insert_term($speaker, 'os_panelist');
						if (is_wp_error($panelist_term)) {
                            echo "<div class=\"upload-error\"><p>fatal error on panelist update boom! Speaker $speaker - " . $panelist_term->get_error_message() . "</p></div>";
							return;
						}
						$normalized_panelist_cache[$normalized_speaker] = array(
							'term_id' => $panelist_term['term_id'],
							'name' => $speaker,
							'slug' => $speaker,
						);
					}
					$speakers_IDs[] = $normalized_panelist_cache[$normalized_speaker]['term_id'];
				}

			}
			$post_data['tax_input']['os_panelist'] = $speakers_IDs;
			// wp_set_post_terms($event_id, $speakers_IDs, 'os_panelist');


			// Handle tags taxonomy
			$tags_terms = array();
			$tags_list = explode(',', $tags);
			foreach ($tags_list as $tag) {
				$tag = trim($tag);
				if (!empty($tag)) {
					$tag = ucwords($tag);
					$tag_slug = online_create_custom_slug($tag);

					if (empty($tag_cache[$tag_slug])) {
						$tags_term = wp_insert_term($tag, 'os_tag',
							array('slug' => $tag_slug));

						if (is_wp_error($tags_term)) {
							echo "<div class=\"upload-error\"><p>fatal error creating term $tag and $tag_slug</p></div>";
							wp_die();
						}
						$tag_cache[$tag_slug] = array(
							'term_id' => $tags_term['term_id'],
							'slug' => $tag_slug,
							'name' => $tag,
						);
					}

					$tags_terms[] = (int)$tag_cache[$tag_slug]['term_id'];
				}
			}

			$post_data['tax_input']['os_tag'] = $tags_terms;
			// wp_set_post_terms($event_id, $tags_terms, 'os_tag');


			// write out room woot!
			$post_id = wp_insert_post($post_data);
            if (is_wp_error($post_id) || $post_id == 0) {
                if (!empty($post_id)) {
                    $error_message = $post_id->get_error_message();
                } else {
                    $error_message = "no error message provided";
                }
                echo "<div class=\"upload-error\"><p>fatal error creating event in row {$row} (input line $input_line) - {$error_message}</p></div>";
            }


			// DO it seperate to get around some behavior that is setitng it to -99 in the db
//            update_post_meta($event_id, 'onlinesched_sorttime', $mysql_time);

//            echo "<pre>"; var_dump($post_data); echo "</pre>";

// if ($name === "Salsa Dancing 101") {wp_die("end of line"); die();}


//            echo '<pre>';
//            var_dump(array($external_event_id, $name, $date, $time, $description, $room_type, $speakers, $length, $tags));
//            var_dump($day_of_week, $formatted_date, $hour, $minutes, $mysql_time);
			//           print "date object<br />";
			//          var_dump($full_date);
			//         echo "</pre>";
			//         wp_die();

		}
		fclose($handle);


		$end_time = microtime(true);
		$execution_time = $end_time - $start_time;

		echo '<div class="schedule-updated"><p>CSV file processed successfully taking ' . intval($execution_time) . ' seconds.</p></div>';
	} else {
		echo '<div class="upload-error"><p>Failed to open the uploaded CSV file.</p></div>';
	}


	$wpdb->query('COMMIT;');
	// Re-enable post revisions

	wp_defer_term_counting(false);

	// $pauser->resume();
	if (function_exists('w3tc_flush_all')) {
		w3tc_flush_all();
	}


}

function export_os_event_csv()
{

	// Kill the hidden fields
	remove_filter('parse_query', 'OnlineSched_posts_filter');


	$filename = 'os_event_export_' . date('Ymd') . '.csv';

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment;filename=' . $filename);

	$output = fopen('php://output', 'w');
	fputcsv($output, array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags'));

	$args = array(
		'post_type' => 'os_event',
		'posts_per_page' => -1,
	);
	$events = get_posts($args);

	foreach ($events as $event) {
		$post_id = $event->ID;

		// Check if the EventId meta field is missing or empty
		$event_id = get_post_meta($post_id, 'onlinesched_external_event_id', true);

		if (empty($event_id)) {
			// Generate a unique EventId
			$event_id = generate_unique_event_id();

			// Save the EventId as post meta
			update_post_meta($post_id, 'onlinesched_external_event_id', $event_id);
		}


		$name = $event->post_title;
		$description = $event->post_content;
		$sorttime = get_post_meta($post_id, 'onlinesched_sorttime', true);
		// Convert timestamp to Excel-compatible format
		if ($sorttime) {
			// use default system timezone stuff
			$date = date('Y-m-d', $sorttime);
			$time = date('H:i', $sorttime);
		} else {
			$date = '';
			$time = '';
		}


		$room_type = wp_get_post_terms($post_id, 'os_room', array('fields' => 'names'));
		$speakers = wp_get_post_terms($post_id, 'os_panelist', array('fields' => 'names'));
		$tags = wp_get_post_terms($post_id, 'os_tag', array('fields' => 'names'));

		$room_type = !empty($room_type) ? implode(', ', $room_type) : '';
		$speakers = !empty($speakers) ? implode(', ', $speakers) : '';
		$length = get_post_meta($post_id, 'onlinesched_timelen', true);
		$tags = !empty($tags) ? implode(', ', $tags) : '';

		fputcsv($output, array($event_id, $name, $date, $time, $description, $room_type, $speakers, $length, $tags));
	}

	fclose($output);
	exit();
}

function online_create_custom_slug($text)
{
	// Convert special characters to their textual representations
	$replace_pairs = array(
		'$' => 'dollar',
		'#' => 'hash',
		'&amp' => 'and',
		'&' => 'and',
		'%' => 'percent',
		'^' => 'caret',
		'@' => 'at',
		'(' => 'left-parenthesis',
		')' => 'right-parenthesis',
		'*' => 'asterisk',
		'!' => 'exclamation'
	);

	// Replace the special characters
	$text = strtr($text, $replace_pairs);

	// Replace spaces with hyphens
	$text = preg_replace('/\s+/', '-', $text);

	// Remove any characters that are not alphanumeric, hyphens, or underscores
	$text = preg_replace('/[^a-zA-Z0-9-_]/', '', $text);

	// Convert to lowercase
	$text = strtolower($text);

	// Trim any leftover hyphens
	$text = trim($text, '-');

	return $text;
}


function generate_unique_event_id()
{
	$min = 1000; // Minimum value for EventId
	$max = 999999; // Maximum value for EventId

	do {
		// Generate a random number within the specified range
		$unique_id = rand($min, $max);

		// Check if the generated ID is already used
		$existing_posts = new WP_Query(array(
			'post_type' => 'os_event',
			'meta_query' => array(
				array(
					'key' => 'onlinesched_external_event_id',
					'value' => $unique_id,
					'compare' => '='
				)
			),
			'posts_per_page' => 1,
			'fields' => 'ids'
		));
	} while ($existing_posts->have_posts());

	return $unique_id;
}

// Fast way to get event id
function get_post_id_by_event_id($external_event_id)
{
	if (empty($external_event_id)) {
		return false;
	}

	$args = array(
		'post_type' => 'os_event',
		'meta_query' => array(
			array(
				'key' => 'onlinesched_external_event_id',
				'value' => $external_event_id,
				'compare' => '='
			)
		),
		'posts_per_page' => 1, // We only need one post since onlinesched_external_event_id should be unique
		'fields' => 'ids' // Only retrieve the post ID
	);

	$matching_posts = get_posts($args);

	if (!empty($matching_posts)) {
		return $matching_posts[0]; // Return the post ID of the matching post
	} else {
		return false; // Return false if no post is found
	}
}

function online_sched_grab_all_tags($tax, $by = 'slug')
{
	$tags_by_array = array();
	$tags = get_terms([
		'taxonomy' => $tax,
		'hide_empty' => false,
	]);

	foreach ($tags as $tag) {
		$build_array = array(
			'name' => $tag->name,
			'term_id' => $tag->term_id,
			'description' => $tag->description,
			'slug' => $tag->slug,
		);
		$tags_by_array[$build_array[$by]] = $build_array;
	}

	return $tags_by_array;
}


function schedule_convert_to_utf8_and_kses($input) {
	return wp_kses_post(schedule_convert_to_utf8($input));
}

function schedule_convert_to_utf8_and_santize($input) {

    return sanitize_text_field(schedule_convert_to_utf8($input));
}

function schedule_convert_to_utf8($input) {

	$replace = array(

		// Smart quotes (UTF-8 and Windows-1252 encoding) to HTML entities
		"\xE2\x80\x98" => '&lsquo;', // ‘ (left single quotation mark)
		"\xE2\x80\x99" => '&rsquo;', // ’ (right single quotation mark)
		"\xE2\x80\x9C" => '&ldquo;', // “ (left double quotation mark)
		"\xE2\x80\x9D" => '&rdquo;', // ” (right double quotation mark)
		chr(145) => '&lsquo;',       // ‘ (left single quotation mark, Windows-1252)
		chr(146) => '&rsquo;',       // ’ (right single quotation mark, Windows-1252)
		chr(147) => '&ldquo;',       // “ (left double quotation mark, Windows-1252)
		chr(148) => '&rdquo;',       // ” (right double quotation mark, Windows-1252)
		// En dash and em dash to HTML entities
		"\xE2\x80\x93" => '&ndash;', // – (en dash)
		"\xE2\x80\x94" => '&mdash;', // — (em dash)
		chr(150) => '&ndash;',       // – (en dash, Windows-1252)
		chr(151) => '&mdash;',       // — (em dash, Windows-1252)
		// Ellipsis to HTML entity
		"\xE2\x80\xA6" => '&hellip;', // … (ellipsis)
		chr(133) => '&hellip;',      // … (ellipsis, Windows-1252)
		// Dagger to HTML entity
		chr(134) => '&dagger;',      // † (dagger symbol, Windows-1252)
		// Double dagger to HTML entity
		chr(135) => '&Dagger;',      // ‡ (double dagger symbol, Windows-1252)
		// Non-breaking space to HTML entity
		"\xC2\xA0" => '&nbsp;',       //   (non-breaking space, UTF-8)
		chr(160) => '&nbsp;',         //   (non-breaking space, Windows-1252)
		// Trademark to HTML entity
		chr(153) => '&trade;',       // ™ (trademark, Windows-1252)
		// Euro sign to HTML entity
		chr(128) => '&euro;',        // € (euro sign, Windows-1252)
		// Bullet to HTML entity
		chr(149) => '&bull;',        // • (bullet, Windows-1252)
	);

	$input =  str_replace(array_keys($replace), array_values($replace), $input);


	// Detect the character encoding
	$encoding = mb_detect_encoding($input, mb_detect_order(), true);

    if ($encoding == 'ASCII' || !$encoding) {
        // assume windows
	    $input = mb_convert_encoding($input, 'UTF-8', 'Windows-1252');
    }
	// If it's not UTF-8, convert it
	if ($encoding !== 'UTF-8') {
		 $input = mb_convert_encoding($input, 'UTF-8', $encoding);
	}

	// Replace any invalid UTF-8 characters with HTML entities
	// $input = mb_convert_encoding($input, 'HTML-ENTITIES', 'UTF-8');

	// Now return string so it can get santized the last bits.
	return $input;
}

function scan_for_non_ascii_characters($text) {
	$non_ascii_characters = [];

	// Loop through each character in the string
	for ($i = 0; $i < strlen($text); $i++) {
		$char = $text[$i];

		// Check if the character is non-ASCII
		if (ord($char) > 127) {
			$hex_value = bin2hex($char);
			$non_ascii_characters[] = [
				'character' => $char,
				'hex' => $hex_value,
				'ord' => ord($char),
			];
		}
	}

	return $non_ascii_characters;
}