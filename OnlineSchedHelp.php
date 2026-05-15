<?php

function os_event_help_page()
{
	remove_filter('parse_query', 'OnlineSched_posts_filter');
	?>
    <p class="wrap">
    <h2>Special Tags</h2>
    <p>These tags will do effects and highlight their text. As of this time they are hard coded. This should be an
        option in site.
    </p>
    <ul style="list-style-type: disc; padding-left: 40px;">
        <li><strong>Streaming</strong> - Marks it with a tag.</li>
        <li><strong>Essentials</strong> - Marks column with a distinctive color and bolds tag.</li>
        <li><strong>canceled</strong> - <em><strong>Highly recommended to use</strong></em> will signal to calendars it's cancelled. It crosses out the text on the screen.</li>
        <li><strong>guest of honor</strong> - Highlights row</li>
        <li><strong>special guest</strong> - Highlights row</li>
        <li><strong>restricted</strong> - Puts Adult tag next to the title.</li>
        <li><strong>sensory</strong> - Puts Sensory tag next to the title.</li>
        <li><strong>sensitivity</strong> - Marks with a sensory tag on the title.</li>
    </ul>
    <h2>Essentials Tab</h2>
    <p>The tab "essentials" is right now hard coded. This should be an option in the future. With the tab selected it
        will filter out the other items. The following tabs will be put on the tab.</p>
    <ul style="list-style-type: disc; padding-left: 40px;">
        <li><strong>Guest Of Honor</strong></li>
        <li><strong>Special Guest</strong></li>
        <li><strong>VIP</strong></li>
        <li><strong>Essentials</strong></li>
    </ul>
    <h2>Special Feeds</h2>
    <ul>
        <li>Streaming tab will put it on the live streaming page for FM.</li>
    </ul>
    <h2>Year Setting</h2>
    <p>This setting in settings page sets which year is the app running in. This will hide other years in the UI. So it might list 240 items in total, while you can only see 115 because only 115 of them are in the currently set year.</p>

	<?php
}