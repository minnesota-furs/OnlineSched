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

    // Make gtag_event globally accessible for all handlers
    window.gtag_event = gtag_event;

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
                // If switching to Programming or Essentials tab, reset all filters
                if (hash === '#programming') {
                    resetDropDowns();
                    window.favoritesFilterActive = false;
                    jQuery('#schedule-favorites-toggle').removeClass('active').attr('aria-pressed', 'false');
                    scheduleSort();
                    resetSelectTags();
                }
            });
        });

        jQuery("a[data-target=#modal-schedule]").click(function (ev) {
            ev.preventDefault();

            var parent = jQuery(this).parents('.schedule-item');
            var eventDetails = getEventDetailsFromElement(parent);
            window.currentModalEventDetails = eventDetails; // Store globally for modal use
            var panelists = eventDetails.panelists;
            var title = jQuery(this).html();
            var description = eventDetails.description;
            var date = parent.parent().siblings('h2').html();
            var time = parent.find('.schedule-time span').html();
            var room = eventDetails.room;
            var tags = eventDetails.tags;
            var ical = parent.find('.schedule-ical').attr('href');
            var googleCal = parent.find('.schedule-google').attr('href');
            // --- Get badges HTML ---
            var $scheduleTitle = parent.find('.schedule-title');
            var $badges = $scheduleTitle.clone();
            $badges.find('a').remove();
            var badgesHtml = $badges.html();
            // --- Build one-time Google Calendar event link ---
            var gcalUrl =
                'https://calendar.google.com/calendar/render?action=TEMPLATE' +
                '&text=' + encodeURIComponent(eventDetails.title) +
                '&details=' + encodeURIComponent(eventDetails.gcalDetails) +
                '&location=' + encodeURIComponent(eventDetails.gcalLocation) +
                '&dates=' + eventDetails.gcalDates;
            // Prepare eventDetails for Android modal
            var eventDetailsForModal = {
                title: eventDetails.title,
                details: eventDetails.gcalDetails,
                location: eventDetails.gcalLocation,
                dates: eventDetails.gcalDates
            };
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
            jQuery("#modal-schedule-title").html(favBtn + title + badgesHtml);
            // Always update the modal star to match the current favorite state
            function updateModalFavoriteStar(state) {
                let $modalBtn = jQuery('#modal-schedule-title .schedule-favorite-toggle');
                $modalBtn.toggleClass('active', state).attr('aria-pressed', state ? 'true' : 'false');
                $modalBtn.find('i').toggleClass('fas', state).toggleClass('far', !state);
            }

            jQuery("#modal-schedule-title .schedule-favorite-toggle").off('click').on('click', function (e) {
                e.preventDefault();
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
            jQuery("#modal-schedule-google").attr('href', gcalUrl); // Always set correct link
            let evt_hash = jQuery(parent).attr('id').substring(6);
            window.location.hash = evt_hash;
            jQuery("#modal-schedule").modal("show");
            jQuery("#modal-schedule").one('hidden.bs.modal', function () {
                removeHash();
            });
        });

        // Patch: override Google Calendar click for Android to show modal with eventDetails
        jQuery("#modal-schedule-google").off('click').on('click', function(e) {
            if (isAndroidDevice()) {
                e.preventDefault();
                // Use global event details for correct times
                var eventDetails = window.currentModalEventDetails || null;
                var eventDetailsForModal = null;
                if (eventDetails) {
                    eventDetailsForModal = {
                        title: eventDetails.title,
                        details: eventDetails.gcalDetails,
                        location: eventDetails.gcalLocation,
                        dates: eventDetails.gcalDates
                    };
                }
                var googleUrl = jQuery(this).attr('href');
                let rawLink = '';
                try {
                    let urlObj = new URL(googleUrl);
                    let cid = urlObj.searchParams.get('cid');
                    if (cid) {
                        rawLink = decodeURIComponent(cid);
                    }
                } catch (err) {
                    rawLink = googleUrl;
                }
                let downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;
                showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetailsForModal);
                return false;
            }
            // Otherwise, let default handler run
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
        window.favoritesFilterActive = false;

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
        });

        for (var slug in eventschedule_scheduleRooms) {
            if (slug !== '') {
                jQuery("#schedule-select-rooms").append("<option value='" + slug + "'>" + eventschedule_scheduleRooms[slug] + "</option>");
            }
        }

        // Ensure resetSelectTags is called after every filter change, including favorites toggle
        jQuery("#schedule-select-days, #schedule-select-tags, #schedule-select-rooms").change(function () {
            scheduleSort();
            resetSelectTags();
        });
        jQuery("#schedule-search-text").on('input', function () {
            scheduleSort();
            resetSelectTags();
        });
        jQuery("#schedule-reset").click(function () {
            resetDropDowns();
            // Reset favorites filter
            window.favoritesFilterActive = false;
            jQuery('#schedule-favorites-toggle').removeClass('active').attr('aria-pressed', 'false');
            scheduleSort();
            resetSelectTags();
        });
        jQuery('#schedule-favorites-toggle').on('click', function () {
            window.favoritesFilterActive = !window.favoritesFilterActive;
            jQuery(this).toggleClass('active', window.favoritesFilterActive);
            jQuery(this).attr('aria-pressed', window.favoritesFilterActive ? 'true' : 'false');
            // Toggle star icon between hollow and solid
            var $icon = jQuery(this).find('i');
            if (window.favoritesFilterActive) {
                $icon.removeClass('far').addClass('fas'); // solid star
            } else {
                $icon.removeClass('fas').addClass('far'); // hollow star
            }
            if (window.favoritesFilterActive) {
                jQuery('#schedule-select-days').val('all');
            }
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
            var favoritesFilterActive = window.favoritesFilterActive;

            // Determine if essentials tab is active
            var essentialsFilterActive = (window.eventschedule_showEvents === false);
            var essentialsTags = window.essentialsTags || [];

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

                // Essentials filter: only show items with an essentials tag
                if (essentialsFilterActive) {
                    let hasEssentialsTag = false;
                    for (let i = 0; i < essentialsTags.length; i++) {
                        if ($item.hasClass('schedule-tag-' + essentialsTags[i])) {
                            hasEssentialsTag = true;
                            break;
                        }
                    }
                    if (!hasEssentialsTag) {
                        show = false;
                    } else {
                        disableReset = false;
                    }
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
                    $item.hide();
                }
            });

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
            resetHoursDays();
            if (resetTags) {
                resetSelectTags();
            }

            sort_options_by_id("#schedule-select-tags");
            sort_rooms_options_by_id("#schedule-select-rooms");
            setOddEven();
        }

        function resetSelectTags() {
            // Only use visible schedule items to build tag list
            var scheduleReset = {};
            jQuery('.schedule-item:visible .schedule-tags').each(function () {
                var tags = jQuery.map(jQuery(this).html().split(","), jQuery.trim);
                tags.forEach(function(tag) {
                    tag = tag.replace(/<\/?[^>]+(>|$)/g, "");
                    if (tag && !scheduleReset.hasOwnProperty(tag)) {
                        scheduleReset[tag] = tag;
                    }
                });
            });

            // get selected option
            var selectedTagValue = jQuery("#schedule-select-tags option:selected").val();

            // Remove all except 'all'
            jQuery("#schedule-select-tags option").each(function () {
                if (jQuery(this).val() !== "all") {
                    jQuery(this).remove();
                }
            });

            for (var key in scheduleReset) {
                jQuery("#schedule-select-tags").append(jQuery('<option>', {
                    value: window.eventschedule_scheduleTags[key],
                    html: key
                }));
            }

            // reset selected option
            if (selectedTagValue !== 'all') {
                jQuery("#schedule-select-tags").val(selectedTagValue);
            }

            sort_options_by_id("#schedule-select-tags");
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

        window.confirmCalendarGoogleSubscription = function (link) {
            if (isAndroidDevice()) {
                let googleUrl = rewriteGoogleCalendarUrlForAndroid(link.href);
                let rawLink = '';
                try {
                    let urlObj = new URL(googleUrl);
                    let cid = urlObj.searchParams.get('cid');
                    if (cid) {
                        rawLink = decodeURIComponent(cid);
                    }
                } catch (e) {
                    rawLink = googleUrl;
                }
                let downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;
                let $event = jQuery(link).closest('.schedule-item');
                let eventDetails = $event.length ? getEventDetailsFromElement($event) : null;
                let eventDetailsForModal = null;
                if (eventDetails) {
                    eventDetailsForModal = {
                        title: eventDetails.title,
                        details: eventDetails.gcalDetails,
                        location: eventDetails.gcalLocation,
                        dates: eventDetails.gcalDates
                    };
                }
                showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetailsForModal);
                return false;
            } else {
                link.href = rewriteGoogleCalendarUrlForAndroid(link.href);
                return confirmCalendarSubscription(link, "Google");
            }
        };

        window.confirmCalendarSubscription = function (link, service) {
            if (confirm("This will subscribe you to your " + service + " calendar. This will keep updating until you delete it. Do you want to continue?")) {
                gtag_event('click', 'engagement', 'subscribe-' + service + '-calendar-single');
                setTimeout(function () {
                    window.location.href = link.href;
                }, 300); // Small delay to allow tracking
            }
            return false;
        };

        window.open_calendar_google = function () {
            let url = generate_ical_url();
            let googleUrl = 'https://calendar.google.com/calendar/r?cid=' + encodeURIComponent(url);
            googleUrl = rewriteGoogleCalendarUrlForAndroid(googleUrl);

            // Extract and decode the cid parameter
            let rawLink = '';
            try {
                let urlObj = new URL(googleUrl);
                let cid = urlObj.searchParams.get('cid');
                if (cid) {
                    rawLink = decodeURIComponent(cid);
                }
            } catch (e) {
                rawLink = url;
            }
            // For download, use https if webcal
            let downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;

            gtag_event('click', 'engagement', 'subscribe-google-calendar');

            if (isAndroidDevice()) {
                showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl);
                return false;
            } else {
                window.open(googleUrl, '_blank');
                return false;
            }
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
        if (isAndroidDevice()) {
            let googleUrl = rewriteGoogleCalendarUrlForAndroid(link.href);
            let rawLink = '';
            try {
                let urlObj = new URL(googleUrl);
                let cid = urlObj.searchParams.get('cid');
                if (cid) {
                    rawLink = decodeURIComponent(cid);
                }
            } catch (e) {
                rawLink = googleUrl;
            }
            let downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;
            let $event = jQuery(link).closest('.schedule-item');
            let eventDetails = $event.length ? getEventDetailsFromElement($event) : null;
            let eventDetailsForModal = null;
            if (eventDetails) {
                eventDetailsForModal = {
                    title: eventDetails.title,
                    details: eventDetails.gcalDetails,
                    location: eventDetails.gcalLocation,
                    dates: eventDetails.gcalDates
                };
            }
            showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetailsForModal);
            return false;
        } else {
            link.href = rewriteGoogleCalendarUrlForAndroid(link.href);
            return confirmCalendarSubscription(link, "Google");
        }
    };

    window.confirmCalendarSubscription = function (link, service) {
        if (confirm("This will subscribe you to your " + service + " calendar. This will keep updating until you delete it. Do you want to continue?")) {
            gtag_event('click', 'engagement', 'subscribe-' + service + '-calendar-single');
            setTimeout(function () {
                window.location.href = link.href;
            }, 300); // Small delay to allow tracking
        }
        return false;
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

function showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetails) {
    jQuery('#android-google-calendar-modal').modal('show');
    // Hide and reset One Time Google Event section and button
    var $onetimeSection = jQuery('#android-google-calendar-modal .android-gcal-onetime-section');
    var $onetimeLink = jQuery('#android-google-calendar-modal .android-gcal-onetime-link');
    var $onetimeBtn = jQuery('#android-google-calendar-modal .android-gcal-onetime-btn');
    $onetimeSection.hide();
    $onetimeBtn.hide();
    $onetimeLink.text("");
    // If eventDetails present, show and fill One Time Google Event section and button
    var gcalUrl = null;
    if (eventDetails) {
        gcalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE' +
            '&text=' + encodeURIComponent(eventDetails.title) +
            '&details=' + encodeURIComponent(eventDetails.details) +
            '&location=' + encodeURIComponent(eventDetails.location) +
            '&dates=' + eventDetails.dates;
        $onetimeSection.show();
        $onetimeLink.text(gcalUrl);
        $onetimeBtn.show();
        $onetimeBtn.attr('data-gcal-url', gcalUrl); // Store for click handler
        $onetimeBtn.off('click').on('click', function(e) {
            e.preventDefault();
            gtag_event('click', 'engagement', 'android-onetime-google-event');
            window.open(gcalUrl, '_blank');
            jQuery('#android-google-calendar-modal').modal('hide');
        });
    } else {
        $onetimeBtn.off('click');
    }
    // Button handlers
    jQuery('#android-gcal-try-link').off('click').on('click', function () {
        gtag_event('click', 'engagement', 'android-try-google-calendar');
        window.open(googleUrl, '_blank');
        jQuery('#android-google-calendar-modal').modal('hide');
    });
    jQuery('#android-gcal-download').off('click').on('click', function () {
        gtag_event('click', 'engagement', 'android-download-ics');
        var a = document.createElement('a');
        a.href = downloadUrl;
        a.download = 'schedule.ics';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    });
    jQuery('#android-gcal-copy').off('click').on('click', function () {
        gtag_event('click', 'engagement', 'android-copy-calendar-link');
        var linkToCopy = rawLink;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(linkToCopy).then(function () {
                jQuery('#android-gcal-copy-confirm').show().delay(1500).fadeOut();
            });
        } else {
            var temp = document.createElement('input');
            temp.value = linkToCopy;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            jQuery('#android-gcal-copy-confirm').show().delay(1500).fadeOut();
        }
    });
}

