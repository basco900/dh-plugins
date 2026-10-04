<?php
/**
 * Plugin Name: DixcoverHub Custom UI
 * Description: The DixcoverHub design studio for navigation, typography, popups, and site UI.
 * Version: 0.8.17
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: dixcoverhub-custom-ui
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DIXCOVERHUB_CORE_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'DixcoverHub Custom UI requires DixcoverHub Core to be active.', 'dixcoverhub-custom-ui' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'DIXCOVERHUB_CUSTOM_UI_VERSION', '0.8.17' );
define( 'DIXCOVERHUB_CUSTOM_UI_FILE', __FILE__ );
define( 'DIXCOVERHUB_CUSTOM_UI_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-icons.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-fonts.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-single-post.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-archive.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-deadlines.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-footer.php';
require_once __DIR__ . '/includes/class-dixcoverhub-custom-ui-popups.php';

DixcoverHub_Custom_UI::init();
DixcoverHub_Custom_UI_Fonts::init();
DixcoverHub_Custom_UI_Single_Post::init();
DixcoverHub_Custom_UI_Archive::init();
DixcoverHub_Custom_UI_Deadlines::init();
DixcoverHub_Custom_UI_Footer::init();
DixcoverHub_Custom_UI_Popups::init();

/** Let other DixcoverHub plugins detect the UI feature plugin. */
do_action( 'dixcoverhub/custom-ui/loaded' );

