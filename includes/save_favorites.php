<?php
/**
 * Backward-compatible direct favorites save endpoint.
 *
 * @package    OnlineSched
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

onlinesched_direct_save_favorites();
