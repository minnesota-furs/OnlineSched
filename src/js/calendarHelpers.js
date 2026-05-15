/**
 * OnlineSched Calendar Helpers - Lightweight scripts for cross-component usage.
 *
 * @package OnlineSched
 * @author Kurst Hyperyote for Furry Migration
 */

(function(window) {
    /**
     * Detect if the device is Android.
     */
    function isAndroidDevice() {
        return /android/i.test(navigator.userAgent);
    }
    window.isAndroidDevice = isAndroidDevice;

    /**
     * Rewrite Google Calendar URL for Android to handle webcal correctly.
     */
    function rewriteGoogleCalendarUrlForAndroid(url) {
        if (!isAndroidDevice()) return url;
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
    window.rewriteGoogleCalendarUrlForAndroid = rewriteGoogleCalendarUrlForAndroid;

    /**
     * Confirm a calendar subscription.
     */
    function confirmCalendarSubscription(link, service) {
        if (confirm('This will subscribe you to your ' + service + ' calendar. This will keep updating until you delete it. Do you want to continue?')) {
            if (window.gtag_event) {
                window.gtag_event('click', 'engagement', 'subscribe-' + service + '-calendar-single');
            }
            setTimeout(function() {
                window.location.href = link.href;
            }, 300);
        }
        return false;
    }
    window.confirmCalendarSubscription = confirmCalendarSubscription;

    /**
     * Confirm Apple Calendar subscription.
     */
    window.confirmCalendarAppleSubscription = function(link) {
        return confirmCalendarSubscription(link, 'Apple');
    };

    /**
     * Confirm Google Calendar subscription (with Android modal support).
     */
    window.confirmCalendarGoogleSubscription = function(link) {
        if (isAndroidDevice() && window.showAndroidGoogleCalendarModal) {
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

            // Solo cards might not have the full eventDetails logic but can still use the basic modal
            window.showAndroidGoogleCalendarModal(googleUrl, rawLink, downloadUrl, null);
            return false;
        }

        link.href = rewriteGoogleCalendarUrlForAndroid(link.href);
        return confirmCalendarSubscription(link, 'Google');
    };

})(window);
