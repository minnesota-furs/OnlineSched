<?php
// OnlineSchedBadgeTypes.php
// Admin page for managing badge types

function onlinesched_badge_types_menu() {
	add_submenu_page(
		'edit.php?post_type=event_schedule',
		'Badge Types',
		'Badge Types',
		'manage_event_schedule_tags_type',
		'onlinesched-badge-types',
		'onlinesched_badge_types_page'
	);
}
add_action('admin_menu', 'onlinesched_badge_types_menu', 9);

function onlinesched_badge_types_page() {
	// Handle add/edit/delete/restore
	if (!current_user_can('manage_event_schedule_tags_type')) {
		wp_die('You do not have permission to manage badge types.');
	}

	$option_name = 'onlinesched_badge_types';
	$badge_types = get_option($option_name, array());
	$display_option_name = 'onlinesched_badge_types_display';
	$badge_types_display = get_option($display_option_name, array());
	$action = isset($_POST['badge_action']) ? $_POST['badge_action'] : '';
	$message = '';

	$default_badge_types = array('Adult', 'Sensory', 'VIP', 'Essentials', 'Guest Of Honor', 'Special Guest', 'Streaming', 'Cancelled');

	// Sort badge types for display in table
	if (!empty($badge_types)) {
		natcasesort($badge_types);
	}

	if ($action === 'restore_defaults') {
		// Non-destructive: add missing defaults, keep custom badge types
		$added = 0;
		foreach ($default_badge_types as $default) {
			if (!in_array($default, $badge_types)) {
				$badge_types[] = $default;
				$added++;
				$badge_types_display[$default] = true; // Default: show badge
			}
		}
		update_option($option_name, $badge_types);
		update_option($display_option_name, $badge_types_display);
		if ($added > 0) {
			$message = 'Default badge types restored (added ' . $added . ' missing).';
		} else {
			$message = 'All default badge types already present. No changes made.';
		}
	}
	if ($action === 'add' && !empty($_POST['badge_type_name'])) {
		$new_name = sanitize_text_field($_POST['badge_type_name']);
		$show_badge = !empty($_POST['badge_type_show_badge']) && $_POST['badge_type_show_badge'] == '1';
		if (!in_array($new_name, $badge_types)) {
			$badge_types[] = $new_name;
			$badge_types_display[$new_name] = $show_badge;
			update_option($option_name, $badge_types);
			update_option($display_option_name, $badge_types_display);
			$message = 'Badge type added.';
		} else {
			$message = 'Badge type already exists.';
		}
	}
	if ($action === 'delete' && isset($_POST['badge_type_delete'])) {
		$del = sanitize_text_field($_POST['badge_type_delete']);
		$key = array_search($del, $badge_types);
		if ($key !== false) {
			unset($badge_types[$key]);
			unset($badge_types_display[$del]);
			$badge_types = array_values($badge_types);
			update_option($option_name, $badge_types);
			update_option($display_option_name, $badge_types_display);
			$message = 'Badge type deleted.';
		}
	}
	if ($action === 'edit' && isset($_POST['badge_type_edit_old'], $_POST['badge_type_edit_new'])) {
		$old = sanitize_text_field($_POST['badge_type_edit_old']);
		$new = sanitize_text_field($_POST['badge_type_edit_new']);
		$show_badge = !empty($_POST['badge_type_show_badge_edit']) && $_POST['badge_type_show_badge_edit'] == '1';
		$key = array_search($old, $badge_types);
		if ($key !== false && (!in_array($new, $badge_types) || $old === $new)) {
			$badge_types[$key] = $new;
			unset($badge_types_display[$old]);
			$badge_types_display[$new] = $show_badge;
			update_option($option_name, $badge_types);
			update_option($display_option_name, $badge_types_display);
			$message = 'Badge type updated.';
		} else {
			$message = 'Badge type already exists or not found.';
		}
	}
	?>
	<style>
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
		position: relative;
	}
	.schedule-updated .close-message {
		position: absolute;
		top: 8px;
		right: 12px;
		background: none;
		border: none;
		font-size: 18px;
		color: #1a531b;
		cursor: pointer;
	}
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
		position: relative;
	}
	.upload-error .close-message {
		position: absolute;
		top: 8px;
		right: 12px;
		background: none;
		border: none;
		font-size: 18px;
		color: #b32d2e;
		cursor: pointer;
	}
	</style>
	<script>
	function closeMessageBox(btn) {
		btn.parentElement.style.display = 'none';
	}
	</script>
	<div class="wrap">
		<h2>Badge Types</h2>
		<?php if ($message) {
			$class = (strpos($message, 'error') !== false || strpos($message, 'exists') !== false) ? 'upload-error' : 'schedule-updated';
			echo '<div class="' . $class . '"><button class="close-message" onclick="closeMessageBox(this)">&times;</button><p>' . esc_html($message) . '</p></div>';
		} ?>
		<form method="post" style="margin-bottom:1em;">
			<input type="hidden" name="badge_action" value="add">
			<input type="text" name="badge_type_name" placeholder="New badge type" required>
			<input type="hidden" name="badge_type_show_badge" value="0">
			<label style="margin-left:10px;"><input type="checkbox" name="badge_type_show_badge" value="1" checked> Show badge visually?</label>
			<?php submit_button('Add Badge Type', 'primary', 'add_badge_type', false); ?>
		</form>
		<form method="post" style="margin-bottom:1em;">
			<input type="hidden" name="badge_action" value="restore_defaults">
			<?php submit_button('Restore Defaults', 'secondary', 'restore_defaults_badge_type', false); ?>
		</form>
		<form method="post" style="margin-bottom:1em;">
			<button type="button" id="assign-default-badge-types" class="button">Assign Default Badge Types to Tags</button>
		</form>
		<div id="assign-badge-types-message" style="display:none;"></div>
		<script>
		jQuery(document).ready(function($){
			$('#assign-default-badge-types').on('click', function(e){
				e.preventDefault();
				var btn = $(this);
				btn.prop('disabled', true);
				$('#assign-badge-types-message').hide().empty();
				$.post(ajaxurl, { action: 'onlinesched_assign_default_badge_types' }, function(resp){
					btn.prop('disabled', false);
					var msgClass = resp.success ? 'schedule-updated' : 'upload-error';
					var msgText = resp.success ?
						'Assigned badge types to ' + resp.data.updated + ' tags.' :
						'Error: ' + resp.data;
					var msgHtml = '<div class="' + msgClass + '"><button class="close-message" onclick="closeMessageBox(this)">&times;</button><p>' + msgText + '</p></div>';
					$('#assign-badge-types-message').html(msgHtml).show();
				});
			});
		});
		</script>
		<h3>Existing Badge Types</h3>
		<table class="widefat">
			<thead><tr><th>Name</th><th>Show Badge?</th><th>Actions</th></tr></thead>
			<tbody>
			<?php foreach ($badge_types as $badge) : ?>
			<tr>
				<td><?php echo esc_html($badge); ?></td>
				<td>
					<form method="post" style="display:inline;">
						<input type="hidden" name="badge_action" value="edit">
						<input type="hidden" name="badge_type_edit_old" value="<?php echo esc_attr($badge); ?>">
						<input type="text" name="badge_type_edit_new" value="<?php echo esc_attr($badge); ?>" required>
						<input type="hidden" name="badge_type_show_badge_edit" value="0">
						<label style="margin-left:10px;"><input type="checkbox" name="badge_type_show_badge_edit" value="1" <?php echo (!isset($badge_types_display[$badge]) || $badge_types_display[$badge]) ? 'checked' : ''; ?>> Show badge visually?</label>
						<?php submit_button('Edit', 'secondary', 'edit_badge_type', false); ?>
					</form>
				</td>
				<td>
					<form method="post" style="display:inline;">
						<input type="hidden" name="badge_action" value="delete">
						<input type="hidden" name="badge_type_delete" value="<?php echo esc_attr($badge); ?>">
						<?php submit_button('Delete', 'delete', 'delete_badge_type', false); ?>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

