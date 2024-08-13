<?php

/*
 *
// Usage in your importer:
function run_import() {
$yoast_pauser = new YoastPauser();
$yoast_pauser->pause();

// Your import code here
// ...

$yoast_pauser->resume();
$yoast_pauser->rebuild_index();
}
 */
class YoastPauser {
    private $paused = false;

    public function __construct() {
        add_filter('wpseo_enable_notification_post_trash', '__return_false');
        add_filter('wpseo_enable_notification_post_update', '__return_false');
        add_filter('wpseo_enable_notification_post_publish', '__return_false');
        add_filter('wpseo_enable_notification_term_create', '__return_false');
        add_filter('wpseo_enable_notification_term_update', '__return_false');
        add_action('wpseo_reset_transients_after_post_content_change', '__return_false');
        add_filter('wpseo_indexable_save', '__return_false');
    }

    public function pause() {
        if ($this->paused) {
            return;
        }

        // Disable sitemap updates for all post types
        add_filter('wpseo_sitemap_exclude_post_type', '__return_true', 999);

        // Disable indexable creation/updates
        add_filter('wpseo_should_save_indexable', '__return_false', 999);

        // Remove actions safely
        $this->remove_action_safely('transition_post_status', 'WPSEO_Sitemaps_Cache', 'status_transition', 10);
        $this->remove_action_safely('edited_terms', 'WPSEO_Sitemaps_Cache', 'invalidate_taxonomy', 10);
        $this->remove_action_safely('save_post', 'WPSEO_Meta', 'save_postdata');

        // Disable parsing for custom fields
        add_filter('wpseo_parse_opengraph_image', '__return_false');
        add_filter('wpseo_parse_twitter_image', '__return_false');

        $this->paused = true;


        # Disable WP Obj Caching ...
        wp_using_ext_object_cache(false);

        # Disable W3 Constants (not sure whether they are recognized during runtime)
        $cons = array(
            'DONOTCACHEPAGE',
            'DONOTCACHEDB',
            'DONOTMINIFY',
            'DONOTCDN',
            'DONOTCACHEOBJECT',
            'DONOTCACHEPAGE'
        );
        foreach($cons as $c)
        {
            if(!defined($c))
                define($c, true);
        }

        # Add Filter w3tc_dbcache_can_cache_sql
        add_filter('w3tc_dbcache_can_cache_sql', function() { return 'DONT_CACHE_MY_CRONS'; });

        ########## CHANGE CACHE REJECT REASON for DB CACHING...
        // Create the closure by reference
        // https://stackoverflow.com/a/17560595/701049
        $reader = function & ($object, $property) {
            $value = & Closure::bind(function & () use ($property) {
                return $this->$property;
            }, $object, $object)->__invoke();
            return $value;
        };

        global $wpdb;
        try {
            $active_processor = & $reader($wpdb, 'active_processor'); # GET property by reference ... to be able to change it
            $reject_reason = & $reader($active_processor, 'cache_reject_reason'); # GET property by reference ... to be able to change it
            $reject_reason = 'DOING_CRON'; # Change private property by reference -> modifies original WPDB
        } catch (Exception $e) {}

        # flush cache
         wp_cache_flush();
        wp_cache_init();
    }

    public function resume() {
        if (!$this->paused) {
            return;
        }

        remove_filter('wpseo_sitemap_exclude_post_type', '__return_true', 999);
        remove_filter('wpseo_should_save_indexable', '__return_false', 999);

        // Add actions safely
        $this->add_action_safely('transition_post_status', 'WPSEO_Sitemaps_Cache', 'status_transition', 10, 3);
        $this->add_action_safely('edited_terms', 'WPSEO_Sitemaps_Cache', 'invalidate_taxonomy', 10, 2);
        $this->add_action_safely('save_post', 'WPSEO_Meta', 'save_postdata');

        remove_filter('wpseo_parse_opengraph_image', '__return_false');
        remove_filter('wpseo_parse_twitter_image', '__return_false');

        $this->paused = false;

        # Disable WP Obj Caching ...
        wp_using_ext_object_cache(true);
        wp_cache_flush();
        wp_cache_init();
    }

    private function remove_action_safely($hook_name, $class_name, $method_name, $priority = 10) {
        global $wp_filter;
        if (isset($wp_filter[$hook_name][$priority])) {
            foreach ($wp_filter[$hook_name][$priority] as $key => $filter) {
                if (is_array($filter['function']) && is_string($filter['function'][0]) && $filter['function'][0] === $class_name && $filter['function'][1] === $method_name) {
                    remove_action($hook_name, [$filter['function'][0], $method_name], $priority);
                    break;
                }
            }
        }
    }

    private function add_action_safely($hook_name, $class_name, $method_name, $priority, $accepted_args = 1) {
        if (class_exists($class_name) && method_exists($class_name, $method_name)) {
            add_action($hook_name, [$class_name, $method_name], $priority, $accepted_args);
        }
    }

    public function rebuild_index() {
        if (class_exists('WPSEO_Sitemaps_Cache')) {
            WPSEO_Sitemaps_Cache::clear();
        }
        if (class_exists('WPSEO_Recalculate_Posts')) {
            $recalculate_posts = new WPSEO_Recalculate_Posts();
            $recalculate_posts->recalculate();
        }
    }
}
