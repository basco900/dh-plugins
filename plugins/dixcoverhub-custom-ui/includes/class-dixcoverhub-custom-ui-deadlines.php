<?php
/**
 * Timezone-aware opportunity deadline overview and period pages.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Deadlines {
	const ROUTE_VAR       = 'dh_deadline_page';
	const PERIOD_VAR      = 'dh_deadline_period';
	const REWRITE_OPTION  = 'dixcoverhub_custom_ui_deadline_rewrite_version';
	const MAX_RANGE_DAYS  = 366;

	/** Register only the opt-in route and its render hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 100 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'pre_handle_404', array( __CLASS__, 'prevent_route_404' ), 10, 2 );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'replace_deadlines_page' ), 25 );
		add_shortcode( 'dixcoverhub_deadlines', array( __CLASS__, 'shortcode' ) );
	}

	/** Add the public paths only while the Deadlines feature is active. */
	public static function add_rewrite_rules() {
		if ( ! self::enabled() ) {
			return;
		}
		add_rewrite_rule( '^deadlines/([^/]+)/?$', 'index.php?' . self::ROUTE_VAR . '=1&' . self::PERIOD_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^deadlines/?$', 'index.php?' . self::ROUTE_VAR . '=1', 'top' );
	}

	/** Refresh rewrite rules once when the plugin version or activation changes. */
	public static function maybe_flush_rewrite_rules() {
		$version = DIXCOVERHUB_CUSTOM_UI_VERSION . ':' . (int) self::enabled();
		if ( $version === get_option( self::REWRITE_OPTION, '' ) ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, $version, false );
	}

	/** Register route query variables. */
	public static function query_vars( $vars ) {
		$vars[] = self::ROUTE_VAR;
		$vars[] = self::PERIOD_VAR;
		return $vars;
	}

	/** Treat the overview and supported period paths as valid pages. */
	public static function prevent_route_404( $preempt, $query ) {
		if ( ! self::enabled() || ! ( $query instanceof WP_Query ) || ! $query->get( self::ROUTE_VAR ) ) {
			return $preempt;
		}
		$period = sanitize_key( (string) $query->get( self::PERIOD_VAR ) );
		if ( $period && ! self::window( $period, wp_timezone() ) ) {
			$query->set_404();
			status_header( 404 );
			return true;
		}
		return true;
	}

	/** Load a theme-wrapped template for the custom route or matching page. */
	public static function template( $template ) {
		if ( ! self::enabled() || is_admin() || ( ! get_query_var( self::ROUTE_VAR ) && ! is_page( 'deadlines' ) ) ) {
			return $template;
		}
		$period = sanitize_key( (string) get_query_var( self::PERIOD_VAR ) );
		if ( is_404() || ( $period && ! self::window( $period, wp_timezone() ) ) ) {
			status_header( 404 );
			$not_found_template = get_404_template();
			return $not_found_template ? $not_found_template : $template;
		}
		$deadline_template = dirname( __DIR__ ) . '/templates/deadlines.php';
		return is_readable( $deadline_template ) ? $deadline_template : $template;
	}

	/** Load page CSS only on the route, page, or shortcode host. */
	public static function enqueue_assets() {
		if ( ! self::enabled() || is_admin() ) {
			return;
		}
		$should_enqueue = (bool) get_query_var( self::ROUTE_VAR ) || is_page( 'deadlines' );
		if ( ! $should_enqueue && is_singular() ) {
			$queried = get_queried_object();
			$should_enqueue = $queried instanceof WP_Post && has_shortcode( $queried->post_content, 'dixcoverhub_deadlines' );
		}
		if ( $should_enqueue ) {
			wp_enqueue_style( 'dixcoverhub-deadlines', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/deadlines.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
		}
	}

	/** Replace a regular page with this slug only after explicit activation. */
	public static function replace_deadlines_page( $content ) {
		if ( self::enabled() && ! is_admin() && is_page( 'deadlines' ) && in_the_loop() && is_main_query() && ! has_shortcode( $content, 'dixcoverhub_deadlines' ) ) {
			return self::render();
		}
		return $content;
	}

	/** Render the configured deadlines page in a Shortcode block. */
	public static function shortcode() {
		return self::enabled() ? self::render() : '';
	}

	/** Render either the rolling overview or a date-window results page. */
	public static function render() {
		if ( ! self::enabled() ) {
			return '';
		}
		$options  = DixcoverHub_Custom_UI::options();
		$timezone = wp_timezone();
		$windows  = self::windows( $timezone );
		$accent   = sanitize_hex_color( $options['primary_color'] ) ?: '#611f69';

		$period = sanitize_key( (string) get_query_var( self::PERIOD_VAR ) );
		if ( $period ) {
			$window = self::window( $period, $timezone );
			if ( ! $window ) {
				return '';
			}
			$results = self::query_deadlines( $window['from'], $window['to'], self::requested_page(), absint( $options['deadline_page_results_per_page'] ) );
			return self::render_results_page( $options, $windows, $timezone, $accent, $results, $window, null, '', self::sidebar_posts() );
		}

		$from_input = self::query_value( 'from' );
		$to_input   = self::query_value( 'to' );
		if ( '' !== $from_input || '' !== $to_input ) {
			$range = self::parse_range( $from_input, $to_input, $timezone );
			$error = self::range_error( $from_input, $to_input, $range );
			if ( $range ) {
				$results = self::query_deadlines( $range['from'], $range['to'], self::requested_page(), absint( $options['deadline_page_results_per_page'] ) );
				return self::render_results_page( $options, $windows, $timezone, $accent, $results, null, $range, '', self::sidebar_posts() );
			}
			$overview = self::overview( $windows, absint( $options['deadline_page_preview_per_window'] ) );
			$range_input = array( 'from' => $from_input, 'to' => $to_input );
			return self::render_overview_page( $options, $windows, $overview, $timezone, $accent, self::sidebar_posts(), $range_input, $error );
		}

		$overview = self::overview( $windows, absint( $options['deadline_page_preview_per_window'] ) );
		return self::render_overview_page( $options, $windows, $overview, $timezone, $accent, self::sidebar_posts(), array(), '' );
	}

	/** Whether this individual feature is active. */
	private static function enabled() {
		$options = DixcoverHub_Custom_UI::options();
		return ! empty( $options['deadline_page_enabled'] );
	}

	/** Return the seven calendar windows in the WordPress site timezone. */
	private static function windows( $timezone ) {
		$today         = current_datetime()->setTimezone( $timezone )->format( 'Y-m-d' );
		$tomorrow      = self::add_days( $today, 1, $timezone );
		$in_two_days   = self::add_days( $today, 2, $timezone );
		$today_date    = self::date_object( $today, $timezone );
		$week_start    = self::add_days( $today, 1 - (int) $today_date->format( 'N' ), $timezone );
		$week_end      = self::add_days( $week_start, 6, $timezone );
		$month_start   = $today_date->modify( 'first day of this month' )->format( 'Y-m-d' );
		$next_start    = self::date_object( $month_start, $timezone )->modify( 'first day of next month' )->format( 'Y-m-d' );
		$next_end      = self::add_days( self::date_object( $next_start, $timezone )->modify( 'first day of next month' )->format( 'Y-m-d' ), -1, $timezone );
		$later_start   = self::date_object( $month_start, $timezone )->modify( 'first day of +2 months' )->format( 'Y-m-d' );
		$year_end      = $today_date->format( 'Y' ) . '-12-31';

		return array(
			self::make_window( 'today', 'Deadlines today', 'TODAY', 'The opportunities closing before the day is over.', $today, $today ),
			self::make_window( 'tomorrow', 'Deadlines tomorrow', 'TOMORROW', 'A clear head start on what needs your attention next.', $tomorrow, $tomorrow ),
			self::make_window( 'in-2-days', 'Deadlines in 2 days', 'IN 2 DAYS', 'Opportunities worth preparing for right away.', $in_two_days, $in_two_days ),
			self::make_window( 'this-week', 'Deadlines this week', 'THIS WEEK', 'Every published opportunity closing from Monday through Sunday.', $week_start, $week_end ),
			self::make_window( 'this-month', 'Deadlines this month', 'THIS MONTH', 'A wider view of everything closing before the month ends.', $month_start, self::add_days( $next_start, -1, $timezone ) ),
			self::make_window( 'next-month', 'Deadlines next month', 'NEXT MONTH', 'Plan ahead for the next full month of closing dates.', $next_start, $next_end ),
			self::make_window( 'later-this-year', 'Deadlines later this year', 'LATER THIS YEAR', 'Keep longer-horizon opportunities on your radar.', $later_start, $year_end ),
		);
	}

	/** Create a stable, URL-addressable period definition. */
	private static function make_window( $key, $label, $eyebrow, $description, $from, $to ) {
		return array(
			'key'         => $key,
			'label'       => $label,
			'eyebrow'     => $eyebrow,
			'description' => $description,
			'from'        => $from,
			'to'          => $to,
			'href'        => home_url( '/deadlines/' . $key . '/' ),
		);
	}

	/** Find one valid window by its public slug. */
	private static function window( $key, $timezone ) {
		foreach ( self::windows( $timezone ) as $window ) {
			if ( $window['key'] === $key ) {
				return $window;
			}
		}
		return null;
	}

	/** Load a compact page of results and total count for each overview window. */
	private static function overview( $windows, $limit ) {
		$overview = array();
		foreach ( $windows as $window ) {
			$overview[ $window['key'] ] = self::query_deadlines( $window['from'], $window['to'], 1, $limit );
		}
		return $overview;
	}

	/** Query deadline metadata using inclusive, date-only site-calendar ranges. */
	private static function query_deadlines( $from, $to, $page, $limit ) {
		$page  = max( 1, absint( $page ) );
		$limit = min( 60, max( 1, absint( $limit ) ) );
		if ( ! self::valid_date( $from, wp_timezone() ) || ! self::valid_date( $to, wp_timezone() ) || $from > $to ) {
			return array( 'items' => array(), 'total' => 0, 'page' => $page, 'page_size' => $limit, 'has_more' => false );
		}
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'paged'                  => $page,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'meta_key'               => '_dixcoverhub_deadline',
				'meta_type'              => 'DATE',
				'meta_query'             => array(
					array(
						'key'     => '_dixcoverhub_deadline',
					'value'   => array( $from, $to ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
					),
				),
				'orderby'                => array( 'meta_value' => 'ASC', 'date' => 'DESC' ),
			)
		);
		$total = (int) $query->found_posts;
		return array(
			'items'     => $query->posts,
			'total'     => $total,
			'page'      => $page,
			'page_size' => $limit,
			'has_more'  => $page * $limit < $total,
		);
	}

	/** Parse and validate a user-selected calendar range. */
	private static function parse_range( $from, $to, $timezone ) {
		if ( ! self::valid_date( $from, $timezone ) || ! self::valid_date( $to, $timezone ) || $from > $to ) {
			return null;
		}
		$start = self::date_object( $from, $timezone );
		$end   = self::date_object( $to, $timezone );
		$days  = (int) $start->diff( $end )->format( '%a' ) + 1;
		if ( $days > self::MAX_RANGE_DAYS ) {
			return null;
		}
		return array( 'from' => $from, 'to' => $to );
	}

	/** Give a clear validation reason while keeping the entered values visible. */
	private static function range_error( $from, $to, $range ) {
		if ( '' === $from || '' === $to ) {
			return __( 'Choose both a start date and an end date.', 'dixcoverhub-custom-ui' );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) && $from > $to ) {
			return __( 'The end date must be on or after the start date.', 'dixcoverhub-custom-ui' );
		}
		return $range ? '' : __( 'Choose two valid dates within a 12-month window.', 'dixcoverhub-custom-ui' );
	}

	/** Validate a date-only value without browser or UTC conversion. */
	private static function valid_date( $value, $timezone ) {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
		$errors = DateTimeImmutable::getLastErrors();
		return $date && $date->format( 'Y-m-d' ) === $value && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) );
	}

	/** Construct a date-only value in the requested site timezone. */
	private static function date_object( $value, $timezone ) {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
		return $date instanceof DateTimeImmutable ? $date : new DateTimeImmutable( 'now', $timezone );
	}

	/** Move by whole local calendar days, including across month and year edges. */
	private static function add_days( $value, $days, $timezone ) {
		return self::date_object( $value, $timezone )->modify( ( $days >= 0 ? '+' : '' ) . (int) $days . ' days' )->format( 'Y-m-d' );
	}

	/** Read a scalar query parameter after WordPress unslashing. */
	private static function query_value( $key ) {
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) ) : '';
	}

	/** Sanitize the requested pagination number. */
	private static function requested_page() {
		return max( 1, absint( self::query_value( 'page' ) ) );
	}

	/** Load the latest published posts used by the optional sidebar. */
	private static function sidebar_posts() {
		return get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 18,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'meta_query'             => array( array( 'key' => '_dixcoverhub_opportunity_data', 'compare' => 'EXISTS' ) ),
			)
		);
	}

	/** Render the overview dashboard. */
	private static function render_overview_page( $options, $windows, $overview, $timezone, $accent, $sidebar_posts, $range_input, $range_error ) {
		$metrics = array(
			array( 'label' => __( 'Today', 'dixcoverhub-custom-ui' ), 'value' => $overview['today']['total'], 'note' => __( 'closing today', 'dixcoverhub-custom-ui' ), 'tone' => 'rose' ),
			array( 'label' => __( 'This week', 'dixcoverhub-custom-ui' ), 'value' => $overview['this-week']['total'], 'note' => __( 'through Sunday', 'dixcoverhub-custom-ui' ), 'tone' => 'green' ),
			array( 'label' => __( 'This month', 'dixcoverhub-custom-ui' ), 'value' => $overview['this-month']['total'], 'note' => __( 'before month end', 'dixcoverhub-custom-ui' ), 'tone' => 'amber' ),
			array( 'label' => __( 'Later this year', 'dixcoverhub-custom-ui' ), 'value' => $overview['later-this-year']['total'], 'note' => __( 'longer horizon', 'dixcoverhub-custom-ui' ), 'tone' => 'violet' ),
		);
		$sidebar_lists = self::sidebar_lists( $sidebar_posts );
		ob_start();
		?>
		<main class="dh-deadlines-page" style="--dh-deadline-accent:<?php echo esc_attr( $accent ); ?>;">
			<div class="dh-deadlines-shell">
				<header class="dh-deadlines-heading"><div><p class="dh-deadlines-eyebrow"><span aria-hidden="true"><?php echo self::icon( 'File01Icon', 'dh-deadlines-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?></span><?php esc_html_e( 'DEADLINE PLANNER', 'dixcoverhub-custom-ui' ); ?></p><h1><?php echo esc_html( $options['deadline_page_heading'] ); ?></h1><p><?php echo esc_html( $options['deadline_page_intro'] ); ?></p></div><span class="dh-deadlines-timezone"><?php echo esc_html( sprintf( __( 'Dates shown in %s', 'dixcoverhub-custom-ui' ), $timezone->getName() ) ); ?></span></header>
				<section class="dh-deadlines-metrics" aria-label="<?php esc_attr_e( 'Deadline summary', 'dixcoverhub-custom-ui' ); ?>"><?php foreach ( $metrics as $metric ) : ?><div class="dh-deadline-metric is-<?php echo esc_attr( $metric['tone'] ); ?>"><span class="dh-deadline-metric-icon" aria-hidden="true"><?php echo self::icon( 'File01Icon', 'dh-deadlines-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?></span><div><div class="dh-deadline-metric-line"><strong><?php echo number_format_i18n( $metric['value'] ); ?></strong><span><?php echo esc_html( $metric['label'] ); ?></span></div><p><?php echo esc_html( $metric['note'] ); ?></p></div></div><?php endforeach; ?></section>
				<?php echo self::date_range_form( $range_input, $range_error ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values are escaped in the renderer. ?>
				<div class="dh-deadlines-layout"><div class="dh-deadlines-main"><header class="dh-deadlines-list-heading"><div><h2><?php esc_html_e( 'Upcoming deadlines', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Choose a window to see its published opportunities.', 'dixcoverhub-custom-ui' ); ?></p></div><span><?php esc_html_e( 'Browse by closing date', 'dixcoverhub-custom-ui' ); ?></span></header><div class="dh-deadline-window-list"><?php foreach ( $windows as $window ) { echo self::render_window( $window, $overview[ $window['key'] ], $timezone ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values. */ } ?></div></div>
					<?php if ( ! empty( $options['deadline_page_sidebar_enabled'] ) ) : ?><?php echo self::render_sidebar( $sidebar_lists, $timezone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values. ?><?php endif; ?>
				</div>
			</div>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	/** Render one overview window with its preview and full-list link. */
	private static function render_window( $window, $results, $timezone ) {
		ob_start();
		?>
		<section class="dh-deadline-window">
			<header><span class="dh-deadline-window-marker" aria-hidden="true"></span><div><p><?php echo esc_html( $window['eyebrow'] ); ?></p><h3><?php echo esc_html( preg_replace( '/^Deadlines\s+/i', '', $window['label'] ) ); ?></h3><span><?php echo esc_html( $window['description'] ); ?></span></div><strong class="dh-deadline-window-count"><?php echo number_format_i18n( $results['total'] ); ?></strong></header>
			<div class="dh-deadline-window-items"><?php if ( $results['items'] ) : foreach ( $results['items'] as $post ) { echo self::render_deadline_card( $post, $timezone ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values. */ } else : ?><p class="dh-deadline-window-empty"><?php esc_html_e( 'No published opportunities close in this window.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?></div>
			<footer><a href="<?php echo esc_url( $window['href'] ); ?>"><?php echo esc_html( sprintf( __( 'See all %s', 'dixcoverhub-custom-ui' ), strtolower( $window['label'] ) ) ); ?> <span aria-hidden="true">→</span></a></footer>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Render one compact deadline entry. */
	private static function render_deadline_card( $post, $timezone, $row = false ) {
		$post_id   = (int) $post->ID;
		$data      = get_post_meta( $post_id, '_dixcoverhub_opportunity_data', true );
		$data      = is_array( $data ) ? $data : array();
		$deadline  = get_post_meta( $post_id, '_dixcoverhub_deadline', true );
		$deadline  = is_string( $deadline ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ? $deadline : (string) ( $data['deadline'] ?? '' );
		$category  = get_the_category( $post_id );
		$category  = $category ? $category[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' );
		$provider  = sanitize_text_field( (string) ( get_post_meta( $post_id, '_dixcoverhub_provider_name', true ) ?: ( $data['provider_name'] ?? '' ) ) );
		$location  = sanitize_text_field( (string) ( get_post_meta( $post_id, '_dixcoverhub_location', true ) ?: ( $data['location'] ?? '' ) ) );
		$image     = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
		$alt       = $image ? (string) get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true ) : '';
		$url       = get_permalink( $post_id );
		$title     = get_the_title( $post_id );
		$timestamp = self::date_timestamp( $deadline, $timezone );
		$classes   = 'dh-deadline-card' . ( $row ? ' is-row' : '' );
		ob_start();
		?>
		<article class="<?php echo esc_attr( $classes ); ?>">
			<?php if ( $row ) : ?><a class="dh-deadline-row-image" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'dixcoverhub-custom-ui' ), $title ) ); ?>"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $alt ? $alt : $title ); ?>" loading="lazy" decoding="async" /><?php else : ?><?php echo self::icon( 'File01Icon', 'dh-deadlines-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?><?php endif; ?></a><?php else : ?><a class="dh-deadline-date-tile" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'dixcoverhub-custom-ui' ), $title ) ); ?>"><span><?php echo $timestamp ? esc_html( wp_date( 'M', $timestamp, $timezone ) ) : ''; ?></span><strong><?php echo $timestamp ? esc_html( wp_date( 'd', $timestamp, $timezone ) ) : '–'; ?></strong></a><?php endif; ?>
			<div class="dh-deadline-card-copy"><div class="dh-deadline-card-meta"><span><?php echo esc_html( $category ); ?></span><i aria-hidden="true">·</i><span><?php echo esc_html( $provider ? $provider : __( 'Verified opportunity', 'dixcoverhub-custom-ui' ) ); ?></span></div><a class="dh-deadline-card-title" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a><div class="dh-deadline-card-details"><?php if ( $location ) : ?><span><?php echo self::icon( 'Compass01Icon', 'dh-deadlines-detail-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?><?php echo esc_html( $location ); ?></span><?php endif; ?><?php if ( $timestamp ) : ?><time datetime="<?php echo esc_attr( $deadline ); ?>"><?php echo self::icon( 'Award01Icon', 'dh-deadlines-detail-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?><?php echo esc_html( sprintf( __( 'Closes %s', 'dixcoverhub-custom-ui' ), wp_date( 'M j', $timestamp, $timezone ) ) ); ?></time><?php endif; ?></div></div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/** Render the custom calendar range form and any validation message. */
	private static function date_range_form( $range, $error ) {
		$range = is_array( $range ) ? $range : array();
		ob_start();
		?>
		<section class="dh-deadline-range"><header><div><span class="dh-deadline-range-icon" aria-hidden="true"><?php echo self::icon( 'File01Icon', 'dh-deadlines-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?></span><div><h2><?php esc_html_e( 'Find a date window', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Build a focused list of closing opportunities.', 'dixcoverhub-custom-ui' ); ?></p></div></div><span><?php esc_html_e( 'Up to 12 months', 'dixcoverhub-custom-ui' ); ?></span></header><form action="<?php echo esc_url( home_url( '/deadlines/' ) ); ?>" method="get"><label><?php esc_html_e( 'From', 'dixcoverhub-custom-ui' ); ?><input name="from" type="date" value="<?php echo esc_attr( $range['from'] ?? '' ); ?>" required /></label><label><?php esc_html_e( 'To', 'dixcoverhub-custom-ui' ); ?><input name="to" type="date" value="<?php echo esc_attr( $range['to'] ?? '' ); ?>" required /></label><button type="submit"><?php esc_html_e( 'Show deadlines', 'dixcoverhub-custom-ui' ); ?><span aria-hidden="true">→</span></button></form><?php if ( $error ) : ?><p class="dh-deadline-range-error" role="alert"><?php echo esc_html( $error ); ?></p><?php endif; ?></section>
		<?php
		return (string) ob_get_clean();
	}

	/** Render paginated results for one rolling or custom date range. */
	private static function render_results_page( $options, $windows, $timezone, $accent, $results, $window, $custom_range, $range_error, $sidebar_posts ) {
		$heading = $custom_range ? __( 'Your deadline window', 'dixcoverhub-custom-ui' ) : ( $window['label'] ?? __( 'Deadlines', 'dixcoverhub-custom-ui' ) );
		$from    = $custom_range ? $custom_range['from'] : $window['from'];
		$to      = $custom_range ? $custom_range['to'] : $window['to'];
		$range_label = self::format_range( $from, $to, $timezone );
		$base_url = $custom_range ? add_query_arg( array( 'from' => $from, 'to' => $to ), home_url( '/deadlines/' ) ) : $window['href'];
		$sidebar_lists = self::sidebar_lists( $sidebar_posts ? $sidebar_posts : $results['items'] );
		ob_start();
		?>
		<main class="dh-deadlines-page" style="--dh-deadline-accent:<?php echo esc_attr( $accent ); ?>;">
			<div class="dh-deadlines-shell dh-deadlines-shell--results"><header class="dh-deadlines-results-heading"><div><a class="dh-deadlines-back" href="<?php echo esc_url( home_url( '/deadlines/' ) ); ?>"><span aria-hidden="true">←</span><?php esc_html_e( 'All deadline windows', 'dixcoverhub-custom-ui' ); ?></a><h1><?php echo esc_html( $heading ); ?></h1><p><?php echo esc_html( sprintf( __( '%1$s · dates shown in %2$s', 'dixcoverhub-custom-ui' ), $range_label, $timezone->getName() ) ); ?></p></div><div class="dh-deadlines-published"><span><?php esc_html_e( 'PUBLISHED', 'dixcoverhub-custom-ui' ); ?></span><strong><?php echo number_format_i18n( $results['total'] ); ?></strong></div></header>
				<?php echo self::date_range_form( $custom_range, $range_error ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values are escaped in the renderer. ?>
				<div class="dh-deadlines-layout dh-deadlines-results-layout"><section class="dh-deadlines-results-list"><header><div><h2><?php esc_html_e( 'Every opportunity in this window', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Sorted by the soonest closing date.', 'dixcoverhub-custom-ui' ); ?></p></div><span><?php echo $results['items'] ? esc_html( sprintf( __( 'Showing %1$s–%2$s', 'dixcoverhub-custom-ui' ), number_format_i18n( ( $results['page'] - 1 ) * $results['page_size'] + 1 ), number_format_i18n( min( $results['page'] * $results['page_size'], $results['total'] ) ) ) ) : esc_html__( 'No matches', 'dixcoverhub-custom-ui' ); ?></span></header>
					<?php if ( $results['items'] ) : ?><div class="dh-deadline-results-grid"><?php foreach ( $results['items'] as $post ) { echo self::render_deadline_card( $post, $timezone, true ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values. */ } ?></div><?php else : ?><div class="dh-deadline-results-empty"><span aria-hidden="true"><?php echo self::icon( 'File01Icon', 'dh-deadlines-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?></span><h3><?php esc_html_e( 'Nothing closes in this window', 'dixcoverhub-custom-ui' ); ?></h3><p><?php esc_html_e( 'Try a wider date range or return to the deadline overview.', 'dixcoverhub-custom-ui' ); ?></p><a href="<?php echo esc_url( home_url( '/deadlines/' ) ); ?>"><?php esc_html_e( 'Browse all windows', 'dixcoverhub-custom-ui' ); ?></a></div><?php endif; ?>
					<?php echo self::pagination( $results, $base_url ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated URLs are escaped in the renderer. ?>
				</section><?php if ( ! empty( $options['deadline_page_sidebar_enabled'] ) ) : ?><?php echo self::render_sidebar( $sidebar_lists, $timezone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes all dynamic values. ?><?php endif; ?></div>
				<nav class="dh-deadlines-other-windows" aria-label="<?php esc_attr_e( 'Other deadline windows', 'dixcoverhub-custom-ui' ); ?>"><strong><?php esc_html_e( 'Browse another deadline window', 'dixcoverhub-custom-ui' ); ?></strong><div><?php foreach ( array_slice( $windows, 0, 6 ) as $other_window ) : ?><a href="<?php echo esc_url( $other_window['href'] ); ?>"><?php echo esc_html( $other_window['eyebrow'] ); ?></a><?php endforeach; ?></div></nav>
			</div>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	/** Preserve custom parameters while moving between result pages. */
	private static function pagination( $results, $base_url ) {
		if ( $results['total'] <= $results['page_size'] ) {
			return '';
		}
		$previous = $results['page'] > 1 ? add_query_arg( 'page', $results['page'] - 1, $base_url ) : '';
		$next     = $results['has_more'] ? add_query_arg( 'page', $results['page'] + 1, $base_url ) : '';
		ob_start();
		?>
		<nav class="dh-deadline-pagination" aria-label="<?php esc_attr_e( 'Deadline pages', 'dixcoverhub-custom-ui' ); ?>"><?php if ( $previous ) : ?><a href="<?php echo esc_url( $previous ); ?>"><span aria-hidden="true">←</span><?php esc_html_e( 'Previous', 'dixcoverhub-custom-ui' ); ?></a><?php else : ?><span></span><?php endif; ?><span><?php echo esc_html( sprintf( __( 'Page %1$s · %2$s deadlines', 'dixcoverhub-custom-ui' ), number_format_i18n( $results['page'] ), number_format_i18n( $results['total'] ) ) ); ?></span><?php if ( $next ) : ?><a href="<?php echo esc_url( $next ); ?>"><?php esc_html_e( 'Next', 'dixcoverhub-custom-ui' ); ?><span aria-hidden="true">→</span></a><?php else : ?><span></span><?php endif; ?></nav>
		<?php
		return (string) ob_get_clean();
	}

	/** Select sidebar entries into featured, trending, and latest groups. */
	private static function sidebar_lists( $posts ) {
		$posts = array_values( array_unique( array_filter( (array) $posts, static function ( $post ) { return $post instanceof WP_Post; } ), SORT_REGULAR ) );
		$featured = array_values( array_filter( $posts, static function ( $post ) { return '1' === (string) get_post_meta( $post->ID, '_dixcoverhub_featured', true ); } ) );
		$featured = array_slice( $featured ? $featured : $posts, 0, 3 );
		$featured_ids = wp_list_pluck( $featured, 'ID' );
		$remaining = array_values( array_filter( $posts, static function ( $post ) use ( $featured_ids ) { return ! in_array( $post->ID, $featured_ids, true ); } ) );
		$trending = array_slice( $remaining, 0, 4 );
		$trending_ids = wp_list_pluck( $trending, 'ID' );
		$latest = array_slice( array_values( array_filter( $remaining, static function ( $post ) use ( $trending_ids ) { return ! in_array( $post->ID, $trending_ids, true ); } ) ), 0, 5 );
		return array( 'featured' => $featured, 'trending' => $trending, 'latest' => $latest );
	}

	/** Render the discovery sidebar. */
	private static function render_sidebar( $lists, $timezone ) {
		if ( empty( $lists['featured'] ) && empty( $lists['trending'] ) && empty( $lists['latest'] ) ) {
			return '';
		}
		$groups = array( 'featured' => array( __( 'Featured Opportunities', 'dixcoverhub-custom-ui' ), 'Award01Icon' ), 'trending' => array( __( 'Trending', 'dixcoverhub-custom-ui' ), 'BarChartIcon' ), 'latest' => array( __( 'Latest Opportunities', 'dixcoverhub-custom-ui' ), 'Compass01Icon' ) );
		ob_start();
		?>
		<aside class="dh-deadlines-sidebar" aria-label="<?php esc_attr_e( 'Opportunity spotlight', 'dixcoverhub-custom-ui' ); ?>"><?php foreach ( $groups as $key => $group ) : if ( empty( $lists[ $key ] ) ) { continue; } ?><section><header><span><?php echo self::icon( $group[1], 'dh-deadlines-sidebar-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bundled SVG. ?><h2><?php echo esc_html( $group[0] ); ?></h2></span></header><?php foreach ( $lists[ $key ] as $post ) : $categories = get_the_category( $post->ID ); $category = $categories ? $categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ); ?><a class="dh-deadlines-sidebar-item" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><span><?php echo esc_html( $category ); ?></span><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( wp_date( 'M j', get_post_time( 'U', true, $post ), $timezone ) ); ?></time><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong><?php $provider = get_post_meta( $post->ID, '_dixcoverhub_provider_name', true ); if ( $provider ) : ?><small><?php echo esc_html( sanitize_text_field( (string) $provider ) ); ?></small><?php endif; ?></a><?php endforeach; ?></section><?php endforeach; ?></aside>
		<?php
		return (string) ob_get_clean();
	}

	/** Format an inclusive calendar range in the configured site timezone. */
	private static function format_range( $from, $to, $timezone ) {
		$start = self::date_timestamp( $from, $timezone );
		$end   = self::date_timestamp( $to, $timezone );
		if ( ! $start || ! $end ) {
			return '';
		}
		$start_label = wp_date( 'M j, Y', $start, $timezone );
		$end_label   = wp_date( 'M j, Y', $end, $timezone );
		return $from === $to ? $start_label : $start_label . ' – ' . $end_label;
	}

	/** Convert a validated date-only value into a local-noon timestamp. */
	private static function date_timestamp( $value, $timezone ) {
		if ( ! self::valid_date( $value, $timezone ) ) {
			return 0;
		}
		$date = self::date_object( $value, $timezone )->setTime( 12, 0, 0 );
		return $date->getTimestamp();
	}

	/** Render an allow-listed Hugeicons SVG. */
	private static function icon( $name, $class ) {
		return DixcoverHub_Custom_UI_Icons::svg( $name, $class );
	}
}
