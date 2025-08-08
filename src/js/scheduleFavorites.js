export function scheduleFavorites() {
// Favorite (star) toggle button logic
    jQuery(document).on('click', '.schedule-favorite-toggle', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        const $icon = $btn.find('i');
        const isActive = $btn.hasClass('active');
        // Determine if this is the modal's star or a schedule-item's star
        const $modalTitle = $btn.closest('#modal-schedule-title');
        let $mainItem = null;
        let evt_id = null;
        if ($modalTitle.length) {
            // This is the modal's star
            // Get the event id from the modal's star (from hash)
            evt_id = window.location.hash.replace('#', 'onlineevt-');
            $mainItem = jQuery('#' + evt_id);
        } else {
            // This is a schedule-item's star
            $mainItem = $btn.closest('.schedule-item');
            evt_id = $mainItem.attr('id');
        }
        if (!$mainItem || !$mainItem.length) return;
        // Toggle favorite state
        const newState = !isActive;
        // Update main item
        if (newState) {
            $mainItem.attr('data-favorite', 'true');
            $mainItem.find('.schedule-favorite-toggle').addClass('active').attr('aria-pressed', 'true');
            $mainItem.find('.schedule-favorite-toggle i').removeClass('far').addClass('fas');
        } else {
            $mainItem.removeAttr('data-favorite');
            $mainItem.find('.schedule-favorite-toggle').removeClass('active').attr('aria-pressed', 'false');
            $mainItem.find('.schedule-favorite-toggle i').removeClass('fas').addClass('far');
        }
        // If modal is open for this event, update modal star too
        if (window.location.hash.replace('#', 'onlineevt-') === evt_id) {
            let $modalBtn = jQuery('#modal-schedule-title .schedule-favorite-toggle');
            $modalBtn.toggleClass('active', newState).attr('aria-pressed', newState ? 'true' : 'false');
            $modalBtn.find('i').toggleClass('fas', newState).toggleClass('far', !newState);
        }
        updateFavoritesCookie && updateFavoritesCookie();
    });


// Utility to set a cooki

     function setCookie(name, value, days) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = "expires=" + d.toUTCString();
        document.cookie = name + "=" + encodeURIComponent(value) + ";" + expires + ";path=/";
    }

// Utility to get a cookie
     function getCookie(name) {
        const cname = name + "=";
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1);
            if (c.indexOf(cname) === 0) return c.substring(cname.length, c.length);
        }
        return "";
    }

// Update favorites cookie
     function updateFavoritesCookie() {
        const ids = [];
        jQuery('.schedule-item[data-favorite="true"]').each(function () {
            const id = jQuery(this).attr('id');
            const match = id && id.match(/onlineevt-(\d+)/);
            if (match) ids.push(match[1]);
        });
        setCookie('schedule_favorites', JSON.stringify(ids), 30); // 30 days
        // --- Save to DB if logged in ---
        if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn && window.ONLINESCHED_USER.provider && window.ONLINESCHED_USER.identifier) {
            jQuery.ajax({
                url: '/wp-content/plugins/OnlineSched/includes/save_favorites.php',
                method: 'POST',
                data: {
                    provider: window.ONLINESCHED_USER.provider,
                    identifier: window.ONLINESCHED_USER.identifier,
                    favorites: JSON.stringify(ids)
                },
                success: function(resp) {
                    // Optionally handle success
                },
                error: function(xhr) {
                    // Optionally handle error
                }
            });
        }
    }

    window.updateFavoritesCookie = updateFavoritesCookie;

// Restore favorites from cookie
     function restoreFavoritesFromCookie() {
        const favCookie = getCookie('schedule_favorites');
        if (favCookie) {
            try {
                // Try to parse as array, fallback to object keys if needed
                let favIds = JSON.parse(favCookie);
                if (favIds && typeof favIds === 'object' && !Array.isArray(favIds)) {
                    // If it's an object, convert keys to array
                    favIds = Object.keys(favIds);
                }
                if (Array.isArray(favIds)) {
                    favIds.forEach(function (id) {
                        const $item = jQuery('#onlineevt-' + id);
                        if ($item.length) {
                            $item.attr('data-favorite', 'true');
                            $item.find('.schedule-favorite-toggle').addClass('active').attr('aria-pressed', 'true');
                            $item.find('.schedule-favorite-toggle i').removeClass('far').addClass('fas');
                        }
                    });
                }
            } catch (e) {
                // ignore parse errors
            }
        }
    }
