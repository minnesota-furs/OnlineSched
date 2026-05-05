<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
    <!-- Info Modal -->
    <dialog id="info-modal" class="os-modal info-modal" aria-modal="true">
        <div class="os-modal__header">
            <h3>How Favorites, Login, Calendar, and Sharing Work</h3>
            <button type="button" class="os-close" id="info-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <div style="font-size:1.1em;">
                <p><strong><i class="<?php echo esc_attr(onlinesched_get_favorite_icon_classes(false)); ?>" aria-hidden="true"></i> Favorites:</strong> Mark events as favorites to keep track of your schedule. If you're not logged in, your favorites are saved only on this device. If you log in, your favorites are saved to your account and sync across devices.</p>
                <p><strong><i class="fas fa-sign-in-alt" aria-hidden="true"></i> Login:</strong> Logging in lets you save your schedule and favorites to your account, so you can access them from any device. Your login info is only used to identify you, nothing more! And won't be kept past the convention.</p>
                <p><strong><i class="far fa-calendar-alt" aria-hidden="true"></i> Calendar:</strong> Add events or your whole schedule to your calendar (Google, Apple, Outlook). You can add individual events by tapping the calendar icons, or add everything at once from the bottom of the page. Calendar feeds update periodically, but may not always reflect real-time changes. For the latest info, check this website.</p>
                <p><strong><i class="fas fa-copy" aria-hidden="true"></i> Share:</strong> Want to share an event with a friend? Tap the copy icon to grab the event link and paste it anywhere like social media, email, chat, on side of your car, Rico's hand, or stiched on your fursuit. No tech wizardry required!</p>
            </div>
        </div>
    </dialog>
