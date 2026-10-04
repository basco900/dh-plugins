<?php
/**
 * Custom font upload, assignment, and front-end delivery.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Fonts {
	const FONTS_OPTION   = 'dixcoverhub_custom_ui_fonts';
	const SETTINGS_OPTION = 'dixcoverhub_custom_ui_font_settings';
	const FONT_DIRECTORY = 'dixcoverhub-fonts';
	const MAX_FILE_BYTES = 10485760;

	/** Register font administration and front-end output. */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_admin_font_faces' ) );
		add_action( 'admin_post_dixcoverhub_upload_font', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_dixcoverhub_delete_font', array( __CLASS__, 'handle_delete' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_frontend_css' ), 30 );
	}

	/** Load the local font converter only in the Fonts workspace. */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'appearance_page_dixcoverhub-custom-ui' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'dixcoverhub-fonts-admin', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/fonts-admin.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		wp_localize_script( 'dixcoverhub-fonts-admin', 'DixcoverHubFontOptimizer', array(
			'workerUrl'   => DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/font-optimizer-worker.js',
			'maxFileBytes' => self::MAX_FILE_BYTES,
		) );
	}

	/** Defaults for the site-wide typography roles. */
	public static function defaults() {
		return array(
			'body_family'      => '',
			'body_weight'      => 400,
			'heading_family'   => '',
			'heading_weight'   => 700,
			'interface_family' => '',
			'interface_weight' => 500,
			'small_family'     => '',
			'small_weight'     => 400,
		);
	}

	/** Register typography settings through the WordPress settings API. */
	public static function register_settings() {
		register_setting(
			'dixcoverhub_custom_ui_font_settings_group',
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	/** Sanitize family assignments and requested weights. */
	public static function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$defaults = self::defaults();
		$families = self::families();
		$output   = array();

		foreach ( array( 'body', 'heading', 'interface', 'small' ) as $role ) {
			$family_key = $role . '_family';
			$weight_key = $role . '_weight';
			$family     = isset( $input[ $family_key ] ) && is_scalar( $input[ $family_key ] ) ? sanitize_key( $input[ $family_key ] ) : '';
			$output[ $family_key ] = isset( $families[ $family ] ) ? $family : '';

			$weight = isset( $input[ $weight_key ] ) ? absint( $input[ $weight_key ] ) : $defaults[ $weight_key ];
			$output[ $weight_key ] = in_array( $weight, array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ), true ) ? $weight : $defaults[ $weight_key ];
		}

		return $output;
	}

	/** Render the font workspace inside the Custom UI studio. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$fonts    = self::fonts();
		$families = self::families();
		$settings = self::settings();
		$ui_options = DixcoverHub_Custom_UI::options();
		$message  = isset( $_GET['dh_font_result'] ) ? sanitize_key( wp_unslash( $_GET['dh_font_result'] ) ) : '';
		?>
		<div class="dh-ui-section-heading">
			<div><p class="dh-ui-eyebrow"><?php esc_html_e( 'TYPE SYSTEM', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Fonts', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Upload web fonts once, then assign them to the parts of your site that need them.', 'dixcoverhub-custom-ui' ); ?></p></div>
		</div>
		<form class="dh-ui-feature-opt-in <?php echo ! empty( $ui_options['fonts_enabled'] ) ? 'is-active' : 'is-inactive'; ?>" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<div><strong><?php esc_html_e( 'Use these fonts on the public site', 'dixcoverhub-custom-ui' ); ?></strong><p><?php esc_html_e( 'Your font files and role assignments stay saved while this is off. Turn it on when you are ready to apply them.', 'dixcoverhub-custom-ui' ); ?></p></div>
			<input type="hidden" name="<?php echo esc_attr( DixcoverHub_Custom_UI::OPTION_KEY ); ?>[fonts_enabled]" value="0" />
			<label><input type="checkbox" name="<?php echo esc_attr( DixcoverHub_Custom_UI::OPTION_KEY ); ?>[fonts_enabled]" value="1" <?php checked( ! empty( $ui_options['fonts_enabled'] ) ); ?> /> <span><?php echo ! empty( $ui_options['fonts_enabled'] ) ? esc_html__( 'Active', 'dixcoverhub-custom-ui' ) : esc_html__( 'Off', 'dixcoverhub-custom-ui' ); ?></span></label>
			<?php submit_button( __( 'Save', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?>
		</form>
		<?php if ( 'uploaded' === $message ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Font added to your library.', 'dixcoverhub-custom-ui' ); ?></p></div><?php endif; ?>
		<?php if ( 'deleted' === $message ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Font removed from your library.', 'dixcoverhub-custom-ui' ); ?></p></div><?php endif; ?>
		<?php if ( 'invalid' === $message ) : ?><div class="notice notice-error"><p><?php esc_html_e( 'That file could not be added. Use a valid WOFF2 or WOFF font under 10 MB. TrueType and OpenType files must be converted in your browser before upload.', 'dixcoverhub-custom-ui' ); ?></p></div><?php endif; ?>

		<div class="dh-ui-font-grid">
			<section class="dh-ui-card">
				<div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'FONT LIBRARY', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Add a font face', 'dixcoverhub-custom-ui' ); ?></h3></div><span class="dh-ui-badge"><?php esc_html_e( 'WOFF2 recommended', 'dixcoverhub-custom-ui' ); ?></span></div>
				<p class="description"><?php esc_html_e( 'WOFF2 is the most compact web font format. Choose WOFF2 directly, or add a TTF/OTF file and it will be converted to WOFF2 in this browser before upload. WOFF is accepted as a fallback. The original TTF/OTF file is never sent to another service.', 'dixcoverhub-custom-ui' ); ?></p>
				<form class="dh-ui-upload-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="dixcoverhub_upload_font" />
					<?php wp_nonce_field( 'dixcoverhub_upload_font' ); ?>
					<label class="dh-ui-field"><span><?php esc_html_e( 'Font family name', 'dixcoverhub-custom-ui' ); ?></span><input type="text" name="font_family" required maxlength="80" placeholder="e.g. Inter" /></label>
					<label class="dh-ui-field"><span><?php esc_html_e( 'Font file', 'dixcoverhub-custom-ui' ); ?></span><input type="file" name="font_file" accept=".woff2,.woff,.ttf,.otf,font/woff2,font/woff,font/ttf,font/otf" required /><small><?php esc_html_e( 'Maximum source size: 10 MB. TTF/OTF files are converted locally to WOFF2 before WordPress receives them.', 'dixcoverhub-custom-ui' ); ?></small></label><span class="dh-ui-font-optimization-status" data-dh-font-optimization role="status" aria-live="polite" hidden></span>
					<p class="dh-ui-note"><?php esc_html_e( 'Use the same family name for each Regular, Medium, Semibold, or Bold file. A TTF/OTF weight axis is detected during conversion; WOFF2 variable files can be marked manually when the file contains a real weight axis.', 'dixcoverhub-custom-ui' ); ?></p>
					<div class="dh-ui-field-row">
						<label class="dh-ui-field"><span><?php esc_html_e( 'Face type', 'dixcoverhub-custom-ui' ); ?></span><select name="face_type" data-dh-font-face-type><option value="static" selected="selected"><?php esc_html_e( 'Single weight', 'dixcoverhub-custom-ui' ); ?></option><option value="variable"><?php esc_html_e( 'Variable font', 'dixcoverhub-custom-ui' ); ?></option></select></label>
						<label class="dh-ui-field"><span><?php esc_html_e( 'Style', 'dixcoverhub-custom-ui' ); ?></span><select name="font_style"><option value="normal"><?php esc_html_e( 'Normal', 'dixcoverhub-custom-ui' ); ?></option><option value="italic"><?php esc_html_e( 'Italic', 'dixcoverhub-custom-ui' ); ?></option></select></label>
					</div>
					<div class="dh-ui-field-row" data-dh-font-variable-fields>
						<label class="dh-ui-field"><span><?php esc_html_e( 'Minimum weight', 'dixcoverhub-custom-ui' ); ?></span><select name="weight_min"><?php self::weight_options( 100 ); ?></select></label>
						<label class="dh-ui-field"><span><?php esc_html_e( 'Maximum weight', 'dixcoverhub-custom-ui' ); ?></span><select name="weight_max"><?php self::weight_options( 900 ); ?></select></label>
					</div>
					<div class="dh-ui-field-row" data-dh-font-static-fields hidden>
						<label class="dh-ui-field"><span><?php esc_html_e( 'Weight', 'dixcoverhub-custom-ui' ); ?></span><select name="weight_static"><?php self::weight_options( 400 ); ?></select></label>
					</div>
					<button class="button button-primary dh-ui-primary-button" type="submit"><?php esc_html_e( 'Add font', 'dixcoverhub-custom-ui' ); ?></button>
				</form>
			</section>

			<section class="dh-ui-card dh-ui-font-library">
				<div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'YOUR FONTS', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Font library', 'dixcoverhub-custom-ui' ); ?></h3></div><span class="dh-ui-count"><?php echo esc_html( (string) count( $fonts ) ); ?></span></div>
				<?php if ( empty( $fonts ) ) : ?>
					<div class="dh-ui-empty-state"><span class="dh-ui-empty-icon" aria-hidden="true"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'TextFontIcon', 'dh-ui-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated from bundled, allow-listed Hugeicons data. ?></span><strong><?php esc_html_e( 'Your library is ready', 'dixcoverhub-custom-ui' ); ?></strong><p><?php esc_html_e( 'Add a WOFF2 face or convert a TTF/OTF face in your browser.', 'dixcoverhub-custom-ui' ); ?></p></div>
				<?php else : ?>
					<div class="dh-ui-font-list">
						<?php foreach ( $fonts as $font ) : ?>
							<div class="dh-ui-font-row">
								<div class="dh-ui-font-preview"><span style="font-family:<?php echo esc_attr( self::family_key( $font['family'] ) ); ?>,sans-serif">Aa</span></div>
								<div class="dh-ui-font-info"><strong><?php echo esc_html( $font['family'] ); ?></strong><span><?php echo esc_html( 'variable' === $font['type'] ? $font['weight'] . ' variable' : $font['weight'] . ' weight' ); ?> · <?php echo esc_html( strtoupper( $font['style'] ) ); ?> · <?php echo esc_html( strtoupper( $font['format'] ) ); ?> · <?php echo esc_html( size_format( (int) $font['size'] ) ); ?></span></div>
								<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js( __( 'Remove this font file?', 'dixcoverhub-custom-ui' ) ); ?>');">
									<input type="hidden" name="action" value="dixcoverhub_delete_font" /><input type="hidden" name="font_id" value="<?php echo esc_attr( $font['id'] ); ?>" />
									<?php wp_nonce_field( 'dixcoverhub_delete_font_' . $font['id'] ); ?>
									<button class="button-link-delete" type="submit"><?php esc_html_e( 'Remove', 'dixcoverhub-custom-ui' ); ?></button>
								</form>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>
		</div>

		<section class="dh-ui-card dh-ui-type-settings">
			<div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'TYPOGRAPHY ROLES', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Assign fonts to your site', 'dixcoverhub-custom-ui' ); ?></h3></div></div>
			<form action="options.php" method="post">
				<?php settings_fields( 'dixcoverhub_custom_ui_font_settings_group' ); ?>
				<div class="dh-ui-role-grid">
					<?php self::role_fields( $families, $settings, 'body', __( 'Body text', 'dixcoverhub-custom-ui' ), 400 ); ?>
					<?php self::role_fields( $families, $settings, 'heading', __( 'Headings', 'dixcoverhub-custom-ui' ), 700 ); ?>
					<?php self::role_fields( $families, $settings, 'interface', __( 'Navigation and buttons', 'dixcoverhub-custom-ui' ), 500 ); ?>
					<?php self::role_fields( $families, $settings, 'small', __( 'Small text and captions', 'dixcoverhub-custom-ui' ), 400 ); ?>
				</div>
				<p class="dh-ui-note"><?php esc_html_e( 'Variable font files declare their real weight range. Each role disables synthetic bold and italic so the browser uses uploaded font faces instead of drawing a blurred imitation.', 'dixcoverhub-custom-ui' ); ?></p>
				<?php submit_button( __( 'Save typography', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?>
			</form>
		</section>
		<?php
	}

	/** Handle a validated web-font upload. TTF/OTF inputs arrive as WOFF2 from the browser converter. */
	public static function handle_upload() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to upload fonts.', 'dixcoverhub-custom-ui' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'dixcoverhub_upload_font' );

		$file   = isset( $_FILES['font_file'] ) ? $_FILES['font_file'] : array();
		$family = isset( $_POST['font_family'] ) ? sanitize_text_field( wp_unslash( $_POST['font_family'] ) ) : '';
		$ext    = isset( $file['name'] ) ? strtolower( pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_EXTENSION ) ) : '';
		$headers = array( 'woff2' => 'wOF2', 'woff' => 'wOFF' );

		if ( empty( $file['tmp_name'] ) || empty( $file['size'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) || ! isset( $headers[ $ext ] ) || (int) $file['size'] > self::MAX_FILE_BYTES || '' === $family ) {
			self::redirect( 'invalid' );
		}

		$handle = fopen( $file['tmp_name'], 'rb' );
		$magic  = $handle ? fread( $handle, 4 ) : '';
		if ( $handle ) {
			fclose( $handle );
		}
		if ( $magic !== $headers[ $ext ] ) {
			self::redirect( 'invalid' );
		}

		$type_raw = isset( $_POST['face_type'] ) ? sanitize_key( wp_unslash( $_POST['face_type'] ) ) : 'static';
		$type = 'variable' === $type_raw ? 'variable' : 'static';
		$style = isset( $_POST['font_style'] ) && 'italic' === sanitize_key( wp_unslash( $_POST['font_style'] ) ) ? 'italic' : 'normal';
		if ( 'variable' === $type ) {
			$min = isset( $_POST['weight_min'] ) ? absint( $_POST['weight_min'] ) : 100;
			$max = isset( $_POST['weight_max'] ) ? absint( $_POST['weight_max'] ) : 900;
			if ( $min < 1 || $max > 1000 || $min >= $max ) {
				self::redirect( 'invalid' );
			}
			$weight = $min . ' ' . $max;
		} else {
			$weight_value = isset( $_POST['weight_static'] ) ? absint( $_POST['weight_static'] ) : 400;
			if ( ! in_array( $weight_value, array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ), true ) ) {
				$weight_value = 400;
			}
			$weight = (string) $weight_value;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			self::redirect( 'invalid' );
		}
		$directory = trailingslashit( $uploads['basedir'] ) . self::FONT_DIRECTORY;
		if ( ! wp_mkdir_p( $directory ) || ! is_writable( $directory ) ) {
			self::redirect( 'invalid' );
		}
		$id       = str_replace( '-', '', wp_generate_uuid4() );
		$filename = 'dh-font-' . $id . '.' . $ext;
		$filename = wp_unique_filename( $directory, $filename );
		$path     = trailingslashit( $directory ) . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
			self::redirect( 'invalid' );
		}

		$fonts   = self::fonts();
		$fonts[] = array(
			'id'      => $id,
			'family'  => substr( $family, 0, 80 ),
			'filename'=> $filename,
			'format'  => $ext,
			'type'    => $type,
			'style'   => $style,
			'weight'  => $weight,
			'size'    => (int) filesize( $path ),
		);
		update_option( self::FONTS_OPTION, $fonts, false );
		self::redirect( 'uploaded' );
	}

	/** Remove one uploaded face and its file from the managed font directory. */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You are not allowed to remove fonts.', 'dixcoverhub-custom-ui' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['font_id'] ) ? sanitize_key( wp_unslash( $_POST['font_id'] ) ) : '';
		check_admin_referer( 'dixcoverhub_delete_font_' . $id );

		$fonts = self::fonts();
		foreach ( $fonts as $index => $font ) {
			if ( $id !== $font['id'] ) {
				continue;
			}
			$uploads = wp_upload_dir();
			$base    = realpath( trailingslashit( $uploads['basedir'] ) . self::FONT_DIRECTORY );
			$path    = realpath( trailingslashit( $uploads['basedir'] ) . self::FONT_DIRECTORY . '/' . basename( $font['filename'] ) );
			if ( $base && $path && 0 === strpos( $path, trailingslashit( $base ) ) && is_file( $path ) ) {
				wp_delete_file( $path );
			}
			unset( $fonts[ $index ] );
			update_option( self::FONTS_OPTION, array_values( $fonts ), false );
			break;
		}
		self::redirect( 'deleted' );
	}

	/** Print only selected font families and their role assignments. */
	public static function print_frontend_css() {
		$options = DixcoverHub_Custom_UI::options();
		if ( is_admin() || empty( $options['fonts_enabled'] ) ) {
			return;
		}
		$fonts    = self::fonts();
		$settings = self::settings();
		$selected = array_unique( array_filter( array( $settings['body_family'], $settings['heading_family'], $settings['interface_family'], $settings['small_family'] ) ) );
		if ( empty( $selected ) ) {
			return;
		}

		$font_urls = array();
		foreach ( $fonts as $font ) {
			$family_key = self::family_key( $font['family'] );
			if ( in_array( $family_key, $selected, true ) ) {
				$url = self::font_url( $font['filename'] );
				$font_urls[ $family_key ][] = array( 'url' => $url, 'font' => $font );
			}
		}
		if ( empty( $font_urls ) ) {
			return;
		}

		$preloads = array();
		foreach ( array( 'body', 'heading' ) as $role ) {
			$key = $settings[ $role . '_family' ];
			if ( empty( $key ) || empty( $font_urls[ $key ] ) ) {
				continue;
			}
			$face = self::matching_face( $font_urls[ $key ], $settings[ $role . '_weight' ] );
			if ( $face ) {
				$preloads[ $face['url'] ] = $face['font']['format'];
			}
		}
		foreach ( $preloads as $url => $format ) {
			printf( '<link rel="preload" href="%1$s" as="font" type="font/%2$s" crossorigin />' . "\n", esc_url( $url ), esc_attr( $format ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		$css = '';
		foreach ( $font_urls as $family_key => $faces ) {
			foreach ( $faces as $face ) {
				$font = $face['font'];
				$css .= '@font-face{font-family:"' . $family_key . '";src:url("' . esc_url_raw( $face['url'] ) . '") format("' . esc_attr( $font['format'] ) . '");font-style:' . esc_attr( $font['style'] ) . ';font-weight:' . esc_attr( $font['weight'] ) . ';font-display:swap;}';
			}
		}

		$roles = array(
			'body'      => 'body',
			'heading'   => 'h1,h2,h3,h4,h5,h6',
			'interface' => '.dh-navbar,button,input,select,textarea,.wp-element-button',
			'small'     => 'small,figcaption,.has-small-font-size',
		);
		foreach ( $roles as $role => $selector ) {
			$key = $settings[ $role . '_family' ];
			if ( empty( $key ) || empty( $font_urls[ $key ] ) ) {
				continue;
			}
			$css .= $selector . '{font-family:"' . $key . '",system-ui,sans-serif;font-weight:' . absint( $settings[ $role . '_weight' ] ) . ';font-synthesis:none;font-optical-sizing:auto;}';
		}
		if ( $css ) {
			echo '<style id="dixcoverhub-custom-fonts">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values above are generated or allow-listed.
		}
	}

	/** Load uploaded faces in the Studio so each library row previews its real font. */
	public static function print_admin_font_faces() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'appearance_page_dixcoverhub-custom-ui' !== $screen->id ) {
			return;
		}
		$css = '';
		foreach ( self::fonts() as $font ) {
			$url = self::font_url( $font['filename'] );
			$css .= '@font-face{font-family:"' . self::family_key( $font['family'] ) . '";src:url("' . esc_url_raw( $url ) . '") format("' . esc_attr( $font['format'] ) . '");font-style:' . esc_attr( $font['style'] ) . ';font-weight:' . esc_attr( $font['weight'] ) . ';font-display:swap;}';
		}
		if ( $css ) {
			echo '<style id="dixcoverhub-admin-font-previews">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- values above are generated or allow-listed.
		}
	}

	/** Return sanitized, stored font faces. */
	private static function fonts() {
		$fonts = get_option( self::FONTS_OPTION, array() );
		if ( ! is_array( $fonts ) ) {
			return array();
		}
		$valid = array();
		foreach ( $fonts as $font ) {
			if ( ! is_array( $font ) || empty( $font['id'] ) || empty( $font['family'] ) || empty( $font['filename'] ) || empty( $font['format'] ) || ! in_array( $font['format'], array( 'woff2', 'woff' ), true ) ) {
				continue;
			}
			$font['id']       = sanitize_key( $font['id'] );
			$font['family']   = sanitize_text_field( $font['family'] );
			$font['filename'] = basename( sanitize_file_name( $font['filename'] ) );
			$font['type']     = isset( $font['type'] ) && 'static' === $font['type'] ? 'static' : 'variable';
			$font['style']    = isset( $font['style'] ) && 'italic' === $font['style'] ? 'italic' : 'normal';
			$font['weight']   = isset( $font['weight'] ) ? preg_replace( '/[^0-9 ]/', '', (string) $font['weight'] ) : '400';
			$font['size']     = isset( $font['size'] ) ? absint( $font['size'] ) : 0;
			$valid[]          = $font;
		}
		return $valid;
	}

	/** Settings merged with defaults. */
	private static function settings() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/** Families keyed by a stable CSS-safe identifier. */
	private static function families() {
		$families = array();
		foreach ( self::fonts() as $font ) {
			$families[ self::family_key( $font['family'] ) ] = $font['family'];
		}
		return $families;
	}

	/** Stable, safe CSS family name shared by static and variable faces. */
	private static function family_key( $family ) {
		$slug = sanitize_title( $family );
		return 'dhfont-' . ( $slug ? $slug : 'custom' ) . '-' . substr( md5( strtolower( $family ) ), 0, 6 );
	}

	/** Build a same-origin URL from the current WordPress uploads location. */
	private static function font_url( $filename ) {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['baseurl'] ) . self::FONT_DIRECTORY . '/' . rawurlencode( basename( $filename ) );
	}

	/** Select the variable or closest static face for preloading. */
	private static function matching_face( $faces, $weight ) {
		$target_weight = min( 900, max( 100, absint( $weight ) ) );
		$closest       = null;
		$closest_gap   = PHP_INT_MAX;
		foreach ( $faces as $face ) {
			if ( 'variable' === $face['font']['type'] ) {
				$range = preg_split( '/\s+/', trim( $face['font']['weight'] ) );
				$min   = isset( $range[0] ) ? absint( $range[0] ) : 400;
				$max   = isset( $range[1] ) ? absint( $range[1] ) : $min;
				if ( $target_weight >= $min && $target_weight <= $max ) {
					return $face;
				}
				continue;
			}
			if ( 'normal' !== $face['font']['style'] ) {
				continue;
			}
			$gap = abs( absint( $face['font']['weight'] ) - $target_weight );
			if ( $gap < $closest_gap ) {
				$closest     = $face;
				$closest_gap = $gap;
			}
		}
		return $closest ? $closest : ( isset( $faces[0] ) ? $faces[0] : null );
	}

	/** Render the typography role's family and weight selectors. */
	private static function role_fields( $families, $settings, $role, $label, $default_weight ) {
		$family_key = $role . '_family';
		$weight_key = $role . '_weight';
		?>
		<div class="dh-ui-role-card">
			<label class="dh-ui-field"><span><?php echo esc_html( $label ); ?></span><select name="<?php echo esc_attr( self::SETTINGS_OPTION . '[' . $family_key . ']' ); ?>"><option value=""><?php esc_html_e( 'Use theme font', 'dixcoverhub-custom-ui' ); ?></option><?php foreach ( $families as $key => $name ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings[ $family_key ], $key ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label>
			<label class="dh-ui-field"><span><?php esc_html_e( 'Weight', 'dixcoverhub-custom-ui' ); ?></span><select name="<?php echo esc_attr( self::SETTINGS_OPTION . '[' . $weight_key . ']' ); ?>"><?php self::weight_options( $settings[ $weight_key ] ? $settings[ $weight_key ] : $default_weight ); ?></select></label>
		</div>
		<?php
	}

	/** Output common weight choices. */
	private static function weight_options( $current ) {
		foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $weight ) {
			$label = $weight . ' · ' . self::weight_name( $weight );
			printf( '<option value="%1$d" %2$s>%3$s</option>', absint( $weight ), selected( (int) $current, $weight, false ), esc_html( $label ) );
		}
	}

	/** Human readable standard weight names. */
	private static function weight_name( $weight ) {
		$names = array( 100 => 'Thin', 200 => 'Extra light', 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'Semi bold', 700 => 'Bold', 800 => 'Extra bold', 900 => 'Black' );
		return isset( $names[ $weight ] ) ? $names[ $weight ] : 'Regular';
	}

	/** Return to the Fonts workspace with a short result code. */
	private static function redirect( $result ) {
		$url = add_query_arg(
			array( 'page' => 'dixcoverhub-custom-ui', 'section' => 'fonts', 'dh_font_result' => sanitize_key( $result ) ),
			admin_url( 'themes.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
