<?php
// OnlineSchedBadgeTypes.php
// Admin page for managing badge types

// Enqueue admin CSS/JS only for badge types page
add_action('admin_enqueue_scripts', function($hook) {
    if (!isset($_GET['page']) || $_GET['page'] !== 'onlinesched-badge-types') return;
    
    $css_version = file_exists(plugin_dir_path(__FILE__) . 'build/admin-badge-types.css') ? filemtime(plugin_dir_path(__FILE__) . 'build/admin-badge-types.css') : '1.1';
    $js_version = file_exists(plugin_dir_path(__FILE__) . 'admin-badge-types.js') ? filemtime(plugin_dir_path(__FILE__) . 'admin-badge-types.js') : '1.1';
    
    wp_enqueue_style('onlinesched-badge-types-admin', plugin_dir_url(__FILE__) . 'build/admin-badge-types.css', [], $css_version);
    wp_enqueue_script('onlinesched-badge-types-admin', plugin_dir_url(__FILE__) . 'admin-badge-types.js', [], $js_version, true);
    wp_localize_script('onlinesched-badge-types-admin', 'OnlineSchedBadgeTypes', array(
        'nonce' => wp_create_nonce('onlinesched_badge_types'),
    ));

    // Removed direct enqueue of Font Awesome. It will be imported via admin-badge-types.scss and compiled by webpack
});

