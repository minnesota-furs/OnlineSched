<?php

function event_schedule_csv_uploader_menu() {
    add_submenu_page(
        'edit.php?post_type=event_schedule',  // Parent slug
        'CSV Uploader',                       // Page title
        'CSV Uploader',                       // Menu title
        'manage_options',                     // Capability
        'event-schedule-csv-uploader',        // Menu slug
        'event_schedule_csv_uploader_page'    // Function to display the page
    );

    // Handle export before any page output
    add_action('load-event_schedule_page_event-schedule-csv-uploader', 'event_schedule_csv_export_handler');

}
add_action('admin_menu', 'event_schedule_csv_uploader_menu');

function event_schedule_csv_export_handler() {
    if (isset($_POST['export_csv'])) {
        export_event_schedule_csv();
        exit(); // Kit is after
    }
}


function event_schedule_csv_uploader_page() {
    ?>
    <div class="wrap">
        <h2>Upload Event Schedule CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="event_schedule_csv" accept=".csv" required>
            <?php submit_button('Upload CSV'); ?>
            <br />
            <p><strong>Note:</strong> All imports will be associated with <?php echo get_option('event_schedule_year');?> year. Change in settings->Online scheduler.
        </form>

        <hr />
        <h2>Export Current Schedule CSV</h2>
        <form method="post">
            <?php submit_button('Export CSV', 'primary', 'export_csv'); ?>
        </form>

        <form method="post">
            <?php submit_button('Delete All Event Schedule Posts', 'delete', 'delete_all_event_schedule_posts'); ?>
        </form>
        <form method="post">
            <?php submit_button('Delete Unused Panelists', 'delete', 'delete_unused_panelists'); ?>
        </form>
    </div>
    <?php
    if (isset($_FILES['event_schedule_csv'])) {
        handle_event_schedule_csv_upload($_FILES['event_schedule_csv']);
    }


    if (isset($_POST['delete_all_event_schedule_posts'])) {
        delete_all_event_schedule_posts();
    }

    if (isset($_POST['delete_unused_panelists'])) {
        delete_unused_panelists();
    }
}

function delete_all_event_schedule_posts() {
    $args = array(
        'post_type' => 'event_schedule',
        'posts_per_page' => -1,
        'post_status' => 'any'
    );
    $events = get_posts($args);

    foreach ($events as $event) {
        wp_delete_post($event->ID, true);
    }

    echo '<div class="updated"><p>All Event Schedule posts have been deleted.</p></div>';
}

function delete_unused_panelists() {
    $terms = get_terms(array(
        'taxonomy' => 'event_schedule_panelist_type',
        'hide_empty' => false,
    ));

    foreach ($terms as $term) {
        $term_count = $term->count;
        if ($term_count == 0) {
            wp_delete_term($term->term_id, 'event_schedule_panelist_type');
        }
    }

    echo '<div class="updated"><p>All unused Panelists have been deleted.</p></div>';
}


