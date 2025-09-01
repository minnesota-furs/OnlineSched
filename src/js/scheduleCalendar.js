export function scheduleCalendar() {
    // --- Google, Apple, Outlook Calendar logic ---
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
        return url;
    }

    // Utility: is Android device
    function isAndroidDevice() {
        return /android/i.test(navigator.userAgent);
    }
    window.isAndroidDevice = isAndroidDevice;

    // Utility: extract event details from a schedule-item element
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
    window.getEventDetailsFromElement = getEventDetailsFromElement;

    // --- Android Google Calendar Modal logic ---
    function showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetails) {
        // Determine if we should show four or five options
        var $modal = jQuery('#android-google-calendar-modal');
        if (eventDetails) {
            $modal.removeClass('android-gcal-options-four').addClass('android-gcal-options-five');
        } else {
            $modal.removeClass('android-gcal-options-five').addClass('android-gcal-options-four');
        }
        $modal.modal('show');
        var $onetimeSection = $modal.find('.android-gcal-onetime-section');
        var $onetimeLink = $modal.find('.android-gcal-onetime-link');
        var $onetimeBtn = $modal.find('.android-gcal-onetime-btn');
        $onetimeSection.hide();
        $onetimeBtn.hide();
        $onetimeLink && $onetimeLink.text("");
        var gcalUrl = null;
        if (eventDetails) {
            gcalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE' +
                '&text=' + encodeURIComponent(eventDetails.title) +
                '&details=' + encodeURIComponent(eventDetails.details) +
                '&location=' + encodeURIComponent(eventDetails.location) +
                '&dates=' + eventDetails.dates;
            $onetimeSection.show();
            $onetimeLink && $onetimeLink.text(gcalUrl);
            $onetimeBtn.show();
            $onetimeBtn.attr('data-gcal-url', gcalUrl);
            $onetimeBtn.off('click').on('click', function(e) {
                e.preventDefault();
                window.gtag_event && window.gtag_event('click', 'engagement', 'android-onetime-google-event');
                window.open(gcalUrl, '_blank');
                $modal.modal('hide');
            });
        } else {
            $onetimeBtn.off('click');
        }
        $modal.find('#android-gcal-try-link').off('click').on('click', function () {
            window.gtag_event && window.gtag_event('click', 'engagement', 'android-try-google-calendar');
            window.open(googleUrl, '_blank');
            $modal.modal('hide');
        });
        $modal.find('#android-gcal-download').off('click').on('click', function () {
            window.gtag_event && window.gtag_event('click', 'engagement', 'android-download-ics');
            // Always use https for downloadUrl
            var httpsDownloadUrl = downloadUrl.replace(/^webcal:\/\//i, 'https://');
            var a = document.createElement('a');
            a.href = httpsDownloadUrl;
            a.download = 'schedule.ics';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
        $modal.find('#android-gcal-copy').off('click').on('click', function () {
            window.gtag_event && window.gtag_event('click', 'engagement', 'android-copy-calendar-link');
            var linkToCopy = rawLink;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(linkToCopy).then(function () {
                    $modal.find('#android-gcal-copy-confirm').show().delay(1500).fadeOut();
                });
            } else {
                var temp = document.createElement('input');
                temp.value = linkToCopy;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                $modal.find('#android-gcal-copy-confirm').show().delay(1500).fadeOut();
            }
        });
        // Modal close logic
        $modal.find('#android-google-calendar-modal-close').off('click').on('click', function(e) {
            e.preventDefault();
            $modal.modal('hide');
        });
        $modal.off('click').on('click', function(e) {
            if (e.target === this) {
                $modal.modal('hide');
            }
        });
    }
    window.showAndroidGoogleCalendarModal = showAndroidGoogleCalendarModal;

    // --- Calendar button handlers ---
    jQuery(document).ready(function () {
        // Modal Google Calendar button (Android logic)
        jQuery("#modal-schedule-google").off('click').on('click', function(e) {
            if (isAndroidDevice()) {
                e.preventDefault();
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
        });

        // Bottom Add to Calendar buttons
        window.open_calendar_apple = function () {
            let url = generate_ical_url();
            window.open(url);
            window.gtag_event && window.gtag_event('click', 'engagement', 'subscribe-apple-calendar');
        };
        window.open_calendar_google = function () {
            let url = generate_ical_url();
            let googleUrl = 'https://calendar.google.com/calendar/r?cid=' + encodeURIComponent(url);
            googleUrl = rewriteGoogleCalendarUrlForAndroid(googleUrl);
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
            let downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;
            window.gtag_event && window.gtag_event('click', 'engagement', 'subscribe-google-calendar');
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
            let outlookUrl = 'https://outlook.office.com/owa/?path=/calendar/action/compose&rru=addsubscription';
            outlookUrl += '&url=' + encodeURIComponent(webcalUrl);
            outlookUrl += '&name=' + encodeURIComponent(calendarName);
            window.open(outlookUrl, '_blank');
            window.gtag_event && window.gtag_event('click', 'engagement', 'subscribe-outlook-calendar');
        };

        // Utility: Generate ICS/webcal URL for calendar feed
        function generate_ical_url() {
            let url = "";
            if (jQuery('#schedule-search-text').val().trim() != '') {
                url = "?room=all";
            } else {
                var selectedTag = jQuery("#schedule-select-tags").find('option:selected').text();
                var tagSlug = window.scheduleMasterTags ? window.scheduleMasterTags[selectedTag] : null;
                if (tagSlug) {
                    url = "&tag=" + tagSlug;
                }
                var selectedRoom = jQuery("#schedule-select-rooms").find('option:selected').text();
                var roomSlug = window.scheduleMasterRooms ? window.scheduleMasterRooms[selectedRoom] : null;
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
}
