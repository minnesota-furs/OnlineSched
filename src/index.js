import 'scss/schedule.scss';


import { onlineScheduleGrid } from "js/onlineScheduleGrid.js";
import { new_schedule} from "js/new_schedule.js";
import {scheduleFavorites} from "./js/scheduleFavorites";

jQuery(document).ready(function () {
    onlineScheduleGrid();
    new_schedule();
    scheduleFavorites();
});
