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

	$icons_option_name = 'onlinesched_badge_types_icons';
	$badge_types_icons = get_option($icons_option_name, array());

	$colors_option_name = 'onlinesched_badge_types_colors';
	$badge_types_colors = get_option($colors_option_name, array());

	$row_colors_option_name = 'onlinesched_badge_types_row_colors';
	$badge_types_row_colors = get_option($row_colors_option_name, array());

	$fg_colors_option_name = 'onlinesched_badge_types_fg_colors';

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
		$new_icon = !empty($_POST['badge_type_icon']) ? sanitize_text_field($_POST['badge_type_icon']) : '';
		$new_color = !empty($_POST['badge_type_color']) ? sanitize_hex_color($_POST['badge_type_color']) : '';
		$new_fg_color = !empty($_POST['badge_type_fg_color']) ? sanitize_hex_color($_POST['badge_type_fg_color']) : '';
		$new_row_color = !empty($_POST['badge_type_row_color']) ? sanitize_hex_color($_POST['badge_type_row_color']) : '';
		if (!in_array($new_name, $badge_types)) {
			$badge_types[] = $new_name;
			$badge_types_display[$new_name] = $show_badge;
			$badge_types_icons[$new_name] = $new_icon;
			$badge_types_colors[$new_name] = $new_color;
			$badge_types_fg_colors[$new_name] = $new_fg_color;
			$badge_types_row_colors[$new_name] = $new_row_color;
			update_option($option_name, $badge_types);
			update_option($display_option_name, $badge_types_display);
			update_option($icons_option_name, $badge_types_icons);
			update_option($colors_option_name, $badge_types_colors);
			update_option($fg_colors_option_name, $badge_types_fg_colors);
			update_option($row_colors_option_name, $badge_types_row_colors);
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
			unset($badge_types_icons[$del]);
			unset($badge_types_colors[$del]);
			unset($badge_types_fg_colors[$del]);
			unset($badge_types_row_colors[$del]);
			$badge_types = array_values($badge_types);
			update_option($option_name, $badge_types);
			update_option($display_option_name, $badge_types_display);
			update_option($icons_option_name, $badge_types_icons);
			update_option($colors_option_name, $badge_types_colors);
			update_option($fg_colors_option_name, $badge_types_fg_colors);
			update_option($row_colors_option_name, $badge_types_row_colors);
			// Remove badge_type meta from all tags referencing this badge type
			$tags = get_terms([
				'taxonomy' => 'event_schedule_tags_type',
				'hide_empty' => false,
			]);
			foreach ($tags as $tag) {
				$badge_type = get_term_meta($tag->term_id, 'badge_type', true);
				if ($badge_type === $del) {
					delete_term_meta($tag->term_id, 'badge_type');
				}
			}
			$message = 'Badge type deleted.';
		}
	}
	if ($action === 'edit' && isset($_POST['badge_type_edit_old'], $_POST['badge_type_edit_new'])) {
		$old = sanitize_text_field($_POST['badge_type_edit_old']);
		$new = sanitize_text_field($_POST['badge_type_edit_new']);
		$show_badge = !empty($_POST['badge_type_show_badge_edit']) && $_POST['badge_type_show_badge_edit'] == '1';
		$new_icon = isset($_POST['badge_type_icon_edit']) ? sanitize_text_field($_POST['badge_type_icon_edit']) : '';
		// Fix: handle transparent checkbox and color value
		$new_color = '';
		if (isset($_POST['badge_type_color_transparent']) && $_POST['badge_type_color_transparent'] == '1') {
			$new_color = 'transparent';
		} elseif (isset($_POST['badge_type_color_edit'])) {
			$new_color = sanitize_hex_color($_POST['badge_type_color_edit']);
			if ($new_color === null) $new_color = '';
		}
		$new_fg_color = isset($_POST['badge_type_fg_color_edit']) ? $_POST['badge_type_fg_color_edit'] : '';
		if ($new_fg_color !== '') {
			if ($new_fg_color[0] !== '#') {
				$new_fg_color = '#' . ltrim($new_fg_color, '#');
			}
			$new_fg_color = sanitize_hex_color($new_fg_color);
			if ($new_fg_color === null) $new_fg_color = '';
		}
		$new_row_color = isset($_POST['badge_type_row_color_edit']) ? sanitize_hex_color($_POST['badge_type_row_color_edit']) : '';
		$key = array_search($old, $badge_types);
		if ($key !== false && (!in_array($new, $badge_types) || $old === $new)) {
			$badge_types[$key] = $new;
			unset($badge_types_display[$old]);
			unset($badge_types_icons[$old]);
			unset($badge_types_colors[$old]);
			unset($badge_types_fg_colors[$old]);
			unset($badge_types_row_colors[$old]);
			$badge_types_display[$new] = $show_badge;
			$badge_types_icons[$new] = $new_icon;
			$badge_types_colors[$new] = $new_color;
			$badge_types_fg_colors[$new] = $new_fg_color;
			$badge_types_row_colors[$new] = $new_row_color;
			update_option($option_name, $badge_types);
			update_option($display_option_name, $badge_types_display);
			update_option($icons_option_name, $badge_types_icons);
			update_option($colors_option_name, $badge_types_colors);
			update_option($fg_colors_option_name, $badge_types_fg_colors);
			update_option($row_colors_option_name, $badge_types_row_colors);
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
		<form method="post" style="margin-bottom:1em;">
			<!-- Removed global icon usage toggle -->
		</form>
		<?php if ($message) {
			$class = (strpos($message, 'error') !== false || strpos($message, 'exists') !== false) ? 'upload-error' : 'schedule-updated';
			echo '<div class="' . $class . '"><button class="close-message" onclick="closeMessageBox(this)">&times;</button><p>' . esc_html($message) . '</p></div>';
		} ?>
		<form method="post" style="margin-bottom:1em;" id="add-badge-type-form">
			<input type="hidden" name="badge_action" value="add">
			<label for="badge_type_name" style="margin-right:10px;">Badge Type Name:
				<input type="text" name="badge_type_name" id="badge_type_name" placeholder="New badge type" required>
			</label>
			<input type="hidden" name="badge_type_show_badge" value="0">
			<label for="badge_type_show_badge" style="margin-left:10px;"><input type="checkbox" name="badge_type_show_badge" id="badge_type_show_badge" value="1" checked> Show badge visually?</label>
			<label for="badge_type_icon" style="margin-left:10px;">Font Awesome Icon:
				<input type="text" name="badge_type_icon" id="badge_type_icon" placeholder="Font Awesome icon class (e.g. fa-solid fa-star)" style="margin-left:10px;" />
			</label>
			<label style="margin-left:10px;">Badge Background Color:
				<input type="color" name="badge_type_color" id="badge_type_color" value="#b5d8ac" style="width:40px;" title="Default: #b5d8ac (Essentials)" />
				<button type="button" onclick="document.getElementById('badge_type_color').value='#b5d8ac'; document.getElementById('badge_type_color_hidden').value=''; document.getElementById('badge_type_color_transparent').checked=false;">Clear</button>
				<span style="font-size:12px;color:#666;">(Leave as default for Essentials green)</span>
				<input type="hidden" name="badge_type_color_hidden" id="badge_type_color_hidden" value="">
				<label style="margin-left:10px;"><input type="checkbox" id="badge_type_color_transparent" name="badge_type_color_transparent" value="1"> Transparent</label>
			</label>
			<label style="margin-left:10px;">Badge Text/Icon Color:
				<input type="color" name="badge_type_fg_color" id="badge_type_fg_color" value="#333333" style="width:40px;" title="Default: #333333 (Text)" />
				<button type="button" onclick="document.getElementById('badge_type_fg_color').value='#333333'; document.getElementById('badge_type_fg_color_hidden').value='';">Clear</button>
				<span style="font-size:12px;color:#666;">(Leave as default for standard text)</span>
				<input type="hidden" name="badge_type_fg_color_hidden" id="badge_type_fg_color_hidden" value="">
			</label>
			<label style="margin-left:10px;">Row Highlight Color:
				<input type="color" name="badge_type_row_color" id="badge_type_row_color" value="" style="width:40px;" title="Row background color for schedule highlight" disabled />
				<button type="button" onclick="document.getElementById('badge_type_row_color').value=''; document.getElementById('badge_type_row_color_enable').checked=false; document.getElementById('badge_type_row_color').disabled=true;">Clear</button>
				<span style="font-size:12px;color:#666;">(Optional: color for schedule row highlight)</span>
				<label style="margin-left:10px;"><input type="checkbox" id="badge_type_row_color_enable" name="badge_type_row_color_enable" value="1"> Enable Row Highlight</label>
			</label>
			<script>
			// On submit, if color is default, set hidden field to blank
			document.getElementById('add-badge-type-form').onsubmit = function() {
				var bg = document.getElementById('badge_type_color').value;
				var fg = document.getElementById('badge_type_fg_color').value;
				var row = document.getElementById('badge_type_row_color').value;
				var transparent = document.getElementById('badge_type_color_transparent').checked;
				var enableRow = document.getElementById('badge_type_row_color_enable').checked;
				document.getElementById('badge_type_color_hidden').value = transparent ? 'transparent' : (bg === '#b5d8ac' ? '' : bg);
				document.getElementById('badge_type_fg_color_hidden').value = (fg === '#333333') ? '' : fg;
				// Set actual color fields to hidden values for PHP
				document.getElementById('badge_type_color').value = document.getElementById('badge_type_color_hidden').value;
				document.getElementById('badge_type_fg_color').value = document.getElementById('badge_type_fg_color_hidden').value;
				// Row color
				if (!enableRow) {
					document.getElementById('badge_type_row_color').value = '';
				}
				return true;
			};
			// When transparent is checked, disable color picker
			document.getElementById('badge_type_color_transparent').onchange = function() {
				document.getElementById('badge_type_color').disabled = this.checked;
			};
			document.getElementById('badge_type_row_color_enable').onchange = function() {
				document.getElementById('badge_type_row_color').disabled = !this.checked;
			};
			</script>
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
			<thead>
				<tr>
					<th>Name</th>
					<th>Show Badge?</th>
					<th>Font Awesome Icon</th>
					<th>Badge Background Color</th>
					<th>Badge Text/Icon Color</th>
					<th>Row Highlight Color</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
<?php foreach ($badge_types as $badge) : ?>
<tr>
    <td>
        <form method="post" id="edit-badge-type-form-<?php echo esc_attr($badge); ?>">
            <input type="hidden" name="badge_action" value="edit">
            <input type="hidden" name="badge_type_edit_old" value="<?php echo esc_attr($badge); ?>">
            <label for="badge_type_edit_new_<?php echo esc_attr($badge); ?>">Name:
                <input type="text" name="badge_type_edit_new" id="badge_type_edit_new_<?php echo esc_attr($badge); ?>" value="<?php echo esc_attr($badge); ?>" required>
            </label>
    </td>
    <td>
            <input type="hidden" name="badge_type_show_badge_edit" value="0">
            <label for="badge_type_show_badge_edit_<?php echo esc_attr($badge); ?>" style="margin-left:10px;"><input type="checkbox" name="badge_type_show_badge_edit" id="badge_type_show_badge_edit_<?php echo esc_attr($badge); ?>" value="1" <?php echo (!empty($badge_types_display[$badge])) ? 'checked' : ''; ?>> Show badge visually?</label>
    </td>
    <td>
            <label for="badge_type_icon_edit_<?php echo esc_attr($badge); ?>">Icon:
                <input type="text" name="badge_type_icon_edit" id="badge_type_icon_edit_<?php echo esc_attr($badge); ?>" value="<?php echo isset($badge_types_icons[$badge]) ? esc_attr($badge_types_icons[$badge]) : ''; ?>" placeholder="Font Awesome icon class (e.g. fa-solid fa-star)" style="width:140px;" />
            </label>
    </td>
    <td>
            <input type="color" name="badge_type_color_edit" id="badge_type_color_edit_<?php echo esc_attr($badge); ?>" value="<?php echo (isset($badge_types_colors[$badge]) && $badge_types_colors[$badge] && $badge_types_colors[$badge] !== 'transparent') ? esc_attr($badge_types_colors[$badge]) : '#b5d8ac'; ?>" style="width:40px;" title="Default: #b5d8ac (Essentials)" <?php echo (isset($badge_types_colors[$badge]) && $badge_types_colors[$badge] === 'transparent') ? 'disabled' : ''; ?> />
            <button type="button" onclick="document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge); ?>').value='#b5d8ac'; document.getElementById('badge_type_color_edit_hidden_<?php echo esc_attr($badge); ?>').value=''; document.getElementById('badge_type_color_transparent_<?php echo esc_attr($badge); ?>').checked=false; document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge); ?>').disabled=false;">Clear</button>
            <br />
            <input type="hidden" name="badge_type_color_edit_hidden" id="badge_type_color_edit_hidden_<?php echo esc_attr($badge); ?>" value="">
            <label style="margin-left:10px;"><input type="checkbox" id="badge_type_color_transparent_<?php echo esc_attr($badge); ?>" name="badge_type_color_transparent" value="1" <?php echo (isset($badge_types_colors[$badge]) && $badge_types_colors[$badge] === 'transparent') ? 'checked' : ''; ?>> Transparent</label>
            <script>
                document.getElementById('edit-badge-type-form-<?php echo esc_attr($badge); ?>').onsubmit = function() {
                    var bg = document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge); ?>').value;
                    var transparent = document.getElementById('badge_type_color_transparent_<?php echo esc_attr($badge); ?>').checked;
                    document.getElementById('badge_type_color_edit_hidden_<?php echo esc_attr($badge); ?>').value = transparent ? 'transparent' : (bg === '#b5d8ac' ? '' : bg);
                    if (transparent) document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge); ?>').disabled = true;
                    else document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge); ?>').disabled = false;
                    return true;
                };
                document.getElementById('badge_type_color_transparent_<?php echo esc_attr($badge); ?>').onchange = function() {
                    document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge); ?>').disabled = this.checked;
                };
            </script>
    </td>
    <td>
            <label for="badge_type_fg_color_edit_<?php echo esc_attr($badge); ?>">Text/Icon Color:
                <input type="color" name="badge_type_fg_color_edit" id="badge_type_fg_color_edit_<?php echo esc_attr($badge); ?>" value="<?php echo isset($badge_types_fg_colors[$badge]) && $badge_types_fg_colors[$badge] ? esc_attr($badge_types_fg_colors[$badge]) : '#333333'; ?>" style="width:40px;" title="Default: #333333 (Text)" />
                <button type="button" onclick="document.getElementById('badge_type_fg_color_edit_<?php echo esc_attr($badge); ?>').value='#333333'; document.getElementById('badge_type_fg_color_edit_hidden_<?php echo esc_attr($badge); ?>').value='';">Clear</button>
                <input type="hidden" name="badge_type_fg_color_edit_hidden" id="badge_type_fg_color_edit_hidden_<?php echo esc_attr($badge); ?>" value="">
            </label>
    </td>
    <td>
            <label for="badge_type_row_color_edit_<?php echo esc_attr($badge); ?>">Row Highlight Color:
                <input type="color" name="badge_type_row_color_edit" id="badge_type_row_color_edit_<?php echo esc_attr($badge); ?>" value="<?php echo isset($badge_types_row_colors[$badge]) && $badge_types_row_colors[$badge] ? esc_attr($badge_types_row_colors[$badge]) : ''; ?>" style="width:40px;" title="Row background color for schedule highlight" <?php echo (empty($badge_types_row_colors[$badge])) ? 'disabled' : ''; ?> />
                <label style="margin-left:10px;"><input type="checkbox" id="badge_type_row_color_enable_<?php echo esc_attr($badge); ?>" name="badge_type_row_color_enable_edit" value="1" <?php echo (!empty($badge_types_row_colors[$badge])) ? 'checked' : ''; ?>> Enable Row Highlight</label>
                <button type="button" onclick="document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge); ?>').value=''; document.getElementById('badge_type_row_color_enable_<?php echo esc_attr($badge); ?>').checked=false; document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge); ?>').disabled=true;">Clear</button>
                <span style="font-size:12px;color:#666;">(Optional: color for schedule row highlight)</span>
                <script>
                    document.getElementById('badge_type_row_color_enable_<?php echo esc_attr($badge); ?>').onchange = function() {
                        document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge); ?>').disabled = !this.checked;
                    };
                    document.getElementById('edit-badge-type-form-<?php echo esc_attr($badge); ?>').onsubmit = function() {
                        var enableRow = document.getElementById('badge_type_row_color_enable_<?php echo esc_attr($badge); ?>').checked;
                        if (!enableRow) {
                            document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge); ?>').value = '';
                        }
                        return true;
                    };
                </script>
            </label>
    </td>
    <td>
            <?php submit_button('Edit', 'secondary', 'edit_badge_type', false); ?>
        </form>
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
