<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
    <!-- Login Modal -->
    <dialog id="login-modal" class="os-modal login-modal" aria-modal="true" aria-label="Login">
        <div class="os-modal__header">
            <h3>Login</h3>
            <button type="button" class="os-close" id="login-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="os-modal__body">
            <p>You can keep track of your schedule by logging in. If you choose not to log in, any events you favorite will be saved locally on your device.</p>
            <p>Login with your account:</p>
            <div class="login-provider-list">
            <?php
            $social_config = require ONLINESCHED_PLUGIN_DIR . 'includes/social_providers_config.php';
            if (isset($social_config['providers']) && is_array($social_config['providers'])) {
                foreach ($social_config['providers'] as $provider => $providerData) {
                    $showProvider = function_exists('onlinesched_social_provider_is_enabled')
                        ? onlinesched_social_provider_is_enabled($provider, $providerData)
                        : false;
                    if ($showProvider) {
                        $icon = onlinesched_get_provider_icon_html($provider, $providerData);
                        echo '<div class="login-provider-item"><button onclick="openLoginWithProvider(\'' . esc_js($provider) . '\', event)" class="os-btn os-btn--default">' . $icon . 'Login with ' . esc_html($provider) . '</button></div>';
                    }
                }
            }
            ?>
            </div>
        </div>
    </dialog>
