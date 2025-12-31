<?php
/**
 * Plugin Name: Hebrew Dates Admin
 * Plugin URI: https://github.com/pauljacobson/hebrew-dates-wp-admin
 * Description: Displays the current Hebrew date in a WordPress Admin dashboard widget, featuring both Hebrew characters and transliteration, plus Jewish holidays and events.
 * Version: 1.0.0
 * Author: Paul Jacobson
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: hebrew-dates-admin
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Hebrew_Dates_Admin
 */

// Prevent direct file access.
// This is a security measure to ensure the file is only executed
// within the WordPress environment, not accessed directly via URL.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version constant.
 * Used for cache busting and version tracking.
 */
define( 'HEBREW_DATES_ADMIN_VERSION', '1.0.0' );

/**
 * Plugin directory path constant.
 * Used for including files relative to the plugin directory.
 */
define( 'HEBREW_DATES_ADMIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Include the Hebcal API class.
 *
 * This class handles all communication with the Hebcal REST API,
 * including caching responses in WordPress transients.
 */
require_once HEBREW_DATES_ADMIN_PATH . 'includes/class-hebcal-api.php';

/**
 * Register the dashboard widget.
 *
 * Hooks into 'wp_dashboard_setup' which fires after core dashboard
 * widgets are registered. This is the correct hook for adding
 * custom dashboard widgets.
 */
add_action( 'wp_dashboard_setup', 'hebrew_dates_admin_register_widget' );

/**
 * Register the Hebrew Date dashboard widget.
 *
 * Uses wp_add_dashboard_widget() to add a widget to the WordPress
 * Admin Dashboard. The widget will appear in the normal dashboard
 * widget area and can be moved/hidden by users.
 *
 * @since 1.0.0
 */
function hebrew_dates_admin_register_widget() {
	wp_add_dashboard_widget(
		'hebrew_dates_admin_widget',                          // Widget ID (unique identifier).
		__( 'Hebrew Date', 'hebrew-dates-admin' ),            // Widget title (translatable).
		'hebrew_dates_admin_display_widget'                   // Display callback function.
	);
}

/**
 * Display the Hebrew Date widget content.
 *
 * This callback function renders the widget's content. It:
 * 1. Fetches the Hebrew date from the Hebcal API (with caching)
 * 2. Displays the date in Hebrew characters (primary)
 * 3. Shows the transliterated version below
 * 4. Lists any Jewish holidays/events for the day
 *
 * All output is properly escaped to prevent XSS vulnerabilities.
 *
 * @since 1.0.0
 */
function hebrew_dates_admin_display_widget() {
	// Instantiate the API client and fetch today's Hebrew date.
	$api    = new Hebcal_API();
	$result = $api->get_hebrew_date();

	// Get the URL to the plugin's assets directory for the icon.
	$icon_url = plugin_dir_url( __FILE__ ) . 'assets/icon.svg';

	// Start widget output.
	echo '<div class="hebrew-date-widget">';

	if ( $result['success'] ) {
		// Main content area: flexbox layout with dates and icon spaced around.
		echo '<div class="hebrew-date-content" style="display: flex; align-items: center; justify-content: space-around;">';

		// Left side: Date text container (centered within its own space).
		echo '<div class="hebrew-date-text" style="text-align: center;">';

		// Primary display: Hebrew date in Hebrew characters.
		// Using RTL direction for proper Hebrew display.
		printf(
			'<p class="hebrew-date-primary" style="font-size: 2em; direction: rtl; margin: 0.25em 0; font-family: \'Times New Roman\', serif;">%s</p>',
			esc_html( $result['hebrew'] )
		);

		// Secondary display: Transliterated Hebrew date.
		// Smaller, muted text for those who prefer Latin characters.
		printf(
			'<p class="hebrew-date-transliterated" style="font-size: 1.1em; color: #666; margin: 0.25em 0;">%s</p>',
			esc_html( $result['transliterated'] )
		);

		echo '</div>'; // End .hebrew-date-text

		// Right side: Calendar icon.
		printf(
			'<div class="hebrew-date-icon"><img src="%s" alt="%s" style="width: 64px; height: 64px;" /></div>',
			esc_url( $icon_url ),
			esc_attr__( 'Hebrew Calendar', 'hebrew-dates-admin' )
		);

		echo '</div>'; // End .hebrew-date-content

		// Events display: Jewish holidays or special days.
		// Only shown if there are events for today.
		if ( ! empty( $result['events'] ) ) {
			echo '<div class="hebrew-date-events" style="text-align: center; margin-top: 1em; padding-top: 0.5em; border-top: 1px solid #eee;">';

			foreach ( $result['events'] as $event ) {
				printf(
					'<p style="font-size: 0.95em; color: #0073aa; margin: 0.25em 0; font-style: italic;">%s</p>',
					esc_html( $event )
				);
			}

			echo '</div>';
		}
	} else {
		// Error state: API failed and no cached data available.
		// Display a friendly message instead of breaking the widget.
		printf(
			'<p class="hebrew-date-error" style="text-align: center; color: #666; font-style: italic;">%s</p>',
			esc_html__( 'Unable to load Hebrew date. Please try again later.', 'hebrew-dates-admin' )
		);
	}

	echo '</div>';
}
