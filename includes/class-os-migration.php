<?php
// includes/class-os-migration.php
//
// One-time data migration from legacy `event_schedule*` identifiers to `os_*` identifiers.
//
// Version history:
//   1.0   - initial release. SHIPPED BROKEN: every old->new map was identity, so installs
//           that ran 1.0 stamped onlinesched_db_version=1.0 without moving any data.
//   1.0.1 - corrected old->new maps. Bumped TARGET_VERSION so 1.0-broken installs
//           auto-rerun the migration on next admin pageload or activation.
//
// If you fix a bug in this script later, bump TARGET_VERSION again so already-migrated
// installs pick up the fix without manual intervention.

if (!defined('ABSPATH')) {
    exit;
}

class OS_Migration {
    const TARGET_VERSION = '1.0.1';

    /**
     * Old taxonomy slug => new taxonomy slug.
     * Drives the rename in wp_term_taxonomy.
     */
    private static $taxonomy_map = array(
        'event_schedule_room_type'     => 'os_room',
        'event_schedule_tags_type'     => 'os_tag',
        'event_schedule_day_type'      => 'os_day',
        'event_schedule_panelist_type' => 'os_panelist',
    );

    /**
     * Old capability => new capability.
     * Only taxonomy-derived caps were renamed; CPT caps (`*_onlinesched_event_schedules`)
     * were already correctly prefixed and stay as-is.
     */
    private static $capability_map = array(
        'manage_event_schedule_room_type'     => 'manage_os_room',
        'edit_event_schedule_room_type'       => 'edit_os_room',
        'delete_event_schedule_room_type'     => 'delete_os_room',
        'assign_event_schedule_room_type'     => 'assign_os_room',
        'manage_event_schedule_tags_type'     => 'manage_os_tag',
        'edit_event_schedule_tags_type'       => 'edit_os_tag',
        'delete_event_schedule_tags_type'     => 'delete_os_tag',
        'assign_event_schedule_tags_type'     => 'assign_os_tag',
        'manage_event_schedule_day_type'      => 'manage_os_day',
        'edit_event_schedule_day_type'        => 'edit_os_day',
        'delete_event_schedule_day_type'      => 'delete_os_day',
        'assign_event_schedule_day_type'      => 'assign_os_day',
        'manage_event_schedule_panelist_type' => 'manage_os_panelist',
        'edit_event_schedule_panelist_type'   => 'edit_os_panelist',
        'delete_event_schedule_panelist_type' => 'delete_os_panelist',
        'assign_event_schedule_panelist_type' => 'assign_os_panelist',
    );

    public static function maybe_migrate() {
        $current = get_option('onlinesched_db_version', '0');
        if (version_compare($current, self::TARGET_VERSION, '>=')) {
            return; // Already migrated to current version
        }
        self::run_migration();          // CPT/taxonomy/option rename + role caps
        self::ensure_favorites_table(); // dbDelta safety net (must run AFTER commit)
    }

    /**
     * Idempotent dbDelta call. Mirrors onlinesched_create_favorites_table() in OnlineSched.php
     * but runs from the admin_init shim so a deploy-without-reactivate (FTP/git pull/docker
     * rebuild) still gets the table on installs that never re-fire the activation hook.
     *
     * IMPORTANT: This runs OUTSIDE the migration transaction. MySQL implicitly commits any
     * in-flight transaction the moment DDL is executed, so adding CREATE/ALTER inside
     * run_migration() would silently void the rollback safety. Keep DDL out there.
     */
    private static function ensure_favorites_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'onlinesched_favorites';
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider varchar(64) NOT NULL,
            identifier varchar(255) NOT NULL,
            favorites longtext NULL,
            last_updated datetime DEFAULT NULL,
            last_logged_in datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY provider_identifier (provider, identifier)
        ) $charset_collate;";
        dbDelta($sql);
    }

    private static function run_migration() {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // Step 1: Rename CPT in wp_posts
            $old_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'event_schedule'"
            );
            if ($old_count > 0) {
                $result = $wpdb->update(
                    $wpdb->posts,
                    array('post_type' => 'os_event'),
                    array('post_type' => 'event_schedule')
                );
                if (false === $result) {
                    throw new RuntimeException(
                        'Failed to rename CPT event_schedule -> os_event: ' . $wpdb->last_error
                    );
                }
                error_log("OnlineSched migration: renamed {$old_count} posts from event_schedule to os_event");
            }

            // Step 2: Rename taxonomies in wp_term_taxonomy
            foreach (self::$taxonomy_map as $old_tax => $new_tax) {
                $tax_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
                    $old_tax
                ));
                if ($tax_count > 0) {
                    $result = $wpdb->update(
                        $wpdb->term_taxonomy,
                        array('taxonomy' => $new_tax),
                        array('taxonomy' => $old_tax)
                    );
                    if (false === $result) {
                        throw new RuntimeException(sprintf(
                            'Failed to rename taxonomy %s -> %s: %s',
                            $old_tax, $new_tax, $wpdb->last_error
                        ));
                    }
                    error_log("OnlineSched migration: renamed taxonomy {$old_tax} -> {$new_tax} ({$tax_count} terms)");
                }
            }

            // Step 3: Rename WP option event_schedule_year -> onlinesched_year
            $old_year = get_option('event_schedule_year');
            if (false !== $old_year) {
                update_option('onlinesched_year', $old_year);
                delete_option('event_schedule_year');
                error_log("OnlineSched migration: renamed event_schedule_year option to onlinesched_year");
            }

            // Step 4: Update role capabilities
            $roles_to_update = array('administrator', 'onlinesched_admin', 'onlinesched_editor');
            foreach ($roles_to_update as $role_name) {
                $role = get_role($role_name);
                if (!$role) {
                    continue;
                }
                foreach (self::$capability_map as $old_cap => $new_cap) {
                    if ($role->has_cap($old_cap)) {
                        $role->add_cap($new_cap);
                        $role->remove_cap($old_cap);
                    }
                }
            }
            error_log("OnlineSched migration: updated capabilities on roles");

            // Step 5: Broad cleanup of any remaining legacy caps on ALL roles
            self::cleanup_legacy_caps();

            // Step 6: Mark migration complete
            update_option('onlinesched_db_version', self::TARGET_VERSION);

            $wpdb->query('COMMIT');
            error_log("OnlineSched migration: completed successfully to version " . self::TARGET_VERSION);

            // Step 7: Flush rewrite rules (must run after commit)
            flush_rewrite_rules();

        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log("OnlineSched migration FAILED: " . $e->getMessage());
            // Do NOT update onlinesched_db_version on failure — let maybe_migrate() try
            // again on the next admin pageload after the underlying issue is fixed.
        }
    }

    /**
     * Iterates through all roles and removes any capability starting with legacy prefixes.
     * Phase 12: Sterile Scour.
     */
    public static function cleanup_legacy_caps() {
        global $wp_roles;
        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        $legacy_prefixes = array(
            'manage_event_schedule_',
            'edit_event_schedule_',
            'delete_event_schedule_',
            'assign_event_schedule_',
        );

        foreach ($wp_roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if (!$role) {
                continue;
            }

            foreach ($role->capabilities as $cap => $granted) {
                foreach ($legacy_prefixes as $prefix) {
                    if (strpos($cap, $prefix) === 0) {
                        $role->remove_cap($cap);
                        error_log("OnlineSched migration: removed legacy cap {$cap} from role {$role_name}");
                        break;
                    }
                }
            }
        }
    }
}
