<?php
/** Opt-in DixcoverHub front-page template. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
DixcoverHub_Custom_UI::render_home_content();
get_footer();