function onlinesched_badge_types_page() {
	// Handle add/edit/delete/restore
	if (!current_user_can('manage_os_tag')) {
		wp_die('You do not have permission to manage badge types.');
	}

	$option_name = 'onlinesched_badge_types';
	$badge_types = get_option($option_name, array());
	$display_option_name = 'onlinesched_badge_types_display';
	$badge_types_display = get_option($display_option_name, array());
	$action = isset($_POST['badge_action']) ? $_POST['badge_action'] : '';
	$message = '';
    if ($action) {
        check_admin_referer('onlinesched_badge_types');
    }

	$icons_option_name = 'onlinesched_badge_types_icons';
	$badge_types_icons = get_option($icons_option_name, array());

	$colors_option_name = 'onlinesched_badge_types_colors';
	$badge_types_colors = get_option($colors_option_name, array());

	$row_colors_option_name = 'onlinesched_badge_types_row_colors';
	$badge_types_row_colors = get_option($row_colors_option_name, array());

	$fg_colors_option_name = 'onlinesched_badge_types_fg_colors';
	$badge_types_fg_colors = get_option($fg_colors_option_name, array());

	$default_badge_types_config = array( // Renamed to avoid conflict with $default_name in the loop
        'Adult' => array('color' => '#d12229', 'fg_color' => '#ffffff', 'show_badge' => true),
        'Sensory' => array('color' => '#0a58ca', 'fg_color' => '#ffffff', 'show_badge' => true),
        'VIP' => array('row_color' => '#fff0b2', 'show_badge' => true),
        'Essentials' => array(),
        'Guest Of Honor' => array('row_color' => '#b5d8ac', 'icon' => 'fas fa-star', 'show_badge' => false),
        'Special Guest' => array('row_color' => '#b5d8ac', 'icon' => 'fas fa-star', 'show_badge' => false),
        'Streaming' => array(),
        'Cancelled' => array()
    );

	// Sort badge types for display in table
	if (!empty($badge_types)) {
		natcasesort($badge_types);
	}

	if ($action === 'restore_defaults') {
		$updated_count = 0;
		$new_badge_types = array();
		$new_badge_types_display = array();
		$new_badge_types_icons = array();
		$new_badge_types_colors = array();
		$new_badge_types_fg_colors = array();
		$new_badge_types_row_colors = array();

		// First, preserve existing custom badge types
		foreach ($badge_types as $existing_badge_name) {
			if (!array_key_exists($existing_badge_name, $default_badge_types_config)) {
				$new_badge_types[] = $existing_badge_name;
				$new_badge_types_display[$existing_badge_name] = $badge_types_display[$existing_badge_name] ?? true;
				$new_badge_types_icons[$existing_badge_name] = $badge_types_icons[$existing_badge_name] ?? '';
				$new_badge_types_colors[$existing_badge_name] = $badge_types_colors[$existing_badge_name] ?? '';
				$new_badge_types_fg_colors[$existing_badge_name] = $badge_types_fg_colors[$existing_badge_name] ?? '';
				$new_badge_types_row_colors[$existing_badge_name] = $badge_types_row_colors[$existing_badge_name] ?? '';
			}
		}

		// Then, add/overwrite default badge types
		foreach ($default_badge_types_config as $default_name => $attrs) {
			if (!in_array($default_name, $new_badge_types)) {
				$new_badge_types[] = $default_name;
			}
			$new_badge_types_display[$default_name] = $attrs['show_badge'] ?? true;
			$new_badge_types_icons[$default_name] = $attrs['icon'] ?? '';
			$new_badge_types_colors[$default_name] = $attrs['color'] ?? '';
			$new_badge_types_fg_colors[$default_name] = $attrs['fg_color'] ?? '';
			$new_badge_types_row_colors[$default_name] = $attrs['row_color'] ?? '';
			$updated_count++;
		}
        
        // Sort the final list of badge types alphabetically
        natcasesort($new_badge_types);

		update_option($option_name, array_values($new_badge_types)); // Re-index array
		update_option($display_option_name, $new_badge_types_display);
		update_option($icons_option_name, $new_badge_types_icons);
		update_option($colors_option_name, $new_badge_types_colors);
		update_option($fg_colors_option_name, $new_badge_types_fg_colors);
		update_option($row_colors_option_name, $new_badge_types_row_colors);
		
		$message = 'Default badge types restored and custom badge types preserved (' . $updated_count . ' defaults updated).';
	}
	if ($action === 'add' && !empty($_POST['badge_type_name'])) {
		$new_name = sanitize_text_field($_POST['badge_type_name']);
		$show_badge = !empty($_POST['badge_type_show_badge']) && $_POST['badge_type_show_badge'] == '1';
		$new_icon = !empty($_POST['badge_type_icon']) ? sanitize_text_field($_POST['badge_type_icon']) : '';
		
		$new_color = '';
		if (isset($_POST['badge_type_color_transparent']) && $_POST['badge_type_color_transparent'] == '1') {
			$new_color = 'transparent';
		} else {
			$picker_val = isset($_POST['badge_type_color']) ? $_POST['badge_type_color'] : '';
			$new_color = sanitize_hex_color($picker_val);
			if ($new_color === null) $new_color = '';
		}

		$new_fg_color = '';
		if (!isset($_POST['badge_type_fg_color_default']) || $_POST['badge_type_fg_color_default'] !== '1') {
			$fg_picker = isset($_POST['badge_type_fg_color']) ? $_POST['badge_type_fg_color'] : '';
			$new_fg_color = sanitize_hex_color($fg_picker);
			if ($new_fg_color === null) $new_fg_color = '';
		}

		$new_row_color = '';
		if (isset($_POST['badge_type_row_color_enable']) && $_POST['badge_type_row_color_enable'] == '1') {
			$new_row_color = isset($_POST['badge_type_row_color']) ? sanitize_hex_color($_POST['badge_type_row_color']) : '';
			if ($new_row_color === null) $new_row_color = '';
		}

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
	if ($action === 'save_tag_map') {
		$assigned = array();
		$posted = isset($_POST['badge_tags']) && is_array($_POST['badge_tags'])
			? $_POST['badge_tags']
			: array();
		$claims = array();
		foreach ($posted as $type => $slugs) {
			$type = sanitize_text_field($type);
			if (!in_array($type, $badge_types, true)) {
				continue;
			}
			foreach ((array) $slugs as $slug) {
				$slug = sanitize_title($slug);
				if ('' === $slug) {
					continue;
				}
				// A tag belongs to at most one type, so a second claim is a
				// conflict to report rather than a duplicate to store.
				if (isset($claims[$slug])) {
					$claims[$slug][] = $type;
					continue;
				}
				$claims[$slug] = array($type);
			}
		}

		$conflicts = array();
		foreach ($claims as $slug => $types) {
			if (count($types) > 1) {
				$conflicts[] = $slug;
				continue;
			}
			$assigned[$slug] = $types[0];
		}

		if ($conflicts) {
			$message = 'Not saved: ' . implode(', ', $conflicts)
				. ' was claimed by more than one badge type. A tag belongs to one type.';
		} else {
			$existing = onlinesched_get_tag_badge_map();
			foreach ($existing as $slug => $type) {
				// Keep an explicit None; anything else not re-claimed is released.
				if (ONLINESCHED_BADGE_NONE === $type && !isset($assigned[$slug])) {
					$assigned[$slug] = ONLINESCHED_BADGE_NONE;
				}
			}
			onlinesched_save_tag_badge_map($assigned);
			$message = 'Tag associations saved.';
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
				'taxonomy' => 'os_tag',
				'hide_empty' => false,
			]);
			foreach ($tags as $tag) {
				$badge_type = get_term_meta($tag->term_id, 'badge_type', true);
				if ($badge_type === $del) {
					delete_term_meta($tag->term_id, 'badge_type');
				}
			}
			$unmapped = onlinesched_reconcile_tag_badge_map($del, null);
			$message = 'Badge type deleted. ' . $unmapped . ' tag mapping(s) released.';
		}
	}
	if ($action === 'edit' && isset($_POST['badge_type_edit_old'], $_POST['badge_type_edit_new'])) {
		$old = sanitize_text_field($_POST['badge_type_edit_old']);
		$new = sanitize_text_field($_POST['badge_type_edit_new']);
		$show_badge = !empty($_POST['badge_type_show_badge_edit']) && $_POST['badge_type_show_badge_edit'] == '1';
		$new_icon = isset($_POST['badge_type_icon_edit']) ? sanitize_text_field($_POST['badge_type_icon_edit']) : '';
		
		$new_color = '';
		if (isset($_POST['badge_type_color_transparent']) && $_POST['badge_type_color_transparent'] == '1') {
			$new_color = 'transparent';
		} else {
            $picker_val = isset($_POST['badge_type_color_edit']) ? $_POST['badge_type_color_edit'] : '';
            $new_color = sanitize_hex_color($picker_val);
            if ($new_color === null) $new_color = '';
		}
		
		$new_fg_color = '';
		if (!isset($_POST['badge_type_fg_color_default_edit']) || $_POST['badge_type_fg_color_default_edit'] !== '1') {
			$fg_picker = isset($_POST['badge_type_fg_color_edit']) ? $_POST['badge_type_fg_color_edit'] : '';
            $new_fg_color = sanitize_hex_color($fg_picker);
            if ($new_fg_color === null) $new_fg_color = '';
		}
		
		$new_row_color = '';
		if (isset($_POST['badge_type_row_color_enable_edit']) && $_POST['badge_type_row_color_enable_edit'] == '1') {
			$new_row_color = isset($_POST['badge_type_row_color_edit']) ? sanitize_hex_color($_POST['badge_type_row_color_edit']) : '';
			if ($new_row_color === null) $new_row_color = '';
		}

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
			$moved = onlinesched_reconcile_tag_badge_map($old, $new);
			$message = 'Badge type updated. ' . $moved . ' tag mapping(s) followed the rename.';
		} else {
			$message = 'Badge type already exists or not found.';
		}
	}
	?>
	<div class="wrap">
		<h2>Badge Types</h2>

		<?php onlinesched_render_tag_association_panel($badge_types); ?>

        
        <script>
            // Hardcoded to prevent cache issues
            function showEditFormOS(badge_slug) {
                document.querySelectorAll('.badge-edit-row').forEach(function(row) {
                    row.style.display = 'none';
                });
                document.querySelectorAll('.badge-types-table tr.main-row').forEach(function(row) {
                    row.classList.remove('editing');
                });
                var editRow = document.getElementById('badge-edit-row-' + badge_slug);
                if (editRow) {
                    editRow.style.display = 'table-row';
                    var mainRow = document.getElementById('badge-row-' + badge_slug);
                    if (mainRow) mainRow.classList.add('editing');
                }
            }
            function hideEditFormOS(badge_slug) {
                var editRow = document.getElementById('badge-edit-row-' + badge_slug);
                if (editRow) {
                    editRow.style.display = 'none';
                    var mainRow = document.getElementById('badge-row-' + badge_slug);
                    if (mainRow) mainRow.classList.remove('editing');
                }
            }
        </script>

		<form method="post" style="margin-bottom:1em;">
			<!-- Removed global icon usage toggle -->
		</form>
		<?php if ($message) {
			$class = (strpos($message, 'error') !== false || strpos($message, 'exists') !== false) ? 'upload-error' : 'schedule-updated';
			echo '<div class="' . $class . '"><button class="close-message" onclick="closeMessageBox(this)">&times;</button><p>' . esc_html($message) . '</p></div>';
		} ?>
		<!-- Add Badge Type Button -->
		<div style="margin-bottom:18px;">
			<button type="button" id="show-add-badge-type-btn" class="button button-primary" style="font-size:16px; padding:8px 18px; border-radius:4px;">+ Add Badge Type</button>
		</div>
		<!-- Add Badge Type Form (hidden by default, fallback to visible if no JS) -->
		<div class="badge-add-form-card" id="badge-add-form-card" style="display:none; margin-bottom:2em; border:2px solid #e5e5e5; background:#f7fbff; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:18px 24px; border-radius:8px; max-width:900px;">
			<h3 style="margin-top:0; margin-bottom:18px; font-weight:600; color:#1890ff;">Add New Badge Type</h3>
		<form method="post" id="add-badge-type-form" aria-label="Add New Badge Type">
            <?php wp_nonce_field('onlinesched_badge_types'); ?>
				<input type="hidden" name="badge_action" value="add">
				<div style="display:flex; flex-wrap:wrap; gap:18px; align-items:center;">
					<label for="badge_type_name" style="min-width:180px;">Name:<br>
						<input type="text" name="badge_type_name" id="badge_type_name" placeholder="New badge type" required aria-required="true" aria-label="Badge Type Name">
						<span class="help-tip" title="Enter a unique name for this badge type."><i class="fa fa-info-circle"></i></span>
					</label>
					<input type="hidden" name="badge_type_show_badge" value="0">
					<label for="badge_type_show_badge" style="min-width:180px;"><input type="checkbox" name="badge_type_show_badge" id="badge_type_show_badge" value="1" checked aria-label="Show badge visually?"> Show badge visually?
						<span class="help-tip" title="If checked, this badge type will be shown visually in the schedule."><i class="fa fa-info-circle"></i></span>
					</label>
					<label for="badge_type_icon" style="min-width:180px;">Font Awesome Icon:<br>
						<input type="text" name="badge_type_icon" id="badge_type_icon" placeholder="Font Awesome icon class (e.g. fa-solid fa-star)" style="width:140px;" aria-label="Font Awesome Icon" />
						<span class="help-tip" title="Enter a Font Awesome icon class, e.g. fa-solid fa-star. Leave blank for no icon."><i class="fa fa-info-circle"></i></span>
					</label>
					<label style="min-width:180px;">Badge Background Color:<br>
						<input type="color" name="badge_type_color" id="badge_type_color" value="#b5d8ac" style="width:40px;" title="Default: #b5d8ac (Essentials)" aria-label="Badge Background Color" />
						<button type="button" class="badge-action-btn" onclick="document.getElementById('badge_type_color').value='#b5d8ac'; document.getElementById('badge_type_color_transparent').checked=false; document.getElementById('badge_type_color').disabled=false;">Clear</button>
						<label style="margin-left:10px;"><input type="checkbox" id="badge_type_color_transparent" name="badge_type_color_transparent" value="1" aria-label="Transparent background"> Transparent</label>
						<span class="help-tip" title="Pick a background color for the badge. Or check 'Transparent' for no background."><i class="fa fa-info-circle"></i></span>
					</label>
					<label style="min-width:180px;">Badge Text/Icon Color:<br>
						<input type="color" name="badge_type_fg_color" id="badge_type_fg_color" value="#333333" style="width:40px;" title="Default: #333333 (Text)" aria-label="Badge Text/Icon Color" disabled />
						<button type="button" class="badge-action-btn" onclick="document.getElementById('badge_type_fg_color').value='#333333'; document.getElementById('badge_type_fg_color_default').checked=true; document.getElementById('badge_type_fg_color').disabled=true;">Clear</button>
						<label style="margin-left:10px;"><input type="checkbox" id="badge_type_fg_color_default" name="badge_type_fg_color_default" value="1" checked aria-label="Use default text/icon color"> Use default color</label>
						<span class="help-tip" title="Pick a color for badge text/icon, or check 'Use default color' to use the theme default."><i class="fa fa-info-circle"></i></span>
					</label>
					<label style="min-width:180px;">Row Highlight Color:<br>
						<input type="color" name="badge_type_row_color" id="badge_type_row_color" value="#ffffff" style="width:40px;" title="Row background color for schedule highlight" disabled aria-label="Row Highlight Color" />
						<button type="button" class="badge-action-btn" onclick="document.getElementById('badge_type_row_color').value='#ffffff'; document.getElementById('badge_type_row_color_enable').checked=false; document.getElementById('badge_type_row_color').disabled=true;">Clear</button>
						<label style="margin-left:10px;"><input type="checkbox" id="badge_type_row_color_enable" name="badge_type_row_color_enable" value="1" aria-label="Enable row highlight"> Enable Row Highlight</label>
						<span class="help-tip" title="Pick a color to highlight schedule rows for this badge type. Optional."><i class="fa fa-info-circle"></i></span>
					</label>
				</div>
				<div style="margin-top:18px; display:flex; gap:12px;">
					<button type="submit" class="button button-primary" style="font-size:16px; padding:8px 24px;">Add Badge Type</button>
					<button type="button" class="badge-action-btn" id="cancel-add-badge-type-btn">Cancel</button>
				</div>
				<script>
				// When transparent is checked, disable color picker
				document.getElementById('badge_type_color_transparent').onchange = function() {
					document.getElementById('badge_type_color').disabled = this.checked;
				};
				document.getElementById('badge_type_row_color_enable').onchange = function() {
					document.getElementById('badge_type_row_color').disabled = !this.checked;
				};
				document.getElementById('badge_type_fg_color_default').onchange = function() {
					document.getElementById('badge_type_fg_color').disabled = this.checked;
					if (this.checked) {
						document.getElementById('badge_type_fg_color').value = '#333333';
					}
				};
				document.getElementById('badge_type_fg_color').oninput = function() {
					document.getElementById('badge_type_fg_color_default').checked = false;
					this.disabled = false;
				};
				</script>
			</form>
		</div>
		<h3>Existing Badge Types</h3>
		<table class="badge-types-table widefat">
			<thead>
				<tr>
					<th>Name</th>
					<th>Show Badge?</th>
					<th>Font Awesome Icon</th>
					<th>Badge Background Color</th>
					<th>Badge Text/Icon Color</th>
					<th>Row Highlight Color</th>
					<th class="actions">Actions</th>
				</tr>
			</thead>
			<tbody>