// Add badge type field to ADD tag screen
add_action('event_schedule_tags_type_add_form_fields', function() {
    $badge_types = get_option('onlinesched_badge_types', array());
    if (!empty($badge_types)) {
        natcasesort($badge_types);
    }
    ?>
    <div class="form-field">
        <label for="badge_type">Badge Type</label>
        <select name="badge_type" id="badge_type">
            <option value="">None</option>
            <?php foreach ($badge_types as $type) : ?>
                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select a badge type for this tag (optional).</p>
    </div>
    <?php
});

// Add badge type field to EDIT tag screen
add_action('event_schedule_tags_type_edit_form_fields', function($term) {
    $badge_types = get_option('onlinesched_badge_types', array());
    if (!empty($badge_types)) {
        natcasesort($badge_types);
    }
    $selected = get_term_meta($term->term_id, 'badge_type', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="badge_type">Badge Type</label></th>
        <td>
            <select name="badge_type" id="badge_type">
                <option value="">None</option>
                <?php foreach ($badge_types as $type) : ?>
                    <option value="<?php echo esc_attr($type); ?>" <?php selected($selected, $type); ?>><?php echo esc_html($type); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">Select a badge type for this tag (optional).</p>
        </td>
    </tr>
    <?php
}, 10, 1);

// Save badge type on tag CREATE
add_action('created_event_schedule_tags_type', function($term_id) {
    $badge_type = isset($_POST['badge_type']) ? sanitize_text_field($_POST['badge_type']) : '';
    if ($badge_type) {
        update_term_meta($term_id, 'badge_type', $badge_type);
    } else {
        // Auto-assign badge type based on slug if not set
        $term = get_term($term_id, 'event_schedule_tags_type');
        $slug = $term ? $term->slug : '';
        $name = $term ? strtolower($term->name) : '';
        $default_slug_to_badge_type = [
            'essentials' => 'Essentials',
            'streaming' => 'Streaming',
            'restricted' => 'Adult',
            'sensory' => 'Sensory',
            'guest-of-honor' => 'Guest Of Honor',
            'special-guest' => 'Special Guest',
            'vip' => 'VIP',
            'cancelled' => 'Cancelled',
            'canceled' => 'Cancelled',
        ];
        if (isset($default_slug_to_badge_type[$slug])) {
            update_term_meta($term_id, 'badge_type', $default_slug_to_badge_type[$slug]);
        } elseif (isset($default_slug_to_badge_type[sanitize_title($name)])) {
            update_term_meta($term_id, 'badge_type', $default_slug_to_badge_type[sanitize_title($name)]);
        }
    }
}, 10, 1);

// Save badge type on tag EDIT
add_action('edited_event_schedule_tags_type', function($term_id) {
    $badge_type = isset($_POST['badge_type']) ? sanitize_text_field($_POST['badge_type']) : '';
    if ($badge_type) {
        update_term_meta($term_id, 'badge_type', $badge_type);
    }
    // Removed default assignment logic from EDIT hook
}, 10, 1);

function onlinesched_assign_default_badge_types_ajax() {
    if (!current_user_can('manage_event_schedule_tags_type')) {
        wp_send_json_error('Permission denied');
    }
    $default_slug_to_badge_type = [
        'essentials' => 'Essentials',
        'streaming' => 'Streaming',
        'restricted' => 'Adult',
        'sensory' => 'Sensory',
        'guest-of-honor' => 'Guest Of Honor',
        'special-guest' => 'Special Guest',
        'vip' => 'VIP',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
    ];
    $tags = get_terms([
        'taxonomy' => 'event_schedule_tags_type',
        'hide_empty' => false,
    ]);
    $updated = 0;
    foreach ($tags as $tag) {
        $slug = $tag->slug;
        if (isset($default_slug_to_badge_type[$slug])) {
            update_term_meta($tag->term_id, 'badge_type', $default_slug_to_badge_type[$slug]);
            $updated++;
        }
    }
    wp_send_json_success([ 'updated' => $updated ]);
}
add_action('wp_ajax_onlinesched_assign_default_badge_types', 'onlinesched_assign_default_badge_types_ajax');
