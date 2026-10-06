<?php
/**
 * Custom UI plugin bootstrap and first feature: the public navbar.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI {
	const OPTION_KEY = 'dixcoverhub_custom_ui_options';
	const OPT_IN_MIGRATION_OPTION = 'dixcoverhub_custom_ui_opt_in_migrated';
	const BOTTOM_NAV_RESTORATION_OPTION = 'dixcoverhub_custom_ui_bottom_nav_restored';

	/** @var bool Prevent the body hook and shortcode from printing twice. */
	private static $rendered = false;

	/** Register the plugin's WordPress hooks. */
	public static function init() {
		self::migrate_feature_activation();
		self::restore_bottom_navigation_for_existing_site();
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'maybe_home_template' ), 8 );
		add_action( 'wp_body_open', array( __CLASS__, 'render_at_body_open' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'render_bottom_navigation' ), 9 );
		add_filter( 'body_class', array( __CLASS__, 'add_bottom_navigation_body_class' ) );
		add_action( 'wp', array( __CLASS__, 'maybe_replace_astra_header' ), 20 );
		add_shortcode( 'dixcoverhub_navbar', array( __CLASS__, 'render_shortcode' ) );
		add_filter( 'render_block', array( __CLASS__, 'maybe_replace_block_theme_header' ), 5, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( DIXCOVERHUB_CUSTOM_UI_FILE ), array( __CLASS__, 'plugin_action_links' ) );
	}

	/** Default first-version navbar options. */
	public static function defaults() {
		return array(
			'home_enabled'             => 0,
			'home_title_before'        => 'Discover Your Next',
			'home_title_highlight'     => 'Opportunity',
			'home_title_after'         => 'Here.',
			'home_description'         => 'Discover verified scholarships, full-time jobs, internships, and grants from world-class companies and institutions worldwide.',
			'home_cta_label'           => 'Explore opportunities',
			'home_cta_url'             => '/opportunities/',
			'home_category_strip'      => 1,
			'home_deck_count'           => 10,
			'home_background_color'    => '#f4f7fb',
			'home_highlight_color'     => '#611f69',
			'enabled'                  => 0,
			'automatic_display'        => 1,
			'replace_block_header'     => 1,
			'sticky'                   => 0,
			'transparent'              => 0,
			'logo_url'                 => '',
			'show_wordmark'            => 1,
			'logo_max_width'           => 132,
			'logo_max_height'          => 32,
			'logo_mobile_max_width'    => 108,
			'logo_mobile_max_height'   => 28,
			'primary_color'            => '#611f69',
			'content_width'            => 1100,
			'navbar_surface_color'     => '#f8f2fa',
			'navbar_text_color'        => '#18181b',
			'navbar_link_color'        => '#52525b',
			'navbar_border_color'      => '#e4e4e7',
			'manual_nav_enabled'       => 0,
			'manual_nav_items'         => array(),
			'bottom_nav_enabled'      => 0,
			'bottom_nav_home_label'   => 'Home',
			'bottom_nav_community_label' => 'Join Our Community',
			'bottom_nav_community_url' => 'https://whatsapp.com/channel/0029Va9uQXIAYlUP7x3kDQ2T',
			'bottom_nav_more_label'   => 'More',
			'bottom_nav_panel_title'  => 'Explore More',
			'bottom_nav_jobs_label'   => 'Jobs',
			'bottom_nav_jobs_description' => 'Find your next role.',
			'bottom_nav_jobs_url'     => '/opportunities?category=job',
			'bottom_nav_opportunities_label' => 'Opportunities',
			'bottom_nav_opportunities_description' => 'Browse every opportunity.',
			'bottom_nav_opportunities_url' => '/opportunities/',
			'bottom_nav_deadlines_label' => 'Deadlines',
			'bottom_nav_deadlines_description' => 'See what is closing soon.',
			'bottom_nav_deadlines_url' => '/deadlines/',
			'bottom_nav_accent_color' => '#611f69',
			'jobs_label'               => 'Jobs',
			'jobs_parent_slug'         => 'jobs',
			'jobs_category_limit'      => 8,
			'opportunities_label'      => 'Opportunities',
			'opportunity_term_slugs'   => 'internships, scholarships, programs-calls, training-bootcamps, competitions-challenges',
			'taxonomy'                 => 'category',
			'show_counts'              => 1,
			'deadlines_label'          => 'Deadlines',
			'deadlines_url'            => '',
			'about_label'              => 'About',
			'about_url'                => '',
			'contact_label'            => 'Contact',
			'contact_url'              => '',
			'extra_menu_id'            => 0,
			'show_login'               => 1,
			'login_label'              => 'Log in',
			'login_url'                => '',
			'cta_label'                => 'Explore',
			'cta_url'                  => '',
			'cta_icon'                 => 'ArrowUpRight01Icon',
			'mobile_cta_label'         => 'Explore Opportunities',
			'single_post_enabled'      => 0,
			'single_post_sidebar_enabled' => 1,
			'single_post_sidebar_featured_enabled' => 1,
			'single_post_sidebar_trending_enabled' => 1,
			'single_post_sidebar_latest_enabled' => 1,
			'single_post_show_apply'   => 1,
			'single_post_show_summary' => 1,
			'single_post_show_faqs'    => 1,
			'single_post_show_tags'    => 1,
			'single_post_show_related' => 1,
			'single_post_protect_content' => 0,
			'single_post_sidebar_count' => 3,
			'single_post_sidebar_trending_count' => 4,
			'single_post_sidebar_latest_count' => 5,
			'single_post_width'        => 1100,
			'single_post_header_color' => '#f8f2fa',
			'archive_enabled'          => 0,
			'archive_sidebar_enabled'  => 1,
			'archive_sidebar_featured_enabled' => 1,
			'archive_sidebar_trending_enabled' => 1,
			'archive_sidebar_latest_enabled' => 1,
			'archive_sidebar_featured_count' => 3,
			'archive_sidebar_trending_count' => 4,
			'archive_sidebar_latest_count' => 5,
			'archive_heading'          => 'Explore Opportunities',
			'archive_intro'            => 'Browse jobs, scholarships, internships, funding, fellowships, and programmes.',
			'archive_posts_per_page'   => 18,
			'deadline_page_enabled'    => 0,
			'deadline_page_sidebar_enabled' => 1,
			'deadline_page_heading'    => 'Deadlines',
			'deadline_page_intro'      => 'A clear view of opportunities closing soon.',
			'deadline_page_results_per_page' => 36,
			'deadline_page_preview_per_window' => 10,
			'footer_enabled'          => 0,
			'footer_automatic'        => 1,
			'footer_replace_theme'    => 1,
			'footer_show_brand'       => 1,
			'footer_show_social_links' => 1,
			'footer_show_wordmark'    => 1,
			'footer_logo_url'         => '',
			'footer_logo_alt'         => '',
			'footer_description'      => 'Discover jobs, scholarships, internships, and programmes selected to help you take your next step.',
			'footer_cta_enabled'      => 0,
			'footer_cta_label'        => 'Explore opportunities',
			'footer_cta_url'          => '/opportunities/',
			'footer_banner_enabled'   => 0,
			'footer_banner_badge'     => 'Updated daily',
			'footer_banner_heading'   => 'Find a move worth making.',
			'footer_banner_primary_label' => 'Join Our Community',
			'footer_banner_primary_url' => 'https://whatsapp.com/channel/0029Va9uQXIAYlUP7x3kDQ2T',
			'footer_banner_primary_new_tab' => 1,
			'footer_banner_secondary_label' => 'Explore opportunities',
			'footer_banner_secondary_url' => '/opportunities/',
			'footer_banner_secondary_new_tab' => 0,
			'footer_banner_start_color' => '#2a1234',
			'footer_banner_end_color' => '#611f69',
			'footer_banner_text_color' => '#ffffff',
			'footer_banner_badge_bg_color' => '#6e3d79',
			'footer_banner_badge_text_color' => '#f4e9f6',
			'footer_banner_button_bg_color' => '#ffffff',
			'footer_banner_button_text_color' => '#211827',
			'footer_banner_secondary_border_color' => '#a985b0',
			'footer_banner_radius'     => 22,
			'footer_secondary_text'   => 'Built for ambitious talent.',
			'footer_placements'       => array( 'global' ),
			'footer_path_patterns'    => '',
			'footer_legal_links'      => array(
				array( 'label' => 'Privacy', 'url' => '/privacy-policy/', 'icon' => '', 'new_tab' => 0 ),
				array( 'label' => 'Terms', 'url' => '/terms-of-use/', 'icon' => '', 'new_tab' => 0 ),
				array( 'label' => 'Disclaimer', 'url' => '/disclaimer/', 'icon' => '', 'new_tab' => 0 ),
			),
			'footer_copyright'        => 'Copyright © %year% %site_name% | All rights reserved.',
			'footer_columns'          => array(
				array( 'heading' => 'Explore', 'description' => '', 'links' => array( array( 'label' => 'All opportunities', 'url' => '/opportunities/', 'icon' => '', 'new_tab' => 0 ), array( 'label' => 'Jobs', 'url' => '/opportunities?category=job', 'icon' => '', 'new_tab' => 0 ), array( 'label' => 'Deadlines', 'url' => '/deadlines/', 'icon' => '', 'new_tab' => 0 ) ) ),
				array( 'heading' => 'DixcoverHub', 'description' => '', 'links' => array( array( 'label' => 'About us', 'url' => '/about/', 'icon' => '', 'new_tab' => 0 ), array( 'label' => 'Contact', 'url' => '/contact/', 'icon' => '', 'new_tab' => 0 ), array( 'label' => 'Log in', 'url' => '/login/', 'icon' => '', 'new_tab' => 0 ) ) ),
			),
			'footer_social_links'     => array( array( 'label' => 'WhatsApp Community', 'url' => 'https://whatsapp.com/channel/0029Va9uQXIAYlUP7x3kDQ2T', 'icon' => 'WhatsappIcon', 'new_tab' => 1 ) ),
			'footer_background_color' => '#211827',
			'footer_text_color'       => '#ffffff',
			'footer_muted_color'      => '#c9bfcd',
			'footer_link_color'       => '#f2d9f5',
			'footer_border_color'     => '#4b3c51',
			'footer_content_width'    => 1100,
			'logo_enabled'            => 0,
			'fonts_enabled'           => 0,
			'popup_enabled'           => 0,
		);
	}

	/** Read saved values without losing defaults when settings are added later. */
	public static function options() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Turn off legacy auto-enabled features once while preserving their settings.
	 *
	 * Older plugin builds shipped these features enabled by default. The first
	 * request after upgrade makes activation explicit without deleting any of
	 * the user's navbar, footer, font, or layout configuration.
	 */
	private static function migrate_feature_activation() {
		if ( '1' === get_option( self::OPT_IN_MIGRATION_OPTION, '' ) ) {
			return;
		}
		$saved = get_option( self::OPTION_KEY, array() );
		if ( is_array( $saved ) ) {
			foreach ( array( 'enabled', 'single_post_enabled', 'archive_enabled', 'footer_enabled', 'logo_enabled', 'fonts_enabled' ) as $feature ) {
				$saved[ $feature ] = 0;
			}
			update_option( self::OPTION_KEY, $saved, false );
		}
		update_option( self::OPT_IN_MIGRATION_OPTION, '1', false );
	}

	/** Restore the quick navigation switch on a site that received the mistaken removal update. */
	private static function restore_bottom_navigation_for_existing_site() {
		if ( '1' === get_option( self::BOTTOM_NAV_RESTORATION_OPTION, '' ) ) { return; }
		if ( '1' === get_option( 'dixcoverhub_custom_ui_bottom_nav_removed', '' ) ) {
			$saved = get_option( self::OPTION_KEY, array() );
			if ( is_array( $saved ) ) {
				$saved['bottom_nav_enabled'] = 1;
				update_option( self::OPTION_KEY, $saved, false );
			}
			delete_option( 'dixcoverhub_custom_ui_bottom_nav_removed' );
		}
		update_option( self::BOTTOM_NAV_RESTORATION_OPTION, '1', false );
	}

	/** Add the settings screen under Appearance. */
	public static function add_settings_page() {
		add_theme_page(
			__( 'DixcoverHub Studio', 'dixcoverhub-custom-ui' ),
			__( 'DixcoverHub UI', 'dixcoverhub-custom-ui' ),
			'manage_options',
			'dixcoverhub-custom-ui',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/** Register and sanitize the shared Studio settings record. */
	public static function register_settings() {
		register_setting(
			'dixcoverhub_custom_ui_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
			)
		);
	}

	/** Validate all values before WordPress stores them. */
	public static function sanitize_options( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION_KEY, array() );
		$existing = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
		$output   = $existing;
		$get_text = static function ( $key ) use ( $input, $existing ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : ( isset( $existing[ $key ] ) ? $existing[ $key ] : '' );
			return is_scalar( $value ) ? (string) $value : '';
		};

		$checkboxes = array( 'home_enabled', 'home_category_strip', 'enabled', 'automatic_display', 'replace_block_header', 'sticky', 'transparent', 'show_wordmark', 'show_counts', 'show_login', 'manual_nav_enabled', 'bottom_nav_enabled', 'single_post_enabled', 'single_post_sidebar_enabled', 'single_post_sidebar_featured_enabled', 'single_post_sidebar_trending_enabled', 'single_post_sidebar_latest_enabled', 'single_post_show_apply', 'single_post_show_summary', 'single_post_show_faqs', 'single_post_show_tags', 'single_post_show_related', 'single_post_protect_content', 'archive_enabled', 'archive_sidebar_enabled', 'archive_sidebar_featured_enabled', 'archive_sidebar_trending_enabled', 'archive_sidebar_latest_enabled', 'deadline_page_enabled', 'deadline_page_sidebar_enabled', 'footer_enabled', 'footer_automatic', 'footer_replace_theme', 'footer_show_brand', 'footer_show_social_links', 'footer_show_wordmark', 'footer_cta_enabled', 'footer_banner_enabled', 'footer_banner_primary_new_tab', 'footer_banner_secondary_new_tab', 'logo_enabled', 'fonts_enabled', 'popup_enabled' );
		foreach ( $checkboxes as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			} else {
				$output[ $key ] = empty( $existing[ $key ] ) ? 0 : 1;
			}
		}

		$text_fields = array(
			'jobs_label',
			'opportunities_label',
			'deadlines_label',
			'about_label',
			'contact_label',
			'login_label',
			'cta_label',
			'mobile_cta_label',
			'bottom_nav_home_label',
			'bottom_nav_community_label',
			'bottom_nav_more_label',
			'bottom_nav_panel_title',
			'bottom_nav_jobs_label',
			'bottom_nav_jobs_description',
			'bottom_nav_opportunities_label',
			'bottom_nav_opportunities_description',
			'bottom_nav_deadlines_label',
			'bottom_nav_deadlines_description',
			'archive_heading',
			'archive_intro',
			'home_title_before',
			'home_title_highlight',
			'home_title_after',
			'home_description',
			'home_cta_label',
			'deadline_page_heading',
			'deadline_page_intro',
			'footer_description',
			'footer_logo_alt',
			'footer_cta_label',
			'footer_banner_badge',
			'footer_banner_heading',
			'footer_banner_primary_label',
			'footer_banner_secondary_label',
			'footer_copyright',
			'footer_secondary_text',
		);
		foreach ( $text_fields as $key ) {
			$value          = sanitize_text_field( $get_text( $key ) );
			$output[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		$url_fields = array( 'logo_url', 'deadlines_url', 'about_url', 'contact_url', 'login_url', 'cta_url', 'home_cta_url', 'bottom_nav_community_url', 'bottom_nav_jobs_url', 'bottom_nav_opportunities_url', 'bottom_nav_deadlines_url', 'footer_logo_url', 'footer_cta_url', 'footer_banner_primary_url', 'footer_banner_secondary_url' );
		foreach ( $url_fields as $key ) {
			$output[ $key ] = esc_url_raw( trim( $get_text( $key ) ) );
		}

		$taxonomy = sanitize_key( $get_text( 'taxonomy' ) );
		$taxonomy = $taxonomy ? $taxonomy : 'category';
		$taxonomy_object = get_taxonomy( $taxonomy );
		$output['taxonomy'] = $taxonomy_object && ! empty( $taxonomy_object->public ) ? $taxonomy : 'category';

		$jobs_parent_slug = sanitize_title( $get_text( 'jobs_parent_slug' ) );
		$output['jobs_parent_slug'] = $jobs_parent_slug ? $jobs_parent_slug : $defaults['jobs_parent_slug'];
		$jobs_category_limit = $get_text( 'jobs_category_limit' );
		$output['jobs_category_limit'] = '' !== $jobs_category_limit ? min( 12, max( 1, absint( $jobs_category_limit ) ) ) : $defaults['jobs_category_limit'];

		$opportunity_slugs = preg_split( '/[\s,]+/', $get_text( 'opportunity_term_slugs' ) );
		$opportunity_slugs = array_filter( array_map( 'sanitize_title', (array) $opportunity_slugs ) );
		$output['opportunity_term_slugs'] = implode( ', ', array_unique( $opportunity_slugs ) );

		$content_width = $get_text( 'content_width' );
		$output['content_width'] = '' !== $content_width ? min( 1440, max( 960, absint( $content_width ) ) ) : $defaults['content_width'];
		$output['logo_max_width'] = min( 300, max( 48, absint( $get_text( 'logo_max_width' ) ?: $defaults['logo_max_width'] ) ) );
		$output['logo_max_height'] = min( 80, max( 20, absint( $get_text( 'logo_max_height' ) ?: $defaults['logo_max_height'] ) ) );
		$output['logo_mobile_max_width'] = min( 260, max( 40, absint( $get_text( 'logo_mobile_max_width' ) ?: $defaults['logo_mobile_max_width'] ) ) );
		$output['logo_mobile_max_height'] = min( 72, max( 18, absint( $get_text( 'logo_mobile_max_height' ) ?: $defaults['logo_mobile_max_height'] ) ) );
		$output['primary_color'] = sanitize_hex_color( $get_text( 'primary_color' ) );
		if ( ! $output['primary_color'] ) {
			$output['primary_color'] = $defaults['primary_color'];
		}
		$output['single_post_sidebar_count'] = min( 8, max( 3, absint( $get_text( 'single_post_sidebar_count' ) ?: $defaults['single_post_sidebar_count'] ) ) );
		$output['single_post_sidebar_trending_count'] = min( 8, max( 2, absint( $get_text( 'single_post_sidebar_trending_count' ) ?: $defaults['single_post_sidebar_trending_count'] ) ) );
		$output['single_post_sidebar_latest_count'] = min( 8, max( 2, absint( $get_text( 'single_post_sidebar_latest_count' ) ?: $defaults['single_post_sidebar_latest_count'] ) ) );
		$output['single_post_width'] = min( 1440, max( 960, absint( $get_text( 'single_post_width' ) ?: $defaults['single_post_width'] ) ) );
		$output['archive_posts_per_page'] = min( 36, max( 6, absint( $get_text( 'archive_posts_per_page' ) ?: $defaults['archive_posts_per_page'] ) ) );
		$output['home_deck_count'] = min( 20, max( 1, absint( $get_text( 'home_deck_count' ) ?: $defaults['home_deck_count'] ) ) );
		$output['archive_sidebar_featured_count'] = min( 8, max( 1, absint( $get_text( 'archive_sidebar_featured_count' ) ?: $defaults['archive_sidebar_featured_count'] ) ) );
		$output['archive_sidebar_trending_count'] = min( 8, max( 1, absint( $get_text( 'archive_sidebar_trending_count' ) ?: $defaults['archive_sidebar_trending_count'] ) ) );
		$output['archive_sidebar_latest_count'] = min( 8, max( 1, absint( $get_text( 'archive_sidebar_latest_count' ) ?: $defaults['archive_sidebar_latest_count'] ) ) );
		$output['deadline_page_results_per_page'] = min( 60, max( 6, absint( $get_text( 'deadline_page_results_per_page' ) ?: $defaults['deadline_page_results_per_page'] ) ) );
		$output['deadline_page_preview_per_window'] = min( 18, max( 3, absint( $get_text( 'deadline_page_preview_per_window' ) ?: $defaults['deadline_page_preview_per_window'] ) ) );
		$output['footer_content_width'] = min( 1440, max( 960, absint( $get_text( 'footer_content_width' ) ?: $defaults['footer_content_width'] ) ) );
		$footer_banner_radius = $get_text( 'footer_banner_radius' );
		$output['footer_banner_radius'] = '' !== $footer_banner_radius ? min( 48, max( 0, absint( $footer_banner_radius ) ) ) : $defaults['footer_banner_radius'];
		$output['single_post_header_color'] = sanitize_hex_color( $get_text( 'single_post_header_color' ) );
		if ( ! $output['single_post_header_color'] ) {
			$output['single_post_header_color'] = $defaults['single_post_header_color'];
		}
		foreach ( array( 'navbar_surface_color', 'navbar_text_color', 'navbar_link_color', 'navbar_border_color', 'bottom_nav_accent_color', 'home_background_color', 'home_highlight_color', 'footer_background_color', 'footer_text_color', 'footer_muted_color', 'footer_link_color', 'footer_border_color', 'footer_banner_start_color', 'footer_banner_end_color', 'footer_banner_text_color', 'footer_banner_badge_bg_color', 'footer_banner_badge_text_color', 'footer_banner_button_bg_color', 'footer_banner_button_text_color', 'footer_banner_secondary_border_color' ) as $color_key ) {
			$output[ $color_key ] = sanitize_hex_color( $get_text( $color_key ) );
			if ( ! $output[ $color_key ] ) {
				$output[ $color_key ] = $defaults[ $color_key ];
			}
		}
		$manual_items_input = ! empty( $input['manual_nav_items_present'] ) ? ( isset( $input['manual_nav_items'] ) && is_array( $input['manual_nav_items'] ) ? $input['manual_nav_items'] : array() ) : ( array_key_exists( 'manual_nav_items', $input ) ? $input['manual_nav_items'] : $existing['manual_nav_items'] );
		$output['manual_nav_items'] = self::sanitize_manual_nav_items( $manual_items_input );
		$footer_columns_input = ! empty( $input['footer_columns_present'] ) ? ( isset( $input['footer_columns'] ) && is_array( $input['footer_columns'] ) ? $input['footer_columns'] : array() ) : ( array_key_exists( 'footer_columns', $input ) ? $input['footer_columns'] : $existing['footer_columns'] );
		$output['footer_columns'] = self::sanitize_footer_columns( $footer_columns_input );
		$footer_social_input = ! empty( $input['footer_social_links_present'] ) ? ( isset( $input['footer_social_links'] ) && is_array( $input['footer_social_links'] ) ? $input['footer_social_links'] : array() ) : ( array_key_exists( 'footer_social_links', $input ) ? $input['footer_social_links'] : $existing['footer_social_links'] );
		$output['footer_social_links'] = self::sanitize_footer_social_links( $footer_social_input );
		$footer_legal_input = ! empty( $input['footer_legal_links_present'] ) ? ( isset( $input['footer_legal_links'] ) && is_array( $input['footer_legal_links'] ) ? $input['footer_legal_links'] : array() ) : ( array_key_exists( 'footer_legal_links', $input ) ? $input['footer_legal_links'] : $existing['footer_legal_links'] );
		$output['footer_legal_links'] = self::sanitize_footer_legal_links( $footer_legal_input );
		$valid_placements = array( 'global', 'home', 'opportunities', 'posts', 'pages', 'path' );
		$placement_values = isset( $input['footer_placements'] ) && is_array( $input['footer_placements'] ) ? $input['footer_placements'] : ( ! empty( $input['footer_placements_present'] ) ? array() : (array) $existing['footer_placements'] );
		$placement_values = array_filter( $placement_values, 'is_scalar' );
		$output['footer_placements'] = array_values( array_unique( array_intersect( array_map( 'sanitize_key', $placement_values ), $valid_placements ) ) );
		if ( ! $output['footer_placements'] ) {
			$output['footer_placements'] = array( 'global' );
		}
		$patterns_value = isset( $input['footer_path_patterns'] ) && is_scalar( $input['footer_path_patterns'] ) ? (string) $input['footer_path_patterns'] : (string) $existing['footer_path_patterns'];
		$patterns_value = sanitize_textarea_field( $patterns_value );
		$patterns = array_map( array( __CLASS__, 'sanitize_footer_path' ), preg_split( '/\r\n|\r|\n/', $patterns_value ) );
		$output['footer_path_patterns'] = implode( "\n", array_slice( array_values( array_unique( array_filter( $patterns ) ) ), 0, 30 ) );
		$cta_icon = $get_text( 'cta_icon' );
		$output['cta_icon'] = in_array( $cta_icon, self::manual_icon_names(), true ) ? $cta_icon : $defaults['cta_icon'];

		$menu_id = absint( $get_text( 'extra_menu_id' ) );
		$output['extra_menu_id'] = $menu_id && wp_get_nav_menu_object( $menu_id ) ? $menu_id : 0;

		return $output;
	}

	/** Sanitize manually created navigation links and their optional dropdowns. */
	private static function sanitize_manual_nav_items( $items ) {
		$valid_icons = self::manual_icon_names();
		$output = array();
		foreach ( array_slice( is_array( $items ) ? $items : array(), 0, 12 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = sanitize_text_field( isset( $item['label'] ) && is_scalar( $item['label'] ) ? (string) $item['label'] : '' );
			$url = esc_url_raw( trim( isset( $item['url'] ) && is_scalar( $item['url'] ) ? (string) $item['url'] : '' ) );
			$type_raw = isset( $item['type'] ) && is_scalar( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
			$type = in_array( $type_raw, array( 'dropdown', 'text' ), true ) ? $type_raw : 'link';
			$icon = isset( $item['icon'] ) && is_scalar( $item['icon'] ) ? sanitize_text_field( (string) $item['icon'] ) : '';
			$icon = in_array( $icon, $valid_icons, true ) ? $icon : '';
			$children = array();
			foreach ( array_slice( is_array( $item['children'] ?? null ) ? $item['children'] : array(), 0, 12 ) as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$child_label = sanitize_text_field( isset( $child['label'] ) && is_scalar( $child['label'] ) ? (string) $child['label'] : '' );
				$child_url = esc_url_raw( trim( isset( $child['url'] ) && is_scalar( $child['url'] ) ? (string) $child['url'] : '' ) );
				$child_icon = isset( $child['icon'] ) && is_scalar( $child['icon'] ) ? sanitize_text_field( (string) $child['icon'] ) : '';
				$child_icon = in_array( $child_icon, $valid_icons, true ) ? $child_icon : '';
				if ( $child_label && $child_url ) {
					$children[] = array( 'label' => $child_label, 'url' => $child_url, 'icon' => $child_icon, 'new_tab' => empty( $child['new_tab'] ) ? 0 : 1 );
				}
			}
			if ( $label && ( 'text' === $type || $url || $children ) ) {
				$output[] = array( 'label' => $label, 'url' => $url, 'icon' => $icon, 'type' => $type, 'new_tab' => empty( $item['new_tab'] ) ? 0 : 1, 'children' => $children );
			}
		}
		return $output;
	}

	/** Sanitize footer sections and their configurable content blocks. */
	private static function sanitize_footer_columns( $columns ) {
		$output = array();
		foreach ( array_slice( is_array( $columns ) ? $columns : array(), 0, 8 ) as $column ) {
			if ( ! is_array( $column ) ) {
				continue;
			}
			$heading = sanitize_text_field( isset( $column['heading'] ) && is_scalar( $column['heading'] ) ? (string) $column['heading'] : '' );
			$description = sanitize_text_field( isset( $column['description'] ) && is_scalar( $column['description'] ) ? (string) $column['description'] : '' );
			$raw_items = isset( $column['items'] ) && is_array( $column['items'] ) ? $column['items'] : array();
			if ( ! array_key_exists( 'items', $column ) && ! empty( $column['links'] ) && is_array( $column['links'] ) ) {
				foreach ( $column['links'] as $legacy_link ) {
					if ( is_array( $legacy_link ) ) {
						$legacy_link['type'] = 'link';
						$legacy_link['text'] = '';
						$legacy_link['image_url'] = '';
						$raw_items[] = $legacy_link;
					}
				}
			}
			$items = self::sanitize_footer_items( $raw_items, 24 );
			if ( $heading || $description || $items ) {
				$output[] = array( 'heading' => $heading, 'description' => $description, 'items' => $items );
			}
		}
		return $output;
	}

	/** Sanitize text, link, logo, divider, and social content blocks. */
	private static function sanitize_footer_items( $items, $limit = 24 ) {
		$valid_icons = self::manual_icon_names();
		$output = array();
		foreach ( array_slice( is_array( $items ) ? $items : array(), 0, absint( $limit ) ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['type'] ) && is_scalar( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'link';
			if ( ! in_array( $type, array( 'link', 'text', 'logo', 'divider', 'social' ), true ) ) {
				$type = 'link';
			}
			$label = sanitize_text_field( isset( $item['label'] ) && is_scalar( $item['label'] ) ? (string) $item['label'] : '' );
			$text = sanitize_textarea_field( isset( $item['text'] ) && is_scalar( $item['text'] ) ? (string) $item['text'] : '' );
			$url = esc_url_raw( trim( isset( $item['url'] ) && is_scalar( $item['url'] ) ? (string) $item['url'] : '' ) );
			$image_url = esc_url_raw( trim( isset( $item['image_url'] ) && is_scalar( $item['image_url'] ) ? (string) $item['image_url'] : '' ) );
			$alt = sanitize_text_field( isset( $item['alt'] ) && is_scalar( $item['alt'] ) ? (string) $item['alt'] : '' );
			$icon = isset( $item['icon'] ) && is_scalar( $item['icon'] ) ? sanitize_text_field( (string) $item['icon'] ) : '';
			$icon = in_array( $icon, $valid_icons, true ) ? $icon : '';
			if ( 'link' === $type && ( ! $label || ! $url ) ) {
				continue;
			}
			if ( 'text' === $type && '' === $text ) {
				continue;
			}
			if ( 'logo' === $type && '' === $image_url ) {
				continue;
			}
			$output[] = array(
				'type'      => $type,
				'label'     => $label,
				'text'      => $text,
				'url'       => $url,
				'image_url' => $image_url,
				'alt'       => $alt,
				'icon'      => $icon,
				'new_tab'   => empty( $item['new_tab'] ) ? 0 : 1,
			);
		}
		return $output;
	}

	/** Legal links use the same validated link fields as footer link blocks. */
	private static function sanitize_footer_legal_links( $links ) {
		return array_values( array_filter( self::sanitize_footer_items( $links, 20 ), static function ( $item ) { return 'link' === $item['type']; } ) );
	}

	/** Keep custom footer placement paths local, normalized, and glob-free except for a trailing wildcard. */
	private static function sanitize_footer_path( $path ) {
		$path = is_scalar( $path ) ? trim( (string) $path ) : '';
		$path = preg_replace( '/[?#].*$/', '', $path );
		$path = preg_replace( '/[^a-zA-Z0-9_\-.*\/]/', '', $path );
		$path = '/' . trim( $path, '/' );
		if ( false !== strpos( substr( $path, 0, -1 ), '*' ) ) {
			return '';
		}
		return '/' === $path ? '' : $path;
	}

	/** Sanitize social profile links for the footer. */
	private static function sanitize_footer_social_links( $links ) {
		$valid_icons = self::manual_icon_names();
		$output = array();
		foreach ( array_slice( is_array( $links ) ? $links : array(), 0, 8 ) as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$label = sanitize_text_field( isset( $link['label'] ) && is_scalar( $link['label'] ) ? (string) $link['label'] : '' );
			$url = esc_url_raw( trim( isset( $link['url'] ) && is_scalar( $link['url'] ) ? (string) $link['url'] : '' ) );
			$icon = isset( $link['icon'] ) && is_scalar( $link['icon'] ) ? sanitize_text_field( (string) $link['icon'] ) : '';
			$icon = in_array( $icon, $valid_icons, true ) ? $icon : '';
			if ( $label && $url ) {
				$output[] = array( 'label' => $label, 'url' => $url, 'icon' => $icon, 'new_tab' => empty( $link['new_tab'] ) ? 0 : 1 );
			}
		}
		return $output;
	}

	/** Available curated Hugeicons selected in the Studio editor. */
	private static function manual_icon_names() {
		$icons = array( 'Home01Icon', 'Menu01Icon', 'LayoutBottomIcon', 'Image01Icon', 'TextFontIcon', 'Briefcase01Icon', 'Compass01Icon', 'ArrowDown01Icon', 'ArrowRight01Icon', 'ArrowUpRight01Icon', 'Cancel01Icon', 'CodeIcon', 'GraduationCapIcon', 'BarChartIcon', 'File01Icon', 'PaintbrushIcon', 'NetworkIcon', 'Money01Icon', 'HealthIcon', 'Rocket01Icon', 'Award01Icon', 'DiscountTag01Icon', 'CalendarDaysIcon', 'Link01Icon', 'Linkedin01Icon', 'NewTwitterIcon', 'Share08Icon', 'UserRoundIcon', 'WhatsappIcon', 'Bookmark01Icon' );
		return apply_filters( 'dixcoverhub/custom-ui/navbar-icons', $icons );
	}

	/** Enqueue the custom studio interface on its admin page only. */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'appearance_page_dixcoverhub-custom-ui' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'dixcoverhub-custom-ui-admin', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/admin-studio.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
		$dependencies = array();
		if ( isset( $_GET['section'] ) && in_array( sanitize_key( wp_unslash( $_GET['section'] ) ), array( 'logo', 'footer' ), true ) ) {
			wp_enqueue_media();
			$dependencies = array( 'media-views' );
		}
		wp_enqueue_script( 'dixcoverhub-custom-ui-admin', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/admin-studio.js', $dependencies, DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		if ( isset( $_GET['section'] ) && 'popups' === sanitize_key( wp_unslash( $_GET['section'] ) ) ) {
			wp_enqueue_style( 'dixcoverhub-custom-ui-popups-admin', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/popups-admin.css', array( 'dixcoverhub-custom-ui-admin' ), DIXCOVERHUB_CUSTOM_UI_VERSION );
		}
	}

	/** Render the custom sectioned DixcoverHub Studio interface. */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sections = array(
			'overview' => __( 'Overview', 'dixcoverhub-custom-ui' ),
			'home'     => __( 'Home', 'dixcoverhub-custom-ui' ),
			'navbar'   => __( 'Navbar', 'dixcoverhub-custom-ui' ),
			'single-post' => __( 'Single Post', 'dixcoverhub-custom-ui' ),
			'archive'     => __( 'Archive & Filters', 'dixcoverhub-custom-ui' ),
			'deadlines'   => __( 'Deadlines', 'dixcoverhub-custom-ui' ),
			'footer'   => __( 'Footer', 'dixcoverhub-custom-ui' ),
			'logo'     => __( 'Logo', 'dixcoverhub-custom-ui' ),
			'fonts'    => __( 'Fonts', 'dixcoverhub-custom-ui' ),
			'popups'   => __( 'Popups', 'dixcoverhub-custom-ui' ),
		);
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview';
		if ( ! isset( $sections[ $section ] ) ) {
			$section = 'overview';
		}
		?>
		<div class="wrap dh-ui-studio">
			<header class="dh-ui-studio-header">
				<div><p class="dh-ui-eyebrow"><?php esc_html_e( 'DIXCOVERHUB DESIGN SYSTEM', 'dixcoverhub-custom-ui' ); ?></p><h1><?php esc_html_e( 'Studio', 'dixcoverhub-custom-ui' ); ?></h1><p><?php esc_html_e( 'Shape the visual parts of your site in one place.', 'dixcoverhub-custom-ui' ); ?></p></div>
				<a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview site', 'dixcoverhub-custom-ui' ); ?><span class="dh-ui-preview-icon" aria-hidden="true"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-ui-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from bundled, allow-listed Hugeicons data. ?></span></a>
			</header>
			<div class="dh-ui-studio-layout">
				<nav class="dh-ui-sidebar" aria-label="<?php esc_attr_e( 'DixcoverHub Studio sections', 'dixcoverhub-custom-ui' ); ?>">
					<p class="dh-ui-sidebar-label"><?php esc_html_e( 'WORKSPACES', 'dixcoverhub-custom-ui' ); ?></p>
					<?php foreach ( $sections as $slug => $label ) : ?>
						<a class="dh-ui-sidebar-link <?php echo $section === $slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::studio_url( $slug ) ); ?>" <?php echo $section === $slug ? 'aria-current="page"' : ''; ?>>
							<span class="dh-ui-sidebar-icon" aria-hidden="true"><?php echo self::section_icon( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from bundled, allow-listed Hugeicons data. ?></span><span><?php echo esc_html( $label ); ?></span>
						</a>
					<?php endforeach; ?>
					<div class="dh-ui-sidebar-footer"><span class="dh-ui-status-dot"></span><?php esc_html_e( 'Local preview workspace', 'dixcoverhub-custom-ui' ); ?></div>
				</nav>
				<main class="dh-ui-studio-main">
					<?php
					if ( 'home' === $section ) {
						self::render_home_workspace();
					} elseif ( 'navbar' === $section ) {
						self::render_navbar_workspace();
					} elseif ( 'single-post' === $section ) {
						self::render_single_post_workspace();
					} elseif ( 'archive' === $section ) {
						self::render_archive_workspace();
					} elseif ( 'deadlines' === $section ) {
						self::render_deadlines_workspace();
					} elseif ( 'footer' === $section ) {
						self::render_footer_workspace();
					} elseif ( 'logo' === $section ) {
						self::render_logo_workspace();
					} elseif ( 'fonts' === $section ) {
						DixcoverHub_Custom_UI_Fonts::render_page();
					} elseif ( 'popups' === $section ) {
						DixcoverHub_Custom_UI_Popups::render_page();
					} elseif ( 'overview' === $section ) {
						self::render_overview( $sections );
					}
					?>
				</main>
			</div>
		</div>
		<?php
	}

	/** URL for a Studio workspace. */
	private static function studio_url( $section ) {
		return add_query_arg( array( 'page' => 'dixcoverhub-custom-ui', 'section' => $section ), admin_url( 'themes.php' ) );
	}

	/** Render the selected bundled Hugeicons icon for a Studio section. */
	private static function section_icon( $section ) {
		$icons = array( 'overview' => 'Home01Icon', 'home' => 'Home01Icon', 'navbar' => 'Menu01Icon', 'single-post' => 'File01Icon', 'archive' => 'Compass01Icon', 'deadlines' => 'File01Icon', 'footer' => 'LayoutBottomIcon', 'logo' => 'Image01Icon', 'fonts' => 'TextFontIcon', 'popups' => 'DiscountTag01Icon' );
		return DixcoverHub_Custom_UI_Icons::svg( isset( $icons[ $section ] ) ? $icons[ $section ] : 'Home01Icon', 'dh-ui-icon' );
	}

	/** Render the Studio landing page. */
	private static function render_overview( $sections ) {
		$options = self::options();
		$states  = array(
			'home'        => ! empty( $options['home_enabled'] ),
			'navbar'      => ! empty( $options['enabled'] ),
			'single-post' => ! empty( $options['single_post_enabled'] ),
			'archive'     => ! empty( $options['archive_enabled'] ),
			'deadlines'   => ! empty( $options['deadline_page_enabled'] ),
			'footer'      => ! empty( $options['footer_enabled'] ),
			'logo'        => ! empty( $options['logo_enabled'] ),
			'fonts'       => ! empty( $options['fonts_enabled'] ),
			'popups'      => ! empty( $options['popup_enabled'] ),
		);
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'YOUR DESIGN SYSTEM', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'What are we shaping today?', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Choose a workspace to tune that part of DixcoverHub.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
		<div class="dh-ui-workspace-grid">
			<?php foreach ( array( 'home', 'navbar', 'single-post', 'archive', 'deadlines', 'footer', 'logo', 'fonts', 'popups' ) as $slug ) : ?>
				<a class="dh-ui-workspace-card" href="<?php echo esc_url( self::studio_url( $slug ) ); ?>"><span class="dh-ui-workspace-icon"><?php echo self::section_icon( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from bundled, allow-listed Hugeicons data. ?></span><span class="dh-ui-workspace-copy"><strong><?php echo esc_html( $sections[ $slug ] ); ?></strong><small><?php echo esc_html( self::workspace_description( $slug ) ); ?></small></span><span class="dh-ui-workspace-status <?php echo ! empty( $states[ $slug ] ) ? 'is-active' : 'is-inactive'; ?>"><?php echo ! empty( $states[ $slug ] ) ? esc_html__( 'Active', 'dixcoverhub-custom-ui' ) : esc_html__( 'Off', 'dixcoverhub-custom-ui' ); ?></span><span class="dh-ui-workspace-arrow" aria-hidden="true"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-ui-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from bundled, allow-listed Hugeicons data. ?></span></a>
			<?php endforeach; ?>
		</div>
		<div class="dh-ui-card dh-ui-studio-note"><p class="dh-ui-eyebrow"><?php esc_html_e( 'BUILT FOR DIXCOVERHUB', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Your site’s controls, in their own space.', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Navbar, footer, logo, and typography settings live here and are stored in WordPress. Changes can be previewed locally before you deploy.', 'dixcoverhub-custom-ui' ); ?></p></div>
		<?php
	}

	/** Short summaries for the workspace cards. */
	private static function workspace_description( $section ) {
		$copy = array(
			'home' => __( 'Homepage hero, category links, and opportunity deck.', 'dixcoverhub-custom-ui' ),
			'navbar' => __( 'Links, categories, layout, and appearance.', 'dixcoverhub-custom-ui' ),
			'single-post' => __( 'Article layout, facts, and related content.', 'dixcoverhub-custom-ui' ),
			'archive' => __( 'Opportunity discovery, search, filters, and results.', 'dixcoverhub-custom-ui' ),
			'deadlines' => __( 'Date windows, custom ranges, and closing opportunities.', 'dixcoverhub-custom-ui' ),
			'footer' => __( 'Build the site footer and its link groups.', 'dixcoverhub-custom-ui' ),
			'logo'   => __( 'Choose the logo and tune its desktop and mobile sizing.', 'dixcoverhub-custom-ui' ),
			'fonts'  => __( 'Upload fonts and assign them to text roles.', 'dixcoverhub-custom-ui' ),
			'popups' => __( 'Create targeted popup experiences with preview, schedules, and metrics.', 'dixcoverhub-custom-ui' ),
		);
		return isset( $copy[ $section ] ) ? $copy[ $section ] : '';
	}

	/** Render the opt-in homepage workspace. */
	private static function render_home_workspace() {
		$options = self::options();
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'HOMEPAGE EXPERIENCE', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Home', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Build the reference homepage from your published WordPress opportunities.', 'dixcoverhub-custom-ui' ); ?></p></div><a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview home', 'dixcoverhub-custom-ui' ); ?></a></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'PAGE TEMPLATE', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Homepage hero and opportunity deck', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'This template takes over only the site front page. Its opportunity archive section appears only when Archive & Filters is active.', 'dixcoverhub-custom-ui' ); ?></p></div><span class="dh-ui-workspace-status <?php echo ! empty( $options['home_enabled'] ) ? 'is-active' : 'is-inactive'; ?>"><?php echo ! empty( $options['home_enabled'] ) ? esc_html__( 'Active', 'dixcoverhub-custom-ui' ) : esc_html__( 'Off', 'dixcoverhub-custom-ui' ); ?></span></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'home_enabled', __( 'Activate the DixcoverHub homepage', 'dixcoverhub-custom-ui' ), __( 'Your current front page stays in place until you turn this on.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'home_title_before', __( 'Headline before highlight', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'home_title_highlight', __( 'Highlighted headline text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'home_title_after', __( 'Headline after highlight', 'dixcoverhub-custom-ui' ) ); ?>
				<tr><th scope="row"><label for="dh-navbar-home_description"><?php esc_html_e( 'Introductory text', 'dixcoverhub-custom-ui' ); ?></label></th><td><textarea class="large-text" id="dh-navbar-home_description" rows="3" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[home_description]"><?php echo esc_textarea( $options['home_description'] ); ?></textarea></td></tr>
				<?php self::text_row( $options, 'home_cta_label', __( 'Explore button label', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'home_cta_url', __( 'Explore button URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
				<?php self::checkbox_row( $options, 'home_category_strip', __( 'Show animated category links', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'home_deck_count', __( 'Opportunity cards in deck', 'dixcoverhub-custom-ui' ), 1, 20 ); ?>
				<?php self::color_row( $options, 'home_background_color', __( 'Hero background colour', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'home_highlight_color', __( 'Headline and button accent', 'dixcoverhub-custom-ui' ) ); ?>
			</tbody></table></section>
			<div class="dh-ui-form-actions"><?php submit_button( __( 'Save homepage settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'Cards use published WordPress posts. The archive and its filters remain controlled by their own switch.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</form>
		<?php
	}

	/** Render the logo and wordmark workspace. */
	private static function render_logo_workspace() {
		$options = self::options();
		$preview = $options['logo_url'];
		if ( ! $preview ) {
			$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
			$preview = $custom_logo_id ? (string) wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
		}
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'BRAND IDENTITY', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Logo', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Choose the mark used in the custom navbar, set its size, and decide how it pairs with your site name.', 'dixcoverhub-custom-ui' ); ?></p></div><a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview site', 'dixcoverhub-custom-ui' ); ?></a></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'SITE MARK', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Logo and wordmark', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'This logo is shared with the navbar. The footer reuses it unless you set a separate footer logo.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<table class="form-table" role="presentation"><tbody>
			<?php self::checkbox_row( $options, 'logo_enabled', __( 'Activate Studio logo settings', 'dixcoverhub-custom-ui' ), __( 'When off, the custom navbar uses the WordPress theme logo and wordmark settings.', 'dixcoverhub-custom-ui' ) ); ?>
			<tr><th scope="row"><label for="dh-navbar-logo_url"><?php esc_html_e( 'Logo image', 'dixcoverhub-custom-ui' ); ?></label></th><td><input class="regular-text" id="dh-navbar-logo_url" type="text" inputmode="url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[logo_url]" value="<?php echo esc_attr( $options['logo_url'] ); ?>" /><p class="description"><?php esc_html_e( 'Upload or select a logo from the Media Library. Leave this empty to use the logo set in WordPress Site Identity.', 'dixcoverhub-custom-ui' ); ?></p><div class="dh-ui-logo-actions"><button type="button" class="button" data-dh-logo-select="header"><?php esc_html_e( 'Upload or choose logo', 'dixcoverhub-custom-ui' ); ?></button><button type="button" class="button-link-delete" data-dh-logo-clear="header"><?php esc_html_e( 'Use site logo', 'dixcoverhub-custom-ui' ); ?></button></div><div class="dh-ui-logo-preview" data-dh-logo-preview="header"><?php if ( $preview ) : ?><img src="<?php echo esc_url( $preview ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /><?php else : ?><span><?php esc_html_e( 'No site logo is set. Add one here or set it in Site Identity. The navbar will show your site name without an icon.', 'dixcoverhub-custom-ui' ); ?></span><?php endif; ?></div></td></tr>
				<?php self::checkbox_row( $options, 'show_wordmark', __( 'Show the site name beside the logo', 'dixcoverhub-custom-ui' ), __( 'Useful when the image mark does not contain your brand name.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'logo_max_width', __( 'Desktop logo maximum width (px)', 'dixcoverhub-custom-ui' ), 48, 300 ); ?>
				<?php self::number_row( $options, 'logo_max_height', __( 'Desktop logo maximum height (px)', 'dixcoverhub-custom-ui' ), 20, 80 ); ?>
				<?php self::number_row( $options, 'logo_mobile_max_width', __( 'Mobile logo maximum width (px)', 'dixcoverhub-custom-ui' ), 40, 260 ); ?>
				<?php self::number_row( $options, 'logo_mobile_max_height', __( 'Mobile logo maximum height (px)', 'dixcoverhub-custom-ui' ), 18, 72 ); ?>
			</tbody></table></section>
			<div class="dh-ui-form-actions"><?php submit_button( __( 'Save logo settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'The selected Media Library image remains stored in WordPress. This plugin saves its URL and sizing preferences.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</form>
		<?php
	}

	/** Render the navbar editor inside its Studio workspace. */
	private static function render_navbar_workspace() {

		$options    = self::options();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$menus      = wp_get_nav_menus();
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'NAVIGATION DESIGN', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Navbar', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Control how people move around DixcoverHub.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
				<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
				<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'QUICK NAVIGATION', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Mobile dock and community pill', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'A compact mobile dock and a matching floating desktop pill. This is separate from the standard top navbar.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
				<table class="form-table" role="presentation"><tbody>
					<?php self::checkbox_row( $options, 'bottom_nav_enabled', __( 'Activate mobile dock and desktop community pill', 'dixcoverhub-custom-ui' ), __( 'When off, neither quick-navigation element appears. Your standard top navbar setting stays independent.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::color_row( $options, 'bottom_nav_accent_color', __( 'Dock and community accent colour', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_home_label', __( 'Home label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_community_label', __( 'Community button label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_community_url', __( 'Community destination URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
					<?php self::text_row( $options, 'bottom_nav_more_label', __( 'More button label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_panel_title', __( 'More panel title', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_jobs_label', __( 'Jobs link label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_jobs_description', __( 'Jobs link description', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_jobs_url', __( 'Jobs destination URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
					<?php self::text_row( $options, 'bottom_nav_opportunities_label', __( 'Opportunities link label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_opportunities_description', __( 'Opportunities link description', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_opportunities_url', __( 'Opportunities destination URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
					<?php self::text_row( $options, 'bottom_nav_deadlines_label', __( 'Deadlines link label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_deadlines_description', __( 'Deadlines link description', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'bottom_nav_deadlines_url', __( 'Deadlines destination URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
				</tbody></table></section>
				<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'FOUNDATION', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Display and brand', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
				<table class="form-table" role="presentation"><tbody>
					<?php self::checkbox_row( $options, 'enabled', __( 'Activate custom navbar', 'dixcoverhub-custom-ui' ), __( 'When off, your current theme header stays in place.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::checkbox_row( $options, 'automatic_display', __( 'Show automatically at the top of the site', 'dixcoverhub-custom-ui' ), __( 'Turn this off to place [dixcoverhub_navbar] in a Shortcode block instead.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::checkbox_row( $options, 'replace_block_header', __( 'Replace the theme header', 'dixcoverhub-custom-ui' ), __( 'Supported for WordPress block themes and Astra. Other classic themes may need a theme-specific integration.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::checkbox_row( $options, 'sticky', __( 'Keep the navbar visible while scrolling', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::checkbox_row( $options, 'transparent', __( 'Use a transparent header background', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::color_row( $options, 'primary_color', __( 'Accent colour', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::color_row( $options, 'navbar_surface_color', __( 'Navbar and menu background', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::color_row( $options, 'navbar_text_color', __( 'Brand and active text colour', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::color_row( $options, 'navbar_link_color', __( 'Navigation link colour', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::color_row( $options, 'navbar_border_color', __( 'Divider and border colour', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::number_row( $options, 'content_width', __( 'Content width (px)', 'dixcoverhub-custom-ui' ), 960, 1440 ); ?>
				</tbody></table></section>

				<section class="dh-ui-card dh-ui-manual-nav-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'BUILD YOUR OWN MENU', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Manual navigation items', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Add and reorder direct links, dropdowns, or text labels. Items can use Hugeicons, and dropdowns can contain their own links.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
				<table class="form-table" role="presentation"><tbody><tr><th scope="row"><?php esc_html_e( 'Menu source', 'dixcoverhub-custom-ui' ); ?></th><td><input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[manual_nav_enabled]" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[manual_nav_enabled]" value="1" <?php checked( ! empty( $options['manual_nav_enabled'] ) ); ?> /> <?php esc_html_e( 'Use my manual items instead of the preset category and page links', 'dixcoverhub-custom-ui' ); ?></label><p class="description"><?php esc_html_e( 'Your custom items will appear on desktop and inside the mobile drawer. Login and action buttons stay in their separate controls below.', 'dixcoverhub-custom-ui' ); ?></p></td></tr></tbody></table>
				<div class="dh-ui-nav-repeater" data-dh-nav-repeater>
					<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[manual_nav_items_present]" value="1" />
					<div class="dh-ui-nav-items" data-dh-nav-items>
						<?php foreach ( (array) $options['manual_nav_items'] as $item_index => $item ) { self::render_manual_nav_item( $item, $item_index ); } ?>
					</div>
					<button class="button dh-ui-nav-add" type="button" data-dh-nav-add><?php esc_html_e( 'Add navigation item', 'dixcoverhub-custom-ui' ); ?></button>
					<template data-dh-nav-item-template><?php self::render_manual_nav_item( array(), '__ITEM__' ); ?></template>
				</div></section>

				<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'DISCOVERY MENUS', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Categories', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><label for="dh-navbar-taxonomy"><?php esc_html_e( 'Category taxonomy', 'dixcoverhub-custom-ui' ); ?></label></th>
						<td><select id="dh-navbar-taxonomy" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[taxonomy]">
							<?php foreach ( $taxonomies as $taxonomy ) : ?>
								<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $options['taxonomy'], $taxonomy->name ); ?>><?php echo esc_html( $taxonomy->labels->singular_name . ' (' . $taxonomy->name . ')' ); ?></option>
							<?php endforeach; ?>
						</select><p class="description"><?php esc_html_e( 'The menu reads category terms and their live WordPress counts from this taxonomy.', 'dixcoverhub-custom-ui' ); ?></p></td>
					</tr>
					<?php self::text_row( $options, 'jobs_parent_slug', __( 'Jobs parent term slug', 'dixcoverhub-custom-ui' ), 'text', __( 'Child terms appear in the Jobs menu. Default: jobs.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::number_row( $options, 'jobs_category_limit', __( 'Number of job categories', 'dixcoverhub-custom-ui' ), 1, 12 ); ?>
					<?php self::text_row( $options, 'opportunities_label', __( 'Opportunities menu label', 'dixcoverhub-custom-ui' ) ); ?>
					<tr>
						<th scope="row"><label for="dh-navbar-opportunity-slugs"><?php esc_html_e( 'Opportunity term slugs', 'dixcoverhub-custom-ui' ); ?></label></th>
						<td><textarea id="dh-navbar-opportunity-slugs" class="large-text" rows="3" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[opportunity_term_slugs]"><?php echo esc_textarea( $options['opportunity_term_slugs'] ); ?></textarea><p class="description"><?php esc_html_e( 'Comma-separated taxonomy slugs, in menu order. Missing terms use the legacy navbar label and show no count.', 'dixcoverhub-custom-ui' ); ?></p></td>
					</tr>
					<?php self::checkbox_row( $options, 'show_counts', __( 'Show category counts', 'dixcoverhub-custom-ui' ), __( 'Counts come from WordPress taxonomy terms; zero counts are hidden.', 'dixcoverhub-custom-ui' ) ); ?>
				</tbody></table></section>

				<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'SITE LINKS', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Pages and actions', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
				<table class="form-table" role="presentation"><tbody>
					<?php self::text_row( $options, 'deadlines_label', __( 'Deadlines label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'deadlines_url', __( 'Deadlines URL', 'dixcoverhub-custom-ui' ), 'url', __( 'Leave empty to use the page with the slug “deadlines”.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'about_label', __( 'About label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'about_url', __( 'About URL', 'dixcoverhub-custom-ui' ), 'url', __( 'Leave empty to use the page with the slug “about”.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'contact_label', __( 'Contact label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'contact_url', __( 'Contact URL', 'dixcoverhub-custom-ui' ), 'url', __( 'Leave empty to use the page with the slug “contact”.', 'dixcoverhub-custom-ui' ) ); ?>
					<tr>
						<th scope="row"><label for="dh-navbar-extra-menu"><?php esc_html_e( 'Additional WordPress menu', 'dixcoverhub-custom-ui' ); ?></label></th>
						<td><select id="dh-navbar-extra-menu" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[extra_menu_id]"><option value="0"><?php esc_html_e( 'None', 'dixcoverhub-custom-ui' ); ?></option><?php foreach ( $menus as $menu ) : ?><option value="<?php echo absint( $menu->term_id ); ?>" <?php selected( (int) $options['extra_menu_id'], (int) $menu->term_id ); ?>><?php echo esc_html( $menu->name ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'Optional links from an existing WordPress menu appear after the main links.', 'dixcoverhub-custom-ui' ); ?></p></td>
					</tr>
					<?php self::checkbox_row( $options, 'show_login', __( 'Show login button', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'login_label', __( 'Login button label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'login_url', __( 'Login URL', 'dixcoverhub-custom-ui' ), 'url', __( 'Leave empty to use the site’s “login” page or the standard WordPress login screen.', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'cta_label', __( 'Desktop action label', 'dixcoverhub-custom-ui' ) ); ?>
					<?php self::text_row( $options, 'cta_url', __( 'Action URL', 'dixcoverhub-custom-ui' ), 'url', __( 'Leave empty to use the “opportunities” page.', 'dixcoverhub-custom-ui' ) ); ?>
					<tr><th scope="row"><label for="dh-navbar-cta-icon"><?php esc_html_e( 'Action button icon', 'dixcoverhub-custom-ui' ); ?></label></th><td><select id="dh-navbar-cta-icon" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cta_icon]"><?php self::render_icon_options( $options['cta_icon'] ); ?></select></td></tr>
					<?php self::text_row( $options, 'mobile_cta_label', __( 'Mobile action label', 'dixcoverhub-custom-ui' ) ); ?>
				</tbody></table></section>

				<div class="dh-ui-form-actions"><?php submit_button( __( 'Save navbar settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'Your settings are saved in this WordPress site and can be previewed locally.', 'dixcoverhub-custom-ui' ); ?></p></div>
			</form>
		<div class="dh-ui-note dh-ui-shortcode-note"><?php esc_html_e( 'The shortcode [dixcoverhub_navbar] is available for themes that do not support automatic display.', 'dixcoverhub-custom-ui' ); ?></div>
		<?php
	}

	/** Render the customizable public footer editor. */
	private static function render_footer_workspace() {
		$options = self::options();
		$footer_logo_preview = $options['footer_logo_url'] ? $options['footer_logo_url'] : $options['logo_url'];
		if ( ! $footer_logo_preview ) {
			$footer_logo_id = (int) get_theme_mod( 'custom_logo' );
			$footer_logo_preview = $footer_logo_id ? (string) wp_get_attachment_image_url( $footer_logo_id, 'full' ) : '';
		}
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'SITE FOOTER', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Footer', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Configure the footer brand, placement, flexible content blocks, links, and appearance.', 'dixcoverhub-custom-ui' ); ?></p></div><a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview site', 'dixcoverhub-custom-ui' ); ?></a></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'DISPLAY AND BRAND', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Footer foundation', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'footer_enabled', __( 'Enable the custom footer', 'dixcoverhub-custom-ui' ), __( 'Your current theme footer stays in place until this feature is activated.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'footer_automatic', __( 'Show automatically on public pages', 'dixcoverhub-custom-ui' ), __( 'Turn off to place [dixcoverhub_footer] where you want it.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'footer_replace_theme', __( 'Replace the theme footer', 'dixcoverhub-custom-ui' ), __( 'Supported for Astra and block themes. Other themes can use the shortcode or may need a theme-specific footer hook.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'footer_show_brand', __( 'Show logo, site name, and description', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'footer_show_wordmark', __( 'Show the site name beside the logo', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_logo_url', __( 'Footer logo URL', 'dixcoverhub-custom-ui' ), 'url', __( 'Leave empty to reuse the navbar or WordPress logo.', 'dixcoverhub-custom-ui' ) ); ?>
				<tr><th scope="row"><?php esc_html_e( 'Footer logo image', 'dixcoverhub-custom-ui' ); ?></th><td><div class="dh-ui-logo-actions"><button type="button" class="button" data-dh-logo-select="footer"><?php esc_html_e( 'Choose from Media Library', 'dixcoverhub-custom-ui' ); ?></button><button type="button" class="button-link-delete" data-dh-logo-clear="footer"><?php esc_html_e( 'Clear footer override', 'dixcoverhub-custom-ui' ); ?></button></div><div class="dh-ui-logo-preview" data-dh-logo-preview="footer"><?php if ( $footer_logo_preview ) : ?><img src="<?php echo esc_url( $footer_logo_preview ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /><?php else : ?><span><?php esc_html_e( 'The footer will use the site title until a logo image is selected.', 'dixcoverhub-custom-ui' ); ?></span><?php endif; ?></div></td></tr>
				<?php self::text_row( $options, 'footer_logo_alt', __( 'Footer logo alt text', 'dixcoverhub-custom-ui' ), 'text', __( 'Used by screen readers; defaults to the site name.', 'dixcoverhub-custom-ui' ) ); ?>
				<tr><th scope="row"><label for="dh-navbar-footer_description"><?php esc_html_e( 'Brand description', 'dixcoverhub-custom-ui' ); ?></label></th><td><textarea class="large-text" rows="3" id="dh-navbar-footer_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_description]"><?php echo esc_textarea( $options['footer_description'] ); ?></textarea></td></tr>
				<?php self::checkbox_row( $options, 'footer_cta_enabled', __( 'Show a footer action button', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_cta_label', __( 'Action button text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_cta_url', __( 'Action button URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
				<?php self::text_row( $options, 'footer_copyright', __( 'Copyright line', 'dixcoverhub-custom-ui' ), 'text', __( 'Use %year% and %site_name% as automatic values.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_secondary_text', __( 'Secondary footer text', 'dixcoverhub-custom-ui' ) ); ?>
			</tbody></table></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'FEATURE CALLOUT', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Footer banner and actions', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Build the prominent callout area above the footer columns. This banner is independently optional.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'footer_banner_enabled', __( 'Show the footer callout banner', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_banner_badge', __( 'Small badge text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_banner_heading', __( 'Banner headline', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_banner_primary_label', __( 'Primary action label', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_banner_primary_url', __( 'Primary action URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
				<?php self::checkbox_row( $options, 'footer_banner_primary_new_tab', __( 'Open primary action in a new tab', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_banner_secondary_label', __( 'Secondary action label', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'footer_banner_secondary_url', __( 'Secondary action URL', 'dixcoverhub-custom-ui' ), 'url' ); ?>
				<?php self::checkbox_row( $options, 'footer_banner_secondary_new_tab', __( 'Open secondary action in a new tab', 'dixcoverhub-custom-ui' ) ); ?>
			</tbody></table></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'PAGE PLACEMENT', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Choose where this footer appears', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'The default applies site-wide. Choose one or more page groups, or add exact paths and prefix patterns such as /about/*.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<div class="dh-ui-footer-placement"><input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_placements_present]" value="1" /><?php foreach ( array( 'global' => __( 'Entire site', 'dixcoverhub-custom-ui' ), 'home' => __( 'Homepage', 'dixcoverhub-custom-ui' ), 'opportunities' => __( 'Opportunities archive', 'dixcoverhub-custom-ui' ), 'posts' => __( 'Posts', 'dixcoverhub-custom-ui' ), 'pages' => __( 'Pages', 'dixcoverhub-custom-ui' ), 'path' => __( 'Custom paths below', 'dixcoverhub-custom-ui' ) ) as $placement_key => $placement_label ) : ?><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_placements][]" value="<?php echo esc_attr( $placement_key ); ?>" <?php checked( in_array( $placement_key, (array) $options['footer_placements'], true ) ); ?> /> <?php echo esc_html( $placement_label ); ?></label><?php endforeach; ?></div>
			<label class="dh-ui-footer-paths"><span><?php esc_html_e( 'Custom paths (one per line)', 'dixcoverhub-custom-ui' ); ?></span><textarea class="large-text code" rows="4" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_path_patterns]" placeholder="/about/&#10;/guides/*"><?php echo esc_textarea( $options['footer_path_patterns'] ); ?></textarea><small><?php esc_html_e( 'Paths are local to this site. A trailing /* matches that path and its child paths.', 'dixcoverhub-custom-ui' ); ?></small></label></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'CONTENT SECTIONS', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Footer content builder', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Build up to eight columns. Add links, text, logos, social links, and dividers, then reorder each item.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<div class="dh-ui-footer-repeater" data-dh-footer-columns>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_columns_present]" value="1" />
				<div data-dh-footer-column-list><?php foreach ( (array) $options['footer_columns'] as $column_index => $column ) { self::render_footer_column( $column, $column_index ); } ?></div>
				<button class="button dh-ui-nav-add" type="button" data-dh-footer-add-column><?php esc_html_e( 'Add footer section', 'dixcoverhub-custom-ui' ); ?></button>
				<template data-dh-footer-column-template><?php self::render_footer_column( array(), '__COLUMN__' ); ?></template>
			</div></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'SOCIAL LINKS', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Community and profiles', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
			<table class="form-table" role="presentation"><tbody><?php self::checkbox_row( $options, 'footer_show_social_links', __( 'Show social and community links', 'dixcoverhub-custom-ui' ) ); ?></tbody></table>
			<div class="dh-ui-footer-repeater" data-dh-footer-social>
				<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_social_links_present]" value="1" />
				<div data-dh-footer-social-list><?php foreach ( (array) $options['footer_social_links'] as $social_index => $social ) { self::render_footer_social( $social, $social_index ); } ?></div>
				<button class="button dh-ui-nav-add" type="button" data-dh-footer-add-social><?php esc_html_e( 'Add social link', 'dixcoverhub-custom-ui' ); ?></button>
				<template data-dh-footer-social-template><?php self::render_footer_social( array(), '__SOCIAL__' ); ?></template>
			</div></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'LEGAL LINKS', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Privacy and policy links', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'These links appear alongside your copyright line.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<div class="dh-ui-footer-repeater" data-dh-footer-legal><input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_legal_links_present]" value="1" /><div data-dh-footer-legal-list><?php foreach ( (array) $options['footer_legal_links'] as $legal_index => $legal_link ) { self::render_footer_legal_link( $legal_link, $legal_index ); } ?></div><button class="button dh-ui-nav-add" type="button" data-dh-footer-add-legal><?php esc_html_e( 'Add legal link', 'dixcoverhub-custom-ui' ); ?></button><template data-dh-footer-legal-template><?php self::render_footer_legal_link( array(), '__LEGAL__' ); ?></template></div></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'APPEARANCE', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Footer colours and width', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::color_row( $options, 'footer_background_color', __( 'Background colour', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_text_color', __( 'Heading and brand text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_muted_color', __( 'Description and secondary text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_link_color', __( 'Link and accent colour', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_border_color', __( 'Divider colour', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'footer_content_width', __( 'Content width (px)', 'dixcoverhub-custom-ui' ), 960, 1440 ); ?>
				<tr><th scope="row"><strong><?php esc_html_e( 'Callout banner', 'dixcoverhub-custom-ui' ); ?></strong></th><td></td></tr>
				<?php self::color_row( $options, 'footer_banner_start_color', __( 'Banner gradient start', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_end_color', __( 'Banner gradient end', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_text_color', __( 'Banner headline and action text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_badge_bg_color', __( 'Badge background', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_badge_text_color', __( 'Badge text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_button_bg_color', __( 'Primary button background', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_button_text_color', __( 'Primary button text', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::color_row( $options, 'footer_banner_secondary_border_color', __( 'Secondary button border', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'footer_banner_radius', __( 'Banner corner radius (px)', 'dixcoverhub-custom-ui' ), 0, 48 ); ?>
			</tbody></table></section>
			<div class="dh-ui-form-actions"><?php submit_button( __( 'Save footer settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'Your custom footer is rendered by the Custom UI plugin and can be previewed locally.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</form>
		<div class="dh-ui-note dh-ui-shortcode-note"><?php esc_html_e( 'Footer shortcode: ', 'dixcoverhub-custom-ui' ); ?><code>[dixcoverhub_footer]</code></div>
		<?php
	}


	/** Render a footer section and its editable content blocks. */
	private static function render_footer_column( $column, $index ) {
		$column = is_array( $column ) ? wp_parse_args( $column, array( 'heading' => '', 'description' => '', 'links' => array(), 'items' => array() ) ) : array( 'heading' => '', 'description' => '', 'links' => array(), 'items' => array() );
		if ( empty( $column['items'] ) && ! empty( $column['links'] ) && is_array( $column['links'] ) ) {
			foreach ( $column['links'] as $legacy_link ) {
				if ( is_array( $legacy_link ) ) {
					$column['items'][] = array_merge( $legacy_link, array( 'type' => 'link', 'text' => '', 'image_url' => '', 'alt' => '' ) );
				}
			}
		}
		$base = self::OPTION_KEY . '[footer_columns][' . $index . ']';
		?>
		<fieldset class="dh-ui-footer-column" data-dh-footer-column><legend><span data-dh-footer-column-number><?php echo is_numeric( $index ) ? absint( $index ) + 1 : ''; ?></span> <?php esc_html_e( 'Footer section', 'dixcoverhub-custom-ui' ); ?></legend><div class="dh-ui-nav-item-head"><div class="dh-ui-nav-item-actions"><button type="button" class="button" data-dh-footer-up aria-label="<?php esc_attr_e( 'Move section up', 'dixcoverhub-custom-ui' ); ?>">↑</button><button type="button" class="button" data-dh-footer-down aria-label="<?php esc_attr_e( 'Move section down', 'dixcoverhub-custom-ui' ); ?>">↓</button><button type="button" class="button-link-delete" data-dh-footer-remove-column><?php esc_html_e( 'Remove', 'dixcoverhub-custom-ui' ); ?></button></div></div>
		<div class="dh-ui-footer-column-fields"><label><span><?php esc_html_e( 'Section heading', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="80" name="<?php echo esc_attr( $base . '[heading]' ); ?>" value="<?php echo esc_attr( $column['heading'] ); ?>" placeholder="e.g. Resources" /></label><label><span><?php esc_html_e( 'Short description (optional)', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="160" name="<?php echo esc_attr( $base . '[description]' ); ?>" value="<?php echo esc_attr( $column['description'] ); ?>" placeholder="A short note under the heading" /></label></div>
		<div class="dh-ui-footer-links"><div class="dh-ui-nav-children-heading"><strong><?php esc_html_e( 'Content blocks', 'dixcoverhub-custom-ui' ); ?></strong><button type="button" class="button" data-dh-footer-add-item><?php esc_html_e( 'Add content block', 'dixcoverhub-custom-ui' ); ?></button></div><div data-dh-footer-item-list><?php foreach ( (array) $column['items'] as $item_index => $item ) { self::render_footer_item( $item, $index, $item_index ); } ?></div><template data-dh-footer-item-template><?php self::render_footer_item( array(), $index, '__ITEM__' ); ?></template></div></fieldset>
		<?php
	}

	/** Render one configurable footer block. */
	private static function render_footer_item( $item, $column_index, $item_index ) {
		$item = is_array( $item ) ? wp_parse_args( $item, array( 'type' => 'link', 'label' => '', 'url' => '', 'icon' => '', 'text' => '', 'image_url' => '', 'alt' => '', 'new_tab' => 0 ) ) : array( 'type' => 'link', 'label' => '', 'url' => '', 'icon' => '', 'text' => '', 'image_url' => '', 'alt' => '', 'new_tab' => 0 );
		$base = self::OPTION_KEY . '[footer_columns][' . $column_index . '][items][' . $item_index . ']';
		?>
		<fieldset class="dh-ui-footer-item" data-dh-footer-item><div class="dh-ui-footer-item-head"><label><span><?php esc_html_e( 'Block type', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( $base . '[type]' ); ?>" data-dh-footer-item-type><option value="link" <?php selected( $item['type'], 'link' ); ?>><?php esc_html_e( 'Link', 'dixcoverhub-custom-ui' ); ?></option><option value="text" <?php selected( $item['type'], 'text' ); ?>><?php esc_html_e( 'Text', 'dixcoverhub-custom-ui' ); ?></option><option value="logo" <?php selected( $item['type'], 'logo' ); ?>><?php esc_html_e( 'Logo / image', 'dixcoverhub-custom-ui' ); ?></option><option value="social" <?php selected( $item['type'], 'social' ); ?>><?php esc_html_e( 'Social link', 'dixcoverhub-custom-ui' ); ?></option><option value="divider" <?php selected( $item['type'], 'divider' ); ?>><?php esc_html_e( 'Divider', 'dixcoverhub-custom-ui' ); ?></option></select></label><div class="dh-ui-repeater-actions"><button type="button" class="button" data-dh-footer-item-up aria-label="<?php esc_attr_e( 'Move block up', 'dixcoverhub-custom-ui' ); ?>">↑</button><button type="button" class="button" data-dh-footer-item-down aria-label="<?php esc_attr_e( 'Move block down', 'dixcoverhub-custom-ui' ); ?>">↓</button><button type="button" class="button-link-delete" data-dh-footer-remove-item><?php esc_html_e( 'Remove', 'dixcoverhub-custom-ui' ); ?></button></div></div>
		<div class="dh-ui-footer-item-fields" data-dh-footer-link-fields><label><span><?php esc_html_e( 'Label', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="80" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $item['label'] ); ?>" placeholder="Link or social label" /></label><label><span><?php esc_html_e( 'URL', 'dixcoverhub-custom-ui' ); ?></span><input type="text" inputmode="url" name="<?php echo esc_attr( $base . '[url]' ); ?>" value="<?php echo esc_attr( $item['url'] ); ?>" placeholder="/page/ or https://" /></label><label><span><?php esc_html_e( 'Icon', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( $base . '[icon]' ); ?>"><?php self::render_icon_options( $item['icon'] ); ?></select></label></div>
		<div class="dh-ui-footer-item-fields" data-dh-footer-text-fields><label class="dh-ui-footer-item-wide"><span><?php esc_html_e( 'Text content', 'dixcoverhub-custom-ui' ); ?></span><textarea rows="3" name="<?php echo esc_attr( $base . '[text]' ); ?>"><?php echo esc_textarea( $item['text'] ); ?></textarea></label></div>
		<div class="dh-ui-footer-item-fields" data-dh-footer-logo-fields><label class="dh-ui-footer-item-wide"><span><?php esc_html_e( 'Image URL', 'dixcoverhub-custom-ui' ); ?></span><span class="dh-ui-footer-image-control"><input type="text" inputmode="url" data-dh-footer-image-url name="<?php echo esc_attr( $base . '[image_url]' ); ?>" value="<?php echo esc_attr( $item['image_url'] ); ?>" placeholder="https:// or /wp-content/uploads/..." /><button class="button" type="button" data-dh-footer-image-select><?php esc_html_e( 'Choose image', 'dixcoverhub-custom-ui' ); ?></button></span><small><?php esc_html_e( 'Choose an uploaded image by copying its URL from the Media Library.', 'dixcoverhub-custom-ui' ); ?></small></label><label><span><?php esc_html_e( 'Image alt text', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="120" name="<?php echo esc_attr( $base . '[alt]' ); ?>" value="<?php echo esc_attr( $item['alt'] ); ?>" /></label><label><span><?php esc_html_e( 'Optional image link', 'dixcoverhub-custom-ui' ); ?></span><input type="text" inputmode="url" name="<?php echo esc_attr( $base . '[url]' ); ?>" value="<?php echo esc_attr( $item['url'] ); ?>" placeholder="/ or https://" /></label></div>
		<label class="dh-ui-nav-new-tab" data-dh-footer-new-tab><input type="hidden" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="0" /><input type="checkbox" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="1" <?php checked( ! empty( $item['new_tab'] ) ); ?> /> <?php esc_html_e( 'Open link in a new tab', 'dixcoverhub-custom-ui' ); ?></label></fieldset>
		<?php
	}

	/** Render one legal link. */
	private static function render_footer_legal_link( $link, $index ) {
		$link = is_array( $link ) ? wp_parse_args( $link, array( 'label' => '', 'url' => '', 'icon' => '', 'new_tab' => 0 ) ) : array( 'label' => '', 'url' => '', 'icon' => '', 'new_tab' => 0 );
		$base = self::OPTION_KEY . '[footer_legal_links][' . $index . ']';
		?>
		<div class="dh-ui-footer-legal-item" data-dh-footer-legal-item><label><span><?php esc_html_e( 'Label', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="80" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $link['label'] ); ?>" placeholder="Privacy" /></label><label><span><?php esc_html_e( 'URL', 'dixcoverhub-custom-ui' ); ?></span><input type="text" inputmode="url" name="<?php echo esc_attr( $base . '[url]' ); ?>" value="<?php echo esc_attr( $link['url'] ); ?>" placeholder="/privacy-policy/" /></label><div class="dh-ui-repeater-actions"><button type="button" class="button" data-dh-footer-legal-up aria-label="<?php esc_attr_e( 'Move legal link up', 'dixcoverhub-custom-ui' ); ?>">↑</button><button type="button" class="button" data-dh-footer-legal-down aria-label="<?php esc_attr_e( 'Move legal link down', 'dixcoverhub-custom-ui' ); ?>">↓</button><button type="button" class="button-link-delete" data-dh-footer-remove-legal><?php esc_html_e( 'Remove', 'dixcoverhub-custom-ui' ); ?></button></div></div>
		<?php
	}

	/** Render one social or community footer profile. */
	private static function render_footer_social( $social, $index ) {
		$social = is_array( $social ) ? wp_parse_args( $social, array( 'label' => '', 'url' => '', 'icon' => '', 'new_tab' => 1 ) ) : array( 'label' => '', 'url' => '', 'icon' => '', 'new_tab' => 1 );
		$base = self::OPTION_KEY . '[footer_social_links][' . $index . ']';
		?>
		<div class="dh-ui-footer-social" data-dh-footer-social-item><label><span><?php esc_html_e( 'Network / community label', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="80" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $social['label'] ); ?>" placeholder="e.g. LinkedIn" /></label><label><span><?php esc_html_e( 'Profile URL', 'dixcoverhub-custom-ui' ); ?></span><input type="text" inputmode="url" name="<?php echo esc_attr( $base . '[url]' ); ?>" value="<?php echo esc_attr( $social['url'] ); ?>" placeholder="https://" /></label><label><span><?php esc_html_e( 'Icon', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( $base . '[icon]' ); ?>"><?php self::render_icon_options( $social['icon'] ); ?></select></label><label data-dh-nav-link-field class="dh-ui-nav-new-tab"><input type="hidden" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="0" /><input type="checkbox" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="1" <?php checked( ! empty( $social['new_tab'] ) ); ?> /> <?php esc_html_e( 'New tab', 'dixcoverhub-custom-ui' ); ?></label><div class="dh-ui-repeater-actions"><button type="button" class="button" data-dh-footer-social-up aria-label="<?php esc_attr_e( 'Move social link up', 'dixcoverhub-custom-ui' ); ?>">↑</button><button type="button" class="button" data-dh-footer-social-down aria-label="<?php esc_attr_e( 'Move social link down', 'dixcoverhub-custom-ui' ); ?>">↓</button><button type="button" class="button-link-delete" data-dh-footer-remove-social><?php esc_html_e( 'Remove', 'dixcoverhub-custom-ui' ); ?></button></div></div>
		<?php
	}

	/** Render one manually configured navigation item. */
	private static function render_manual_nav_item( $item, $index ) {
		$item = is_array( $item ) ? wp_parse_args( $item, array( 'label' => '', 'url' => '', 'icon' => '', 'type' => 'link', 'new_tab' => 0, 'children' => array() ) ) : array( 'label' => '', 'url' => '', 'icon' => '', 'type' => 'link', 'new_tab' => 0, 'children' => array() );
		$base = self::OPTION_KEY . '[manual_nav_items][' . $index . ']';
		?>
		<fieldset class="dh-ui-nav-item" data-dh-nav-item>
			<legend><span data-dh-nav-item-number><?php echo is_numeric( $index ) ? absint( $index ) + 1 : ''; ?></span> <?php esc_html_e( 'Menu item', 'dixcoverhub-custom-ui' ); ?></legend>
			<div class="dh-ui-nav-item-head"><div class="dh-ui-nav-item-actions"><button type="button" class="button" data-dh-nav-up aria-label="<?php esc_attr_e( 'Move item up', 'dixcoverhub-custom-ui' ); ?>">↑</button><button type="button" class="button" data-dh-nav-down aria-label="<?php esc_attr_e( 'Move item down', 'dixcoverhub-custom-ui' ); ?>">↓</button><button type="button" class="button-link-delete" data-dh-nav-remove><?php esc_html_e( 'Remove', 'dixcoverhub-custom-ui' ); ?></button></div></div>
			<div class="dh-ui-nav-fields">
				<label><span><?php esc_html_e( 'Label', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="80" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $item['label'] ); ?>" placeholder="e.g. Jobs" /></label>
				<label data-dh-nav-link-field><span><?php esc_html_e( 'Destination URL', 'dixcoverhub-custom-ui' ); ?></span><input type="text" inputmode="url" name="<?php echo esc_attr( $base . '[url]' ); ?>" value="<?php echo esc_attr( $item['url'] ); ?>" placeholder="https:// or /page/" /></label>
				<label><span><?php esc_html_e( 'Item type', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( $base . '[type]' ); ?>" data-dh-nav-type><option value="link" <?php selected( $item['type'], 'link' ); ?>><?php esc_html_e( 'Direct link', 'dixcoverhub-custom-ui' ); ?></option><option value="dropdown" <?php selected( $item['type'], 'dropdown' ); ?>><?php esc_html_e( 'Dropdown menu', 'dixcoverhub-custom-ui' ); ?></option><option value="text" <?php selected( $item['type'], 'text' ); ?>><?php esc_html_e( 'Text label', 'dixcoverhub-custom-ui' ); ?></option></select></label>
				<label><span><?php esc_html_e( 'Icon', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( $base . '[icon]' ); ?>"><?php self::render_icon_options( $item['icon'] ); ?></select></label>
				<label class="dh-ui-nav-new-tab"><input type="hidden" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="0" /><input type="checkbox" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="1" <?php checked( ! empty( $item['new_tab'] ) ); ?> /> <?php esc_html_e( 'Open in a new tab', 'dixcoverhub-custom-ui' ); ?></label>
			</div>
			<div class="dh-ui-nav-children" data-dh-nav-children-panel>
				<div class="dh-ui-nav-children-heading"><strong><?php esc_html_e( 'Dropdown links', 'dixcoverhub-custom-ui' ); ?></strong><button class="button" type="button" data-dh-nav-add-child><?php esc_html_e( 'Add child link', 'dixcoverhub-custom-ui' ); ?></button></div>
				<div class="dh-ui-nav-child-list" data-dh-nav-children><?php foreach ( (array) $item['children'] as $child_index => $child ) { self::render_manual_nav_child( $child, $index, $child_index ); } ?></div>
				<template data-dh-nav-child-template><?php self::render_manual_nav_child( array(), '__ITEM__', '__CHILD__' ); ?></template>
			</div>
		</fieldset>
		<?php
	}

	/** Render one child link inside a manual dropdown. */
	private static function render_manual_nav_child( $child, $item_index, $child_index ) {
		$child = is_array( $child ) ? wp_parse_args( $child, array( 'label' => '', 'url' => '', 'icon' => '', 'new_tab' => 0 ) ) : array( 'label' => '', 'url' => '', 'icon' => '', 'new_tab' => 0 );
		$base  = self::OPTION_KEY . '[manual_nav_items][' . $item_index . '][children][' . $child_index . ']';
		?>
		<div class="dh-ui-nav-child" data-dh-nav-child>
			<label><span><?php esc_html_e( 'Link label', 'dixcoverhub-custom-ui' ); ?></span><input type="text" maxlength="80" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $child['label'] ); ?>" placeholder="e.g. Graduate roles" /></label>
			<label><span><?php esc_html_e( 'Destination URL', 'dixcoverhub-custom-ui' ); ?></span><input type="text" inputmode="url" name="<?php echo esc_attr( $base . '[url]' ); ?>" value="<?php echo esc_attr( $child['url'] ); ?>" placeholder="https:// or /category/" /></label>
			<label><span><?php esc_html_e( 'Icon', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( $base . '[icon]' ); ?>"><?php self::render_icon_options( $child['icon'] ); ?></select></label>
			<label class="dh-ui-nav-new-tab"><input type="hidden" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="0" /><input type="checkbox" name="<?php echo esc_attr( $base . '[new_tab]' ); ?>" value="1" <?php checked( ! empty( $child['new_tab'] ) ); ?> /> <?php esc_html_e( 'New tab', 'dixcoverhub-custom-ui' ); ?></label>
			<div class="dh-ui-repeater-actions"><button type="button" class="button" data-dh-nav-child-up aria-label="<?php esc_attr_e( 'Move child link up', 'dixcoverhub-custom-ui' ); ?>">↑</button><button type="button" class="button" data-dh-nav-child-down aria-label="<?php esc_attr_e( 'Move child link down', 'dixcoverhub-custom-ui' ); ?>">↓</button><button type="button" class="button-link-delete" data-dh-nav-remove-child><?php esc_html_e( 'Remove link', 'dixcoverhub-custom-ui' ); ?></button></div>
		</div>
		<?php
	}

	/** Render the curated Hugeicons picker. */
	private static function render_icon_options( $selected_icon ) {
		echo '<option value="">' . esc_html__( 'No icon', 'dixcoverhub-custom-ui' ) . '</option>';
		foreach ( self::manual_icon_names() as $icon_name ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $icon_name ), selected( $selected_icon, $icon_name, false ) );
		}
	}

	/** Render the single-post template controls. */
	private static function render_single_post_workspace() {
		$options = self::options();
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'ARTICLE EXPERIENCE', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Single Post', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Shape the public article page using WordPress post content and the opportunity details saved by AI Editor.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'PAGE TEMPLATE', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Article layout', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Uses the active theme header and footer, with the article layout shown in your reference.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'single_post_enabled', __( 'Use the DixcoverHub single-post layout', 'dixcoverhub-custom-ui' ), __( 'Stays off until you enable it here. Applies to standard posts; pages and other post types keep their theme templates.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_sidebar_enabled', __( 'Show the opportunities sidebar', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_sidebar_featured_enabled', __( 'Show Editor’s selection', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'single_post_sidebar_count', __( 'Featured opportunities', 'dixcoverhub-custom-ui' ), 3, 8 ); ?>
				<?php self::checkbox_row( $options, 'single_post_sidebar_trending_enabled', __( 'Show trending opportunities', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'single_post_sidebar_trending_count', __( 'Trending items', 'dixcoverhub-custom-ui' ), 2, 8 ); ?>
				<?php self::checkbox_row( $options, 'single_post_sidebar_latest_enabled', __( 'Show latest opportunities', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'single_post_sidebar_latest_count', __( 'Latest items', 'dixcoverhub-custom-ui' ), 2, 8 ); ?>
				<?php self::number_row( $options, 'single_post_width', __( 'Content width (px)', 'dixcoverhub-custom-ui' ), 960, 1440 ); ?>
				<?php self::color_row( $options, 'single_post_header_color', __( 'Title band background', 'dixcoverhub-custom-ui' ) ); ?>
			</tbody></table></section>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'OPPORTUNITY CONTENT', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'AI Generator fields', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Optional fields render only when the post has saved values.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'single_post_show_summary', __( 'Show the reader summary', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_show_apply', __( 'Show application links and deadline status', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_show_faqs', __( 'Show generated FAQs', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_show_tags', __( 'Show post tags', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_show_related', __( 'Show related opportunities', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'single_post_protect_content', __( 'Prevent copying and cutting article text', 'dixcoverhub-custom-ui' ), __( 'Optional reference behavior. Text remains selectable and screen readers can still read it.', 'dixcoverhub-custom-ui' ) ); ?>
			</tbody></table></section>
			<div class="dh-ui-form-actions"><?php submit_button( __( 'Save single-post settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'Preview a published post locally to review the layout.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</form>
		<?php
	}

	/** Render the opportunity archive controls. */
	private static function render_archive_workspace() {
		$options = self::options();
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'OPPORTUNITY DISCOVERY', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Archive & Filters', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Give visitors a fast way to search and narrow down published opportunities.', 'dixcoverhub-custom-ui' ); ?></p></div><a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/opportunities/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview archive', 'dixcoverhub-custom-ui' ); ?></a></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'ARCHIVE PAGE', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Heading and layout', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'The plugin serves the reference archive at /opportunities/ and also provides a shortcode for other pages.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'archive_enabled', __( 'Enable opportunity archive', 'dixcoverhub-custom-ui' ), __( 'The custom archive and filters take over /opportunities/ only after you activate them here.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'archive_sidebar_enabled', __( 'Show the opportunities sidebar', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::checkbox_row( $options, 'archive_sidebar_featured_enabled', __( 'Show Editor’s selection', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'archive_sidebar_featured_count', __( 'Featured items', 'dixcoverhub-custom-ui' ), 1, 8 ); ?>
				<?php self::checkbox_row( $options, 'archive_sidebar_trending_enabled', __( 'Show trending opportunities', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'archive_sidebar_trending_count', __( 'Trending items', 'dixcoverhub-custom-ui' ), 1, 8 ); ?>
				<?php self::checkbox_row( $options, 'archive_sidebar_latest_enabled', __( 'Show latest opportunities', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::number_row( $options, 'archive_sidebar_latest_count', __( 'Latest items', 'dixcoverhub-custom-ui' ), 1, 8 ); ?>
				<?php self::text_row( $options, 'archive_heading', __( 'Archive heading', 'dixcoverhub-custom-ui' ) ); ?>
				<tr><th scope="row"><label for="dh-navbar-archive_intro"><?php esc_html_e( 'Intro text', 'dixcoverhub-custom-ui' ); ?></label></th><td><textarea class="large-text" rows="3" id="dh-navbar-archive_intro" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[archive_intro]"><?php echo esc_textarea( $options['archive_intro'] ); ?></textarea></td></tr>
				<?php self::number_row( $options, 'archive_posts_per_page', __( 'Listings per page', 'dixcoverhub-custom-ui' ), 6, 36 ); ?>
			</tbody></table></section>
			<div class="dh-ui-form-actions"><?php submit_button( __( 'Save archive settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'Filters use the shared opportunity types, modes, and locations taxonomies.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</form>
		<div class="dh-ui-card dh-ui-studio-note"><p class="dh-ui-eyebrow"><?php esc_html_e( 'PAGE SHORTCODE', 'dixcoverhub-custom-ui' ); ?></p><p><code>[dixcoverhub_opportunities]</code></p><p><?php esc_html_e( 'Use this in a Shortcode block if you want to place the listing on a different page.', 'dixcoverhub-custom-ui' ); ?></p></div>
		<?php
	}

	/** Render the rolling deadline planner controls. */
	private static function render_deadlines_workspace() {
		$options = self::options();
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'CLOSING DATE PLANNER', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Deadlines', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Show live closing-date windows and let visitors browse deadlines by period or choose a date range.', 'dixcoverhub-custom-ui' ); ?></p></div><a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/deadlines/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview deadlines', 'dixcoverhub-custom-ui' ); ?></a></div>
		<form class="dh-ui-navbar-form" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<section class="dh-ui-card"><div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'DEADLINE PAGE', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Activation and content', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Uses the site timezone and published WordPress posts with a saved opportunity deadline. The overview, period links, and custom date ranges all share one activation switch.', 'dixcoverhub-custom-ui' ); ?></p></div><span class="dh-ui-workspace-status <?php echo ! empty( $options['deadline_page_enabled'] ) ? 'is-active' : 'is-inactive'; ?>"><?php echo ! empty( $options['deadline_page_enabled'] ) ? esc_html__( 'Active', 'dixcoverhub-custom-ui' ) : esc_html__( 'Off', 'dixcoverhub-custom-ui' ); ?></span></div>
			<table class="form-table" role="presentation"><tbody>
				<?php self::checkbox_row( $options, 'deadline_page_enabled', __( 'Activate the custom deadlines experience', 'dixcoverhub-custom-ui' ), __( 'Until activated, this plugin leaves the /deadlines/ path to your theme or WordPress page.', 'dixcoverhub-custom-ui' ) ); ?>
				<?php self::text_row( $options, 'deadline_page_heading', __( 'Overview heading', 'dixcoverhub-custom-ui' ) ); ?>
				<tr><th scope="row"><label for="dh-navbar-deadline_page_intro"><?php esc_html_e( 'Introduction', 'dixcoverhub-custom-ui' ); ?></label></th><td><textarea class="large-text" rows="3" id="dh-navbar-deadline_page_intro" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[deadline_page_intro]"><?php echo esc_textarea( $options['deadline_page_intro'] ); ?></textarea></td></tr>
				<?php self::number_row( $options, 'deadline_page_results_per_page', __( 'Results per page', 'dixcoverhub-custom-ui' ), 6, 60 ); ?>
				<?php self::number_row( $options, 'deadline_page_preview_per_window', __( 'Preview items per window', 'dixcoverhub-custom-ui' ), 3, 18 ); ?>
				<?php self::checkbox_row( $options, 'deadline_page_sidebar_enabled', __( 'Show featured, trending, and latest opportunities', 'dixcoverhub-custom-ui' ) ); ?>
			</tbody></table></section>
			<div class="dh-ui-form-actions"><?php submit_button( __( 'Save deadlines settings', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?><p class="dh-ui-note"><?php esc_html_e( 'Dates are interpreted as calendar days in Settings → General → Timezone. Custom ranges can span up to 366 days.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</form>
		<div class="dh-ui-card dh-ui-studio-note"><p class="dh-ui-eyebrow"><?php esc_html_e( 'SHAREABLE WINDOWS', 'dixcoverhub-custom-ui' ); ?></p><p><code>/deadlines/today/</code>, <code>/deadlines/tomorrow/</code>, <code>/deadlines/in-2-days/</code>, <code>/deadlines/this-week/</code>, <code>/deadlines/this-month/</code>, <code>/deadlines/next-month/</code>, <code>/deadlines/later-this-year/</code></p><p><?php esc_html_e( 'The main page also accepts ?from=YYYY-MM-DD&to=YYYY-MM-DD. Use [dixcoverhub_deadlines] to place the same experience on another page.', 'dixcoverhub-custom-ui' ); ?></p></div>
		<?php
	}

	/** Add a checkbox row to the settings form. */
	private static function checkbox_row( $options, $key, $label, $description = '' ) {
		$name = self::OPTION_KEY . '[' . $key . ']';
		?>
		<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $options[ $key ] ) ); ?> /> <?php echo esc_html( $label ); ?></label><?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?></td></tr>
		<?php
	}

	/** Add a text, URL, or slug row to the settings form. */
	private static function text_row( $options, $key, $label, $type = 'text', $description = '' ) {
		$name = self::OPTION_KEY . '[' . $key . ']';
		$input_type = 'url' === $type ? 'text' : $type;
		?>
		<tr><th scope="row"><label for="dh-navbar-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input class="regular-text" id="dh-navbar-<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $input_type ); ?>" <?php echo 'url' === $type ? 'inputmode="url"' : ''; ?> name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>" /><?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?></td></tr>
		<?php
	}

	/** Add the color field to the settings form. */
	private static function color_row( $options, $key, $label ) {
		$name = self::OPTION_KEY . '[' . $key . ']';
		?>
		<tr><th scope="row"><label for="dh-navbar-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="dh-navbar-<?php echo esc_attr( $key ); ?>" type="color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $options[ $key ] ); ?>" /></td></tr>
		<?php
	}

	/** Add a bounded number field to the settings form. */
	private static function number_row( $options, $key, $label, $min, $max ) {
		$name = self::OPTION_KEY . '[' . $key . ']';
		?>
		<tr><th scope="row"><label for="dh-navbar-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="dh-navbar-<?php echo esc_attr( $key ); ?>" type="number" min="<?php echo absint( $min ); ?>" max="<?php echo absint( $max ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo absint( $options[ $key ] ); ?>" /></td></tr>
		<?php
	}

	/** Add a direct link to the plugin's settings from the Plugins list. */
	public static function plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'themes.php?page=dixcoverhub-custom-ui' ) ) . '">' . esc_html__( 'Open Studio', 'dixcoverhub-custom-ui' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/** Load front-end styles and behaviour only when the feature is enabled. */
	public static function enqueue_assets() {
		$options = self::options();
		if ( ! is_admin() && is_front_page() && ! empty( $options['home_enabled'] ) ) {
			wp_enqueue_style( 'dixcoverhub-home', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/home.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
			wp_enqueue_script( 'dixcoverhub-home', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/home.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		}
		if ( ! empty( $options['enabled'] ) ) {
			wp_enqueue_style( 'dixcoverhub-navbar', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/navbar.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
			wp_enqueue_script( 'dixcoverhub-navbar', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/navbar.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		}
		if ( ! empty( $options['bottom_nav_enabled'] ) ) {
			wp_enqueue_style( 'dixcoverhub-bottom-nav', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/bottom-nav.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
			wp_enqueue_script( 'dixcoverhub-bottom-nav', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/bottom-nav.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		}
	}

	/** Use the custom front-page template only after the Home feature is activated. */
	public static function maybe_home_template( $template ) {
		$options = self::options();
		if ( is_admin() || ! is_front_page() || empty( $options['home_enabled'] ) ) {
			return $template;
		}
		$home_template = dirname( __DIR__ ) . '/templates/home.php';
		return is_readable( $home_template ) ? $home_template : $template;
	}

	/** Render the opt-in reference-style homepage. */
	public static function render_home_content() {
		$options = self::options();
		$posts = new WP_Query( array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => min( 20, max( 1, absint( $options['home_deck_count'] ) ) ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		) );
		$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'parent' => 0, 'exclude' => array( absint( get_option( 'default_category' ) ) ), 'orderby' => 'count', 'order' => 'DESC', 'number' => 20 ) );
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}
		$category_rows = array( array(), array() );
		foreach ( array_values( $categories ) as $index => $category ) {
			$category_rows[ $index % 2 ][] = $category;
		}
		if ( empty( $category_rows[0] ) && ! empty( $category_rows[1] ) ) {
			$category_rows[0] = $category_rows[1];
		}
		$cta_url = self::bottom_nav_url( $options['home_cta_url'], home_url( '/opportunities/' ) );
		$accent = sanitize_hex_color( $options['home_highlight_color'] ) ?: '#611f69';
		$surface = sanitize_hex_color( $options['home_background_color'] ) ?: '#f4f7fb';
		$style = sprintf( '--dh-home-accent:%1$s;--dh-home-surface:%2$s;', esc_attr( $accent ), esc_attr( $surface ) );
		?>
		<main class="dh-home" style="<?php echo esc_attr( $style ); ?>">
			<section class="dh-home-hero" aria-labelledby="dh-home-title">
				<div class="dh-home-shell">
					<div class="dh-home-copy">
						<p class="dh-home-kicker"><span></span><?php esc_html_e( 'YOUR NEXT STEP STARTS HERE', 'dixcoverhub-custom-ui' ); ?></p>
						<h1 id="dh-home-title"><?php echo esc_html( $options['home_title_before'] ); ?> <span><?php echo esc_html( $options['home_title_highlight'] ); ?></span> <?php echo esc_html( $options['home_title_after'] ); ?></h1>
						<p class="dh-home-description"><?php echo esc_html( $options['home_description'] ); ?></p>
						<a class="dh-home-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $options['home_cta_label'] ); ?><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-home-cta-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?></a>
						<?php if ( ! empty( $options['home_category_strip'] ) && $categories ) : ?>
							<div class="dh-home-categories" aria-label="<?php esc_attr_e( 'Browse opportunities by category', 'dixcoverhub-custom-ui' ); ?>">
								<p><?php esc_html_e( 'Browse opportunities', 'dixcoverhub-custom-ui' ); ?><span></span></p>
								<nav class="dh-home-category-accessible" aria-label="<?php esc_attr_e( 'Opportunity categories', 'dixcoverhub-custom-ui' ); ?>"><?php foreach ( $categories as $category ) : ?><a href="<?php echo esc_url( add_query_arg( 'dh_category', $category->slug, home_url( '/opportunities/' ) ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endforeach; ?></nav>
								<div class="dh-home-categories-mobile"><?php foreach ( $categories as $category ) : ?><a href="<?php echo esc_url( add_query_arg( 'dh_category', $category->slug, home_url( '/opportunities/' ) ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endforeach; ?></div>
								<div class="dh-home-category-marquee" aria-hidden="true"><?php foreach ( $category_rows as $row_index => $row ) : if ( ! $row ) { continue; } ?><div class="dh-home-category-row <?php echo 1 === $row_index ? 'is-reverse' : ''; ?>"><div><?php for ( $repeat = 0; $repeat < 3; $repeat++ ) : foreach ( $row as $category ) : ?><a tabindex="-1" href="<?php echo esc_url( add_query_arg( 'dh_category', $category->slug, home_url( '/opportunities/' ) ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endforeach; endfor; ?></div></div><?php endforeach; ?></div>
							</div>
						<?php endif; ?>
					</div>
					<div class="dh-home-deck-wrap">
						<?php if ( $posts->have_posts() ) : ?>
							<div class="dh-home-deck" data-dh-home-deck role="region" aria-roledescription="carousel" aria-label="<?php esc_attr_e( 'Latest opportunities', 'dixcoverhub-custom-ui' ); ?>" tabindex="0">
								<span class="dh-home-deck-back dh-home-deck-back--two" aria-hidden="true"></span><span class="dh-home-deck-back dh-home-deck-back--one" aria-hidden="true"></span>
				<?php $card_index = 0; while ( $posts->have_posts() ) : $posts->the_post(); $post_id = get_the_ID(); $post_categories = get_the_category( $post_id ); $category_name = $post_categories ? $post_categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ); $opportunity_data = get_post_meta( $post_id, '_dixcoverhub_opportunity_data', true ); $opportunity_data = is_array( $opportunity_data ) ? $opportunity_data : array(); $provider_value = $opportunity_data['provider_name'] ?? get_post_meta( $post_id, '_dixcoverhub_provider_name', true ); $provider_name = is_scalar( $provider_value ) ? sanitize_text_field( (string) $provider_value ) : ''; $location_value = $opportunity_data['location'] ?? get_post_meta( $post_id, '_dixcoverhub_location', true ); $location = is_scalar( $location_value ) ? sanitize_text_field( (string) $location_value ) : ''; $image = get_the_post_thumbnail_url( $post_id, 'large' ); $excerpt = get_the_excerpt(); if ( ! $excerpt ) { $excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_the_content() ) ), 23 ); } ?>
									<article class="dh-home-card<?php echo 0 === $card_index ? ' is-current' : ''; ?>" data-dh-home-card aria-hidden="<?php echo 0 === $card_index ? 'false' : 'true'; ?>" <?php echo 0 === $card_index ? '' : 'inert'; ?> aria-label="<?php echo esc_attr( sprintf( __( 'Opportunity %1$d of %2$d', 'dixcoverhub-custom-ui' ), $card_index + 1, $posts->post_count ) ); ?>">
										<a class="dh-home-card-link" href="<?php the_permalink(); ?>">
											<div class="dh-home-card-image"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true ) ?: get_the_title() ); ?>" loading="<?php echo 0 === $card_index ? 'eager' : 'lazy'; ?>" /><?php else : ?><span><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Briefcase01Icon', 'dh-home-card-placeholder-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?><?php echo esc_html( $category_name ); ?></span><?php endif; ?><b><?php echo esc_html( $category_name ); ?></b></div>
											<div class="dh-home-card-copy"><p class="dh-home-card-meta"><?php if ( $provider_name ) : ?><span><?php echo esc_html( $provider_name ); ?></span><?php endif; ?><span><?php echo esc_html( $category_name ); ?></span><?php if ( $location ) : ?><span><?php echo esc_html( $location ); ?></span><?php endif; ?></p><h2><?php the_title(); ?></h2><p class="dh-home-card-excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 27 ) ); ?></p><span class="dh-home-card-action"><?php esc_html_e( 'View opportunity', 'dixcoverhub-custom-ui' ); ?><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowRight01Icon', 'dh-home-card-action-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?></span></div>
										</a>
						<button class="dh-home-card-bookmark" type="button" data-dh-home-bookmark data-save-label="<?php echo esc_attr( sprintf( __( 'Save %s', 'dixcoverhub-custom-ui' ), get_the_title() ) ); ?>" data-remove-label="<?php echo esc_attr( sprintf( __( 'Remove %s from saved opportunities', 'dixcoverhub-custom-ui' ), get_the_title() ) ); ?>" data-saved-message="<?php echo esc_attr( sprintf( __( '%s saved', 'dixcoverhub-custom-ui' ), get_the_title() ) ); ?>" data-unsaved-message="<?php echo esc_attr( sprintf( __( '%s removed from saved opportunities', 'dixcoverhub-custom-ui' ), get_the_title() ) ); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( __( 'Save %s', 'dixcoverhub-custom-ui' ), get_the_title() ) ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Bookmark01Icon', 'dh-home-card-bookmark-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?><span class="screen-reader-text" data-dh-home-bookmark-status aria-live="polite"></span></button>
									</article>
								<?php $card_index++; endwhile; wp_reset_postdata(); ?>
							</div>
							<div class="dh-home-deck-controls"><span data-dh-home-deck-status aria-live="polite">1 / <?php echo absint( $posts->post_count ); ?></span><div><button type="button" data-dh-home-prev aria-label="<?php esc_attr_e( 'Previous opportunity', 'dixcoverhub-custom-ui' ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowLeft01Icon', 'dh-home-control-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?></button><button type="button" data-dh-home-next aria-label="<?php esc_attr_e( 'Next opportunity', 'dixcoverhub-custom-ui' ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowRight01Icon', 'dh-home-control-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?></button></div></div>
						<?php else : ?>
							<div class="dh-home-empty-card"><span><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Briefcase01Icon', 'dh-home-empty-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled Hugeicons SVG. ?></span><p><?php esc_html_e( 'Your next opportunity is on its way.', 'dixcoverhub-custom-ui' ); ?></p><a href="<?php echo esc_url( $cta_url ); ?>"><?php esc_html_e( 'Explore opportunities', 'dixcoverhub-custom-ui' ); ?></a></div>
						<?php endif; ?>
					</div>
				</div>
			</section>
			<?php if ( ! empty( $options['archive_enabled'] ) && class_exists( 'DixcoverHub_Custom_UI_Archive' ) ) : ?><div class="dh-home-archive"><?php echo DixcoverHub_Custom_UI_Archive::render_archive( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- archive renderer escapes dynamic content. ?></div><?php endif; ?>
		</main>
		<?php
	}

	/** Reserve space under page content while the mobile navigation is active. */
	public static function add_bottom_navigation_body_class( $classes ) {
		$options = self::options();
		if ( ! is_admin() && ! is_page( 'login' ) && ! empty( $options['bottom_nav_enabled'] ) ) {
			$classes[] = 'dh-has-bottom-nav';
		}
		return $classes;
	}

	/** Restore the responsive mobile dock and its companion desktop pill. */
	public static function render_bottom_navigation() {
		$options = self::options();
		if ( is_admin() || is_page( 'login' ) || empty( $options['bottom_nav_enabled'] ) ) {
			return;
		}
		$community_url = self::bottom_nav_url( $options['bottom_nav_community_url'], 'https://whatsapp.com/channel/0029Va9uQXIAYlUP7x3kDQ2T' );
		$links = array(
			array( 'label' => $options['bottom_nav_jobs_label'], 'description' => $options['bottom_nav_jobs_description'], 'url' => $options['bottom_nav_jobs_url'], 'icon' => 'Briefcase01Icon' ),
			array( 'label' => $options['bottom_nav_opportunities_label'], 'description' => $options['bottom_nav_opportunities_description'], 'url' => $options['bottom_nav_opportunities_url'], 'icon' => 'Compass01Icon' ),
			array( 'label' => $options['bottom_nav_deadlines_label'], 'description' => $options['bottom_nav_deadlines_description'], 'url' => $options['bottom_nav_deadlines_url'], 'icon' => 'CalendarDaysIcon' ),
		);
		$accent = sanitize_hex_color( $options['bottom_nav_accent_color'] ) ?: '#611f69';
		?>
		<div class="dh-bottom-nav" data-dh-bottom-nav style="--dh-bottom-nav-accent: <?php echo esc_attr( $accent ); ?>;">
			<button class="dh-bottom-nav__overlay" type="button" data-dh-bottom-nav-close aria-label="<?php esc_attr_e( 'Close more navigation', 'dixcoverhub-custom-ui' ); ?>" hidden></button>
			<div class="dh-bottom-nav__panel-wrap" data-dh-bottom-nav-panel-wrap hidden>
				<section class="dh-bottom-nav__panel" data-dh-bottom-nav-panel role="dialog" aria-modal="true" aria-labelledby="dh-bottom-nav-panel-title" tabindex="-1">
					<div class="dh-bottom-nav__panel-heading"><h2 id="dh-bottom-nav-panel-title"><?php echo esc_html( $options['bottom_nav_panel_title'] ); ?></h2></div>
					<div class="dh-bottom-nav__panel-links">
						<?php foreach ( $links as $link ) : ?>
							<a class="dh-bottom-nav__panel-link" href="<?php echo esc_url( self::bottom_nav_url( $link['url'], home_url( '/' ) ) ); ?>">
								<span class="dh-bottom-nav__panel-icon" aria-hidden="true"><?php echo DixcoverHub_Custom_UI_Icons::svg( $link['icon'], 'dh-bottom-nav__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?></span>
								<span class="dh-bottom-nav__panel-copy"><span class="dh-bottom-nav__panel-label"><?php echo esc_html( $link['label'] ); ?></span><span class="dh-bottom-nav__panel-description"><?php echo esc_html( $link['description'] ); ?></span></span>
								<?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-bottom-nav__arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			</div>
			<nav class="dh-bottom-nav__mobile" aria-label="<?php esc_attr_e( 'Quick navigation', 'dixcoverhub-custom-ui' ); ?>">
				<a class="dh-bottom-nav__icon-button<?php echo is_front_page() ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $options['bottom_nav_home_label'] ); ?>" <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Home01Icon', 'dh-bottom-nav__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?></a>
				<a class="dh-bottom-nav__community" href="<?php echo esc_url( $community_url ); ?>" target="_blank" rel="noopener noreferrer" data-dh-analytics="community_join"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'WhatsappIcon', 'dh-bottom-nav__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?><span><?php echo esc_html( $options['bottom_nav_community_label'] ); ?></span></a>
				<button class="dh-bottom-nav__icon-button" type="button" data-dh-bottom-nav-toggle aria-expanded="false" aria-label="<?php echo esc_attr( $options['bottom_nav_more_label'] ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Menu01Icon', 'dh-bottom-nav__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?></button>
			</nav>
			<div class="dh-bottom-nav__desktop">
				<a class="dh-bottom-nav__desktop-community" href="<?php echo esc_url( $community_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $options['bottom_nav_community_label'] ); ?> on WhatsApp" data-dh-analytics="community_join"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'WhatsappIcon', 'dh-bottom-nav__desktop-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?><span><?php echo esc_html( $options['bottom_nav_community_label'] ); ?></span></a>
				<button class="dh-bottom-nav__desktop-toggle" type="button" data-dh-bottom-nav-toggle aria-expanded="false" aria-label="<?php echo esc_attr( $options['bottom_nav_more_label'] ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowDown01Icon', 'dh-bottom-nav__desktop-arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons SVG. ?></button>
			</div>
		</div>
		<?php
	}

	/** Resolve a saved relative URL against the current WordPress home URL. */
	private static function bottom_nav_url( $value, $fallback ) {
		$url = trim( (string) $value );
		if ( '' === $url ) { $url = (string) $fallback; }
		return 0 === strpos( $url, '/' ) ? home_url( $url ) : $url;
	}

	/** Print the navbar at the WordPress body hook when automatic display is on. */
	public static function render_at_body_open() {
		$options = self::options();
		if ( ! empty( $options['automatic_display'] ) ) {
			echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes all dynamic values.
		}
	}

	/** Shortcode fallback for themes that do not call wp_body_open(). */
	public static function render_shortcode() {
		return self::render();
	}

	/** Hide a block theme's own header when the custom navbar is replacing it. */
	public static function maybe_replace_block_theme_header( $block_content, $block ) {
		$options = self::options();
		if ( is_admin() || empty( $options['enabled'] ) || empty( $options['automatic_display'] ) || empty( $options['replace_block_header'] ) ) {
			return $block_content;
		}

		if ( ! is_array( $block ) || 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$area  = sanitize_key( $attrs['area'] ?? '' );
		$slug  = sanitize_title( $attrs['slug'] ?? '' );
		if ( 'header' === $area || 'header' === $slug ) {
			return '';
		}

		return $block_content;
	}

	/** Remove Astra's existing desktop and mobile header when this navbar replaces it. */
	public static function maybe_replace_astra_header() {
		$options = self::options();
		if ( is_admin() || empty( $options['enabled'] ) || empty( $options['automatic_display'] ) || empty( $options['replace_block_header'] ) || 'astra' !== get_template() ) {
			return;
		}

		// Remove the wrapper used by Astra's legacy header and Header Footer Builder.
		remove_action( 'astra_header', 'astra_header_markup' );
		remove_action( 'astra_masthead', 'astra_masthead_primary_template' );
	}

	/** Build and return the accessible responsive navbar markup. */
	private static function render() {
		$options = self::options();
		if ( empty( $options['enabled'] ) || self::$rendered ) {
			return '';
		}

		self::$rendered = true;
		$jobs_items   = self::get_jobs_items( $options );
		$opportunity_items = self::get_opportunity_items( $options );
		$jobs_url     = self::jobs_url( $options );
		$opportunities_url = self::setting_url( '', 'opportunities' );
		$cta_url       = self::setting_url( $options['cta_url'], 'opportunities' );
		$deadlines_url = self::setting_url( $options['deadlines_url'], 'deadlines' );
		$about_url    = self::setting_url( $options['about_url'], 'about' );
		$contact_url  = self::setting_url( $options['contact_url'], 'contact' );
		$login_url    = self::login_url( $options );
		$site_name    = get_bloginfo( 'name' );
		$logo_enabled = ! empty( $options['logo_enabled'] );
		$logo_url     = $logo_enabled ? $options['logo_url'] : '';
		$show_wordmark = ! $logo_enabled || ! empty( $options['show_wordmark'] );
		$logo_defaults = self::defaults();
		if ( ! $logo_url ) {
			$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
			$logo_url = $custom_logo_id ? (string) wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
		}

		$current = self::current_navigation_state( $options );
		$use_manual_items = ! empty( $options['manual_nav_enabled'] );
		$classes = array( 'dh-navbar' );
		if ( ! empty( $options['sticky'] ) ) {
			$classes[] = 'dh-navbar--sticky';
		}
		if ( empty( $options['transparent'] ) ) {
			$classes[] = 'dh-navbar--solid';
		}
		$style = sprintf( '--dh-navbar-primary:%1$s;--dh-navbar-width:%2$dpx;--dh-navbar-surface:%3$s;--dh-navbar-ink:%4$s;--dh-navbar-muted:%5$s;--dh-navbar-line:%6$s;--dh-navbar-logo-width:%7$dpx;--dh-navbar-logo-height:%8$dpx;--dh-navbar-logo-mobile-width:%9$dpx;--dh-navbar-logo-mobile-height:%10$dpx;', esc_attr( $options['primary_color'] ), absint( $options['content_width'] ), esc_attr( $options['navbar_surface_color'] ), esc_attr( $options['navbar_text_color'] ), esc_attr( $options['navbar_link_color'] ), esc_attr( $options['navbar_border_color'] ), absint( $logo_enabled ? $options['logo_max_width'] : $logo_defaults['logo_max_width'] ), absint( $logo_enabled ? $options['logo_max_height'] : $logo_defaults['logo_max_height'] ), absint( $logo_enabled ? $options['logo_mobile_max_width'] : $logo_defaults['logo_mobile_max_width'] ), absint( $logo_enabled ? $options['logo_mobile_max_height'] : $logo_defaults['logo_mobile_max_height'] ) );
		ob_start();
		?>
		<header class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-dh-navbar style="<?php echo esc_attr( $style ); ?>">
			<div class="dh-navbar__inner">
				<a class="dh-navbar__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s home', 'dixcoverhub-custom-ui' ), $site_name ) ); ?>">
					<?php if ( $logo_url ) : ?>
						<img class="dh-navbar__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" />
					<?php endif; ?>
					<?php if ( $show_wordmark ) : ?><span class="dh-navbar__wordmark"><?php echo esc_html( $site_name ); ?></span><?php endif; ?>
				</a>

				<nav class="dh-navbar__desktop-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'dixcoverhub-custom-ui' ); ?>">
					<ul class="dh-navbar__desktop-links">
						<?php if ( $use_manual_items ) : ?>
							<?php foreach ( $options['manual_nav_items'] as $manual_index => $manual_item ) { self::render_manual_desktop_item( $manual_item, $manual_index ); } ?>
						<?php else : ?>
							<?php self::render_category_menu( $options['jobs_label'], $jobs_url, __( 'Browse all jobs', 'dixcoverhub-custom-ui' ), $jobs_items, ! empty( $current['jobs'] ), 'jobs' ); ?>
							<?php self::render_category_menu( $options['opportunities_label'], $opportunities_url, __( 'Explore all opportunities', 'dixcoverhub-custom-ui' ), $opportunity_items, ! empty( $current['opportunities'] ), 'opportunities' ); ?>
							<?php self::render_page_link( $options['deadlines_label'], $deadlines_url, ! empty( $current['deadlines'] ) ); ?>
							<?php self::render_page_link( $options['about_label'], $about_url, ! empty( $current['about'] ) ); ?>
							<?php self::render_page_link( $options['contact_label'], $contact_url, ! empty( $current['contact'] ) ); ?>
						<?php endif; ?>
					</ul>
					<?php echo self::render_extra_menu( $options['extra_menu_id'], 'desktop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu escapes menu data. ?>
				</nav>

				<div class="dh-navbar__actions">
					<?php if ( ! empty( $options['show_login'] ) ) : ?><a class="dh-navbar__login" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $options['login_label'] ); ?></a><?php endif; ?>
					<a class="dh-navbar__cta" href="<?php echo esc_url( $cta_url ); ?>"><span><?php echo esc_html( $options['cta_label'] ); ?></span><?php echo DixcoverHub_Custom_UI_Icons::svg( $options['cta_icon'], 'dh-navbar__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected bundled Hugeicons. ?></a>
				</div>

				<button class="dh-navbar__mobile-toggle" type="button" data-dh-mobile-toggle aria-controls="dh-navbar-drawer" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open navigation menu', 'dixcoverhub-custom-ui' ); ?>">
					<?php echo self::icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
				<span class="dh-navbar__divider" aria-hidden="true"></span>
			</div>

			<button class="dh-navbar__scrim" type="button" data-dh-close hidden aria-label="<?php esc_attr_e( 'Close navigation menu', 'dixcoverhub-custom-ui' ); ?>"></button>
			<aside class="dh-navbar__drawer" id="dh-navbar-drawer" data-dh-drawer role="dialog" aria-modal="true" hidden aria-label="<?php esc_attr_e( 'Mobile navigation', 'dixcoverhub-custom-ui' ); ?>">
				<div class="dh-navbar__drawer-head">
					<a class="dh-navbar__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%s home', 'dixcoverhub-custom-ui' ), $site_name ) ); ?>">
						<?php if ( $logo_url ) : ?><img class="dh-navbar__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" /><?php endif; ?>
						<?php if ( $show_wordmark ) : ?><span class="dh-navbar__wordmark"><?php echo esc_html( $site_name ); ?></span><?php endif; ?>
					</a>
					<button class="dh-navbar__close" type="button" data-dh-close aria-label="<?php esc_attr_e( 'Close navigation menu', 'dixcoverhub-custom-ui' ); ?>"><?php echo self::icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				</div>
				<div class="dh-navbar__drawer-scroll">
					<?php if ( $use_manual_items ) : ?>
						<div class="dh-navbar__drawer-pages"><ul class="dh-navbar__drawer-link-list"><?php foreach ( $options['manual_nav_items'] as $manual_index => $manual_item ) { self::render_manual_mobile_item( $manual_item, $manual_index ); } ?></ul><?php echo self::render_extra_menu( $options['extra_menu_id'], 'mobile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu escapes menu data. ?></div>
					<?php else : ?>
						<?php self::render_mobile_category_group( $options['jobs_label'], $jobs_url, $jobs_items, ! empty( $current['jobs'] ), 'jobs' ); ?>
						<?php self::render_mobile_category_group( $options['opportunities_label'], $opportunities_url, $opportunity_items, ! empty( $current['opportunities'] ), 'opportunities' ); ?>
						<div class="dh-navbar__drawer-pages">
							<ul class="dh-navbar__drawer-link-list">
							<?php self::render_page_link( $options['deadlines_label'], $deadlines_url, ! empty( $current['deadlines'] ), true ); ?>
							<?php self::render_page_link( $options['about_label'], $about_url, ! empty( $current['about'] ), true ); ?>
							<?php self::render_page_link( $options['contact_label'], $contact_url, ! empty( $current['contact'] ), true ); ?>
							</ul>
							<?php echo self::render_extra_menu( $options['extra_menu_id'], 'mobile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nav_menu escapes menu data. ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="dh-navbar__drawer-footer">
					<a class="dh-navbar__cta dh-navbar__cta--mobile" href="<?php echo esc_url( $cta_url ); ?>"><span><?php echo esc_html( $options['mobile_cta_label'] ); ?></span><?php echo DixcoverHub_Custom_UI_Icons::svg( $options['cta_icon'], 'dh-navbar__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected bundled Hugeicons. ?></a>
					<?php if ( ! empty( $options['show_login'] ) ) : ?><a class="dh-navbar__login dh-navbar__login--mobile" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $options['login_label'] ); ?></a><?php endif; ?>
				</div>
			</aside>
		</header>
		<noscript><style>@media(max-width:900px){.dh-navbar__desktop-nav{display:block!important}.dh-navbar__desktop-links{flex-wrap:wrap}.dh-navbar__mobile-toggle,.dh-navbar__actions{display:none!important}.dh-navbar__desktop-nav .dh-navbar__dropdown-panel{position:static;display:block;transform:none;box-shadow:none;margin:8px 0}.dh-navbar__desktop-nav .dh-navbar__dropdown-grid{grid-template-columns:1fr}}</style></noscript>
		<?php
		return (string) ob_get_clean();
	}

	/** Render a manually configured desktop menu item. */
	private static function render_manual_desktop_item( $item, $index ) {
		$label = $item['label'];
		$url   = self::manual_nav_url( $item['url'] );
		$icon  = ! empty( $item['icon'] ) ? DixcoverHub_Custom_UI_Icons::svg( $item['icon'], 'dh-navbar__icon dh-navbar__manual-icon' ) : '';
		$active = self::is_current_manual_url( $item['url'] );
		$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
		if ( 'dropdown' === $item['type'] && $children ) {
			foreach ( $children as $child ) {
				$active = $active || self::is_current_manual_url( $child['url'] );
			}
			?>
			<li class="dh-navbar__menu-item"><details class="dh-navbar__dropdown dh-navbar__manual-dropdown" data-dh-dropdown><summary class="dh-navbar__top-link<?php echo $active ? ' is-active' : ''; ?>" aria-controls="dh-navbar-manual-menu-<?php echo absint( $index ); ?>"><?php if ( $icon ) : ?><span class="dh-navbar__manual-item-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span><?php echo self::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?></summary>
				<div class="dh-navbar__dropdown-panel dh-navbar__manual-panel" id="dh-navbar-manual-menu-<?php echo absint( $index ); ?>"><div class="dh-navbar__panel-head"><span><?php echo esc_html( $label ); ?></span><?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php esc_html_e( 'View all', 'dixcoverhub-custom-ui' ); ?><?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?></a><?php endif; ?></div><div class="dh-navbar__manual-child-grid">
					<?php foreach ( $children as $child ) : $child_icon = ! empty( $child['icon'] ) ? DixcoverHub_Custom_UI_Icons::svg( $child['icon'], 'dh-navbar__icon' ) : ''; ?>
						<a class="dh-navbar__manual-child<?php echo self::is_current_manual_url( $child['url'] ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::manual_nav_url( $child['url'] ) ); ?>" <?php echo ! empty( $child['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php if ( $child_icon ) : ?><span class="dh-navbar__manual-child-icon" aria-hidden="true"><?php echo $child_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?></span><?php endif; ?><span><?php echo esc_html( $child['label'] ); ?></span></a>
					<?php endforeach; ?>
				</div></div></details></li>
			<?php
			return;
		}
		if ( 'text' === $item['type'] ) {
			?>
			<li class="dh-navbar__menu-item dh-navbar__menu-item--text"><span class="dh-navbar__top-link dh-navbar__text-item"><?php if ( $icon ) : ?><span class="dh-navbar__manual-item-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></span></li>
			<?php
			return;
		}

		?>
		<li class="dh-navbar__menu-item"><a class="dh-navbar__top-link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $active ? 'aria-current="page"' : ''; ?> <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php if ( $icon ) : ?><span class="dh-navbar__manual-item-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></a></li>
		<?php
	}

	/** Render a manually configured mobile drawer link or accordion. */
	private static function render_manual_mobile_item( $item, $index ) {
		$label = $item['label'];
		$url = self::manual_nav_url( $item['url'] );
		$icon = ! empty( $item['icon'] ) ? DixcoverHub_Custom_UI_Icons::svg( $item['icon'], 'dh-navbar__icon' ) : '';
		$children = isset( $item['children'] ) && is_array( $item['children'] ) ? $item['children'] : array();
		$active = self::is_current_manual_url( $item['url'] );
		if ( 'dropdown' === $item['type'] && $children ) {
			foreach ( $children as $child ) {
				$active = $active || self::is_current_manual_url( $child['url'] );
			}
			$panel_id = 'dh-navbar-manual-mobile-' . absint( $index );
			?>
			<li class="dh-navbar__manual-mobile-item"><div class="dh-navbar__drawer-group-head"><div class="dh-navbar__manual-mobile-label"><?php if ( $url ) : ?><a class="dh-navbar__drawer-group-link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?><span><?php echo esc_html( $label ); ?></span></a><?php else : ?><span class="dh-navbar__drawer-group-link"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?><span><?php echo esc_html( $label ); ?></span></span><?php endif; ?></div><button class="dh-navbar__drawer-expand" type="button" data-dh-accordion aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( __( 'Toggle %s links', 'dixcoverhub-custom-ui' ), $label ) ); ?>"><?php echo self::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?></button></div><div class="dh-navbar__drawer-categories dh-navbar__manual-mobile-children" id="<?php echo esc_attr( $panel_id ); ?>" data-dh-accordion-panel hidden><?php foreach ( $children as $child ) : $child_icon = ! empty( $child['icon'] ) ? DixcoverHub_Custom_UI_Icons::svg( $child['icon'], 'dh-navbar__icon' ) : ''; ?><a class="dh-navbar__manual-mobile-child<?php echo self::is_current_manual_url( $child['url'] ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::manual_nav_url( $child['url'] ) ); ?>" <?php echo ! empty( $child['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $child_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?><span><?php echo esc_html( $child['label'] ); ?></span></a><?php endforeach; ?></div></li>
			<?php
			return;
		}
		if ( 'text' === $item['type'] ) {
			?>
			<li class="dh-navbar__menu-item dh-navbar__menu-item--mobile dh-navbar__menu-item--text"><span class="dh-navbar__drawer-link dh-navbar__text-item"><?php if ( $icon ) : ?><span class="dh-navbar__manual-item-icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?></span><?php endif; ?><span><?php echo esc_html( $label ); ?></span></span></li>
			<?php
			return;
		}

		?>
		<li class="dh-navbar__menu-item dh-navbar__menu-item--mobile"><a class="dh-navbar__drawer-link dh-navbar__drawer-link--mobile<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $active ? 'aria-current="page"' : ''; ?> <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicons. ?><span><?php echo esc_html( $label ); ?></span></a></li>
		<?php
	}

	/** Make saved site-relative URLs absolute on this WordPress install. */
	private static function manual_nav_url( $url ) {
		$url = trim( (string) $url );
		return $url && '/' === substr( $url, 0, 1 ) ? home_url( $url ) : $url;
	}

	/** Mark links active by matching local paths. */
	private static function is_current_manual_url( $url ) {
		$url = self::manual_nav_url( $url );
		if ( ! $url ) {
			return false;
		}
		$site_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$link_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $link_host && $site_host && $link_host !== $site_host ) {
			return false;
		}
		$current_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$current_path = wp_parse_url( $current_uri, PHP_URL_PATH );
		$link_path = wp_parse_url( $url, PHP_URL_PATH );
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$current_path = untrailingslashit( (string) $current_path );
		$expected_path = untrailingslashit( untrailingslashit( (string) $home_path ) . '/' . ltrim( (string) $link_path, '/' ) );
		return $current_path === $expected_path;
	}

	/** Render a desktop menu using native details/summary disclosure semantics. */
	private static function render_category_menu( $label, $view_all_url, $view_all_label, $items, $active, $id ) {
		?>
		<li class="dh-navbar__menu-item">
			<details class="dh-navbar__dropdown" data-dh-dropdown>
				<summary class="dh-navbar__top-link<?php echo $active ? ' is-active' : ''; ?>"><span><?php echo esc_html( $label ); ?></span><?php echo self::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></summary>
				<div class="dh-navbar__dropdown-panel" id="dh-navbar-menu-<?php echo esc_attr( $id ); ?>">
					<div class="dh-navbar__panel-head"><span><?php echo esc_html( sprintf( __( '%s categories', 'dixcoverhub-custom-ui' ), $label ) ); ?></span><a href="<?php echo esc_url( $view_all_url ); ?>"><?php echo esc_html( $view_all_label ); ?><?php echo self::icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a></div>
					<div class="dh-navbar__dropdown-grid"><?php foreach ( $items as $index => $item ) { self::render_category_card( $item, $index ); } ?></div>
				</div>
			</details>
		</li>
		<?php
	}

	/** Render one accessible taxonomy card. */
	private static function render_category_card( $item, $index, $mobile = false ) {
		$classes = 'dh-navbar__category-card dh-navbar__tone-' . ( absint( $index ) % 6 );
		if ( $mobile ) {
			$classes .= ' dh-navbar__category-card--mobile';
		}
		?>
		<a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $item['url'] ); ?>">
			<span class="dh-navbar__category-icon" aria-hidden="true"><?php echo self::icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="dh-navbar__category-copy"><span class="dh-navbar__category-name"><?php echo esc_html( $item['label'] ); ?></span><?php if ( $item['description'] ) : ?><span class="dh-navbar__category-description"><?php echo esc_html( $item['description'] ); ?></span><?php endif; ?></span>
			<?php if ( ! empty( self::options()['show_counts'] ) && $item['count'] > 0 ) : ?><span class="dh-navbar__count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></span><?php endif; ?>
		</a>
		<?php
	}

	/** Render a plain page link in desktop or drawer navigation. */
	private static function render_page_link( $label, $url, $active, $mobile = false ) {
		$classes = $mobile ? 'dh-navbar__drawer-link' : 'dh-navbar__top-link';
		if ( $mobile ) {
			$classes .= ' dh-navbar__drawer-link--mobile';
		}
		if ( $active ) {
			$classes .= ' is-active';
		}
		?>
		<li class="dh-navbar__menu-item<?php echo $mobile ? ' dh-navbar__menu-item--mobile' : ''; ?>"><a class="<?php echo esc_attr( $classes ); ?>" href="<?php echo esc_url( $url ); ?>"<?php echo $active ? ' aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute value. ?>><?php echo esc_html( $label ); ?></a></li>
		<?php
	}

	/** Render a mobile category accordion and its root archive link. */
	private static function render_mobile_category_group( $label, $root_url, $items, $active, $id ) {
		?>
		<section class="dh-navbar__drawer-group">
			<div class="dh-navbar__drawer-group-head">
				<a class="dh-navbar__drawer-group-link<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $root_url ); ?>"><?php echo 'jobs' === $id ? self::icon( 'briefcase' ) : self::icon( 'compass' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $label ); ?></span></a>
				<button class="dh-navbar__drawer-expand" type="button" data-dh-accordion aria-controls="dh-navbar-mobile-<?php echo esc_attr( $id ); ?>" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( __( 'Toggle %s categories', 'dixcoverhub-custom-ui' ), $label ) ); ?>"><?php echo self::icon( 'chevron' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</div>
			<div class="dh-navbar__drawer-categories" id="dh-navbar-mobile-<?php echo esc_attr( $id ); ?>" data-dh-accordion-panel hidden>
				<?php foreach ( $items as $index => $item ) { self::render_category_card( $item, $index, true ); } ?>
			</div>
		</section>
		<?php
	}

	/** Resolve the job menu from children of the configured WordPress term. */
	private static function get_jobs_items( $options ) {
		$items = array();
		$root  = get_term_by( 'slug', $options['jobs_parent_slug'], $options['taxonomy'] );
		if ( $root && ! is_wp_error( $root ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $options['taxonomy'],
					'parent'     => (int) $root->term_id,
					'hide_empty' => false,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => absint( $options['jobs_category_limit'] ),
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$items[] = self::term_item( $term );
				}
			}
		}

		if ( empty( $items ) ) {
			$items = self::legacy_jobs_fallback();
		}
		return $items;
	}

	/** Resolve selected opportunity categories, falling back to the legacy menu labels. */
	private static function get_opportunity_items( $options ) {
		$items    = array();
		$slugs    = array_filter( array_map( 'trim', explode( ',', $options['opportunity_term_slugs'] ) ) );
		$fallback = self::legacy_opportunity_fallback();
		$aliases  = array(
			'internships'           => array( 'internship' ),
			'scholarships'          => array( 'scholarship' ),
			'programs-calls'        => array( 'programme-call', 'program-call' ),
			'training-bootcamps'    => array( 'training-bootcamp' ),
			'competitions-challenges' => array( 'competition-challenge' ),
		);

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $options['taxonomy'] );
			if ( ! $term && isset( $aliases[ $slug ] ) ) {
				foreach ( $aliases[ $slug ] as $alias ) {
					$term = get_term_by( 'slug', $alias, $options['taxonomy'] );
					if ( $term ) {
						break;
					}
				}
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$items[] = self::term_item( $term );
			} elseif ( isset( $fallback[ $slug ] ) ) {
				$items[] = $fallback[ $slug ];
			}
		}

		return empty( $items ) ? array_values( $fallback ) : $items;
	}

	/** Convert a WordPress term to the compact structure used by the renderer. */
	private static function term_item( $term ) {
		$link = self::archive_aware_term_url( $term );
		return array(
			'label'       => $term->name,
			'url'         => $link,
			'description' => wp_trim_words( wp_strip_all_tags( $term->description ), 8, '' ),
			'count'       => (int) $term->count,
			'icon'        => self::icon_for_term( $term->slug ),
			'slug'        => $term->slug,
		);
	}

	/** Route category terms through the custom archive only while that feature is active. */
	private static function archive_aware_term_url( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return home_url( '/' );
		}
		$options = self::options();
		if ( 'category' === $term->taxonomy && ! empty( $options['archive_enabled'] ) ) {
			return add_query_arg( 'category', $term->slug, self::setting_url( '', 'opportunities' ) );
		}
		$link = get_term_link( $term );
		return is_wp_error( $link ) ? home_url( '/' ) : $link;
	}

	/** Reference categories are used only while a fresh local site has no terms yet. */
	private static function legacy_jobs_fallback() {
		$rows = array(
			array( 'Engineering & Tech', 'engineering-technology', 'Software, cloud and systems', 'code' ),
			array( 'Graduate Trainee', 'graduate-trainee-programs', 'Early career and corporate intake', 'study' ),
			array( 'Sales & Marketing', 'sales-marketing', 'Growth, business development and brand strategy', 'chart' ),
			array( 'Content & Media', 'content-editorial', 'Editorial, copy and journalism', 'document' ),
			array( 'Design & Creative', 'design-creative', 'UI/UX, visual and product design', 'design' ),
			array( 'Operations & HR', 'operations-hr', 'People, administration and logistics', 'network' ),
			array( 'Finance & Accounting', 'finance-accounting', 'Audit, advisory and fintech', 'money' ),
			array( 'Healthcare & Biotech', 'healthcare-medicine', 'Clinical, pharma and health technology', 'health' ),
		);
		return self::fallback_items( $rows );
	}

	/** Legacy labels remain useful as a preview before local terms are imported. */
	private static function legacy_opportunity_fallback() {
		$rows = array(
			'internships' => array( 'Internships', 'internship', 'Paid student and graduate attachments', 'compass' ),
			'scholarships' => array( 'Scholarships', 'scholarship', 'Undergraduate, Masters and PhD awards', 'study' ),
			'programs-calls' => array( 'Programmes & Calls', 'programme-call', 'Accelerators, incubators and initiatives', 'rocket' ),
			'training-bootcamps' => array( 'Training & Bootcamps', 'training-bootcamp', 'Technical academies and skill bootcamps', 'document' ),
			'competitions-challenges' => array( 'Competitions', 'competition-challenge', 'Hackathons, awards and pitch events', 'award' ),
		);
		$items = array();
		foreach ( $rows as $key => $row ) {
			$item = self::fallback_items( array( $row ) )[0];
			$items[ $key ] = $item;
		}
		return $items;
	}

	/** Build one or more legacy preview items that route through the opportunity archive. */
	private static function fallback_items( $rows ) {
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'label'       => $row[0],
				'url'         => add_query_arg( 'category', $row[1], self::setting_url( '', 'opportunities' ) ),
				'description' => $row[2],
				'count'       => 0,
				'icon'        => $row[3],
				'slug'        => $row[1],
			);
		}
		return $items;
	}

	/** Select a simple icon from a term's slug. */
	private static function icon_for_term( $slug ) {
		$slug = strtolower( (string) $slug );
		if ( preg_match( '/(engineer|software|frontend|backend|data|cyber|cloud|tech|it)/', $slug ) ) {
			return 'code';
		}
		if ( preg_match( '/(scholar|intern|graduate|fellow|education)/', $slug ) ) {
			return 'study';
		}
		if ( preg_match( '/(sales|marketing|business|finance|account)/', $slug ) ) {
			return 'chart';
		}
		if ( preg_match( '/(content|media|editor|writing|training)/', $slug ) ) {
			return 'document';
		}
		if ( preg_match( '/(design|creative|art)/', $slug ) ) {
			return 'design';
		}
		if ( preg_match( '/(health|medical|biotech)/', $slug ) ) {
			return 'health';
		}
		return 'tag';
	}

	/** Resolve the Jobs archive or its configured category term. */
	private static function jobs_url( $options ) {
		$term = get_term_by( 'slug', $options['jobs_parent_slug'], $options['taxonomy'] );
		if ( $term && ! is_wp_error( $term ) ) {
			return self::archive_aware_term_url( $term );
		}
		return add_query_arg( 'category', 'job', self::setting_url( '', 'opportunities' ) );
	}

	/** Use a supplied URL, an existing page, or a predictable local page path. */
	private static function setting_url( $value, $page_slug ) {
		if ( $value ) {
			return 0 === strpos( $value, '/' ) ? home_url( $value ) : $value;
		}
		$page = get_page_by_path( $page_slug );
		return $page ? get_permalink( $page ) : home_url( '/' . trim( $page_slug, '/' ) . '/' );
	}

	/** Prefer the site's public member login page, then WordPress's login URL. */
	private static function login_url( $options ) {
		if ( $options['login_url'] ) {
			return self::setting_url( $options['login_url'], 'login' );
		}
		$page = get_page_by_path( 'login' );
		return $page ? get_permalink( $page ) : wp_login_url();
	}

	/** Render the selected WordPress menu after the fixed links. */
	private static function render_extra_menu( $menu_id, $context ) {
		if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
			return '';
		}
		$class = 'desktop' === $context ? 'dh-navbar__extra-menu' : 'dh-navbar__drawer-extra-menu';
		return (string) wp_nav_menu(
			array(
				'menu'        => (int) $menu_id,
				'container'   => false,
				'menu_class'  => $class,
				'menu_id'     => 'dh-navbar-' . $context . '-extra-menu',
				'depth'       => 1,
				'fallback_cb' => false,
				'echo'        => false,
			)
		);
	}

	/** Determine which main section is active for the current request. */
	private static function current_navigation_state( $options ) {
		$state = array(
			'jobs'          => false,
			'opportunities' => false,
			'deadlines'     => is_page( 'deadlines' ),
			'about'         => is_page( 'about' ),
			'contact'       => is_page( 'contact' ),
		);
		$current = get_queried_object();
		if ( $current instanceof WP_Term && $current->taxonomy === $options['taxonomy'] ) {
			$root = get_term_by( 'slug', $options['jobs_parent_slug'], $options['taxonomy'] );
			$ancestors = get_ancestors( (int) $current->term_id, $options['taxonomy'], 'taxonomy' );
			$state['jobs'] = $root && ( (int) $root->term_id === (int) $current->term_id || in_array( (int) $root->term_id, array_map( 'intval', $ancestors ), true ) );
			$state['opportunities'] = ! $state['jobs'];
		}
		if ( is_page( 'opportunities' ) || is_post_type_archive( 'opportunity' ) ) {
			$state['opportunities'] = true;
		}
		if ( isset( $_GET['category'] ) ) {
			$query_category = sanitize_title( wp_unslash( $_GET['category'] ) );
			$query_term = $query_category ? get_term_by( 'slug', $query_category, $options['taxonomy'] ) : false;
			$query_is_job = $query_term && ! is_wp_error( $query_term ) && self::is_job_term( $query_term, $options );
			if ( 'job' === $query_category || 'jobs' === $query_category || $query_is_job || ( $current instanceof WP_Term && self::is_job_term( $current, $options ) ) ) {
				$state['jobs'] = true;
				$state['opportunities'] = false;
			} elseif ( $query_category ) {
				$state['opportunities'] = true;
			}
		}
		return $state;
	}

	/** Check whether a term belongs to the configured Jobs branch. */
	private static function is_job_term( $term, $options ) {
		$root = get_term_by( 'slug', $options['jobs_parent_slug'], $options['taxonomy'] );
		if ( ! $root ) {
			return false;
		}
		$ancestors = get_ancestors( (int) $term->term_id, $options['taxonomy'], 'taxonomy' );
		return (int) $term->term_id === (int) $root->term_id || in_array( (int) $root->term_id, array_map( 'intval', $ancestors ), true );
	}

	/** Render the selected Hugeicons free icon inline for the public navbar. */
	private static function icon( $name ) {
		$icons = array(
			'briefcase' => 'Briefcase01Icon',
			'compass'   => 'Compass01Icon',
			'chevron'   => 'ArrowDown01Icon',
			'arrow'     => 'ArrowRight01Icon',
			'external'  => 'ArrowUpRight01Icon',
			'menu'      => 'Menu01Icon',
			'close'     => 'Cancel01Icon',
			'code'      => 'CodeIcon',
			'study'     => 'GraduationCapIcon',
			'chart'     => 'BarChartIcon',
			'document'  => 'File01Icon',
			'design'    => 'PaintbrushIcon',
			'network'   => 'NetworkIcon',
			'money'     => 'Money01Icon',
			'health'    => 'HealthIcon',
			'rocket'    => 'Rocket01Icon',
			'award'     => 'Award01Icon',
			'tag'       => 'DiscountTag01Icon',
		);
		$name = isset( $icons[ $name ] ) ? $icons[ $name ] : 'DiscountTag01Icon';
		return DixcoverHub_Custom_UI_Icons::svg( $name, 'dh-navbar__icon' );
	}
}
