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
	if ( '' === $summary && ! empty( $opportunity['summary'] ) && is_scalar( $opportunity['summary'] ) ) {
		$summary = sanitize_textarea_field( (string) $opportunity['summary'] );
	}
	$faq_rows      = get_post_meta( $post_id, '_dixcoverhub_faqs', true );
	$faq_rows      = is_array( $faq_rows ) && $faq_rows ? $faq_rows : ( isset( $opportunity['faqs'] ) && is_array( $opportunity['faqs'] ) ? $opportunity['faqs'] : array() );
	$faqs          = array();
	$faq_allowed_html = array(
		'p'          => array(),
		'br'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		's'          => array(),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'blockquote' => array(),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'code'       => array(),
		'pre'        => array(),
		'a'          => array( 'href' => true, 'rel' => true ),
	);
	foreach ( $faq_rows as $faq ) {
		if ( ! is_array( $faq ) ) {
			continue;
		}
		$question = sanitize_text_field( isset( $faq['question'] ) ? $faq['question'] : '' );
		$answer   = trim( wp_kses( isset( $faq['answer'] ) ? (string) $faq['answer'] : '', $faq_allowed_html, array( 'http', 'https', 'mailto' ) ) );
		if ( $question && trim( wp_strip_all_tags( $answer ) ) ) {
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
	$category_link  = home_url( '/opportunities/' );
	if ( $category ) {
		$category_link = ! empty( $options['archive_enabled'] ) ? add_query_arg( 'category', $category->slug, home_url( '/opportunities/' ) ) : get_category_link( $category->term_id );
	}
	$provider         = sanitize_text_field( (string) ( $opportunity['provider_name'] ?? '' ) );
	if ( '' === $provider ) { $provider = sanitize_text_field( (string) get_post_meta( $post_id, '_dixcoverhub_provider_name', true ) ); }
	$location         = sanitize_text_field( (string) ( $opportunity['location'] ?? '' ) );
	if ( '' === $location ) { $location = sanitize_text_field( (string) get_post_meta( $post_id, '_dixcoverhub_location', true ) ); }
	$employment_type  = sanitize_text_field( (string) ( $opportunity['employment_type'] ?? '' ) );
	if ( '' === $employment_type ) { $employment_type = sanitize_text_field( (string) get_post_meta( $post_id, '_dixcoverhub_employment_type', true ) ); }
	$duration         = sanitize_text_field( (string) ( $opportunity['duration'] ?? '' ) );
	$salary           = sanitize_text_field( (string) ( $opportunity['salary'] ?? '' ) );
	$deadline         = sanitize_text_field( (string) ( $opportunity['deadline'] ?? '' ) );
	if ( '' === $deadline ) { $deadline = sanitize_text_field( (string) get_post_meta( $post_id, '_dixcoverhub_deadline', true ) ); }
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
	$deadline_date      = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $deadline, wp_timezone() ) : false;
	$deadline_errors    = DateTimeImmutable::getLastErrors();
	if ( ! $deadline_date || ( is_array( $deadline_errors ) && ( $deadline_errors['warning_count'] || $deadline_errors['error_count'] ) ) ) {
		$deadline_date = false;
	}
	$deadline_timestamp = $deadline_date ? $deadline_date->setTime( 23, 59, 59 )->getTimestamp() : false;
	$is_expired         = $deadline_timestamp && $deadline_timestamp < current_time( 'timestamp', true );
	$deadline_label     = $deadline_timestamp ? wp_date( get_option( 'date_format' ), $deadline_timestamp ) : '';
	$today_date          = new DateTimeImmutable( 'today', wp_timezone() );
	$days_until_deadline = $deadline_date ? (int) $today_date->diff( $deadline_date )->format( '%r%a' ) : null;
	$excerpt_source    = has_excerpt( $post_id ) ? get_the_excerpt() : $summary;
	$excerpt_plain     = html_entity_decode( wp_strip_all_tags( (string) $excerpt_source ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$excerpt_plain     = trim( preg_replace( '/\s+/u', ' ', $excerpt_plain ) );
	$article_plain     = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $post->post_content ), true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$article_plain     = trim( preg_replace( '/\s+/u', ' ', $article_plain ) );
	$excerpt           = '';
	$looks_corrupted   = preg_match( '/pointer-events|threadscroll|scroll-mt|calc\(|class=|dir=|--[a-z-]+:/i', (string) $excerpt_source );
	if ( ! $looks_corrupted && strlen( $excerpt_plain ) >= 24 ) {
		$normalized_excerpt = strtolower( preg_replace( '/[^a-z0-9]+/', ' ', $excerpt_plain ) );
		$normalized_article = strtolower( preg_replace( '/[^a-z0-9]+/', ' ', $article_plain ) );
		if ( $normalized_excerpt && 0 === strpos( $normalized_article, substr( trim( $normalized_excerpt ), 0, 100 ) ) ) {
			if ( preg_match_all( '/[^.!?]+[.!?]/u', $article_plain, $sentences ) ) {
				foreach ( $sentences[0] as $sentence ) {
					if ( preg_match( '/\b(benefit|receive|support|funding|funded|stipend|training|mentor|certif|exposure|placement)\b/i', $sentence ) ) {
						$excerpt = trim( $sentence );
						break;
					}
				}
			}
		} elseif ( preg_match( '/^.{30,240}?[.!?](?:\s|$)/u', $excerpt_plain, $first_sentence ) ) {
			$excerpt = trim( $first_sentence[0] );
		} else {
			$excerpt = wp_html_excerpt( $excerpt_plain, 220, '…' );
		}
	}
	$featured_image = get_the_post_thumbnail_url( $post_id, 'large' );
	$featured_alt   = get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true );
	if ( ! $featured_image ) {
		foreach ( get_attached_media( 'image', $post_id ) as $attached_image ) {
			$attached_url = wp_get_attachment_image_url( $attached_image->ID, 'large' );
			if ( $attached_url ) {
				$featured_image = $attached_url;
				$featured_alt   = get_post_meta( $attached_image->ID, '_wp_attachment_image_alt', true );
				break;
			}
		}
	}
	$post_tags      = get_the_tags( $post_id );
	$post_tags      = is_array( $post_tags ) ? $post_tags : array();
	$accent         = sanitize_hex_color( $options['primary_color'] );
	$header_color   = sanitize_hex_color( $options['single_post_header_color'] );
	$layout_style   = '--dh-post-width:' . absint( $options['single_post_width'] ) . 'px;--dh-post-accent:' . ( $accent ? $accent : '#611f69' ) . ';--dh-post-hero:' . ( $header_color ? $header_color : '#f8f2fa' ) . ';';
	$categories_ids = wp_list_pluck( $categories, 'term_id' );
	$sidebar_active = ! empty( $options['single_post_sidebar_enabled'] );
	$show_featured_sidebar = $sidebar_active && ! empty( $options['single_post_sidebar_featured_enabled'] );
	$show_trending_sidebar = $sidebar_active && ! empty( $options['single_post_sidebar_trending_enabled'] );
	$show_latest_sidebar = $sidebar_active && ! empty( $options['single_post_sidebar_latest_enabled'] );
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
	$featured_posts = array();
	if ( $show_featured_sidebar ) {
		$featured_args['meta_key']   = '_dixcoverhub_featured';
		$featured_args['meta_value'] = '1';
		$featured_posts              = get_posts( $featured_args );
		if ( ! $featured_posts ) {
			unset( $featured_args['meta_key'], $featured_args['meta_value'] );
			$featured_posts = get_posts( $featured_args );
		}
	}
	$featured_ids = array_map( 'absint', wp_list_pluck( $featured_posts, 'ID' ) );
	$trending_posts = array();
	if ( $show_trending_sidebar ) {
		$trending_count = absint( $options['single_post_sidebar_trending_count'] );
		$trending_posts = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => max( 8, $trending_count ),
				'post__not_in'        => array_merge( array( $post_id ), $featured_ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => array( 'modified' => 'DESC', 'date' => 'DESC' ),
			)
		);
		$trending_posts = array_slice( $trending_posts, 0, $trending_count );
	}
	$trending_ids = array_map( 'absint', wp_list_pluck( $trending_posts, 'ID' ) );
	$related_posts = array();
	if ( ! empty( $options['single_post_show_related'] ) && $categories_ids ) {
		$related_posts = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'post__not_in'        => array_merge( array( $post_id ), $featured_ids, $trending_ids ),
				'category__in'        => $categories_ids,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);
	}
	$related_ids = array_map( 'absint', wp_list_pluck( $related_posts, 'ID' ) );
	$latest_posts = array();
	if ( $show_latest_sidebar ) {
		$latest_count = absint( $options['single_post_sidebar_latest_count'] );
		$latest_posts = get_posts(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => max( 8, $latest_count ),
				'post__not_in'        => array_merge( array( $post_id ), $featured_ids, $trending_ids, $related_ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => array( 'date' => 'DESC' ),
			)
		);
		$latest_posts = array_slice( $latest_posts, 0, $latest_count );
	}
	$sidebar_has_content = $show_featured_sidebar || ( $show_trending_sidebar && $trending_posts ) || ( $show_latest_sidebar && $latest_posts );
	$sidebar_item_meta = static function ( $item_id ) {
		$data     = get_post_meta( $item_id, '_dixcoverhub_opportunity_data', true );
		$provider = is_array( $data ) && isset( $data['provider_name'] ) && is_scalar( $data['provider_name'] ) ? sanitize_text_field( (string) $data['provider_name'] ) : '';
		$location = is_array( $data ) && isset( $data['location'] ) && is_scalar( $data['location'] ) ? sanitize_text_field( (string) $data['location'] ) : '';
		if ( '' === $provider ) {
			$provider = sanitize_text_field( (string) get_post_meta( $item_id, '_dixcoverhub_provider_name', true ) );
		}
		if ( '' === $location ) {
			$location = sanitize_text_field( (string) get_post_meta( $item_id, '_dixcoverhub_location', true ) );
		}
		return array( 'provider' => $provider, 'location' => $location );
	};
	$sidebar_image_url = static function ( $item_id ) {
		$image = get_the_post_thumbnail_url( $item_id, 'thumbnail' );
		if ( $image ) {
			return $image;
		}
		foreach ( get_attached_media( 'image', $item_id ) as $attachment ) {
			$image = wp_get_attachment_image_url( $attachment->ID, 'thumbnail' );
			if ( $image ) {
				return $image;
			}
		}
		return '';
	};
	$published_timestamp = get_post_time( 'U', true, $post_id );
	$elapsed_seconds     = max( 0, current_time( 'timestamp', true ) - $published_timestamp );
	if ( $elapsed_seconds < 60 ) {
		$relative_time = __( 'Just now', 'dixcoverhub-custom-ui' );
	} elseif ( $elapsed_seconds < HOUR_IN_SECONDS ) {
		$relative_time = floor( $elapsed_seconds / MINUTE_IN_SECONDS ) . 'm ago';
	} elseif ( $elapsed_seconds < DAY_IN_SECONDS ) {
		$relative_time = floor( $elapsed_seconds / HOUR_IN_SECONDS ) . 'h ago';
	} elseif ( $elapsed_seconds < 7 * DAY_IN_SECONDS ) {
		$relative_time = floor( $elapsed_seconds / DAY_IN_SECONDS ) . 'd ago';
	} elseif ( $elapsed_seconds < 35 * DAY_IN_SECONDS ) {
		$relative_time = floor( $elapsed_seconds / WEEK_IN_SECONDS ) . 'w ago';
	} elseif ( $elapsed_seconds < YEAR_IN_SECONDS ) {
		$relative_time = floor( $elapsed_seconds / ( 30 * DAY_IN_SECONDS ) ) . 'mo ago';
	} else {
		$relative_time = floor( $elapsed_seconds / YEAR_IN_SECONDS ) . 'y ago';
	}
	$share_title       = wp_strip_all_tags( get_the_title() );
	$share_permalink   = get_permalink();
	$share_x_url       = add_query_arg( array( 'text' => $share_title, 'url' => $share_permalink ), 'https://twitter.com/intent/tweet' );
	$share_linkedin_url = add_query_arg( 'url', $share_permalink, 'https://www.linkedin.com/sharing/share-offsite/' );
	$share_whatsapp_url = add_query_arg( 'text', $share_title . ' ' . $share_permalink, 'https://wa.me/' );
	$share_menu_id     = 'dh-single-share-menu-' . absint( $post_id );
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
	$opportunity_terms = get_the_terms( $post_id, 'dh_opportunity_type' );
	$analytics_content_type = ! empty( $opportunity ) || ( is_array( $opportunity_terms ) && $opportunity_terms ) ? 'opportunity' : 'article';
	$analytics_content_title = wp_strip_all_tags( get_the_title( $post_id ) );
	?>
	<div class="dh-single-post" data-dh-analytics-context data-content-type="<?php echo esc_attr( $analytics_content_type ); ?>" data-content-id="<?php echo esc_attr( (string) $post_id ); ?>" data-content-title="<?php echo esc_attr( $analytics_content_title ); ?>" style="<?php echo esc_attr( $layout_style ); ?>">
		<header class="dh-single-post-hero">
			<div class="dh-single-post-hero-inner">
				<div class="dh-single-post-heading">
					<a class="dh-single-post-category" href="<?php echo esc_url( $category_link ); ?>"><span aria-hidden="true">/</span><?php echo esc_html( $category_label ); ?></a>
					<h1><?php the_title(); ?></h1>
					<?php if ( $excerpt ) : ?><p class="dh-single-post-excerpt"><?php echo esc_html( wp_strip_all_tags( $excerpt ) ); ?></p><?php endif; ?>
					<div class="dh-single-post-meta">
						<time class="dh-single-post-meta-item" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'CalendarDaysIcon', 'dh-single-meta-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?><span><?php echo esc_html( $relative_time ); ?></span></time>
						<span aria-hidden="true">•</span><span class="dh-single-post-meta-item"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'UserRoundIcon', 'dh-single-meta-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?><span><?php echo esc_html( get_the_author() ); ?></span></span>
						<?php if ( $provider && 'article' === $analytics_content_type ) : ?><span aria-hidden="true">•</span><span><?php echo esc_html( $provider ); ?></span><?php endif; ?>
						<div class="dh-single-post-share" data-dh-share><span><?php esc_html_e( 'Share', 'dixcoverhub-custom-ui' ); ?></span><button class="dh-single-post-share-trigger" type="button" data-dh-share-toggle aria-controls="<?php echo esc_attr( $share_menu_id ); ?>" aria-expanded="false" aria-label="<?php esc_attr_e( 'Share this article', 'dixcoverhub-custom-ui' ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Share08Icon', 'dh-single-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicon. ?></button><div class="dh-single-share-menu" id="<?php echo esc_attr( $share_menu_id ); ?>" data-dh-share-menu role="group" aria-label="<?php esc_attr_e( 'Share options', 'dixcoverhub-custom-ui' ); ?>" hidden><button type="button" data-dh-copy-link data-dh-share-action data-dh-analytics="share" data-copy-url="<?php echo esc_url( $share_permalink ); ?>"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Link01Icon', 'dh-single-share-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicon. ?><span><?php esc_html_e( 'Copy link', 'dixcoverhub-custom-ui' ); ?></span></button><a href="<?php echo esc_url( $share_x_url ); ?>" target="_blank" rel="noopener noreferrer" data-dh-share-action data-dh-analytics="share"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'NewTwitterIcon', 'dh-single-share-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicon. ?><span><?php esc_html_e( 'Share on X', 'dixcoverhub-custom-ui' ); ?></span></a><a href="<?php echo esc_url( $share_linkedin_url ); ?>" target="_blank" rel="noopener noreferrer" data-dh-share-action data-dh-analytics="share"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Linkedin01Icon', 'dh-single-share-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicon. ?><span><?php esc_html_e( 'Share on LinkedIn', 'dixcoverhub-custom-ui' ); ?></span></a><a href="<?php echo esc_url( $share_whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer" data-dh-share-action data-dh-analytics="share"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'WhatsappIcon', 'dh-single-share-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicon. ?><span><?php esc_html_e( 'Share on WhatsApp', 'dixcoverhub-custom-ui' ); ?></span></a></div></div>
					</div>
				</div>
				<figure class="dh-single-post-featured-image">
					<?php if ( $featured_image ) : ?><a class="dh-single-post-image-open" href="<?php echo esc_url( $featured_image ); ?>" data-dh-image-open aria-label="<?php esc_attr_e( 'Enlarge featured image', 'dixcoverhub-custom-ui' ); ?>" title="<?php esc_attr_e( 'View larger', 'dixcoverhub-custom-ui' ); ?>"><img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $featured_alt ? $featured_alt : get_the_title() ); ?>" loading="eager" fetchpriority="high" /><span class="dh-single-post-image-action" aria-hidden="true"><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-single-post-image-action-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled Hugeicon. ?><span><?php esc_html_e( 'View larger', 'dixcoverhub-custom-ui' ); ?></span></span></a><?php else : ?><span class="dh-single-post-image-placeholder"><span><?php echo esc_html( $category_label ); ?></span></span><?php endif; ?>
				</figure>
			</div>
		</header>

		<div class="dh-single-post-layout">
			<main class="dh-single-post-main">
				<article <?php post_class( 'dh-single-post-article' ); ?><?php echo ! empty( $options['single_post_protect_content'] ) ? ' data-dh-copy-protected' : ''; ?>>
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
							<?php elseif ( 0 === $days_until_deadline ) : ?><p class="dh-single-post-deadline-notice" role="alert"><?php esc_html_e( 'Apply soon — the deadline is today.', 'dixcoverhub-custom-ui' ); ?></p>
							<?php elseif ( 1 === $days_until_deadline ) : ?><p class="dh-single-post-deadline-notice" role="status"><?php esc_html_e( 'Apply soon — the deadline is tomorrow.', 'dixcoverhub-custom-ui' ); ?></p>
							<?php elseif ( 2 === $days_until_deadline ) : ?><p class="dh-single-post-deadline-notice" role="status"><?php esc_html_e( 'Apply soon — the deadline is in 2 days.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?>
							<?php if ( $application_links && ! $is_expired ) : ?><div class="dh-single-post-apply-links"><?php foreach ( $application_links as $application ) : ?><a href="<?php echo esc_url( $application['url'] ); ?>" <?php echo 0 === strpos( $application['url'], 'mailto:' ) ? '' : 'target="_blank" rel="noopener noreferrer"'; ?> data-dh-analytics="application_click"><?php echo esc_html( $application['label'] ? $application['label'] : __( 'Apply on the official site', 'dixcoverhub-custom-ui' ) ); ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?></div>
							<?php elseif ( ! $application_links && ! $is_expired ) : ?><a class="dh-single-post-read-apply" href="#dh-single-post-content"><?php esc_html_e( 'Read application details', 'dixcoverhub-custom-ui' ); ?></a><?php endif; ?>
						</section>
					<?php endif; ?>
					<?php if ( ! empty( $options['single_post_show_faqs'] ) && $faqs ) : ?><section class="dh-single-post-faqs"><h2><?php esc_html_e( 'Frequently asked questions', 'dixcoverhub-custom-ui' ); ?></h2><?php foreach ( $faqs as $faq ) : ?><details><summary><?php echo esc_html( $faq['question'] ); ?></summary><div><?php echo wp_kses( nl2br( $faq['answer'] ), $faq_allowed_html, array( 'http', 'https', 'mailto' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized against the FAQ allow-list above. ?></div></details><?php endforeach; ?></section><?php endif; ?>
					<?php if ( ! empty( $options['single_post_show_tags'] ) && $post_tags ) : ?><footer class="dh-single-post-tags"><p><?php esc_html_e( 'Tags', 'dixcoverhub-custom-ui' ); ?></p><div><?php foreach ( $post_tags as $post_tag ) : ?><a href="<?php echo esc_url( get_tag_link( $post_tag->term_id ) ); ?>">#<?php echo esc_html( $post_tag->name ); ?></a><?php endforeach; ?></div></footer><?php endif; ?>
					<?php if ( $research_sources ) : ?><details class="dh-single-post-sources"><summary><?php esc_html_e( 'Research sources', 'dixcoverhub-custom-ui' ); ?></summary><ul><?php foreach ( $research_sources as $source ) : if ( ! is_array( $source ) || empty( $source['url'] ) || ! wp_http_validate_url( $source['url'] ) ) { continue; } ?><li><a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( ! empty( $source['title'] ) ? $source['title'] : wp_parse_url( $source['url'], PHP_URL_HOST ) ); ?></a></li><?php endforeach; ?></ul></details><?php endif; ?>
					<?php if ( ! empty( $options['single_post_show_related'] ) && ( $related_posts || $category ) ) : ?><section class="dh-single-post-related"><div class="dh-single-post-related-heading"><div><span><?php esc_html_e( 'Explore more', 'dixcoverhub-custom-ui' ); ?></span><h2><?php esc_html_e( 'More opportunities like this', 'dixcoverhub-custom-ui' ); ?></h2></div><a href="<?php echo esc_url( $category_link ); ?>"><?php echo esc_html( sprintf( __( 'View %s', 'dixcoverhub-custom-ui' ), $category_label ) ); ?> <span aria-hidden="true">↗</span></a></div><?php if ( $related_posts ) : ?><div class="dh-single-post-related-grid"><?php foreach ( $related_posts as $related ) : ?><a href="<?php echo esc_url( get_permalink( $related ) ); ?>"><span><?php echo esc_html( get_the_title( $related ) ); ?></span><small><?php echo esc_html( get_the_date( 'M j', $related ) ); ?></small></a><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
				</article>
			</main>
			<?php if ( $sidebar_active && $sidebar_has_content ) : ?>
				<aside class="dh-single-post-sidebar" aria-label="<?php esc_attr_e( 'More opportunities', 'dixcoverhub-custom-ui' ); ?>">
					<div class="dh-single-post-sidebar-stack">
						<?php if ( $show_featured_sidebar ) : ?>
							<section class="dh-single-post-featured">
								<header><div><p><?php esc_html_e( "Editor's selection", 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Featured Opportunities', 'dixcoverhub-custom-ui' ); ?></h2></div><a href="<?php echo esc_url( home_url( '/opportunities/' ) ); ?>"><?php esc_html_e( 'View all', 'dixcoverhub-custom-ui' ); ?></a></header>
								<?php if ( $featured_posts ) : ?>
									<div><?php foreach ( $featured_posts as $featured ) : $image = $sidebar_image_url( $featured->ID ); $featured_categories = get_the_category( $featured->ID ); $featured_meta = $sidebar_item_meta( $featured->ID ); $featured_provider = $featured_meta['provider']; $featured_location = $featured_meta['location']; ?>
										<a class="dh-single-post-sidebar-item" href="<?php echo esc_url( get_permalink( $featured ) ); ?>">
											<span class="dh-single-post-sidebar-image"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" /><?php else : ?><?php echo DixcoverHub_Custom_UI_Icons::svg( 'Image01Icon', 'dh-single-sidebar-placeholder-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?><?php endif; ?></span>
											<span class="dh-single-post-sidebar-copy"><span class="dh-single-post-sidebar-meta"><small><?php echo esc_html( $featured_categories ? $featured_categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ) ); ?></small><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $featured->ID ) ); ?>"><?php echo esc_html( get_the_date( 'M j', $featured->ID ) ); ?></time></span><strong><?php echo esc_html( get_the_title( $featured ) ); ?></strong><?php if ( $featured_provider || $featured_location ) : ?><small class="dh-single-post-sidebar-provider"><?php echo esc_html( implode( ' / ', array_filter( array( $featured_provider, $featured_location ) ) ) ); ?></small><?php endif; ?></span>
										</a>
									<?php endforeach; ?></div>
								<?php else : ?><p class="dh-single-post-empty-featured"><?php esc_html_e( 'New opportunities will appear here as they are published.', 'dixcoverhub-custom-ui' ); ?></p><?php endif; ?>
							</section>
						<?php endif; ?>

						<?php if ( $show_trending_sidebar && $trending_posts ) : ?>
							<section class="dh-single-post-trending">
								<header class="dh-single-post-sidebar-heading"><div><p><?php echo DixcoverHub_Custom_UI_Icons::svg( 'BarChartIcon', 'dh-single-sidebar-heading-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?><?php esc_html_e( "What's moving", 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Trending', 'dixcoverhub-custom-ui' ); ?></h2></div><a href="<?php echo esc_url( home_url( '/opportunities/' ) ); ?>"><?php esc_html_e( 'View all', 'dixcoverhub-custom-ui' ); ?></a></header>
								<div><?php foreach ( $trending_posts as $index => $trending ) : $trending_categories = get_the_category( $trending->ID ); $trending_meta = $sidebar_item_meta( $trending->ID ); $trending_provider = $trending_meta['provider']; $trending_location = $trending_meta['location']; $trending_meta = array_filter( array( $trending_provider, $trending_location ) ); ?>
									<a class="dh-single-post-trending-item" href="<?php echo esc_url( get_permalink( $trending ) ); ?>"><span class="dh-single-post-trending-rank"><?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?></span><span class="dh-single-post-trending-copy"><span class="dh-single-post-trending-meta"><small><?php echo esc_html( $trending_categories ? $trending_categories[0]->name : __( 'Opportunity', 'dixcoverhub-custom-ui' ) ); ?></small><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $trending->ID ) ); ?>"><?php echo esc_html( get_the_date( 'M j', $trending->ID ) ); ?></time></span><strong><?php echo esc_html( get_the_title( $trending ) ); ?></strong><?php if ( $trending_meta ) : ?><small class="dh-single-post-sidebar-provider"><?php echo esc_html( implode( ' / ', $trending_meta ) ); ?></small><?php endif; ?></span></a>
								<?php endforeach; ?></div>
							</section>
						<?php endif; ?>

						<?php if ( $show_latest_sidebar && $latest_posts ) : ?>
							<section class="dh-single-post-latest">
								<header class="dh-single-post-sidebar-heading"><div><p><?php echo DixcoverHub_Custom_UI_Icons::svg( 'CalendarDaysIcon', 'dh-single-sidebar-heading-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?><?php esc_html_e( 'Just in', 'dixcoverhub-custom-ui' ); ?></p><h2><?php esc_html_e( 'Latest Opportunities', 'dixcoverhub-custom-ui' ); ?></h2></div></header>
								<div><?php foreach ( $latest_posts as $latest ) : ?><a class="dh-single-post-latest-item" href="<?php echo esc_url( get_permalink( $latest ) ); ?>"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $latest->ID ) ); ?>"><?php echo esc_html( get_the_date( 'M j', $latest->ID ) ); ?></time><strong><?php echo esc_html( get_the_title( $latest ) ); ?></strong><?php echo DixcoverHub_Custom_UI_Icons::svg( 'ArrowUpRight01Icon', 'dh-single-sidebar-latest-arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe bundled SVG. ?></a><?php endforeach; ?></div>
							</section>
						<?php endif; ?>
					</div>
				</aside>
			<?php endif; ?>
		</div>
	</div>
	<?php
endwhile;

get_footer();
