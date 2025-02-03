<?php

// Remove onesignal on homepage and product pages
function onesignal_initialize_sdk_filter($onesignal_settings)
{
    if (is_page('kiosk-schedule')) {
        return false;
    } else {
        return true;
    }
}

add_filter('onesignal_initialize_sdk', 'onesignal_initialize_sdk_filter', 10, 1);