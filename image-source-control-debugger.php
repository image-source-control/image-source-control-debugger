<?php
/**
 * Plugin Name:       Image Source Control – Debugger
 * Plugin URI:        https://imagesourcecontrol.com/
 * Description:       Debug features for Image Source Control. Requires Image Source Control (Free or Pro).
 * Version:           1.0.5
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

require_once 'includes/debug-bar.php';
require_once 'includes/media-library-meta-information.php';
