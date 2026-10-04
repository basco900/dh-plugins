<?php
/**
 * Plugin Name: DixcoverHub AI Editor
 * Description: AI-assisted opportunity writing, summaries, and editorial tools for DixcoverHub.
 * Version: 0.3.8
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: dixcoverhub-ai-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Wait until every plugin is loaded so activation order does not matter. */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! defined( 'DIXCOVERHUB_CORE_VERSION' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-warning"><p>' . esc_html__( 'DixcoverHub AI Editor requires DixcoverHub Core to be active.', 'dixcoverhub-ai-editor' ) . '</p></div>';
					}
				}
			);
			return;
		}
		if ( ! defined( 'DIXCOVERHUB_AI_EDITOR_VERSION' ) ) { define( 'DIXCOVERHUB_AI_EDITOR_VERSION', '0.3.8' ); }
		if ( ! defined( 'DIXCOVERHUB_AI_EDITOR_FILE' ) ) { define( 'DIXCOVERHUB_AI_EDITOR_FILE', __FILE__ ); }
		if ( ! defined( 'DIXCOVERHUB_AI_EDITOR_DIR' ) ) { define( 'DIXCOVERHUB_AI_EDITOR_DIR', plugin_dir_path( __FILE__ ) ); }
		if ( ! defined( 'DIXCOVERHUB_AI_EDITOR_URL' ) ) { define( 'DIXCOVERHUB_AI_EDITOR_URL', plugin_dir_url( __FILE__ ) ); }
		require_once DIXCOVERHUB_AI_EDITOR_DIR . 'includes/class-dixcoverhub-ai-editor.php';
		DixcoverHub_AI_Editor::init();
		/** Let other DixcoverHub plugins detect the AI feature plugin. */
		do_action( 'dixcoverhub/ai-editor/loaded' );
	},
	20
);

