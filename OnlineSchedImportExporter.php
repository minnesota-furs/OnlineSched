<?php

include("lib/YoastPauser.php");


function os_event_csv_export_handler()
{
	if (isset($_POST['export_csv'])) {
        check_admin_referer('onlinesched_csv_export');
		export_os_event_csv();
		exit(); // Kit is after
	}
}


function os_event_csv_uploader_page()
{
    remove_filter('parse_query', 'OnlineSched_posts_filter');
    
    // Process form actions before rendering page HTML
    $messages = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['os_event_csv']) && !empty($_FILES['os_event_csv']['name'])) {
            check_admin_referer('onlinesched_csv_upload');
            $messages[] = handle_os_event_csv_upload($_FILES['os_event_csv']);
        }
        if (isset($_POST['delete_all_os_event_posts'])) {
            check_admin_referer('onlinesched_csv_delete_posts');
            $messages[] = delete_all_os_event_posts();
        }
        if (isset($_POST['delete_unused_panelists'])) {
            check_admin_referer('onlinesched_csv_delete_panelists');
            $messages[] = delete_unused_tax('os_panelist', 'Panelists');
        }
        if (isset($_POST['delete_unused_days'])) {
            check_admin_referer('onlinesched_csv_delete_days');
            $messages[] = delete_unused_tax('os_day', "Days");
        }
    }
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
        <?php foreach ($messages as $msg) { echo $msg; } ?>
        <h2>Upload Event Schedule CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('onlinesched_csv_upload'); ?>
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
            <?php wp_nonce_field('onlinesched_csv_export'); ?>
			<?php submit_button('Export CSV', 'primary', 'export_csv'); ?>
        </form>

        <hr/>
        <br/>
        <h2>Clean Up Tasks</h2>
        <form method="post">
            <?php wp_nonce_field('onlinesched_csv_delete_posts'); ?>
			<?php submit_button('Delete All Event Schedule Posts', 'delete', 'delete_all_os_event_posts'); ?>
            <br/>This will delete <strong>ALL YEARS</strong> posts even hidden ones.
        </form>
        <form method="post">
            <?php wp_nonce_field('onlinesched_csv_delete_panelists'); ?>
			<?php submit_button('Delete Unused Panelists', 'delete', 'delete_unused_panelists'); ?>
        </form>

        <form method="post">
            <?php wp_nonce_field('onlinesched_csv_delete_days'); ?>
			<?php submit_button('Delete Unused Days', 'delete', 'delete_unused_days'); ?>
        </form>
    </div>
	<?php
}

function delete_all_os_event_posts()
{
	$args = array(
		'post_type' => 'os_event',
		'posts_per_page' => -1,
		'post_status' => 'any'
	);
	$events = get_posts($args);
	$count = count($events);

	foreach ($events as $event) {
		wp_delete_post($event->ID, true);
	}

	return '<div class="schedule-updated"><p>All <strong>' . $count . '</strong> Event Schedule posts have been deleted.</p></div>';
}

function delete_unused_tax($taxonomy, $name)
{
	$terms = get_terms(array(
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
	));

	$count = 0;
	if (!is_wp_error($terms)) {
		foreach ($terms as $term) {
			$term_count = $term->count;
			if ($term_count == 0) {
				wp_delete_term($term->term_id, $taxonomy);
				$count++;
			}
		}
	}

	return "<div class=\"schedule-updated\"><p>All <strong>{$count}</strong> unused {$name} have been deleted.</p></div>";
}


