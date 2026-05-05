/* Schedule code to do filtering and the magic */
import { rewriteGoogleCalendarUrlForAndroid } from './scheduleCalendar.js';
import { openModal } from './osModal.js';
import { updateIconClasses } from './osIcons.js';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

function showElement(el) {
    if (el) el.style.display = '';
}

function hideElement(el) {
    if (el) el.style.display = 'none';
}

function isVisible(el) {
    if (!el) return false;

    let current = el;
    while (current && current.nodeType === Node.ELEMENT_NODE) {
        if (current.style.display === 'none') {
            return false;
        }
        current = current.parentElement;
    }

    return true;
}

function stripTags(value) {
    const holder = document.createElement('div');
    holder.innerHTML = value || '';
    return holder.textContent || holder.innerText || '';
}

function roomSlug(room) {
    return room.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
}

function dispatchChange(el) {
    if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
}

export function new_schedule() {
    const scheduleConfig = window.OS_SCHEDULE_CONFIG || {};
    const header_top = Number.parseInt(scheduleConfig.stickyOffsetDesktop ?? 0, 10) || 0;
    const header_mobile_top = Number.parseInt(scheduleConfig.stickyOffsetMobile ?? header_top, 10) || 0;
    const tablet_width = Number.parseInt(scheduleConfig.stickyBreakpoint ?? 991, 10) || 991;
    const fixed_tabs_height = Number.parseInt(scheduleConfig.fixedTabsHeight ?? 40, 10) || 40;

    function normalizeRouteKey(text) {
        return (text || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function getHashState() {
        const rawHash = window.location.hash.substring(1);
        if (rawHash && !rawHash.includes('=')) {
            if (rawHash.startsWith('evt-')) return { evt: rawHash.substring(4) };
            if (rawHash.startsWith('tag-')) return { tag: rawHash.substring(4) };
            if (rawHash.startsWith('room-')) return { room: rawHash.substring(5) };
            if (rawHash === 'hours' || rawHash === 'hour') return { tab: 'hours' };
            if (rawHash === 'essentials') return { tab: 'essentials' };
            if (rawHash === 'programming') return { tab: 'programming' };
        }
        const params = new URLSearchParams(rawHash);
        return Object.fromEntries(params.entries());
    }

    function updateHashState(updates, replace = true) {
        const current = getHashState();
        const next = { ...current, ...updates };
        Object.keys(next).forEach(k => (!next[k]) && delete next[k]);
        const newHash = new URLSearchParams(next).toString();
        const url = window.location.pathname + window.location.search + (newHash ? '#' + newHash : '');
        if (replace) {
            history.replaceState(next, '', url);
        } else {
            history.pushState(next, '', url);
        }
    }

    function getSelectedTagRouteValue() {
        const select = $('#schedule-select-tags');
        const text = select?.options[select.selectedIndex]?.textContent?.trim();
        return normalizeRouteKey(text);
    }

    function selectTagFromRouteValue(tagSlug) {
        const select = $('#schedule-select-tags');
        if (!select || !tagSlug) return;
        for (const option of select.options) {
            const text = option.textContent.trim();
            const optionSlug = normalizeRouteKey(text);
            if (optionSlug === tagSlug) {
                select.value = option.value;
                return;
            }
        }
    }

    function routeToEvent(eventId) {
        updateHashState({ evt: eventId.replace('onlineevt-', '') }, false);
        const item = document.getElementById(eventId.startsWith('#') ? eventId.substring(1) : eventId);
        if (item) {
            let offset = item.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset(true);
            if (offset < 0) offset = 0;
            window.scrollTo({ top: offset, behavior: 'smooth' });
        }
        setTimeout(() => {
            openEventModal(eventId);
        }, 300);
    }

    function currentStickyOffset(includeTabs = false) {
        const width = document.body ? document.body.getBoundingClientRect().width : window.innerWidth;
        const offset = width <= tablet_width ? header_mobile_top : header_top;
        return includeTabs ? offset + fixed_tabs_height : offset;
    }

    function scrollPageTo(top, behavior) {
        if (behavior !== 'auto') {
            window.scrollTo({ top, behavior });
            return;
        }

        window.scrollTo(0, top);
        [
            document.scrollingElement,
            document.documentElement,
            document.body,
            $('#body')
        ].forEach((el) => {
            if (el) {
                el.scrollTop = top;
            }
        });
    }

    window.scrollTopMenu = function () {
        const schedule = $('#schedule');
        if (!schedule) return;

        const isKiosk = schedule.classList.contains('kiosk-schedule');
        let offset = schedule.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset();
        if (isKiosk) {
            // In kiosk mode, there's no site header, so scrolling to 0 keeps the title's natural top spacing visible
            offset = 0;
        } else if (offset < 0) {
            offset = 0;
        }

        scrollPageTo(offset, isKiosk ? 'auto' : 'smooth');
    };

    function animate_clipboard(clipObject) {
        if (!clipObject) return;

        clipObject.blur();
        clipObject.dispatchEvent(new Event('focusout', { bubbles: true }));

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            return;
        }

        const clipboardEffect = document.createElement('div');
        clipboardEffect.className = 'os-clipboard-effect';
        clipboardEffect.innerHTML = '<i class="fas fa-clipboard-check"></i> Copied!';

        // showModal() promotes a <dialog> to the browser top layer, which renders above
        // everything in the normal stacking context regardless of z-index. Appending the
        // effect to document.body would put it behind the dialog backdrop and make it
        // invisible. When the trigger lives inside an open <dialog>, we must append the
        // effect inside that dialog so it shares the same top-layer rendering context.
        const parentDialog = clipObject.closest('dialog');
        const container = (parentDialog && parentDialog.open) ? parentDialog : document.body;

        clipboardEffect.style.position = 'fixed';
        clipboardEffect.style.visibility = 'hidden';
        container.appendChild(clipboardEffect);

        const rect = clipObject.getBoundingClientRect();
        const effectWidth = clipboardEffect.offsetWidth;
        const effectHeight = clipboardEffect.offsetHeight;
        const topPosition = rect.top - effectHeight - 10;
        const leftPosition = rect.left + (clipObject.offsetWidth / 2) - (effectWidth / 2);

        clipboardEffect.style.top = `${topPosition}px`;
        clipboardEffect.style.left = `${leftPosition}px`;
        clipboardEffect.style.visibility = '';

        clipboardEffect.addEventListener('animationend', () => clipboardEffect.remove(), { once: true });
        window.setTimeout(() => clipboardEffect.remove(), 1800);

        clipObject.classList.add('os-copy-confirm');
        clipObject.addEventListener('animationend', () => clipObject.classList.remove('os-copy-confirm'), { once: true });
    }

    $('#modal-copy-url')?.addEventListener('click', function (e) {
        e.preventDefault();
        navigator.clipboard?.writeText(window.location.href);
        animate_clipboard(this);
    });

    $$('.schedule-clipboard').forEach((button) => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const item = this.closest('.schedule-item');
            if (!item || !item.id) return;

            const eventID = item.id.replace('onlineevt-', '');
            const nextState = { ...getHashState(), evt: eventID };
            const newHash = new URLSearchParams(nextState).toString();
            const url = window.location.origin + window.location.pathname + window.location.search + '#' + newHash;
            navigator.clipboard?.writeText(url);
            animate_clipboard(this);
        });
    });

    document.addEventListener('os:tab:shown', function (e) {
        const hash = e.detail.hash;
        if (hash && e.detail.isTrusted) {
            const tabName = hash.substring(1);
            if (tabName !== 'programming') {
                updateHashState({ day: null, tag: null, room: null, q: null, evt: null, tab: tabName }, false);
            } else {
                updateHashState({ day: null, tag: null, room: null, q: null, evt: null, tab: null }, false);
            }

            resetDropDowns();
            window.favoritesFilterActive = false;
            const favoritesToggle = $('#schedule-favorites-toggle');
            if (favoritesToggle) {
                favoritesToggle.classList.remove('active');
                favoritesToggle.setAttribute('aria-pressed', 'false');
            }
        }

        if (hash === '#programming' || hash === '#essentials') {
            scheduleSort();
            resetSelectTags();
            resetSelectRooms();
            updateResetButtonState();
        }

        window.requestAnimationFrame(() => window.scrollTopMenu?.());
    });

    function openEventModal(eventId) {
        const item = document.getElementById(eventId.startsWith('#') ? eventId.substring(1) : eventId);
        if (!item) return;

        if (!window.getEventDetailsFromElement) return;
        const eventDetails = window.getEventDetailsFromElement(item);
        window.currentModalEventDetails = eventDetails;

        const panelists = eventDetails.panelists;
        const titleLink = item.querySelector('.schedule-title a');
        const title = titleLink ? titleLink.innerHTML : '';
        const description = eventDetails.description;
        const day = item.closest('.schedule-day');
        const date = day?.querySelector('h2')?.innerHTML || '';
        const time = item.querySelector('.schedule-time span')?.innerHTML || '';
        const room = eventDetails.room;
        const tags = eventDetails.tags;
        const ical = item.querySelector('.schedule-ical')?.getAttribute('href') || '#';
        const googleCal = item.querySelector('.schedule-google')?.getAttribute('href') || '#';

        const badges = item.querySelector('.schedule-title')?.cloneNode(true);
        badges?.querySelectorAll('a').forEach((anchor) => anchor.remove());
        const badgesHtml = badges?.innerHTML || '';

        let isFavorite = item.getAttribute('data-favorite') === 'true';
        const raw_id = item.getAttribute('id');
        let favBtn = '';
        if (!scheduleConfig.isKiosk && !scheduleConfig.isLive) {
            const config = window.OnlineSchedPublic || {};
            const iconClass = isFavorite ? (config.iconFavActive || 'fas fa-star') : (config.iconFavInactive || 'far fa-star');
            favBtn = '<button type="button" class="schedule-favorite-toggle' + (isFavorite ? ' active' : '') + '" aria-pressed="' + (isFavorite ? 'true' : 'false') + '" title="Favorite" style="margin-right:8px;"><i class="' + iconClass + '"></i></button>';
        }

        const modalTitle = $('#modal-schedule-title');
        if (modalTitle) {
            modalTitle.innerHTML = favBtn + title + badgesHtml;
        }

        function updateModalFavoriteStar(state) {
            const modalBtn = $('#modal-schedule-title .schedule-favorite-toggle');
            if (!modalBtn) return;
            modalBtn.classList.toggle('active', state);
            modalBtn.setAttribute('aria-pressed', state ? 'true' : 'false');
            const icon = modalBtn.querySelector('i');
            updateIconClasses(icon, state);
        }

        // Re-attach favorite listener
        $('#modal-schedule-title .schedule-favorite-toggle')?.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const mainItem = document.getElementById(raw_id);
            const btn = mainItem?.querySelector('.schedule-favorite-toggle');
            isFavorite = !isFavorite;

            if (mainItem) {
                if (isFavorite) {
                    mainItem.setAttribute('data-favorite', 'true');
                } else {
                    mainItem.removeAttribute('data-favorite');
                }

                if (btn) {
                    btn.classList.toggle('active', isFavorite);
                    btn.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
                    const icon = btn.querySelector('i');
                    icon?.classList.toggle('fas', isFavorite);
                    icon?.classList.toggle('far', !isFavorite);
                }
            }

            updateModalFavoriteStar(isFavorite);
            window.updateFavoritesCookie?.();
        });

        const descriptionEl = $('#modal-schedule-description');
        if (descriptionEl) {
            showElement(descriptionEl);
            descriptionEl.innerHTML = description;
            const hr = descriptionEl.parentElement?.querySelector('hr');
            if (hr) showElement(hr);
        }

        modal_popup_fill('#modal-schedule-date', date);
        modal_popup_fill('#modal-schedule-time', time);
        modal_popup_fill('#modal-schedule-room', room);
        modal_popup_fill('#modal-schedule-tags', tags);
        modal_popup_fill('#modal-schedule-panelists', panelists);

        $('#modal-schedule-ical')?.setAttribute('href', ical);
        $('#modal-schedule-google')?.setAttribute('href', googleCal);

        window.currentModalEventId = '#' + item.id;

        openModal('modal-schedule');
        document.getElementById('modal-schedule')?.addEventListener('close', () => {
            updateHashState({ evt: null }, true);
        }, { once: true });
    }

    $$('a[data-target="#modal-schedule"]').forEach((link) => {
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            const parent = this.closest('.schedule-item');
            if (parent) routeToEvent(parent.id);
        });
    });

    function modal_popup_fill(id, value) {
        const el = $(id);
        if (!el) return;

        const label = el.previousElementSibling;
        if (value === undefined || value === '') {
            hideElement(label);
            hideElement(el);
        } else {
            el.innerHTML = value;
            showElement(el);
            showElement(label);
        }
    }

    $$('.schedule-day').forEach((dayEl) => {
        const day = dayEl.dataset.scheduleDay;
        if (day) {
            $('#schedule-select-days')?.append(new Option(day, day));
        }
    });

    window.eventschedule_showEvents = true;
    window.favoritesFilterActive = false;

    window.setFilterEvents = function (args) {
        scrollTopMenu();
        window.eventschedule_showEvents = args;
        scheduleSort();
        resetSelectTags();
    };

    window.eventschedule_scheduleTags = {};
    window.eventschedule_count = 0;

    $$('.schedule-tags').forEach((tagsEl) => {
        const tags = tagsEl.innerHTML.split(',').map((tag) => tag.trim());
        const item = tagsEl.closest('.schedule-item');

        tags.forEach((tagHtml) => {
            const tag = stripTags(tagHtml).trim();
            if (!tag) return;

            if (!Object.prototype.hasOwnProperty.call(window.eventschedule_scheduleTags, tag)) {
                window.eventschedule_scheduleTags[tag] = window.eventschedule_count++;
            }

            item?.setAttribute(
                `data-schedule-tag${window.eventschedule_scheduleTags[tag]}`,
                window.eventschedule_scheduleTags[tag]
            );
        });
    });

    for (const key in window.eventschedule_scheduleTags) {
        if (typeof key === 'string' && key.trim().length !== 0) {
            $('#schedule-select-tags')?.append(new Option(key, window.eventschedule_scheduleTags[key]));
        }
    }

    window.eventschedule_scheduleRooms = {};
    $$('.schedule-room').forEach((roomEl) => {
        const rooms = roomEl.innerHTML.split(',').map((room) => stripTags(room).trim()).filter(Boolean);
        const parentItem = roomEl.closest('.schedule-item');

        rooms.forEach((room) => {
            const slug = roomSlug(room);
            if (!Object.prototype.hasOwnProperty.call(window.eventschedule_scheduleRooms, slug)) {
                window.eventschedule_scheduleRooms[slug] = room;
            }
            parentItem?.setAttribute(`data-schedule-room-${slug}`, slug);
        });
    });

    function updateResetButtonState() {
        const isDefault = (
            ($('#schedule-search-text')?.value || '').trim() === '' &&
            $('#schedule-select-tags')?.value === 'all' &&
            $('#schedule-select-rooms')?.value === 'all' &&
            $('#schedule-select-days')?.value === 'Current' &&
            !window.favoritesFilterActive
        );
        if ($('#schedule-reset')) {
            $('#schedule-reset').disabled = isDefault;
        }
    }

    updateResetButtonState();
    resetSelectRooms();

    $$('#schedule-select-days, #schedule-select-tags, #schedule-select-rooms').forEach((select) => {
        select.addEventListener('change', function () {
            if (this.id === 'schedule-select-days') updateHashState({ day: this.value === 'Current' ? null : this.value }, true);
            else if (this.id === 'schedule-select-tags') updateHashState({ tag: this.value === 'all' ? null : getSelectedTagRouteValue() }, true);
            else if (this.id === 'schedule-select-rooms') updateHashState({ room: this.value === 'all' ? null : this.value }, true);
            
            scheduleSort();
            resetSelectTags();
            resetSelectRooms();
            updateResetButtonState();
        });
    });

    $('#schedule-search-text')?.addEventListener('input', function () {
        updateHashState({ q: this.value.trim() || null }, true);
        scheduleSort();
        resetSelectTags();
        resetSelectRooms();
        updateResetButtonState();
    });

    $('#schedule-reset')?.addEventListener('click', function () {
        updateHashState({ day: null, tag: null, room: null, q: null }, true);
        resetDropDowns();
        window.favoritesFilterActive = false;
        const favoritesToggle = $('#schedule-favorites-toggle');
        if (favoritesToggle) {
            favoritesToggle.classList.remove('active');
            favoritesToggle.setAttribute('aria-pressed', 'false');
        }
        scheduleSort();
        resetSelectTags();
        resetSelectRooms();
        this.disabled = true;
    });

    $('#schedule-favorites-toggle')?.addEventListener('click', function () {
        window.favoritesFilterActive = !window.favoritesFilterActive;
        this.classList.toggle('active', window.favoritesFilterActive);
        this.setAttribute('aria-pressed', window.favoritesFilterActive ? 'true' : 'false');

        const icon = this.querySelector('i');
        updateIconClasses(icon, window.favoritesFilterActive);

        if (window.favoritesFilterActive && $('#schedule-select-days')) {
            $('#schedule-select-days').value = 'all';
        }

        scheduleSort();
        resetSelectTags();
        resetSelectRooms();
        this.blur();
        updateResetButtonState();
    });

    function setOddEven() {
        $$('.schedule-item').forEach((item) => item.classList.remove('even'));
        $$('.schedule-hour').forEach((hour) => {
            let even = false;
            $$('.schedule-item', hour).forEach((item) => {
                if (!isVisible(item)) return;

                if (even) {
                    item.classList.add('even');
                    even = false;
                } else {
                    even = true;
                }
            });
        });
    }

    function resetDropDowns() {
        if ($('#schedule-select-days')) $('#schedule-select-days').value = 'Current';
        if ($('#schedule-select-tags')) $('#schedule-select-tags').value = 'all';
        if ($('#schedule-select-rooms')) $('#schedule-select-rooms').value = 'all';
        if ($('#schedule-search-text')) $('#schedule-search-text').value = '';
    }

    function hasMatchingAttribute(item, prefix, value) {
        if (!item || !item.attributes) return false;

        for (const attr of item.attributes) {
            if (attr.name.startsWith(prefix) && String(attr.value) === String(value)) {
                return true;
            }
        }

        return false;
    }

    function scheduleSort() {
        if (!$('#schedule')) {
            return;
        }

        let selectedDay = $('#schedule-select-days')?.value || 'Current';
        let selectedTag = $('#schedule-select-tags')?.value || 'all';
        let selectedRoom = $('#schedule-select-rooms')?.value || 'all';
        const searchText = ($('#schedule-search-text')?.value || '').toLowerCase();

        if (selectedTag !== 'all') selectedTag = String(selectedTag);
        if (selectedRoom !== 'all') selectedRoom = String(selectedRoom);

        const favoritesFilterActive = window.favoritesFilterActive;
        const essentialsFilterActive = (window.eventschedule_showEvents === false);
        const essentialsTags = window.essentialsTags || [];

        if (selectedDay === 'all' || selectedDay === 'Current') {
            $$('.schedule-day').forEach(showElement);
        } else {
            $$('.schedule-day').forEach((dayEl) => {
                if (dayEl.getAttribute('data-schedule-day') === selectedDay) {
                    showElement(dayEl);
                } else {
                    hideElement(dayEl);
                }
            });
        }

        const currentDateUTC = selectedDay === 'Current' ? currentDateTimeTimestampUTC() : null;

        $$('.schedule-item').forEach((item) => {
            let show = true;

            const day = item.closest('.schedule-day');
            if (selectedDay !== 'Current' && !isVisible(day)) {
                show = false;
            }

            if (essentialsFilterActive) {
                let hasEssentialsTag = false;
                for (let i = 0; i < essentialsTags.length; i++) {
                    if (item.classList.contains('schedule-tag-' + essentialsTags[i])) {
                        hasEssentialsTag = true;
                        break;
                    }
                }
                if (!hasEssentialsTag) {
                    show = false;
                }
            }

            if (selectedTag !== 'all' && !hasMatchingAttribute(item, 'data-schedule-tag', selectedTag)) {
                show = false;
            }

            if (selectedRoom !== 'all' && !hasMatchingAttribute(item, 'data-schedule-room-', selectedRoom)) {
                show = false;
            }

            if (selectedDay === 'Current') {
                const itemDate = Number(item.dataset.endTime);
                if (!itemDate || itemDate <= currentDateUTC) {
                    show = false;
                }
            }

            if (searchText !== '') {
                const found = $$('.schedule-title a, .schedule-panelists, .schedule-tags, .schedule-room', item)
                    .some((el) => el.textContent.toLowerCase().indexOf(searchText) !== -1);
                if (!found) {
                    show = false;
                }
            }

            if (favoritesFilterActive && item.getAttribute('data-favorite') !== 'true') {
                show = false;
            }

            if (show) {
                showElement(item);
            } else {
                hideElement(item);
            }
        });

        const isDefault = (
            searchText.trim() === '' &&
            selectedTag === 'all' &&
            selectedRoom === 'all' &&
            selectedDay === 'Current' &&
            !favoritesFilterActive
        );

        if ($('#schedule-reset')) {
            $('#schedule-reset').disabled = isDefault;
        }

        reset_schedule(true);
        messageAtBottomForCalendar();
    }

    function resetHoursDays() {
        $$('.schedule-hour').forEach((hour) => {
            showElement(hour);
            const visibleLength = Array.from(hour.children).filter(isVisible).length;
            if (visibleLength < 2) {
                hideElement(hour);
            }
        });

        $$('.schedule-day').forEach((day) => {
            showElement(day);
            const visibleLength = Array.from(day.children).filter(isVisible).length;
            if (visibleLength < 2) {
                hideElement(day);
            }
        });
    }

    function reset_schedule(resetTags) {
        resetHoursDays();
        if (resetTags) {
            resetSelectTags();
            resetSelectRooms();
        }
        sort_options_by_id('#schedule-select-tags');
        sort_rooms_options_by_id('#schedule-select-rooms');
        setOddEven();
    }

    function resetSelectTags() {
        const scheduleReset = {};
        $$('.schedule-item').filter(isVisible).forEach((item) => {
            $$('.schedule-tags', item).forEach((tagsEl) => {
                const tags = tagsEl.innerHTML.split(',').map((tag) => tag.trim());
                tags.forEach((tagHtml) => {
                    const tag = stripTags(tagHtml).trim();
                    if (tag && !Object.prototype.hasOwnProperty.call(scheduleReset, tag)) {
                        scheduleReset[tag] = tag;
                    }
                });
            });
        });

        const select = $('#schedule-select-tags');
        if (!select) return;

        const selectedTagValue = select.value;
        Array.from(select.options).forEach((option) => {
            if (option.value !== 'all') {
                option.remove();
            }
        });

        for (const key in scheduleReset) {
            select.append(new Option(key, window.eventschedule_scheduleTags[key]));
        }

        if (selectedTagValue !== 'all') {
            select.value = selectedTagValue;
        }

        sort_options_by_id('#schedule-select-tags');
        setOddEven();
    }

    function resetSelectRooms() {
        const scheduleRooms = {};
        $$('.schedule-item').filter(isVisible).forEach((item) => {
            for (const attr of item.attributes) {
                if (attr.name.startsWith('data-schedule-room-')) {
                    const slug = attr.value;
                    const name = window.eventschedule_scheduleRooms?.[slug] || slug;
                    if (slug && !Object.prototype.hasOwnProperty.call(scheduleRooms, slug)) {
                        scheduleRooms[slug] = name;
                    }
                }
            }
        });

        const select = $('#schedule-select-rooms');
        if (!select) return;

        const selectedRoomValue = select.value;
        Array.from(select.options).forEach((option) => {
            if (option.value !== 'all') {
                option.remove();
            }
        });

        for (const slug in scheduleRooms) {
            select.append(new Option(scheduleRooms[slug], slug));
        }

        if (selectedRoomValue !== 'all') {
            select.value = selectedRoomValue;
        }

        sort_rooms_options_by_id('#schedule-select-rooms');
        setOddEven();
    }

    function sort_options_by_id(id) {
        const select = $(id);
        if (!select) return;

        const selected = select.value;
        const sorted = Array.from(select.options).sort((a, b) => {
            const aText = a.textContent.toUpperCase();
            const bText = b.textContent.toUpperCase();

            if (a.value === 'all') return -1;
            if (b.value === 'all') return 1;

            return aText === bText ? 0 : aText < bText ? -1 : 1;
        });

        select.replaceChildren(...sorted);
        select.value = selected;
    }

    function sort_rooms_options_by_id(id) {
        const select = $(id);
        if (!select) return;

        const selected = select.value;
        const sorted = Array.from(select.options).sort((a, b) => {
            const aText = a.textContent.toUpperCase();
            const bText = b.textContent.toUpperCase();

            if (aText !== 'ALL ROOMS' && bText !== 'ALL ROOMS') {
                if (aText === 'MAINSTAGE') return -1;
                if (bText === 'MAINSTAGE') return 1;
                if (aText === 'SPECIAL EVENTS') return 1;
                if (bText === 'SPECIAL EVENTS') return -1;
            }

            return aText === bText ? 0 : aText < bText ? -1 : 1;
        });

        select.replaceChildren(...sorted);
        select.value = selected;
    }

    function handleHashRouting() {
        const state = getHashState();
        showElement($('#schedule'));

        // When restoring a room filter, the room dropdown is only populated from
        // currently-visible items. On a fresh load those are today-only events,
        // so the target room option may not exist yet. Expand to all days first
        // so every room slug is present in the dropdown, then apply the real state.
        if (state.room && state.room !== 'all') {
            const daysSelect = $('#schedule-select-days');
            if (daysSelect) daysSelect.value = 'all';
            scheduleSort();
        }

        if (state.day !== undefined && $('#schedule-select-days')) $('#schedule-select-days').value = state.day || 'Current';
        if (state.tag !== undefined && $('#schedule-select-tags')) selectTagFromRouteValue(state.tag);
        if (state.room !== undefined && $('#schedule-select-rooms')) $('#schedule-select-rooms').value = state.room || 'all';
        if (state.q !== undefined && $('#schedule-search-text')) $('#schedule-search-text').value = state.q || '';

        scheduleSort();

        if (state.tab === 'hours') {
            $('#hours-tab')?.click();
            scrollTopMenu();
        } else if (state.tab === 'essentials') {
            window.setFilterEvents(false);
            $('[data-os-tab="essentials"]')?.click();
        } else if (state.tab === 'programming') {
            $('[data-os-tab="programming"]')?.click();
        }

        if (state.evt) {
            const fullEventId = 'onlineevt-' + state.evt;
            const eventEl = document.getElementById(fullEventId);
            if (eventEl) {
                if (!isVisible(eventEl)) {
                    if ($('#schedule-select-days')) $('#schedule-select-days').value = 'all';
                    scheduleSort();
                }
                
                let offset = eventEl.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset(true);
                if (offset < 0) offset = 0;
                window.scrollTo({ top: offset, behavior: 'smooth' });

                setTimeout(() => {
                    const modal = $('#modal-schedule');
                    const isModalOpen = modal && modal.hasAttribute('open');
                    const isSameEvent = window.currentModalEventId === '#' + fullEventId;

                    if (!isModalOpen || !isSameEvent) {
                        openEventModal(fullEventId);
                    }
                }, 300);
            }
        } else {
            const modal = document.getElementById('modal-schedule');
            if (modal && modal.hasAttribute('open')) {
                modal.close();
            }
        }

        document.dispatchEvent(new CustomEvent('os:hash-routing:complete', { detail: { hash: window.location.hash } }));
    }

    document.addEventListener('click', function (e) {
        const room = e.target.closest('.schedule-room.schedule-filter-link');
        if (!room) return;

        e.preventDefault();
        const text = room.textContent.trim();
        if (!text) return;

        const slug = roomSlug(text);
        const select = $('#schedule-select-rooms');
        if (select && Array.from(select.options).some((option) => option.value === slug)) {
            select.value = slug;
            dispatchChange(select);
            scrollTopMenu();
        }
    });

    document.addEventListener('click', function (e) {
        const tags = e.target.closest('.schedule-tags.schedule-filter-link');
        if (!tags) return;

        const tagText = e.target.textContent.trim().replace(/<\/?[^>]+(>|$)/g, '');
        if (!tagText) return;

        const select = $('#schedule-select-tags');
        if (!select) return;

        let matched = false;
        for (const option of select.options) {
            if (option.textContent.trim().toLowerCase() === tagText.toLowerCase()) {
                select.value = option.value;
                dispatchChange(select);
                matched = true;
                break;
            }
        }

        if (matched) {
            scrollTopMenu();
        }
    });

    handleHashRouting();
    window.addEventListener('popstate', handleHashRouting);

    function messageAtBottomForCalendar() {
        const message = $('#schedule-add-to-calendar-message');
        if (!message) return;

        if (
            (($('#schedule-search-text')?.value || '').trim() === '') &&
            (($('#schedule-select-tags')?.value !== 'all') || ($('#schedule-select-rooms')?.value !== 'all'))
        ) {
            message.innerHTML = 'Add this filtered list to your calendar!';
        } else {
            message.innerHTML = 'Import the full schedule into your calendar!';
        }
    }

    function currentDateTimeTimestampUTC() {
        const utcDate = new Date();
        return utcDate.getTime() / 1000;
    }

    window.confirmCalendarAppleSubscription = function (link) {
        return confirmCalendarSubscription(link, 'Apple');
    };

    window.confirmCalendarGoogleSubscription = function (link) {
        if (window.isAndroidDevice?.()) {
            const googleUrl = rewriteGoogleCalendarUrlForAndroid(link.href);
            let rawLink = '';
            try {
                const urlObj = new URL(googleUrl);
                const cid = urlObj.searchParams.get('cid');
                if (cid) {
                    rawLink = decodeURIComponent(cid);
                }
            } catch (e) {
                rawLink = googleUrl;
            }

            const downloadUrl = rawLink.startsWith('webcal://') ? rawLink.replace('webcal://', 'https://') : rawLink;
            const scheduleItem = link.closest('.schedule-item');
            const eventDetails = scheduleItem && window.getEventDetailsFromElement ? window.getEventDetailsFromElement(scheduleItem) : null;
            let eventDetailsForModal = null;
            if (eventDetails) {
                eventDetailsForModal = {
                    title: eventDetails.title,
                    details: eventDetails.gcalDetails,
                    location: eventDetails.gcalLocation,
                    dates: eventDetails.gcalDates
                };
            }
            window.showAndroidGoogleCalendarModal?.(googleUrl, rawLink, downloadUrl, eventDetailsForModal);
            return false;
        }

        link.href = rewriteGoogleCalendarUrlForAndroid(link.href);
        return confirmCalendarSubscription(link, 'Google');
    };

    window.confirmCalendarSubscription = function (link, service) {
        if (confirm('This will subscribe you to your ' + service + ' calendar. This will keep updating until you delete it. Do you want to continue?')) {
            window.gtag_event?.('click', 'engagement', 'subscribe-' + service + '-calendar-single');
            setTimeout(function () {
                window.location.href = link.href;
            }, 300);
        }
        return false;
    };

    $('#login-modal-btn')?.addEventListener('click', (e) => {
        e.preventDefault();
        openModal('login-modal');
    });

    $('#info-modal-btn')?.addEventListener('click', (e) => {
        e.preventDefault();
        openModal('info-modal');
    });
}
