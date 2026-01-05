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
 * 1. Determines if current time is before or after sunset (Hebrew day boundary)
 * 2. Checks for cached data in WordPress transients (separate caches for day/evening)
 * 3. If no cache, fetches from Hebcal API with gs=on parameter when after sunset
 * 4. Parses JSON response and extracts Hebrew date, transliteration, and events
 * 5. Caches successful results (up to 2 API calls per day: one for day, one for evening)
 * 6. Returns structured array with date information or error state
 *
 * ## Sunset-aware behavior:
 * - Hebrew dates begin at sunset, not midnight
 * - Uses PHP's date_sunset() to calculate sunset for the configured location
 * - Default location is Jerusalem; customizable via WordPress filters
 * - Maintains separate cache entries for before/after sunset
 *
 * ## Caching strategy:
 * - Up to 2 API calls per day per site (before sunset + after sunset)
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
	 * Default latitude for sunset calculation (Jerusalem).
	 *
	 * Can be overridden with the 'hebrew_dates_admin_latitude' filter.
	 *
	 * @var float
	 */
	const DEFAULT_LATITUDE = 31.7683;

	/**
	 * Default longitude for sunset calculation (Jerusalem).
	 *
	 * Can be overridden with the 'hebrew_dates_admin_longitude' filter.
	 *
	 * @var float
	 */
	const DEFAULT_LONGITUDE = 35.2137;

	/**
	 * Get the Hebrew date for today.
	 *
	 * Returns cached data if available, otherwise fetches from API.
	 * Accounts for the Hebrew day beginning at sunset by checking
	 * the current time against sunset and requesting the appropriate date.
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

		// Determine if we're after sunset (Hebrew day has advanced).
		$after_sunset = $this->is_after_sunset();

		// Check cache first (separate caches for before/after sunset).
		$cached = $this->get_cached_date( $today, $after_sunset );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch from API with sunset awareness.
		$result = $this->fetch_from_api( $today, $after_sunset );

		// Cache successful results.
		if ( $result['success'] ) {
			$this->cache_date( $today, $after_sunset, $result );
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
	 * Uses separate cache keys for before/after sunset to ensure
	 * the correct Hebrew date is returned based on time of day.
	 *
	 * @param string $date         Date in YYYY-MM-DD format.
	 * @param bool   $after_sunset Whether current time is after sunset.
	 * @return array|false Cached data array or false if not cached.
	 */
	private function get_cached_date( $date, $after_sunset ) {
		$cache_key = $this->build_cache_key( $date, $after_sunset );
		return get_transient( $cache_key );
	}

	/**
	 * Cache Hebrew date data.
	 *
	 * Stores data with a cache key that includes the sunset state,
	 * ensuring before-sunset and after-sunset dates are cached separately.
	 *
	 * @param string $date         Date in YYYY-MM-DD format.
	 * @param bool   $after_sunset Whether current time is after sunset.
	 * @param array  $data         Data to cache.
	 * @return bool True if cached successfully.
	 */
	private function cache_date( $date, $after_sunset, $data ) {
		$cache_key = $this->build_cache_key( $date, $after_sunset );
		return set_transient( $cache_key, $data, self::CACHE_DURATION );
	}

	/**
	 * Build a cache key that includes sunset state.
	 *
	 * Creates unique cache keys for before/after sunset to ensure
	 * the correct Hebrew date is served throughout the day.
	 *
	 * @param string $date         Date in YYYY-MM-DD format.
	 * @param bool   $after_sunset Whether current time is after sunset.
	 * @return string Cache key for WordPress transient.
	 */
	private function build_cache_key( $date, $after_sunset ) {
		$suffix = $after_sunset ? '_evening' : '_day';
		return self::CACHE_KEY_PREFIX . $date . $suffix;
	}

	/**
	 * Fetch Hebrew date from Hebcal API.
	 *
	 * Uses WordPress HTTP API (wp_remote_get) for the request,
	 * which handles SSL, timeouts, and redirects properly.
	 *
	 * When $after_sunset is true, adds the 'gs=on' parameter to the API
	 * request, which tells Hebcal to return the Hebrew date that began
	 * at sunset (i.e., the next Hebrew day).
	 *
	 * @param string $date         Date in YYYY-MM-DD format.
	 * @param bool   $after_sunset Whether current time is after sunset.
	 * @return array Result array with success status and data or error.
	 */
	private function fetch_from_api( $date, $after_sunset = false ) {
		// Build API URL with parameters.
		$params = array(
			'cfg'  => 'json',  // Response format.
			'date' => $date,   // Gregorian date to convert.
			'g2h'  => '1',     // Gregorian to Hebrew conversion.
		);

		// Add sunset parameter when after sunset.
		// The 'gs=on' parameter tells Hebcal that the query is for
		// after sunset, so it returns the Hebrew date that began at sunset
		// (which is one day ahead of the daytime Hebrew date).
		if ( $after_sunset ) {
			$params['gs'] = 'on';
		}

		$url = add_query_arg( $params, self::API_BASE_URL );

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

	/**
	 * Determine if current time is after sunset.
	 *
	 * Uses PHP's date_sunset() function to calculate sunset time based on
	 * geographic coordinates. The Hebrew day begins at sunset, so after
	 * sunset the Hebrew date advances to the next day.
	 *
	 * Location coordinates default to Jerusalem but can be customized using
	 * the 'hebrew_dates_admin_latitude' and 'hebrew_dates_admin_longitude'
	 * filters for sites serving users in different locations.
	 *
	 * @return bool True if current time is after sunset, false otherwise.
	 */
	private function is_after_sunset() {
		// Get location coordinates, allowing customization via filters.
		// Default is Jerusalem (31.7683°N, 35.2137°E).
		$latitude  = apply_filters( 'hebrew_dates_admin_latitude', self::DEFAULT_LATITUDE );
		$longitude = apply_filters( 'hebrew_dates_admin_longitude', self::DEFAULT_LONGITUDE );

		// Get current time in WordPress timezone.
		$timezone = wp_timezone();
		$now      = new DateTime( 'now', $timezone );

		// Calculate sunset timestamp for today at the specified location.
		// SUNFUNCS_RET_TIMESTAMP returns Unix timestamp.
		// We use the current timestamp as the base date for calculation.
		$sunset_timestamp = date_sunset(
			$now->getTimestamp(),
			SUNFUNCS_RET_TIMESTAMP,
			$latitude,
			$longitude,
			// Zenith: 90°50' is the standard definition for sunset
			// (when the sun's upper edge disappears below the horizon).
			90.833333
		);

		// If sunset calculation fails (e.g., polar regions), default to false.
		// This ensures we don't incorrectly advance the date.
		if ( false === $sunset_timestamp ) {
			return false;
		}

		return $now->getTimestamp() >= $sunset_timestamp;
	}
}
