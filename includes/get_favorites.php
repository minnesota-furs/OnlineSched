<?php
/**
 * Backward-compatible direct favorites read endpoint.
 *
 * @package    OnlineSched
 */

define('WP_USE_THEMES', false);
require_once dirname(__DIR__, 4) . '/wp-load.php';

onlinesched_direct_get_favorites();
