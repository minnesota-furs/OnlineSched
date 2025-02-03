
jQuery("a[data-target=#modal-schedule]").click(function (ev) {
    ev.preventDefault();

    var parent = jQuery(this).parents('.schedule-item');
    var panelists = undefined;
    if (parent.find('.schedule-panelists').length != 0) {
        panelists = parent.find('.schedule-panelists').html();
    }


    var title = jQuery(this).html();
    var description = parent.find('.schedule-description').html();
    var date = parent.parent().siblings('h2').html();
    var time = parent.find('.schedule-time span').html();
    var room = parent.find('.schedule-room').html();
    var tags = parent.find('.schedule-tags').html();
    var ical = parent.find('.schedule-ical').attr('href');
    var googleCal = parent.find('.schedule-google').attr('href');


    jQuery("#modal-schedule-title").html(title);
    if (description.length >= 4) {
        jQuery("#modal-schedule-description").show().html(description).siblings('hr').show();
    } else {
        jQuery("#modal-schedule-description").hide().siblings('hr').hide();
    }
    modal_popup_fill("#modal-schedule-date", date);
    modal_popup_fill("#modal-schedule-time", time);
    modal_popup_fill("#modal-schedule-room", room);
    modal_popup_fill("#modal-schedule-tags", tags);
    modal_popup_fill("#modal-schedule-panelists", panelists);

    jQuery("#modal-schedule-ical").attr('href', ical);
    jQuery("#modal-schedule-google").attr('href', googleCal);

    jQuery("#modal-schedule").modal("show");
});

function modal_popup_fill(id, value) {

    if (value === undefined) {
        jQuery(id).prev().hide();
        jQuery(id).hide();
    } else {
        jQuery(id).html(value).show();
        jQuery(id).prev().show();

    }
}

jQuery(".schedule-day").each(function (index) {
    var day = jQuery(this).data("scheduleDay");
    jQuery("#schedule-select-days").append("<option value='" + day + "'>" + day + "</option>");

});

// Set events
var showEvents = true;

function setFilterEvents(args) {
    scrollTopMenu();

    showEvents = args;

    resetDropDowns();
    scheduleSort();
    resetSelectTags();
}

// fill tag drop down mind you check if it's unique too
var scheduleTags = new Object();
var count = 0;
jQuery(".schedule-tags").each(function (index) {
    // Current tags
    var tags = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
    for (index = 0, len = tags.length; index < len; ++index) {
        var tag = tags[index];
        if (!scheduleTags.hasOwnProperty(tag)) {
            scheduleTags[tag] = count++;
        }

        jQuery(this).parent().parent().attr("data-schedule-tag" + scheduleTags[tag], scheduleTags[tag]);
    }

});
for (var key in scheduleTags) {
    if (typeof key === 'string' && key.trim().length !=0 && typeof scheduleTags[key] === 'string' && scheduleTags[key].trim.length != 0)
    jQuery("#schedule-select-tags").append("<option value='" + scheduleTags[key] + "'>" + key + "</option>");
}


var scheduleRooms = new Object();
count = 0;
jQuery(".schedule-room").each(function (index) {
    // Current tags
    var rooms = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
    for (index = 0, len = rooms.length; index < len; ++index) {
        var room = rooms[index];
        if (!scheduleRooms.hasOwnProperty(room)) {
            scheduleRooms[room] = count++;
        }

        jQuery(this).parent().parent().attr("data-schedule-room" + scheduleRooms[room], scheduleRooms[room]);
    }

});

for (var key in scheduleRooms) {
    if (key != '' && scheduleRooms[key] != '') {
        jQuery("#schedule-select-rooms").append("<option value='" + scheduleRooms[key] + "'>" + key + "</option>");
    }
}

jQuery("#schedule-select-days, #schedule-select-tags, #schedule-select-rooms").change(function () {
    scheduleSort();
});
jQuery("#schedule-search-text").on('input', function () {
    scheduleSort();
});

