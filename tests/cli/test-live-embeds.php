<?php

if (!defined('ABSPATH')) {
    exit(1);
}

$assert = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$sources = array(
    'https://player.twitch.tv/?channel=furrymigration&parent=furrymigration.local',
    'https://www.twitch.tv/embed/furrymigration/chat?parent=furrymigration.local',
    'https://player.twitch.tv/?channel=furrymigration&parent=www.furrymigration.org',
);
foreach ($sources as $src) {
    $html = '<div class="twitch-container"><iframe src="' . esc_attr($src)
        . '" class="twitch-responsive-iframe" title="Livestream" frameborder="0"'
        . ' allowfullscreen="true" scrolling="no"></iframe></div>';
    $assert(onlinesched_live_content_html($html) === $html, 'Twitch embed attributes must survive.');
    $assert(strpos(wp_kses_post($html), '<iframe') === false, 'Ordinary post filtering must remain unchanged.');
}

foreach (array(
    'javascript:alert(1)',
    'data:text/html,test',
    'http://player.twitch.tv/',
    '//player.twitch.tv/',
    'https://player.twitch.tv.evil.example/',
    'https://player.twitch.tv@evil.example/',
    'https://evil.example/',
    'https://player.twitch.tv:444/',
    'https://player.twitch.tv/other',
    'https://www.twitch.tv/other',
    'https://player.twitch.tv\\@evil.example/',
) as $src) {
    $html = onlinesched_live_content_html('<iframe src="' . esc_attr($src) . '"></iframe>');
    $assert(strpos($html, '<iframe') === false, 'Unapproved iframe source survived: ' . $src);
}

$html = onlinesched_live_content_html(
    '<iframe src="https://evil.example/" class="twitch-responsive-iframe" width="640"></iframe>'
);
$assert($html === '', 'A rejected source must remove the frame even when allowed attributes remain.');

$html = onlinesched_live_content_html(
    '<p>Watch live</p><script>alert(1)</script><iframe src="' . esc_attr($sources[0])
    . '" srcdoc="unsafe" onload="unsafe" allow="camera; microphone"></iframe>'
);
$assert(strpos($html, '<p>Watch live</p>') !== false, 'Normal content must survive.');
foreach (array('<script', 'srcdoc=', 'onload=', 'allow=') as $unsafe) {
    $assert(strpos($html, $unsafe) === false, 'Unsafe markup survived: ' . $unsafe);
}
$assert(strpos(onlinesched_live_content_html('<iframe srcdoc="unsafe"></iframe>'), '<iframe') === false, 'A source is required.');
echo "OnlineSched live embed tests passed.\n";