window.restoreFavoritesFromCookie = restoreFavoritesFromCookie;

// Favorite (star) toggle button logic
     function setupFavoriteToggleHandler() {
        jQuery(document).on('click', '.schedule-favorite-toggle', function (e) {
            e.preventDefault();
            const $btn = jQuery(this);
            const isActive = $btn.hasClass('active');
            // Determine if this is the modal's star or a schedule-item's star
            const $modalTitle = $btn.closest('#modal-schedule-title');
            let $mainItem = null;
            let evt_id = null;
            if ($modalTitle.length) {
                // This is the modal's star
                evt_id = window.location.hash.replace('#', 'onlineevt-');
                $mainItem = jQuery('#' + evt_id);
            } else {
                // This is a schedule-item's star
                $mainItem = $btn.closest('.schedule-item');
                evt_id = $mainItem.attr('id');
            }
            if (!$mainItem || !$mainItem.length) return;
            // Toggle favorite state
            const newState = !isActive;
            // Update main item
            if (newState) {
                $mainItem.attr('data-favorite', 'true');
                $mainItem.find('.schedule-favorite-toggle').addClass('active').attr('aria-pressed', 'true');
                $mainItem.find('.schedule-favorite-toggle i').removeClass('far').addClass('fas');
            } else {
                $mainItem.removeAttr('data-favorite');
                $mainItem.find('.schedule-favorite-toggle').removeClass('active').attr('aria-pressed', 'false');
                $mainItem.find('.schedule-favorite-toggle i').removeClass('fas').addClass('far');
            }
            // If modal is open for this event, update modal star too
            if (window.location.hash.replace('#', 'onlineevt-') === evt_id) {
                let $modalBtn = jQuery('#modal-schedule-title .schedule-favorite-toggle');
                $modalBtn.toggleClass('active', newState).attr('aria-pressed', newState ? 'true' : 'false');
                $modalBtn.find('i').toggleClass('fas', newState).toggleClass('far', !newState);
            }
            updateFavoritesCookie && updateFavoritesCookie();
        });
    }

init_favorites();
    function init_favorites() {
        window.restoreFavoritesFromCookie();
        // Fetch login state and then restore favorites
        jQuery.ajax({
            url: '/wp-content/plugins/OnlineSched/includes/login_state.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                window.ONLINESCHED_USER = data;

                if (window.ONLINESCHED_USER && window.ONLINESCHED_USER.loggedIn && window.ONLINESCHED_USER.provider && window.ONLINESCHED_USER.identifier) {
                    // Use favorites from login_state.php response

                    if (data.favorites) {
                        document.cookie = 'schedule_favorites=' + JSON.stringify(data.favorites) + ';path=/;max-age=' + (60*60*24*30);
                    }
                    window.restoreFavoritesFromCookie();
                } else {
                    if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
                }
                if (window.updateLoginLogoutUI) window.updateLoginLogoutUI();
            },
            error: function() {
                if (window.restoreFavoritesFromCookie) window.restoreFavoritesFromCookie();
            }
        });


        jQuery('#login-modal-btn').on('click', function() {
            jQuery('#login-modal').show();
        });
        jQuery('#login-modal-close').on('click', function() {
            jQuery('#login-modal').hide();
        });
        // Optional: Hide modal when clicking outside the modal content
        jQuery('#login-modal').on('click', function(e) {
            if (e.target === this) {
                jQuery(this).hide();
            }
        });
        // Show/hide login/logout buttons based on login state
        if (window.ONLINESCHED_USER && typeof updateLoginLogoutUI === 'function') {
            updateLoginLogoutUI();
        }
    }

};
