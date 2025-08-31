<?php
// OnlineSchedSettings.php

// Admin settings for OnlineSched plugin

function OnlineSched_admin_init()
{
	add_meta_box(
		'OnlineSched_timeslot',
		'Event Information',
		'OnlineSched_timeslot_metabox',
		'event_schedule',
		'normal',
		'high');
	add_option('event_schedule_year', 'Event Schedule Year');
	register_setting('event_schedule_option_group', 'event_schedule_year', 'OnlineSched_callback');
}

function OnlineSched_register_options_page()
{
    add_submenu_page(
        'edit.php?post_type=event_schedule',
        'Event Schedule Settings',
        'Event Settings',
        'edit_onlinesched_event_schedules',
        'onlinesched-settings',
        'OnlineSched_options_page'
    );
}

function OnlineSched_options_page()
{
    ?>
    <div>
        <h2>Event Schedule Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('event_schedule_option_group'); ?>
            <table>
                <tr>
                    <th scope="row"><label for="event_schedule_year">Event Schedule Year</label></th>
                    <td><input type="text" id="event_schedule_year" name="event_schedule_year"
                               value="<?php echo esc_attr(get_option('event_schedule_year')); ?>"/></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
