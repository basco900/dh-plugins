<?php
/**
 * Server-rendered opportunity archive and filter system.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DixcoverHub_Custom_UI_Archive {
	const REWRITE_OPTION = 'dixcoverhub_custom_ui_rewrite_version';
	const ROUTE_VAR      = 'dh_opportunity_archive';

	/** Register the archive route and shortcode. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ), 5 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'backfill_filter_meta' ), 30 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'pre_handle_404', array( __CLASS__, 'prevent_archive_404' ), 10, 2 );
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_filter( 'the_content', array( __CLASS__, 'replace_opportunities_page' ), 25 );
		add_shortcode( 'dixcoverhub_opportunities', array( __CLASS__, 'shortcode' ) );
	}

	/** Index older AI Editor opportunity records in small batches during admin use. */
	public static function backfill_filter_meta() {
		if ( ! current_user_can( 'manage_options' ) || 'complete' === get_option( 'dixcoverhub_archive_meta_backfill', '' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( array( 'key' => '_dixcoverhub_archive_indexed', 'compare' => 'NOT EXISTS' ) ),
			)
		);
		foreach ( $ids as $post_id ) {
			$data = get_post_meta( $post_id, '_dixcoverhub_opportunity_data', true );
			if ( is_array( $data ) ) {
				$values = array(
					'_dixcoverhub_deadline'        => $data['deadline'] ?? '',
					'_dixcoverhub_location'        => $data['location'] ?? '',
					'_dixcoverhub_employment_type' => $data['employment_type'] ?? '',
					'_dixcoverhub_provider_name'   => $data['provider_name'] ?? '',
				);
				foreach ( $values as $key => $value ) {
					$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
					if ( '' === $value ) {
						delete_post_meta( $post_id, $key );
					} else {
						update_post_meta( $post_id, $key, $value );
					}
				}
			}
			update_post_meta( $post_id, '_dixcoverhub_archive_indexed', '1' );
		}
		if ( count( $ids ) < 200 ) {
			update_option( 'dixcoverhub_archive_meta_backfill', 'complete', false );
		}
	}

	/** Load archive styling on the route, page, or shortcode host only. */
	public static function enqueue_assets() {
		$options = DixcoverHub_Custom_UI::options();
		if ( is_admin() || empty( $options['archive_enabled'] ) ) {
			return;
		}
		$should_enqueue = (bool) get_query_var( self::ROUTE_VAR ) || is_page( 'opportunities' );
		if ( ! $should_enqueue && is_singular() ) {
			$queried = get_queried_object();
			$should_enqueue = $queried instanceof WP_Post && has_shortcode( $queried->post_content, 'dixcoverhub_opportunities' );
		}
		if ( $should_enqueue ) {
			wp_enqueue_style( 'dixcoverhub-opportunity-archive', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/css/archive.css', array(), DIXCOVERHUB_CUSTOM_UI_VERSION );
			wp_enqueue_script( 'dixcoverhub-opportunity-archive', DIXCOVERHUB_CUSTOM_UI_URL . 'assets/js/archive.js', array(), DIXCOVERHUB_CUSTOM_UI_VERSION, true );
		}
	}

	/** Register the stable /opportunities/ path. */
	public static function add_rewrite_rule() {
		$options = DixcoverHub_Custom_UI::options();
		if ( ! empty( $options['archive_enabled'] ) ) {
			add_rewrite_rule( '^opportunities/?$', 'index.php?' . self::ROUTE_VAR . '=1', 'top' );
		}
	}

	/** Refresh stored rules once after this feature is introduced or updated. */
	public static function maybe_flush_rewrite_rules() {
		$options = DixcoverHub_Custom_UI::options();
		$version = DIXCOVERHUB_CUSTOM_UI_VERSION . ':' . (int) ! empty( $options['archive_enabled'] );
		if ( $version !== get_option( self::REWRITE_OPTION, '' ) ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_OPTION, $version, false );
		}
	}

	/** Allow WordPress to parse the archive route marker. */
	public static function query_vars( $vars ) {
		$vars[] = self::ROUTE_VAR;
		return $vars;
	}

	/** An empty listing remains a valid archive page instead of a 404. */
	public static function prevent_archive_404( $preempt, $query ) {
		$options = DixcoverHub_Custom_UI::options();
		if ( ! empty( $options['archive_enabled'] ) && $query instanceof WP_Query && $query->get( self::ROUTE_VAR ) ) {
			return true;
		}
		return $preempt;
	}

	/** Load the custom template when the archive route is requested. */
	public static function template( $template ) {
		$options = DixcoverHub_Custom_UI::options();
		if ( ! empty( $options['archive_enabled'] ) && get_query_var( self::ROUTE_VAR ) ) {
			$archive_template = dirname( __DIR__ ) . '/templates/archive-opportunities.php';
			if ( is_readable( $archive_template ) ) {
				return $archive_template;
			}
		}
		return $template;
	}

	/** Turn a regular WordPress page with the opportunities slug into the archive. */
	public static function replace_opportunities_page( $content ) {
		$options = DixcoverHub_Custom_UI::options();
		if ( ! is_admin() && is_page( 'opportunities' ) && in_the_loop() && is_main_query() && ! has_shortcode( $content, 'dixcoverhub_opportunities' ) && ! empty( $options['archive_enabled'] ) ) {
			return self::render_archive();
		}
		return $content;
	}

	/** Render the archive shortcode. */
	public static function shortcode() {
		return self::render_archive();
	}

	/** Prepare normalized filters and render an archive query. */
	public static function render_archive() {
		$options = DixcoverHub_Custom_UI::options();
		if ( empty( $options['archive_enabled'] ) ) {
			return '';
		}

		$filters = self::filters();
		$all_categories = self::terms( 'category', false );
		$category_counts = self::category_counts( $all_categories );
		$terms   = array(
			'categories'    => array_values( array_filter( $all_categories, static function ( $term ) use ( $category_counts ) { return ! empty( $category_counts[ $term->term_id ] ); } ) ),
			'types'         => self::terms( 'dh_opportunity_type' ),
			'modes'         => self::terms( 'dh_opportunity_mode' ),
			'locations'     => self::terms( 'dh_opportunity_location' ),
		);
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => absint( $options['archive_posts_per_page'] ),
			'paged'               => max( 1, $filters['page'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'dh_archive_search_extended' => true,
	);
		$tax_query = array( 'relation' => 'AND' );
		if ( $filters['category'] ) {
			$tax_query[] = array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => $filters['category'], 'include_children' => true );
		}
		foreach ( array( 'type' => 'dh_opportunity_type', 'mode' => 'dh_opportunity_mode', 'location' => 'dh_opportunity_location' ) as $filter_key => $taxonomy ) {
			if ( $filters[ $filter_key ] ) {
				$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $filters[ $filter_key ] );
			}
		}
		if ( count( $tax_query ) > 1 ) {
			$query_args['tax_query'] = $tax_query;
		}
		if ( $filters['search'] ) {
			$query_args['s'] = $filters['search'];
		}
		if ( 'oldest' === $filters['sort'] ) {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'ASC';
		} elseif ( 'title' === $filters['sort'] ) {
			$query_args['orderby'] = 'title';
			$query_args['order']   = 'ASC';
		} elseif ( 'deadline' === $filters['sort'] ) {
			$query_args['dh_archive_sort_deadline'] = 1;
			$query_args['orderby']                 = 'date';
			$query_args['order']                   = 'DESC';
		} else {
			$query_args['orderby'] = 'date';
			$query_args['order']   = 'DESC';
		}
		$meta_query = self::deadline_meta_query( $filters['deadline'] );
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query;
		}

		$extended_search = null;
		if ( $filters['search'] ) {
			$needle = $filters['search'];
			$extended_search = static function ( $search, $query ) use ( $needle ) {
				global $wpdb;
				if ( ! $query->get( 'dh_archive_search_extended' ) || ! $search ) {
					return $search;
				}
				$like  = '%' . $wpdb->esc_like( $needle ) . '%';
				$extra = $wpdb->prepare(
					"(EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS dh_search_meta WHERE dh_search_meta.post_id = {$wpdb->posts}.ID AND dh_search_meta.meta_key IN ('_dixcoverhub_opportunity_data','_dixcoverhub_location','_dixcoverhub_provider_name','_dixcoverhub_employment_type') AND dh_search_meta.meta_value LIKE %s) OR EXISTS (SELECT 1 FROM {$wpdb->term_relationships} AS dh_search_rel INNER JOIN {$wpdb->term_taxonomy} AS dh_search_tax ON dh_search_tax.term_taxonomy_id = dh_search_rel.term_taxonomy_id INNER JOIN {$wpdb->terms} AS dh_search_term ON dh_search_term.term_id = dh_search_tax.term_id WHERE dh_search_rel.object_id = {$wpdb->posts}.ID AND dh_search_term.name LIKE %s))",
					$like,
					$like
				);
				$combined = preg_replace( '/^\s*AND\s*\((.*)\)\s*$/s', ' AND (($1) OR ' . $extra . ')', trim( $search ), 1 );
				return is_string( $combined ) ? $combined : $search;
			};
			add_filter( 'posts_search', $extended_search, 20, 2 );
		}
		$deadline_order = null;
		if ( 'deadline' === $filters['sort'] ) {
			$deadline_order = static function ( $clauses, $query ) {
				if ( ! $query->get( 'dh_archive_sort_deadline' ) ) {
					return $clauses;
				}
				global $wpdb;
				$deadline_key = $wpdb->prepare( '%s', '_dixcoverhub_deadline' );
				$today        = $wpdb->prepare( '%s', current_time( 'Y-m-d' ) );
				$clauses['join'] .= " LEFT JOIN (SELECT post_id, MIN(NULLIF(meta_value, '')) AS deadline_value FROM {$wpdb->postmeta} WHERE meta_key = {$deadline_key} GROUP BY post_id) AS dh_archive_deadline ON dh_archive_deadline.post_id = {$wpdb->posts}.ID";
				$deadline = 'dh_archive_deadline.deadline_value';
				$clauses['orderby'] = "CASE WHEN {$deadline} IS NULL THEN 1 WHEN {$deadline} >= {$today} THEN 0 ELSE 2 END ASC, CASE WHEN {$deadline} >= {$today} THEN {$deadline} ELSE '9999-12-31' END ASC, {$wpdb->posts}.post_date DESC";
				return $clauses;
			};
			add_filter( 'posts_clauses', $deadline_order, 20, 2 );
		}
		$query = new WP_Query( $query_args );
		if ( $extended_search ) {
			remove_filter( 'posts_search', $extended_search, 20 );
		}
		if ( $deadline_order ) {
			remove_filter( 'posts_clauses', $deadline_order, 20 );
		}

		$featured = array();
		if ( ! empty( $options['archive_sidebar_enabled'] ) ) {
			$featured = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'posts_per_page'      => 3,
				'post__not_in'        => wp_list_pluck( $query->posts, 'ID' ),
				'meta_key'            => '_dixcoverhub_featured',
				'meta_value'          => '1',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
			);
			if ( count( $featured ) < 3 ) {
				$featured = array_merge(
					$featured,
					get_posts(
						array(
							'post_type'           => 'post',
							'post_status'         => 'publish',
							'posts_per_page'      => 3 - count( $featured ),
							'post__not_in'        => array_merge( wp_list_pluck( $query->posts, 'ID' ), wp_list_pluck( $featured, 'ID' ) ),
							'ignore_sticky_posts' => true,
							'no_found_rows'       => true,
						)
					)
				);
			}
		}

		$base_url = home_url( '/opportunities/' );
		$style = '--dh-archive-accent:' . ( sanitize_hex_color( $options['primary_color'] ) ?: '#611f69' ) . ';';
		ob_start();
		?>
		<div class="dh-opportunity-archive" style="<?php echo esc_attr( $style ); ?>">
			<header class="dh-opportunity-archive-hero"><div><h1><?php echo esc_html( $filters['category_name'] ? $filters['category_name'] : $options['archive_heading'] ); ?></h1><p><?php echo esc_html( $options['archive_intro'] ); ?></p></div></header>
			<div class="dh-opportunity-archive-layout">
				<main class="dh-opportunity-archive-main">
					<form class="dh-opportunity-filters" method="get" action="<?php echo esc_url( $base_url ); ?>">
						<div class="dh-opportunity-search-row">
							<label class="dh-opportunity-search"><span class="screen-reader-text"><?php esc_html_e( 'Search opportunities', 'dixcoverhub-custom-ui' ); ?></span><input type="search" name="dh_s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search titles, categories, locations, or keywords?', 'dixcoverhub-custom-ui' ); ?>" /><button type="submit" aria-label="<?php esc_attr_e( 'Search', 'dixcoverhub-custom-ui' ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Compass01Icon', 'dh-archive-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?></button></label>
						</div>
						<section class="dh-opportunity-filter-refine" aria-label="<?php esc_attr_e( 'Refine opportunities', 'dixcoverhub-custom-ui' ); ?>">
							<div class="dh-opportunity-filter-heading"><div><span><?php esc_html_e( 'REFINE RESULTS', 'dixcoverhub-custom-ui' ); ?></span><p><?php esc_html_e( 'Narrow the list to what you are looking for.', 'dixcoverhub-custom-ui' ); ?></p></div></div>
							<div class="dh-opportunity-filter-grid">
								<?php
								$category_options = array( '' => __( 'All categories', 'dixcoverhub-custom-ui' ) );
								foreach ( self::hierarchical_terms( $terms['categories'] ) as $category_row ) {
									$term   = $category_row['term'];
									$prefix = $category_row['depth'] ? str_repeat( '— ', min( 4, $category_row['depth'] ) ) : '';
									$category_options[ $term->slug ] = $prefix . $term->name . ' (' . ( $category_counts[ $term->term_id ] ?? $term->count ) . ')';
								}
								$type_options = array( '' => __( 'All types', 'dixcoverhub-custom-ui' ) );
								foreach ( $terms['types'] as $term ) { $type_options[ $term->slug ] = $term->name; }
								$mode_options = array( '' => __( 'Any mode', 'dixcoverhub-custom-ui' ) );
								foreach ( $terms['modes'] as $term ) { $mode_options[ $term->slug ] = $term->name; }
								$location_options = array( '' => __( 'Any location', 'dixcoverhub-custom-ui' ) );
								foreach ( $terms['locations'] as $term ) { $location_options[ $term->slug ] = $term->name; }
								$deadline_options = array( '' => __( 'Any deadline', 'dixcoverhub-custom-ui' ), 'open' => __( 'Still open', 'dixcoverhub-custom-ui' ), 'closing' => __( 'Closing in 7 days', 'dixcoverhub-custom-ui' ), 'expired' => __( 'Deadline passed', 'dixcoverhub-custom-ui' ), 'none' => __( 'No deadline stated', 'dixcoverhub-custom-ui' ) );
								$sort_options = array( 'newest' => __( 'Newest first', 'dixcoverhub-custom-ui' ), 'oldest' => __( 'Oldest first', 'dixcoverhub-custom-ui' ), 'title' => __( 'Title A–Z', 'dixcoverhub-custom-ui' ), 'deadline' => __( 'Deadline soon', 'dixcoverhub-custom-ui' ) );
								echo self::render_filter_dropdown( 'dh_category', __( 'Category', 'dixcoverhub-custom-ui' ), $category_options, $filters['category'] );
								echo self::render_filter_dropdown( 'dh_type', __( 'Opportunity type', 'dixcoverhub-custom-ui' ), $type_options, $filters['type'] );
								echo self::render_filter_dropdown( 'dh_mode', __( 'Work mode', 'dixcoverhub-custom-ui' ), $mode_options, $filters['mode'] );
								echo self::render_filter_dropdown( 'dh_location', __( 'Location', 'dixcoverhub-custom-ui' ), $location_options, $filters['location'] );
								echo self::render_filter_dropdown( 'dh_deadline', __( 'Deadline status', 'dixcoverhub-custom-ui' ), $deadline_options, $filters['deadline'] );
								echo self::render_filter_dropdown( 'dh_sort', __( 'Sort results', 'dixcoverhub-custom-ui' ), $sort_options, $filters['sort'] );
								?>
							</div>
							<div class="dh-opportunity-filter-actions"><button class="dh-opportunity-filter-submit" type="submit"><?php esc_html_e( 'Apply filters', 'dixcoverhub-custom-ui' ); ?></button><a class="dh-opportunity-filter-reset" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear all', 'dixcoverhub-custom-ui' ); ?></a></div>
						</section>
					</form>
					<?php if ( $terms['categories'] ) : ?><nav class="dh-opportunity-category-chips" aria-label="<?php esc_attr_e( 'Popular categories', 'dixcoverhub-custom-ui' ); ?>"><a class="<?php echo $filters['category'] ? '' : 'is-active'; ?>" href="<?php echo esc_url( self::category_url( $base_url, $filters, '' ) ); ?>"><?php esc_html_e( 'All', 'dixcoverhub-custom-ui' ); ?></a><?php foreach ( array_slice( array_values( array_filter( $terms['categories'], static function ( $term ) { return ! $term->parent; } ) ), 0, 9 ) as $term ) : ?><a class="<?php echo $filters['category'] === $term->slug ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::category_url( $base_url, $filters, $term->slug ) ); ?>"><?php echo esc_html( $term->name ); ?><span><?php echo absint( $category_counts[ $term->term_id ] ?? $term->count ); ?></span></a><?php endforeach; ?></nav><?php endif; ?>
					<div class="dh-opportunity-results-heading"><p><?php echo esc_html( sprintf( _n( '%s opportunity', '%s opportunities', (int) $query->found_posts, 'dixcoverhub-custom-ui' ), number_format_i18n( $query->found_posts ) ) ); ?></p><?php if ( $filters['search'] || $filters['category'] || $filters['type'] || $filters['mode'] || $filters['location'] || $filters['deadline'] ) : ?><span><?php esc_html_e( 'Filtered results', 'dixcoverhub-custom-ui' ); ?></span><?php endif; ?></div>
					<?php if ( $query->have_posts() ) : ?><div class="dh-opportunity-list"><?php while ( $query->have_posts() ) : $query->the_post(); self::render_opportunity_card( get_the_ID(), $filters ); endwhile; ?></div>
						<?php self::pagination( $query, $filters, $base_url ); ?>
					<?php else : ?><div class="dh-opportunity-empty"><span aria-hidden="true">⌕</span><h2><?php esc_html_e( 'No opportunities match these filters', 'dixcoverhub-custom-ui' ); ?></h2><p><?php esc_html_e( 'Try changing a filter or clearing your search to see more listings.', 'dixcoverhub-custom-ui' ); ?></p><a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Reset all filters', 'dixcoverhub-custom-ui' ); ?></a></div><?php endif; wp_reset_postdata(); ?>
				</main>
				<?php if ( ! empty( $options['archive_sidebar_enabled'] ) ) : ?><aside class="dh-opportunity-archive-sidebar"><section><header><div><p><?php esc_html_e( "Editor's selection", 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Featured Opportunities', 'dixcoverhub-custom-ui' ); ?></h2></div><a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'View all', 'dixcoverhub-custom-ui' ); ?></a></header><?php foreach ( $featured as $item ) : $image = get_the_post_thumbnail_url( $item, 'thumbnail' ); $item_cats = get_the_category( $item->ID ); $item_data = get_post_meta( $item->ID, '_dixcoverhub_opportunity_data', true ); ?><a class="dh-opportunity-featured-item" href="<?php echo esc_url( get_permalink( $item ) ); ?>"><span class="dh-opportunity-featured-image"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" /><?php else : ?><span aria-hidden="true">✦</span><?php endif; ?></span><span><small><?php echo esc_html( $item_cats ? $item_cats[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ) ); ?></small><strong><?php echo esc_html( get_the_title( $item ) ); ?></strong><?php if ( is_array( $item_data ) && ! empty( $item_data['provider_name'] ) ) : ?><em><?php echo esc_html( $item_data['provider_name'] ); ?><?php if ( ! empty( $item_data['location'] ) ) : ?> / <?php echo esc_html( $item_data['location'] ); ?><?php endif; ?></em><?php endif; ?></span></a><?php endforeach; ?><?php if ( ! $featured ) : ?><p class="dh-opportunity-sidebar-empty"><?php esc_html_e( 'New listings will appear here as they are published.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?></section></aside><?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Render a compact accessible listbox filter with a submitted hidden field. */
	private static function render_filter_dropdown( $name, $label, $options, $selected ) {
		$id = 'dh-filter-' . sanitize_key( $name );
		$selected_label = isset( $options[ $selected ] ) ? $options[ $selected ] : reset( $options );
		ob_start();
		?>
		<div class="dh-opportunity-filter-control" data-dh-select>
			<span class="dh-opportunity-filter-label" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $label ); ?></span>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $selected ); ?>" data-dh-select-value />
			<button class="dh-opportunity-select-trigger" type="button" aria-haspopup="listbox" aria-controls="<?php echo esc_attr( $id ); ?>-menu" aria-expanded="false" aria-labelledby="<?php echo esc_attr( $id ); ?>-label <?php echo esc_attr( $id ); ?>-value" data-dh-select-trigger>
				<span id="<?php echo esc_attr( $id ); ?>-value" data-dh-select-label><?php echo esc_html( $selected_label ); ?></span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m5 7.5 5 5 5-5" /></svg>
			</button>
			<div class="dh-opportunity-select-menu" id="<?php echo esc_attr( $id ); ?>-menu" role="listbox" aria-labelledby="<?php echo esc_attr( $id ); ?>-label" tabindex="-1" hidden data-dh-select-menu>
				<?php foreach ( $options as $value => $option_label ) : ?><button type="button" role="option" aria-selected="<?php echo (string) $selected === (string) $value ? 'true' : 'false'; ?>" class="dh-opportunity-select-option<?php echo (string) $selected === (string) $value ? ' is-selected' : ''; ?>" data-value="<?php echo esc_attr( $value ); ?>"><span><?php echo esc_html( $option_label ); ?></span><svg aria-hidden="true" viewBox="0 0 20 20"><path d="m4.5 10 3.5 3.5 7.5-7.5" /></svg></button><?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Parse and validate archive filters, including the legacy category query. */
	private static function filters() {
		$category_raw = sanitize_title( self::query_value( array( 'dh_category', 'category' ) ) );
		$type_raw     = sanitize_title( self::query_value( array( 'dh_type', 'type' ) ) );
		$category_name = '';
		if ( 'job' === $category_raw && ! get_term_by( 'slug', 'job', 'category' ) && ! get_term_by( 'slug', 'jobs', 'category' ) ) {
			$job_type = get_term_by( 'slug', 'job', 'dh_opportunity_type' );
			if ( $job_type ) {
				$type_raw = 'job';
				$category_raw = '';
			}
		}
		$category = $category_raw && get_term_by( 'slug', $category_raw, 'category' ) ? $category_raw : '';
		$type     = $type_raw && get_term_by( 'slug', $type_raw, 'dh_opportunity_type' ) ? $type_raw : '';
		$mode     = sanitize_title( self::query_value( array( 'dh_mode', 'mode' ) ) );
		$mode     = $mode && get_term_by( 'slug', $mode, 'dh_opportunity_mode' ) ? $mode : '';
		$location = sanitize_title( self::query_value( array( 'dh_location', 'location' ) ) );
		$location = $location && get_term_by( 'slug', $location, 'dh_opportunity_location' ) ? $location : '';
		if ( $category ) {
			$term = get_term_by( 'slug', $category, 'category' );
			$category_name = $term && ! is_wp_error( $term ) ? $term->name : '';
		}
		$deadline = sanitize_key( self::query_value( array( 'dh_deadline', 'deadline' ) ) );
		if ( ! in_array( $deadline, array( 'open', 'closing', 'expired', 'none' ), true ) ) {
			$deadline = '';
		}
		$sort = sanitize_key( self::query_value( array( 'dh_sort', 'sort' ), 'newest' ) );
		if ( ! in_array( $sort, array( 'newest', 'oldest', 'title', 'deadline' ), true ) ) {
			$sort = 'newest';
		}
		return array(
			'search'       => sanitize_text_field( self::query_value( array( 'dh_s', 'search', 's', 'q' ) ) ),
			'category'    => $category,
			'category_name' => $category_name,
			'type'         => $type,
			'mode'         => $mode,
			'location'     => $location,
			'deadline'     => $deadline,
			'sort'         => $sort,
			'page'         => max( 1, absint( self::query_value( array( 'dh_page', 'page' ), '1' ) ) ),
		);
	}

	/** Return the first scalar query value from the current or legacy filter names. */
	private static function query_value( $keys, $default = '' ) {
		foreach ( (array) $keys as $key ) {
			if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ) {
				return (string) wp_unslash( $_GET[ $key ] );
			}
		}
		return (string) $default;
	}

	/** Return visible terms in a stable name order. */
	private static function terms( $taxonomy, $hide_empty = true ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => $hide_empty, 'orderby' => 'name', 'order' => 'ASC', 'number' => 250 ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/** Return category terms in parent-first order with a stable visual depth. */
	private static function hierarchical_terms( $terms ) {
		$term_ids = array();
		foreach ( $terms as $term ) {
			$term_ids[ (int) $term->term_id ] = true;
		}

		$children = array();
		foreach ( $terms as $term ) {
			$parent_id = (int) $term->parent;
			if ( ! isset( $term_ids[ $parent_id ] ) ) {
				$parent_id = 0;
			}
			$children[ $parent_id ][] = $term;
		}
		foreach ( $children as &$siblings ) {
			usort(
				$siblings,
				static function ( $left, $right ) {
					return strnatcasecmp( $left->name, $right->name );
				}
			);
		}
		unset( $siblings );

		$ordered = array();
		$seen    = array();
		$walk    = static function ( $parent_id, $depth ) use ( &$walk, &$ordered, &$seen, $children ) {
			foreach ( $children[ $parent_id ] ?? array() as $term ) {
				$term_id = (int) $term->term_id;
				if ( isset( $seen[ $term_id ] ) ) {
					continue;
				}
				$seen[ $term_id ] = true;
				$ordered[]        = array( 'term' => $term, 'depth' => $depth );
				$walk( $term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		// A malformed or incomplete term tree should not hide any filter option.
		foreach ( $terms as $term ) {
			$term_id = (int) $term->term_id;
			if ( ! isset( $seen[ $term_id ] ) ) {
				$seen[ $term_id ] = true;
				$ordered[]        = array( 'term' => $term, 'depth' => 0 );
				$walk( $term_id, 1 );
			}
		}

		return $ordered;
	}

	/** Include descendants in the visible category count. */
	private static function category_counts( $categories ) {
		$counts = array();
		foreach ( $categories as $category ) {
			$counts[ $category->term_id ] = (int) $category->count;
		}
		foreach ( $categories as $category ) {
			if ( ! $category->parent ) {
				continue;
			}
			$ancestors = get_ancestors( (int) $category->term_id, 'category', 'taxonomy' );
			foreach ( $ancestors as $ancestor_id ) {
				$counts[ $ancestor_id ] = ( $counts[ $ancestor_id ] ?? 0 ) + (int) $category->count;
			}
		}
		return $counts;
	}

	/** Date-based query clauses use normalized scalar meta written by AI Editor. */
	private static function deadline_meta_query( $filter ) {
		if ( ! $filter ) {
			return array();
		}
		$today = wp_date( 'Y-m-d', current_time( 'timestamp', true ) );
		$today_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $today, wp_timezone() );
		$soon       = $today_date ? $today_date->modify( '+7 days' )->format( 'Y-m-d' ) : $today;
		if ( 'open' === $filter ) {
			return array(
				'relation' => 'OR',
				array( 'key' => '_dixcoverhub_deadline', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
				array( 'key' => '_dixcoverhub_deadline', 'compare' => 'NOT EXISTS' ),
			);
		}
		if ( 'closing' === $filter ) {
			return array( array( 'key' => '_dixcoverhub_deadline', 'value' => array( $today, $soon ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) );
		}
		if ( 'expired' === $filter ) {
			return array( array( 'key' => '_dixcoverhub_deadline', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ) );
		}
		return array(
			'relation' => 'OR',
			array( 'key' => '_dixcoverhub_deadline', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_dixcoverhub_deadline', 'value' => '', 'compare' => '=' ),
		);
	}

	/** Build category chip URLs without dropping the other active filters. */
	private static function category_url( $base_url, $filters, $category_slug ) {
		$args = array(
			'dh_s'        => $filters['search'],
			'dh_type'     => $filters['type'],
			'dh_mode'     => $filters['mode'],
			'dh_location' => $filters['location'],
			'dh_deadline' => $filters['deadline'],
			'dh_sort'     => $filters['sort'],
		);
		$args = array_filter( $args, static function ( $value ) { return '' !== (string) $value && 'newest' !== $value; } );
		if ( $category_slug ) {
			$args['dh_category'] = $category_slug;
		}
		return add_query_arg( $args, $base_url );
	}

	/** Render one compact opportunity row. */
	private static function render_opportunity_card( $post_id, $filters ) {
		$post     = get_post( $post_id );
		$image    = get_the_post_thumbnail_url( $post_id, 'medium' );
		$categories = get_the_category( $post_id );
		$terms = array();
		foreach ( array( 'dh_opportunity_type', 'dh_opportunity_mode' ) as $taxonomy ) {
			$found = get_the_terms( $post_id, $taxonomy );
			if ( is_array( $found ) ) {
				$terms = array_merge( $terms, $found );
			}
		}
		$location_terms = get_the_terms( $post_id, 'dh_opportunity_location' );
		$location_names = is_array( $location_terms ) ? wp_list_pluck( array_slice( $location_terms, 0, 2 ), 'name' ) : array();
		$opportunity = get_post_meta( $post_id, '_dixcoverhub_opportunity_data', true );
		$opportunity = is_array( $opportunity ) ? $opportunity : array();
		if ( ! $location_names && ! empty( $opportunity['location'] ) ) {
			$location_names[] = sanitize_text_field( $opportunity['location'] );
		}
		$excerpt = get_the_excerpt( $post );
		?>
		<article class="dh-opportunity-card">
			<div class="dh-opportunity-card-media"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'View %s', 'dixcoverhub-custom-ui' ), get_the_title( $post ) ) ); ?>"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true ) ?: get_the_title( $post ) ); ?>" loading="lazy" /><?php else : ?><span class="dh-opportunity-card-placeholder"><?php echo esc_html( $categories ? $categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ) ); ?></span><?php endif; ?></a><a class="dh-opportunity-card-view" href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'View', 'dixcoverhub-custom-ui' ); ?> <span aria-hidden="true">↗</span></a></div>
			<div class="dh-opportunity-card-content"><p class="dh-opportunity-card-meta"><span><?php echo esc_html( $categories ? $categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ) ); ?></span><i aria-hidden="true">/</i><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( human_time_diff( get_post_time( 'U', true, $post ), current_time( 'timestamp', true ) ) . ' ' . __( 'ago', 'dixcoverhub-custom-ui' ) ); ?></time></p><h2><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h2><?php if ( $excerpt ) : ?><p class="dh-opportunity-card-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), 25, '…' ) ); ?></p><?php endif; ?><div class="dh-opportunity-card-tags"><?php if ( $location_names ) : ?><span class="dh-opportunity-card-location"><?php echo esc_html( implode( ', ', $location_names ) ); ?></span><?php endif; ?><?php foreach ( array_slice( $terms, 0, 2 ) as $term ) : ?><span class="dh-opportunity-card-tag"><?php echo esc_html( $term->name ); ?></span><?php endforeach; ?><?php if ( isset( $opportunity['deadline'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $opportunity['deadline'] ) ) : ?><span class="dh-opportunity-card-deadline"><?php echo esc_html( sprintf( __( 'Closes %s', 'dixcoverhub-custom-ui' ), wp_date( get_option( 'date_format' ), strtotime( $opportunity['deadline'] . ' 12:00:00 ' . wp_timezone_string() ) ) ) ); ?></span><?php endif; ?></div></div>
		</article>
		<?php
	}

	/** Render pagination while preserving the active filter state. */
	private static function pagination( $query, $filters, $base_url ) {
		$total_pages = (int) $query->max_num_pages;
		if ( $total_pages < 2 ) {
			return;
		}
		$filter_args = array(
			'dh_s' => $filters['search'], 'dh_category' => $filters['category'], 'dh_type' => $filters['type'],
			'dh_mode' => $filters['mode'], 'dh_location' => $filters['location'], 'dh_deadline' => $filters['deadline'], 'dh_sort' => $filters['sort'],
		);
		$filter_args = array_filter( $filter_args, static function ( $value ) { return '' !== (string) $value && 'newest' !== $value; } );
		$current = min( $filters['page'], $total_pages );
		$start   = max( 1, $current - 2 );
		$end     = min( $total_pages, $current + 2 );
		?>
		<nav class="dh-opportunity-pagination" aria-label="<?php esc_attr_e( 'Opportunity pages', 'dixcoverhub-custom-ui' ); ?>">
			<?php if ( $current > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'dh_page' => $current - 1 ) ), $base_url ) ); ?>" rel="prev">← <span><?php esc_html_e( 'Previous', 'dixcoverhub-custom-ui' ); ?></span></a><?php endif; ?>
			<?php for ( $page = $start; $page <= $end; $page++ ) : ?><a class="<?php echo $page === $current ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'dh_page' => $page ) ), $base_url ) ); ?>" <?php echo $page === $current ? 'aria-current="page"' : ''; ?>><?php echo absint( $page ); ?></a><?php endfor; ?>
			<?php if ( $current < $total_pages ) : ?><a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'dh_page' => $current + 1 ) ), $base_url ) ); ?>" rel="next"><span><?php esc_html_e( 'Next', 'dixcoverhub-custom-ui' ); ?></span> →</a><?php endif; ?>
		</nav>
		<?php
	}
}
