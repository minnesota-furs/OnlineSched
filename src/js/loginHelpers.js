export function loginHelpers() {
    const endpoints = window.OnlineSchedPublic || {};
    const loginStateUrl = endpoints.loginStateUrl || '/wp-content/plugins/OnlineSched/includes/login_state.php';

    function openLoginWithProvider(provider, event) {
        if (event) event.preventDefault();
        var rand = (window.crypto && window.crypto.getRandomValues) ?
            Array.from(window.crypto.getRandomValues(new Uint32Array(2)), x => x.toString(16)).join('') :
            Math.random().toString(36).substring(2) + Date.now();
        var url = '/wp-content/plugins/OnlineSched/includes/login.php?provider=' + encodeURIComponent(provider) + '&cachebreak=' + rand;
        var w = 500, h = 600;
        var left = (screen.width / 2) - (w / 2), top = (screen.height / 2) - (h / 2);
        var win = window.open(url, 'onlinesched_login', 'width=' + w + ',height=' + h + ',top=' + top + ',left=' + left + ',resizable,scrollbars');
        if (!win) {
            alert('Popup blocked! Please allow popups for this site to log in.');
        }
        return false;
    }
    window.openLoginWithProvider = openLoginWithProvider;

    function openLogoutProvider(provider, event) {
        if (event) event.preventDefault();
        var rand = (window.crypto && window.crypto.getRandomValues) ?
            Array.from(window.crypto.getRandomValues(new Uint32Array(2)), x => x.toString(16)).join('') :
            Math.random().toString(36).substring(2) + Date.now();
        var url = '/wp-content/plugins/OnlineSched/includes/login.php?logout=' + encodeURIComponent(provider) + '&cachebreak=' + rand;
        var w = 500, h = 400;
        var left = (screen.width / 2) - (w / 2), top = (screen.height / 2) - (h / 2);
        var win = window.open(url, 'onlinesched_logout', 'width=' + w + ',height=' + h + ',top=' + top + ',left=' + left + ',resizable,scrollbars');
        if (!win) {
            alert('Popup blocked! Please allow popups for this site to log out.');
            // Clear login state if popup blocked
            if (window.ONLINESCHED_USER) {
                window.ONLINESCHED_USER.loggedIn = false;
                window.ONLINESCHED_USER.provider = '';
                window.ONLINESCHED_USER.favoritesToken = '';
            }
            if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
            window.location.reload();
        } else {
            // Poll for window close, then reload
            var pollTimer = window.setInterval(function () {
                if (win.closed) {
                    window.clearInterval(pollTimer);
                    // Clear login state after logout popup closes
                    if (window.ONLINESCHED_USER) {
                        window.ONLINESCHED_USER.loggedIn = false;
                        window.ONLINESCHED_USER.provider = '';
                        window.ONLINESCHED_USER.favoritesToken = '';
                    }
                    if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
                    window.location.reload();
                }
            }, 500);
        }
        return false;
    }
    window.openLogoutProvider = openLogoutProvider;

// Show/hide login/logout buttons based on login state
    function updateLoginLogoutUI() {
        var loggedIn = window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn;
        var loginBtn = document.getElementById('login-modal-btn');
        var logoutBtn = document.getElementById('logout-modal-btn');
        if (loginBtn) loginBtn.style.display = loggedIn ? 'none' : '';
        if (logoutBtn) logoutBtn.style.display = loggedIn ? '' : 'none';
    }
    window.updateLoginLogoutUI = updateLoginLogoutUI;

// Fetch login state via AJAX and update UI
    window.ONLINESCHED_USER = window.ONLINESCHED_USER || {loggedIn: false, provider: '', favoritesToken: ''};

// Hide login/logout buttons until state is loaded
    function hideLoginButtons() {
        var loginBtn = document.getElementById('login-modal-btn');
        var logoutBtn = document.getElementById('logout-modal-btn');
        if (loginBtn) loginBtn.style.display = 'none';
        if (logoutBtn) logoutBtn.style.display = 'none';
    }
    window.hideLoginButtons = hideLoginButtons;

    function fetchLoginStateAndInit() {
        const statePromise = window.fetchOnlineSchedLoginState
            ? window.fetchOnlineSchedLoginState()
            : fetch(loginStateUrl, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function (resp) {
                return resp.json();
            });

        statePromise
            .then(function (data) {
                window.ONLINESCHED_USER = data;
                if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
                if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
            })
            .catch(function () {
                window.ONLINESCHED_USER = {loggedIn: false, provider: '', favoritesToken: ''};
                if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
                if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
            });
    }
    window.fetchLoginStateAndInit = fetchLoginStateAndInit;

    fetchLoginStateAndInit();
}
