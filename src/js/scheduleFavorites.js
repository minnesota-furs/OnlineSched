import { updateIconClasses } from "./osIcons";

export function scheduleFavorites() {
    const endpoints = window.OnlineSchedPublic || {};
    const saveFavoritesUrl = endpoints.saveFavoritesUrl || '/wp-content/plugins/OnlineSched/includes/save_favorites.php';
    const loginStateUrl = endpoints.loginStateUrl || '/wp-content/plugins/OnlineSched/includes/login_state.php';
    let loginStatePromise = null;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.schedule-favorite-toggle');
        if (!btn) return;

        e.preventDefault();

        const icon = btn.querySelector('i');
        const isActive = btn.classList.contains('active');
        const modalTitle = btn.closest('#modal-schedule-title');
        let mainItem = null;
        let evtId = null;

        if (modalTitle) {
            evtId = String(window.currentModalEventId || '').replace(/\D/g, '');
            mainItem = document.querySelector(`[data-os-event-id="${evtId}"]`) || document.getElementById(`onlineevt-${evtId}`);
        } else {
            mainItem = btn.closest('.schedule-item');
            evtId = mainItem?.getAttribute('data-os-event-id') || mainItem?.getAttribute('id')?.replace('onlineevt-', '');
        }

        if (!mainItem || !evtId) return;

        const newState = !isActive;
        setFavoriteState(mainItem, newState);

        if (String(window.currentModalEventId || '').replace(/\D/g, '') === String(evtId)) {
            const modalBtn = document.querySelector('#modal-schedule-title .schedule-favorite-toggle');
            if (modalBtn) {
                modalBtn.classList.toggle('active', newState);
                modalBtn.setAttribute('aria-pressed', newState ? 'true' : 'false');
                const modalIcon = modalBtn.querySelector('i');
                updateIconClasses(modalIcon, newState);
            }
        }

        if (icon) {
            updateIconClasses(icon, newState);
        }

        window.updateFavoritesCookie?.();
    });

    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + d.toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + ';' + expires + ';path=/;SameSite=Lax';
    }

    function getCookie(name) {
        const cname = name + '=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1);
            if (c.indexOf(cname) === 0) return c.substring(cname.length, c.length);
        }
        return '';
    }

    function normalizeFavorites(value) {
        let favIds = value;

        if (typeof favIds === 'string') {
            try {
                favIds = JSON.parse(favIds);
            } catch (e) {
                favIds = [];
            }
        }

        if (favIds && typeof favIds === 'object' && !Array.isArray(favIds)) {
            favIds = Object.keys(favIds);
        }

        if (!Array.isArray(favIds)) {
            return [];
        }

        return [...new Set(favIds.map((id) => String(id).replace(/\D/g, '')).filter(Boolean))];
    }

    function getFavoriteIdsFromCookie() {
        return normalizeFavorites(getCookie('schedule_favorites'));
    }

    function setFavoritesCookie(value) {
        setCookie('schedule_favorites', JSON.stringify(normalizeFavorites(value)), 30);
    }

    function setFavoriteState(item, state) {
        const eventId = item.getAttribute('data-os-event-id') || item.getAttribute('id')?.replace('onlineevt-', '');
        if (!eventId) return;

        // Find all elements for this event ID (main schedule and solo cards)
        const selector = `#onlineevt-${eventId}, [data-os-event-id="${eventId}"]`;
        document.querySelectorAll(selector).forEach((el) => {
            if (state) {
                el.setAttribute('data-favorite', 'true');
            } else {
                el.removeAttribute('data-favorite');
            }

            el.querySelectorAll('.schedule-favorite-toggle').forEach((button) => {
                button.classList.toggle('active', state);
                button.setAttribute('aria-pressed', state ? 'true' : 'false');
                const buttonIcon = button.querySelector('i');
                updateIconClasses(buttonIcon, state);
            });
        });
    }

    function clearFavoriteStates() {
        document.querySelectorAll('.schedule-item[data-favorite="true"]').forEach((item) => {
            const eventId = item.getAttribute('data-os-event-id') || item.getAttribute('id')?.replace('onlineevt-', '');
            if (eventId) {
                setFavoriteState(item, false);
            }
        });
    }

    function applyFavoriteIds(ids) {
        clearFavoriteStates();
        normalizeFavorites(ids).forEach((id) => {
            const items = document.querySelectorAll(`#onlineevt-${id}, [data-os-event-id="${id}"]`);
            items.forEach((item) => {
                setFavoriteState(item, true);
            });
        });
    }

    function collectFavoriteIdsFromDom() {
        const ids = [];
        document.querySelectorAll('.schedule-item[data-favorite="true"]').forEach((item) => {
            const id = item.getAttribute('data-os-event-id') || item.getAttribute('id')?.replace('onlineevt-', '');
            if (id) ids.push(id);
        });

        return [...new Set(ids)];
    }

    function saveFavoritesToServer(ids) {
        const user = window.ONLINESCHED_USER || {};
        if (!user.loggedIn || !user.favoritesToken) {
            return;
        }

        const body = new URLSearchParams({
            favorites: JSON.stringify(normalizeFavorites(ids)),
            favorites_token: user.favoritesToken
        });

        fetch(saveFavoritesUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body
        }).catch(() => {});
    }

    function updateFavoritesCookie() {
        const ids = collectFavoriteIdsFromDom();
        setFavoritesCookie(ids);
        saveFavoritesToServer(ids);
    }

    function restoreFavoritesFromCookie() {
        applyFavoriteIds(getFavoriteIdsFromCookie());
    }

    function fetchLoginState(forceRefresh = false) {
        if (loginStatePromise && !forceRefresh) {
            return loginStatePromise;
        }

        loginStatePromise = fetch(loginStateUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json'
            }
        }).then((response) => {
            if (!response.ok) {
                throw new Error('Could not load OnlineSched login state.');
            }

            return response.json();
        }).catch((error) => {
            loginStatePromise = null;
            throw error;
        });

        return loginStatePromise;
    }

    window.getCookie = getCookie;
    window.setFavoritesCookie = setFavoritesCookie;
    window.updateFavoritesCookie = updateFavoritesCookie;
    window.restoreFavoritesFromCookie = restoreFavoritesFromCookie;
    window.fetchOnlineSchedLoginState = fetchLoginState;

    init_favorites();

    function init_favorites() {
        window.restoreFavoritesFromCookie();

        fetchLoginState()
            .then((data) => {
                const localFavorites = getFavoriteIdsFromCookie();
                const serverFavorites = normalizeFavorites(data.favorites);
                window.ONLINESCHED_USER = data;

                if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn) {
                    const mergedFavorites = normalizeFavorites(serverFavorites.concat(localFavorites));
                    setFavoritesCookie(mergedFavorites);
                    applyFavoriteIds(mergedFavorites);

                    if (localFavorites.some((id) => !serverFavorites.includes(id))) {
                        saveFavoritesToServer(mergedFavorites);
                    }
                } else {
                    window.restoreFavoritesFromCookie();
                }

                window.updateLoginLogoutUI?.();
            })
            .catch(() => {
                window.restoreFavoritesFromCookie();
            });

        window.updateLoginLogoutUI?.();
    }
}
