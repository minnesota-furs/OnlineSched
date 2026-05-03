<?php
/**
 * WP-CLI migration for legacy ACF Hours data.
 *
 * @package    OnlineSched
 * @author     BL, BM, AL & Contributors
 * @copyright  2016-2026 Original Authors
 * @license    GPL-2.0-or-later
 *
 * Revised by: Kurst Hyperyote for Furry Migration
 */

if (!defined('ABSPATH')) {
    exit;
}

class OnlineSched_Migrate_Hours_Command
{
    /**
     * Convert ACF Pro hours-of-operations data on a page to native OnlineSched blocks.
     *
     * ## OPTIONS
     *
     * [<page_id>]
     * : Page ID to migrate. Defaults to the configured OnlineSched Hours page.
     *
     * [--dry-run]
     * : Print the resulting block markup without saving.
     *
     * [--backup]
     * : Save original post_content to _onlinesched_hours_premigration before updating.
     *
     * [--force]
     * : Replace existing OnlineSched hours blocks if the page was already migrated.
     */
    public function __invoke($args, $assoc_args)
    {
        $page_id = isset($args[0]) ? absint($args[0]) : (int) get_option('onlinesched_hours_page_id', 0);
        if (!$page_id) {
            WP_CLI::error('No page ID provided and no Hours page configured.');
        }

        if (!function_exists('get_field')) {
            WP_CLI::error('ACF is not active - nothing to migrate from.');
        }

        if (empty($assoc_args['force']) && onlinesched_page_has_native_hours($page_id)) {
            WP_CLI::error('Page already contains OnlineSched hours blocks. Re-run with --force to replace them.');
        }

        $enabled = get_field('enable_hours', $page_id);
        $departments = get_field('departments', $page_id);
        if (empty($enabled) || !is_array($departments) || empty($departments)) {
            WP_CLI::error("Page {$page_id} has no enabled ACF hours data.");
        }

        $post = get_post($page_id);
        $blocks = onlinesched_build_migrated_hours_content($post ? $post->post_content : '', $departments);
        if (!empty($assoc_args['dry-run'])) {
            WP_CLI::log($blocks);
            return;
        }

        $result = onlinesched_migrate_hours_from_acf($page_id, !empty($assoc_args['backup']), !empty($assoc_args['force']));
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        WP_CLI::success("Migrated {$result} departments to OnlineSched hours blocks on page {$page_id}. ACF can stay active; this command only updates page content.");
    }
}

WP_CLI::add_command('onlinesched migrate-hours', 'OnlineSched_Migrate_Hours_Command');
