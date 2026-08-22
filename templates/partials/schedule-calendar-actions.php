<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
                                        <script>
                                            var scheduleMasterRooms = <?php echo json_encode(decode_array_keys($masterRooms));?>;
                                            var scheduleMasterTags = <?php echo json_encode(decode_array_keys($masterTags));?>;
                                        </script>
                                        <div id="schedule-add-to-calendar">
                                            <?php if (onlinesched_calendar_subscriptions_enabled()) : ?>
                                            <div class="os-row" id="schedule-add-to-calendar-div">
                                                <div class="os-col-xs-12 os-col-md-7 schedule-add-to-calendar-blurb d-flex align-items-center">
                                                    <span
                                                            id="schedule-add-to-calendar-message">Add this filtered list to your calendar.</span>
                                                    <label class="schedule-calendar-scope" for="schedule-calendar-scope">
                                                        <span>Events to include</span>
                                                        <select id="schedule-calendar-scope">
                                                            <option value="current">Current calendar feed</option>
                                                            <option value="all-favorites">All favorites</option>
                                                        </select>
                                                    </label>
                                                    <span class="schedule-calendar-scope-help" id="schedule-calendar-scope-help">
                                                        Choose a favorites option to snapshot your favorites.
                                                    </span>
                                                    <button type="button" class="schedule-feed-reset-link" id="schedule-feed-reset-link" hidden>Reset calendar link</button>
                                                    <div class="schedule-feed-reset-confirm" id="schedule-feed-reset-confirm" hidden>
                                                        <span class="schedule-feed-reset-confirm-title">Reset your calendar link?</span>
                                                        <span>Calendars you added before will stop updating. Use the buttons to add your calendar again afterward.</span>
                                                        <span class="schedule-feed-reset-confirm-actions">
                                                            <button type="button" id="schedule-feed-reset-yes">Reset link</button>
                                                            <button type="button" id="schedule-feed-reset-no">Keep current link</button>
                                                        </span>
                                                    </div>
                                                    <div class="schedule-feed-reset-done" id="schedule-feed-reset-done" hidden>Link reset. Old calendars stopped updating. Use the buttons to add your calendar again.</div>
                                                </div>
                                                <div class="os-col-xs-12 os-col-md-5 schedule-add-to-calendar-buttons">
                                                    <button id="schedule-calendar-google" onclick="open_calendar_google()"
                                                            aria-label="Add selection to Google Calendar"><i class="fab fa-google"
                                                                                             aria-hidden="true"></i><br/>
                                                        Add to Google
                                                    </button>
                                                    <button id="schedule-calendar-apple" onclick="open_calendar_apple()"
                                                            aria-label="Add selection to Apple Calendar"><i class="fab fa-apple"
                                                                                             aria-hidden="true"></i><br/>
                                                        Add to Apple<br/>(WebCal)
                                                    </button>
                                                    <button id="schedule-calendar-outlook" onclick="open_calendar_outlook()"
                                                            aria-label="Add selection to Outlook Calendar"><i class="fas fa-calendar-alt"
                                                                                             aria-hidden="true"></i><br/>
                                                        Add to Outlook
                                                    </button>
                                                </div>
                                            </div>
                                            <?php else : ?>
                                            <div class="os-row" id="schedule-add-to-calendar-div">
                                                <div class="os-col-xs-12 schedule-add-to-calendar-blurb">
                                                    Schedule calendar subscriptions are not available yet.
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div id="schedule-key">


                                            <div class="os-row">
                                                <div class="os-col-xs-12">
                                                    <h3>Key</h3>
                                                    <p>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fab fa-apple"
                                                           aria-label="Apple Symbol used to represent Apple's Calendar"
                                                           style="margin-right:10px;"></i>
                                                        Webcal/ICS file and feeds that work with all iCal-compatible and
                                                        web calendars. If your browser isn't set up with application
                                                        shortcuts for webcal://, it will download an ICS file that can
                                                            be used by any calendar app.<br/>
                                                        </span>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fab fa-google"
                                                           aria-label="Google Symbol used to represent Google's Calendar"
                                                           style="margin-right:10px;"></i>
                                                        Native support for adding event entries directly to Google
                                                            Calendar.<br/>
                                                            </span>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fas fa-calendar-alt"
                                                           aria-label="Calendar Symbol used to represent Outlook Web Calendar"
                                                           style="margin-right:10px;"></i>
                                                        Native support for adding events to the Outlook web calendar
                                                            feed.<br/>
                                                        </span>
                                                        <span style="display: flex; align-items: flex-start;">
                                                        <i class="fas fa-copy"
                                                           aria-label="Copy symbol to represent copy to clipboard"
                                                           style="margin-right:10px;"></i>
                                                        Copies the specific programming event URL to the clipboard. It
                                                        can be used to copy and paste through social media, email, and
                                                            any other direct linking.</span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="os-row">
                                                <div class="os-col-xs-12">
                                                    <h2>Important Notes About Calendar Feeds</h2>
                                                    <p>Please be aware that calendar feeds may not always reflect
                                                        real-time updates and are controlled by calendar client. The
                                                        website will always have the most up-to-date information. Update
                                                        frequencies can vary:</p>
                                                    <ul>
                                                        <li>Apple updates upon app/program startup and every 1-3 hours.
                                                            (I&rsquo;ve seen some default to as much as 1 week on my
                                                            Mac)
                                                        </li>
                                                        <li>Google normally updates every 24 hours.</li>
                                                        <li>Outlook updates upon app/program startup &amp; every 1-3
                                                            hours.
                                                        </li>
                                                        <li>Outlook.com updates every 3 hours.</li>
                                                        <li>Yahoo updates every 8-12 hours.</li>
                                                    </ul>
                                                    <p>Once the convention is over, please consider removing the feed
                                                        from your calendar.</p>
                                                </div>
                                            </div>
                                        </div>
