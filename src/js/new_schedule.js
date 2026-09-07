/* Schedule code to do filtering and the magic */
import { rewriteGoogleCalendarUrlForAndroid } from './scheduleCalendar.js';
import { isElementVisible } from './elementVisibility.js';
import { openModal } from './osModal.js';
import { updateIconClasses } from './osIcons.js';
import { createFilterPanels, refreshFilterPanels } from './scheduleFilterPanel.js';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

function showElement(el) {
    if (el) el.style.display = '';
}

function hideElement(el) {
    if (el) el.style.display = 'none';
}

// Declared ahead of everything that runs at init: scheduleSort fills these
// and is called before the later definitions execute.
let dayAvailability = new Set();
let tagAvailability = new Set();
let roomAvailability = new Set();

// What the person has narrowed to. An empty set means no restriction, which
// is what the single "All" option used to mean.
const filterState = {
    rooms: new Set(),
    tags: new Set(),
    // `all` and `Current` are modes rather than days, so they never coexist
    // with a chosen day. Null mode means the day set is in charge.
    dayMode: 'Current',
    days: new Set(),
};

// Every route that clears the filters clears both hash forms, or a plural set
// outlives the control that says it is gone.
const CLEARED_FILTER_KEYS = {
    day: null, tag: null, room: null,
    days: null, tags: null, rooms: null,
};

