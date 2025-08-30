export function loginHelpers() {
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
                window.ONLINESCHED_USER.identifier = '';
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
                        window.ONLINESCHED_USER.identifier = '';
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
    window.ONLINESCHED_USER = {loggedIn: false, provider: '', identifier: ''};

// Hide login/logout buttons until state is loaded
    function hideLoginButtons() {
        var loginBtn = document.getElementById('login-modal-btn');
        var logoutBtn = document.getElementById('logout-modal-btn');
        if (loginBtn) loginBtn.style.display = 'none';
        if (logoutBtn) logoutBtn.style.display = 'none';
    }
    window.hideLoginButtons = hideLoginButtons;

    function fetchLoginStateAndInit() {
        fetch('/wp-content/plugins/OnlineSched/includes/login_state.php', {credentials: 'same-origin'})
            .then(function (resp) {
                return resp.json();
            })
            .then(function (data) {
                window.ONLINESCHED_USER = data;
                if (typeof updateLoginLogoutUI === 'function') updateLoginLogoutUI();
                // If logged in, fetch favorites from DB and sync cookie/UI
                if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn && window.ONLINESCHED_USER.provider && window.ONLINESCHED_USER.identifier) {
                    fetch('/wp-content/plugins/OnlineSched/includes/get_favorites.php?provider=' + encodeURIComponent(window.ONLINESCHED_USER.provider) + '&identifier=' + encodeURIComponent(window.ONLINESCHED_USER.identifier), {credentials: 'same-origin'})
                        .then(function (resp) {
                            return resp.json();
                        })
                        .then(function (favData) {
                            if (favData.favorites) {
                                // Set cookie as raw JSON string, not encodeURIComponent
                                document.cookie = 'schedule_favorites=' + favData.favorites + ';path=/;max-age=' + (60 * 60 * 24 * 30);
                            }
                            // Always call restoreFavoritesFromCookie after updating the cookie
                            if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
                            if (window.scheduleFavorites) window.scheduleFavorites();
                        });
                } else {
                    if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
                    if (window.scheduleFavorites) window.scheduleFavorites();
                }
            });
    }
    window.fetchLoginStateAndInit = fetchLoginStateAndInit;

    document.addEventListener('DOMContentLoaded', fetchLoginStateAndInit);
}