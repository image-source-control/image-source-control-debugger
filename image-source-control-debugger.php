
<?php
/**
 * Plugin Name:       Image Source Control – Debugger
 * Plugin URI:        https://imagesourcecontrol.com/
 * Description:       Adds an Admin Bar menu to debug Image Source Control attributions on singular pages for administrators. Requires Image Source Control (Free or Pro).
 * Version:           1.0.1
 * Author:            Thomas Maier
 * Author URI:        https://imagesourcecontrol.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP:      7.4
 * Requires at least: 5.8
 * Requires Plugins:  image-source-control-isc | image-source-control
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add ISC Debug menu to the Admin Bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
 * @return void
 */
function isc_debug_admin_bar_menu( $wp_admin_bar ) {
	// 1. Check Visibility Conditions:
	// - Admin bar must be showing.
	// - User must have 'manage_options' capability (typically Administrators).
	// - Must be a singular page (post, page, custom post type).
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! is_singular() ) {
		return;
	}

	// 2. Check if Image Source Control is active.
	// Use the main ISC plugin class.
	if ( ! class_exists( 'ISC\\Plugin' ) ) {
		// Optional: Add a node indicating ISC is not active if needed for debugging the debugger itself.
		/*
		$wp_admin_bar->add_node( [
			'id'    => 'isc-debug-inactive',
			'title' => 'ISC Debug (ISC not active)',
			'href'  => '#',
		] );
		*/
		return; // Exit if ISC is not active.
	}

	// 3. Get Current Post ID.
	$current_post_id = get_the_ID();
	if ( ! $current_post_id ) {
		return; // Should not happen on is_singular(), but check anyway.
	}

	// 4. Retrieve indexed image data for this post.
	// The 'isc_post_images' meta stores an array: [attachment_id => [url, ...], ...]
	$isc_post_images = get_post_meta( $current_post_id, 'isc_post_images', true );

	// 5. Add the Main "ISC Debug" Admin Bar Node.
	$wp_admin_bar->add_node(
		[
			'id'    => 'isc-debug',
			'title' => 'ISC Debug',
			'href'  => '#', // Parent node is not clickable.
		]
	);

	// 6. Handle Case: No Images Found by ISC for this post.
	if ( empty( $isc_post_images ) || ! is_array( $isc_post_images ) ) {
		$wp_admin_bar->add_node(
			[
				'id'     => 'isc-debug-no-images',
				'title'  => 'No ISC-indexed images found for this post.',
				'parent' => 'isc-debug',
				'href'   => false,
			]
		);
		return;
	}

	// 7. Process and Add Each Image Found.
	foreach ( $isc_post_images as $attachment_id => $image_data ) {
		// Ensure attachment ID is a valid positive integer.
		if ( ! is_numeric( $attachment_id ) || $attachment_id <= 0 ) {
			continue; // Ignore entries without a valid attachment ID.
		}

		// Get the image URL from the stored data (usually the first element).
		$image_url = is_string( $image_data ) ? $image_data : '';

		// Fallback if URL wasn't in the expected format in meta.
		if ( empty( $image_url ) ) {
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				$image_url = '(URL not found)'; // Handle cases where URL is truly missing.
			}
		}

		// Get the relative path from the URL.
		$relative_url = '(URL parse error)'; // Default in case of parsing failure.
		if ( $image_url !== '(URL not found)' ) {
			$parsed_url = wp_parse_url( $image_url, PHP_URL_PATH );
			if ( is_string( $parsed_url ) && ! empty( $parsed_url ) ) {
				$relative_url = $parsed_url;
			} elseif ( $image_url === '(URL not found)' ) {
				$relative_url = '(URL not found)';
			}
		}


		// Get the raw attribution text from the attachment's post meta.
		$attribution_text = get_post_meta( $attachment_id, 'isc_image_source', true );

		// Prepare display text: Attribution or Warning.
		$display_attribution = '';
		if ( ! empty( $attribution_text ) && is_string( $attribution_text ) ) {
			$display_attribution = trim( $attribution_text );
		} else {
			// Use a warning emoji if no attribution text is found.
			$display_attribution = '⚠️ No attribution';
		}

		// Format the final string for the admin bar node title.
		$node_title = sprintf(
			'%d | %s | %s',
			$attachment_id,
			esc_html( $relative_url ),
			esc_html( $display_attribution )
		);

		// Add a node for this specific image.
		$wp_admin_bar->add_node(
			[
				'id'     => 'isc-debug-image-' . $attachment_id,
				'title'  => $node_title,
				'parent' => 'isc-debug',
				'href'   => false, // Make image entries non-clickable.
				'meta'   => [ 'class' => 'isc-debug-item' ], // Add class for potential styling.
			]
		);
	}
}
// Hook into the admin bar menu rendering action with a high priority.
add_action( 'admin_bar_menu', 'isc_debug_admin_bar_menu', 999 );

/**
 * Optional: Add basic CSS for better readability of the debug items.
 *
 * @return void
 */
function isc_debug_admin_bar_styles() {
	// Only output styles when the menu is likely visible and ISC is active.
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! is_singular() ) {
		return; // Conditions not met for showing the menu.
	}
	// Check if ISC is active before adding styles.
	if ( ! class_exists( 'ISC\\Plugin' ) ) {
		return; // ISC not active.
	}

	// Conditions are met, output the styles.
	?>
	<style>
		/* Target the container for the image list items */
		#wp-admin-bar-isc-debug-default .ab-item {
			/* Prevent text wrapping */
			white-space: nowrap;
			/* Hide overflow */
			overflow: hidden;
			/* Show ellipsis (...) for overflow */
			text-overflow: ellipsis;
			/* Set a max-width to prevent overly long items */
			max-width: 600px; /* Adjust as needed */
			/* Override default height/line-height if necessary for readability */
			height: auto;
			line-height: 1.6;
			padding-top: 4px;
			padding-bottom: 4px;
			/* Use a monospace font for better alignment */
			font-family: Consolas, Monaco, monospace;
			font-size: 12px;
		}
		/* Optional: Style the main 'ISC Debug' item */
		#wp-admin-bar-isc-debug > .ab-item {
			/* Example: Add a small icon or different background */
		}
	</style>
	<?php
}
// Add styles to the frontend <head>.
add_action( 'wp_head', 'isc_debug_admin_bar_styles' );
// Add styles to the admin <head> (though menu primarily appears on frontend).
add_action( 'admin_head', 'isc_debug_admin_bar_styles' );
        