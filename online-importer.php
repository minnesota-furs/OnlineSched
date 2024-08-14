<?php

include ("lib/YoastPauser.php");

function event_schedule_csv_uploader_menu() {
    add_submenu_page(
        'edit.php?post_type=event_schedule',  // Parent slug
        'CSV Uploader',                       // Page title
        'CSV Uploader',                       // Menu title
        'manage_event_schedule_room_type',                     // Capability
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
    remove_filter( 'parse_query', 'OnlineSched_posts_filter');
    ?>
    <div class="wrap">
        <h2>Upload Event Schedule CSV</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="event_schedule_csv" accept=".csv" required>
            <?php submit_button('Upload CSV'); ?>
            <br />
            <p><strong>Note:</strong> All imports will be associated with <?php echo get_option('event_schedule_year');?> year. Change in settings->Online scheduler.<br />
                <strong>Note:</strong> If item doesn't exist in the upload set it will still stay there.
            </p>
        </form>

        <hr />
        <h2>Export Current Schedule CSV</h2>
        <form method="post">
            <?php submit_button('Export CSV', 'primary', 'export_csv'); ?>
        </form>

        <hr />
        <br />
        <h2>Clean Up Tasks</h2>
        <form method="post">
            <?php submit_button('Delete All Event Schedule Posts', 'delete', 'delete_all_event_schedule_posts'); ?>
            <br />This will delete <strong>ALL YEARS</strong> posts even hidden ones.
        </form>
        <form method="post">
            <?php submit_button('Delete Unused Panelists', 'delete', 'delete_unused_panelists'); ?>
        </form>

        <form method="post">
            <?php submit_button('Delete Unused Days', 'delete', 'delete_unused_days'); ?>
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
        delete_unused_tax('event_schedule_panelist_type', 'Panalists');
    }

    if (isset($_POST['delete_unused_days'])) {
        delete_unused_tax('event_schedule_day_type', "Days");
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

function delete_unused_tax($taxonomy, $name) {
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

    echo "<div class=\"updated\"><p>All unused {$name} have been deleted.</p></div>";
}


function handle_event_schedule_csv_upload($file) {

    $start_time = microtime(true);

    // prevent writing to innodb until it needs to
    global $wpdb;
    $wpdb->query('START TRANSACTION');

    set_time_limit(1200); // 10 minutes
    ini_set('memory_limit', '4096M');

    //$pauser = new YoastPauser();
    //$pauser->pause();

    $event_schedule_year = get_option('event_schedule_year');

    if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
        wp_defer_term_counting(true);

        $room_cache = online_sched_grab_all_tags('event_schedule_room_type');
        $panelist_cache = online_sched_grab_all_tags('event_schedule_panelist_type', 'name');
        $tag_cache = online_sched_grab_all_tags('event_schedule_tags_type');

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
            echo '<div class="error"><p>CSV file format is incorrect. Expected headers: ID, Name, Date, Time, Description, Room_Type, Speakers, Length, Tags).</p></div>';
            return;
        }

        $unscheduled_term = term_exists('Unscheduled', 'event_schedule_day_type');
        if (!$unscheduled_term) {
            $unscheduled_term = wp_insert_term('Unscheduled', 'event_schedule_day_type', array('description' => '0'));
        }
        $day_tag_cache = online_sched_grab_all_tags('event_schedule_day_type');

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
            $description = wp_kses_post($data[4]);
            $room_type = sanitize_text_field($data[5]);
            $speakers = sanitize_text_field($data[6]);
            $length = sanitize_text_field($data[7]);
            $tags = sanitize_text_field($data[8]);

            if (empty($name)) {
                $name = trim(wp_kses_post($data[1])); // reduce it a bit just in case
            }

            if (empty($name)){
                echo "<div class='error'><p>Row $row has no name In String In case of filter {$data[1]}, ID {$external_event_id}, description: {$description}.</p></div>";
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
                    if (!empty($full_date)){
                        if (intval($full_date->format('Y')) < 1000) {
                            $full_date = DateTime::createFromFormat('n/j/y H:i:s', "{$date} {$time}");
                        }
                    }
                }

                if (!$full_date) {
                    echo "<div class='error'><p>Row $row has an invalid DateTime format. Expected format: Y-m-d H:i:s or n/j/Y H:i:s. {$date} {$time}</p></div>";
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
                'post_type' => 'event_schedule',
                'meta_input' => array(
                    'onlinesched_time_hr' => $hour,
                    'onlinesched_time_min' => $minutes,
                    'onlinesched_year' => $event_schedule_year,
                    'onlinesched_sorttime' => intval($mysql_time),
                    'onlinesched_timelen' => $length,
                    'onlinesched_external_event_id' => $external_event_id,
                )
            );

            if ($event_id) {
                $post_data['ID'] = $event_id;
            }

            $room_type_id = null;
            if (empty($room_type)) {
                $room_type_slug = online_create_custom_slug($room_type);
                if (empty($room_cache[$room_type_slug]['term_id'])) {
                    $room_type_term = wp_insert_term($room_type, 'event_schedule_room_type', array('slug' => $room_type_slug));

                    if (is_wp_error($room_type_term)) {
                        echo "<div class=\"error\"><p>couldn't create a room fatal error $room_type</p></div>";
                        return;
                    }

                    $room_cache[$room_type_slug] = array(
                        'term_id' => $room_type_term['term_id']

                    );

                }
                $room_type_id = array($room_cache[$room_type_slug]['term_id'])
            }

            // Handle room_type taxonomy
//             $room_type_term = term_exists($room_type, 'event_schedule_room_type');
  //          if (!$room_type_term) {
                // print "Creating room";
    //            $room_type_term = wp_insert_term($room_type, 'event_schedule_room_type');
     //       }

            // Ok if this person has the privlidges should be able to do
                // wp_set_post_terms($event_id, $room_type, 'event_schedule_room_type');
                $post_data['tax_input']['event_schedule_room_type'] = $room_type_id;

            // Handle event_schedule_day_type taxonomy
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
                        wp_update_term($day_tag_cache[$day_of_week_slug]['term_id'], 'event_schedule_day_type', array(
                            'name' => $new_name,
                            'slug' => $new_slug
                        ));

                        // update ecache since that would take time otherwise
                        $day_tag_cache[$new_slug] = $day_tag_cache[$day_of_week_slug];
                        $day_tag_cache[$new_slug]['name'] = $new_name;
                    }
                }


                if (!$day_term_id) {
                    $day_term = wp_insert_term($day_of_week, 'event_schedule_day_type', array(
                        'description' => $formatted_date,
                        'slug' => $day_of_week_slug,
                    ));
                    if (is_wp_error($day_term)) {
                           echo "<div class=\"error\"><p>fatal error creating day type $day_of_week slug $day_of_week_slug</p></div>";
                            return;
                        }
                        $day_tag_cache[$day_of_week_slug]['term_id'] = $day_term['term_id'];
                        $day_tag_cache[$day_of_week_slug]['slug'] = $day_of_week;
                        $day_tag_cache[$day_of_week_slug]['name'] = $day_of_week;
                        $day_tag_cache[$day_of_week_slug]['description'] = $formatted_date;
                        $day_term_id = $day_term['term_id'];

                }

                $post_data['tax_input']['event_schedule_day_type']  = array($day_term_id);

            /*
            $day_terms = get_terms(array(
                'taxonomy' => 'event_schedule_day_type',
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
                $day_term = wp_insert_term($day_of_week, 'event_schedule_day_type', array(
                    'description' => $formatted_date,
                ));
                if (!is_wp_error($day_term)) {
                    $day_term_id = $day_term['term_id'];
                }
            }

            if ($day_term_id) {
                $post_data['tax_input']['event_schedule_day_type']  = array($day_term_id);
                // wp_set_post_terms($event_id, array($day_term_id), 'event_schedule_day_type');
            } else {
                $post_data['tax_input']['event_schedule_day_type']  = array($unscheduled_term['term_id']);
                // wp_set_post_terms($event_id, array($unscheduled_term['term_id']), 'event_schedule_day_type');
            }
            */


            // Handle speakers taxonomy
            $speakers_IDs = array();
            $speakers_list =  array_map('trim', explode(',', $speakers));
            foreach ($speakers_list as $speaker) {
                if (!empty($speaker)) {
                    if (empty($panelist_cache[$speaker])) {
                        $panelist_term = wp_insert_term($speaker, 'event_schedule_panelist_type');
                        if (is_wp_error($panelist_term)) {
                            echo "<div class=\"error\"><p>fatal error on panelist update boom! Speaker $speaker</p></div>";
                            return;
                        }

                        $panelist_cache[$speaker] = array(
                            'term_id' => $panelist_term['term_id'],
                            'name' => $speaker,
                            'slug' => $speaker,
                        );
                    }

                    $speakers_IDs[] = $panelist_cache[$speaker]['term_id'];
                }

            }
            $post_data['tax_input']['event_schedule_panelist_type']  = $speakers_IDs;
            // wp_set_post_terms($event_id, $speakers_IDs, 'event_schedule_panelist_type');




            // Handle tags taxonomy
            $tags_terms = array();
            $tags_list = explode(',', $tags);
            foreach ($tags_list as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tag = ucwords($tag);
                    $tag_slug = online_create_custom_slug($tag);

                    if (empty($tag_cache[$tag_slug])) {
                        $tags_term = wp_insert_term($tag, 'event_schedule_tags_type',
                            array('slug' => $tag_slug));

                        if (is_wp_error($tags_term)) {
                            echo "<div class=\"error\"><p>fatal error creating term $tag and $tag_slug</p></div>";
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

            $post_data['tax_input']['event_schedule_tags_type']  = $tags_terms;
            // wp_set_post_terms($event_id, $tags_terms, 'event_schedule_tags_type');



            // write out room woot!
            $event_id = wp_insert_post($post_data);

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

        echo '<div class="updated"><p>CSV file processed successfully taking '.intval($execution_time).' seconds.</p></div>';
    } else {
        echo '<div class="error"><p>Failed to open the uploaded CSV file.</p></div>';
    }


    // Re-enable post revisions

    wp_defer_term_counting(false);

    // $pauser->resume();
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }

    $wpdb->query('COMMIT;');

}

function export_event_schedule_csv() {

    // Kill the hidden fields
    remove_filter( 'parse_query', 'OnlineSched_posts_filter');


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
            $date = date( 'Y-m-d', $sorttime);
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

function online_create_custom_slug($text) {
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
    if (empty($external_event_id)) {
        return false;
    }

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

function online_sched_grab_all_tags($tax, $by = 'slug') {
    $tags_by_array = array();
    $tags = get_terms([
            'taxonomy' => $tax,
            'hide_empty' => false,
            ]);

    foreach ($tags as $tag) {
        $build_array =  array(
            'name' => $tag->name,
            'term_id' => $tag->term_id,
            'description' => $tag->description,
            'slug' => $tag->slug,
        );
        $tags_by_array[$build_array[$by]] = $build_array;
    }

    return $tags_by_array;
}

