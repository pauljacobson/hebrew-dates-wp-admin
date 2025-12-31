<?php
/**
 * Hebcal API Client
 *
 * Handles fetching Hebrew date data from the Hebcal REST API
 * with WordPress transient caching for performance and reliability.
 *
 * @package Hebrew_Dates_Admin
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hebcal_API
 *
 * Fetches and caches Hebrew date information from the Hebcal converter API.
 *
 * ## How it works:
 * 1. Checks for cached data in WordPress transients (24-hour cache)
 * 2. If no cache, fetches from Hebcal API using WordPress HTTP API
 * 3. Parses JSON response and extracts Hebrew date, transliteration, and events
 * 4. Caches successful responses for 24 hours
 * 5. Returns structured array with date information or error state
 *
 * ## Why 24-hour caching:
 * - Hebrew date only changes once per day (at sunset, technically)
 * - Reduces API calls to ~1 per day per site
 * - Provides resilience if Hebcal API is temporarily unavailable
 * - Well within Hebcal's rate limit (90 requests/10 seconds)
 */
class Hebcal_API {

	/**
	 * Hebcal API base URL for date conversion.
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://www.hebcal.com/converter';

	/**
	 * Cache duration in seconds (24 hours).
	 *
	 * @var int
	 */
	const CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Transient key prefix for cached Hebrew dates.
	 *
	 * @var string
	 */
	const CACHE_KEY_PREFIX = 'hebrew_date_';

	/**
	 * Get the Hebrew date for today.
	 *
	 * Returns cached data if available, otherwise fetches from API.
	 *
	 * @return array {
	 *     Hebrew date information.
	 *
	 *     @type bool   $success       Whether the data was retrieved successfully.
	 *     @type string $hebrew        Hebrew date in Hebrew characters (e.g., "א׳ בְּטֵבֵת תשפ״ה").
	 *     @type string $transliterated Transliterated date (e.g., "1 Tevet 5785").
	 *     @type array  $events        Array of events/holidays for this date.
	 *     @type string $error         Error message if $success is false.
	 * }
	 */
	public function get_hebrew_date() {
		// Get today's date in site's timezone.
		$today = $this->get_today_date();

		// Check cache first.
		$cached = $this->get_cached_date( $today );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch from API.
		$result = $this->fetch_from_api( $today );

		// Cache successful results.
		if ( $result['success'] ) {
			$this->cache_date( $today, $result );
		}

		return $result;
	}

	/**
	 * Get today's date formatted for the API.
	 *
	 * Uses WordPress's wp_date() function which respects the site's
	 * configured timezone (Settings > General > Timezone).
	 *
	 * @return string Date in YYYY-MM-DD format.
	 */
	private function get_today_date() {
		// wp_date() is timezone-aware using WordPress settings.
		// This ensures the Hebrew date matches the site's local date,
		// not the server's timezone.
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Get cached Hebrew date data.
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @return array|false Cached data array or false if not cached.
	 */
	private function get_cached_date( $date ) {
		$cache_key = self::CACHE_KEY_PREFIX . $date;
		return get_transient( $cache_key );
	}

	/**
	 * Cache Hebrew date data.
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @param array  $data Data to cache.
	 * @return bool True if cached successfully.
	 */
	private function cache_date( $date, $data ) {
		$cache_key = self::CACHE_KEY_PREFIX . $date;
		return set_transient( $cache_key, $data, self::CACHE_DURATION );
	}

	/**
	 * Fetch Hebrew date from Hebcal API.
	 *
	 * Uses WordPress HTTP API (wp_remote_get) for the request,
	 * which handles SSL, timeouts, and redirects properly.
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @return array Result array with success status and data or error.
	 */
	private function fetch_from_api( $date ) {
		// Build API URL with parameters.
		$url = add_query_arg(
			array(
				'cfg'  => 'json',  // Response format.
				'date' => $date,   // Gregorian date to convert.
				'g2h'  => '1',     // Gregorian to Hebrew conversion.
			),
			self::API_BASE_URL
		);

		// Make the API request.
		// wp_remote_get() is the WordPress way to make HTTP requests.
		// It handles SSL certificates, follows redirects, and respects
		// WordPress proxy settings if configured.
		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 10, // 10 second timeout.
			)
		);

		// Check for request errors (network issues, timeouts, etc.).
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		// Check HTTP status code.
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %d: HTTP status code */
					__( 'API returned status code %d', 'hebrew-dates-admin' ),
					$status_code
				),
			);
		}

		// Parse JSON response.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Validate response structure.
		if ( ! is_array( $data ) || ! isset( $data['hebrew'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid API response format', 'hebrew-dates-admin' ),
			);
		}

		// Build transliterated date string from components.
		// Format: "1 Tevet 5785"
		$transliterated = sprintf(
			'%d %s %d',
			isset( $data['hd'] ) ? (int) $data['hd'] : 0,
			isset( $data['hm'] ) ? $data['hm'] : '',
			isset( $data['hy'] ) ? (int) $data['hy'] : 0
		);

		// Return structured result.
		return array(
			'success'       => true,
			'hebrew'        => $data['hebrew'],
			'transliterated' => $transliterated,
			'events'        => isset( $data['events'] ) ? $data['events'] : array(),
		);
	}
}
