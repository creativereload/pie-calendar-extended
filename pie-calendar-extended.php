<?php
/**
 * Plugin Name:       Pie Calendar Extended
 * Plugin URI:        https://creativereload.com
 * Description:       Extends Pie Calendar with extra display layouts (list/compact/column, via a shortcode and Gutenberg block) and Category/Tag filter controls on the native calendar.
 * Version:           0.0.3
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Requires Plugins:  pie-calendar
 * Author:            Bryan Miller
 * Author URI:        https://creativereload.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pie-calendar-extended
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PCE_VERSION', '0.0.3' );
define( 'PCE_FILE', __FILE__ );
define( 'PCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PCE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Backwards-compatible aliases. The layouts classes (PCL_*) were written
 * against these constants; keeping them lets those files carry over verbatim.
 */
define( 'PCL_VERSION', PCE_VERSION );
define( 'PCL_FILE', PCE_FILE );
define( 'PCL_PATH', PCE_PATH );
define( 'PCL_URL', PCE_URL );

/**
 * Load plugin classes.
 */
require_once PCE_PATH . 'includes/class-pcl-query.php';
require_once PCE_PATH . 'includes/class-pcl-render.php';
require_once PCE_PATH . 'includes/class-pcl-shortcode.php';
require_once PCE_PATH . 'includes/class-pcl-block.php';
require_once PCE_PATH . 'includes/class-pcl-customizer.php';
require_once PCE_PATH . 'includes/class-pcl-ajax.php';
require_once PCE_PATH . 'includes/class-pce-filter.php';

/**
 * Main plugin class. Boots everything and checks for Pie Calendar.
 */
final class Pie_Calendar_Extended {

	/**
	 * Singleton instance.
	 *
	 * @var Pie_Calendar_Extended|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_missing_dependency_notice' ) );
	}

	/**
	 * Boot the plugin pieces.
	 */
	public function init() {
		load_plugin_textdomain( 'pie-calendar-extended', false, dirname( plugin_basename( PCE_FILE ) ) . '/languages' );

		// Layouts feature set (shortcode + block + customizer). These register
		// themselves even if Pie Calendar is missing, but they will simply
		// render a friendly notice instead of events. This keeps activation
		// order (this plugin vs. Pie Calendar) from causing fatals.
		new PCL_Shortcode();
		new PCL_Block();
		new PCL_Customizer();
		new PCL_Ajax();

		// Filter feature set (Category/Tag controls on the native calendar).
		new PCE_Filter();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
	}

	/**
	 * Register (but don't necessarily enqueue) the layouts' front-end CSS/JS.
	 * Actual enqueue happens lazily when the shortcode/block is used.
	 */
	public function register_frontend_assets() {
		wp_register_style(
			'pcl-frontend',
			PCE_URL . 'assets/css/frontend.css',
			array(),
			PCE_VERSION
		);

		wp_register_script(
			'pcl-frontend',
			PCE_URL . 'assets/js/frontend.js',
			array(),
			PCE_VERSION,
			true
		);
	}

	/**
	 * Warn admins if Pie Calendar isn't active. We don't hard-block activation
	 * since the site owner may activate plugins in either order. Both feature
	 * sets depend on Pie Calendar, so a single notice covers the whole plugin.
	 */
	public function maybe_show_missing_dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$pie_calendar_active = defined( 'PIECAL_VERSION' )
			|| defined( 'PIE_CALENDAR_VERSION' )
			|| function_exists( 'piecal_get_events' )
			|| pce_is_plugin_active_for_notice( 'pie-calendar/pie-calendar.php' );

		if ( $pie_calendar_active ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses_post(
			__( '<strong>Pie Calendar Extended</strong> is active, but the <strong>Pie Calendar</strong> plugin was not detected. Please install and activate Pie Calendar so events have data to display.', 'pie-calendar-extended' )
		);
		echo '</p></div>';
	}
}

/**
 * Small helper since is_plugin_active() lives in an admin-only file that
 * isn't always loaded yet when this check runs.
 */
function pce_is_plugin_active_for_notice( $plugin ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active( $plugin );
}

Pie_Calendar_Extended::instance();
