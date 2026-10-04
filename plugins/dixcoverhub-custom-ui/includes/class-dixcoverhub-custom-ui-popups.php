<?php
/**
 * Custom, independently activated public popup experiences.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Popups {
	const POPUPS_OPTION  = 'dixcoverhub_custom_ui_popups';
	const METRICS_OPTION = 'dixcoverhub_custom_ui_popup_metrics';
	const REST_NAMESPACE = 'dixcoverhub-custom-ui/v1';
	private static $current_popup = null;

	public static function init() {
		add_action( 'admin_post_dixcoverhub_save_popup', array( __CLASS__, 'save_popup' ) );
		add_action( 'admin_post_dixcoverhub_delete_popup', array( __CLASS__, 'delete_popup' ) );
		add_action( 'admin_post_dixcoverhub_duplicate_popup', array( __CLASS__, 'duplicate_popup' ) );
		add_action( 'admin_post_dixcoverhub_restore_popup', array( __CLASS__, 'restore_popup' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_frontend' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/** Popup defaults mirror the reference runtime while keeping new records in draft. */
	public static function defaults() {
		return array(
			'id' => '', 'name' => __( 'Untitled popup', 'dixcoverhub-custom-ui' ), 'description' => '', 'status' => 'draft',
			'html' => '', 'css' => '', 'javascript' => '', 'frequency' => 'once_per_day', 'trigger_type' => 'delay',
			'trigger_value' => 5, 'trigger_selector' => '', 'position' => 'center', 'desktop' => 1, 'tablet' => 1, 'mobile' => 1,
			'include_paths' => array(), 'exclude_paths' => array( '/wp-admin/*', '/admin/*' ), 'start_at' => '', 'end_at' => '',
			'priority' => 10, 'close_on_overlay' => 1, 'close_on_escape' => 1, 'show_close_button' => 1, 'max_displays' => 0,
			'background_color' => '#ffffff', 'text_color' => '#201a24', 'accent_color' => '#611f69', 'width' => 520, 'version' => 1, 'created_at' => 0, 'updated_at' => 0,
			'revisions' => array(),
		);
	}

	/** Read normalized popup records from the WordPress options table. */
	public static function all() {
		$saved = get_option( self::POPUPS_OPTION, array() );
		if ( ! is_array( $saved ) ) { return array(); }
		$records = array();
		foreach ( $saved as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) { continue; }
			$records[] = wp_parse_args( $item, self::defaults() );
		}
		usort( $records, static function ( $left, $right ) {
			$priority = (int) $left['priority'] <=> (int) $right['priority'];
			if ( 0 !== $priority ) { return $priority; }
			$updated = absint( $right['updated_at'] ) <=> absint( $left['updated_at'] );
			return 0 !== $updated ? $updated : strcmp( (string) $left['id'], (string) $right['id'] );
		} );
		return $records;
	}

	/** Render the Popups Studio section. */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$records = self::all();
		$id = isset( $_GET['popup_id'] ) ? sanitize_key( wp_unslash( $_GET['popup_id'] ) ) : '';
		$new_record = isset( $_GET['new'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['new'] ) );
		$selected = null;
		foreach ( $records as $record ) { if ( $id && $id === $record['id'] ) { $selected = $record; break; } }
		if ( $new_record || ! $selected ) { $selected = self::defaults(); }
		$options = DixcoverHub_Custom_UI::options();
		$metrics = get_option( self::METRICS_OPTION, array() );
		$metrics = is_array( $metrics ) ? $metrics : array();
		$selected_metrics = $selected['id'] && isset( $metrics[ $selected['id'] ] ) && is_array( $metrics[ $selected['id'] ] ) ? $metrics[ $selected['id'] ] : array();
		$popup_views = absint( $selected_metrics['impressions'] ?? 0 );
		$popup_clicks = absint( $selected_metrics['cta_click'] ?? 0 );
		$popup_dismissals = absint( $selected_metrics['close_button'] ?? 0 ) + absint( $selected_metrics['overlay_close'] ?? 0 ) + absint( $selected_metrics['escape_close'] ?? 0 );
		$popup_ctr = $popup_views ? number_format_i18n( 100 * $popup_clicks / $popup_views, 1 ) . '%' : '0%';
		$popup_devices = isset( $selected_metrics['devices'] ) && is_array( $selected_metrics['devices'] ) ? wp_parse_args( $selected_metrics['devices'], array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0, 'unknown' => 0 ) ) : array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0, 'unknown' => 0 );
		$popup_device_total = max( 1, array_sum( array_map( 'absint', $popup_devices ) ) );
		$popup_total_events = absint( $selected_metrics['total_events'] ?? ( $popup_views + $popup_clicks + absint( $selected_metrics['button_click'] ?? 0 ) + $popup_dismissals ) );
		$base_url = add_query_arg( array( 'page' => 'dixcoverhub-custom-ui', 'section' => 'popups' ), admin_url( 'themes.php' ) );
		$notice = isset( $_GET['dh_popup_result'] ) ? sanitize_key( wp_unslash( $_GET['dh_popup_result'] ) ) : '';
		?>
		<div class="dh-ui-section-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'PUBLIC EXPERIENCES', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Popups', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Build scheduled, targeted popup experiences and preview them before activation.', 'dixcoverhub-custom-ui' ); ?></p></div><a class="button dh-ui-preview-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview site', 'dixcoverhub-custom-ui' ); ?></a></div>
		<?php if ( 'saved' === $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Popup saved. It will appear only when both the popup feature and this popup are active.', 'dixcoverhub-custom-ui' ); ?></p></div><?php elseif ( 'restored' === $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Revision restored as a new draft version.', 'dixcoverhub-custom-ui' ); ?></p></div><?php elseif ( 'deleted' === $notice ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Popup deleted.', 'dixcoverhub-custom-ui' ); ?></p></div><?php elseif ( 'invalid' === $notice ) : ?><div class="notice notice-error"><p><?php esc_html_e( 'The popup could not be saved or restored. Check the selected version and settings.', 'dixcoverhub-custom-ui' ); ?></p></div><?php endif; ?>
		<form class="dh-ui-feature-opt-in <?php echo ! empty( $options['popup_enabled'] ) ? 'is-active' : 'is-inactive'; ?>" action="options.php" method="post">
			<?php settings_fields( 'dixcoverhub_custom_ui_group' ); ?>
			<div><strong><?php esc_html_e( 'Allow active popups on the public site', 'dixcoverhub-custom-ui' ); ?></strong><p><?php esc_html_e( 'This master switch stays off until you enable it. Each popup also needs its own Active status and schedule.', 'dixcoverhub-custom-ui' ); ?></p></div>
			<input type="hidden" name="<?php echo esc_attr( DixcoverHub_Custom_UI::OPTION_KEY ); ?>[popup_enabled]" value="0" />
			<label><input type="checkbox" name="<?php echo esc_attr( DixcoverHub_Custom_UI::OPTION_KEY ); ?>[popup_enabled]" value="1" <?php checked( ! empty( $options['popup_enabled'] ) ); ?> /> <span><?php echo ! empty( $options['popup_enabled'] ) ? esc_html__( 'Active', 'dixcoverhub-custom-ui' ) : esc_html__( 'Off', 'dixcoverhub-custom-ui' ); ?></span></label>
			<?php submit_button( __( 'Save', 'dixcoverhub-custom-ui' ), 'primary', 'submit', false, array( 'class' => 'button button-primary dh-ui-primary-button' ) ); ?>
		</form>
		<div class="dh-popup-studio-grid">
			<aside class="dh-ui-card dh-popup-list-card">
				<div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php esc_html_e( 'EXPERIENCES', 'dixcoverhub-custom-ui' ); ?></p><h3><?php esc_html_e( 'Your popups', 'dixcoverhub-custom-ui' ); ?></h3></div><span class="dh-ui-count"><?php echo esc_html( (string) count( $records ) ); ?></span></div>
				<a class="button button-primary dh-ui-primary-button dh-popup-add" href="<?php echo esc_url( add_query_arg( 'new', '1', $base_url ) ); ?>">+ <?php esc_html_e( 'Add popup', 'dixcoverhub-custom-ui' ); ?></a>
				<div class="dh-popup-record-list">
					<?php if ( ! $records ) : ?><p class="dh-ui-note"><?php esc_html_e( 'No popups yet. Start with a draft and preview it before activation.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?>
					<?php foreach ( $records as $record ) : $is_selected = $record['id'] === $id; $record_metrics = isset( $metrics[ $record['id'] ] ) && is_array( $metrics[ $record['id'] ] ) ? $metrics[ $record['id'] ] : array(); ?>
						<a class="dh-popup-record <?php echo $is_selected ? 'is-selected' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'popup_id', rawurlencode( $record['id'] ), $base_url ) ); ?>"><span><strong><?php echo esc_html( $record['name'] ); ?></strong><small><?php echo esc_html( ucfirst( $record['status'] ) ); ?> · <?php echo esc_html( number_format_i18n( absint( $record_metrics['impressions'] ?? 0 ) ) ); ?> <?php esc_html_e( 'views', 'dixcoverhub-custom-ui' ); ?></small></span><i class="is-<?php echo esc_attr( $record['status'] ); ?>"></i></a>
					<?php endforeach; ?>
				</div>
			</aside>
			<main class="dh-ui-card dh-popup-editor-card">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="dh-popup-editor" data-dh-popup-editor>
					<input type="hidden" name="action" value="dixcoverhub_save_popup" /><input type="hidden" name="popup_id" value="<?php echo esc_attr( $selected['id'] ); ?>" /><?php wp_nonce_field( 'dixcoverhub_save_popup' ); ?>
					<div class="dh-ui-card-heading"><div><p class="dh-ui-eyebrow"><?php echo $selected['id'] ? esc_html__( 'POPUP EDITOR', 'dixcoverhub-custom-ui' ) : esc_html__( 'NEW EXPERIENCE', 'dixcoverhub-custom-ui' ); ?></p><h3><?php echo esc_html( $selected['name'] ); ?></h3><p><?php esc_html_e( 'This popup remains a draft until you activate it here and turn on the master switch.', 'dixcoverhub-custom-ui' ); ?></p></div><span class="dh-popup-version">v<?php echo esc_html( (string) absint( $selected['version'] ) ); ?></span></div>
					<div class="dh-popup-editor-tabs" role="tablist"><button type="button" class="is-active" data-dh-popup-tab="content"><?php esc_html_e( 'Content', 'dixcoverhub-custom-ui' ); ?></button><button type="button" data-dh-popup-tab="behavior"><?php esc_html_e( 'Behavior', 'dixcoverhub-custom-ui' ); ?></button><button type="button" data-dh-popup-tab="audience"><?php esc_html_e( 'Audience', 'dixcoverhub-custom-ui' ); ?></button><button type="button" data-dh-popup-tab="design"><?php esc_html_e( 'Design & code', 'dixcoverhub-custom-ui' ); ?></button><button type="button" data-dh-popup-tab="metrics"><?php esc_html_e( 'Metrics', 'dixcoverhub-custom-ui' ); ?></button><button type="button" data-dh-popup-tab="history"><?php esc_html_e( 'History', 'dixcoverhub-custom-ui' ); ?></button></div>
					<section class="dh-popup-panel is-active" data-dh-popup-panel="content">
						<label class="dh-popup-field"><span><?php esc_html_e( 'Internal name', 'dixcoverhub-custom-ui' ); ?></span><input name="name" required maxlength="120" value="<?php echo esc_attr( $selected['name'] ); ?>" /></label>
						<label class="dh-popup-field"><span><?php esc_html_e( 'Purpose / description', 'dixcoverhub-custom-ui' ); ?></span><input name="description" maxlength="300" value="<?php echo esc_attr( $selected['description'] ); ?>" placeholder="e.g. Invite readers to join the WhatsApp community" /></label>
						<div class="dh-popup-field-row"><label class="dh-popup-field"><span><?php esc_html_e( 'Status', 'dixcoverhub-custom-ui' ); ?></span><select name="status"><?php foreach ( array( 'draft' => __( 'Draft', 'dixcoverhub-custom-ui' ), 'active' => __( 'Active', 'dixcoverhub-custom-ui' ), 'paused' => __( 'Paused', 'dixcoverhub-custom-ui' ), 'archived' => __( 'Archived', 'dixcoverhub-custom-ui' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected['status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label class="dh-popup-field"><span><?php esc_html_e( 'Popup position', 'dixcoverhub-custom-ui' ); ?></span><select name="position"><?php foreach ( array( 'center' => __( 'Centered card', 'dixcoverhub-custom-ui' ), 'bottom_right' => __( 'Bottom right', 'dixcoverhub-custom-ui' ), 'bottom_left' => __( 'Bottom left', 'dixcoverhub-custom-ui' ), 'top_center' => __( 'Top center', 'dixcoverhub-custom-ui' ), 'full_screen' => __( 'Full screen', 'dixcoverhub-custom-ui' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected['position'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></div>
						<label class="dh-popup-field"><span><?php esc_html_e( 'Popup HTML', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'WordPress-safe HTML. Use links and buttons for calls to action.', 'dixcoverhub-custom-ui' ); ?></small></span><textarea name="html" rows="12" data-dh-popup-html placeholder="<div class=&quot;dh-popup-card&quot;><p class=&quot;dh-popup-eyebrow&quot;>A note from DixcoverHub</p><h2>Find your next opportunity</h2><p>Get new opportunities delivered to you.</p><a class=&quot;dh-popup-cta&quot; href=&quot;https://example.com&quot;>Join our community</a></div>"><?php echo esc_textarea( $selected['html'] ); ?></textarea></label>
						<div class="dh-popup-actions"><button type="button" class="button" data-dh-popup-preview><?php esc_html_e( 'Preview this draft', 'dixcoverhub-custom-ui' ); ?></button><p><?php esc_html_e( 'Preview does not publish, activate, or run custom JavaScript.', 'dixcoverhub-custom-ui' ); ?></p></div>
					</section>
					<section class="dh-popup-panel" data-dh-popup-panel="behavior" hidden>
						<div class="dh-popup-field-row"><label class="dh-popup-field"><span><?php esc_html_e( 'Show frequency', 'dixcoverhub-custom-ui' ); ?></span><select name="frequency"><?php foreach ( array( 'every_visit' => __( 'Every visit', 'dixcoverhub-custom-ui' ), 'once_per_session' => __( 'Once per session', 'dixcoverhub-custom-ui' ), 'once_per_day' => __( 'Once per day', 'dixcoverhub-custom-ui' ), 'once_per_week' => __( 'Once per week', 'dixcoverhub-custom-ui' ), 'once_ever' => __( 'Once ever', 'dixcoverhub-custom-ui' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected['frequency'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label><label class="dh-popup-field"><span><?php esc_html_e( 'Trigger', 'dixcoverhub-custom-ui' ); ?></span><select name="trigger_type" data-dh-popup-trigger><?php foreach ( array( 'immediate' => __( 'Immediately', 'dixcoverhub-custom-ui' ), 'delay' => __( 'After a delay', 'dixcoverhub-custom-ui' ), 'scroll' => __( 'After scrolling', 'dixcoverhub-custom-ui' ), 'exit_intent' => __( 'Exit intent', 'dixcoverhub-custom-ui' ), 'click' => __( 'Click a CSS selector', 'dixcoverhub-custom-ui' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected['trigger_type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></div>
						<div class="dh-popup-field-row"><label class="dh-popup-field" data-dh-popup-trigger-value><span><?php esc_html_e( 'Trigger value', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Seconds for delay, scroll percentage for scroll trigger', 'dixcoverhub-custom-ui' ); ?></small></span><input type="number" name="trigger_value" min="1" max="300" value="<?php echo esc_attr( (string) $selected['trigger_value'] ); ?>" /></label><label class="dh-popup-field" data-dh-popup-trigger-selector><span><?php esc_html_e( 'CSS selector', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Used only for click-triggered popups', 'dixcoverhub-custom-ui' ); ?></small></span><input name="trigger_selector" value="<?php echo esc_attr( $selected['trigger_selector'] ); ?>" placeholder=".newsletter-button" /></label></div>
						<div class="dh-popup-toggle-grid"><?php self::render_toggle( 'desktop', __( 'Desktop', 'dixcoverhub-custom-ui' ), $selected ); ?><?php self::render_toggle( 'tablet', __( 'Tablet', 'dixcoverhub-custom-ui' ), $selected ); ?><?php self::render_toggle( 'mobile', __( 'Mobile', 'dixcoverhub-custom-ui' ), $selected ); ?><?php self::render_toggle( 'close_on_overlay', __( 'Close on overlay', 'dixcoverhub-custom-ui' ), $selected ); ?><?php self::render_toggle( 'close_on_escape', __( 'Close on Escape', 'dixcoverhub-custom-ui' ), $selected ); ?><?php self::render_toggle( 'show_close_button', __( 'Show close button', 'dixcoverhub-custom-ui' ), $selected ); ?></div>
						<div class="dh-popup-field-row"><label class="dh-popup-field"><span><?php esc_html_e( 'Priority', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Lower numbers are shown first', 'dixcoverhub-custom-ui' ); ?></small></span><input type="number" name="priority" min="0" max="999" value="<?php echo esc_attr( (string) $selected['priority'] ); ?>" /></label><label class="dh-popup-field"><span><?php esc_html_e( 'Maximum lifetime displays', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( '0 means unlimited; frequency still applies', 'dixcoverhub-custom-ui' ); ?></small></span><input type="number" name="max_displays" min="0" max="100" value="<?php echo esc_attr( (string) $selected['max_displays'] ); ?>" /></label></div>
					</section>
					<section class="dh-popup-panel" data-dh-popup-panel="audience" hidden>
						<div class="dh-popup-field-row"><label class="dh-popup-field"><span><?php esc_html_e( 'Include paths', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Blank includes every public page. Use * as a wildcard.', 'dixcoverhub-custom-ui' ); ?></small></span><textarea name="include_paths" rows="5" placeholder="/opportunities/*"><?php echo esc_textarea( implode( "\n", (array) $selected['include_paths'] ) ); ?></textarea></label><label class="dh-popup-field"><span><?php esc_html_e( 'Exclude paths', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Admin and login paths are always excluded.', 'dixcoverhub-custom-ui' ); ?></small></span><textarea name="exclude_paths" rows="5" placeholder="/privacy-policy/"><?php echo esc_textarea( implode( "\n", (array) $selected['exclude_paths'] ) ); ?></textarea></label></div>
						<div class="dh-popup-field-row"><label class="dh-popup-field"><span><?php esc_html_e( 'Start date and time', 'dixcoverhub-custom-ui' ); ?></span><input type="datetime-local" name="start_at" value="<?php echo esc_attr( $selected['start_at'] ? substr( str_replace( ' ', 'T', $selected['start_at'] ), 0, 16 ) : '' ); ?>" /></label><label class="dh-popup-field"><span><?php esc_html_e( 'End date and time', 'dixcoverhub-custom-ui' ); ?></span><input type="datetime-local" name="end_at" value="<?php echo esc_attr( $selected['end_at'] ? substr( str_replace( ' ', 'T', $selected['end_at'] ), 0, 16 ) : '' ); ?>" /></label></div>
					</section>
					<section class="dh-popup-panel" data-dh-popup-panel="design" hidden>
						<div class="dh-popup-field-row"><?php self::render_color_field( 'background_color', __( 'Popup background', 'dixcoverhub-custom-ui' ), $selected['background_color'] ); ?><?php self::render_color_field( 'text_color', __( 'Text colour', 'dixcoverhub-custom-ui' ), $selected['text_color'] ); ?><?php self::render_color_field( 'accent_color', __( 'Accent colour', 'dixcoverhub-custom-ui' ), $selected['accent_color'] ); ?></div>
						<label class="dh-popup-field"><span><?php esc_html_e( 'Maximum popup width (px)', 'dixcoverhub-custom-ui' ); ?></span><input type="number" name="width" min="280" max="960" value="<?php echo esc_attr( (string) $selected['width'] ); ?>" /></label>
						<label class="dh-popup-field"><span><?php esc_html_e( 'Custom CSS', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Trusted administrator code. Keep selectors under .dh-popup so styles stay local to this experience.', 'dixcoverhub-custom-ui' ); ?></small></span><textarea name="css" rows="8" data-dh-popup-css placeholder=".dh-popup .dh-popup-card { padding: 32px; border-radius: 18px; }" ><?php echo esc_textarea( $selected['css'] ); ?></textarea></label>
						<label class="dh-popup-field"><span><?php esc_html_e( 'Custom JavaScript', 'dixcoverhub-custom-ui' ); ?><small><?php esc_html_e( 'Optional trusted code. Receives root, closePopup, and popupId.', 'dixcoverhub-custom-ui' ); ?></small></span><textarea name="javascript" rows="6" placeholder="// Example: root.classList.add('is-ready');"><?php echo esc_textarea( $selected['javascript'] ); ?></textarea></label>
						<p class="dh-ui-note"><?php esc_html_e( 'Popup HTML is filtered through WordPress post-content rules. Custom CSS and JavaScript run only for active popups saved by an administrator.', 'dixcoverhub-custom-ui' ); ?></p>
					</section>
					<section class="dh-popup-panel" data-dh-popup-panel="metrics" hidden>
						<?php if ( ! $selected['id'] ) : ?><p class="dh-ui-note"><?php esc_html_e( 'Save this popup to create its metrics record.', 'dixcoverhub-custom-ui' ); ?></p><?php else : ?>
						<div class="dh-popup-metric-grid"><article><span><?php esc_html_e( 'Launched', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo esc_html( number_format_i18n( $popup_views ) ); ?></strong><small><?php esc_html_e( 'Times the popup appeared', 'dixcoverhub-custom-ui' ); ?></small></article><article><span><?php esc_html_e( 'CTA clicks', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo esc_html( number_format_i18n( $popup_clicks ) ); ?></strong><small><?php echo esc_html( $popup_ctr . ' ' . __( 'click-through rate', 'dixcoverhub-custom-ui' ) ); ?></small></article><article><span><?php esc_html_e( 'All events', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo esc_html( number_format_i18n( $popup_total_events ) ); ?></strong><small><?php esc_html_e( 'Tracked interactions', 'dixcoverhub-custom-ui' ); ?></small></article></div>
						<div class="dh-popup-metric-columns"><section><h4><?php esc_html_e( 'Dismissals and clicks', 'dixcoverhub-custom-ui' ); ?></h4><?php foreach ( array( 'close_button' => __( 'Close button', 'dixcoverhub-custom-ui' ), 'overlay_close' => __( 'Clicked outside', 'dixcoverhub-custom-ui' ), 'escape_close' => __( 'Pressed Escape', 'dixcoverhub-custom-ui' ), 'button_click' => __( 'Other button clicks', 'dixcoverhub-custom-ui' ) ) as $event => $label ) : ?><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( number_format_i18n( absint( $selected_metrics[ $event ] ?? 0 ) ) ); ?></strong></div><?php endforeach; ?><div class="is-total"><span><?php esc_html_e( 'Total dismissals', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo esc_html( number_format_i18n( $popup_dismissals ) ); ?></strong></div></section>
						<section><h4><?php esc_html_e( 'Devices', 'dixcoverhub-custom-ui' ); ?></h4><p><?php esc_html_e( 'All recorded popup events', 'dixcoverhub-custom-ui' ); ?></p><?php foreach ( array( 'desktop' => __( 'Desktop', 'dixcoverhub-custom-ui' ), 'tablet' => __( 'Tablet', 'dixcoverhub-custom-ui' ), 'mobile' => __( 'Mobile', 'dixcoverhub-custom-ui' ), 'unknown' => __( 'Unknown', 'dixcoverhub-custom-ui' ) ) as $device => $label ) : $device_count = absint( $popup_devices[ $device ] ); $device_percent = (int) round( 100 * $device_count / $popup_device_total ); ?><div class="dh-popup-device-row"><div><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( number_format_i18n( $device_count ) . ' · ' . $device_percent . '%' ); ?></strong></div><i><b style="width:<?php echo esc_attr( (string) $device_percent ); ?>%"></b></i></div><?php endforeach; ?></section></div>
						<div class="dh-popup-metric-dates"><div><span><?php esc_html_e( 'Last launched', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo ! empty( $selected_metrics['last_launch'] ) ? esc_html( wp_date( 'M j, Y g:i a', absint( $selected_metrics['last_launch'] ) ) ) : esc_html__( 'No activity yet', 'dixcoverhub-custom-ui' ); ?></strong></div><div><span><?php esc_html_e( 'Last activity', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo ! empty( $selected_metrics['last_event'] ) ? esc_html( wp_date( 'M j, Y g:i a', absint( $selected_metrics['last_event'] ) ) ) : esc_html__( 'No activity yet', 'dixcoverhub-custom-ui' ); ?></strong></div></div>
						<p class="dh-ui-note"><?php esc_html_e( 'Only event totals, device types, and page paths are recorded. No visitor profiles or IP addresses are stored.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?>
					</section>
					<section class="dh-popup-panel" data-dh-popup-panel="history" hidden><div class="dh-popup-history-heading"><strong><?php esc_html_e( 'Revision history', 'dixcoverhub-custom-ui' ); ?></strong><span><?php esc_html_e( 'Code changes are saved as recoverable versions.', 'dixcoverhub-custom-ui' ); ?></span></div><?php if ( ! $selected['id'] || empty( $selected['revisions'] ) ) : ?><p class="dh-ui-note"><?php esc_html_e( 'Save this popup to begin its history.', 'dixcoverhub-custom-ui' ); ?></p><?php else : ?><ol class="dh-popup-revision-list"><?php foreach ( array_slice( $selected['revisions'], 0, 12 ) as $revision ) : $is_current_revision = absint( $revision['version'] ) === absint( $selected['version'] ); ?><li><span class="dh-popup-version">v<?php echo esc_html( (string) absint( $revision['version'] ) ); ?></span><span class="dh-popup-revision-info"><strong><?php echo esc_html( number_format_i18n( strlen( $revision['html'] ) + strlen( $revision['css'] ) + strlen( $revision['javascript'] ) ) ); ?> <?php esc_html_e( 'characters', 'dixcoverhub-custom-ui' ); ?></strong><small><?php echo esc_html( wp_date( 'M j, Y g:i a', absint( $revision['time'] ) ) ); ?></small></span><?php if ( $is_current_revision ) : ?><em><?php esc_html_e( 'Current', 'dixcoverhub-custom-ui' ); ?></em><?php else : ?><button type="submit" class="button dh-popup-restore-button" form="dh-popup-restore-<?php echo esc_attr( $selected['id'] . '-' . absint( $revision['version'] ) ); ?>"><?php esc_html_e( 'Restore', 'dixcoverhub-custom-ui' ); ?></button><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?></section>
					<div class="dh-popup-form-actions"><button class="button button-primary dh-ui-primary-button" type="submit"><?php esc_html_e( 'Save popup', 'dixcoverhub-custom-ui' ); ?></button><a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'dixcoverhub-custom-ui' ); ?></a></div>
				</form>
				<?php foreach ( (array) $selected['revisions'] as $revision ) : if ( absint( $revision['version'] ) === absint( $selected['version'] ) ) { continue; } $restore_form_id = 'dh-popup-restore-' . $selected['id'] . '-' . absint( $revision['version'] ); ?><form id="<?php echo esc_attr( $restore_form_id ); ?>" class="dh-popup-hidden-action-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="dixcoverhub_restore_popup" /><input type="hidden" name="popup_id" value="<?php echo esc_attr( $selected['id'] ); ?>" /><input type="hidden" name="revision_version" value="<?php echo esc_attr( (string) absint( $revision['version'] ) ); ?>" /><?php wp_nonce_field( 'dixcoverhub_restore_popup_' . $selected['id'] . '_' . absint( $revision['version'] ) ); ?></form><?php endforeach; ?>
				<?php if ( $selected['id'] ) : ?><form class="dh-popup-duplicate-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="dixcoverhub_duplicate_popup" /><input type="hidden" name="popup_id" value="<?php echo esc_attr( $selected['id'] ); ?>" /><?php wp_nonce_field( 'dixcoverhub_duplicate_popup_' . $selected['id'] ); ?><button class="button" type="submit"><?php esc_html_e( 'Duplicate popup', 'dixcoverhub-custom-ui' ); ?></button></form><?php endif; ?>
				<?php if ( $selected['id'] ) : ?><form class="dh-popup-delete-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete this popup and its revision history?', 'dixcoverhub-custom-ui' ) ); ?>');"><input type="hidden" name="action" value="dixcoverhub_delete_popup" /><input type="hidden" name="popup_id" value="<?php echo esc_attr( $selected['id'] ); ?>" /><?php wp_nonce_field( 'dixcoverhub_delete_popup_' . $selected['id'] ); ?><button class="button-link-delete" type="submit"><?php esc_html_e( 'Delete popup', 'dixcoverhub-custom-ui' ); ?></button></form><?php endif; ?>
			</main>
		</div>
		<div class="dh-popup-preview" data-dh-popup-preview hidden><button type="button" class="dh-popup-preview-scrim" data-dh-popup-preview-close aria-label="Close preview"></button><div class="dh-popup-preview-frame" role="dialog" aria-modal="true" aria-label="Popup preview"><button type="button" class="dh-popup-preview-x" data-dh-popup-preview-close aria-label="Close preview">×</button><div class="dh-popup dh-popup-preview-content" data-dh-popup-preview-content><div class="dh-popup-surface dh-popup-preview-surface" data-dh-popup-preview-surface><div class="dh-popup-runtime-content" data-dh-popup-preview-root></div></div></div></div></div>
		<?php
	}

	private static function render_toggle( $name, $label, $popup ) {
		printf( '<label class="dh-popup-toggle"><input type="hidden" name="%1$s" value="0" /><input type="checkbox" name="%1$s" value="1" %2$s /><span>%3$s</span></label>', esc_attr( $name ), checked( ! empty( $popup[ $name ] ), true, false ), esc_html( $label ) );
	}

	private static function render_color_field( $name, $label, $value ) {
		printf( '<label class="dh-popup-field dh-popup-color-field"><span>%1$s</span><input type="color" name="%2$s" value="%3$s" /></label>', esc_html( $label ), esc_attr( $name ), esc_attr( $value ) );
	}

	/** Save one popup record and keep a small content revision history. */
	public static function save_popup() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage popup experiences.', 'dixcoverhub-custom-ui' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'dixcoverhub_save_popup' );
		$records = self::all();
		$id = isset( $_POST['popup_id'] ) ? sanitize_key( wp_unslash( $_POST['popup_id'] ) ) : '';
		$old_index = null;
		foreach ( $records as $index => $record ) { if ( $id && $id === $record['id'] ) { $old_index = $index; break; } }
		$old = null !== $old_index ? $records[ $old_index ] : self::defaults();
		$input = wp_unslash( $_POST );
		$popup = self::sanitize_popup( $input, $old );
		if ( '' === $popup['name'] || ( 'active' === $popup['status'] && '' === trim( wp_strip_all_tags( $popup['html'] ) ) ) ) { self::redirect( $popup['id'], 'invalid' ); }
		$changed = $old['html'] !== $popup['html'] || $old['css'] !== $popup['css'] || $old['javascript'] !== $popup['javascript'];
		$is_new = empty( $old['id'] );
		$popup['version'] = max( 1, absint( $old['version'] ) + ( $changed && ! $is_new ? 1 : 0 ) );
		$popup['created_at'] = absint( $old['created_at'] ) ?: time();
		$popup['updated_at'] = time();
		$popup['revisions'] = is_array( $old['revisions'] ) ? $old['revisions'] : array();
		if ( ! $popup['id'] ) { $popup['id'] = strtolower( str_replace( '-', '', wp_generate_uuid4() ) ); }
		if ( $is_new || $changed ) {
			array_unshift( $popup['revisions'], array( 'version' => absint( $popup['version'] ), 'time' => time(), 'html' => $popup['html'], 'css' => $popup['css'], 'javascript' => $popup['javascript'] ) );
			$popup['revisions'] = array_slice( $popup['revisions'], 0, 12 );
		}
		if ( null !== $old_index ) { $records[ $old_index ] = $popup; } else { $records[] = $popup; }
		update_option( self::POPUPS_OPTION, array_values( $records ), false );
		self::redirect( $popup['id'], 'saved' );
	}

	private static function sanitize_popup( $input, $old ) {
		$popup = wp_parse_args( is_array( $old ) ? $old : array(), self::defaults() );
		$scalar = static function ( $key, $default = '' ) use ( $input ) { return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : $default; };
		$popup['id'] = isset( $old['id'] ) ? sanitize_key( $old['id'] ) : '';
		$popup['name'] = sanitize_text_field( $scalar( 'name', $popup['name'] ) );
		$popup['description'] = sanitize_text_field( $scalar( 'description', $popup['description'] ) );
		$popup['status'] = in_array( $scalar( 'status', $popup['status'] ), array( 'draft', 'active', 'paused', 'archived' ), true ) ? $scalar( 'status', $popup['status'] ) : 'draft';
		$popup['html'] = wp_kses_post( $scalar( 'html', $popup['html'] ) );
		$popup['css'] = substr( sanitize_textarea_field( $scalar( 'css', $popup['css'] ) ), 0, 30000 );
		$javascript = $scalar( 'javascript', $popup['javascript'] );
		$popup['javascript'] = substr( wp_check_invalid_utf8( $javascript ), 0, 20000 );
		$enum = array(
			'frequency' => array( 'every_visit', 'once_per_session', 'once_per_day', 'once_per_week', 'once_ever' ),
			'trigger_type' => array( 'immediate', 'delay', 'scroll', 'exit_intent', 'click' ),
			'position' => array( 'center', 'bottom_right', 'bottom_left', 'top_center', 'full_screen' ),
		);
		foreach ( $enum as $key => $values ) { $value = sanitize_key( $scalar( $key, $popup[ $key ] ) ); $popup[ $key ] = in_array( $value, $values, true ) ? $value : self::defaults()[ $key ]; }
		$popup['trigger_value'] = min( 300, max( 1, absint( $scalar( 'trigger_value', $popup['trigger_value'] ) ) ) );
		$popup['trigger_selector'] = substr( sanitize_text_field( $scalar( 'trigger_selector', $popup['trigger_selector'] ) ), 0, 180 );
		foreach ( array( 'desktop', 'tablet', 'mobile', 'close_on_overlay', 'close_on_escape', 'show_close_button' ) as $key ) { $popup[ $key ] = empty( $input[ $key ] ) ? 0 : 1; }
		foreach ( array( 'include_paths', 'exclude_paths' ) as $key ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $scalar( $key, implode( "\n", (array) $popup[ $key ] ) ) );
			$paths = array();
			foreach ( (array) $lines as $path ) {
				$path = trim( sanitize_text_field( (string) $path ) );
				if ( '' !== $path ) { $paths[] = '/' . ltrim( $path, '/' ); }
			}
			$popup[ $key ] = array_slice( array_values( array_unique( $paths ) ), 0, 30 );
		}
		foreach ( array( 'start_at', 'end_at' ) as $key ) { $value = sanitize_text_field( $scalar( $key, $popup[ $key ] ) ); $popup[ $key ] = preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? str_replace( 'T', ' ', $value ) . ':00' : ''; }
		$popup['priority'] = min( 999, absint( $scalar( 'priority', $popup['priority'] ) ) );
		$popup['max_displays'] = min( 100, absint( $scalar( 'max_displays', $popup['max_displays'] ) ) );
		$popup['background_color'] = sanitize_hex_color( $scalar( 'background_color', $popup['background_color'] ) ) ?: '#ffffff';
		$popup['text_color'] = sanitize_hex_color( $scalar( 'text_color', $popup['text_color'] ) ) ?: '#201a24';
		$popup['accent_color'] = sanitize_hex_color( $scalar( 'accent_color', $popup['accent_color'] ) ) ?: '#611f69';
		$popup['width'] = min( 960, max( 280, absint( $scalar( 'width', $popup['width'] ) ) ) );
		return $popup;
	}

	/** Restore a saved code revision as a new, inactive version. */
	public static function restore_popup() {
		$id = isset( $_POST['popup_id'] ) ? sanitize_key( wp_unslash( $_POST['popup_id'] ) ) : '';
		$revision_version = isset( $_POST['revision_version'] ) ? absint( $_POST['revision_version'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage popup experiences.', 'dixcoverhub-custom-ui' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'dixcoverhub_restore_popup_' . $id . '_' . $revision_version );
		$records = self::all();
		foreach ( $records as $index => $popup ) {
			if ( $popup['id'] !== $id ) { continue; }
			$revision = null;
			foreach ( (array) $popup['revisions'] as $saved_revision ) {
				if ( absint( $saved_revision['version'] ?? 0 ) === $revision_version ) { $revision = $saved_revision; break; }
			}
			if ( ! $revision || $revision_version === absint( $popup['version'] ) ) { self::redirect( $id, 'invalid' ); }
			$new_version = absint( $popup['version'] ) + 1;
			$popup['html'] = wp_kses_post( $revision['html'] ?? '' );
			$popup['css'] = substr( sanitize_textarea_field( $revision['css'] ?? '' ), 0, 30000 );
			$popup['javascript'] = substr( wp_check_invalid_utf8( $revision['javascript'] ?? '' ), 0, 20000 );
			$popup['status'] = 'draft';
			$popup['version'] = $new_version;
			$popup['updated_at'] = time();
			array_unshift( $popup['revisions'], array( 'version' => $new_version, 'time' => time(), 'html' => $popup['html'], 'css' => $popup['css'], 'javascript' => $popup['javascript'] ) );
			$popup['revisions'] = array_slice( $popup['revisions'], 0, 12 );
			$records[ $index ] = $popup;
			update_option( self::POPUPS_OPTION, array_values( $records ), false );
			self::redirect( $id, 'restored' );
		}
		self::redirect( '', 'invalid' );
	}

	public static function delete_popup() {
		$id = isset( $_POST['popup_id'] ) ? sanitize_key( wp_unslash( $_POST['popup_id'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage popup experiences.', 'dixcoverhub-custom-ui' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'dixcoverhub_delete_popup_' . $id );
		$records = array_values( array_filter( self::all(), static function ( $popup ) use ( $id ) { return $popup['id'] !== $id; } ) );
		update_option( self::POPUPS_OPTION, $records, false );
		$metrics = get_option( self::METRICS_OPTION, array() );
		if ( is_array( $metrics ) ) { unset( $metrics[ $id ] ); update_option( self::METRICS_OPTION, $metrics, false ); }
		self::redirect( '', 'deleted' );
	}

	public static function duplicate_popup() {
		$id = isset( $_POST['popup_id'] ) ? sanitize_key( wp_unslash( $_POST['popup_id'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage popup experiences.', 'dixcoverhub-custom-ui' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'dixcoverhub_duplicate_popup_' . $id );
		$records = self::all();
		foreach ( $records as $record ) {
			if ( $record['id'] !== $id ) { continue; }
			$copy = $record;
			$copy['id'] = strtolower( str_replace( '-', '', wp_generate_uuid4() ) );
			$copy['name'] .= ' (copy)'; $copy['status'] = 'draft'; $copy['version'] = 1; $copy['created_at'] = time(); $copy['updated_at'] = time();
			$copy['revisions'] = array( array( 'version' => 1, 'time' => time(), 'html' => $copy['html'], 'css' => $copy['css'], 'javascript' => $copy['javascript'] ) );
			$records[] = $copy;
			update_option( self::POPUPS_OPTION, $records, false );
			self::redirect( $copy['id'], 'saved' );
		}
		self::redirect( '', 'invalid' );
	}

	/** Public-facing assets are loaded only when a matching, active popup exists. */
	public static function enqueue_frontend() {
		$options = DixcoverHub_Custom_UI::options();
		if ( is_admin() || is_feed() || empty( $options['popup_enabled'] ) ) { return; }
		$popup = self::active_for_request();
		if ( ! $popup ) { return; }
		self::$current_popup = $popup;
		wp_enqueue_style( 'dixcoverhub-popup', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/popups.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
		wp_enqueue_script( 'dixcoverhub-popup', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/popups.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		wp_localize_script( 'dixcoverhub-popup', 'DixcoverHubPopup', array(
			'id' => $popup['id'], 'version' => absint( $popup['version'] ), 'frequency' => $popup['frequency'], 'trigger' => $popup['trigger_type'],
			'triggerValue' => absint( $popup['trigger_value'] ), 'triggerSelector' => $popup['trigger_selector'], 'maxDisplays' => absint( $popup['max_displays'] ),
			'devices' => array( 'desktop' => ! empty( $popup['desktop'] ), 'tablet' => ! empty( $popup['tablet'] ), 'mobile' => ! empty( $popup['mobile'] ) ),
			'closeOnOverlay' => ! empty( $popup['close_on_overlay'] ), 'closeOnEscape' => ! empty( $popup['close_on_escape'] ), 'showCloseButton' => ! empty( $popup['show_close_button'] ),
			'javascript' => $popup['javascript'], 'eventUrl' => rest_url( self::REST_NAMESPACE . '/popup-event' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
		$style = sprintf( '.dh-popup{--dh-popup-background:%1$s;--dh-popup-text:%2$s;--dh-popup-accent:%3$s;--dh-popup-width:%4$dpx}', esc_attr( $popup['background_color'] ), esc_attr( $popup['text_color'] ), esc_attr( $popup['accent_color'] ), absint( $popup['width'] ) );
		wp_add_inline_style( 'dixcoverhub-popup', $style . $popup['css'] );
	}

	/** Render the selected popup after the theme content. */
	public static function render_frontend() {
		$popup = self::$current_popup;
		if ( ! $popup ) { return; }
		$position = in_array( $popup['position'], array( 'center', 'bottom_right', 'bottom_left', 'top_center', 'full_screen' ), true ) ? $popup['position'] : 'center';
		?>
		<div class="dh-popup dh-popup-overlay" data-dh-popup data-state="closed" data-position="<?php echo esc_attr( $position ); ?>" aria-hidden="true">
			<button class="dh-popup-scrim" type="button" data-dh-popup-overlay-close aria-label="<?php esc_attr_e( 'Close popup', 'dixcoverhub-custom-ui' ); ?>"></button>
			<section class="dh-popup-surface" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $popup['name'] ); ?>" tabindex="-1">
				<div class="dh-popup-runtime-content" data-dh-popup-root><?php echo wp_kses_post( $popup['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress post HTML allow-list is applied on save. ?></div>
				<?php if ( ! empty( $popup['show_close_button'] ) ) : ?><button type="button" class="dh-popup-close" data-dh-popup-close aria-label="<?php esc_attr_e( 'Close popup', 'dixcoverhub-custom-ui' ); ?>">×</button><?php endif; ?>
			</section>
		</div>
		<?php
	}

	private static function active_for_request() {
		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
		$path = '/' . ltrim( is_string( $path ) ? $path : '/', '/' );
		if ( 0 === strpos( $path, '/wp-admin' ) || 0 === strpos( $path, '/admin' ) || 0 === strpos( $path, '/login' ) ) { return null; }
		$now = current_datetime();
		foreach ( self::all() as $popup ) {
			if ( 'active' !== $popup['status'] || ! self::path_applies( $popup, $path ) ) { continue; }
			$timezone = wp_timezone();
			$start = $popup['start_at'] ? date_create_immutable_from_format( 'Y-m-d H:i:s', $popup['start_at'], $timezone ) : false;
			$end = $popup['end_at'] ? date_create_immutable_from_format( 'Y-m-d H:i:s', $popup['end_at'], $timezone ) : false;
			if ( $start && $now < $start ) { continue; }
			if ( $end && $now > $end ) { continue; }
			return $popup;
		}
		return null;
	}

	private static function path_applies( $popup, $path ) {
		foreach ( (array) $popup['exclude_paths'] as $pattern ) { if ( self::path_matches( $pattern, $path ) ) { return false; } }
		if ( empty( $popup['include_paths'] ) ) { return true; }
		foreach ( (array) $popup['include_paths'] as $pattern ) { if ( self::path_matches( $pattern, $path ) ) { return true; } }
		return false;
	}

	private static function path_matches( $pattern, $path ) {
		$pattern = trim( (string) $pattern );
		if ( '' === $pattern ) { return false; }
		$regex = '#^' . str_replace( '\\*', '.*', preg_quote( $pattern, '#' ) ) . '$#u';
		return (bool) preg_match( $regex, $path );
	}

	public static function register_routes() {
		register_rest_route( self::REST_NAMESPACE, '/popup-event', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'record_event' ), 'permission_callback' => '__return_true' ) );
	}

	/** Store anonymous event totals and limited page/device aggregates without visitor identifiers. */
	public static function record_event( WP_REST_Request $request ) {
		$id = sanitize_key( (string) $request->get_param( 'popupId' ) );
		$event = sanitize_key( (string) $request->get_param( 'event' ) );
		$allowed = array( 'impression', 'cta_click', 'button_click', 'close_button', 'overlay_close', 'escape_close' );
		if ( ! $id || ! in_array( $event, $allowed, true ) ) { return new WP_Error( 'dh_popup_event_invalid', __( 'Invalid popup event.', 'dixcoverhub-custom-ui' ), array( 'status' => 400 ) ); }
		$path = isset( $request['path'] ) && is_scalar( $request['path'] ) ? substr( sanitize_text_field( (string) $request['path'] ), 0, 500 ) : '/';
		if ( '' === $path || '/' !== substr( $path, 0, 1 ) ) { $path = '/'; }
		$path = strtok( $path, '?#' );
		$device = isset( $request['device'] ) ? sanitize_key( (string) $request['device'] ) : 'unknown';
		if ( ! in_array( $device, array( 'desktop', 'tablet', 'mobile' ), true ) ) { $device = 'unknown'; }
		$popup = null;
		foreach ( self::all() as $item ) { if ( $item['id'] === $id && 'active' === $item['status'] ) { $popup = $item; break; } }
		if ( ! $popup || absint( $request->get_param( 'version' ) ) !== absint( $popup['version'] ) ) { return new WP_REST_Response( null, 204 ); }
		$metrics = get_option( self::METRICS_OPTION, array() );
		$metrics = is_array( $metrics ) ? $metrics : array();
		if ( ! isset( $metrics[ $id ] ) || ! is_array( $metrics[ $id ] ) ) { $metrics[ $id ] = array(); }
		$metrics[ $id ][ $event ] = absint( $metrics[ $id ][ $event ] ?? 0 ) + 1;
		$metrics[ $id ]['total_events'] = absint( $metrics[ $id ]['total_events'] ?? 0 ) + 1;
		$metrics[ $id ]['devices'] = isset( $metrics[ $id ]['devices'] ) && is_array( $metrics[ $id ]['devices'] ) ? wp_parse_args( $metrics[ $id ]['devices'], array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0, 'unknown' => 0 ) ) : array( 'desktop' => 0, 'tablet' => 0, 'mobile' => 0, 'unknown' => 0 );
		$metrics[ $id ]['devices'][ $device ] = absint( $metrics[ $id ]['devices'][ $device ] ?? 0 ) + 1;
		$metrics[ $id ]['page_paths'] = isset( $metrics[ $id ]['page_paths'] ) && is_array( $metrics[ $id ]['page_paths'] ) ? $metrics[ $id ]['page_paths'] : array();
		if ( ! isset( $metrics[ $id ]['page_paths'][ $path ] ) && count( $metrics[ $id ]['page_paths'] ) >= 40 ) { $path = '/other'; }
		$metrics[ $id ]['page_paths'][ $path ] = absint( $metrics[ $id ]['page_paths'][ $path ] ?? 0 ) + 1;
		$metrics[ $id ]['last_event'] = time();
		if ( 'impression' === $event ) { $metrics[ $id ]['last_launch'] = time(); }
		update_option( self::METRICS_OPTION, $metrics, false );
		return new WP_REST_Response( null, 204 );
	}

	private static function redirect( $id, $result ) {
		$url = add_query_arg( array( 'page' => 'dixcoverhub-custom-ui', 'section' => 'popups', 'dh_popup_result' => $result ), admin_url( 'themes.php' ) );
		if ( $id ) { $url = add_query_arg( 'popup_id', rawurlencode( $id ), $url ); }
		wp_safe_redirect( $url );
		exit;
	}
}
