<?php
/**
 * Public single-post page, styled after the DixcoverHub reference site.
 *
 * WordPress fields remain the source of truth; optional opportunity fields are
 * read from the metadata created by DixcoverHub AI Editor.
 *
 * @package DixcoverHub\CustomUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$post_id       = get_the_ID();
	$options       = DixcoverHub_Custom_UI::options();
	$opportunity   = get_post_meta( $post_id, '_dixcoverhub_opportunity_data', true );
	$opportunity   = is_array( $opportunity ) ? $opportunity : array();
	$summary       = sanitize_textarea_field( (string) get_post_meta( $post_id, '_dixcoverhub_summary', true ) );
	$faq_rows      = get_post_meta( $post_id, '_dixcoverhub_faqs', true );
	$faq_rows      = is_array( $faq_rows ) && $faq_rows ? $faq_rows : ( isset( $opportunity['faqs'] ) && is_array( $opportunity['faqs'] ) ? $opportunity['faqs'] : array() );
	$faqs          = array();
	foreach ( $faq_rows as $faq ) {
		if ( ! is_array( $faq ) ) {
			continue;
		}
		$question = sanitize_text_field( isset( $faq['question'] ) ? $faq['question'] : '' );
		$answer   = sanitize_textarea_field( isset( $faq['answer'] ) ? $faq['answer'] : '' );
		if ( $question && $answer ) {
			$faqs[] = array( 'question' => $question, 'answer' => $answer );
		}
	}
	$categories      = get_the_category( $post_id );
	$category         = null;
	foreach ( $categories as $candidate_category ) {
		if ( ! empty( $candidate_category->parent ) ) {
			$category = $candidate_category;
			break;
		}
	}
	if ( ! $category && ! empty( $categories ) ) {
		$category = $categories[0];
	}
	$category_label_parts = array();
	if ( $category ) {
		$ancestor_ids = array_reverse( get_ancestors( $category->term_id, 'category', 'taxonomy' ) );
		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'category' );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$category_label_parts[] = $ancestor->name;
			}
		}
		$category_label_parts[] = $category->name;
	}
	$category_label = $category_label_parts ? implode( ' / ', array_unique( $category_label_parts ) ) : __( 'Opportunity', 'dixcoverhub-custom-ui' );
	$category_link  = $category ? get_category_link( $category->term_id ) : home_url( '/opportunities/' );
	$provider         = sanitize_text_field( (string) ( $opportunity['provider_name'] ?? '' ) );
	$location         = sanitize_text_field( (string) ( $opportunity['location'] ?? '' ) );
	$employment_type  = sanitize_text_field( (string) ( $opportunity['employment_type'] ?? '' ) );
	$duration         = sanitize_text_field( (string) ( $opportunity['duration'] ?? '' ) );
	$salary           = sanitize_text_field( (string) ( $opportunity['salary'] ?? '' ) );
	$deadline         = sanitize_text_field( (string) ( $opportunity['deadline'] ?? '' ) );
	$application_link = esc_url( (string) ( $opportunity['application_link'] ?? '' ) );
	$application_mail = sanitize_email( (string) ( $opportunity['application_email'] ?? '' ) );
	$application_links = array();
	$stored_application_links = isset( $opportunity['application_links'] ) && is_array( $opportunity['application_links'] ) ? $opportunity['application_links'] : array();
	foreach ( $stored_application_links as $stored_link ) {
		if ( ! is_array( $stored_link ) || empty( $stored_link['url'] ) ) {
			continue;
		}
		$url = esc_url_raw( (string) $stored_link['url'] );
		if ( ! $url || ! wp_http_validate_url( $url ) || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
			continue;
		}
		$application_links[] = array(
			'label' => sanitize_text_field( (string) ( $stored_link['label'] ?? '' ) ),
			'url'   => $url,
		);
		if ( count( $application_links ) >= 8 ) {
			break;
		}
	}
	if ( ! $application_links && $application_link && wp_http_validate_url( $application_link ) && in_array( strtolower( (string) wp_parse_url( $application_link, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ) {
		$application_links[] = array( 'label' => '', 'url' => $application_link );
	}
	if ( $application_mail ) {
		$application_links[] = array( 'label' => __( 'Apply by email', 'dixcoverhub-custom-ui' ), 'url' => 'mailto:' . $application_mail );
	}
	$deadline_timestamp = $deadline ? strtotime( $deadline . ' 23:59:59 ' . wp_timezone_string() ) : false;
	$is_expired         = $deadline_timestamp && $deadline_timestamp < current_time( 'timestamp', true );
	$deadline_label     = $deadline_timestamp ? wp_date( get_option( 'date_format' ), $deadline_timestamp ) : '';
	$excerpt            = has_excerpt( $post_id ) ? get_the_excerpt() : $summary;
	if ( ! $excerpt ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 34, '…' );
	}
	$featured_image = get_the_post_thumbnail_url( $post_id, 'large' );
	$featured_alt   = get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true );
	$accent         = sanitize_hex_color( $options['primary_color'] );
	$header_color   = sanitize_hex_color( $options['single_post_header_color'] );
	$layout_style   = '--dh-post-width:' . absint( $options['single_post_width'] ) . 'px;--dh-post-accent:' . ( $accent ? $accent : '#611f69' ) . ';--dh-post-hero:' . ( $header_color ? $header_color : '#f8f2fa' ) . ';';
	$categories_ids = wp_list_pluck( $categories, 'term_id' );
	$featured_args  = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => absint( $options['single_post_sidebar_count'] ),
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
	);
	if ( $categories_ids ) {
		$featured_args['category__in'] = $categories_ids;
	}
	$featured_posts = array();
	if ( ! empty( $options['single_post_sidebar_enabled'] ) ) {
		$featured_args['meta_key']   = '_dixcoverhub_featured';
		$featured_args['meta_value'] = '1';
		$featured_posts              = get_posts( $featured_args );
		if ( count( $featured_posts ) < absint( $options['single_post_sidebar_count'] ) ) {
			unset( $featured_args['meta_key'], $featured_args['meta_value'] );
			$featured_args['posts_per_page'] = absint( $options['single_post_sidebar_count'] ) - count( $featured_posts );
			$featured_args['post__not_in']   = array_merge( array( $post_id ), wp_list_pluck( $featured_posts, 'ID' ) );
			$featured_posts                  = array_merge( $featured_posts, get_posts( $featured_args ) );
		}
		if ( count( $featured_posts ) < absint( $options['single_post_sidebar_count'] ) && isset( $featured_args['category__in'] ) ) {
			unset( $featured_args['category__in'] );
			$featured_args['posts_per_page'] = absint( $options['single_post_sidebar_count'] ) - count( $featured_posts );
			$featured_args['post__not_in']   = array_merge( array( $post_id ), wp_list_pluck( $featured_posts, 'ID' ) );
			$featured_posts                  = array_merge( $featured_posts, get_posts( $featured_args ) );
		}
	}
	$related_posts = array();
	if ( ! empty( $options['single_post_show_related'] ) && $categories_ids ) {
		$related_posts = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'post__not_in'        => array( $post_id ),
				'category__in'        => $categories_ids,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);
	}
	$published_timestamp = get_post_time( 'U', true, $post_id );
	$share_title = rawurlencode( wp_strip_all_tags( get_the_title() ) );
	$share_url   = rawurlencode( get_permalink() );
	$clean_list = static function ( $values ) {
		$output = array();
		foreach ( (array) $values as $value ) {
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$output[] = sanitize_text_field( (string) $value );
			}
		}
		return $output;
	};
	$requirements = $clean_list( $opportunity['requirements'] ?? array() );
	$benefits     = $clean_list( $opportunity['benefits'] ?? array() );
	$level_terms  = get_the_terms( $post_id, 'dh_opportunity_level' );
	$levels       = is_array( $level_terms ) ? $clean_list( wp_list_pluck( $level_terms, 'name' ) ) : array();
	if ( ! $levels ) {
		$saved_taxonomies = isset( $opportunity['taxonomy_suggestions'] ) && is_array( $opportunity['taxonomy_suggestions'] ) ? $opportunity['taxonomy_suggestions'] : array();
		$levels = $clean_list( $saved_taxonomies['levelNames'] ?? ( $opportunity['levels'] ?? array() ) );
	}
	$research_sources = get_post_meta( $post_id, '_dixcoverhub_research_sources', true );
	$research_sources = is_array( $research_sources ) ? $research_sources : array();
	?>
	<div class="dh-single-post" style="<?php echo esc_attr( $layout_style ); ?>">
		<header class="dh-single-post-hero">
			<div class="dh-single-post-hero-inner">
				<div class="dh-single-post-heading">
					<a class="dh-single-post-category" href="<?php echo esc_url( $category_link ); ?>"><span aria-hidden="true">/</span><?php echo esc_html( $category_label ); ?></a>
					<h1><?php the_title(); ?></h1>
					<?php if ( $excerpt ) : ?><p class="dh-single-post-excerpt"><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p><?php endif; ?>
					<div class="dh-single-post-meta">
						<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( sprintf( __( '%s ago', 'dixcoverhub-custom-ui' ), human_time_diff( $published_timestamp, current_time( 'timestamp' ) ) ) ); ?></time>
						<span aria-hidden="true">•</span><span><?php echo esc_html( get_the_author() ); ?></span>
						<?php if ( $provider ) : ?><span aria-hidden="true">•</span><span><?php echo esc_html( $provider ); ?></span><?php endif; ?>
						<div class="dh-single-post-share"><span><?php esc_html_e( 'Share', 'dixcoverhub-custom-ui' ); ?></span><button type="button" data-dh-copy-link data-dh-analytics="share" data-copy-url="<?php echo esc_url( get_permalink() ); ?>" aria-label="<?php esc_attr_e( 'Copy article link', 'dixcoverhub-custom-ui' ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-single-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?></button><a href="https://wa.me/?text=<?php echo esc_attr( $share_title . '%20' . $share_url ); ?>" target="_blank" rel="noopener noreferrer" data-dh-analytics="share" aria-label="<?php esc_attr_e( 'Share on WhatsApp', 'dixcoverhub-custom-ui' ); ?>">WA</a></div>
					</div>
				</div>
				<figure class="dh-single-post-featured-image">
					<?php if ( $featured_image ) : ?><a href="<?php echo esc_url( $featured_image ); ?>" data-dh-image-open><img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $featured_alt ? $featured_alt : get_the_title() ); ?>" loading="eager" fetchpriority="high" /></a><?php else : ?><span class="dh-single-post-image-placeholder"><span><?php echo esc_html( $category_label ); ?></span></span><?php endif; ?>
				</figure>
			</div>
		</header>

		<div class="dh-single-post-layout">
			<main class="dh-single-post-main">
				<article <?php post_class( 'dh-single-post-article' ); ?>>
					<?php if ( ! empty( $options['single_post_show_summary'] ) && $summary && $summary !== $excerpt ) : ?><aside class="dh-single-post-summary"><strong><?php esc_html_e( 'Quick summary', 'dixcoverhub-custom-ui' ); ?></strong><p><?php echo esc_html( $summary ); ?></p></aside><?php endif; ?>
					<div class="dh-single-post-content" id="dh-single-post-content"><?php the_content(); ?></div>
					<?php if ( $location || $employment_type || $duration || $salary || $deadline || $levels ) : ?>
						<section class="dh-single-post-facts" aria-label="<?php esc_attr_e( 'Opportunity details', 'dixcoverhub-custom-ui' ); ?>"><h2><?php esc_html_e( 'Opportunity details', 'dixcoverhub-custom-ui' ); ?></h2><dl>
							<?php if ( $employment_type ) : ?><div><dt><?php esc_html_e( 'Type', 'dixcoverhub-custom-ui' ); ?></dt><dd><?php echo esc_html( $employment_type ); ?></dd></div><?php endif; ?>
							<?php if ( $levels ) : ?><div><dt><?php esc_html_e( 'Level', 'dixcoverhub-custom-ui' ); ?></dt><dd><?php echo esc_html( implode( ', ', $levels ) ); ?></dd></div><?php endif; ?>
							<?php if ( $location ) : ?><div><dt><?php esc_html_e( 'Location', 'dixcoverhub-custom-ui' ); ?></dt><dd><?php echo esc_html( $location ); ?></dd></div><?php endif; ?>
							<?php if ( $duration ) : ?><div><dt><?php esc_html_e( 'Duration', 'dixcoverhub-custom-ui' ); ?></dt><dd><?php echo esc_html( $duration ); ?></dd></div><?php endif; ?>
							<?php if ( $salary ) : ?><div><dt><?php esc_html_e( 'Pay / funding', 'dixcoverhub-custom-ui' ); ?></dt><dd><?php echo esc_html( $salary ); ?></dd></div><?php endif; ?>
							<?php if ( $deadline_label ) : ?><div><dt><?php esc_html_e( 'Deadline', 'dixcoverhub-custom-ui' ); ?></dt><dd><?php echo esc_html( $deadline_label ); ?><?php if ( $is_expired ) : ?> <span class="dh-single-post-expired"><?php esc_html_e( 'Closed', 'dixcoverhub-custom-ui' ); ?></span><?php endif; ?></dd></div><?php endif; ?>
						</dl></section>
					<?php endif; ?>
					<?php if ( $requirements || $benefits ) : ?><section class="dh-single-post-requirements">
						<?php if ( $requirements ) : ?><div><h2><?php esc_html_e( 'Requirements', 'dixcoverhub-custom-ui' ); ?></h2><ul><?php foreach ( $requirements as $requirement ) : ?><li><?php echo esc_html( $requirement ); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
						<?php if ( $benefits ) : ?><div><h2><?php esc_html_e( 'Benefits', 'dixcoverhub-custom-ui' ); ?></h2><ul><?php foreach ( $benefits as $benefit ) : ?><li><?php echo esc_html( $benefit ); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
					</section><?php endif; ?>
					<?php if ( ! empty( $options['single_post_show_apply'] ) ) : ?>
						<section class="dh-single-post-apply" aria-label="<?php esc_attr_e( 'Application', 'dixcoverhub-custom-ui' ); ?>">
							<?php if ( $deadline_label && $is_expired ) : ?><p class="dh-single-post-closed-notice"><?php echo esc_html( sprintf( __( 'Applications closed after the deadline on %s.', 'dixcoverhub-custom-ui' ), $deadline_label ) ); ?></p>
							<?php elseif ( $deadline_label && wp_date( 'Y-m-d', $deadline_timestamp ) === wp_date( 'Y-m-d', current_time( 'timestamp', true ) ) ) : ?><p class="dh-single-post-deadline-notice"><?php esc_html_e( 'Apply soon: the deadline is today.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?>
							<?php if ( $application_links && ! $is_expired ) : ?><div class="dh-single-post-apply-links"><?php foreach ( $application_links as $application ) : ?><a href="<?php echo esc_url( $application['url'] ); ?>" <?php echo 0 === strpos( $application['url'], 'mailto:' ) ? '' : 'target="_blank" rel="noopener noreferrer"'; ?> data-dh-analytics="application_click"><?php echo esc_html( $application['label'] ? $application['label'] : __( 'Apply on the official site', 'dixcoverhub-custom-ui' ) ); ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?></div>
							<?php elseif ( ! $application_links && ! $is_expired ) : ?><a class="dh-single-post-read-apply" href="#dh-single-post-content"><?php esc_html_e( 'Read application details', 'dixcoverhub-custom-ui' ); ?></a><?php endif; ?>
						</section>
					<?php endif; ?>
					<?php if ( ! empty( $options['single_post_show_faqs'] ) && $faqs ) : ?><section class="dh-single-post-faqs"><h2><?php esc_html_e( 'Frequently asked questions', 'dixcoverhub-custom-ui' ); ?></h2><?php foreach ( $faqs as $faq ) : ?><details><summary><?php echo esc_html( $faq['question'] ); ?></summary><div><?php echo nl2br( esc_html( $faq['answer'] ) ); ?></div></details><?php endforeach; ?></section><?php endif; ?>
					<?php if ( $research_sources ) : ?><details class="dh-single-post-sources"><summary><?php esc_html_e( 'Research sources', 'dixcoverhub-custom-ui' ); ?></summary><ul><?php foreach ( $research_sources as $source ) : if ( ! is_array( $source ) || empty( $source['url'] ) || ! wp_http_validate_url( $source['url'] ) ) { continue; } ?><li><a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ! empty( $source['title'] ) ? $source['title'] : wp_parse_url( $source['url'], PHP_URL_HOST ) ); ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
					<?php if ( $related_posts ) : ?><section class="dh-single-post-related"><div class="dh-single-post-related-heading"><div><span><?php esc_html_e( 'Explore more', 'dixcoverhub-custom-ui' ); ?></span><h2><?php esc_html_e( 'More opportunities like this', 'dixcoverhub-custom-ui' ); ?></h2></div><a href="<?php echo esc_url( $category_link ); ?>"><?php echo esc_html( sprintf( __( 'View %s', 'dixcoverhub-custom-ui' ), $category_label ) ); ?> <span aria-hidden="true">↗</span></a></div><div class="dh-single-post-related-grid"><?php foreach ( $related_posts as $related ) : ?><a href="<?php echo esc_url( get_permalink( $related ) ); ?>"><span><?php echo esc_html( get_the_title( $related ) ); ?></span><small><?php echo esc_html( get_the_date( 'M j', $related ) ); ?></small></a><?php endforeach; ?></div></section><?php endif; ?>
				</article>
			</main>
			<?php if ( ! empty( $options['single_post_sidebar_enabled'] ) ) : ?><aside class="dh-single-post-sidebar"><section class="dh-single-post-featured"><header><div><p><?php esc_html_e( "Editor's selection", 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Featured Opportunities', 'dixcoverhub-custom-ui' ); ?></h2></div><a href="<?php echo esc_url( home_url( '/opportunities/' ) ); ?>"><?php esc_html_e( 'View all', 'dixcoverhub-custom-ui' ); ?></a></header><?php if ( $featured_posts ) : ?><div><?php foreach ( $featured_posts as $featured ) : $image = get_the_post_thumbnail_url( $featured, 'thumbnail' ); $featured_categories = get_the_category( $featured->ID ); ?><a class="dh-single-post-sidebar-item" href="<?php echo esc_url( get_permalink( $featured ) ); ?>"><span class="dh-single-post-sidebar-image"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" /><?php else : ?><span aria-hidden="true">✦</span><?php endif; ?></span><span class="dh-single-post-sidebar-copy"><span class="dh-single-post-sidebar-meta"><small><?php echo esc_html( $featured_categories ? $featured_categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ) ); ?></small><time><?php echo esc_html( get_the_date( 'M j', $featured ) ); ?></time></span><strong><?php echo esc_html( get_the_title( $featured ) ); ?></strong><?php $featured_data = get_post_meta( $featured->ID, '_dixcoverhub_opportunity_data', true ); if ( is_array( $featured_data ) && ! empty( $featured_data['provider_name'] ) ) : ?><small class="dh-single-post-sidebar-provider"><?php echo esc_html( $featured_data['provider_name'] ); ?><?php if ( ! empty( $featured_data['location'] ) ) : ?> / <?php echo esc_html( $featured_data['location'] ); ?><?php endif; ?></small><?php endif; ?></span></a><?php endforeach; ?></div><?php else : ?><p class="dh-single-post-empty-featured"><?php esc_html_e( 'New opportunities will appear here as they are published.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?></section></aside><?php endif; ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
