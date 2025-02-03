export function new_schedule() {
let header_top = 60;
let header_mobile_top = 0;
let tablet_width = 900;

    //
    // Function needs to be defined asap.
    // so need "before" ready
    //
    window.scrollTopMenu = function() {

        var width = jQuery('body').innerWidth();
        var offsetDiv = header_top;
        if (width <= tablet_width) {
            offsetDiv = header_mobile_top;
        }

        var offset = jQuery('#schedule').offset().top - offsetDiv;
        if (offset < 0) {
            offset = 0;
        }
        jQuery('html, body').animate({
            scrollTop: offset
        }, 'slow');
    };

    function gtag_event(eventType = 'click', category = 'schedule', label = 'schedule', value = 1 ){
        if (typeof gtag === 'function'){
            gtag('event', eventType, {
                'event_category': category,
                'event_label': label,
                'value': value
            });
        }
    }

    jQuery(document).ready(function () {
        // added for copy url
        jQuery("#modal-copy-url").on("click", function(e) {

            e.preventDefault(); // Prevent the default action of the link

            const url = window.location.href;
            navigator.clipboard.writeText(url);

            animate_clipboard(this);

            gtag_event('copy_link_modal', 'engagement', url );
        });

        jQuery(".schedule-clipboard").on("click", function(e) {

            e.preventDefault(); // Prevent the default action of the link
            // get the id
            const eventID = jQuery(this).parent().parent().attr('id').substring(6);
            const url = window.location.href.replace(location.hash,"")+"#"+eventID;

            navigator.clipboard.writeText( url);

            animate_clipboard(this);

            gtag_event('copy_link', 'engagement', url );
        });

        function animate_clipboard(clipObject) {

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (prefersReducedMotion) {
                // just blur the button lose focus on it.
                jQuery(clipObject).blur().focusout();
                return; // Skip animation if the user prefers reduced motion
            }
            // Create clipboard icon
            const $clipboardEffect = jQuery('<div class="clipboard-effect"><i class="fas fa-clipboard-check"></i> Copied!</div>').appendTo('body');

            // Position the effect near the button
            const offset = jQuery(clipObject).offset();
            const effectWidth = $clipboardEffect.outerWidth();
            const effectHeight = $clipboardEffect.outerHeight();

            let topPosition = offset.top - effectHeight - 10; // Position above the button
            let leftPosition = offset.left + jQuery(clipObject).outerWidth() / 2 - effectWidth / 2; // Center above the button

            // Apply the calculated position
            $clipboardEffect.css({
                top: topPosition,
                left: leftPosition
            });

            // Animate the effect
            $clipboardEffect.animate({
                top: topPosition - 80, // Adjust to desired end position
                opacity: 0,
            }, 1500, function() {
                jQuery(this).remove(); // Remove the effect after animation
            });

            // Fade the button color
            jQuery(clipObject).blur().focusout().fadeOut(750).fadeIn(750);
        }

        jQuery(document).ready(function() {
            // Listen for the tab change event
            jQuery('.nav-tabs a').on('shown.bs.tab', function(e) {
                // Get the href attribute of the active tab
                var hash = jQuery(e.target).attr('href');

                // Update the browser's hash
                if (hash) {
                    history.pushState(null, null, hash);
                }
            });
        });

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
            if (description !== undefined && description.length >= 4) {
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


            let evt_hash = jQuery(parent).attr('id').substring(6);
            // set hash
            window.location.hash = evt_hash;

            jQuery("#modal-schedule").modal("show");
            // clear hash on hide
            jQuery("#modal-schedule").one('hidden.bs.modal', function () {
                removeHash();
            });
        });

        function modal_popup_fill(id, value) {

            if (value === undefined || value === "") {
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
        window.eventschedule_showEvents = true;

        window.setFilterEvents = function (args) {
            scrollTopMenu();

            eventschedule_showEvents = args;

            resetDropDowns();
            scheduleSort();
            resetSelectTags();
        }

// fill tag drop down mind you check if it's unique too
        window.eventschedule_scheduleTags = new Object();
        window.eventschedule_count = 0;
        jQuery(".schedule-tags").each(function (index) {
            // Current tags
            var tags = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
            var len = 0;
            for (index = 0, len = tags.length; index < len; ++index) {
                var tag = tags[index];
                tag = tag = tag.replace(/<\/?[^>]+(>|$)/g, "");
                if (!eventschedule_scheduleTags.hasOwnProperty(tag)) {
                    eventschedule_scheduleTags[tag] = eventschedule_count++;
                }

                jQuery(this).parent().parent().attr("data-schedule-tag" + eventschedule_scheduleTags[tag], eventschedule_scheduleTags[tag]);
            }

        });

        for (var key in eventschedule_scheduleTags) {
            if (typeof key === 'string' && key.trim().length != 0 && typeof eventschedule_scheduleTags[key] === 'string' && eventschedule_scheduleTags[key].trim.length != 0)
                jQuery("#schedule-select-tags").append("<option value='" + eventschedule_scheduleTags[key] + "'>" + key + "</option>");
        }

        window.eventschedule_scheduleRooms = new Object();
        eventschedule_count = 0;
        jQuery(".schedule-room").each(function (index) {
            // Current tags
            var rooms = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
            let len = 0;
            for (index = 0, len = rooms.length; index < len; ++index) {
                var room = rooms[index];
                if (!eventschedule_scheduleRooms.hasOwnProperty(room)) {
                    eventschedule_scheduleRooms[room] = eventschedule_count++;
                }

                jQuery(this).parent().parent().attr("data-schedule-room" + eventschedule_scheduleRooms[room], eventschedule_scheduleRooms[room]);
            }

        });

        for (var key in eventschedule_scheduleRooms) {
            if (key != '' && eventschedule_scheduleRooms[key] != '') {
                jQuery("#schedule-select-rooms").append("<option value='" + eventschedule_scheduleRooms[key] + "'>" + key + "</option>");
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

//    jQuery('.schedule-room-tabletop,.schedule-item.schedule-room-tabletop-special-a,.schedule-room-tabletop-special-b,.schedule-item.schedule-room-video-gaming,.schedule-item.schedule-room-tabletop-a,.schedule-item.schedule-room-tabletop-b,.schedule-item.schedule-room-tabletop-c,.schedule-item.schedule-room-tabletop-d,.schedule-item.schedule-room-tabletop-e,.schedule-item.schedule-room-board-gaming').hide();
            if (eventschedule_showEvents) {
                /*			jQuery('#schedule-select-rooms option').show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop A"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop B"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop C"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop D"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop E"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop Special A"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop Special B"] +"]").hide();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Video Gaming"] +"]").hide();
                */
                jQuery('#schedule-select-rooms').parent().show();
                jQuery('.schedule-reset').removeClass('col-sm-offset-2');
                jQuery('#schedule-add-to-calendar').show();
            } else {
                jQuery('.schedule-item').not('.schedule-tag-essentials, .schedule-tag-vip, .schedule-tag-guest-of-honor, .schedule-tag-special-guest').hide();

//        jQuery('.schedule-item').not('.schedule-tag-essentials, .schedule-tag-vip, .schedule-tag-guest-of-honor, .schedule-tag-vip');
                // old way of gaming
                // jQuery('.schedule-item').not('.schedule-room-tabletop,.schedule-room-tabletop-special-a,.schedule-room-tabletop-special-b,.schedule-room-video-gaming,.schedule-room-tabletop-a,.schedule-room-tabletop-b,.schedule-room-tabletop-c,.schedule-room-tabletop-d,.schedule-room-tabletop-e,.schedule-item.schedule-room-board-gaming').hide();
                /*jQuery('#schedule-select-rooms option').hide()
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop A"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop B"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop C"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop D"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop E"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop Special A"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Tabletop Special B"] +"]").show();
                jQuery("#schedule-select-rooms option[value="+eventschedule_scheduleRooms["Video Gaming"] +"]").show();
                jQuery("#schedule-select-rooms option[value=all]").show();
                */
                jQuery('#schedule-select-rooms').parent().hide();
                jQuery('.schedule-reset').addClass('col-sm-offset-2');
                jQuery('#schedule-add-to-calendar').hide();

            }

        }

        function scheduleSort() {
            if (jQuery('#schedule').length === 0) {
                // Element with ID "schedule" doesn't exist exit out of function.
                return;
            }

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

            if (selectedTag !== "all") {
                searchData += "[data-schedule-tag" + selectedTag + "='" + selectedTag + "']";
            }

            var selectedRoom = jQuery("#schedule-select-rooms").val();
            if (selectedRoom !== "all") {
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
            messageAtBottomForCalendar();
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
                    let len = 0;
                    for (index = 0, len = tags.length; index < len; ++index) {
                        var tag = tags[index];
                        tag = tag.replace(/<\/?[^>]+(>|$)/g, "");
                        if (!scheduleReset.hasOwnProperty(tag)) {
                            scheduleReset[tag] = tag;
                        }
                    }
                }
            });

            // get selected option
            var selectedTagValue = jQuery("#schedule-select-tags option:selected").val();

            jQuery("#schedule-select-tags option").each(function () {
                if (jQuery(this).val() !== "all") {
                    jQuery(this).remove();
                }
            });

            for (var key in scheduleReset) {
                jQuery("#schedule-select-tags").append(jQuery('<option>', {
                    value: eventschedule_scheduleTags[key],
                    html: key
                }));
            }

            // reset selected option
            if (selectedTagValue !== 'all') {
                jQuery("#schedule-select-tags").val(selectedTagValue);
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
                    let len = 0;
                    for (index = 0, len = tags.length; index < len; ++index) {
                        var tag = tags[index];
                        if (!scheduleResetRooms.hasOwnProperty(tag)) {
                            scheduleResetRooms[tag] = tag;
                        }
                    }


                }
            });

            // get selected option
            var selectedRoomsValue = jQuery("#schedule-select-rooms option:selected").val();

            jQuery("#schedule-select-rooms").find("option").not(":first").remove();

            for (let key in scheduleResetRooms) {
                if (key !== '' && (eventschedule_scheduleRooms[key] !== '' || eventschedule_scheduleRooms[key] === 0)) {
                    jQuery("#schedule-select-rooms").append(jQuery('<option>', {
                        value: eventschedule_scheduleRooms[key],
                        html: key
                    }));
                }
            }

            // reset selected option
            if (selectedRoomsValue !== 'all') {
                jQuery("#schedule-select-rooms").val(selectedRoomsValue);
            }

            sort_options_by_id("#schedule-select-Rooms");

            setOddEven();
        }

        function sort_options_by_id(id) {

            var selected = jQuery(id).val();
            jQuery(id).html(jQuery(id + " option").sort(function (a, b) {

                var a_text = jQuery(a).text().toUpperCase();
                var b_text = jQuery(b).text().toUpperCase();

                // Check if 'a' or 'b' should be on top
                if (jQuery(a).val() == 'all') return -1;
                if (jQuery(b).val() == 'all') return 1;

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

        let eventschedule_hash = window.location.hash;

        gtag_event('hash', 'engagement', eventschedule_hash );

        eventschedule_hash = eventschedule_hash.substring(0, 5);

        if (eventschedule_hash === "#hour") {
            jQuery("#schedule").show();
            jQuery('#hours-tab').click();
            scrollTopMenu();
        } else if (eventschedule_hash === '#tag-') {
            let option_val = window.location.hash;
            option_val = option_val.substring(5);

            option_val = option_val.replace(/[^a-zA-Z0-9]/g, '').toLowerCase(); // Filter out spaces and special chars to make it easier on everyone.

            jQuery("#schedule").show();
            reset_schedule(true);

            if (isNaN(option_val)) {
                jQuery("#schedule-select-tags option").each(function () {
                    var optionText = jQuery(this).text().replace(/<\/?[^>]+(>|$)/g, ""); // Remove HTML tags
                    optionText = optionText.replace(/[^a-zA-Z0-9]/g, '').toLowerCase(); // remove also all non character make it easier to tag

                    if (optionText === option_val) {
                        jQuery(this).prop('selected', true);
                        return false; // Exit loop once a match is found
                    } else {
                        console.log('error did not match ' + optionText + ' x ' + option_val);
                    }
                });
            } else {
                jQuery("#schedule-select-tags").val(option_val).trigger('change');
            }

            scheduleSort();
        } else if (eventschedule_hash === "#evt-") {

            jQuery("#schedule").show();
            reset_schedule(true);
            scheduleSort();

            let hash = "#online"+window.location.hash.substring(1);
            let event = jQuery(hash);
            if (event !== undefined) {
                if (jQuery(event).css('display') == 'none') {
                    jQuery('#schedule-select-days').val('all');
                    scheduleSort();
                }

                    var width = jQuery('body').innerWidth();
                    var offsetDiv = header_top * 2;
                    if (width <= tablet_width) {
                        offsetDiv = header_mobile_top;
                    }

                    var offset = jQuery(event).offset().top - offsetDiv;
                    if (offset < 0) {
                        offset = 0;
                    }

                    jQuery('html, body').animate({
                        scrollTop: offset
                    }, 'slow');

                    jQuery(hash + ' .schedule-title a').click();
            }

        } else {
            jQuery("#schedule").show();

            reset_schedule(true);
            scheduleSort();
        }


        function messageAtBottomForCalendar() {
            // Single if statement to check all conditions
            if ((jQuery('#schedule-search-text').val().trim() === '') &&
                (jQuery('#schedule-select-tags').val() !== 'all' || jQuery('#schedule-select-rooms').val() !== 'all')) {

                jQuery('#schedule-add-to-calendar-message').html('Add this filtered list to your calendar!');
            } else {

                jQuery('#schedule-add-to-calendar-message').html('Import the full schedule into your calendar!');
            }
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

        window.open_calendar_apple = function (){
            let url = generate_ical_url();

            window.open(url);
            gtag_event('click', 'engagement', 'subscribe-apple-calendar');

        };

        window.open_calendar_google = function (){
            let url = generate_ical_url();

            url = 'https://calendar.google.com/calendar/r?cid='+encodeURIComponent(url);
            window.open(url);
            gtag_event('click', 'engagement', 'subscribe-google-calendar');
        };

        window.open_calendar_outlook = function (){


            const date = new Date();
            const year = date.getFullYear();
            let calendarName = 'Furry Migration '+year;

            let webcalUrl = generate_ical_url();


            // Construct the Outlook URL
            let outlookUrl = 'https://outlook.office.com/owa/?path=/calendar/action/compose&rru=addsubscription';
            outlookUrl += '&url=' + encodeURIComponent(webcalUrl);
            outlookUrl += '&name=' + encodeURIComponent(calendarName);

            // Open the URL in a new window
            window.open(outlookUrl, '_blank');

            gtag_event('click', 'engagement', 'subscribe-outlook-calendar');
        };
        function generate_ical_url() {

            let url = "";

            if (jQuery('#schedule-search-text').val().trim() != '') {
                url = "?room=all";
            }
            else {

                // Get the selected text
                var selectedTag = jQuery("#schedule-select-tags").find('option:selected').text();

                // Look up the corresponding value in scheduleMasterRooms
                var tagSlug = scheduleMasterTags[selectedTag];
                if (tagSlug) {
                    url = "&tag=" + tagSlug;
                }

                // Get the selected text
                var selectedRoom = jQuery("#schedule-select-rooms").find('option:selected').text();

                // Look up the corresponding value in scheduleMasterRooms
                var roomSlug = scheduleMasterRooms[selectedRoom];
                if (roomSlug) {
                    url += "&room=" + roomSlug;
                }

                if (url === '') {
                    url = "?room=all";
                } else {
                    url = '?' + url.slice(1);
                }
            }
            return 'webcal://' + window.location.host + '/wp-content/plugins/OnlineSched/icalby.php' + url;
        }

});

    // From article https://stackoverflow.com/questions/1397329/how-to-remove-the-hash-from-window-location-url-with-javascript-without-page-r
    function removeHash () {
        var scrollV, scrollH, loc = window.location;
        if ("pushState" in history)
            history.pushState("", document.title, loc.pathname + loc.search);
        else {
            // Prevent scrolling by storing the page's current scroll offset
            scrollV = document.body.scrollTop;
            scrollH = document.body.scrollLeft;

            loc.hash = "";

            // Restore the scroll offset, should be flicker free
            document.body.scrollTop = scrollV;
            document.body.scrollLeft = scrollH;
        }
    }


    window.confirmCalendarAppleSubscription = function(link){
        return confirmCalendarSubscription(link, "Apple");
    };


    window.confirmCalendarGoogleSubscription = function(link){
        return confirmCalendarSubscription(link, "Google");
    };

    window.confirmCalendarSubscription= function(link, service){


        if (confirm("This will subscribe you to your "+service+" calendar. This will keep updating until you delete it. Do you want to continue?")) {
            gtag('event', 'click', {
                'event_category': 'engagement',
                'event_label': 'subscribe-'+service+'-calendar-single'
            });

            // Allow navigation after tracking
            setTimeout(function() {
                window.location.href = link.href;
            }, 300); // Small delay to allow tracking

            return false; // Prevent default behavior to handle navigation manually
        }
        return false; // Cancel action if they click "Cancel"

    };
}