function handle_event_schedule_csv_upload($file) {
    set_time_limit(600); // 5 minutes
    ini_set('memory_limit', '4096M');

    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        $headers = fgetcsv($handle, 4000, ',');

//        $required_headers = array('name', 'DateTime', 'description', 'room_type', 'Speakers', 'tags');
        $required_headers = array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags');

        if (array_slice($headers, 0, count($required_headers)) !== $required_headers) {
            echo '<div class="error"><p>CSV file format is incorrect. Expected headers: name, DateTime, description, room_type, Speakers, tags.</p></div>';
            return;
        }

        $unscheduled_term = term_exists('Unscheduled', 'event_schedule_day_type');
        if (!$unscheduled_term) {
            $unscheduled_term = wp_insert_term('Unscheduled', 'event_schedule_day_type', array('description' => '0'));
        }

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
            if (count($data) < count($required_headers)) {
                echo "<div class='error'><p>Row $row has a mismatched number of fields.</p></div>";
                continue;
            }

            $external_event_id = sanitize_text_field($data[0]);
            $name = sanitize_text_field($data[1]);
            $date = sanitize_text_field($data[2]);
            $time = sanitize_text_field($data[3]);
            $description = sanitize_text_field($data[4]);
            $room_type = sanitize_text_field($data[5]);
            $speakers = sanitize_text_field($data[6]);
            $length = sanitize_text_field($data[7]);
            $tags = sanitize_text_field($data[8]);

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
                    echo "<div class='error'><p>Row $row has an invalid DateTime format. Expected format: Y-m-d H:i:s. {$date} {$time}</p></div>";
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
            if ($event_id) {
                wp_update_post(array(
                    'ID' => $event_id,
                    'title' => $name,
                    'post_content' => $description,
                    'post_status' => 'publish',
                ));
            } else {
                $new_event = array(
                    'post_title' => $name,
                    'post_content' => $description,
                    'post_status' => 'publish',
                    'post_type' => 'event_schedule',
                );
                $event_id = wp_insert_post($new_event);
            }

            // Update custom post meta
            update_post_meta($event_id, 'onlinesched_time_hr', $hour);
            update_post_meta($event_id, 'onlinesched_time_min', $minutes);
            update_post_meta($event_id, 'onlinesched_year', get_option('event_schedule_year'));
            update_post_meta($event_id, 'onlinesched_sorttime', $mysql_time);
            update_post_meta($event_id, 'onlinesched_timelen', $length);

            // Remove previous custom taxonomies
            // terms are reset automatically by the item.
            // wp_set_post_terms($event_id, array(), 'event_schedule_room_type');
            // wp_set_post_terms($event_id, array(), 'event_schedule_day_type');
            // wp_set_post_terms($event_id, array(), 'onlinesched_panelists');
            // wp_set_post_terms($event_id, array(), 'event_schedule_tags_type');
                
            //  Save external event ID
            update_post_meta($event_id, 'onlinesched_external_event_id', $external_event_id);;

            // Handle room_type taxonomy
            $room_type_term = term_exists($room_type, 'event_schedule_room_type');
            if (!$room_type_term) {
                print "Creating room";
                $room_type_term = wp_insert_term($room_type, 'event_schedule_room_type');
            }

            if (!is_wp_error($room_type_term)) {
                wp_set_post_terms($event_id, $room_type, 'event_schedule_room_type');
            }

            // Handle event_schedule_day_type taxonomy
            $day_terms = get_terms(array(
                'taxonomy' => 'event_schedule_day_type',
                'name' => $day_of_week,
                'hide_empty' => false,
            ));

            $day_term_id = null;
            foreach ($day_terms as $term) {
                if ($term->description === $formatted_date) {
                    $day_term_id = $term->term_id;
                    break;
                }
            }

            if (!$day_term_id) {
                $day_term = wp_insert_term($day_of_week, 'event_schedule_day_type', array(
                    'description' => $formatted_date
                ));
                if (!is_wp_error($day_term)) {
                    $day_term_id = $day_term['term_id'];
                }
            }

            if ($day_term_id) {
                wp_set_post_terms($event_id, array($day_term_id), 'event_schedule_day_type');
            } else {
                wp_set_post_terms($event_id, array($unscheduled_term['term_id']), 'event_schedule_day_type');
            }

            // Handle speakers taxonomy
            $speakers_IDs = array();
            $speakers_list =  array_map('trim', explode(',', $speakers));
            foreach ($speakers_list as $speaker) {
                $panelist_term = term_exists($speaker, 'event_schedule_panelist_type');
                if (!$panelist_term) {
                    $panelist_term = wp_insert_term($speaker, 'event_schedule_panelist_type');
                }

                if (!is_wp_error($panelist_term)) {
                    $speakers_IDs[] = (int) $panelist_term['term_id'];
                }
            }
            wp_set_post_terms($event_id, $speakers_IDs, 'event_schedule_panelist_type');

            // Handle tags taxonomy
            $tags_terms = array();
            $tags_list = explode(',', $tags);
            foreach ($tags_list as $tag) {
                $tag = trim($tag);
                $tags_term = term_exists($tag, 'event_schedule_tags_type');
                if (!$tags_term) {
                    $tags_term = wp_insert_term($tag, 'event_schedule_tags_type');
                }
                if (!is_wp_error($tags_term)) {
                    $tags_terms[] = (int) $tags_term['term_id'];
                }
            }
            wp_set_post_terms($event_id, $tags_terms, 'event_schedule_tags_type');


            /*echo '<pre>';
            var_dump(array($external_event_id, $name, $date, $time, $description, $room_type, $speakers, $length, $tags));
            var_dump($day_of_week, $formatted_date, $hour, $minutes, $mysql_time);
            print "date object<br />";
            var_dump($full_date);
            echo "</pre>";
            wp_die();*/

        }
        fclose($handle);

        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        echo '<div class="updated"><p>CSV file processed successfully.</p></div>';
    } else {
        echo '<div class="error"><p>Failed to open the uploaded CSV file.</p></div>';
    }
}

function export_event_schedule_csv() {
    $filename = 'event_schedule_export_' . date('Ymd') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=' . $filename);

    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'Name', 'Date', 'Time', 'Description', 'Room_Type', 'Speakers', 'Length', 'Tags'));

    $args = array(
        'post_type' => 'event_schedule',
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
            $date =  date( 'Y-m-d ', $sorttime);
            $time = date('H:i', $sorttime);
        } else {
            $date = '';
            $time = '';
        }



        $room_type = wp_get_post_terms($post_id, 'event_schedule_room_type', array('fields' => 'names'));
        $speakers = wp_get_post_terms($post_id, 'event_schedule_panelist_type', array('fields' => 'names'));
        $tags = wp_get_post_terms($post_id, 'event_schedule_tags_type', array('fields' => 'names'));

        $room_type = !empty($room_type) ? implode(', ', $room_type) : '';
        $speakers = !empty($speakers) ? implode(', ', $speakers) : '';
        $length = get_post_meta($post_id, 'onlinesched_timelen', true);
        $tags = !empty($tags) ? implode(', ', $tags) : '';

        fputcsv($output, array($event_id, $name, $date, $time, $description, $room_type, $speakers, $length, $tags));
    }

    fclose($output);
    exit();
}


function generate_unique_event_id() {
    $min = 1000; // Minimum value for EventId
    $max = 999999; // Maximum value for EventId

    do {
        // Generate a random number within the specified range
        $unique_id = rand($min, $max);

        // Check if the generated ID is already used
        $existing_posts = new WP_Query(array(
            'post_type' => 'event_schedule',
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
function get_post_id_by_event_id($external_event_id) {
    $args = array(
        'post_type' => 'event_schedule',
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

