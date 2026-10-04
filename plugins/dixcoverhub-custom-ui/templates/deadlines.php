<?php
/** Custom deadline planner route template. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo DixcoverHub_Custom_UI_Deadlines::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render escapes dynamic values.
get_footer();
