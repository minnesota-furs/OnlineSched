import 'scss/schedule.scss';
import 'scss/shortcode_schedule_cheat_display.scss';
import 'scss/android-google-model.scss';

import { onlineScheduleGrid } from "js/onlineScheduleGrid.js";
import { new_schedule} from "js/new_schedule.js";
import {scheduleFavorites} from "./js/scheduleFavorites";
import {loginHelpers} from "./js/loginHelpers";
import { scheduleCalendar } from "./js/scheduleCalendar";

// Initialize everything on document ready

jQuery(document).ready(function () {
    onlineScheduleGrid();
    new_schedule();
    scheduleFavorites();
    loginHelpers();
    scheduleCalendar();
});
