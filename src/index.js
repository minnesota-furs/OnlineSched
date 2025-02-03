import '../schedule-support/schedule.scss';


import { onlineScheduleGrid } from "js/onlineScheduleGrid.js";
import { new_schedule} from "../schedule-support/new_schedule.js";

jQuery(document).ready(function () {
    onlineScheduleGrid();
    new_schedule();
});
