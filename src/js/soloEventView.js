/**
 * Solo Event Block - Frontend hydration (Native JS style).
 * 
 * @package OnlineSched
 */

(function() {
    /**
     * Hydrate all solo event cards on the page.
     */
    function hydrateSoloEvents() {
        const cards = document.querySelectorAll('.os-solo-event-card');
        if (cards.length === 0) return;

        // Wait for the main favorites engine to be ready
        const checkInterval = setInterval(() => {
            if (window.fetchOnlineSchedLoginState) {
                clearInterval(checkInterval);
                
                // Get current user state
                window.fetchOnlineSchedLoginState().then(user => {
                    const favorites = normalizeFavorites(user.favorites || getCookie('schedule_favorites'));
                    
                    cards.forEach(card => {
                        const eventId = card.getAttribute('data-os-event-id');
                        const isFavorite = favorites.includes(String(eventId));
                        
                        // Initial favorite state
                        if (isFavorite) {
                            card.setAttribute('data-favorite', 'true');
                            const btn = card.querySelector('.schedule-favorite-toggle');
                            if (btn) {
                                btn.classList.add('active');
                                btn.setAttribute('aria-pressed', 'true');
                                const icon = btn.querySelector('i');
                                if (window.updateIconClasses) {
                                    window.updateIconClasses(icon, true);
                                }
                            }
                        }

                        // Wire up clipboard
                        const copyBtn = card.querySelector('.schedule-clipboard');
                        if (copyBtn) {
                            copyBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                const url = copyBtn.getAttribute('data-url');
                                if (url && navigator.clipboard) {
                                    navigator.clipboard.writeText(url).then(() => {
                                        if (window.osShowClipboardEffect) {
                                            window.osShowClipboardEffect(copyBtn);
                                        }
                                    });
                                }
                            });
                        }

                        // Re-sync favorites if user interaction occurs
                        const favBtn = card.querySelector('.schedule-favorite-toggle');
                        if (favBtn) {
                            favBtn.addEventListener('click', () => {
                                // Small delay to let the global listener in scheduleFavorites.js finish
                                setTimeout(() => {
                                    if (window.updateFavoritesCookie) {
                                        window.updateFavoritesCookie();
                                    }
                                }, 50);
                            });
                        }
                    });
                });
            }
        }, 100);

        // Timeout after 5 seconds
        setTimeout(() => clearInterval(checkInterval), 5000);
    }

    /**
     * Utility: Normalize favorites list.
     */
    function normalizeFavorites(value) {
        let favIds = value;
        if (typeof favIds === 'string') {
            try { favIds = JSON.parse(favIds); } catch (e) { favIds = []; }
        }
        if (favIds && typeof favIds === 'object' && !Array.isArray(favIds)) {
            favIds = Object.keys(favIds);
        }
        if (!Array.isArray(favIds)) return [];
        return favIds.map(id => String(id).replace(/\D/g, '')).filter(Boolean);
    }

    /**
     * Utility: Get cookie value.
     */
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

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hydrateSoloEvents);
    } else {
        hydrateSoloEvents();
    }
})();
