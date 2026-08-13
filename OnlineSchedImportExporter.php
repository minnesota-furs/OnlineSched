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
	if (!current_user_can('manage_os_room')) {
		wp_die(esc_html__('You do not have permission to import event schedules.', 'onlinesched'));
	}

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
	if (!is_array($file) || empty($file['tmp_name'])) {
		return '<div class="upload-error"><p>No uploaded CSV file was provided.</p></div>';
	}

	$result = onlinesched_import_csv(
		(string) $file['tmp_name'],
		array('year' => get_option('onlinesched_year'))
	);
	$html = '';
	foreach ($result['errors'] as $error) {
		$context = '';
		if (!empty($error['line'])) {
			$context .= 'Line ' . (int) $error['line'];
		}
		if ($error['external_id'] !== '') {
			$context .= ($context === '' ? '' : ', ') . 'event ' . esc_html($error['external_id']);
		}
		if ($context !== '') {
			$context .= ': ';
		}
		$html .= '<div class="upload-error"><p>' . $context . esc_html($error['message']) . '</p></div>';
	}

	$processed = (int) $result['inserted'] + (int) $result['updated'];
	if ($processed > 0 || empty($result['errors'])) {
		$html .= sprintf(
			'<div class="schedule-updated"><p><strong>Success:</strong> CSV processed for %s. Inserted <strong>%d</strong>, updated <strong>%d</strong>, skipped <strong>%d</strong>, and failed <strong>%d</strong> events in %d seconds.</p></div>',
			esc_html($result['year']),
			(int) $result['inserted'],
			(int) $result['updated'],
			(int) $result['skipped'],
			(int) $result['failed'],
			(int) $result['duration_seconds']
		);
	}

	return $html;
}

function export_os_event_csv()
{
	$filename = 'os_event_export_' . wp_date('Ymd') . '.csv';

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment;filename=' . $filename);

	$output = fopen('php://output', 'w');
	onlinesched_export_csv_rows($output);
	fclose($output);
	exit();
}

/**
 * Write the export CSV rows to an open stream. Split from the download
 * handler (headers + exit) so the slash-integrity test can capture the exact
 * bytes the exporter produces.
 *
 * @param resource $output Open writable stream.
 */
function onlinesched_export_csv_rows($output)
{
	// Kill the hidden fields
	remove_filter('parse_query', 'OnlineSched_posts_filter');

	// Empty escape matches the importer's fgetcsv; the default corrupts
	// backslash-before-quote round trips.
	fputcsv($output, array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags'), ',', '"', '');

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
			// Reusing the persisted feed UUID keeps an export and reimport round
			// trip producing the same event_uid, so app favorites survive.
			$feed_uuid = trim((string) get_post_meta($post_id, 'onlinesched_event_uid', true));
			$event_id = ('' !== $feed_uuid) ? $feed_uuid : generate_unique_event_id();

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

		fputcsv($output, array($event_id, $name, $date, $time, $description, $room_type, $speakers, $length, $tags), ',', '"', '');
	}
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

function schedule_convert_to_utf8_and_kses($input) {
	return wp_kses_post(schedule_convert_to_utf8($input));
}

function schedule_convert_to_utf8_and_santize($input) {

    return sanitize_text_field(schedule_convert_to_utf8($input));
}

function schedule_convert_to_utf8($input) {
	if (!is_string($input) || '' === $input) {
		return is_string($input) ? $input : '';
	}

	// Normalize before replacing punctuation so UTF-8 bytes remain intact.
	if (!mb_check_encoding($input, 'UTF-8')) {
		$input = mb_convert_encoding($input, 'UTF-8', 'Windows-1252');
	}

	$replace = array(
		"\u{2018}" => '&lsquo;',
		"\u{2019}" => '&rsquo;',
		"\u{201C}" => '&ldquo;',
		"\u{201D}" => '&rdquo;',
		"\u{2013}" => '&ndash;',
		"\u{2014}" => '&mdash;',
		"\u{2026}" => '&hellip;',
		"\u{2020}" => '&dagger;',
		"\u{2021}" => '&Dagger;',
		"\u{00A0}" => '&nbsp;',
		"\u{2122}" => '&trade;',
		"\u{20AC}" => '&euro;',
		"\u{2022}" => '&bull;',
	);

	return strtr($input, $replace);
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
