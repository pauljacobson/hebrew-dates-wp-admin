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
 * - Uses PHP's date_sun_info() to calculate sunset for the site's location
 * - Location is automatically derived from WordPress timezone setting
 *   using PHP's DateTimeZone::getLocation() for accurate coordinates
 * - Falls back to Jerusalem for UTC offset timezones (e.g., "UTC+2")
 * - Coordinates can be overridden via WordPress filters for precise control
 * - Maintains separate cache entries for before/after sunset
 * - DST is handled automatically by PHP's DateTime functions
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
	 * Fallback latitude for sunset calculation (Jerusalem).
	 *
	 * Used when timezone location cannot be determined (e.g., UTC offsets).
	 * Can be overridden with the 'hebrew_dates_admin_latitude' filter.
	 *
	 * @var float
	 */
	const FALLBACK_LATITUDE = 31.7683;

	/**
	 * Fallback longitude for sunset calculation (Jerusalem).
	 *
	 * Used when timezone location cannot be determined (e.g., UTC offsets).
	 * Can be overridden with the 'hebrew_dates_admin_longitude' filter.
	 *
	 * @var float
	 */
	const FALLBACK_LONGITUDE = 35.2137;

	/**
	 * Maximum allowed API response size in bytes (16 KB).
	 *
	 * Hebcal converter responses are typically under 1 KB.
	 * This limit provides generous headroom while protecting against
	 * unexpectedly large or malformed responses consuming memory.
	 *
	 * @var int
	 */
	const MAX_RESPONSE_SIZE = 16384;

	/**
	 * Maximum number of events to retain from the API response.
	 *
	 * A single Hebrew date rarely has more than a handful of events
	 * (e.g., Shabbat + a holiday). This cap prevents an unexpectedly
	 * large events array from being cached and rendered.
	 *
	 * @var int
	 */
	const MAX_EVENTS = 10;

	/**
	 * Maximum allowed length for a single event string (200 characters).
	 *
	 * Event names from Hebcal are short descriptive strings (e.g.,
	 * "Chanukah: 3 Candles"). This cap protects against unexpected
	 * data from the API being stored or rendered.
	 *
	 * @var int
	 */
	const MAX_EVENT_LENGTH = 200;

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

		// Reject oversized responses before parsing.
		// Hebcal converter responses are typically under 1 KB; anything
		// significantly larger is unexpected and could indicate a problem
		// with the upstream service or a tampered response.
		if ( strlen( $body ) > self::MAX_RESPONSE_SIZE ) {
			return array(
				'success' => false,
				'error'   => __( 'API response exceeded maximum allowed size', 'hebrew-dates-admin' ),
			);
		}

		$data = json_decode( $body, true );

		// Validate response structure.
		// We check for the required fields that we use: 'hebrew' for the
		// Hebrew-character date string, and the component fields for
		// building the transliterated version.
		if ( ! is_array( $data ) || ! isset( $data['hebrew'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid API response format', 'hebrew-dates-admin' ),
			);
		}

		// Validate that the Hebrew date string is actually a string.
		if ( ! is_string( $data['hebrew'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid API response: hebrew field is not a string', 'hebrew-dates-admin' ),
			);
		}

		// Build transliterated date string from components.
		// Format: "1 Tevet 5785"
		$transliterated = sprintf(
			'%d %s %d',
			isset( $data['hd'] ) ? (int) $data['hd'] : 0,
			isset( $data['hm'] ) ? sanitize_text_field( (string) $data['hm'] ) : '',
			isset( $data['hy'] ) ? (int) $data['hy'] : 0
		);

		// Sanitize events: enforce string type, cap count and length.
		// The Hebcal API returns events as a simple array of strings
		// (e.g., ["Chanukah: 3 Candles", "Rosh Chodesh Tevet"]).
		// We filter out any non-string entries and truncate excessively
		// long values to protect against malformed upstream data.
		$events = array();
		if ( isset( $data['events'] ) && is_array( $data['events'] ) ) {
			foreach ( $data['events'] as $event ) {
				// Skip non-string entries entirely.
				if ( ! is_string( $event ) ) {
					continue;
				}

				// Truncate overly long event strings.
				if ( mb_strlen( $event, 'UTF-8' ) > self::MAX_EVENT_LENGTH ) {
					$event = mb_substr( $event, 0, self::MAX_EVENT_LENGTH, 'UTF-8' );
				}

				$events[] = $event;

				// Stop collecting once we hit the cap.
				if ( count( $events ) >= self::MAX_EVENTS ) {
					break;
				}
			}
		}

		// Return structured result.
		return array(
			'success'        => true,
			'hebrew'         => $data['hebrew'],
			'transliterated' => $transliterated,
			'events'         => $events,
		);
	}

	/**
	 * Determine if current time is after sunset.
	 *
	 * Uses PHP's date_sun_info() function to calculate sunset time based on
	 * geographic coordinates. The Hebrew day begins at sunset, so after
	 * sunset the Hebrew date advances to the next day.
	 *
	 * Location coordinates are automatically derived from the WordPress
	 * timezone setting using PHP's DateTimeZone::getLocation(). This ensures
	 * sunset is calculated for the site's configured location. DST is handled
	 * automatically by PHP's DateTime functions.
	 *
	 * Coordinates can be overridden using the 'hebrew_dates_admin_latitude'
	 * and 'hebrew_dates_admin_longitude' filters for precise control.
	 *
	 * @return bool True if current time is after sunset, false otherwise.
	 */
	private function is_after_sunset() {
		// Get current time in WordPress timezone.
		// DateTime automatically handles DST based on the timezone's rules.
		$timezone = wp_timezone();
		$now      = new DateTime( 'now', $timezone );

		// Get coordinates from the WordPress timezone.
		$coordinates = $this->get_timezone_coordinates( $timezone );

		// Allow filter overrides for precise location control.
		$latitude  = apply_filters( 'hebrew_dates_admin_latitude', $coordinates['latitude'] );
		$longitude = apply_filters( 'hebrew_dates_admin_longitude', $coordinates['longitude'] );

		// Get solar information for today at the specified location.
		// date_sun_info() returns an array with sunrise, sunset, and other
		// solar position timestamps. This replaces the deprecated
		// date_sunset() function (deprecated in PHP 8.1).
		$sun_info = date_sun_info( $now->getTimestamp(), $latitude, $longitude );

		// If sunset calculation fails or returns a non-numeric value
		// (e.g., polar regions where sun doesn't set — returns true/false
		// instead of a timestamp), default to false.
		// This ensures we don't incorrectly advance the date.
		if ( ! is_int( $sun_info['sunset'] ) ) {
			return false;
		}

		return $now->getTimestamp() >= $sun_info['sunset'];
	}

	/**
	 * Get geographic coordinates from a timezone.
	 *
	 * Uses PHP's DateTimeZone::getLocation() to retrieve the latitude and
	 * longitude associated with a timezone. This method leverages the IANA
	 * timezone database which includes geographic data for named timezones.
	 *
	 * Falls back to Jerusalem coordinates for:
	 * - UTC offset timezones (e.g., "UTC+2") which have no geographic location
	 * - Any timezone where getLocation() fails or returns invalid data
	 *
	 * @param DateTimeZone $timezone The timezone to get coordinates for.
	 * @return array {
	 *     Geographic coordinates.
	 *
	 *     @type float $latitude  Degrees north (negative for south).
	 *     @type float $longitude Degrees east (negative for west).
	 * }
	 */
	private function get_timezone_coordinates( $timezone ) {
		// Default to Jerusalem (fallback for UTC offsets or failures).
		$coordinates = array(
			'latitude'  => self::FALLBACK_LATITUDE,
			'longitude' => self::FALLBACK_LONGITUDE,
		);

		// Attempt to get location from the timezone.
		// getLocation() returns an array with 'latitude', 'longitude',
		// 'country_code', and 'comments' for named timezones.
		// Returns false for UTC offset timezones like "UTC+2".
		$location = $timezone->getLocation();

		if ( is_array( $location ) && isset( $location['latitude'], $location['longitude'] ) ) {
			// Validate that we have reasonable coordinates.
			// Latitude: -90 to 90, Longitude: -180 to 180.
			if ( abs( $location['latitude'] ) <= 90 && abs( $location['longitude'] ) <= 180 ) {
				$coordinates['latitude']  = (float) $location['latitude'];
				$coordinates['longitude'] = (float) $location['longitude'];
			}
		}

		return $coordinates;
	}
}
