<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Calendar Android Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f8f8f8; }
        .container { max-width: 600px; margin: 0 auto; padding: 16px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-radius: 8px; }
        h1 { font-size: 1.5em; margin-bottom: 0.5em; }
        .instructions { background: #e3f2fd; padding: 12px; border-radius: 6px; margin-bottom: 1em; }
        .link-row { display: flex; align-items: center; margin-bottom: 18px; }
        .calendar-link { flex: 1; font-size: 1.1em; padding: 12px; border-radius: 6px; border: 1px solid #ddd; background: #fafafa; text-align: left; word-break: break-all; }
        .calendar-link.android { border: 2px solid #4caf50; background: #e8f5e9; }
        .copy-btn { margin-left: 10px; padding: 10px 14px; font-size: 1em; border-radius: 6px; border: none; background: #2196f3; color: #fff; cursor: pointer; }
        .copy-btn:active { background: #1976d2; }
        .ua-info { font-size: 0.95em; color: #555; margin-bottom: 1em; }
        .android-detected { color: #388e3c; font-weight: bold; }
        .not-android { color: #d32f2f; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1>Google Calendar Android Link Test</h1>
    <div class="instructions">
        <strong>Instructions:</strong><br>
        <ul>
            <li>Test each link below on your Android device.</li>
            <li>The <span style="color:#388e3c;">highlighted</span> link is recommended for Android.</li>
            <li>Tap a link to try subscribing in Google Calendar.</li>
            <li>Use the <b>Copy</b> button to copy the link for sharing or manual testing.</li>
        </ul>
    </div>
    <div class="ua-info">
        <span id="ua-string"></span><br>
        <span id="android-detect"></span>
    </div>
    <div class="link-row">
        <a href="https://calendar.google.com/calendar/r?cid=webcal%3A%2F%2Fwww.furrymigration.org%2Fwp-content%2Fplugins%2FOnlineSched%2Ficalby.php%3Froom%3Dall" class="calendar-link" id="link-webcal">Webcal (webcal://...)</a>
        <button class="copy-btn" onclick="copyLink('link-webcal')">Copy</button>
    </div>
    <div class="link-row">
        <a href="https://calendar.google.com/calendar/r?cid=https%3A%2F%2Fwww.furrymigration.org%2Fwp-content%2Fplugins%2FOnlineSched%2Ficalby.php%3Froom%3Dall" class="calendar-link" id="link-https">HTTPS (https://...)</a>
        <button class="copy-btn" onclick="copyLink('link-https')">Copy</button>
    </div>
    <div class="link-row">
        <a href="https://calendar.google.com/calendar/r?cid=http%3A%2F%2Fwww.furrymigration.org%2Fwp-content%2Fplugins%2FOnlineSched%2Ficalby.php%3Froom%3Dall" class="calendar-link" id="link-http">HTTP (http://...)</a>
        <button class="copy-btn" onclick="copyLink('link-http')">Copy</button>
    </div>

	<div class="link-row">
		<a href="https://calendar.google.com/calendar/r?cid=https://www.furrymigration.org/wp-content/plugins/OnlineSched/icalby.php?room=all" class="calendar-link" id="ulink-https">HTTPS (https://...) unencoded</a>
		<button class="copy-btn" onclick="copyLink('ulink-https')">Copy</button>
	</div>
	<div class="link-row">
		<a href="https://calendar.google.com/calendar/r?cid=http://www.furrymigration.org/wp-content/plugins/OnlineSched/icalby.php?room=all" class="calendar-link" id="ulink-http">HTTP (http://...) unencoded</a>
		<button class="copy-btn" onclick="copyLink('ulink-http')">Copy</button>
	</div>

	<a href="https://calendar.google.com/calendar/r?cid=https%3A%2F%2Fwww.furrymigration.org%2Fwp-content%2Fplugins%2FOnlineSched%2Ficalby.php%3Froom%3Dall" target="_blank">
		Add to Google Calendar
	</a>

    <div style="margin-top:2em; font-size:0.95em; color:#666;">
        <strong>Notes:</strong><br>
        - Google Calendar on Android may not support <b>webcal://</b> links directly.<br>
        - Try <b>https://</b> or <b>http://</b> links if webcal fails.<br>
        - If none work, try adding the feed manually in Google Calendar settings.<br>
        - You can also test these links on desktop for comparison.
    </div>
</div>
<script>
    // Detect Android
    var ua = navigator.userAgent;
    document.getElementById('ua-string').textContent = 'User Agent: ' + ua;
    var isAndroid = /android/i.test(ua);
    var androidDetect = document.getElementById('android-detect');
    if (isAndroid) {
        androidDetect.textContent = 'Android detected!';
        androidDetect.className = 'android-detected';
        // Highlight the recommended link for Android (https)
        document.getElementById('link-https').classList.add('android');
    } else {
        androidDetect.textContent = 'Android NOT detected.';
        androidDetect.className = 'not-android';
    }
    // Copy to clipboard function
    function copyLink(id) {
        var link = document.getElementById(id).href;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(link).then(function() {
                alert('Copied to clipboard!');
            }, function() {
                alert('Failed to copy.');
            });
        } else {
            // Fallback for older browsers
            var temp = document.createElement('input');
            temp.value = link;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            alert('Copied to clipboard!');
        }
    }
</script>
</body>
</html>