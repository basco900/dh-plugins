<?php
/**
 * Theme compatible WordPress single-post layout for DixcoverHub.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Single_Post {
	/** Register template integration. */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/** Use the bundled article template only for standard posts. */
	public static function template( $template ) {
		$options = DixcoverHub_Custom_UI::options();
		if ( is_admin() || ! is_singular( 'post' ) || empty( $options['single_post_enabled'] ) ) {
			return $template;
		}
		$custom_template = dirname( __DIR__ ) . '/templates/single-post.php';
		return is_readable( $custom_template ) ? $custom_template : $template;
	}

	/** Enqueue page-specific layout and share behaviour. */
	public static function enqueue_assets() {
		$options = DixcoverHub_Custom_UI::options();
		if ( ! is_singular( 'post' ) || empty( $options['single_post_enabled'] ) ) {
			return;
		}
		$stylesheet_path = dirname( __DIR__ ) . '/assets/css/single-post.css';
		$style_version   = is_readable( $stylesheet_path ) ? (string) filemtime( $stylesheet_path ) : DIXCOVERHUB_CUSTOM_UI_VERSION;
		wp_enqueue_style( 'dixcoverhub-single-post', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/single-post.css', array(), $style_version );
		wp_enqueue_script( 'dixcoverhub-single-post', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/single-post.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
	}
}
