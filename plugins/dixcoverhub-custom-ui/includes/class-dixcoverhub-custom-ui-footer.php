<?php
/**
 * Configurable public footer for DixcoverHub.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Footer {
	/** Prevent automatic and shortcode output from rendering twice. */
	private static $rendered = false;

	/** Register footer hooks. */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'maybe_replace_theme_footer' ), 30 );
		add_action( 'astra_footer', array( __CLASS__, 'maybe_replace_footer_during_render' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_automatic_footer' ), 8 );
		add_shortcode( 'dixcoverhub_footer', array( __CLASS__, 'render_shortcode' ) );
		add_filter( 'render_block', array( __CLASS__, 'maybe_replace_block_footer' ), 10, 2 );
	}

	/** Remove the built-in Astra footer when the replacement option is active. */
	public static function maybe_replace_theme_footer() {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['footer_enabled'] ) || empty( $options['footer_replace_theme'] ) || ! self::matches_placement( $options ) ) {
			return;
		}

		if ( function_exists( 'astra_footer_markup' ) ) {
			remove_action( 'astra_footer', 'astra_footer_markup' );
		}
		self::remove_astra_builder_footer_markup();
	}

	/** Remove Astra's footer builder callback immediately before the theme prints it. */
	public static function maybe_replace_footer_during_render() {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['footer_enabled'] ) || empty( $options['footer_replace_theme'] ) || ! self::matches_placement( $options ) ) {
			return;
		}
		self::remove_astra_builder_footer_markup();
	}

	/** Remove the Astra Builder instance callback while leaving its mobile overlays alone. */
	private static function remove_astra_builder_footer_markup() {
		global $wp_filter;
		if ( empty( $wp_filter['astra_footer'] ) || empty( $wp_filter['astra_footer']->callbacks ) ) {
			return;
		}
		foreach ( $wp_filter['astra_footer']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $registered_callback ) {
				$callback = $registered_callback['function'] ?? null;
				if ( ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! is_object( $callback[0] ) ) {
					continue;
				}
				if ( is_a( $callback[0], 'Astra_Builder_Footer' ) && 'footer_markup' === $callback[1] ) {
					remove_action( 'astra_footer', $callback, (int) $priority );
				}
			}
		}
	}

	/** Omit footer template-part blocks in block themes when replacement is enabled. */
	public static function maybe_replace_block_footer( $content, $block ) {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['footer_enabled'] ) || empty( $options['footer_replace_theme'] ) || ! self::matches_placement( $options ) ) {
			return $content;
		}
		if ( ! is_array( $block ) || 'core/template-part' !== ( $block['blockName'] ?? '' ) ) {
			return $content;
		}
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$area  = sanitize_key( $attrs['area'] ?? '' );
		$slug  = sanitize_title( $attrs['slug'] ?? '' );
		return ( 'footer' === $area || 'footer' === $slug ) ? '' : $content;
	}

	/** Enqueue the isolated footer stylesheet and saved palette. */
	public static function enqueue_assets() {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['footer_enabled'] ) || ! self::matches_placement( $options ) ) {
			return;
		}
		wp_enqueue_style( 'dixcoverhub-custom-footer', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/footer.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
		$style = sprintf(
			'.dh-custom-footer{--dh-footer-bg:%1$s;--dh-footer-text:%2$s;--dh-footer-muted:%3$s;--dh-footer-link:%4$s;--dh-footer-border:%5$s;--dh-footer-width:%6$dpx}.dh-custom-footer__banner{--dh-footer-banner-start:%7$s;--dh-footer-banner-end:%8$s;--dh-footer-banner-text:%9$s;--dh-footer-banner-badge-bg:%10$s;--dh-footer-banner-badge-text:%11$s;--dh-footer-banner-button-bg:%12$s;--dh-footer-banner-button-text:%13$s;--dh-footer-banner-secondary-border:%14$s;--dh-footer-banner-radius:%15$dpx}',
			esc_attr( $options['footer_background_color'] ),
			esc_attr( $options['footer_text_color'] ),
			esc_attr( $options['footer_muted_color'] ),
			esc_attr( $options['footer_link_color'] ),
			esc_attr( $options['footer_border_color'] ),
			absint( $options['footer_content_width'] ),
			esc_attr( $options['footer_banner_start_color'] ),
			esc_attr( $options['footer_banner_end_color'] ),
			esc_attr( $options['footer_banner_text_color'] ),
			esc_attr( $options['footer_banner_badge_bg_color'] ),
			esc_attr( $options['footer_banner_badge_text_color'] ),
			esc_attr( $options['footer_banner_button_bg_color'] ),
			esc_attr( $options['footer_banner_button_text_color'] ),
			esc_attr( $options['footer_banner_secondary_border_color'] ),
			absint( $options['footer_banner_radius'] )
		);
		wp_add_inline_style( 'dixcoverhub-custom-footer', $style );
	}

	/** Print the automatically placed footer once near the end of the page. */
	public static function render_automatic_footer() {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['footer_enabled'] ) || empty( $options['footer_automatic'] ) || self::$rendered || is_admin() || ! self::matches_placement( $options ) ) {
			return;
		}
		self::$rendered = true;
		echo self::render( $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes all saved values.
	}

	/** Render the shortcode output. */
	public static function render_shortcode() {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['footer_enabled'] ) || self::$rendered || ! self::matches_placement( $options ) ) {
			return '';
		}
		self::$rendered = true;
		return self::render( $options );
	}

	/** Build the public footer from the Studio configuration. */
	private static function render( $options ) {
		$site_name = get_bloginfo( 'name' );
		$logo_url  = trim( (string) $options['footer_logo_url'] );
		if ( ! $logo_url && ! empty( $options['logo_enabled'] ) ) {
			$logo_url = trim( (string) $options['logo_url'] );
		}
		if ( ! $logo_url ) {
			$custom_logo = get_theme_mod( 'custom_logo' );
			$logo_url = $custom_logo ? (string) wp_get_attachment_image_url( $custom_logo, 'full' ) : '';
		}
		$columns = is_array( $options['footer_columns'] ) ? $options['footer_columns'] : array();
		$socials = is_array( $options['footer_social_links'] ) ? $options['footer_social_links'] : array();
		$legal_links = is_array( $options['footer_legal_links'] ) ? $options['footer_legal_links'] : array();
		$copyright = str_replace( array( '%year%', '%site_name%' ), array( wp_date( 'Y' ), $site_name ), (string) $options['footer_copyright'] );
		$footer_logo_alt = trim( (string) $options['footer_logo_alt'] );
		if ( '' === $footer_logo_alt ) {
			$footer_logo_alt = $site_name;
		}
		ob_start();
		?>
		<footer class="dh-custom-footer" role="contentinfo">
			<div class="dh-custom-footer__inner">
				<?php if ( ! empty( $options['footer_banner_enabled'] ) ) : ?>
					<section class="dh-custom-footer__banner" aria-label="<?php esc_attr_e( 'Featured action', 'dixcoverhub-custom-ui' ); ?>">
						<div class="dh-custom-footer__banner-copy">
							<?php if ( $options['footer_banner_badge'] ) : ?><p class="dh-custom-footer__banner-badge"><?php echo esc_html( $options['footer_banner_badge'] ); ?></p><?php endif; ?>
							<?php if ( $options['footer_banner_heading'] ) : ?><h2><?php echo esc_html( $options['footer_banner_heading'] ); ?></h2><?php endif; ?>
						</div>
						<div class="dh-custom-footer__banner-actions">
							<?php if ( $options['footer_banner_primary_label'] && $options['footer_banner_primary_url'] ) : ?><a class="dh-custom-footer__banner-button dh-custom-footer__banner-button--primary" href="<?php echo esc_url( self::resolve_url( $options['footer_banner_primary_url'] ) ); ?>" <?php echo ! empty( $options['footer_banner_primary_new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $options['footer_banner_primary_label'] ); ?><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-custom-footer__banner-arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled sanitized Hugeicons SVG. ?></a><?php endif; ?>
							<?php if ( $options['footer_banner_secondary_label'] && $options['footer_banner_secondary_url'] ) : ?><a class="dh-custom-footer__banner-button dh-custom-footer__banner-button--secondary" href="<?php echo esc_url( self::resolve_url( $options['footer_banner_secondary_url'] ) ); ?>" <?php echo ! empty( $options['footer_banner_secondary_new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $options['footer_banner_secondary_label'] ); ?><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-custom-footer__banner-arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled sanitized Hugeicons SVG. ?></a><?php endif; ?>
						</div>
					</section>
				<?php endif; ?>
				<div class="dh-custom-footer__main">
					<?php if ( ! empty( $options['footer_show_brand'] ) ) : ?>
						<div class="dh-custom-footer__brand">
							<a class="dh-custom-footer__brand-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $site_name ); ?> home">
								<?php if ( $logo_url ) : ?><img src="<?php echo esc_url( self::resolve_url( $logo_url ) ); ?>" alt="<?php echo esc_attr( $footer_logo_alt ); ?>" loading="lazy" /><?php endif; ?>
								<?php if ( ! $logo_url || ! empty( $options['footer_show_wordmark'] ) ) : ?><span><?php echo esc_html( $site_name ); ?></span><?php endif; ?>
							</a>
							<?php if ( $options['footer_description'] ) : ?><p><?php echo esc_html( $options['footer_description'] ); ?></p><?php endif; ?>
							<?php if ( ! empty( $options['footer_cta_enabled'] ) && $options['footer_cta_url'] ) : ?><a class="dh-custom-footer__cta" href="<?php echo esc_url( self::resolve_url( $options['footer_cta_url'] ) ); ?>"><?php echo esc_html( $options['footer_cta_label'] ); ?></a><?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( $columns ) : ?>
						<div class="dh-custom-footer__columns">
							<?php foreach ( $columns as $column ) : if ( ! is_array( $column ) ) { continue; } ?>
								<section class="dh-custom-footer__column">
									<?php if ( ! empty( $column['heading'] ) ) : ?><h2><?php echo esc_html( $column['heading'] ); ?></h2><?php endif; ?>
									<?php if ( ! empty( $column['description'] ) ) : ?><p class="dh-custom-footer__column-description"><?php echo esc_html( $column['description'] ); ?></p><?php endif; ?>
								<?php self::render_column_items( $column ); ?>
								</section>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $options['footer_show_social_links'] ) && $socials ) : ?>
						<nav class="dh-custom-footer__social" aria-label="<?php esc_attr_e( 'Social links', 'dixcoverhub-custom-ui' ); ?>"><ul><?php foreach ( $socials as $social ) : if ( empty( $social['label'] ) || empty( $social['url'] ) ) { continue; } $icon = ! empty( $social['icon'] ) ? DixcoverHub_Custom_UI_Icons::svg( $social['icon'], 'dh-custom-footer__social-icon' ) : ''; ?><li><a href="<?php echo esc_url( self::resolve_url( $social['url'] ) ); ?>" aria-label="<?php echo esc_attr( $social['label'] ); ?>" <?php echo ! empty( $social['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php if ( $icon ) : ?><span aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled sanitized Hugeicons SVG. ?></span><?php else : ?><span><?php echo esc_html( $social['label'] ); ?></span><?php endif; ?></a></li><?php endforeach; ?></ul></nav>
					<?php endif; ?>
				</div>
				<div class="dh-custom-footer__bottom"><p><?php echo esc_html( $copyright ); ?></p><?php if ( $legal_links ) : ?><nav class="dh-custom-footer__legal" aria-label="<?php esc_attr_e( 'Legal links', 'dixcoverhub-custom-ui' ); ?>"><ul><?php foreach ( $legal_links as $link ) : if ( empty( $link['label'] ) || empty( $link['url'] ) ) { continue; } ?><li><a href="<?php echo esc_url( self::resolve_url( $link['url'] ) ); ?>" <?php echo ! empty( $link['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $link['label'] ); ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?><?php if ( ! empty( $options['footer_secondary_text'] ) ) : ?><span class="dh-custom-footer__secondary"><?php echo esc_html( $options['footer_secondary_text'] ); ?></span><?php endif; ?></div>
			</div>
		</footer>
		<?php
		return (string) ob_get_clean();
	}

	/** Render configurable text, link, image, divider, and social blocks. */
	private static function render_column_items( $column ) {
		$items = isset( $column['items'] ) && is_array( $column['items'] ) ? $column['items'] : array();
		if ( ! $items && ! empty( $column['links'] ) && is_array( $column['links'] ) ) {
			$items = array_map( static function ( $link ) { return array_merge( (array) $link, array( 'type' => 'link' ) ); }, $column['links'] );
		}
		if ( ! $items ) {
			return;
		}
		?><div class="dh-custom-footer__items"><?php foreach ( $items as $item ) : if ( ! is_array( $item ) ) { continue; } $type = isset( $item['type'] ) ? $item['type'] : 'link';
			if ( 'divider' === $type ) : ?><hr class="dh-custom-footer__item-divider" /><?php
			elseif ( 'text' === $type && ! empty( $item['text'] ) ) : ?><p class="dh-custom-footer__item-text"><?php echo nl2br( esc_html( $item['text'] ) ); ?></p><?php
			elseif ( 'logo' === $type && ! empty( $item['image_url'] ) ) : $image = '<img src="' . esc_url( self::resolve_url( $item['image_url'] ) ) . '" alt="' . esc_attr( $item['alt'] ?? '' ) . '" loading="lazy" />'; if ( ! empty( $item['url'] ) ) : ?><a class="dh-custom-footer__item-logo" href="<?php echo esc_url( self::resolve_url( $item['url'] ) ); ?>" <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL and alt are escaped above. ?></a><?php else : ?><span class="dh-custom-footer__item-logo"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL and alt are escaped above. ?></span><?php endif; ?><?php
			elseif ( in_array( $type, array( 'link', 'social' ), true ) && ! empty( $item['label'] ) && ! empty( $item['url'] ) ) : $icon = ! empty( $item['icon'] ) ? DixcoverHub_Custom_UI_Icons::svg( $item['icon'], 'dh-custom-footer__link-icon' ) : ''; ?><a class="dh-custom-footer__item-link<?php echo 'social' === $type ? ' is-social' : ''; ?>" href="<?php echo esc_url( self::resolve_url( $item['url'] ) ); ?>" <?php echo ! empty( $item['new_tab'] ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php if ( $icon ) : ?><span class="dh-custom-footer__icon" aria-hidden="true"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled sanitized Hugeicons SVG. ?></span><?php endif; ?><span><?php echo esc_html( $item['label'] ); ?></span></a><?php endif; ?><?php endforeach; ?></div><?php
	}

	/** Check whether the saved page rules include the current public route. */
	private static function matches_placement( $options ) {
		$placements = isset( $options['footer_placements'] ) && is_array( $options['footer_placements'] ) ? $options['footer_placements'] : array( 'global' );
		if ( in_array( 'global', $placements, true ) ) {
			return true;
		}
		if ( in_array( 'home', $placements, true ) && ( is_front_page() || is_home() ) ) {
			return true;
		}
		$is_opportunities = (bool) get_query_var( 'dh_opportunity_archive' ) || is_page( 'opportunities' );
		if ( in_array( 'opportunities', $placements, true ) && $is_opportunities ) {
			return true;
		}
		if ( in_array( 'posts', $placements, true ) && is_singular( 'post' ) ) {
			return true;
		}
		if ( in_array( 'pages', $placements, true ) && is_page() && ! $is_opportunities ) {
			return true;
		}
		if ( in_array( 'path', $placements, true ) ) {
			$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
			$current_path = '/' . trim( rawurldecode( is_string( $request_path ) ? $request_path : '' ), '/' );
			$site_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
			if ( $site_path && '/' !== $site_path && ( $current_path === $site_path || 0 === strpos( $current_path, $site_path . '/' ) ) ) {
				$current_path = substr( $current_path, strlen( $site_path ) );
				$current_path = $current_path ? $current_path : '/';
			}
			$patterns = preg_split( '/\r\n|\r|\n/', (string) ( $options['footer_path_patterns'] ?? '' ) );
			foreach ( $patterns as $pattern ) {
				$pattern = trim( (string) $pattern );
				if ( '' === $pattern ) { continue; }
				if ( substr( $pattern, -2 ) === '/*' ) {
					$prefix = rtrim( substr( $pattern, 0, -1 ), '/' );
					if ( $current_path === $prefix || 0 === strpos( $current_path, $prefix . '/' ) ) { return true; }
				} elseif ( untrailingslashit( $current_path ) === untrailingslashit( $pattern ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Resolve same-site relative destinations. */
	private static function resolve_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#^(?:https?:)?//#i', $url ) || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, '#' ) ) {
			return $url;
		}
		return home_url( '/' . ltrim( $url, '/' ) );
	}
}
