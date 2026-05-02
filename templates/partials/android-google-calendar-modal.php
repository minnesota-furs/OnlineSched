<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
    <!-- Android Google Calendar Modal -->
    <dialog id="android-google-calendar-modal" class="os-modal android-gcal-options-four" aria-modal="true">
        <div class="os-modal__header">
            <h3>Google Calendar on Android</h3>
            <button type="button" class="os-close" id="android-google-calendar-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <p><strong>Google Calendar on Android does not support direct calendar subscriptions via webcal/ics links.</strong></p>
            <div class="android-gcal-apology">
                <i class="fa fa-exclamation-triangle" aria-hidden="true" style="margin-right:6px;"></i>
                We apologize for the inconvenience.... Google Calendar on Android does not support direct calendar subscriptions. We hope Google or our team can improve this in the future!
            </div>
            <p class="android-gcal-options-text">You have these options below:</p>
            <ol class="android-gcal-options-list">
                <li class="android-gcal-onetime-section">
                    <span class="android-gcal-option-icon"><i class="fa fa-calendar-plus" aria-hidden="true"></i></span>
                    <strong>One Time Google Event:</strong>
                    <span class="android-gcal-onetime-desc">Create a single event in your Google Calendar for this session. This does not subscribe you to future updates, changes, or cancellations.</span>
                    <div class="android-gcal-buttons">
                        <button class="os-btn os-btn--default os-btn--block android-gcal-onetime-btn"><i class="fab fa-google"></i> <i class="fa fa-calendar"></i> One-Time Google Event</button>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fab fa-google" aria-hidden="true"></i></span>
                    <strong>Try the official Google Calendar link:</strong> This may not work on Android, but you can try. It's been spotty for 15+ years.
                    <div class="android-gcal-buttons">
                        <button class="os-btn os-btn--primary os-btn--block" id="android-gcal-try-link"><i class="fab fa-google"></i> Try Google Calendar (may not work)</button>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fa fa-download" aria-hidden="true"></i></span>
                    <strong>Download the calendar file (.ics):</strong> You can manually import this file into Google Calendar by double clicking it. Those will not sync from the web.
                    <div class="android-gcal-buttons">
                        <a class="os-btn os-btn--default os-btn--block" id="android-gcal-download" href="#" download>
                            <i class="fa fa-download"></i> Download calendar file (.ics)
                        </a>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fa fa-copy" aria-hidden="true"></i></span>
                    <strong>Copy the calendar subscription link:</strong> You can add this link manually in Google Calendar settings.
                    <div class="android-gcal-buttons">
                        <button class="os-btn os-btn--default os-btn--block" id="android-gcal-copy"><i class="fa fa-copy"></i> Copy calendar link</button>
                    </div>
                    <div id="android-gcal-copy-confirm" style="display:none;"><i class="fa fa-check"></i> Link copied!</div>
                    <div class="android-gcal-manual-instructions">
                        <strong>Manual Add Instructions:</strong>
                        <ol>
                            <li>Click the <b>Copy calendar link</b> button above.</li>
                            <li>Go to <a href="https://calendar.google.com" target="_blank" rel="noopener">Google Calendar</a> on a computer.</li>
                            <li>In the left sidebar, click the <b>+</b> next to <b>Other calendars</b> and choose <b>From URL</b>.</li>
                            <li>Paste the copied link and click <b>Add calendar</b>.</li>
                            <li>Your calendar will appear under "Other calendars" and update automatically.</li>
                        </ol>
                    </div>
                </li>
                <li>
                    <span class="android-gcal-option-icon"><i class="fa fa-desktop" aria-hidden="true"></i></span>
                    <strong>Subscribe on a computer:</strong> Google recommends this, <a href="https://support.google.com/calendar/answer/37118?hl=en&co=GENIE.Platform%3DAndroid&oco=1" target="_blank" rel="noopener">Seriously</a>. If you are on the computer and you hit the icon and it will subscribe to Google calendar.
                </li>
            </ol>
        </div>
    </dialog>
