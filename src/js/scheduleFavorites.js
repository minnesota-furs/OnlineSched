export function scheduleFavorites() {
    const endpoints = window.OnlineSchedPublic || {};
    const saveFavoritesUrl = endpoints.saveFavoritesUrl || '/wp-content/plugins/OnlineSched/includes/save_favorites.php';
    const loginStateUrl = endpoints.loginStateUrl || '/wp-content/plugins/OnlineSched/includes/login_state.php';

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
            evtId = window.location.hash.replace('#', 'online');
            mainItem = document.getElementById(evtId);
        } else {
            mainItem = btn.closest('.schedule-item');
            evtId = mainItem?.getAttribute('id');
        }

        if (!mainItem || !evtId) return;

        const newState = !isActive;
        setFavoriteState(mainItem, newState);

        if (window.location.hash.replace('#', 'online') === evtId) {
            const modalBtn = document.querySelector('#modal-schedule-title .schedule-favorite-toggle');
            if (modalBtn) {
                modalBtn.classList.toggle('active', newState);
                modalBtn.setAttribute('aria-pressed', newState ? 'true' : 'false');
                const modalIcon = modalBtn.querySelector('i');
                modalIcon?.classList.toggle('fas', newState);
                modalIcon?.classList.toggle('far', !newState);
            }
        }

        if (icon) {
            icon.classList.toggle('fas', newState);
            icon.classList.toggle('far', !newState);
        }

        window.updateFavoritesCookie?.();
    });

    function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + d.toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + ';' + expires + ';path=/';
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

    function setFavoriteState(item, state) {
        if (state) {
            item.setAttribute('data-favorite', 'true');
        } else {
            item.removeAttribute('data-favorite');
        }

        item.querySelectorAll('.schedule-favorite-toggle').forEach((button) => {
            button.classList.toggle('active', state);
            button.setAttribute('aria-pressed', state ? 'true' : 'false');
            const buttonIcon = button.querySelector('i');
            buttonIcon?.classList.toggle('fas', state);
            buttonIcon?.classList.toggle('far', !state);
        });
    }

    function updateFavoritesCookie() {
        const ids = [];
        document.querySelectorAll('.schedule-item[data-favorite="true"]').forEach((item) => {
            const id = item.getAttribute('id');
            const match = id && id.match(/onlineevt-(\d+)/);
            if (match) ids.push(match[1]);
        });

        setCookie('schedule_favorites', JSON.stringify(ids), 30);

        if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn && window.ONLINESCHED_USER.provider && window.ONLINESCHED_USER.identifier) {
            const body = new URLSearchParams({
                provider: window.ONLINESCHED_USER.provider,
                identifier: window.ONLINESCHED_USER.identifier,
                favorites: JSON.stringify(ids)
            });

            if (endpoints.nonce) {
                body.set('nonce', endpoints.nonce);
            }

            fetch(saveFavoritesUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body
            }).catch(() => {});
        }
    }

    window.updateFavoritesCookie = updateFavoritesCookie;

    function restoreFavoritesFromCookie() {
        const favCookie = getCookie('schedule_favorites');
        if (!favCookie) return;

        try {
            let favIds = JSON.parse(favCookie);
            if (favIds && typeof favIds === 'object' && !Array.isArray(favIds)) {
                favIds = Object.keys(favIds);
            }

            if (Array.isArray(favIds)) {
                favIds.forEach((id) => {
                    const item = document.getElementById('onlineevt-' + id);
                    if (item) {
                        setFavoriteState(item, true);
                    }
                });
            }
        } catch (e) {
            // Ignore malformed favorite cookies.
        }
    }

    window.restoreFavoritesFromCookie = restoreFavoritesFromCookie;

    init_favorites();

    function init_favorites() {
        window.restoreFavoritesFromCookie();

        fetch(loginStateUrl, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
            .then((response) => response.json())
            .then((data) => {
                window.ONLINESCHED_USER = data;

                if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn && window.ONLINESCHED_USER.provider && window.ONLINESCHED_USER.identifier) {
                    if (data.favorites) {
                        document.cookie = 'schedule_favorites=' + encodeURIComponent(JSON.stringify(data.favorites)) + ';path=/;max-age=' + (60 * 60 * 24 * 30);
                    }
                    window.restoreFavoritesFromCookie();
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
