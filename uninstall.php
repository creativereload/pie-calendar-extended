<?php
/**
 * Uninstall handler for Pie Calendar Extended.
 *
 * Runs only when the user deletes the plugin from the WordPress admin. It
 * removes the global default colors saved by the Customizer, which are the
 * only data this plugin persists (stored as individual `pcl_*` options via
 * PCL_Customizer). Everything else the plugin reads — events, taxonomy terms —
 * belongs to Pie Calendar/WordPress and is intentionally left untouched.
 */

// Bail if WordPress didn't invoke this via the uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The customizer class defines the option list and naming; requiring the file
// just declares the class (no side effects), so we can reuse it as the single
// source of truth instead of hardcoding the option names here.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pcl-customizer.php';

if ( class_exists( 'PCL_Customizer' ) ) {
	foreach ( PCL_Customizer::color_fields() as $key => $label ) {
		delete_option( PCL_Customizer::option_name( $key ) );
	}
}