jQuery("#schedule-reset").click(function () {
    resetDropDowns();

    scheduleSort();
    resetSelectTags();
});

function setOddEven() {
    jQuery('.schedule-item').removeClass('even');
    jQuery('.schedule-hour').each(function () {
        var even = false;
        jQuery(this).find('.schedule-item').each(function () {
            if (jQuery(this).is(":visible")) {

                if (even) {
                    //code
                    jQuery(this).addClass('even');
                    even = false;
                } else {
                    even = true;
                }
            }

        });
    });
    //code
}

function resetDropDowns() {
    jQuery('#schedule-select-days').val('Current');
    jQuery('#schedule-select-tags').val('all');
    jQuery('#schedule-select-rooms').val('all');
    jQuery('#schedule-search-text').val("");
}

function showHideEvents() {
    jQuery('#programming').addClass('active');
    if (showEvents) {
        jQuery('.schedule-room-tabletop,.schedule-item.schedule-room-tabletop-special-a,.schedule-room-tabletop-special-b,.schedule-item.schedule-room-video-gaming,.schedule-item.schedule-room-tabletop-a,.schedule-item.schedule-room-tabletop-b,.schedule-item.schedule-room-tabletop-c,.schedule-item.schedule-room-tabletop-d,.schedule-item.schedule-room-tabletop-e,.schedule-item.schedule-room-board-gaming').hide();
        /*			jQuery('#schedule-select-rooms option').show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop A"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop B"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop C"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop D"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop E"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop Special A"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop Special B"] +"]").hide();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Video Gaming"] +"]").hide();
        */
        jQuery('#schedule-select-rooms').parent().show();
        jQuery('.schedule-reset').removeClass('col-sm-offset-2');

    } else {
        jQuery('.schedule-item').not('.schedule-room-tabletop,.schedule-room-tabletop-special-a,.schedule-room-tabletop-special-b,.schedule-room-video-gaming,.schedule-room-tabletop-a,.schedule-room-tabletop-b,.schedule-room-tabletop-c,.schedule-room-tabletop-d,.schedule-room-tabletop-e,.schedule-item.schedule-room-board-gaming').hide();
        /*jQuery('#schedule-select-rooms option').hide()
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop A"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop B"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop C"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop D"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop E"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop Special A"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Tabletop Special B"] +"]").show();
        jQuery("#schedule-select-rooms option[value="+scheduleRooms["Video Gaming"] +"]").show();
        jQuery("#schedule-select-rooms option[value=all]").show();
        */
        jQuery('#schedule-select-rooms').parent().hide();
        jQuery('.schedule-reset').addClass('col-sm-offset-2');

    }

}

