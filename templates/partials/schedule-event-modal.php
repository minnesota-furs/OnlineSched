<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
    <dialog id="modal-schedule" class="os-modal" aria-modal="true">
        <div class="os-modal__header">
            <h3 id="modal-schedule-title"></h3>
            <button type="button" class="os-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <p id="modal-schedule-description">&nbsp;</p>
            <hr>
            <div class="os-row">
                <div class="os-col-sm-6">
                    <dl class="schedule-meta">
                        <dt><i class="fa fa-calendar" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-date">&nbsp;</dd>
                        <dt><i class="far fa-clock" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-time">&nbsp;</dd>
                        <dt><i class="fa fa-map-marker" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-room">&nbsp;</dd>
                    </dl>
                </div>
                <div class="os-col-sm-6">
                    <dl class="schedule-meta">
                        <dt><i class="fa fa-tags" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-tags">&nbsp;</dd>
                        <dt><i class="fa fa-user" aria-hidden="true"></i></dt>
                        <dd id="modal-schedule-panelists">&nbsp;</dd>
                    </dl>
                </div>
            </div>
        </div>
        <?php if ($theming != "schedule") { ?>
            <div class="os-modal__footer"><a href="#" class="os-btn os-btn--default" id="modal-schedule-ical"
                                             target="_blank"><i class="fab fa-apple" aria-hidden="true"></i> Apple
                    Calendar</a> <a href="#" class="os-btn os-btn--default" id="modal-schedule-google" target="_blank"><i
                            class="fab fa-google" aria-hidden="true"></i> Google Calendar</a>
                <button href="#" class="os-btn os-btn--default" id="modal-copy-url">
                    <i class="fas fa-copy" aria-hidden="true"></i> Copy
                </button>
                <?php do_action('os_schedule_modal_footer'); ?>
            </div>
        <?php } ?>
    </dialog>
