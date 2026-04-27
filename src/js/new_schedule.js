/* Schedule code to do filtering and the magic */
import { rewriteGoogleCalendarUrlForAndroid } from './scheduleCalendar.js';

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

    jQuery(document).ready(function () {
        // added for copy url
        jQuery("#modal-copy-url").on("click", function (e) {

            e.preventDefault(); // Prevent the default action of the link

            const url = window.location.href;
            navigator.clipboard.writeText(url);

            animate_clipboard(this);
        });

        jQuery(".schedule-clipboard").on("click", function (e) {

            e.preventDefault(); // Prevent the default action of the link
            // get the id
            const eventID = jQuery(this).parent().parent().attr('id').substring(6);
            const url = window.location.href.replace(location.hash, "") + "#" + eventID;

            navigator.clipboard.writeText(url);

            animate_clipboard(this);
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

        // Listen for the vanilla tab change event
        document.addEventListener('os:tab:shown', function (e) {
            // Get the hash of the active tab
            var hash = e.detail.hash;
            // Update the browser's hash
            if (hash) {
                history.pushState(null, null, hash);
            }
            // If switching to Programming or Essentials tab, reset all filters
            if (hash === '#programming' || hash === '#essentials') {
                resetDropDowns();
                window.favoritesFilterActive = false;
                jQuery('#schedule-favorites-toggle').removeClass('active').attr('aria-pressed', 'false');
                scheduleSort();
                resetSelectTags();
            }
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
            jQuery("#modal-schedule-google").attr('href', googleCal); // Set correct subscription link for non-Android
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
        // On page load, ensure reset button is disabled if all filters are at default
        function updateResetButtonState() {
            var isDefault = (
                jQuery('#schedule-search-text').val().trim() === '' &&
                jQuery('#schedule-select-tags').val() === 'all' &&
                jQuery('#schedule-select-rooms').val() === 'all' &&
                jQuery('#schedule-select-days').val() === 'Current' &&
                !window.favoritesFilterActive
            );
            jQuery('#schedule-reset').prop('disabled', isDefault);
        }
        // Call on page load
        updateResetButtonState();
        resetSelectRooms(); // Ensure room dropdown is populated on initial load

        // Ensure resetSelectTags and resetSelectRooms are called after every filter change, including favorites toggle
        jQuery("#schedule-select-days, #schedule-select-tags, #schedule-select-rooms").change(function () {
            scheduleSort();
            resetSelectTags();
            resetSelectRooms();
            updateResetButtonState();
        });
        jQuery("#schedule-search-text").on('input', function () {
            scheduleSort();
            resetSelectTags();
            resetSelectRooms();
            updateResetButtonState();
        });
        jQuery("#schedule-reset").click(function () {
            resetDropDowns();
            // Reset favorites filter
            window.favoritesFilterActive = false;
            jQuery('#schedule-favorites-toggle').removeClass('active').attr('aria-pressed', 'false');
            scheduleSort();
            resetSelectTags();
            resetSelectRooms();
            jQuery('#schedule-reset').prop('disabled', true); // Always disable after reset
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
            resetSelectRooms();
            this.blur(); // Remove focus after click
            updateResetButtonState();
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

            // --- Day filtering logic ---
            if (selectedDay === "all" || selectedDay === "Current") {
                jQuery('.schedule-day').show();
            } else {
                jQuery('.schedule-day').each(function () {
                    var dayLabel = jQuery(this).attr('data-schedule-day');
                    if (dayLabel === selectedDay) {
                        jQuery(this).show();
                    } else {
                        jQuery(this).hide();
                    }
                });
            }

            // --- Main filtering loop ---
            var anyVisible = false;
            var currentDateUTC = null;
            if (selectedDay === "Current") {
                currentDateUTC = currentDateTimeTimestampUTC();
            }
            jQuery('.schedule-item').each(function () {
                var $item = jQuery(this);
                var show = true;

                // Filter by day: only show items whose parent day is visible (unless Current)
                var $day = $item.closest('.schedule-day');
                if (selectedDay !== "Current" && !$day.is(':visible')) {
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
                if (selectedDay === "Current") {
                    var itemDate = $item.data('end-time');
                    if (!itemDate || itemDate <= currentDateUTC) {
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

            // Determine if any filter/search is active
            var isDefault = (
                searchText.trim() === '' &&
                selectedTag === 'all' &&
                selectedRoom === 'all' &&
                selectedDay === 'Current' &&
                !favoritesFilterActive
            );
            disableReset = isDefault;
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
                resetSelectRooms();
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

        function resetSelectRooms() {
            // Only use visible schedule items to build room list
            var scheduleRooms = {};
            jQuery('.schedule-item:visible').each(function () {
                var $item = jQuery(this);
                // Find all data-schedule-room-* attributes
                if ($item[0] && $item[0].attributes) {
                    for (let attr of $item[0].attributes) {
                        if (attr.name.startsWith('data-schedule-room')) {
                            var slug = attr.value;
                            // Get display name from global room map if available
                            var name = window.eventschedule_scheduleRooms && window.eventschedule_scheduleRooms[slug] ? window.eventschedule_scheduleRooms[slug] : slug;
                            if (slug && !scheduleRooms.hasOwnProperty(slug)) {
                                scheduleRooms[slug] = name;
                            }
                        }
                    }
                }
            });
            // get selected option
            var selectedRoomValue = jQuery("#schedule-select-rooms option:selected").val();
            // Remove all except 'all'
            jQuery("#schedule-select-rooms option").each(function () {
                if (jQuery(this).val() !== "all") {
                    jQuery(this).remove();
                }
            });
            for (var slug in scheduleRooms) {
                jQuery("#schedule-select-rooms").append(jQuery('<option>', {
                    value: slug,
                    html: scheduleRooms[slug]
                }));
            }
            // reset selected option
            if (selectedRoomValue !== 'all') {
                jQuery("#schedule-select-rooms").val(selectedRoomValue);
            }
            sort_rooms_options_by_id("#schedule-select-rooms");
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

        function handleHashRouting() {
            let hash = window.location.hash;
            if (!hash) {
                jQuery("#schedule").show();
                reset_schedule(true);
                scheduleSort();
                return;
            }

            if (hash.startsWith("#hour")) {
                jQuery("#schedule").show();
                jQuery('#hours-tab').click();
                scrollTopMenu();
            } else if (hash === '#essentials') {
                jQuery("#schedule").show();
                window.setFilterEvents(false);
                // Trigger tab click to update UI state
                const essentialsTab = document.querySelector('[data-os-tab="essentials"]');
                if (essentialsTab) essentialsTab.click();
            } else if (hash.startsWith('#tag-')) {
                let option_val = hash.substring(5);
                option_val = option_val.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();

                jQuery("#schedule").show();
                reset_schedule(true);

                if (isNaN(option_val)) {
                    jQuery("#schedule-select-tags option").each(function () {
                        var optionText = jQuery(this).text().replace(/<\/?[^>]+(>|$)/g, ""); // Remove HTML tags
                        optionText = optionText.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();

                        if (optionText === option_val) {
                            jQuery(this).prop('selected', true);
                            return false;
                        }
                    });
                } else {
                    jQuery("#schedule-select-tags").val(option_val).trigger('change');
                }
                scheduleSort();
            } else if (hash.startsWith("#evt-")) {
                jQuery("#schedule").show();
                reset_schedule(true);
                scheduleSort();

                let evt_id = "#online" + hash.substring(1);
                let event = jQuery(evt_id);
                if (event.length) {
                    if (event.css('display') === 'none') {
                        jQuery('#schedule-select-days').val('all');
                        scheduleSort();
                    }

                    var width = jQuery('body').innerWidth();
                    var offsetDiv = header_top * 2;
                    if (width <= tablet_width) {
                        offsetDiv = header_mobile_top;
                    }

                    var offset = event.offset().top - offsetDiv;
                    if (offset < 0) offset = 0;

                    jQuery('html, body').animate({
                        scrollTop: offset
                    }, 'slow');

                    setTimeout(() => {
                        jQuery(evt_id + ' .schedule-title a').click();
                    }, 300);
                }
            } else {
                // If it's a known tab or any other hash, make sure schedule is shown
                jQuery("#schedule").show();
                reset_schedule(true);
                scheduleSort();
            }
            // Dispatch custom event for tests/external listeners
            document.dispatchEvent(new CustomEvent('os:hash-routing:complete', { detail: { hash: hash } }));
        }

        // Clickable rooms: clicking a room name in the list sets the room filter
        jQuery(document).on('click', '.schedule-room.schedule-filter-link', function (e) {
            e.preventDefault();
            var roomText = jQuery(this).text().trim();
            if (!roomText) return;
            var slug = roomText.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9\-]/g, '');
            var $option = jQuery('#schedule-select-rooms option[value="' + slug + '"]');
            if ($option.length) {
                jQuery('#schedule-select-rooms').val(slug).trigger('change');
                scrollTopMenu();
            }
        });

        // Clickable tags: clicking a tag name in the list sets the tag filter
        jQuery(document).on('click', '.schedule-tags.schedule-filter-link', function (e) {
            var target = jQuery(e.target);
            // If they clicked a specific tag word, use that; otherwise use the full text
            var tagText = target.text().trim().replace(/<\/?[^>]+(>|$)/g, '');
            if (!tagText) return;
            // Find matching option by display text
            var matched = false;
            jQuery('#schedule-select-tags option').each(function () {
                if (jQuery(this).text().trim().toLowerCase() === tagText.toLowerCase()) {
                    jQuery('#schedule-select-tags').val(jQuery(this).val()).trigger('change');
                    matched = true;
                    return false;
                }
            });
            if (matched) {
                scrollTopMenu();
            }
        });

        // Initial routing on load
        handleHashRouting();

        // Listen for hash changes
        window.addEventListener('hashchange', handleHashRouting);


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
