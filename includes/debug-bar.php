<?php
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
		return;
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

	// Get upload directory info once
	$upload_dir       = wp_get_upload_dir();
	$upload_path_base = '';
	if ( $upload_dir && ! empty( $upload_dir['baseurl'] ) ) {
		$parsed_upload_url = wp_parse_url( $upload_dir['baseurl'] );
		if ( $parsed_upload_url && isset( $parsed_upload_url['path'] ) ) {
			// Ensure the path starts and ends with a slash for reliable comparison/replacement
			$upload_path_base = trailingslashit( $parsed_upload_url['path'] );
		}
	}

	// 7. Process and Add Each Image Found.
	foreach ( $isc_post_images as $attachment_id => $image_data ) {
		// Ensure attachment ID is a valid positive integer.
		if ( ! is_numeric( $attachment_id ) || $attachment_id <= 0 ) {
			continue; // Ignore entries without a valid attachment ID.
		}

		// Apply the fix: Get URL directly if it's a string, otherwise fallback
		$image_url = is_string( $image_data ) ? $image_data : '';

		if ( empty( $image_url ) && is_array( $image_data ) && isset( $image_data[0] ) && is_string( $image_data[0] ) ) {
			$image_url = $image_data[0]; // Try the array format if string wasn't found
		}

		if ( empty( $image_url ) ) {
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				$image_url = '(URL not found)'; // Handle cases where URL is truly missing.
			}
		}

		$relative_url = '(URL parse error)';

		if ( $image_url !== '(URL not found)' ) {
			$parsed_url = wp_parse_url( $image_url );
			if ( $parsed_url && isset( $parsed_url['path'] ) ) {
				$relative_url = $parsed_url['path']; // Use only the path part
			} elseif ( $image_url === '(URL not found)' ) {
				$relative_url = '(URL not found)';
			} else {
				// If path couldn't be parsed but it's not the 'not found' string, show the original URL
				$relative_url = $image_url;
			}
		}

		// Prepare display URL (shortened) and search path (full relative)
		$display_relative_url = $relative_url; // Default to full relative path
		$search_path          = $relative_url; // Keep search path as full relative path

		// Check if the relative URL starts with the upload path base
		if ( ! empty( $upload_path_base ) && strpos( $relative_url, $upload_path_base ) === 0 ) {
			// Remove the base upload path for display only
			$display_relative_url = '/' . ltrim( substr( $relative_url, strlen( $upload_path_base ) ), '/' );
		} elseif ( $relative_url === '(URL parse error)' || $relative_url === '(URL not found)' ) {
			$display_relative_url = $relative_url; // Keep error strings
			$search_path          = ''; // Don't search for error strings
		}
		// else: If it's not in uploads or couldn't parse, keep original relative path for display

		$attribution_text    = get_post_meta( $attachment_id, 'isc_image_source', true );
		$display_attribution = ( ! empty( $attribution_text ) && is_string( $attribution_text ) )
			? trim( $attribution_text )
			: '⚠️ No attribution';

		$edit_link        = get_edit_post_link( $attachment_id, 'raw' );
		$edit_url_escaped = $edit_link ? esc_url( $edit_link ) : '#';

		// Prepare clickable attachment ID with a specific class for styling
		$attachment_id_link = sprintf(
			'<a href="%s" target="_blank" title="%s" class="isc-debug-attachment-link" style="color:inherit; box-shadow:none;">%d</a>',
			$edit_url_escaped,
			esc_attr( 'Edit Attachment ' . $attachment_id ),
			$attachment_id
		);

		$relative_url_link = sprintf(
			'<span class="isc-debug-url-link" data-isc-debug-url="%s" title="%s">%s</span>',
			esc_attr( $search_path ),
			$display_relative_url,            // Tooltip text
			esc_html( $display_relative_url ) // The shortened path
		);

		// Combine parts for the node title
		$node_title_html = sprintf(
			'%s | %s | %s',
			$attachment_id_link,
			$relative_url_link, // Use the span here
			esc_html( $display_attribution )
		);

		// Add the node
		$wp_admin_bar->add_node(
			[
				'id'     => 'isc-debug-image-' . $attachment_id,
				'title'  => $node_title_html, // Use HTML title
				'parent' => 'isc-debug',
				'href'   => false, // Main node is not clickable
				'meta'   => [
					'class' => 'isc-debug-item',
				],
			]
		);
	}
}
add_action( 'admin_bar_menu', 'isc_debug_admin_bar_menu', 999 );

/**
 * Add CSS for the debug menu items and highlighting.
 *
 * @return void
 */