function splitFilterList(value) {
    return String(value ?? '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);
}

function joinFilterList(values) {
    return Array.from(values).join(',');
}

function setDayMode(mode) {
    filterState.dayMode = mode;
    filterState.days.clear();
}

function setDaySet(days) {
    filterState.days = new Set(days);
    filterState.dayMode = filterState.days.size ? null : 'Current';
}

function dayFilterIsOpen() {
    return filterState.dayMode === 'all' || filterState.dayMode === 'Current';
}

function stripTags(value) {
    const holder = document.createElement('div');
    holder.innerHTML = value || '';
    return holder.textContent || holder.innerText || '';
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

    function getTagRouteValueFromText(text) {
        return normalizeRouteKey(text);
    }

    function getScheduleTagItems(tagsEl) {
        const termItems = $$('.os-term-item', tagsEl)
            .map((termItem) => ({
                label: (termItem.dataset.osTermLabel || termItem.textContent.replace(/,\s*$/, '')).trim(),
                route: termItem.dataset.osTagRoute || getTagRouteValueFromText(termItem.textContent),
            }))
            .filter((tag) => tag.label);

        if (termItems.length) {
            return termItems;
        }

        return tagsEl.textContent
            .split(',')
            .map((tag) => tag.trim())
            .filter(Boolean)
            .map((tag) => ({
                label: tag,
                route: getTagRouteValueFromText(tag),
            }));
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
            if (rawHash === 'map') return { tab: 'map' };
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

    // One identity for a tag: the taxonomy slug. A label-derived key gave the
    // same tag three spellings, and "Music & Performance" matched none of them.
    function tagSlugForLabel(label) {
        const slug = window.scheduleMasterTags ? window.scheduleMasterTags[String(label).trim()] : null;
        return slug || null;
    }

    // Older links carry a label-derived key, and term markup carries one built
    // from an entity-encoded name, so both still resolve.
    function tagRouteKeys(option) {
        const label = option.textContent.trim();
        const keys = [normalizeRouteKey(label), normalizeRouteKey(label.replace(/&/g, 'amp'))];
        const slug = tagSlugForLabel(label);
        if (slug) keys.unshift(normalizeRouteKey(slug));
        return keys;
    }

    function tagRouteForValue(value) {
        const select = $('#schedule-select-tags');
        if (!select) return null;
        for (const option of select.options) {
            if (String(option.value) === String(value)) {
                return tagSlugForLabel(option.textContent) || getTagRouteValueFromText(option.textContent);
            }
        }
        return null;
    }

    function tagValueForRoute(route) {
        const select = $('#schedule-select-tags');
        if (!select || !route) return null;
        const normalized = normalizeRouteKey(route);
        for (const option of select.options) {
            if (tagRouteKeys(option).includes(normalized)) return option.value;
        }
        return null;
    }

    // A day value is a full label such as "Tuesday, September 15", so its own
    // comma would split the list it travels in.
    function dayRouteForValue(value) {
        if (value === 'all' || value === 'Current') return value;
        return String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }

    function dayValueForRoute(route) {
        if (route === 'all' || route === 'Current') return route;
        const select = $('#schedule-select-days');
        const normalized = normalizeRouteKey(route);
        for (const option of select?.options || []) {
            if (normalizeRouteKey(option.value) === normalized) return option.value;
        }
        return route;
    }

    // Emits the plural form only; the legacy singular keys are still read on
    // the way in, so old bookmarks and inbound links keep working.
    function writeFilterHash() {
        const days = filterState.dayMode === 'Current'
            ? null
            : (filterState.dayMode === 'all'
                ? 'all'
                : joinFilterList(Array.from(filterState.days).map(dayRouteForValue)));
        const tagRoutes = Array.from(filterState.tags)
            .map(tagRouteForValue)
            .filter(Boolean);
        updateHashState({
            day: null,
            tag: null,
            room: null,
            days,
            tags: tagRoutes.length ? joinFilterList(tagRoutes) : null,
            rooms: filterState.rooms.size ? joinFilterList(filterState.rooms) : null,
        }, true);
    }

    function normalizeEventId(eventId) {
        return String(eventId || '').replace(/^#/, '').replace(/^onlineevt-/, '').replace(/\D/g, '');
    }

    function getEventItemById(eventId) {
        const cleanId = normalizeEventId(eventId);
        if (!cleanId) return null;

        return document.querySelector(`[data-os-event-id="${cleanId}"]`) || document.getElementById(`onlineevt-${cleanId}`);
    }

    function routeToEvent(eventId) {
        const cleanId = normalizeEventId(eventId);
        if (!cleanId) return;

        updateHashState({ evt: cleanId }, false);
        const item = getEventItemById(cleanId);
        if (item) {
            let offset = item.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset(true);
            if (offset < 0) offset = 0;
            window.scrollTo({ top: offset, behavior: 'smooth' });
        }
        setTimeout(() => {
            openEventModal(cleanId, { writeHistory: false });
        }, 300);
    }
    window.routeToEvent = routeToEvent;

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

    function scrollToHoursDepartment(route) {
        const targetRoute = normalizeRouteKey(route);
        if (!targetRoute) return false;

        const department = $$('.os-hours__dept').find((section) => {
            const heading = $('.os-hours__name', section);
            return heading && normalizeRouteKey(heading.textContent) === targetRoute;
        });
        if (!department) return false;

        const align = () => {
            const top = Math.max(
                0,
                department.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset(true)
            );
            const roots = [document.documentElement, document.body];
            const previous = roots.map((root) => root.style.scrollBehavior);
            roots.forEach((root) => { root.style.scrollBehavior = 'auto'; });
            window.scrollTo(0, top);
            roots.forEach((root, index) => { root.style.scrollBehavior = previous[index]; });
        };
        align();
        window.requestAnimationFrame(align);
        document.fonts?.ready.then(() => window.requestAnimationFrame(align));
        return true;
    }

    window.scrollTopMenu = function () {
        const schedule = $('#schedule');
        if (!schedule) return;

        const isKiosk = schedule.classList.contains('kiosk-schedule');
        let offset = schedule.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset(true);
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

        // An open <dialog> renders in the top layer above any z-index, so the effect
        // must be appended inside it rather than to document.body to be visible.
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

    window.osShowClipboardEffect = animate_clipboard;

    $('#modal-copy-url')?.addEventListener('click', function (e) {
        e.preventDefault();
        navigator.clipboard?.writeText(window.location.href);
        animate_clipboard(this);
    });

    $$('.schedule-clipboard').forEach((button) => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const item = this.closest('.schedule-item');
            if (!item) return;

            const eventID = normalizeEventId(item.getAttribute('data-os-event-id') || item.id);
            if (!eventID) return;

            const nextState = { ...getHashState(), evt: eventID };
            const newHash = new URLSearchParams(nextState).toString();
            const url = this.getAttribute('data-url') || (window.location.origin + window.location.pathname + window.location.search + '#' + newHash);
            navigator.clipboard?.writeText(url);
            animate_clipboard(this);
        });
    });

    document.addEventListener('os:tab:shown', function (e) {
        const hash = e.detail.hash;
        if (hash && e.detail.isTrusted) {
            const tabName = hash.substring(1);
            updateHashState({
                ...CLEARED_FILTER_KEYS,
                q: null,
                evt: null,
                tab: tabName !== 'programming' ? tabName : null,
            }, false);

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

        window.requestAnimationFrame(() => {
            const state = getHashState();
            if (hash !== '#hours' || !state.hours || !scrollToHoursDepartment(state.hours)) {
                window.scrollTopMenu?.();
            }
        });
    });

    function openEventModal(eventId, options = {}) {
        const cleanId = normalizeEventId(eventId);
        let item = getEventItemById(cleanId);

        if (!item) return;

        if (!window.getEventDetailsFromElement) return;
        const eventDetails = window.getEventDetailsFromElement(item);
        window.currentModalEventDetails = eventDetails;

        const panelists = eventDetails.panelists;
        const titleLink = item.querySelector('.schedule-title a');
        const title = titleLink ? titleLink.innerHTML : '';
        const cancelled = item.classList.contains('canceled');
        const description = eventDetails.description;
        const day = item.closest('.schedule-day');
        const date = item.dataset.osEventDate || day?.querySelector('h2')?.innerHTML || '';
        const time = item.dataset.osEventTime || item.querySelector('.schedule-time span')?.innerHTML || item.querySelector('.schedule-time')?.textContent?.trim() || '';
        const room = eventDetails.room;
        const tags = eventDetails.tags;
        const ical = item.querySelector('.schedule-ical')?.getAttribute('href') || '#';
        const googleCal = item.querySelector('.schedule-google')?.getAttribute('href') || '#';

        const badges = item.querySelector('.schedule-title')?.cloneNode(true);
        badges?.querySelectorAll('a').forEach((anchor) => anchor.remove());
        if (cancelled) {
            badges?.querySelectorAll('.os-badge--cancelled, .os-badge--canceled').forEach((badge) => badge.remove());
        }
        const badgesHtml = badges?.innerHTML || '';

        let isFavorite = item.getAttribute('data-favorite') === 'true';
        let favBtn = '';
        if (!scheduleConfig.isKiosk && !scheduleConfig.isLive) {
            const config = window.OnlineSchedPublic || {};
            const iconClass = isFavorite ? (config.iconFavActive || 'fas fa-star') : (config.iconFavInactive || 'far fa-star');
            favBtn = '<button type="button" class="schedule-favorite-toggle' + (isFavorite ? ' active' : '') + '" aria-pressed="' + (isFavorite ? 'true' : 'false') + '" title="Favorite" style="margin-right:8px;"><i class="' + iconClass + '"></i></button>';
        }

        const modalTitle = $('#modal-schedule-title');
        if (modalTitle) {
            const titleHtml = cancelled ? '<s>' + title + '</s>' : title;
            const cancellationBadge = cancelled ? item.querySelector('.os-event-cancelled-badge')?.innerHTML || '' : '';
            modalTitle.innerHTML = favBtn + titleHtml + cancellationBadge + badgesHtml;
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

            const mainItem = getEventItemById(cleanId);
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
            window.refreshCalendarFeedScope?.();
        });

        const descriptionEl = $('#modal-schedule-description');
        const hr = descriptionEl?.parentElement?.querySelector('hr');
        const cleanDescription = (description || '').replace(/&(nbsp|#160);/g, ' ').trim();

        if (descriptionEl) {
            if (cleanDescription.length > 0) {
                descriptionEl.innerHTML = description;
                showElement(descriptionEl);
                if (hr) showElement(hr);
            } else {
                descriptionEl.innerHTML = '';
                hideElement(descriptionEl);
                if (hr) hideElement(hr);
            }
        }

        modal_popup_fill('#modal-schedule-date', date);
        modal_popup_fill('#modal-schedule-time', time);
        modal_popup_fill('#modal-schedule-room', room);
        modal_popup_fill('#modal-schedule-tags', tags);
        modal_popup_fill('#modal-schedule-panelists', panelists);

        const extraEl = $('#modal-schedule-extra');
        if (extraEl) {
            extraEl.replaceChildren();
            const template = item.querySelector('.os-event-popup-extra');
            if (template instanceof HTMLTemplateElement) {
                extraEl.appendChild(template.content.cloneNode(true));
            }
            extraEl.hidden = !extraEl.childElementCount && !extraEl.textContent.trim();
        }

        $('#modal-schedule-ical')?.setAttribute('href', ical);
        $('#modal-schedule-google')?.setAttribute('href', googleCal);
        $$('#modal-schedule-ical, #modal-schedule-google').forEach((link) => {
            link.hidden = cancelled;
        });

        window.currentModalEventId = cleanId;
        if (options.writeHistory !== false && getHashState().evt !== cleanId) {
            updateHashState({ evt: cleanId }, false);
        }

        openModal('modal-schedule');
        document.getElementById('modal-schedule')?.addEventListener('close', () => {
            updateHashState({ evt: null }, true);
        }, { once: true });
    }
    window.openEventModal = openEventModal;

    $$('a[data-target="#modal-schedule"]').forEach((link) => {
        link.addEventListener('click', function (ev) {
            ev.preventDefault();
            const parent = this.closest('.schedule-item');
            if (parent) routeToEvent(parent.getAttribute('data-os-event-id') || parent.id);
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

    $$('.schedule-tags .os-term-item').forEach((termItem) => {
        termItem.setAttribute('role', 'button');
        termItem.setAttribute('tabindex', '0');
    });

    $$('.schedule-tags').forEach((tagsEl) => {
        const item = tagsEl.closest('.schedule-item');
        // Popup and template markup carry tag terms too; a label with no
        // owning event must not mint a menu entry.
        if (!item) return;

        getScheduleTagItems(tagsEl).forEach((tag) => {
            if (!Object.prototype.hasOwnProperty.call(window.eventschedule_scheduleTags, tag.label)) {
                window.eventschedule_scheduleTags[tag.label] = window.eventschedule_count++;
            }

            item.setAttribute(
                `data-schedule-tag${window.eventschedule_scheduleTags[tag.label]}`,
                window.eventschedule_scheduleTags[tag.label]
            );
        });
    });

    for (const key in window.eventschedule_scheduleTags) {
        if (typeof key === 'string' && key.trim().length !== 0) {
            $('#schedule-select-tags')?.append(new Option(key, window.eventschedule_scheduleTags[key]));
        }
    }

    // Slug to label, read from the server's term markup. Deriving a second slug
    // here is what put one room in the filter twice.
    window.eventschedule_scheduleRooms = {};
    $$('.schedule-room .os-term-item[data-os-term-slug]').forEach((termItem) => {
        const slug = (termItem.dataset.osTermSlug || '').trim();
        const label = (termItem.dataset.osTermLabel || termItem.textContent.replace(/,\s*$/, '')).trim();
        if (!slug || !label) return;
        if (!Object.prototype.hasOwnProperty.call(window.eventschedule_scheduleRooms, slug)) {
            window.eventschedule_scheduleRooms[slug] = label;
        }
    });

    // Membership from events, not the slug map: the map also reads popup
    // markup, and a room no event carries must be absent, not disabled.
    (function populateRoomOptions() {
        const select = $('#schedule-select-rooms');
        if (!select) return;
        const seen = new Set(Array.from(select.options, (o) => o.value));
        $$('.schedule-item').forEach((item) => {
            if (item.dataset.osFallback === 'true') return;
            for (const attr of item.attributes) {
                if (attr.name.startsWith('data-schedule-room-') && attr.value && !seen.has(attr.value)) {
                    seen.add(attr.value);
                    select.append(new Option(
                        window.eventschedule_scheduleRooms[attr.value] || attr.value, attr.value));
                }
            }
        });
        sort_rooms_options_by_id('#schedule-select-rooms');
        sort_options_by_id('#schedule-select-tags');
    })();

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

    // A menu speaks for its own facet. Rebuilding all three pushes the other
    // sets through a control that holds one value, and the rest are lost.
    function syncStateFromSelect(select) {
        if (!select) return;
        const value = select.value;

        if (select.id === 'schedule-select-days') {
            const day = value || 'Current';
            if (day === 'all' || day === 'Current') setDayMode(day);
            else setDaySet([day]);
            return;
        }

        const set = new Set(!value || value === 'all' ? [] : [String(value)]);
        if (select.id === 'schedule-select-tags') filterState.tags = set;
        if (select.id === 'schedule-select-rooms') filterState.rooms = set;
    }

    buildFilterPanels();

    $$('#schedule-select-days, #schedule-select-tags, #schedule-select-rooms').forEach((select) => {
        select.addEventListener('change', function () {
            syncStateFromSelect(this);
            writeFilterHash();

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
        updateHashState({ ...CLEARED_FILTER_KEYS, q: null }, true);
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

        // Favorites spans the whole convention, so the day narrowing opens up.
        // Room and tag choices are deliberately left alone.
        if (window.favoritesFilterActive) {
            setDayMode('all');
            applyStateToSelects();
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
                if (!isElementVisible(item)) return;

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
        setDayMode('Current');
        filterState.tags.clear();
        filterState.rooms.clear();
        applyStateToSelects();
        if ($('#schedule-search-text')) $('#schedule-search-text').value = '';
    }

    function hasAnyMatchingAttribute(item, prefix, values) {
        if (!values || values.size === 0) return true;
        for (const value of values) {
            if (hasMatchingAttribute(item, prefix, value)) return true;
        }
        return false;
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

        const selectedTags = filterState.tags;
        const selectedRooms = filterState.rooms;
        const searchText = ($('#schedule-search-text')?.value || '').toLowerCase();

        const favoritesFilterActive = window.favoritesFilterActive;
        const essentialsFilterActive = (window.eventschedule_showEvents === false);
        const essentialsTags = window.essentialsTags || [];

        if (dayFilterIsOpen()) {
            $$('.schedule-day').forEach(showElement);
        } else {
            $$('.schedule-day').forEach((dayEl) => {
                if (filterState.days.has(dayEl.getAttribute('data-schedule-day'))) {
                    showElement(dayEl);
                } else {
                    hideElement(dayEl);
                }
            });
        }

        const currentDateUTC = filterState.dayMode === 'Current'
            ? currentDateTimeTimestampUTC()
            : null;

        dayAvailability = new Set();
        tagAvailability = new Set();
        roomAvailability = new Set();
        $$('.schedule-item').forEach((item) => {
            if (item.dataset.osFallback === 'true') {
                showElement(item);
                return;
            }
            let show = true;
            let baseOk = true;
            let dayOk = true;
            let tagOk = true;
            let roomOk = true;

            const day = item.closest('.schedule-day');
            if (filterState.dayMode !== 'Current' && !isElementVisible(day)) {
                show = false;
                dayOk = false;
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
                    baseOk = false;
                }
            }

            if (!hasAnyMatchingAttribute(item, 'data-schedule-tag', selectedTags)) {
                show = false;
                tagOk = false;
            }

            if (!hasAnyMatchingAttribute(item, 'data-schedule-room-', selectedRooms)) {
                show = false;
                roomOk = false;
            }

            if (filterState.dayMode === 'Current') {
                const itemDate = Number(item.dataset.endTime);
                if (!itemDate || itemDate <= currentDateUTC) {
                    show = false;
                    dayOk = false;
                }
            }

            if (searchText !== '') {
                const found = $$('.schedule-title a, .schedule-panelists, .schedule-tags, .schedule-room', item)
                    .some((el) => el.textContent.toLowerCase().indexOf(searchText) !== -1);
                if (!found) {
                    show = false;
                    baseOk = false;
                }
            }

            if (favoritesFilterActive && item.getAttribute('data-favorite') !== 'true') {
                show = false;
                baseOk = false;
            }

            // Counterfactual per facet: availability is judged with that
            // facet's own filter removed, so picking A never greys B.
            if (baseOk) {
                if (tagOk && roomOk && day) {
                    const dayName = day.getAttribute('data-schedule-day');
                    if (dayName) dayAvailability.add(dayName);
                }
                for (const attr of item.attributes) {
                    if (dayOk && roomOk && attr.name.startsWith('data-schedule-tag') && attr.value !== '') {
                        tagAvailability.add(String(attr.value));
                    }
                    if (dayOk && tagOk && attr.name.startsWith('data-schedule-room-') && attr.value) {
                        roomAvailability.add(attr.value);
                    }
                }
            }

            if (show) {
                showElement(item);
            } else {
                hideElement(item);
            }
        });

        resetSelectDays();
        resetSelectTags();
        resetSelectRooms();
        refreshFilterPanels(filterState);

        const isDefault = (
            searchText.trim() === '' &&
            selectedTags.size === 0 &&
            selectedRooms.size === 0 &&
            filterState.dayMode === 'Current' &&
            !favoritesFilterActive
        );

        if ($('#schedule-reset')) {
            $('#schedule-reset').disabled = isDefault;
        }

        const visibleItems = $$('.schedule-item').filter(isElementVisible);
        const scheduleEl = $('#schedule');

        let emptyState = $('.os-empty-state', scheduleEl);
        if (visibleItems.length === 0 && !isDefault) {
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'os-empty-state';
                emptyState.innerHTML = `
                    <div class="os-empty-state-content">
                        <i class="fas fa-search"></i>
                        <p>No events found matching your filters.</p>
                        <button type="button" class="os-reset-filters-btn">Reset Filters</button>
                    </div>
                `;
                scheduleEl.appendChild(emptyState);

                $('.os-reset-filters-btn', emptyState).addEventListener('click', () => {
                    $('#schedule-reset')?.click();
                });
            }
            showElement(emptyState);
        } else {
            hideElement(emptyState);
        }

        reset_schedule(true);
        messageAtBottomForCalendar();
        window.refreshCalendarFeedScope?.();
    }

    function resetHoursDays() {
        $$('.schedule-hour').forEach((hour) => {
            showElement(hour);
            const visibleLength = Array.from(hour.children).filter(isElementVisible).length;
            if (visibleLength < 2) {
                hideElement(hour);
            }
        });

        $$('.schedule-day').forEach((day) => {
            showElement(day);
            const visibleLength = Array.from(day.children).filter(isElementVisible).length;
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

    // Availability greys options; membership never changes here. The menu holds
    // only the first member of a set, so the set keeps a chosen row removable.
    function refreshSelectAvailability(select, available, chosen) {
        if (!select) return;
        Array.from(select.options).forEach((option) => {
            if (option.value === 'all' || option.value === 'Current') return;
            const value = String(option.value);
            const isChosen = String(select.value) === value || !!chosen?.has(value);
            option.disabled = !available.has(value) && !isChosen;
        });
        setOddEven();
    }

    function resetSelectDays() {
        refreshSelectAvailability($('#schedule-select-days'), dayAvailability, filterState.days);
    }

    function resetSelectTags() {
        refreshSelectAvailability($('#schedule-select-tags'), tagAvailability, filterState.tags);
    }

    function resetSelectRooms() {
        refreshSelectAvailability($('#schedule-select-rooms'), roomAvailability, filterState.rooms);
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

    // A day label such as "Tuesday, September 15" holds a comma, and a legacy
    // singular key always carried one value, so it is never split.
    function legacyValue(value) {
        const trimmed = String(value ?? '').trim();
        return trimmed ? [trimmed] : [];
    }

    // Both hash forms are read forever: `rooms=a,b` is what we emit, `room=a`
    // is what every existing bookmark and inbound link still carries.

    function readFilterHash(state) {
        // A bare legacy room=/tag= widens to All Days, which the theme documents
        // for Con Maps. The plural form omits days while Current, so it must not.
        const widensToAllDays = ((state.room && state.room !== 'all')
            || (state.tag && state.tag !== 'all'))
            && state.days === undefined && state.day === undefined;

        filterState.rooms.clear();
        filterState.tags.clear();
        setDayMode(widensToAllDays ? 'all' : 'Current');

        const roomList = state.rooms !== undefined
            ? splitFilterList(state.rooms)
            : (state.room !== undefined ? legacyValue(state.room) : null);
        if (roomList) {
            filterState.rooms = new Set(roomList.filter((value) => value !== 'all'));
        }

        const tagSource = state.tags !== undefined ? state.tags : state.tag;
        if (tagSource !== undefined) {
            const values = (state.tags !== undefined ? splitFilterList(tagSource) : legacyValue(tagSource))
                .filter((route) => route !== 'all')
                .map(tagValueForRoute)
                .filter((value) => value !== null && value !== undefined);
            filterState.tags = new Set(values);
        }

        const daySource = state.days !== undefined ? state.days : state.day;
        if (daySource !== undefined) {
            const dayList = state.days !== undefined
                ? splitFilterList(daySource)
                : legacyValue(daySource);
            if (dayList.length === 1 && (dayList[0] === 'all' || dayList[0] === 'Current')) {
                setDayMode(dayList[0]);
            } else if (dayList.length) {
                setDaySet(dayList.map(dayValueForRoute));
            } else {
                setDayMode('Current');
            }
        }
    }

    function summarizeSet(values, allLabel, noun, labelFor) {
        if (values.size === 0) return allLabel;
        if (values.size === 1) return labelFor(Array.from(values)[0]);
        return `${noun}: ${values.size} selected`;
    }

    const DAY_ABBREVIATIONS = {
        Sunday: 'Sun.', Monday: 'Mon.', Tuesday: 'Tues.', Wednesday: 'Wed.',
        Thursday: 'Thurs.', Friday: 'Fri.', Saturday: 'Sat.',
        January: 'Jan', February: 'Feb', March: 'Mar', April: 'Apr',
        August: 'Aug', September: 'Sept', October: 'Oct',
        November: 'Nov', December: 'Dec',
    };

    // The button has one line to work with. Rows in the list keep the full day
    // name, so only the summary is shortened.
    function shortDayLabel(value) {
        return String(value).replace(/[A-Z][a-z]+/g, (word) => DAY_ABBREVIATIONS[word] || word);
    }

    function optionLabel(selector, value) {
        const select = $(selector);
        if (!select) return String(value);
        for (const option of select.options) {
            if (String(option.value) === String(value)) return option.textContent.trim();
        }
        return String(value);
    }

    function buildFilterPanels() {
        // Kiosk keeps the single-select menus: hallway users compose one-tap
        // queries and the layout must not grow a popover.
        if ($('#schedule')?.classList.contains('kiosk-schedule')) return;

        createFilterPanels([
            {
                selector: '#schedule-select-rooms',
                label: 'Rooms',
                modeValues: ['all'],
                isChecked: (value) => value === 'all'
                    ? filterState.rooms.size === 0
                    : filterState.rooms.has(value),
                isNarrowed: () => filterState.rooms.size > 0,
                summarize: () => summarizeSet(
                    filterState.rooms, optionLabel('#schedule-select-rooms', 'all'), 'Rooms',
                    (value) => optionLabel('#schedule-select-rooms', value)),
                onChange: (value, checked) => {
                    if (value === 'all') filterState.rooms.clear();
                    else if (checked) filterState.rooms.add(value);
                    else filterState.rooms.delete(value);
                    commitPanelChange();
                },
            },
            {
                selector: '#schedule-select-tags',
                label: 'Tags',
                modeValues: ['all'],
                isChecked: (value) => value === 'all'
                    ? filterState.tags.size === 0
                    : filterState.tags.has(value),
                isNarrowed: () => filterState.tags.size > 0,
                summarize: () => summarizeSet(
                    filterState.tags, optionLabel('#schedule-select-tags', 'all'), 'Tags',
                    (value) => optionLabel('#schedule-select-tags', value)),
                onChange: (value, checked) => {
                    if (value === 'all') filterState.tags.clear();
                    else if (checked) filterState.tags.add(value);
                    else filterState.tags.delete(value);
                    commitPanelChange();
                },
            },
            {
                selector: '#schedule-select-days',
                label: 'Days',
                modeValues: ['all', 'Current'],
                isChecked: (value) => (value === 'all' || value === 'Current')
                    ? filterState.dayMode === value
                    : filterState.days.has(value),
                isNarrowed: () => filterState.dayMode !== 'Current',
                summarize: () => {
                    if (filterState.dayMode) return optionLabel('#schedule-select-days', filterState.dayMode);
                    return summarizeSet(
                        filterState.days, optionLabel('#schedule-select-days', 'Current'), 'Days',
                        shortDayLabel);
                },
                onChange: (value, checked) => {
                    // all and Now-and-Future are modes, so they replace any
                    // chosen days rather than joining them.
                    if (value === 'all' || value === 'Current') setDayMode(value);
                    else {
                        const days = new Set(filterState.days);
                        if (checked) days.add(value);
                        else days.delete(value);
                        setDaySet(days);
                    }
                    commitPanelChange();
                },
            },
        ]);
    }

    function commitPanelChange() {
        applyStateToSelects();
        writeFilterHash();
        scheduleSort();
        updateResetButtonState();
    }

    function applyStateToSelects() {
        const daysSelect = $('#schedule-select-days');
        if (daysSelect) {
            daysSelect.value = filterState.dayMode
                ? filterState.dayMode
                : (Array.from(filterState.days)[0] || 'Current');
        }
        const tagsSelect = $('#schedule-select-tags');
        if (tagsSelect) tagsSelect.value = Array.from(filterState.tags)[0] || 'all';
        const roomsSelect = $('#schedule-select-rooms');
        if (roomsSelect) roomsSelect.value = Array.from(filterState.rooms)[0] || 'all';
    }

    function handleHashRouting() {
        const state = getHashState();
        showElement($('#schedule'));

        // Restored tag/room menus are populated from currently-visible items.
        // On a fresh load those are today-only events, so expand to all days first.
        const wantsTag = (state.tag && state.tag !== 'all') || (state.tags && state.tags !== 'all');
        const wantsRoom = (state.room && state.room !== 'all') || (state.rooms && state.rooms !== 'all');
        if (wantsTag || wantsRoom) {
            setDayMode('all');
            applyStateToSelects();
            scheduleSort();
        }

        readFilterHash(state);
        applyStateToSelects();
        if (state.q !== undefined && $('#schedule-search-text')) $('#schedule-search-text').value = state.q || '';

        scheduleSort();

        if (state.tab === 'hours') {
            $('#hours-tab')?.click();
            if (!state.hours || !scrollToHoursDepartment(state.hours)) {
                scrollTopMenu();
            }
        } else if (state.tab === 'essentials') {
            window.setFilterEvents(false);
            $('[data-os-tab="essentials"]')?.click();
        } else if (state.tab === 'programming') {
            $('[data-os-tab="programming"]')?.click();
        } else if (state.tab === 'map') {
            $('#map-tab')?.click();
            scrollTopMenu();
        } else {
            $('[data-os-tab="programming"]')?.click();
        }

        if (state.evt) {
            const eventEl = getEventItemById(state.evt);
            if (eventEl) {
                if (!isElementVisible(eventEl)) {
                    setDayMode('all');
                    applyStateToSelects();
                    scheduleSort();
                }

                let offset = eventEl.getBoundingClientRect().top + window.pageYOffset - currentStickyOffset(true);
                if (offset < 0) offset = 0;
                window.scrollTo({ top: offset, behavior: 'smooth' });

                setTimeout(() => {
                    const modal = $('#modal-schedule');
                    const isModalOpen = modal && modal.hasAttribute('open');
                    const isSameEvent = String(window.currentModalEventId) === normalizeEventId(state.evt);

                    if (!isModalOpen || !isSameEvent) {
                        openEventModal(state.evt, { writeHistory: false });
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

    // The popup carries the same filter links as a row, so filtering from inside
    // it has to dismiss it first; closing also clears the event out of the hash.
    function dismissModalForFilter(target) {
        const modal = target.closest('#modal-schedule');
        if (modal && modal.hasAttribute('open')) modal.close();
    }

    document.addEventListener('click', function (e) {
        const room = e.target.closest('.schedule-room.schedule-filter-link');
        if (!room) return;

        e.preventDefault();
        // The clicked term wins on a multi-room row; otherwise the row's first.
        const termItem = e.target.closest('.os-term-item[data-os-term-slug]')
            || room.querySelector('.os-term-item[data-os-term-slug]');
        const slug = (termItem?.dataset.osTermSlug || '').trim();
        if (!slug) return;

        const select = $('#schedule-select-rooms');
        if (select && Array.from(select.options).some((option) => option.value === slug)) {
            dismissModalForFilter(e.target);
            select.value = slug;
            dispatchChange(select);
            scrollTopMenu();
        }
    });

    document.addEventListener('click', function (e) {
        const clickedTermItem = e.target.closest('.os-term-item');
        const tagsContainer = e.target.closest('.schedule-tags.schedule-filter-link');

        if (!tagsContainer) return;

        const termItem = clickedTermItem || tagsContainer.querySelector('.os-term-item');
        if (!termItem) return;

        e.preventDefault();
        const tagText = (termItem.dataset.osTermLabel || termItem.textContent.replace(/,\s*$/, '')).trim();
        if (!tagText) return;

        const select = $('#schedule-select-tags');
        if (!select) return;

        const tagRoute = termItem.dataset.osTagRoute || getTagRouteValueFromText(tagText);
        const tagValue = tagValueForRoute(tagRoute) ?? tagValueForRoute(getTagRouteValueFromText(tagText));
        const matched = tagValue !== null;
        if (matched) {
            select.value = tagValue;
            dispatchChange(select);
        }

        if (matched) {
            dismissModalForFilter(e.target);
            scrollTopMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        const termItem = e.target.closest('.schedule-tags.schedule-filter-link .os-term-item');
        if (!termItem || (e.key !== 'Enter' && e.key !== ' ')) return;

        e.preventDefault();
        termItem.click();
    });

    handleHashRouting();
    window.addEventListener('popstate', handleHashRouting);
    window.addEventListener('hashchange', handleHashRouting);

    function tagSlugForValue(value) {
        const label = optionLabel('#schedule-select-tags', value);
        return window.scheduleMasterTags ? window.scheduleMasterTags[label] : null;
    }

    // What the feed can express. Search is absent by design: icalby.php has no
    // text query, so the blurb says so rather than quietly widening the feed.
    function currentFeedFilters() {
        return {
            rooms: Array.from(filterState.rooms),
            tags: Array.from(filterState.tags).map(tagSlugForValue).filter(Boolean),
            // Now and Future emits no day filter on purpose: trimming the feed
            // would erase earlier days from a calendar someone subscribed late.
            days: filterState.dayMode ? [] : Array.from(filterState.days).map(dayRouteForValue),
        };
    }
    window.scheduleFeedFilters = currentFeedFilters;

    function messageAtBottomForCalendar() {
        const message = $('#schedule-add-to-calendar-message');
        if (!message) return;

        const feed = currentFeedFilters();
        const narrowed = feed.rooms.length || feed.tags.length || feed.days.length;

        if (($('#schedule-search-text')?.value || '').trim() !== '') {
            message.innerHTML = narrowed
                ? 'Your calendar gets the room, tag and day filters. Search text is not part of a calendar feed.'
                : 'Your calendar gets the full schedule. Search text is not part of a calendar feed.';
        } else if (narrowed && filterState.dayMode === 'Current') {
            message.innerHTML = 'Add this filtered list to your calendar. The feed also carries earlier events for your selection.';
        } else if (narrowed) {
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