function handle_os_event_csv_upload($file)
{
	$start_time = microtime(true);
	$imported_count = 0;
	$result_message = '';

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
			$wpdb->query('COMMIT;');
			return '<div class="upload-error"><p>CSV file format is incorrect. Expected headers: ID, Name, Date, Time, Description, Room_Type, Speakers, Length, Tags).</p></div>';
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
				$full_date = onlinesched_parse_local_datetime($date, $time);

				if (!$full_date) {
					echo "<div class='upload-error'><p>Row $row (input line $input_line) has an invalid date or time. Expected Y-m-d or n/j/Y with a 12-hour or 24-hour time. {$date} {$time}</p></div>";
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
						if ($room_type_term->get_error_code() === 'term_exists') {
							$room_term_id = $room_type_term->get_error_data('term_exists');
							$room_cache[$room_type_slug] = array('term_id' => $room_term_id);
						} else {
							$result_message .= "<div class=\"upload-error\"><p>couldn't create a room error {$room_type}: " . $room_type_term->get_error_message() . "</p></div>";
						}
					} else {
						$room_cache[$room_type_slug] = array('term_id' => $room_type_term['term_id']);
					}
				}
				if (!empty($room_cache[$room_type_slug]['term_id'])) {
					$room_type_id = array($room_cache[$room_type_slug]['term_id']);
				}
			}

			$post_data['tax_input']['os_room'] = $room_type_id;

			// Handle os_day taxonomy
			$day_term_id = null;
			$day_of_week_slug = strtolower($day_of_week);

			if (!empty($day_tag_cache[$day_of_week_slug])) {
				if ($day_tag_cache[$day_of_week_slug]['description'] === $formatted_date) {
					$day_term_id = $day_tag_cache[$day_of_week_slug]['term_id'];
				} else {
					$date = DateTime::createFromFormat('n/j/Y', trim($day_tag_cache[$day_of_week_slug]['description']));
					$year = $date ? $date->format('Y') : 'old';
					$new_name = $day_tag_cache[$day_of_week_slug]['name'] . '-' . $year;
					$new_slug = strtolower(sanitize_title($new_name));
					wp_update_term($day_tag_cache[$day_of_week_slug]['term_id'], 'os_day', array(
						'name' => $new_name,
						'slug' => $new_slug
					));

					$day_tag_cache[$new_slug] = $day_tag_cache[$day_of_week_slug];
					$day_tag_cache[$new_slug]['name'] = $new_name;
					unset($day_tag_cache[$day_of_week_slug]);
				}
			}

			if (!$day_term_id) {
				$day_term = wp_insert_term($day_of_week, 'os_day', array(
					'description' => $formatted_date,
					'slug' => $day_of_week_slug,
				));
				if (is_wp_error($day_term)) {
					if ($day_term->get_error_code() === 'term_exists') {
						$day_term_id = $day_term->get_error_data('term_exists');
						wp_update_term($day_term_id, 'os_day', array(
							'description' => $formatted_date,
						));
					} else {
						$result_message .= "<div class=\"upload-error\"><p>error creating day type $day_of_week: " . $day_term->get_error_message() . "</p></div>";
						$wpdb->query('COMMIT;');
						return $result_message;
					}
				} else {
					$day_term_id = $day_term['term_id'];
				}
				$day_tag_cache[$day_of_week_slug]['term_id'] = $day_term_id;
				$day_tag_cache[$day_of_week_slug]['slug'] = $day_of_week_slug;
				$day_tag_cache[$day_of_week_slug]['name'] = $day_of_week;
				$day_tag_cache[$day_of_week_slug]['description'] = $formatted_date;
			}

			$post_data['tax_input']['os_day'] = array($day_term_id);

			// Handle speakers taxonomy
			$speakers_IDs = array();
			$speakers_list = array_map('trim', explode(',', $speakers));
			foreach ($speakers_list as $speaker) {
				if (!empty($speaker)) {
					$normalized_speaker = strtolower(trim($speaker));
					if (empty($normalized_panelist_cache[$normalized_speaker])) {
						$panelist_term = wp_insert_term($speaker, 'os_panelist');
						if (is_wp_error($panelist_term)) {
							if ($panelist_term->get_error_code() === 'term_exists') {
								$p_id = $panelist_term->get_error_data('term_exists');
								$normalized_panelist_cache[$normalized_speaker] = array(
									'term_id' => $p_id,
									'name' => $speaker,
									'slug' => $speaker,
								);
							} else {
								$result_message .= "<div class=\"upload-error\"><p>error on panelist Speaker $speaker - " . $panelist_term->get_error_message() . "</p></div>";
							}
						} else {
							$normalized_panelist_cache[$normalized_speaker] = array(
								'term_id' => $panelist_term['term_id'],
								'name' => $speaker,
								'slug' => $speaker,
							);
						}
					}
					if (!empty($normalized_panelist_cache[$normalized_speaker]['term_id'])) {
						$speakers_IDs[] = $normalized_panelist_cache[$normalized_speaker]['term_id'];
					}
				}
			}
			$post_data['tax_input']['os_panelist'] = $speakers_IDs;

			// Handle tags taxonomy
			$tags_terms = array();
			$tags_list = explode(',', $tags);
			foreach ($tags_list as $tag) {
				$tag = trim($tag);
				if (!empty($tag)) {
					$tag = ucwords($tag);
					$tag_slug = online_create_custom_slug($tag);

					if (empty($tag_cache[$tag_slug])) {
						$tags_term = wp_insert_term($tag, 'os_tag', array('slug' => $tag_slug));

						if (is_wp_error($tags_term)) {
							if ($tags_term->get_error_code() === 'term_exists') {
								$t_id = $tags_term->get_error_data('term_exists');
								$tag_cache[$tag_slug] = array(
									'term_id' => $t_id,
									'slug' => $tag_slug,
									'name' => $tag,
								);
							} else {
								$result_message .= "<div class=\"upload-error\"><p>error creating term $tag and $tag_slug: " . $tags_term->get_error_message() . "</p></div>";
							}
						} else {
							$tag_cache[$tag_slug] = array(
								'term_id' => $tags_term['term_id'],
								'slug' => $tag_slug,
								'name' => $tag,
							);
						}
					}

					if (!empty($tag_cache[$tag_slug]['term_id'])) {
						$tags_terms[] = (int)$tag_cache[$tag_slug]['term_id'];
					}
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
                $result_message .= "<div class=\"upload-error\"><p>fatal error creating event in row {$row} (input line $input_line) - {$error_message}</p></div>";
            } else {
                $imported_count++;
            }


			// Keep the legacy row workaround and skip direct sorttime writes here.
//            update_post_meta($event_id, 'onlinesched_sorttime', $mysql_time);

		}
		fclose($handle);


		$end_time = microtime(true);
		$execution_time = $end_time - $start_time;

		$result_message .= '<div class="schedule-updated"><p><strong>Success:</strong> CSV file processed successfully. Imported/Updated <strong>' . $imported_count . '</strong> events in ' . intval($execution_time) . ' seconds.</p></div>';
	} else {
		$result_message .= '<div class="upload-error"><p>Failed to open the uploaded CSV file.</p></div>';
	}


	$wpdb->query('COMMIT;');
	// Re-enable post revisions

	wp_defer_term_counting(false);

	// $pauser->resume();
	if (function_exists('w3tc_flush_all')) {
		w3tc_flush_all();
	}

    return $result_message;
}

function export_os_event_csv()
{

	// Kill the hidden fields
	remove_filter('parse_query', 'OnlineSched_posts_filter');


	$filename = 'os_event_export_' . wp_date('Ymd') . '.csv';

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
			// Export the event's local wall time in the configured site timezone.
			$date = wp_date('Y-m-d', $sorttime);
			$time = wp_date('H:i', $sorttime);
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
