export function new_schedule() {
    let header_top = 60;
    let header_mobile_top = 0;
    let tablet_width = 900;

    //
    // Function needs to be defined asap.
    // so need "before" ready
    //
    window.scrollTopMenu = function () {

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

    function gtag_event(eventType = 'click', category = 'schedule', label = 'schedule', value = 1) {
        if (typeof gtag === 'function') {
            gtag('event', eventType, {
                'event_category': category,
                'event_label': label,
                'value': value
            });
        }
    }

    jQuery(document).ready(function () {
        // added for copy url
        jQuery("#modal-copy-url").on("click", function (e) {

            e.preventDefault(); // Prevent the default action of the link

            const url = window.location.href;
            navigator.clipboard.writeText(url);

            animate_clipboard(this);

            gtag_event('copy_link_modal', 'engagement', url);
        });

        jQuery(".schedule-clipboard").on("click", function (e) {

            e.preventDefault(); // Prevent the default action of the link
            // get the id
            const eventID = jQuery(this).parent().parent().attr('id').substring(6);
            const url = window.location.href.replace(location.hash, "") + "#" + eventID;

            navigator.clipboard.writeText(url);

            animate_clipboard(this);

            gtag_event('copy_link', 'engagement', url);
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
            }, 1500, function () {
                jQuery(this).remove(); // Remove the effect after animation
            });

            // Fade the button color
            jQuery(clipObject).blur().focusout().fadeOut(750).fadeIn(750);
        }

        jQuery(document).ready(function () {
            // Listen for the tab change event
            jQuery('.nav-tabs a').on('shown.bs.tab', function (e) {
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

            // --- Get badges HTML ---
            // The badges are in .schedule-title, after the <a> (event title)
            var $scheduleTitle = parent.find('.schedule-title');
            // Clone and remove the <a> (event title) from the clone, leaving only badges
            var $badges = $scheduleTitle.clone();
            $badges.find('a').remove();
            var badgesHtml = $badges.html();

            // Insert favorite toggle button before the title in the modal, only if in schedule mode
            let isFavorite = parent.attr('data-favorite') === 'true';
            let evt_id = jQuery(parent).attr('id');
            let favBtn = '';
            if (
                window.location.href.indexOf('schedule') !== -1 &&
                window.location.href.indexOf('kiosk-schedule') === -1
            ) {
                favBtn = '<button type="button" class="schedule-favorite-toggle' + (isFavorite ? ' active' : '') + '" aria-pressed="' + (isFavorite ? 'true' : 'false') + '" title="Favorite" style="margin-right:8px;"><i class="' + (isFavorite ? 'fas' : 'far') + ' fa-star"></i></button>';
            }
            // --- Build modal title: favorite button + event title + badges ---
            jQuery("#modal-schedule-title").html(favBtn + title + badgesHtml);

            // Always update the modal star to match the current favorite state
            function updateModalFavoriteStar(state) {
                let $modalBtn = jQuery('#modal-schedule-title .schedule-favorite-toggle');
                $modalBtn.toggleClass('active', state).attr('aria-pressed', state ? 'true' : 'false');
                $modalBtn.find('i').toggleClass('fas', state).toggleClass('far', !state);
            }

            jQuery("#modal-schedule-title .schedule-favorite-toggle").off('click').on('click', function (e) {
                e.preventDefault();
                // Toggle favorite state for the event in the main schedule list
                const $mainItem = jQuery('#' + evt_id);
                const $btn = $mainItem.find('.schedule-favorite-toggle');
                isFavorite = !isFavorite;
                if ($mainItem.length) {
                    $mainItem.attr('data-favorite', isFavorite);
                    if (isFavorite) {
                        $btn.addClass('active').attr('aria-pressed', 'true');
                        $btn.find('i').removeClass('far').addClass('fas');
                    } else {
                        $btn.removeClass('active').attr('aria-pressed', 'false');
                        $btn.find('i').removeClass('fas').addClass('far');
                    }
                }
                // Always update the modal star to match the new state
                updateModalFavoriteStar(isFavorite);
                updateFavoritesCookie();
            });

            jQuery("#modal-schedule-description").show().html(description).siblings('hr').show();
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

        window.eventschedule_scheduleRooms = {};
        jQuery(".schedule-room").each(function () {
            var rooms = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
            var parentItem = jQuery(this).parent().parent();
            rooms.forEach(function(room) {
                // Normalize room name to slug
                var slug = room.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
                if (!eventschedule_scheduleRooms.hasOwnProperty(slug)) {
                    eventschedule_scheduleRooms[slug] = room; // Store original name for display
                }
                // Set attribute using slug (for each room)
                parentItem.attr("data-schedule-room-" + slug, slug);
            });
        });

        // Debug: log all schedule-item room attributes
        jQuery('.schedule-item').each(function() {
            var attrs = this.attributes;
            var roomAttrs = [];
            for (let i = 0; i < attrs.length; i++) {
                if (attrs[i].name.startsWith('data-schedule-room')) {
                    roomAttrs.push(attrs[i].name + '=' + attrs[i].value);
                }
            }
            if (roomAttrs.length) {
                console.log('Item', this.id, 'room attrs:', roomAttrs.join(', '));
            }
        });

        for (var slug in eventschedule_scheduleRooms) {
            if (slug !== '') {
                jQuery("#schedule-select-rooms").append("<option value='" + slug + "'>" + eventschedule_scheduleRooms[slug] + "</option>");
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
            // Reset favorites filter
            favoritesFilterActive = false;
            jQuery('#schedule-favorites-toggle').removeClass('active').attr('aria-pressed', 'false');
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
            if (window.eventschedule_showEvents) {
                // Show all items
                jQuery('.schedule-item').show();
                jQuery('#schedule-select-rooms').parent().show();
                jQuery('.schedule-reset').removeClass('col-sm-offset-2');
                jQuery('#schedule-add-to-calendar').show();
            } else {
                // Essentials filter: show only items with a tag in window.essentialsTags
                jQuery('.schedule-item').each(function () {
                    let show = false;
                    if (window.essentialsTags && window.essentialsTags.length > 0) {
                        for (let i = 0; i < window.essentialsTags.length; i++) {
                            if (jQuery(this).hasClass('schedule-tag-' + window.essentialsTags[i])) {
                                show = true;
                                break;
                            }
                        }
                    }
                    if (show) {
                        jQuery(this).show();
                    } else {
                        jQuery(this).hide();
                    }
                });
                jQuery('#schedule-select-rooms').parent().hide();
                jQuery('.schedule-reset').addClass('col-sm-offset-2');
                jQuery('#schedule-add-to-calendar').hide();
            }

        }

        // --- FAVORITES FILTER BUTTON LOGIC ---
        let favoritesFilterActive = false;
        jQuery('#schedule-favorites-toggle').on('click', function () {
            favoritesFilterActive = !favoritesFilterActive;
            jQuery(this).toggleClass('active', favoritesFilterActive);
            jQuery(this).attr('aria-pressed', favoritesFilterActive ? 'true' : 'false');
            // Toggle star icon between hollow and solid
            var $icon = jQuery(this).find('i');
            if (favoritesFilterActive) {
                $icon.removeClass('far').addClass('fas'); // solid star
            } else {
                $icon.removeClass('fas').addClass('far'); // hollow star
            }
            if (favoritesFilterActive) {
                jQuery('#schedule-select-days').val('all');
            }
            scheduleSort();
        });

        function scheduleSort() {
            if (jQuery('#schedule').length === 0) {
                return;
            }

            var disableReset = true;

            // Get filter values
            var selectedDay = jQuery("#schedule-select-days").val();
            var selectedTag = jQuery("#schedule-select-tags").val();
            var selectedRoom = jQuery("#schedule-select-rooms").val();
            var searchText = jQuery("#schedule-search-text").val().toLowerCase();

            // Ensure selectedTag and selectedRoom are strings for comparison
            if (selectedTag !== "all") selectedTag = String(selectedTag);
            if (selectedRoom !== "all") selectedRoom = String(selectedRoom);

            // Determine if favorites filter is active
            var favoritesFilterActive = jQuery('#schedule-favorites-toggle').hasClass('active');

            // Show/hide days based on selectedDay
            if (selectedDay == "all" || selectedDay == "Current") {
                jQuery(".schedule-day").show();
            } else {
                jQuery(".schedule-day").hide();
                jQuery('.schedule-day[data-schedule-day="' + selectedDay + '"]').show();
                disableReset = false;
            }

            // Helper for attribute matching
            function hasMatchingAttribute($item, prefix, value) {
                if (!$item[0] || !$item[0].attributes) return false;
                for (let attr of $item[0].attributes) {
                    if (attr.name.startsWith(prefix)) {
                        // Debug log
                        console.log('Checking', $item.attr('id'), attr.name, attr.value, 'against', value);
                        if (String(attr.value) === String(value)) {
                            return true;
                        }
                    }
                }
                return false;
            }

            // --- Main filtering loop ---
            var anyVisible = false;
            jQuery('.schedule-item').each(function () {
                var $item = jQuery(this);
                var show = true;

                // Filter by day: only show items whose parent day is visible
                var $day = $item.closest('.schedule-day');
                if (!$day.is(':visible')) {
                    show = false;
                }

                // Filter by tag
                if (selectedTag !== "all") {
                    if (!hasMatchingAttribute($item, 'data-schedule-tag', selectedTag)) {
                        show = false;
                    } else {
                        disableReset = false;
                    }
                }

                // Filter by room
                if (selectedRoom !== "all") {
                    if (!hasMatchingAttribute($item, 'data-schedule-room', selectedRoom)) {
                        console.log("hiding select room", selectedRoom,  $item);
                        show = false;
                    } else {
                        disableReset = false;
                    }
                }

                // Filter by "Current" day (hide past events)
                if (selectedDay == "Current") {
                    var currentDateUTC = currentDateTimeTimestampUTC();
                    var itemDate = $item.data('end-time');
                    if (itemDate <= currentDateUTC) {
                        show = false;
                    } else {
                        disableReset = false;
                    }
                }

                // Filter by search text
                if (searchText != "") {
                    var found = false;
                    $item.find(".schedule-title a, .schedule-panelists, .schedule-tags, .schedule-room").each(function () {
                        if (jQuery(this).text().toLowerCase().indexOf(searchText) != -1) {
                            found = true;
                        }
                    });
                    if (!found) {
                        show = false;
                    } else {
                        disableReset = false;
                    }
                }

                // Filter by favorites
                if (favoritesFilterActive) {
                    if ($item.attr('data-favorite') !== 'true') {
                        show = false;
                    } else {
                        disableReset = false;
                    }
                }

                // Show/hide item
                if (show) {
                    $item.show();
                    anyVisible = true;
                } else {
                    console.log("hiding!");
                    $item.hide();
                }
            });
/*
alert('fun');
            // Show all hours and days, then hide those with no visible children
            jQuery('.schedule-hour').show();
            jQuery('.schedule-day').show();
            jQuery('.schedule-hour').each(function () {
                var visibleLength = jQuery(this).children('.schedule-item:visible').length;
                if (visibleLength == 0) {
                    jQuery(this).hide();
                }
            });
            jQuery('.schedule-day').each(function () {
                var visibleLength = jQuery(this).children('.schedule-hour:visible').length;
                if (visibleLength == 0) {
                    jQuery(this).hide();
                }
            });
*/
            jQuery("#schedule-reset").prop("disabled", disableReset);

            reset_schedule(true);
            messageAtBottomForCalendar();
        }

        function resetHoursDays() {

            // Clean up show/hides
            jQuery('.schedule-hour').each(function () {
                jQuery(this).show(); // You have to show them to calculate...
                var visibleLength = jQuery(this).children(':visible').length;
                // IT counts itself as one
                if (visibleLength < 2) {
                    jQuery(this).hide();
                }
            });

            // now for days
            jQuery('.schedule-day').each(function () {
                jQuery(this).show(); // You have to show them to calculate...
                var visibleLength = jQuery(this).children(':visible').length;

                // IT counts itself as one
                if (visibleLength < 2) {
                    jQuery(this).hide();
                }
            });
        }

        function reset_schedule(resetTags) {
            if (resetTags) {
                // showHideEvents();
            }
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

            // Use slugs for value, display name for label
            for (let slug in eventschedule_scheduleRooms) {
                if (slug !== '') {
                    jQuery("#schedule-select-rooms").append(jQuery('<option>', {
                        value: slug,
                        html: eventschedule_scheduleRooms[slug]
                    }));
                }
            }

            // reset selected option
            if (selectedRoomsValue !== 'all') {
                jQuery("#schedule-select-rooms").val(selectedRoomsValue);
            }

            sort_options_by_id("#schedule-select-rooms");

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

        gtag_event('hash', 'engagement', eventschedule_hash);

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

            let hash = "#online" + window.location.hash.substring(1);
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

        window.open_calendar_apple = function () {
            let url = generate_ical_url();

            window.open(url);
            gtag_event('click', 'engagement', 'subscribe-apple-calendar');

        };

        window.open_calendar_google = function () {
            let url = generate_ical_url();
            let googleUrl = 'https://calendar.google.com/calendar/r?cid=' + encodeURIComponent(url);
            googleUrl = rewriteGoogleCalendarUrlForAndroid(googleUrl);

            console.log('working on andoird',googleUrl);
            window.open(googleUrl);
            gtag_event('click', 'engagement', 'subscribe-google-calendar');
        };

        window.open_calendar_outlook = function () {


            const date = new Date();
            const year = date.getFullYear();
            let calendarName = 'Furry Migration ' + year;

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
            } else {

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
    function removeHash() {
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


    window.confirmCalendarAppleSubscription = function (link) {
        return confirmCalendarSubscription(link, "Apple");
    };


    window.confirmCalendarGoogleSubscription = function (link) {
        link.href = rewriteGoogleCalendarUrlForAndroid(link.href);
        return confirmCalendarSubscription(link, "Google");
    };

    window.confirmCalendarSubscription = function (link, service) {


        if (confirm("This will subscribe you to your " + service + " calendar. This will keep updating until you delete it. Do you want to continue?")) {
            gtag('event', 'click', {
                'event_category': 'engagement',
                'event_label': 'subscribe-' + service + '-calendar-single'
            });

            // Allow navigation after tracking
            setTimeout(function () {
                window.location.href = link.href;
            }, 300); // Small delay to allow tracking

            return false; // Prevent default behavior to handle navigation manually
        }
        return false; // Cancel action if they click "Cancel"

    };


    // --- VANILLA JS MODAL LOGIC FOR LOGIN & HELP ---
    var loginBtn = document.getElementById('login-modal-btn');
    var loginModal = document.getElementById('login-modal');
    var loginCloseBtn = document.getElementById('login-modal-close');
    var lastLoginTrigger = null;

    if (loginBtn && loginModal && loginCloseBtn) {
        loginBtn.addEventListener('click', function (e) {
            e.preventDefault();
            loginModal.style.display = 'block';
            lastLoginTrigger = loginBtn;
            loginCloseBtn.focus();
            loginCloseBtn.blur();
        });
        loginCloseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            loginModal.style.display = 'none';

            if (lastLoginTrigger) {
                lastLoginTrigger.focus();
                lastLoginTrigger.blur();
            }
        });
    }

    // --- HELP MODAL ---
    var infoBtn = document.getElementById('info-modal-btn');
    var infoModal = document.getElementById('info-modal');
    var infoCloseBtn = document.getElementById('info-modal-close');
    var lastInfoTrigger = null;

    if (infoBtn && infoModal && infoCloseBtn) {
        infoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            infoModal.style.display = 'block';
            lastInfoTrigger = infoBtn;
            infoCloseBtn.focus();
        });
        infoCloseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            infoModal.style.display = 'none';
            if (lastInfoTrigger) {
                lastInfoTrigger.focus();
                lastInfoTrigger.blur();
            }
        });
    }

    // --- ESCAPE KEY HANDLING FOR BOTH MODALS ---
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (loginModal && loginModal.style.display !== 'none') {
                loginModal.style.display = 'none';
                if (lastLoginTrigger) {
                    lastLoginTrigger.focus();
                    lastLoginTrigger.blur();
                }
            }
            if (infoModal && infoModal.style.display !== 'none') {
                infoModal.style.display = 'none';
                if (lastInfoTrigger) {
                    lastInfoTrigger.focus();
                    lastInfoTrigger.blur();
                }
            }
        }
    });


}

// Utility: rewrite Google Calendar URL for Android (webcal:// to http://)
function rewriteGoogleCalendarUrlForAndroid(url) {
    var isAndroid = /android/i.test(navigator.userAgent);

    if (!isAndroid) return url;
    try {
        var urlObj = new URL(url);
        var cid = urlObj.searchParams.get('cid');
        if (cid) {
            var decodedCid = decodeURIComponent(cid);
            if (decodedCid.startsWith('webcal://')) {
                var newCid = decodedCid.replace(/^webcal:\/\//i, 'http://');
                urlObj.searchParams.set('cid', encodeURIComponent(newCid));
                return urlObj.toString();
            }
        }
    } catch (e) {
        // fallback: do nothing if URL parsing fails
    }
    console.log('android url', url);
    return url;
}
