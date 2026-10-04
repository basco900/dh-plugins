<?php
/**
 * Plugin Name: DixcoverHub Core
 * Description: Shared services and extension points for DixcoverHub plugins.
 * Version: 0.3.6
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Text Domain: dixcoverhub-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIXCOVERHUB_CORE_VERSION', '0.3.6' );
define( 'DIXCOVERHUB_CORE_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-dixcoverhub-analytics.php';
DixcoverHub_Analytics::init();

/** Small shared helpers used by the DixcoverHub feature plugins. */
final class DixcoverHub_Core {
	/** Register shared opportunity taxonomies used by editor, archive and templates. */
	public static function register_taxonomies() {
		$taxonomies = array(
			'dh_opportunity_type'     => array( __( 'Opportunity types', 'dixcoverhub-core' ), __( 'Opportunity type', 'dixcoverhub-core' ), false ),
			'dh_opportunity_level'    => array( __( 'Opportunity levels', 'dixcoverhub-core' ), __( 'Opportunity level', 'dixcoverhub-core' ), false ),
			'dh_opportunity_mode'     => array( __( 'Opportunity modes', 'dixcoverhub-core' ), __( 'Opportunity mode', 'dixcoverhub-core' ), false ),
			'dh_opportunity_location' => array( __( 'Opportunity locations', 'dixcoverhub-core' ), __( 'Opportunity location', 'dixcoverhub-core' ), true ),
		);
		foreach ( $taxonomies as $slug => $labels ) {
			if ( taxonomy_exists( $slug ) ) { continue; }
			register_taxonomy( $slug, array( 'post' ), array(
				'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1], 'search_items' => sprintf( __( 'Search %s', 'dixcoverhub-core' ), $labels[0] ), 'all_items' => sprintf( __( 'All %s', 'dixcoverhub-core' ), $labels[0] ), 'edit_item' => sprintf( __( 'Edit %s', 'dixcoverhub-core' ), $labels[1] ), 'update_item' => sprintf( __( 'Update %s', 'dixcoverhub-core' ), $labels[1] ), 'add_new_item' => sprintf( __( 'Add %s', 'dixcoverhub-core' ), $labels[1] ), 'new_item_name' => sprintf( __( 'New %s name', 'dixcoverhub-core' ), $labels[1] ), 'menu_name' => $labels[0] ),
				'public' => true, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'hierarchical' => $labels[2], 'query_var' => true, 'rewrite' => array( 'slug' => str_replace( '_', '-', $slug ) ),
			) );
		}
	}

	/** Read a secret/config value without storing it in the WordPress database. */
	public static function config( $constant, $environment = '' ) {
		if ( $constant && defined( $constant ) && is_scalar( constant( $constant ) ) ) {
			$value = trim( (string) constant( $constant ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		if ( $environment ) {
			$value = getenv( $environment );
			if ( false !== $value && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		return '';
	}

	/** Make a server-side OpenAI Responses API call. Secrets never reach the browser. */
	public static function openai_response( $model, $payload, $timeout = 180 ) {
		$key = self::config( 'DIXCOVERHUB_OPENAI_API_KEY', 'OPENAI_API_KEY' );
		if ( ! $key ) {
			return new WP_Error( 'dixcoverhub_ai_unconfigured', __( 'AI writing is not configured. Add DIXCOVERHUB_OPENAI_API_KEY or OPENAI_API_KEY to the server environment.', 'dixcoverhub-core' ) );
		}
		$response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'timeout'     => min( 240, max( 20, absint( $timeout ) ) ),
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( array_merge( array( 'model' => $model ), $payload ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'dixcoverhub_ai_request_failed', __( 'The AI service could not be reached. Check the server connection and try again.', 'dixcoverhub-core' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			$message = is_array( $body ) && ! empty( $body['error']['message'] ) ? sanitize_text_field( $body['error']['message'] ) : '';
			if ( 429 === $status ) {
				$message = __( 'The AI service is busy or the account has reached its limit. Try again shortly.', 'dixcoverhub-core' );
			} elseif ( $status >= 500 || ! $status ) {
				$message = __( 'The AI service had a temporary problem. Try again shortly.', 'dixcoverhub-core' );
			} elseif ( ! $message ) {
				$message = __( 'The AI request was rejected. Check the configured model and API access.', 'dixcoverhub-core' );
			}
			return new WP_Error( 'dixcoverhub_ai_api_error', $message, array( 'status' => $status ) );
		}
		return $body;
	}
}

add_action( 'init', array( 'DixcoverHub_Core', 'register_taxonomies' ), 5 );

/**
 * Announces that the shared DixcoverHub plugin layer is available.
 */
do_action( 'dixcoverhub/core/loaded' );

