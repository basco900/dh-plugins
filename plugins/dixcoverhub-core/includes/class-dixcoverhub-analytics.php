<?php
/** GA4 reporting and event tracking for DixcoverHub. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DixcoverHub_Analytics {
	const REST_NAMESPACE = 'dixcoverhub-analytics/v1';
	const PAGE_SLUG = 'dixcoverhub-analytics';
	const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';
	private static $access_token = '';
	private static $access_expires = 0;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracking' ), 20 );
		add_filter( 'post_row_actions', array( __CLASS__, 'post_analytics_row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'post_analytics_row_action' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'invalidate_content_lookup' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'invalidate_content_lookup' ) );
	}

	public static function admin_menu() {
		add_menu_page( __( 'DixcoverHub Analytics', 'dixcoverhub-core' ), __( 'Analytics', 'dixcoverhub-core' ), 'manage_options', self::PAGE_SLUG, array( __CLASS__, 'render_page' ), 'dashicons-chart-area', 58 );
	}

	public static function enqueue_admin( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) { return; }
		wp_enqueue_style( 'dixcoverhub-analytics', plugin_dir_url( DIXCOVERHUB_CORE_FILE ) . 'assets/css/analytics.css', array(), DIXCOVERHUB_CORE_VERSION );
		wp_enqueue_script( 'dixcoverhub-analytics', plugin_dir_url( DIXCOVERHUB_CORE_FILE ) . 'assets/js/analytics.js', array(), DIXCOVERHUB_CORE_VERSION, true );
		$config = self::configuration();
		wp_localize_script( 'dixcoverhub-analytics', 'DixcoverHubAnalytics', array(
			'reportUrl' => rest_url( self::REST_NAMESPACE . '/report' ),
			'contentUrl' => rest_url( self::REST_NAMESPACE . '/content' ),
			'postId' => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0,
			'currentUserId' => get_current_user_id(),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'configured' => $config['configured'],
			'measurementConfigured' => $config['measurementConfigured'],
			'timezone' => $config['timezone'],
			'missing' => $config['missing'],
		) );
	}

	/** Add a direct per-post analytics link without changing the editor or public theme. */
	public static function post_analytics_row_action( $actions, $post ) {
		if ( ! current_user_can( 'manage_options' ) || ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return $actions;
		}
		$url = add_query_arg(
			array(
				'page'    => self::PAGE_SLUG,
				'post_id' => (int) $post->ID,
			),
			admin_url( 'admin.php' )
		);
		$actions['dixcoverhub_analytics'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Analytics', 'dixcoverhub-core' ) . '</a>';
		return $actions;
	}

	/** Invalidate the short-lived WordPress permalink lookup after content changes. */
	public static function invalidate_content_lookup() {
		delete_transient( 'dixcoverhub_ga4_content_lookup_v2' );
	}

	public static function enqueue_tracking() {
		$config = self::configuration();
		$id = $config['measurementId'];
		if ( ! $id ) { return; }
		wp_enqueue_script( 'dixcoverhub-google-tag', 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ), array(), null, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script( 'dixcoverhub-google-tag', 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $id ) . ');', 'before' );
		wp_enqueue_script( 'dixcoverhub-analytics-events', plugin_dir_url( DIXCOVERHUB_CORE_FILE ) . 'assets/js/analytics-events.js', array( 'dixcoverhub-google-tag' ), DIXCOVERHUB_CORE_VERSION, true );
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$config = self::configuration();
		?>
		<div class="wrap dh-analytics" data-dh-analytics>
			<header class="dh-analytics-header"><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'DIXCOVERHUB / PERFORMANCE', 'dixcoverhub-core' ); ?></p><h1><?php esc_html_e( 'Analytics', 'dixcoverhub-core' ); ?></h1><p><?php esc_html_e( 'Understand readership, engagement, and the content people are opening.', 'dixcoverhub-core' ); ?></p></div><div class="dh-analytics-controls"><label for="dh-analytics-period" class="screen-reader-text"><?php esc_html_e( 'Report period', 'dixcoverhub-core' ); ?></label><select id="dh-analytics-period"><option value="today"><?php esc_html_e( 'Today', 'dixcoverhub-core' ); ?></option><option value="yesterday"><?php esc_html_e( 'Yesterday', 'dixcoverhub-core' ); ?></option><option value="7d"><?php esc_html_e( 'Last 7 days', 'dixcoverhub-core' ); ?></option><option value="30d" selected><?php esc_html_e( 'Last 30 days', 'dixcoverhub-core' ); ?></option><option value="90d"><?php esc_html_e( 'Last 90 days', 'dixcoverhub-core' ); ?></option><option value="1y"><?php esc_html_e( 'Last year', 'dixcoverhub-core' ); ?></option></select><button type="button" class="button" data-analytics-refresh><?php esc_html_e( 'Refresh', 'dixcoverhub-core' ); ?></button></div></header>
			<div class="dh-analytics-alert" data-analytics-alert role="status" aria-live="polite" <?php echo $config['configured'] ? 'hidden' : ''; ?>><strong><?php esc_html_e( 'Analytics is waiting for GA4 credentials.', 'dixcoverhub-core' ); ?></strong><span><?php esc_html_e( 'Add the server-side environment variables listed below. No sample numbers are shown while the connection is missing.', 'dixcoverhub-core' ); ?></span><button type="button" class="dh-analytics-config-toggle" data-config-toggle><?php esc_html_e( 'Show setup variables', 'dixcoverhub-core' ); ?></button><div class="dh-analytics-env" data-config <?php echo $config['configured'] ? 'hidden' : ''; ?>><p><code>GA4_PROPERTY_ID</code> — numeric GA4 property ID</p><p><code>GA4_CLIENT_EMAIL</code> — service account email</p><p><code>GA4_PRIVATE_KEY</code> — service account PEM key; literal and escaped newlines are supported</p><p><code>GA4_MEASUREMENT_ID</code> — optional browser tracking ID, for example G-XXXXXXXXXX</p><p class="description"><?php esc_html_e( 'Alternatively provide GA4_SERVICE_ACCOUNT_JSON. The service account needs Viewer access to the GA4 property, the Google Analytics Data API must be enabled, and PHP OpenSSL must be available.', 'dixcoverhub-core' ); ?></p></div></div>
			<div class="dh-analytics-alert is-error" data-analytics-error role="alert" hidden></div>
			<div class="dh-analytics-updated" data-analytics-updated></div>
			<div class="dh-analytics-loading" data-analytics-loading><?php esc_html_e( 'Connecting to Google Analytics…', 'dixcoverhub-core' ); ?></div>
			<section class="dh-analytics-metrics" data-metrics aria-label="<?php esc_attr_e( 'Key metrics', 'dixcoverhub-core' ); ?>"></section>
			<div class="dh-analytics-main-grid">
				<section class="dh-analytics-card dh-analytics-trend-card"><div class="dh-analytics-card-head"><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'TRAFFIC', 'dixcoverhub-core' ); ?></p><h2><?php esc_html_e( 'Views over time', 'dixcoverhub-core' ); ?></h2></div><span data-chart-total>—</span></div><div class="dh-analytics-chart" data-chart><?php esc_html_e( 'Analytics chart will appear here when connected.', 'dixcoverhub-core' ); ?></div><div class="dh-analytics-chart-legend"><span><i class="is-views"></i><?php esc_html_e( 'Views', 'dixcoverhub-core' ); ?></span><span><i class="is-users"></i><?php esc_html_e( 'Active users', 'dixcoverhub-core' ); ?></span></div></section>
				<section class="dh-analytics-card dh-analytics-realtime-card"><div class="dh-analytics-card-head"><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'LIVE', 'dixcoverhub-core' ); ?></p><h2><?php esc_html_e( 'Active right now', 'dixcoverhub-core' ); ?></h2></div><span class="dh-analytics-live-dot"></span></div><div class="dh-analytics-window-picker" data-realtime-windows role="group" aria-label="<?php esc_attr_e( 'Realtime reporting window', 'dixcoverhub-core' ); ?>"><button type="button" data-realtime-window="live" aria-pressed="false"><?php esc_html_e( 'Live', 'dixcoverhub-core' ); ?></button><button type="button" data-realtime-window="5m" aria-pressed="true">5m</button><button type="button" data-realtime-window="30m" aria-pressed="false">30m</button><button type="button" data-realtime-window="1h" aria-pressed="false">1h</button><button type="button" data-realtime-window="today" aria-pressed="false"><?php esc_html_e( 'Today', 'dixcoverhub-core' ); ?></button><button type="button" data-realtime-window="yesterday" aria-pressed="false"><?php esc_html_e( 'Yesterday', 'dixcoverhub-core' ); ?></button></div><div class="dh-analytics-live-stats"><div><span><?php esc_html_e( 'Active users', 'dixcoverhub-core' ); ?></span><strong data-live-users>—</strong></div><div><span><?php esc_html_e( 'Views', 'dixcoverhub-core' ); ?></span><strong data-live-views>—</strong></div></div><p class="dh-analytics-caption" data-live-caption><?php esc_html_e( 'Activity in the last 5 minutes', 'dixcoverhub-core' ); ?></p><div class="dh-analytics-live-list" data-realtime-pages></div></section>
			</div>
			<div class="dh-analytics-main-grid dh-analytics-lower-grid">
				<section class="dh-analytics-card">
					<div class="dh-analytics-card-head"><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'CONTENT', 'dixcoverhub-core' ); ?></p><h2><?php esc_html_e( 'Content performance', 'dixcoverhub-core' ); ?></h2></div></div>
					<div class="dh-analytics-content-tools">
						<div class="dh-analytics-content-scope" role="group" aria-label="<?php esc_attr_e( 'Content author filter', 'dixcoverhub-core' ); ?>"><button type="button" class="is-active" data-content-scope="all" aria-pressed="true"><?php esc_html_e( 'Everyone', 'dixcoverhub-core' ); ?></button><button type="button" data-content-scope="mine" aria-pressed="false"><?php esc_html_e( 'My content', 'dixcoverhub-core' ); ?></button></div>
						<label class="screen-reader-text" for="dh-analytics-content-search"><?php esc_html_e( 'Search content or author', 'dixcoverhub-core' ); ?></label><input id="dh-analytics-content-search" type="search" class="dh-analytics-content-search" data-content-search placeholder="<?php esc_attr_e( 'Search title, URL or author', 'dixcoverhub-core' ); ?>">
						<div class="dh-analytics-content-size" role="group" aria-label="<?php esc_attr_e( 'Rows per page', 'dixcoverhub-core' ); ?>"><button type="button" class="is-active" data-content-page-size="10" aria-pressed="true">10</button><button type="button" data-content-page-size="20" aria-pressed="false">20</button><button type="button" data-content-page-size="50" aria-pressed="false">50</button></div>
					</div>
					<div class="dh-analytics-table-wrap"><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Page', 'dixcoverhub-core' ); ?></th><th><?php esc_html_e( 'Author', 'dixcoverhub-core' ); ?></th><th><?php esc_html_e( 'Views', 'dixcoverhub-core' ); ?></th><th><?php esc_html_e( 'Users', 'dixcoverhub-core' ); ?></th><th><?php esc_html_e( 'Engagement', 'dixcoverhub-core' ); ?></th><th><?php esc_html_e( 'Report', 'dixcoverhub-core' ); ?></th></tr></thead><tbody data-top-pages><tr><td colspan="6">—</td></tr></tbody></table></div>
					<div class="dh-analytics-content-pagination"><span data-content-page-status><?php esc_html_e( 'Loading content…', 'dixcoverhub-core' ); ?></span><div><button type="button" data-content-page-prev disabled><?php esc_html_e( 'Previous', 'dixcoverhub-core' ); ?></button><button type="button" data-content-page-next disabled><?php esc_html_e( 'Next', 'dixcoverhub-core' ); ?></button></div></div>
					<p class="dh-analytics-caption"><?php esc_html_e( 'Open an individual report here or beside a published post in the WordPress Posts list.', 'dixcoverhub-core' ); ?></p>
				</section>
				<section class="dh-analytics-card"><div class="dh-analytics-card-head"><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'AUDIENCE', 'dixcoverhub-core' ); ?></p><h2><?php esc_html_e( 'Acquisition and locations', 'dixcoverhub-core' ); ?></h2></div></div><div class="dh-analytics-subtable"><h3><?php esc_html_e( 'Channels', 'dixcoverhub-core' ); ?></h3><div data-channels></div></div><div class="dh-analytics-subtable"><h3><?php esc_html_e( 'Top countries', 'dixcoverhub-core' ); ?></h3><div data-countries></div></div><div class="dh-analytics-subtable"><h3><?php esc_html_e( 'Devices', 'dixcoverhub-core' ); ?></h3><div data-devices></div></div></section>
			</div>
			<section class="dh-analytics-card dh-analytics-events-card"><div class="dh-analytics-card-head"><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'SITE ACTIONS', 'dixcoverhub-core' ); ?></p><h2><?php esc_html_e( 'Engagement events', 'dixcoverhub-core' ); ?></h2></div></div><div data-custom-events class="dh-analytics-event-grid"></div><p class="dh-analytics-caption"><?php esc_html_e( 'Totals appear after GA4 receives each event. Supported names are application_click, share, bookmark, and community_join.', 'dixcoverhub-core' ); ?></p></section>
		</div>
		<div class="dh-analytics-content-modal" data-content-modal hidden>
			<div class="dh-analytics-content-backdrop" data-content-close></div>
			<section class="dh-analytics-content-dialog" role="dialog" aria-modal="true" aria-labelledby="dh-analytics-content-title" aria-describedby="dh-analytics-content-path">
				<header><div><p class="dh-analytics-eyebrow"><?php esc_html_e( 'CONTENT / GA4', 'dixcoverhub-core' ); ?></p><h2 id="dh-analytics-content-title" data-content-title><?php esc_html_e( 'Post analytics', 'dixcoverhub-core' ); ?></h2><p id="dh-analytics-content-path" data-content-path></p></div><button type="button" class="dh-analytics-content-close" data-content-close aria-label="<?php esc_attr_e( 'Close analytics report', 'dixcoverhub-core' ); ?>">&times;</button></header>
				<div class="dh-analytics-content-loading" data-content-loading><?php esc_html_e( 'Loading this page’s Google Analytics report…', 'dixcoverhub-core' ); ?></div>
				<div class="dh-analytics-content-error" data-content-error role="alert" hidden></div>
				<div data-content-body hidden><div class="dh-analytics-content-metrics" data-content-metrics></div><section class="dh-analytics-content-chart-card"><header><h3><?php esc_html_e( 'Daily views', 'dixcoverhub-core' ); ?></h3><strong data-content-chart-total></strong></header><div class="dh-analytics-content-chart" data-content-chart></div></section></div>
			</section>
		</div>
		<?php
	}

	public static function register_routes() {
		register_rest_route( self::REST_NAMESPACE, '/report', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'report_endpoint' ), 'permission_callback' => static function () { return current_user_can( 'manage_options' ); } ) );
		register_rest_route( self::REST_NAMESPACE, '/content/(?P<post_id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'content_report_endpoint' ), 'permission_callback' => static function () { return current_user_can( 'manage_options' ); } ) );
	}

	public static function report_endpoint( WP_REST_Request $request ) {
		$config = self::configuration();
		$period = sanitize_key( $request->get_param( 'period' ) );
		if ( ! in_array( $period, array( 'today', 'yesterday', '7d', '30d', '90d', '1y' ), true ) ) { $period = '30d'; }
		$realtime_window = sanitize_key( $request->get_param( 'window' ) );
		if ( ! in_array( $realtime_window, array( 'live', '5m', '30m', '1h', 'today', 'yesterday' ), true ) ) { $realtime_window = '30m'; }
		if ( ! $config['configured'] ) { return rest_ensure_response( array( 'configured' => false, 'measurementConfigured' => $config['measurementConfigured'], 'missing' => $config['missing'], 'period' => $period ) ); }
		$range = self::period_range( $period );
		$property = $config['propertyId'];
		$errors = array();
		$force_refresh = (bool) $request->get_param( 'refresh' );
		$load = static function ( $method, $body, $ttl ) use ( $property, $force_refresh, &$errors ) {
			$result = self::google_report( $property, $method, $body, $ttl, $force_refresh );
			if ( is_wp_error( $result ) ) { $errors[] = $result->get_error_message(); return array(); }
			return self::report_rows( $result );
		};
		$score_rows = $load( 'runReport', array(
			'dateRanges' => array( array_merge( $range['current'], array( 'name' => 'current' ) ), array_merge( $range['previous'], array( 'name' => 'previous' ) ) ),
			'dimensions' => array( array( 'name' => 'dateRange' ) ),
			'metrics' => self::metrics( array( 'screenPageViews', 'activeUsers', 'sessions', 'engagedSessions', 'engagementRate', 'conversions' ) ),
			'keepEmptyRows' => true,
		), 900 );
		$series_rows = $load( 'runReport', array( 'dateRanges' => array( $range['current'] ), 'dimensions' => array( array( 'name' => 'date' ) ), 'metrics' => self::metrics( array( 'screenPageViews', 'activeUsers' ) ), 'orderBys' => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ), 'limit' => 400, 'keepEmptyRows' => true ), 900 );
		$page_rows = $load( 'runReport', array( 'dateRanges' => array( $range['current'] ), 'dimensions' => array( array( 'name' => 'pagePath' ), array( 'name' => 'pageTitle' ) ), 'metrics' => self::metrics( array( 'screenPageViews', 'activeUsers', 'averageEngagementTime', 'engagementRate' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'screenPageViews' ), 'desc' => true ) ), 'limit' => 5000 ), 900 );
		$channel_rows = $load( 'runReport', array( 'dateRanges' => array( $range['current'] ), 'dimensions' => array( array( 'name' => 'sessionDefaultChannelGroup' ) ), 'metrics' => self::metrics( array( 'sessions', 'activeUsers' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'sessions' ), 'desc' => true ) ), 'limit' => 8 ), 1800 );
		$country_rows = $load( 'runReport', array( 'dateRanges' => array( $range['current'] ), 'dimensions' => array( array( 'name' => 'country' ) ), 'metrics' => self::metrics( array( 'activeUsers', 'sessions' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'activeUsers' ), 'desc' => true ) ), 'limit' => 6 ), 1800 );
		$device_rows = $load( 'runReport', array( 'dateRanges' => array( $range['current'] ), 'dimensions' => array( array( 'name' => 'deviceCategory' ) ), 'metrics' => self::metrics( array( 'activeUsers' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'activeUsers' ), 'desc' => true ) ), 'limit' => 8 ), 1800 );
		if ( '1h' === $realtime_window ) {
			$hour_keys = self::last_hour_keys( $config['timezone'] );
			$date_range = array( 'startDate' => 'yesterday', 'endDate' => 'today' );
			$hourly_totals = $load( 'runReport', array(
				'dateRanges' => array( $date_range ),
				'dimensions' => array( array( 'name' => 'dateHour' ) ),
				'metrics'    => self::metrics( array( 'activeUsers', 'screenPageViews' ) ),
				'limit'      => 100,
			), 120 );
			$realtime_rows = $load( 'runReport', array(
				'dateRanges' => array( $date_range ),
				'dimensions' => array( array( 'name' => 'dateHour' ), array( 'name' => 'pagePath' ), array( 'name' => 'pageTitle' ) ),
				'metrics'    => self::metrics( array( 'activeUsers', 'screenPageViews' ) ),
				'limit'      => 100000,
			), 120 );
			$realtime_summary = array( array( 'activeUsers' => 0, 'screenPageViews' => 0 ) );
			foreach ( $hourly_totals as $row ) {
				if ( ! in_array( (string) ( $row['dateHour'] ?? '' ), $hour_keys, true ) ) { continue; }
				$realtime_summary[0]['activeUsers'] += self::number( $row['activeUsers'] ?? 0 );
				$realtime_summary[0]['screenPageViews'] += self::number( $row['screenPageViews'] ?? 0 );
			}
			$realtime_rows = array_values( array_filter( $realtime_rows, static function ( $row ) use ( $hour_keys ) {
				return in_array( (string) ( $row['dateHour'] ?? '' ), $hour_keys, true );
			} ) );
		} elseif ( in_array( $realtime_window, array( 'today', 'yesterday' ), true ) ) {
			$window_range = array( 'startDate' => $realtime_window, 'endDate' => $realtime_window );
			$realtime_rows = $load( 'runReport', array( 'dateRanges' => array( $window_range ), 'dimensions' => array( array( 'name' => 'pagePath' ), array( 'name' => 'pageTitle' ) ), 'metrics' => self::metrics( array( 'activeUsers', 'screenPageViews' ) ), 'orderBys' => array( array( 'metric' => array( 'metricName' => 'activeUsers' ), 'desc' => true ) ), 'limit' => 8 ), 120 );
			$realtime_summary = $load( 'runReport', array( 'dateRanges' => array( $window_range ), 'metrics' => self::metrics( array( 'activeUsers', 'screenPageViews' ) ) ), 120 );
		} else {
			$minutes_ago = 'live' === $realtime_window ? 0 : ( '5m' === $realtime_window ? 4 : 29 );
			$window_filter = array( 'filter' => array( 'fieldName' => 'minutesAgo', 'numericFilter' => array( 'operation' => 'LESS_THAN_OR_EQUAL', 'value' => array( 'int64Value' => (string) $minutes_ago ) ) ) );
			$realtime_rows = $load( 'runRealtimeReport', array( 'dimensions' => array( array( 'name' => 'unifiedScreenName' ), array( 'name' => 'minutesAgo' ) ), 'metrics' => self::metrics( array( 'activeUsers', 'screenPageViews' ) ), 'dimensionFilter' => $window_filter, 'limit' => 10000 ), 25 );
			$realtime_summary = $load( 'runRealtimeReport', array( 'metrics' => self::metrics( array( 'activeUsers', 'screenPageViews' ) ), 'dimensionFilter' => $window_filter ), 25 );
		}
		$event_rows = $load( 'runReport', array( 'dateRanges' => array( $range['current'] ), 'dimensions' => array( array( 'name' => 'eventName' ) ), 'metrics' => self::metrics( array( 'eventCount' ) ), 'dimensionFilter' => array( 'filter' => array( 'fieldName' => 'eventName', 'inListFilter' => array( 'values' => array( 'application_click', 'share', 'bookmark', 'community_join' ), 'caseSensitive' => true ) ) ), 'limit' => 8 ), 900 );
		$pages = array();
		$content_map = self::content_lookup();
		foreach ( $content_map as $path => $local ) {
			$pages[ $path ] = array( 'title' => $local['title'], 'path' => $path, 'views' => 0, 'activeUsers' => 0, 'engagementRate' => 0, 'averageEngagementTime' => 0, 'editUrl' => $local['editUrl'], 'contentType' => $local['type'], 'postId' => $local['postId'], 'authorId' => $local['authorId'], 'authorName' => $local['authorName'] );
		}
		foreach ( $page_rows as $row ) {
			$path = self::clean_path( $row['pagePath'] ?? '/' );
			if ( ! isset( $pages[ $path ] ) ) {
				$pages[ $path ] = array( 'title' => $row['pageTitle'] ?? $path, 'path' => $path, 'editUrl' => '', 'contentType' => 'page', 'postId' => 0, 'authorId' => 0, 'authorName' => __( 'Unattributed', 'dixcoverhub-core' ) );
			}
			$pages[ $path ]['views'] = self::number( $row['screenPageViews'] ?? 0 );
			$pages[ $path ]['activeUsers'] = self::number( $row['activeUsers'] ?? 0 );
			$pages[ $path ]['engagementRate'] = self::number( $row['engagementRate'] ?? 0 );
			$pages[ $path ]['averageEngagementTime'] = self::number( $row['averageEngagementTime'] ?? 0 );
		}
		$pages = array_values( $pages );
		usort( $pages, static function ( $a, $b ) { return $b['views'] <=> $a['views'] ?: strcasecmp( $a['title'], $b['title'] ); } );
		$series = array();
		foreach ( $series_rows as $row ) { $date = (string) ( $row['date'] ?? '' ); if ( preg_match( '/^\d{8}$/', $date ) ) { $series[] = array( 'date' => $date, 'views' => self::number( $row['screenPageViews'] ?? 0 ), 'activeUsers' => self::number( $row['activeUsers'] ?? 0 ) ); } }
		$live_users = self::number( $realtime_summary[0]['activeUsers'] ?? 0 );
		$live_views = self::number( $realtime_summary[0]['screenPageViews'] ?? 0 );
		$live_pages = array();
		foreach ( $realtime_rows as $row ) {
			$name = in_array( $realtime_window, array( 'live', '5m', '30m' ), true ) ? (string) ( $row['unifiedScreenName'] ?? '' ) : (string) ( $row['pageTitle'] ?? $row['pagePath'] ?? '' );
			if ( '' === $name ) { continue; }
			if ( ! isset( $live_pages[ $name ] ) ) { $live_pages[ $name ] = array( 'title' => $name, 'path' => self::clean_path( $row['pagePath'] ?? '/' ), 'activeUsers' => 0, 'views' => 0 ); }
			$live_pages[ $name ]['activeUsers'] = max( $live_pages[ $name ]['activeUsers'], self::number( $row['activeUsers'] ?? 0 ) );
			$live_pages[ $name ]['views'] += self::number( $row['screenPageViews'] ?? 0 );
		}
		$live_pages = array_values( $live_pages );
		usort( $live_pages, static function ( $a, $b ) { return $b['activeUsers'] <=> $a['activeUsers'] ?: $b['views'] <=> $a['views']; } );
		$window_labels = array( 'live' => 'Live', '5m' => 'Last 5 minutes', '30m' => 'Last 30 minutes', '1h' => 'Last hour', 'today' => 'Today', 'yesterday' => 'Yesterday' );
		return rest_ensure_response( array(
			'configured' => true, 'measurementConfigured' => $config['measurementConfigured'], 'period' => $period, 'periodLabel' => $range['label'], 'timezone' => $config['timezone'],
			'metrics' => self::aggregate_scorecards( $score_rows ), 'series' => $series, 'topPages' => $pages,
			'realtime' => array( 'window' => $realtime_window, 'windowLabel' => $window_labels[ $realtime_window ], 'activeUsers' => $live_users, 'views' => $live_views, 'pages' => $live_pages ),
			'channels' => array_map( static function ( $row ) { return array( 'name' => (string) ( $row['sessionDefaultChannelGroup'] ?? 'Unassigned' ), 'sessions' => self::number( $row['sessions'] ?? 0 ), 'activeUsers' => self::number( $row['activeUsers'] ?? 0 ) ); }, $channel_rows ),
			'countries' => array_map( static function ( $row ) { return array( 'name' => (string) ( $row['country'] ?? 'Unknown' ), 'activeUsers' => self::number( $row['activeUsers'] ?? 0 ), 'sessions' => self::number( $row['sessions'] ?? 0 ) ); }, $country_rows ),
			'devices' => array_map( static function ( $row ) { return array( 'name' => (string) ( $row['deviceCategory'] ?? 'Other' ), 'activeUsers' => self::number( $row['activeUsers'] ?? 0 ) ); }, $device_rows ),
			'events' => array_map( static function ( $row ) { return array( 'name' => (string) ( $row['eventName'] ?? '' ), 'count' => self::number( $row['eventCount'] ?? 0 ) ); }, $event_rows ),
			'updatedAt' => current_time( 'c' ), 'warnings' => array_values( array_unique( $errors ) ),
		) );
	}

	/** Return a focused GA4 report for one published WordPress post or page. */
	public static function content_report_endpoint( WP_REST_Request $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		$post    = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || 'publish' !== $post->post_status ) {
			return new WP_Error( 'dh_ga_content_missing', __( 'Published content was not found.', 'dixcoverhub-core' ), array( 'status' => 404 ) );
		}

		$config    = self::configuration();
		$permalink = get_permalink( $post );
		$path      = self::clean_path( wp_parse_url( $permalink, PHP_URL_PATH ) ?: '/' );
		$base      = array(
			'configured' => $config['configured'],
			'postId'     => (int) $post->ID,
			'title'      => get_the_title( $post ),
			'path'       => $path,
			'permalink'  => $permalink,
			'editUrl'    => get_edit_post_link( $post->ID, 'raw' ),
			'summary'    => array( 'current' => 0, 'last5Minutes' => 0, 'last30Minutes' => 0, 'today' => 0, 'last7Days' => 0, 'last30Days' => 0, 'allTime' => 0 ),
			'events'     => array( 'applicationClicks' => 0, 'shares' => 0, 'bookmarks' => 0 ),
			'conversionRate' => 0,
			'series'     => array(),
			'updatedAt'  => current_time( 'c' ),
		);
		if ( ! $config['configured'] ) {
			$base['missing'] = $config['missing'];
			return rest_ensure_response( $base );
		}

		$property    = $config['propertyId'];
		$force       = (bool) $request->get_param( 'refresh' );
		$page_filter = self::page_path_filter( $path );
		$warnings    = array();
		$summary_report = self::google_report(
			$property,
			'runReport',
			array(
				'dateRanges' => array(
					array( 'startDate' => 'today', 'endDate' => 'today', 'name' => 'today' ),
					array( 'startDate' => '6daysAgo', 'endDate' => 'today', 'name' => 'last7Days' ),
					array( 'startDate' => '29daysAgo', 'endDate' => 'today', 'name' => 'last30Days' ),
					array( 'startDate' => '2015-08-14', 'endDate' => 'today', 'name' => 'allTime' ),
				),
				'dimensions'       => array( array( 'name' => 'dateRange' ) ),
				'metrics'          => self::metrics( array( 'screenPageViews' ) ),
				'dimensionFilter'  => $page_filter,
				'keepEmptyRows'    => true,
			),
			900,
			$force
		);
		if ( is_wp_error( $summary_report ) ) {
			$warnings[] = $summary_report->get_error_message();
		} else {
			foreach ( self::report_rows( $summary_report ) as $row ) {
				$range_name = sanitize_key( (string) ( $row['dateRange'] ?? '' ) );
				if ( isset( $base['summary'][ $range_name ] ) ) {
					$base['summary'][ $range_name ] = self::number( $row['screenPageViews'] ?? 0 );
				}
			}
		}

		$series_report = self::google_report(
			$property,
			'runReport',
			array(
				'dateRanges'      => array( array( 'startDate' => '29daysAgo', 'endDate' => 'today' ) ),
				'dimensions'      => array( array( 'name' => 'date' ) ),
				'metrics'         => self::metrics( array( 'screenPageViews' ) ),
				'dimensionFilter' => $page_filter,
				'orderBys'        => array( array( 'dimension' => array( 'dimensionName' => 'date' ) ) ),
				'limit'           => 100,
			),
			900,
			$force
		);
		if ( is_wp_error( $series_report ) ) {
			$warnings[] = $series_report->get_error_message();
		} else {
			foreach ( self::report_rows( $series_report ) as $row ) {
				$date = (string) ( $row['date'] ?? '' );
				if ( preg_match( '/^\d{8}$/', $date ) ) {
					$base['series'][] = array( 'label' => $date, 'value' => self::number( $row['screenPageViews'] ?? 0 ) );
				}
			}
		}

		$realtime_report = self::google_report(
			$property,
			'runRealtimeReport',
			array(
				'dimensions' => array( array( 'name' => 'unifiedScreenName' ), array( 'name' => 'minutesAgo' ) ),
				'metrics'    => self::metrics( array( 'activeUsers', 'screenPageViews' ) ),
				'limit'      => 10000,
			),
			25,
			$force
		);
		$action_report = self::google_report(
			$property,
			'runReport',
			array(
				'dateRanges'      => array( array( 'startDate' => '29daysAgo', 'endDate' => 'today' ) ),
				'dimensions'      => array( array( 'name' => 'eventName' ) ),
				'metrics'         => self::metrics( array( 'eventCount' ) ),
				'dimensionFilter' => array(
					'andGroup' => array(
						'expressions' => array(
							self::page_path_filter( $path ),
							array( 'filter' => array( 'fieldName' => 'eventName', 'inListFilter' => array( 'values' => array( 'application_click', 'share', 'bookmark' ), 'caseSensitive' => true ) ) ),
						),
					),
				),
				'limit'           => 10,
			),
			900,
			$force
		);
		if ( is_wp_error( $action_report ) ) {
			$warnings[] = $action_report->get_error_message();
		} else {
			foreach ( self::report_rows( $action_report ) as $row ) {
				$count = self::number( $row['eventCount'] ?? 0 );
				if ( 'application_click' === ( $row['eventName'] ?? '' ) ) {
					$base['events']['applicationClicks'] += $count;
				} elseif ( 'share' === ( $row['eventName'] ?? '' ) ) {
					$base['events']['shares'] += $count;
				} elseif ( 'bookmark' === ( $row['eventName'] ?? '' ) ) {
					$base['events']['bookmarks'] += $count;
				}
			}
		}
		$base['conversionRate'] = $base['summary']['last30Days'] ? round( ( $base['events']['applicationClicks'] / $base['summary']['last30Days'] ) * 100, 1 ) : 0;
		if ( is_wp_error( $realtime_report ) ) {
			$warnings[] = $realtime_report->get_error_message();
		} else {
			$normalized_title = self::clean_analytics_title( get_the_title( $post ) );
			foreach ( self::report_rows( $realtime_report ) as $row ) {
				if ( $normalized_title !== self::clean_analytics_title( (string) ( $row['unifiedScreenName'] ?? '' ) ) ) {
					continue;
				}
				$minute = absint( $row['minutesAgo'] ?? 0 );
				$views  = self::number( $row['screenPageViews'] ?? 0 );
				if ( $minute <= 29 ) {
					$base['summary']['last30Minutes'] += $views;
				}
				if ( $minute <= 4 ) {
					$base['summary']['last5Minutes'] += $views;
				}
				if ( 0 === $minute ) {
					$base['summary']['current'] = max( $base['summary']['current'], self::number( $row['activeUsers'] ?? 0 ) );
				}
			}
		}

		$base['warnings'] = array_values( array_unique( $warnings ) );
		$base['error']    = $warnings && empty( $base['series'] ) && ! array_sum( $base['summary'] ) ? reset( $warnings ) : '';
		return rest_ensure_response( $base );
	}

	private static function configuration() {
		$service_json = self::env( 'DIXCOVERHUB_GA4_SERVICE_ACCOUNT_JSON', 'GA4_SERVICE_ACCOUNT_JSON' );
		$service = $service_json ? json_decode( self::unquote( $service_json ), true ) : array();
		$property = self::first_config( array( array( 'DIXCOVERHUB_GA4_PROPERTY_ID', 'GA4_PROPERTY_ID' ) ) );
		$email = self::first_config( array( array( 'DIXCOVERHUB_GA4_CLIENT_EMAIL', 'GA4_CLIENT_EMAIL' ) ) );
		$key = self::first_config( array( array( 'DIXCOVERHUB_GA4_PRIVATE_KEY', 'GA4_PRIVATE_KEY' ) ) );
		if ( is_array( $service ) ) { $email = $email ?: (string) ( $service['client_email'] ?? '' ); $key = $key ?: (string) ( $service['private_key'] ?? '' ); }
		$key = self::normalize_private_key( $key );
		$measurement = self::first_config( array( array( 'DIXCOVERHUB_GA4_MEASUREMENT_ID', 'GA4_MEASUREMENT_ID' ), array( '', 'NEXT_PUBLIC_GA_MEASUREMENT_ID' ) ) );
		if ( ! preg_match( '/^G-[A-Z0-9]+$/i', $measurement ) ) { $measurement = ''; }
		$missing = array();
		if ( ! preg_match( '/^\d{4,20}$/', $property ) ) { $missing[] = 'GA4_PROPERTY_ID'; }
		if ( ! is_email( $email ) ) { $missing[] = 'GA4_CLIENT_EMAIL'; }
		if ( ! function_exists( 'openssl_pkey_get_private' ) ) { $missing[] = 'PHP OpenSSL extension'; }
		elseif ( ! $key || ! @openssl_pkey_get_private( $key ) ) { $missing[] = 'GA4_PRIVATE_KEY'; }
		$timezone = self::first_config( array( array( 'DIXCOVERHUB_GA4_TIMEZONE', 'GA4_TIMEZONE' ) ) );
		if ( ! $timezone || ! in_array( $timezone, timezone_identifiers_list(), true ) ) { $timezone = 'Africa/Lagos'; }
		return array( 'propertyId' => $property, 'clientEmail' => $email, 'privateKey' => $key, 'measurementId' => $measurement, 'measurementConfigured' => (bool) $measurement, 'configured' => empty( $missing ), 'missing' => $missing, 'timezone' => $timezone );
	}

	private static function first_config( $pairs ) {
		foreach ( $pairs as $pair ) { $value = self::env( $pair[0], $pair[1] ); if ( $value ) { return $value; } }
		return '';
	}

	/** Return the current and preceding GA4-local clock-hour buckets. */
	private static function last_hour_keys( $timezone ) {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( $timezone ) );
		return array( $now->format( 'YmdH' ), $now->modify( '-1 hour' )->format( 'YmdH' ) );
	}

	private static function env( $constant, $environment ) {
		if ( class_exists( 'DixcoverHub_Core' ) ) { return DixcoverHub_Core::config( $constant, $environment ); }
		if ( $constant && defined( $constant ) && is_scalar( constant( $constant ) ) ) { return trim( (string) constant( $constant ) ); }
		$value = $environment ? getenv( $environment ) : false;
		return false !== $value ? trim( (string) $value ) : '';
	}

	private static function unquote( $value ) {
		$value = trim( (string) $value );
		if ( strlen( $value ) > 1 && ( ( '"' === $value[0] && '"' === substr( $value, -1 ) ) || ( "'" === $value[0] && "'" === substr( $value, -1 ) ) ) ) { $value = substr( $value, 1, -1 ); }
		return $value;
	}

	private static function normalize_private_key( $value ) {
		$value = self::unquote( (string) $value );
		if ( '{' === substr( ltrim( $value ), 0, 1 ) ) { $json = json_decode( $value, true ); if ( is_array( $json ) && ! empty( $json['private_key'] ) ) { $value = $json['private_key']; } }
		$value = preg_replace( '/\\\\+r/', "\r", $value );
		$value = preg_replace( '/\\\\+n/', "\n", $value );
		return trim( str_replace( "\r", '', $value ) );
	}

	private static function period_range( $period ) {
		if ( 'today' === $period ) { return array( 'label' => 'Today', 'current' => array( 'startDate' => 'today', 'endDate' => 'today' ), 'previous' => array( 'startDate' => 'yesterday', 'endDate' => 'yesterday' ) ); }
		if ( 'yesterday' === $period ) { return array( 'label' => 'Yesterday', 'current' => array( 'startDate' => 'yesterday', 'endDate' => 'yesterday' ), 'previous' => array( 'startDate' => '2daysAgo', 'endDate' => '2daysAgo' ) ); }
		$days = '7d' === $period ? 7 : ( '90d' === $period ? 90 : ( '1y' === $period ? 365 : 30 ) );
		return array( 'label' => '1y' === $period ? 'Last year' : 'Last ' . $days . ' days', 'current' => array( 'startDate' => ( $days - 1 ) . 'daysAgo', 'endDate' => 'today' ), 'previous' => array( 'startDate' => ( $days * 2 - 1 ) . 'daysAgo', 'endDate' => $days . 'daysAgo' ) );
	}

	private static function metrics( $names ) { return array_map( static function ( $name ) { return array( 'name' => $name ); }, $names ); }

	private static function aggregate_scorecards( $rows ) {
		$empty = array( 'views' => 0, 'activeUsers' => 0, 'sessions' => 0, 'engagedSessions' => 0, 'engagementRate' => 0, 'conversions' => 0 );
		$current = $empty; $previous = $empty;
		foreach ( $rows as $row ) {
			$target = in_array( $row['dateRange'] ?? '', array( 'previous', 'date_range_1' ), true ) ? 'previous' : 'current';
			$values = array( 'views' => self::number( $row['screenPageViews'] ?? 0 ), 'activeUsers' => self::number( $row['activeUsers'] ?? 0 ), 'sessions' => self::number( $row['sessions'] ?? 0 ), 'engagedSessions' => self::number( $row['engagedSessions'] ?? 0 ), 'engagementRate' => self::number( $row['engagementRate'] ?? 0 ), 'conversions' => self::number( $row['conversions'] ?? 0 ) );
			if ( 'previous' === $target ) { $previous = $values; } else { $current = $values; }
		}
		$changes = array();
		foreach ( $current as $key => $value ) { $old = $previous[ $key ]; $changes[ $key ] = $old ? round( ( ( $value - $old ) / $old ) * 100, 1 ) : null; }
		return array( 'current' => $current, 'previous' => $previous, 'changes' => $changes );
	}

	private static function google_report( $property, $method, $body, $ttl, $force_refresh = false ) {
		if ( ! in_array( $method, array( 'runReport', 'runRealtimeReport' ), true ) ) { return new WP_Error( 'dh_ga_method', __( 'Unsupported Google Analytics report.', 'dixcoverhub-core' ) ); }
		$cache_key = 'dh_ga4_' . md5( $property . ':' . $method . ':' . wp_json_encode( $body ) );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) && ! $force_refresh ) { return $cached; }
		if ( $force_refresh ) { delete_transient( $cache_key ); }
		$token = self::google_access_token();
		if ( is_wp_error( $token ) ) { return $token; }
		$url = 'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode( $property ) . ':' . $method;
		$response = wp_remote_post( $url, array( 'timeout' => 20, 'redirection' => 0, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'dh_ga_request', __( 'Google Analytics could not be reached. Check the server connection and try again.', 'dixcoverhub-core' ) ); }
		$status = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			if ( 401 === $status ) { return new WP_Error( 'dh_ga_auth', __( 'Google authentication failed. Check the GA4 service account credentials.', 'dixcoverhub-core' ) ); }
			if ( 403 === $status ) { return new WP_Error( 'dh_ga_permission', __( 'Google Analytics denied access. Enable the Analytics Data API and grant this service account Viewer access to the GA4 property.', 'dixcoverhub-core' ) ); }
			if ( 429 === $status ) { return new WP_Error( 'dh_ga_quota', __( 'Google Analytics report quota was reached. Wait briefly and refresh.', 'dixcoverhub-core' ) ); }
			return new WP_Error( 'dh_ga_report', __( 'Google Analytics rejected this report. Check the property ID and GA4 configuration.', 'dixcoverhub-core' ) );
		}
		set_transient( $cache_key, $data, max( 20, absint( $ttl ) ) );
		return $data;
	}

	private static function google_access_token() {
		if ( self::$access_token && self::$access_expires > time() + 60 ) { return self::$access_token; }
		$config = self::configuration();
		$key = openssl_pkey_get_private( $config['privateKey'] );
		if ( ! $key ) { return new WP_Error( 'dh_ga_key', __( 'The Google service account private key is invalid.', 'dixcoverhub-core' ) ); }
		$now = time();
		$header = self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::base64url( wp_json_encode( array( 'iss' => $config['clientEmail'], 'scope' => self::SCOPE, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600 ) ) );
		$unsigned = $header . '.' . $claims;
		$signature = '';
		if ( ! openssl_sign( $unsigned, $signature, $key, OPENSSL_ALGO_SHA256 ) ) { return new WP_Error( 'dh_ga_sign', __( 'Google authentication could not sign the service account request.', 'dixcoverhub-core' ) ); }
		$assertion = $unsigned . '.' . self::base64url( $signature );
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array( 'timeout' => 15, 'redirection' => 0, 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ), 'body' => http_build_query( array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion ), '', '&', PHP_QUERY_RFC3986 ) ) );
		if ( is_wp_error( $response ) ) { return new WP_Error( 'dh_ga_auth_connection', __( 'Google authentication could not be reached.', 'dixcoverhub-core' ) ); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) { return new WP_Error( 'dh_ga_auth_rejected', __( 'Google did not accept the service account credentials. Check the private key, email, and Analytics read-only access.', 'dixcoverhub-core' ) ); }
		self::$access_token = sanitize_text_field( $data['access_token'] );
		self::$access_expires = $now + max( 300, min( 3500, absint( $data['expires_in'] ?? 3600 ) ) );
		return self::$access_token;
	}

	private static function base64url( $value ) { return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' ); }

	private static function report_rows( $report ) {
		$dimensions = wp_list_pluck( (array) ( $report['dimensionHeaders'] ?? array() ), 'name' );
		$metrics = wp_list_pluck( (array) ( $report['metricHeaders'] ?? array() ), 'name' );
		$output = array();
		foreach ( (array) ( $report['rows'] ?? array() ) as $row ) {
			$values = array();
			foreach ( $dimensions as $index => $name ) { $values[ $name ] = (string) ( $row['dimensionValues'][ $index ]['value'] ?? '' ); }
			foreach ( $metrics as $index => $name ) { $values[ $name ] = (float) ( $row['metricValues'][ $index ]['value'] ?? 0 ); }
			$output[] = $values;
		}
		return $output;
	}

	private static function content_lookup() {
		$cached = get_transient( 'dixcoverhub_ga4_content_lookup_v2' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'posts_per_page' => 5000, 'orderby' => 'none', 'no_found_rows' => true ) );
		$map = array();
		foreach ( $posts as $post ) {
			$path = self::clean_path( wp_parse_url( get_permalink( $post ), PHP_URL_PATH ) ?: '/' );
			$author_id = absint( $post->post_author );
			$author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
			$map[ $path ] = array( 'title' => get_the_title( $post ), 'editUrl' => get_edit_post_link( $post->ID, 'raw' ), 'type' => 'page' === $post->post_type ? 'page' : 'article', 'postId' => (int) $post->ID, 'authorId' => $author_id, 'authorName' => $author_name ?: __( 'Unassigned', 'dixcoverhub-core' ) );
		}
		set_transient( 'dixcoverhub_ga4_content_lookup_v2', $map, 15 * MINUTE_IN_SECONDS );
		return $map;
	}

	/** Match GA4 page paths with or without WordPress's trailing slash. */
	private static function page_path_filter( $path ) {
		$path = self::clean_path( $path );
		$paths = '/' === $path ? array( '/' ) : array( $path, $path . '/' );
		$expressions = array();
		foreach ( array_unique( $paths ) as $variant ) {
			$expressions[] = array( 'filter' => array( 'fieldName' => 'pagePath', 'stringFilter' => array( 'matchType' => 'EXACT', 'value' => $variant ) ) );
		}
		return count( $expressions ) === 1 ? $expressions[0] : array( 'orGroup' => array( 'expressions' => $expressions ) );
	}

	/** Normalize the title and remove the site suffix GA4 may append. */
	private static function clean_analytics_title( $title ) {
		$site_name = trim( (string) get_bloginfo( 'name' ) );
		if ( $site_name ) {
			$title = preg_replace( '/\s+[|–—-]\s+' . preg_quote( $site_name, '/' ) . '\s*$/iu', '', (string) $title );
		}
		$title = preg_replace( '/\s+/u', ' ', trim( (string) $title ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
	}

	private static function clean_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$path = strtolower( rawurldecode( strtok( $path, '?#' ) ) );
		if ( strlen( $path ) > 1 ) { $path = untrailingslashit( $path ); }
		return $path ?: '/';
	}

	private static function number( $value ) { $number = (float) $value; return is_finite( $number ) ? $number : 0; }
}
