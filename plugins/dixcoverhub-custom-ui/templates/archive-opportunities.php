<?php
/** Custom archive route template. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo DixcoverHub_Custom_UI_Archive::render_archive(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_archive escapes its dynamic output.
get_footer();
