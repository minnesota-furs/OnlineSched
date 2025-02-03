import 'scss/schedule.scss';


import { onlineScheduleGrid } from "js/onlineScheduleGrid.js";
import { new_schedule} from "js/new_schedule.js";

jQuery(document).ready(function () {
    onlineScheduleGrid();
    new_schedule();
});
