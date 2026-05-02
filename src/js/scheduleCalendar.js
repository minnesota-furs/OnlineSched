// @author Kurst Hyperyote for Furry Migration
import { openModal, closeModal } from './osModal.js';

export function rewriteGoogleCalendarUrlForAndroid(url) {
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

export function scheduleCalendar() {
    function isAndroidDevice() {
        return /android/i.test(navigator.userAgent);
    }
    window.isAndroidDevice = isAndroidDevice;

    // Takes a plain DOM element (a .schedule-item)
    function getEventDetailsFromElement(el) {
        let title = el.querySelector('.schedule-title a')?.textContent?.trim() ?? '';
        let descEl = el.querySelector('.schedule-description');
        let description = descEl ? descEl.innerHTML : '';
        let room = el.querySelector('.schedule-room')?.textContent?.trim() ?? '';
        let tags = el.querySelector('.schedule-tags')?.textContent?.trim() ?? '';
        let panelists = el.querySelector('.schedule-panelists')?.textContent?.trim() ?? '';
        let endTimestamp = parseInt(el.dataset.endTime, 10);
        let durationText = el.querySelector('.schedule-time')?.textContent ?? '';
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
        let gcalDates = gcalStart + '/' + gcalEnd;
        let gcalDetails = description ? description.replace(/<[^>]+>/g, '') : '';
        if (panelists) gcalDetails += '\nPanelists: ' + panelists.replace(/<[^>]+>/g, '');
        if (tags) gcalDetails += '\nTags: ' + tags.replace(/<[^>]+>/g, '');
        let gcalLocation = room ? room.replace(/<[^>]+>/g, '') : '';
        return {
            title, description, room, tags, panelists,
            endTimestamp, startTimestamp,
            gcalStart, gcalEnd, gcalDates, gcalDetails, gcalLocation
        };
    }
    window.getEventDetailsFromElement = getEventDetailsFromElement;

    function showCopyConfirm(el) {
        if (!el) return;
        el.style.display = '';
        el.classList.add('os-copy-confirm');
        el.addEventListener('animationend', function handler() {
            el.style.display = 'none';
            el.classList.remove('os-copy-confirm');
            el.removeEventListener('animationend', handler);
        });
    }

    function showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetails) {
        const modal = document.getElementById('android-google-calendar-modal');
        if (!modal) return;

        if (eventDetails) {
            modal.classList.remove('android-gcal-options-four');
            modal.classList.add('android-gcal-options-five');
        } else {
            modal.classList.remove('android-gcal-options-five');
            modal.classList.add('android-gcal-options-four');
        }

        const onetimeSection = modal.querySelector('.android-gcal-onetime-section');
        const onetimeBtn = modal.querySelector('.android-gcal-onetime-btn');
        const onetimeLink = modal.querySelector('.android-gcal-onetime-link');
        const copyConfirm = modal.querySelector('#android-gcal-copy-confirm');

        onetimeSection.style.display = 'none';
        onetimeBtn.style.display = 'none';
        if (onetimeLink) onetimeLink.textContent = '';

        let gcalUrl = null;
        if (eventDetails) {
            gcalUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE' +
                '&text=' + encodeURIComponent(eventDetails.title) +
                '&details=' + encodeURIComponent(eventDetails.details) +
                '&location=' + encodeURIComponent(eventDetails.location) +
                '&dates=' + eventDetails.dates;
            onetimeSection.style.display = '';
            if (onetimeLink) onetimeLink.textContent = gcalUrl;
            onetimeBtn.style.display = '';
            onetimeBtn.setAttribute('data-gcal-url', gcalUrl);
            onetimeBtn.onclick = function (e) {
                e.preventDefault();
                window.gtag_event && window.gtag_event('click', 'engagement', 'android-onetime-google-event');
                window.open(gcalUrl, '_blank');
                closeModal('android-google-calendar-modal');
            };
        } else {
            onetimeBtn.onclick = null;
        }

        const tryLink = modal.querySelector('#android-gcal-try-link');
        tryLink.onclick = function () {
            window.gtag_event && window.gtag_event('click', 'engagement', 'android-try-google-calendar');
            window.open(googleUrl, '_blank');
            closeModal('android-google-calendar-modal');
        };

        const downloadLink = modal.querySelector('#android-gcal-download');
        const httpsDownloadUrl = downloadUrl.replace(/^webcal:\/\//i, 'https://').replace(/^http:\/\//i, 'https://');
        downloadLink.setAttribute('href', httpsDownloadUrl);
        downloadLink.setAttribute('download', 'schedule.ics');
        downloadLink.onclick = function (e) {
            window.gtag_event && window.gtag_event('click', 'engagement', 'android-download-ics');
            e.preventDefault();
            var a = document.createElement('a');
            a.href = httpsDownloadUrl;
            a.download = 'schedule.ics';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        };

        const copyBtn = modal.querySelector('#android-gcal-copy');
        copyBtn.onclick = function () {
            window.gtag_event && window.gtag_event('click', 'engagement', 'android-copy-calendar-link');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(rawLink).then(function () {
                    showCopyConfirm(copyConfirm);
                });
            } else {
                var temp = document.createElement('input');
                temp.value = rawLink;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                showCopyConfirm(copyConfirm);
            }
        };

        openModal('android-google-calendar-modal');
    }
    window.showAndroidGoogleCalendarModal = showAndroidGoogleCalendarModal;

    // Modal Google Calendar button (Android logic)
    const modalGoogleBtn = document.getElementById('modal-schedule-google');
    if (modalGoogleBtn) {
        modalGoogleBtn.addEventListener('click', function (e) {
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
                var googleUrl = this.getAttribute('href');
                let rawLink = '';
                try {
                    let urlObj = new URL(googleUrl);
                    let cid = urlObj.searchParams.get('cid');
                    if (cid) rawLink = decodeURIComponent(cid);
                } catch (err) {
                    rawLink = googleUrl;
                }
                let downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;
                showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, eventDetailsForModal);
                return false;
            }
        });
    }

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
            if (cid) rawLink = decodeURIComponent(cid);
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
        const scheduleConfig = window.OS_SCHEDULE_CONFIG || {};
        let calendarName = scheduleConfig.calendarName || `Event Schedule ${year}`;
        let webcalUrl = generate_ical_url();
        let outlookUrl = 'https://outlook.office.com/owa/?path=/calendar/action/compose&rru=addsubscription';
        outlookUrl += '&url=' + encodeURIComponent(webcalUrl);
        outlookUrl += '&name=' + encodeURIComponent(calendarName);
        window.open(outlookUrl, '_blank');
        window.gtag_event && window.gtag_event('click', 'engagement', 'subscribe-outlook-calendar');
    };

    function generate_ical_url() {
        let url = '';
        const searchText = document.getElementById('schedule-search-text');
        if (searchText && searchText.value.trim() !== '') {
            url = '?room=all';
        } else {
            const tagsSelect = document.getElementById('schedule-select-tags');
            const selectedTagText = tagsSelect ? tagsSelect.options[tagsSelect.selectedIndex]?.text : null;
            const tagSlug = (selectedTagText && window.scheduleMasterTags) ? window.scheduleMasterTags[selectedTagText] : null;
            if (tagSlug) url = '&tag=' + tagSlug;

            const roomsSelect = document.getElementById('schedule-select-rooms');
            const selectedRoomText = roomsSelect ? roomsSelect.options[roomsSelect.selectedIndex]?.text : null;
            const roomSlug = (selectedRoomText && window.scheduleMasterRooms) ? window.scheduleMasterRooms[selectedRoomText] : null;
            if (roomSlug) url += '&room=' + roomSlug;

            if (url === '') {
                url = '?room=all';
            } else {
                url = '?' + url.slice(1);
            }
        }
        return 'webcal://' + window.location.host + '/wp-content/plugins/OnlineSched/icalby.php' + url;
    }
}
