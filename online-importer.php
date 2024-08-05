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
        'taxonomy' => 'onlinesched_panelists',
        'hide_empty' => false,
    ));

    foreach ($terms as $term) {
        $term_count = $term->count;
        if ($term_count == 0) {
            wp_delete_term($term->term_id, 'onlinesched_panelists');
        }
    }

    echo '<div class="updated"><p>All unused Panelists have been deleted.</p></div>';
}


function handle_event_schedule_csv_upload($file) {
    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ',');

        $required_headers = array('name', 'DateTime', 'description', 'room_type', 'Speakers', 'tags');
        if (array_slice($headers, 0, count($required_headers)) !== $required_headers) {
            echo '<div class="error"><p>CSV file format is incorrect. Expected headers: name, DateTime, description, room_type, Speakers, tags.</p></div>';
            return;
        }

        $unscheduled_term = term_exists('Unscheduled', 'event_schedule_day_type');
        if (!$unscheduled_term) {
            $unscheduled_term = wp_insert_term('Unscheduled', 'event_schedule_day_type', array('description' => '0'));
        }

        $row = 1;
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $row++;
            if (count($data) < count($required_headers)) {
                echo "<div class='error'><p>Row $row has a mismatched number of fields.</p></div>";
                continue;
            }

            $name = sanitize_text_field($data[0]);
            $datetime = sanitize_text_field($data[1]);
            $description = sanitize_text_field($data[2]);
            $room_type = sanitize_text_field($data[3]);
            $speakers = sanitize_text_field($data[4]);
            $tags = sanitize_text_field($data[5]);

            if (empty($datetime)) {
                $day_of_week = 'Unscheduled';
                $formatted_date = '0';
                $hour = 0;
                $minutes = 0;
                $mysql_time = '00:00';
            } else {
                $date = DateTime::createFromFormat('j/m/Y H:i:s', $datetime);
                if (!$date) {
                    echo "<div class='error'><p>Row $row has an invalid DateTime format. Expected format: j/m/Y H:i:s.</p></div>";
                    continue;
                }

                $day_of_week = $date->format('l');
                $formatted_date = $date->format('n/j/Y');
                $hour = $date->format('H');
                $minutes = $date->format('i');
                $mysql_time = $date->format('H:i');
            }

            // Check for existing event
            $existing_event = get_page_by_title($name, OBJECT, 'event_schedule');
            if ($existing_event) {
                wp_update_post(array(
                    'ID' => $existing_event->ID,
                    'post_content' => $description
                ));
                $event_id = $existing_event->ID;
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
            update_post_meta($event_id, 'datetime', $datetime);
            update_post_meta($event_id, 'onlinesched_time_hr', $hour);
            update_post_meta($event_id, 'onlinesched_time_min', $minutes);
            update_post_meta($event_id, 'onlinesched_year', get_option('event_schedule_year'));
            update_post_meta($event_id, 'onlinesched_sorttime', $mysql_time);

            // Remove previous custom taxonomies
            wp_set_post_terms($event_id, array(), 'event_schedule_room_type');
            wp_set_post_terms($event_id, array(), 'event_schedule_day_type');
            wp_set_post_terms($event_id, array(), 'onlinesched_panelists');
            wp_set_post_terms($event_id, array(), 'event_schedule_tags_type');

            // Handle room_type taxonomy
            $room_type_term = term_exists($room_type, 'event_schedule_room_type');
            if (!$room_type_term) {
                $room_type_term = wp_insert_term($room_type, 'event_schedule_room_type');
            }

            if (!is_wp_error($room_type_term)) {
                wp_set_post_terms($event_id, array($room_type_term['term_id']), 'event_schedule_room_type');
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
            $panelist_terms = array();
            $speakers_list = explode(',', $speakers);
            foreach ($speakers_list as $speaker) {
                $speaker = trim($speaker);
                $panelist_term = term_exists($speaker, 'onlinesched_panelists');
                if (!$panelist_term) {
                    $panelist_term = wp_insert_term($speaker, 'onlinesched_panelists');
                }
                if (!is_wp_error($panelist_term)) {
                    $panelist_terms[] = (int) $panelist_term['term_id'];
                }
            }
            wp_set_post_terms($event_id, $panelist_terms, 'onlinesched_panelists');

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
        }
        fclose($handle);
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
    fputcsv($output, array('name', 'Date', 'Time', 'description', 'room_type', 'Speakers', 'length', 'tags'));

    $args = array(
        'post_type' => 'event_schedule',
        'posts_per_page' => -1,
    );
    $events = get_posts($args);

    foreach ($events as $event) {
        $name = $event->post_title;
        $description = $event->post_content;
        $sorttime = get_post_meta($event->ID, 'onlinesched_sorttime', true);
        // Convert timestamp to Excel-compatible format
        if ($sorttime) {
            $datetime = get_date_from_gmt(date('Y-m-d H:i:s', $sorttime), 'Y-m-d H:i:s');
            $date =  get_date_from_gmt(date('Y-m-d', $sorttime), 'Y-m-d H:i:s');
            $time = get_date_from_gmt(date('H:i:s', $sorttime), 'Y-m-d H:i:s');
        } else {
            $datetime = '';
            $date = '';
            $time = '';
        }



        $room_type = wp_get_post_terms($event->ID, 'event_schedule_room_type', array('fields' => 'names'));
        $$speakers = wp_get_post_terms($event->ID, 'event_schedule_panelist_type', array('fields' => 'names'));
        $tags = wp_get_post_terms($event->ID, 'event_schedule_tags_type', array('fields' => 'names'));

        $room_type = !empty($room_type) ? implode(', ', $room_type) : '';
        $speakers = !empty($speakers) ? implode(', ', $speakers) : '';
        $length = get_post_meta($event->ID, 'onlinesched_timelen', true);
        $tags = !empty($tags) ? implode(', ', $tags) : '';

        fputcsv($output, array($name, $date, $time, $description, $room_type, $speakers, $length, $tags));
    }

    fclose($output);
    exit();
}

