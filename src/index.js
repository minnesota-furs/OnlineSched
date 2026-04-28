import 'scss/schedule.scss';
import 'scss/shortcode_schedule_cheat_display.scss';
import 'scss/android-google-model.scss';

import { new_schedule} from "js/new_schedule.js";
import {scheduleFavorites} from "./js/scheduleFavorites";
import {loginHelpers} from "./js/loginHelpers";
import { scheduleCalendar } from "./js/scheduleCalendar";
import { initTabs } from "./js/osTabs";
import { initModal } from "./js/osModal";

// Initialize everything on document ready

jQuery(document).ready(function () {
    ['login-modal', 'modal-schedule', 'info-modal', 'android-google-calendar-modal'].forEach(initModal);
    initTabs();
    new_schedule();
    scheduleFavorites();
    loginHelpers();
    scheduleCalendar();
});
