<!doctype html>
<html class="no-js" <?php language_attributes(); ?>>
<head>

<meta charset="<?php bloginfo('charset'); ?>" />
<meta name="viewport" content="width=device-width, user-scalable=no" />
<?php
$kiosk_head_styles = apply_filters('os_kiosk_head_styles', array());
if (!is_array($kiosk_head_styles)) {
	$kiosk_head_styles = array($kiosk_head_styles);
}

foreach ($kiosk_head_styles as $style_url) {
	if (!$style_url) {
		continue;
	}
	echo '<link rel="stylesheet" href="' . esc_url($style_url) . '">' . "\n";
}
?>
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />

<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div id="body">