function isc_debug_admin_bar_styles() {
	// Conditions check - Corrected logic: Output styles IF the conditions are met
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! is_singular() ) {
		return;
	}
	if ( ! class_exists( 'ISC\\Plugin' ) ) {
		return;
	}
	?>
	<style>
		#wp-admin-bar-isc-debug-default .ab-item {
			height: auto;
			line-height: 1.6;
			padding-top: 4px;
			padding-bottom: 4px;
			font-family: Consolas, Monaco, monospace;
			font-size: 12px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			max-width: 600px; /* Adjust as needed */
		}
		/* General link styling within debug items */
		#wp-admin-bar-isc-debug-default .ab-item a,
		#wp-admin-bar-isc-debug-default .ab-item .isc-debug-url-link {
			display: inline;
			text-decoration: none;
			box-shadow: none;
			border: none;
		}
		/* Style for the clickable Attachment ID */
		#wp-admin-bar-isc-debug-default .ab-item a.isc-debug-attachment-link {
			text-decoration: underline;
			text-decoration-style: dotted;
		}
		#wp-admin-bar-isc-debug-default .ab-item a.isc-debug-attachment-link:hover {
			color: #00a0d2; /* WordPress blue */
			text-decoration-style: solid;
		}
		/* Style for the clickable URL span */
		#wp-admin-bar-isc-debug-default .ab-item .isc-debug-url-link {
			cursor: pointer;
			text-decoration: underline;
			text-decoration-style: dotted;
		}
		#wp-admin-bar-isc-debug-default .ab-item .isc-debug-url-link:hover {
			color: #00a0d2; /* WordPress blue */
			text-decoration-style: solid;
		}
		#wp-admin-bar-isc-debug > .ab-item {
			/* Optional styling for the main menu item */
		}
		/* Style for highlighting images on the page via JS */
		.isc-debug-highlight {
			outline: 3px solid #ff0000 !important; /* Red outline */
			box-shadow: 0 0 10px #ff0000 !important; /* Red glow */
			transition: outline 0.3s ease, box-shadow 0.3s ease;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'isc_debug_admin_bar_styles' );
add_action( 'admin_head', 'isc_debug_admin_bar_styles' );

/**
 * Add inline JavaScript for the URL clicking functionality.
 *
 * @return void
 */
function isc_debug_add_inline_script() {
	// Conditions check - must match menu visibility
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! is_singular() ) {
		return;
	}
	if ( ! class_exists( 'ISC\\Plugin' ) ) {
		return;
	}
	// Only add script on the frontend where the elements exist
	if ( is_admin() ) {
		return;
	}

	?>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const adminBar = document.getElementById('wpadminbar');
			const highlightClass = 'isc-debug-highlight';
			const currentHighlights = []; // Keep track of highlighted elements

			if (!adminBar) {
				console.log('ISC Debug: Admin bar not found.');
				return;
			}

			adminBar.addEventListener('click', function(event) {
				const target = event.target;

				// Check if the clicked element is our URL link or its child (like the text node inside)
				const urlLinkSpan = target.closest('.isc-debug-url-link');

				if (urlLinkSpan) {
					event.preventDefault();
					const urlPathToFind = urlLinkSpan.getAttribute('data-isc-debug-url');
					console.log('ISC Debug: Clicked. Searching for path:', urlPathToFind);

					if (!urlPathToFind || urlPathToFind === '(URL parse error)' || urlPathToFind === '(URL not found)') {
						console.log('ISC Debug: Invalid or missing URL path in data attribute.');
						return;
					}

					// Remove previous highlights
					while(currentHighlights.length > 0) {
						const el = currentHighlights.pop();
						if (el) el.classList.remove(highlightClass);
					}

					// Find IMG elements on the page
					const imgElements = document.querySelectorAll('body img');
					console.log(`ISC Debug: Found ${imgElements.length} img elements.`);
					let firstMatch = null;

					imgElements.forEach(img => {
						// Check src attribute
						const src = img.getAttribute('src');
						let matched = false;
						if (src && src.includes(urlPathToFind)) {
							console.log('ISC Debug: Match found in src:', src, 'for element:', img);
							matched = true;
						}

						// Check srcset attribute
						const srcset = img.getAttribute('srcset');
						if (!matched && srcset) {
							// Split srcset into individual URLs
							const sources = srcset.split(',').map(s => s.trim().split(' ')[0]); // Get just the URL part
							if (sources.some(sourceUrl => sourceUrl.includes(urlPathToFind))) {
								console.log('ISC Debug: Match found in srcset:', srcset, 'for element:', img);
								matched = true;
							}
						}

						// Check parent link href if image is linked
						if (!matched && img.parentElement && img.parentElement.tagName === 'A') {
							const linkHref = img.parentElement.getAttribute('href');
							if (linkHref && linkHref.includes(urlPathToFind)) {
								console.log('ISC Debug: Match found in parent link href:', linkHref, 'for element:', img);
								matched = true;
							}
						}

						if (matched) {
							img.classList.add(highlightClass);
							currentHighlights.push(img); // Add to tracked highlights
							if (!firstMatch) {
								firstMatch = img;
								console.log('ISC Debug: Setting first match:', firstMatch);
							}
						}
					});

					// Scroll to the first matched element
					if (firstMatch) {
						console.log('ISC Debug: Scrolling to first match:', firstMatch);
						firstMatch.scrollIntoView({
							behavior: 'smooth',
							block: 'center'
						});
						// Optional: Remove highlight after a delay
						// setTimeout(() => {
						//     while(currentHighlights.length > 0) {
						//         const el = currentHighlights.pop();
						//         if (el) el.classList.remove(highlightClass);
						//     }
						// }, 3000);
					} else {
						console.log('ISC Debug: No matching img element found for path:', urlPathToFind);
						// Optional: Provide feedback if no element was found
						// alert('Could not find element with URL path: ' + urlPathToFind);
					}
				}
			});
		});
	</script>
	<?php
}
// Add script to the frontend footer
add_action( 'wp_footer', 'isc_debug_add_inline_script' );