function scheduleSort() {
    var disableReset = true;

    //code
    var selectedDay = jQuery("#schedule-select-days").val();

    if (selectedDay == "all" || selectedDay == "Current") {
        //code
        jQuery(".schedule-day").show();
    } /* else if (selectedDay == "Current") {
                var currentGMTDay = currentDateTimestampUTC();
                jQuery(".schedule-day").hide();
                jQuery(".schedule-day").each(function (index) {
                    var itemUTC = jQuery(this).attr("data-schedule-num-day");
                    if (itemUTC >= currentGMTDay) {
                        jQuery(this).show();
                    }
                });
            }  */ else {
        jQuery(".schedule-day").hide();
        jQuery('.schedule-day[data-schedule-day="' + selectedDay + '"]').show();
        disableReset = false;
    }

    var searchData = "";

    var selectedTag = jQuery("#schedule-select-tags").val();
    if (selectedTag != "all") {
        searchData += "[data-schedule-Tag" + selectedTag + "='" + selectedTag + "']";
    }

    var selectedRoom = jQuery("#schedule-select-rooms").val();
    if (selectedRoom != "all") {
        searchData += "[data-schedule-room" + selectedRoom + "='" + selectedRoom + "']";
    }


    if (searchData == "") {
        jQuery('.schedule-item').show();
    } else {
        jQuery('.schedule-item').hide();
        jQuery('.schedule-item' + searchData).show();
        disableReset = false;
    }

    if (selectedDay == "Current") {
        // lets do the work
        var currentDateUTC = currentDateTimeTimestampUTC();
        jQuery('.schedule-item').each(function (index) {
            var itemDate = jQuery(this).data('end-time');
            if (itemDate <= currentDateUTC) {
                jQuery(this).hide();
            }
        });
    }


    jQuery('.schedule-hour').show(); // Otherwise can't search text
    // last check dynamic search
    var searchText = jQuery("#schedule-search-text").val().toLowerCase();
    if (searchText != "") {

        jQuery('.schedule-item:visible').each(function () {
            /* var findThis = '.schedule-title a:containsi("'+searchText+'")';
             var hide = true;
            jQuery(this).find(findThis).each(function(){
             hide = false;
             alert(jQuery(this).html())
             });
               if (hide) {
                 jQuery(this).hide();
               }
            */
            var hide = true;
            jQuery(this).find(".schedule-title a, .schedule-panelists, .schedule-tags, .schedule-room").each(function () {
                if (jQuery(this).text().toLowerCase().indexOf(searchText) != -1) {
                    hide = false;
                }
            });
            if (hide) {
                //code
                jQuery(this).hide();
            }

        });

        disableReset = false;
    }


    jQuery("#schedule-reset").prop("disabled", disableReset);
    // action when all are hidden

    reset_schedule(true);
}

function resetHoursDays() {

    // Clean up show/hides
    jQuery('.schedule-hour').each(function () {
        jQuery(this).show(); // You have to show them to calculate...
        var visibleLength = jQuery(this).children(':visible').length;

        if (visibleLength < 2) {
            jQuery(this).hide();
        }
    });

    // now for days
    jQuery('.schedule-day').each(function () {
        jQuery(this).show(); // You have to show them to calculate...
        var visibleLength = jQuery(this).children(':visible').length;

        if (visibleLength < 2) {
            jQuery(this).hide();
        }
    });
}


var tagOptions = null;

function reset_schedule(resetTags) {
    showHideEvents();
    resetHoursDays();
    if (resetTags) {
        resetSelectTags();
    }

    sort_options_by_id("#schedule-select-tags");
    sort_rooms_options_by_id("#schedule-select-rooms");
    setOddEven();
}

function resetSelectTags() {
    var scheduleReset = new Object();
    jQuery(".schedule-tags").each(function (index) {
        // check if
        var parent = jQuery(this).parent().parent();
        if (parent.is(":visible")) {
            //code
            // Current tags


            var tags = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
            for (index = 0, len = tags.length; index < len; ++index) {
                var tag = tags[index];
                if (!scheduleReset.hasOwnProperty(tag)) {
                    scheduleReset[tag] = tag;
                }
            }


        }
    });

    // get selected option
    var selectedTag = jQuery("#schedule-select-tags option:selected").text();

    jQuery("#schedule-select-tags").find("option").not(":first").remove();

    for (var key in scheduleReset) {
        jQuery("#schedule-select-tags").append(jQuery('<option>', {
            value: scheduleTags[key],
            html: key
        }));
    }

    // reset selected option
    if (scheduleReset.hasOwnProperty(selectedTag)) {
        jQuery("#schedule-select-tags").val(scheduleTags[selectedTag]);
    }

    sort_options_by_id("#schedule-select-tags");

    // Version for schedule rooms
    // could functionized this now

    var scheduleResetRooms = new Object();
    jQuery(".schedule-room").each(function (index) {
        // check if
        var parent = jQuery(this).parent().parent();
        if (parent.is(":visible")) {
            //code
            // Current tags

            var tags = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
            for (index = 0, len = tags.length; index < len; ++index) {
                var tag = tags[index];
                if (!scheduleResetRooms.hasOwnProperty(tag)) {
                    scheduleResetRooms[tag] = tag;
                }
            }


        }
    });

    // get selected option
    var selectedRooms = jQuery("#schedule-select-rooms option:selected").text();

    jQuery("#schedule-select-rooms").find("option").not(":first").remove();

    for (var key in scheduleResetRooms) {
        if (key != '' && (scheduleRooms[key] != '' || scheduleRooms[key] == 0)) {
            jQuery("#schedule-select-rooms").append(jQuery('<option>', {
                value: scheduleRooms[key],
                html: key
            }));
        }
    }

    // reset selected option
    if (scheduleResetRooms.hasOwnProperty(selectedRooms)) {
        jQuery("#schedule-select-rooms").val(scheduleRooms[selectedRooms]);
    }

    sort_options_by_id("#schedule-select-Rooms");

    setOddEven();
}