<?php foreach ($badge_types as $badge) : 
    $badge_slug = sanitize_title_with_dashes($badge);
?>
<tr id="badge-row-<?php echo esc_attr($badge_slug); ?>" class="main-row">
    <td><?php echo esc_html($badge); ?></td>
    <td><?php echo (!empty($badge_types_display[$badge])) ? 'Yes' : 'No'; ?></td>
    <td><?php echo isset($badge_types_icons[$badge]) && $badge_types_icons[$badge] ? '<i class="' . esc_attr($badge_types_icons[$badge]) . '"></i> ' . esc_html($badge_types_icons[$badge]) : ''; ?></td>
    <td>
        <?php
        $bg = isset($badge_types_colors[$badge]) ? $badge_types_colors[$badge] : '';
        if ($bg === 'transparent') {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;background: repeating-linear-gradient(45deg,#eee,#eee 5px,#ccc 5px,#ccc 10px);vertical-align:middle;margin-right:6px;" title="Transparent"></span>';
            echo '<span style="color:#888;">transparent</span>';
        } elseif ($bg) {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;background:' . esc_attr($bg) . ';vertical-align:middle;margin-right:6px;" title="' . esc_attr($bg) . '"></span>';
            echo esc_html($bg);
        } else {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #eee;background:#fff;vertical-align:middle;margin-right:6px;" title="No color"></span>';
            echo '<span style="color:#888;">none</span>';
        }
        ?>
    </td>
    <td>
        <?php
        $fg = isset($badge_types_fg_colors[$badge]) ? $badge_types_fg_colors[$badge] : '';
        if ($fg) {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;background:#fff;color:' . esc_attr($fg) . ';vertical-align:middle;margin-right:6px;text-align:center;line-height:20px;font-weight:bold;" title="' . esc_attr($fg) . '">A</span>';
            echo esc_html($fg);
        } else {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #eee;background:#fff;vertical-align:middle;margin-right:6px;" title="Default color"></span>';
            echo '<span style="color:#888;">default</span>';
        }
        ?>
    </td>
    <td>
        <?php
        $row = isset($badge_types_row_colors[$badge]) ? $badge_types_row_colors[$badge] : '';
        if ($row) {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #ccc;background:' . esc_attr($row) . ';vertical-align:middle;margin-right:6px;" title="' . esc_attr($row) . '"></span>';
            echo esc_html($row);
        } else {
            echo '<span style="display:inline-block;width:20px;height:20px;border:1px solid #eee;background:#fff;vertical-align:middle;margin-right:6px;" title="No color"></span>';
            echo '<span style="color:#888;">none</span>';
        }
        ?>
    </td>
    <td class="actions">
        <button type="button" class="badge-action-btn edit" title="Edit Badge Type" aria-label="Edit <?php echo esc_attr($badge); ?>" onclick="showEditFormOS('<?php echo esc_js($badge_slug); ?>')"><i class="fa fa-edit"></i> Edit</button>
        <form method="post" id="badge-delete-form-<?php echo esc_attr($badge_slug); ?>" style="display:inline;">
            <?php wp_nonce_field('onlinesched_badge_types'); ?>
            <input type="hidden" name="badge_action" value="delete">
            <input type="hidden" name="badge_type_delete" value="<?php echo esc_attr($badge); ?>">
            <button type="button" class="badge-action-btn delete" title="Delete Badge Type" aria-label="Delete <?php echo esc_attr($badge); ?>" onclick="confirmDelete('<?php echo esc_js($badge_slug); ?>')"><i class="fa fa-trash"></i> Delete</button>
        </form>
    </td>
</tr>
<tr id="badge-edit-row-<?php echo esc_attr($badge_slug); ?>" class="badge-edit-row" style="display:none;">
    <td colspan="7" style="padding:0;">
        <form method="post" class="badge-edit-form active" id="badge-edit-form-<?php echo esc_attr($badge_slug); ?>" style="margin:10px; border:2px solid #e5e5e5; background:#f7fbff; box-shadow:0 2px 8px rgba(0,0,0,0.04); padding:18px 24px; border-radius:8px;">
            <?php wp_nonce_field('onlinesched_badge_types'); ?>
            <h4 style="margin-top:0; margin-bottom:18px; font-weight:600; color:#1890ff;">Edit Badge Type: <?php echo esc_html($badge); ?></h4>
            <input type="hidden" name="badge_action" value="edit">
            <input type="hidden" name="badge_type_edit_old" value="<?php echo esc_attr($badge); ?>">
            <div style="display:flex; flex-wrap:wrap; gap:18px; align-items:center;">
            <label for="badge_type_edit_new_<?php echo esc_attr($badge_slug); ?>" style="min-width:180px;">Name:<br>
                <input type="text" name="badge_type_edit_new" id="badge_type_edit_new_<?php echo esc_attr($badge_slug); ?>" value="<?php echo esc_attr($badge); ?>" required>
            </label>
            <input type="hidden" name="badge_type_show_badge_edit" value="0">
            <label for="badge_type_show_badge_edit_<?php echo esc_attr($badge_slug); ?>" style="min-width:180px;"><input type="checkbox" name="badge_type_show_badge_edit" id="badge_type_show_badge_edit_<?php echo esc_attr($badge_slug); ?>" value="1" <?php echo (!empty($badge_types_display[$badge])) ? 'checked' : ''; ?>> Show badge visually?</label>
            <label for="badge_type_icon_edit_<?php echo esc_attr($badge_slug); ?>" style="min-width:180px;">Icon:<br>
                <input type="text" name="badge_type_icon_edit" id="badge_type_icon_edit_<?php echo esc_attr($badge_slug); ?>" value="<?php echo isset($badge_types_icons[$badge]) ? esc_attr($badge_types_icons[$badge]) : ''; ?>" placeholder="Font Awesome icon class (e.g. fa-solid fa-star)" style="width:140px;" />
            </label>
            <label for="badge_type_color_edit_<?php echo esc_attr($badge_slug); ?>" style="min-width:180px;">Badge Background Color:<br>
                <input type="color" name="badge_type_color_edit" id="badge_type_color_edit_<?php echo esc_attr($badge_slug); ?>" value="<?php echo (isset($badge_types_colors[$badge]) && $badge_types_colors[$badge] && $badge_types_colors[$badge] !== 'transparent') ? esc_attr($badge_types_colors[$badge]) : '#b5d8ac'; ?>" style="width:40px;" title="Default: #b5d8ac (Essentials)" <?php echo (isset($badge_types_colors[$badge]) && $badge_types_colors[$badge] === 'transparent') ? 'disabled' : ''; ?> />
                <button type="button" onclick="document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge_slug); ?>').value='#b5d8ac'; document.getElementById('badge_type_color_transparent_<?php echo esc_attr($badge_slug); ?>').checked=false; document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge_slug); ?>').disabled=false;">Clear</button>
                <label style="margin-left:10px;"><input type="checkbox" id="badge_type_color_transparent_<?php echo esc_attr($badge_slug); ?>" name="badge_type_color_transparent" value="1" <?php echo (isset($badge_types_colors[$badge]) && $badge_types_colors[$badge] === 'transparent') ? 'checked' : ''; ?>> Transparent</label>
            </label>
            <label for="badge_type_fg_color_edit_<?php echo esc_attr($badge_slug); ?>" style="min-width:180px;">Text/Icon Color:<br>
                <input type="color" name="badge_type_fg_color_edit" id="badge_type_fg_color_edit_<?php echo esc_attr($badge_slug); ?>" value="<?php echo isset($badge_types_fg_colors[$badge]) && $badge_types_fg_colors[$badge] ? esc_attr($badge_types_fg_colors[$badge]) : '#333333'; ?>" style="width:40px;" title="Default: #333333 (Text)" <?php echo (empty($badge_types_fg_colors[$badge])) ? 'disabled' : ''; ?> />
                <button type="button" onclick="document.getElementById('badge_type_fg_color_edit_<?php echo esc_attr($badge_slug); ?>').value='#333333'; document.getElementById('badge_type_fg_color_default_edit_<?php echo esc_attr($badge_slug); ?>').checked=true; document.getElementById('badge_type_fg_color_edit_<?php echo esc_attr($badge_slug); ?>').disabled=true;">Clear</button>
                <label style="margin-left:10px;"><input type="checkbox" id="badge_type_fg_color_default_edit_<?php echo esc_attr($badge_slug); ?>" name="badge_type_fg_color_default_edit" value="1" <?php echo (empty($badge_types_fg_colors[$badge])) ? 'checked' : ''; ?>> Use default color</label>
            </label>
            <label for="badge_type_row_color_edit_<?php echo esc_attr($badge_slug); ?>" style="min-width:180px;">Row Highlight Color:<br>
                <input type="color" name="badge_type_row_color_edit" id="badge_type_row_color_edit_<?php echo esc_attr($badge_slug); ?>" value="<?php echo isset($badge_types_row_colors[$badge]) && $badge_types_row_colors[$badge] ? esc_attr($badge_types_row_colors[$badge]) : '#ffffff'; ?>" style="width:40px;" title="Row background color for schedule highlight" <?php echo (empty($badge_types_row_colors[$badge])) ? 'disabled' : ''; ?> />
                <label style="margin-left:10px;"><input type="checkbox" id="badge_type_row_color_enable_<?php echo esc_attr($badge_slug); ?>" name="badge_type_row_color_enable_edit" value="1" <?php echo (!empty($badge_types_row_colors[$badge])) ? 'checked' : ''; ?>> Enable Row Highlight</label>
                <button type="button" onclick="document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge_slug); ?>').value='#ffffff'; document.getElementById('badge_type_row_color_enable_<?php echo esc_attr($badge_slug); ?>').checked=false; document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge_slug); ?>').disabled=true;">Clear</button>
                <span style="font-size:12px;color:#666;">(Optional: color for schedule row highlight)</span>
            </label>
            </div>
            <div style="margin-top:18px; display:flex; gap:12px;">
            <?php submit_button('Save', 'primary', 'edit_badge_type', false); ?>
            <button type="button" class="badge-action-btn" onclick="hideEditFormOS('<?php echo esc_js($badge_slug); ?>')">Cancel</button>
            </div>
            <script>
                (function() {
                    var picker = document.getElementById('badge_type_fg_color_edit_<?php echo esc_attr($badge_slug); ?>');
                    var defaultCheckbox = document.getElementById('badge_type_fg_color_default_edit_<?php echo esc_attr($badge_slug); ?>');
                    var colorPickerEdit = document.getElementById('badge_type_color_edit_<?php echo esc_attr($badge_slug); ?>');
                    var colorTransparent = document.getElementById('badge_type_color_transparent_<?php echo esc_attr($badge_slug); ?>');
                    var rowColorPicker = document.getElementById('badge_type_row_color_edit_<?php echo esc_attr($badge_slug); ?>');
                    var rowColorEnable = document.getElementById('badge_type_row_color_enable_<?php echo esc_attr($badge_slug); ?>');

                    defaultCheckbox.onchange = function() {
                        picker.disabled = this.checked;
                        if (this.checked) picker.value = '#333333';
                    };
                    picker.oninput = function() {
                        defaultCheckbox.checked = false;
                        picker.disabled = false;
                    };

                    colorTransparent.onchange = function() {
                        colorPickerEdit.disabled = this.checked;
                    };

                    rowColorEnable.onchange = function() {
                        rowColorPicker.disabled = !this.checked;
                    };
                })();
            </script>
        </form>
    </td>
</tr>
<?php endforeach; ?>
			</tbody>
		</table>
	<div style="margin-top:32px; display:flex; gap:24px;">
		<form method="post" style="margin-bottom:1em;">
            <?php wp_nonce_field('onlinesched_badge_types'); ?>
			<input type="hidden" name="badge_action" value="restore_defaults">
			<?php submit_button('Restore Defaults', 'secondary', 'restore_defaults_badge_type', false, ['onclick' => 'return confirm("Are you sure you want to restore default badge types? This will overwrite settings for default badges and preserve custom ones.");']); ?>
		</form>
		<form method="post" style="margin-bottom:1em;">
			<button type="button" id="assign-default-badge-types" class="button">Assign Default Badge Types to Tags</button>
		</form>
	</div>
	<div id="assign-badge-types-message" style="display:none;"></div>
</div>
	<?php
}

