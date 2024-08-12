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
    }

    private function remove_action_safely($hook_name, $class_name, $method_name, $priority) {
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
