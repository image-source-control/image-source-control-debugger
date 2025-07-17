<?php

/**
 * Add meta information to the media library attachment edit screen.
 * - wp_posts.guid
 * - wp_postmeta._wp_attached_file
 * - wp_postmeta._wp_attachment_metadata
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the meta box
 *
 * @return void
 */
add_action(
	'add_meta_boxes',
	function () {
		add_meta_box(
			'isc-debug-media-meta',              // ID
			'ISC Debug: Media Meta', // title
			'isc_debug_render_attachment_meta_box',     // callback
			'attachment',                        // screen (attachment)
			'normal',                              // context
			'default'                            // priority
		);
	}
);

/**
 * Callback function to render the meta box content.
 *
 * @param WP_Post $post attachment post object.
 *
 * @return void
 */
function isc_debug_render_attachment_meta_box( $post ) {
	// wp_posts.guid
	$guid = esc_html( $post->guid );

	// wp_postmeta._wp_attached_file
	$attached_file = get_post_meta( $post->ID, '_wp_attached_file', true );
	$attached_file = esc_html( $attached_file );

	// wp_postmeta._wp_attachment_metadata
	$raw_metadata = get_post_meta( $post->ID, '_wp_attachment_metadata', true );

	if ( is_serialized( $raw_metadata ) ) {
		$metadata = @unserialize( $raw_metadata );
	} else {
		$metadata = $raw_metadata;
	}

	echo '<div class="isc-debug-media-meta">';
	echo '<table class="form-table">';
	echo '<tr><th>GUID</th><td><code>' . $guid . '</code></td></tr>';
	echo '<tr><th>_wp_attached_file</th><td><code>' . $attached_file . '</code></td></tr>';
	echo '<tr><th>_wp_attachment_metadata</th><td><pre style="max-height:200px; overflow:auto;"><code>' . esc_html( print_r( $metadata, true ) ) . '</code></pre></td></tr>';
	echo '</table>';
	echo '</div>';
}