function sort_options_by_id(id) {

    var selected = jQuery(id).val();
    jQuery(id).html(jQuery(id + " option").sort(function (a, b) {

        var a_text = jQuery(a).text().toUpperCase();
        var b_text = jQuery(b).text().toUpperCase();
        var sortResult = a_text == b_text ? 0 : a_text < b_text ? -1 : 1;

        return sortResult;
    }));
    jQuery(id).val(selected);


}

function sort_rooms_options_by_id(id) {

    var selected = jQuery(id).val();
    jQuery(id).html(jQuery(id + " option").sort(function (a, b) {

        var a_text = jQuery(a).text().toUpperCase();
        var b_text = jQuery(b).text().toUpperCase();

        if (a_text != 'ALL ROOMS' && b_text != 'ALL ROOMS') {
            if (a_text == 'MAINSTAGE') {
                return -1;
            }

            if (b_text == 'MAINSTAGE') {
                return 1;
            }

            if (a_text == 'SPECIAL EVENTS') {
                return 1;
            }

            if (b_text == 'SPECIAL EVENTS') {
                return -1;
            }
        }
        var sortResult = a_text == b_text ? 0 : a_text < b_text ? -1 : 1

        //	  var sortResult =  jQuery(a).text().toUpperCase() == jQuery(b).text().toUpperCase() ? 0 : jQuery(a).text().toUpperCase() < jQuery(b).text().toUpperCase() ? -1 : 1
        return sortResult;
    }));

    jQuery(id).val(selected);


}

/* jQuery.extend(jQuery.expr[':'], {
  'containsi': function(elem, i, match, array)
  {
    return (elem.textContent || elem.innerText || '').toLowerCase()
    .indexOf((match[3] || "").toLowerCase()) >= 0;
  }
});  */

var hash = window.location.hash;
hash = hash.substring(0, 5);


if (hash == "#hour") {
    jQuery('#hours-tab').click();
    jQuery("#schedule").show();
    scrollTopMenu();

} else if (hash == '#tag-') {
    var option_val = window.location.hash;
    option_val = option_val.substring(5);

    jQuery("#schedule").show();
    jQuery("#schedule-select-tags").val(option_val).trigger('change');
    scheduleSort();
} else {
    jQuery("#schedule").show();

    reset_schedule(true);
    scheduleSort();
}


function currentDateTimestampUTC() {
    var dateObj = new Date();
    var newUTC = Date.UTC(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
    var utcDate = new Date(newUTC);

    return (utcDate.getTime()) / 1000;
}

function currentDateTimeTimestampUTC() {
    var utcDate = new Date();

    return ((utcDate.getTime()) / 1000);
}

function scrollTopMenu() {

    var width = jQuery('body').innerWidth();
    var offsetDiv = 60;
    if (width <= 990) {
        offsetDiv = 0;
    }

    var offset = jQuery('#schedule').offset().top - offsetDiv;
    if (offset < 0) {
        offset = 0;
    }
    jQuery('html, body').animate({
        scrollTop: offset
    }, 'slow');
}