// Add badge type field to ADD tag screen
add_action('os_tag_add_form_fields', function() {
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
add_action('os_tag_edit_form_fields', function($term) {
    $badge_types = get_option('onlinesched_badge_types', array());
    if (!empty($badge_types)) {
        natcasesort($badge_types);
    }
    $selected = onlinesched_badge_type_for_tag($term->slug, $term->term_id);
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
add_action('created_os_tag', function($term_id) {
    $term = get_term($term_id, 'os_tag');
    if (!$term || is_wp_error($term)) {
        return;
    }
    $badge_type = isset($_POST['badge_type']) ? sanitize_text_field($_POST['badge_type']) : '';
    if ('' !== $badge_type) {
        onlinesched_set_tag_badge_type($term->slug, $badge_type);
        return;
    }
    // A recreated tag keeps whatever the map already holds for its slug, which
    // is how a re-imported spelling heals without anyone retyping it.
    $map = onlinesched_get_tag_badge_map();
    if (isset($map[$term->slug])) {
        onlinesched_set_tag_badge_type($term->slug, $map[$term->slug]);
    }
}, 10, 1);

// Save badge type on tag EDIT
add_action('edited_os_tag', function($term_id) {
    if (!isset($_POST['badge_type'])) {
        return;
    }
    $term = get_term($term_id, 'os_tag');
    if (!$term || is_wp_error($term)) {
        return;
    }
    $badge_type = sanitize_text_field($_POST['badge_type']);
    // An empty choice is None, not "leave it alone". Without this the screen
    // offers None and nothing happens.
    onlinesched_set_tag_badge_type(
        $term->slug,
        '' === $badge_type ? ONLINESCHED_BADGE_NONE : $badge_type
    );
}, 10, 1);

function onlinesched_assign_default_badge_types_ajax() {
    check_ajax_referer('onlinesched_badge_types', 'nonce');

    if (!current_user_can('manage_os_tag')) {
        wp_send_json_error('Permission denied');
    }
    wp_send_json_success([ 'updated' => onlinesched_fill_default_badge_types() ]);
}

/**
 * Maps every unmapped tag whose slug has a built-in default.
 *
 * @return int Number of tags mapped.
 */
function onlinesched_fill_default_badge_types() {
    $tags = get_terms([
        'taxonomy' => 'os_tag',
        'hide_empty' => false,
    ]);
    if (is_wp_error($tags)) {
        return 0;
    }

    $map = onlinesched_get_tag_badge_map();
    $updated = 0;
    foreach ($tags as $tag) {
        // A slug the map already answers for was decided by staff, and that
        // includes an explicit None. Filling defaults must not overrule it.
        if (isset($map[$tag->slug])) {
            continue;
        }
        $default_badge_type = onlinesched_default_badge_type_for_tag_slug($tag->slug);
        if ('' === $default_badge_type) {
            continue;
        }
        onlinesched_set_tag_badge_type($tag->slug, $default_badge_type);
        $updated++;
    }

    return $updated;
}
add_action('wp_ajax_onlinesched_assign_default_badge_types', 'onlinesched_assign_default_badge_types_ajax');

/**
 * The association screen: badge types down the page, each holding the schedule
 * tags assigned to it. Names are shown because staff recognise names; slugs are
 * what gets stored.
 *
 * @param string[] $badge_types Configured badge type names.
 * @return void
 */
function onlinesched_render_tag_association_panel($badge_types) {
	$rows = onlinesched_tag_badge_rows();
	$by_type = array();
	$unassigned = array();
	foreach ($rows as $row) {
		if ('' === $row['type']) {
			$unassigned[] = $row;
			continue;
		}
		$by_type[$row['type']][] = $row;
	}
	?>
	<div class="card" style="max-width:none; padding:12px 16px; margin-bottom:20px;">
		<h3 style="margin-top:0;">Tags in each badge type</h3>
		<p class="description">
			One badge type holds many tags; a tag belongs to at most one type.
			Hold command or control to select several. A tag left out of every
			list is unassigned and shows below.
		</p>
		<form method="post">
			<?php wp_nonce_field('onlinesched_badge_types'); ?>
			<input type="hidden" name="badge_action" value="save_tag_map" />
			<table class="widefat striped">
				<tbody>
				<?php foreach ($badge_types as $type) : ?>
					<tr>
						<th scope="row" style="width:180px; vertical-align:top;">
							<?php echo esc_html($type); ?>
						</th>
						<td>
							<?php
							// This type's tags plus the unclaimed ones. The whole
							// catalogue made every box look like it held every tag.
							$choices = array_values(array_filter(
								$rows,
								static function ($row) use ($type) {
									return $row['type'] === $type || '' === $row['type'];
								}
							));
							$mine = count(array_filter(
								$choices,
								static function ($row) use ($type) {
									return $row['type'] === $type;
								}
							));
							?>
							<select name="badge_tags[<?php echo esc_attr($type); ?>][]" multiple size="8" style="min-width:320px;">
								<?php foreach ($choices as $row) : ?>
									<option value="<?php echo esc_attr($row['slug']); ?>"
										<?php selected($row['type'], $type); ?>>
										<?php echo esc_html($row['name']); ?>
										<?php echo $row['missing'] ? ' (missing)' : ''; ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php echo (int) $mine; ?> assigned. The rest of this list is
								unassigned tags you can add.
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="submit" class="button button-primary">Save tag associations</button></p>
		</form>

		<h4>Unassigned tags</h4>
		<?php if (empty($unassigned)) : ?>
			<p class="description">Every tag carries a badge type.</p>
		<?php else : ?>
			<p class="description">
				A newly imported spelling lands here. That is the signal to map it,
				not something to infer.
			</p>
			<ul style="margin-left:1em;">
				<?php foreach ($unassigned as $row) : ?>
					<li>
						<?php echo esc_html($row['name']); ?>
						<code><?php echo esc_html($row['slug']); ?></code>
						<?php echo $row['missing'] ? '<em>(mapping kept, tag missing)</em>' : ''; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