function isAndroidDevice() {
    return /android/i.test(navigator.userAgent);
}

// Utility function to extract event details from a schedule-item element
function getEventDetailsFromElement($event) {
    let title = $event.find('.schedule-title a').text();
    let description = $event.find('.schedule-description').html() || $event.find('.schedule-description').text();
    let room = $event.find('.schedule-room').text();
    let tags = $event.find('.schedule-tags').text();
    let panelists = $event.find('.schedule-panelists').text();
    let endTimestamp = parseInt($event.attr('data-end-time'));
    let durationText = $event.find('.schedule-time').text();
    let durationMinutes = 0;
    let durationMatch = durationText.match(/(\d+)\s*hr(?:s)?(?:\s*(\d+)\s*min)?/i);
    if (durationMatch) {
        durationMinutes += parseInt(durationMatch[1]) * 60;
        if (durationMatch[2]) durationMinutes += parseInt(durationMatch[2]);
    } else {
        let minMatch = durationText.match(/(\d+)\s*min/i);
        if (minMatch) durationMinutes += parseInt(minMatch[1]);
    }
    let startTimestamp = endTimestamp - durationMinutes * 60;
    function formatGCalDate(ts) {
        var d = new Date(ts * 1000);
        return d.getUTCFullYear().toString().padStart(4, '0') +
            (d.getUTCMonth() + 1).toString().padStart(2, '0') +
            d.getUTCDate().toString().padStart(2, '0') +
            'T' +
            d.getUTCHours().toString().padStart(2, '0') +
            d.getUTCMinutes().toString().padStart(2, '0') +
            d.getUTCSeconds().toString().padStart(2, '0') +
            'Z';
    }
    let gcalStart = formatGCalDate(startTimestamp);
    let gcalEnd = formatGCalDate(endTimestamp);
    let gcalDates = gcalStart + "/" + gcalEnd;
    let gcalDetails = description ? description.replace(/<[^>]+>/g, '') : '';
    if (panelists) gcalDetails += '\nPanelists: ' + panelists.replace(/<[^>]+>/g, '');
    if (tags) gcalDetails += '\nTags: ' + tags.replace(/<[^>]+>/g, '');
    let gcalLocation = room ? room.replace(/<[^>]+>/g, '') : '';
    return {
        title,
        description,
        room,
        tags,
        panelists,
        endTimestamp,
        startTimestamp,
        gcalStart,
        gcalEnd,
        gcalDates,
        gcalDetails,
        gcalLocation
    };
}
