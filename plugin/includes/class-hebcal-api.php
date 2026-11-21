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
 * 5. Caches successful results and briefly backs off after a failed request
 * 6. Returns structured array with date information or error state
 *
 * ## Sunset-aware behavior:
 * - Hebrew dates begin at sunset, not midnight
 * - Uses PHP's date_sun_info() to calculate sunset for the site's location
 * - Uses representative coordinates for the site's timezone
 * - Falls back to Jerusalem for UTC offset timezones (e.g., "UTC+2")
 * - Coordinates can be overridden via WordPress filters for precise control
 * - Maintains separate cache entries for before/after sunset
 * - DST is handled automatically by PHP's DateTime functions
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
	 * Seconds to wait before retrying a failed request.
	 *
	 * @var int
	 */
	const RETRY_DELAY = 30;

	/**
	 * Transient key prefix for validated Hebrew dates.
	 *
	 * @var string
	 */
	const CACHE_KEY_PREFIX = 'hebrew_dates_admin_v2_';

	/**
	 * Results shared by widget and block instances during this request.
	 *
	 * @var array
	 */
	private static $request_results = array();

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
	 * @param DateTimeImmutable|null $now Time in the site timezone. Defaults to the current time.
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
	public function get_hebrew_date( ?DateTimeImmutable $now = null ) {
		$now   = $now ?? current_datetime();
		$today = $now->format( 'Y-m-d' );

		// Determine if we're after sunset (Hebrew day has advanced).
		$after_sunset = $this->is_after_sunset( $now, $today );

		$site_id   = get_current_blog_id();
		$suffix    = $after_sunset ? '_evening' : '_day';
		$cache_key = self::CACHE_KEY_PREFIX . $today . $suffix;
		$retry_key = $cache_key . '_retry';

		if ( isset( self::$request_results[ $site_id ][ $cache_key ] ) ) {
			return self::$request_results[ $site_id ][ $cache_key ];
		}

		// Keep failures separate so a failed request cannot overwrite a successful lookup.
		$result = get_transient( $cache_key );
		if ( false === $result ) {
			$result = get_transient( $retry_key );
		}

		if ( false === $result ) {
			$result = $this->fetch_from_api( $today, $after_sunset );
			if ( $result['success'] ) {
				set_transient( $cache_key, $result, self::CACHE_DURATION );
			} else {
				set_transient( $retry_key, $result, self::RETRY_DELAY );
			}
		}

		self::$request_results[ $site_id ][ $cache_key ] = $result;
		return $result;
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
				'timeout'             => 3,
				// Read one extra byte so the body check can detect truncation.
				'limit_response_size' => self::MAX_RESPONSE_SIZE + 1,
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

		$data   = json_decode( $body, true );
		$hebrew = $data['hebrew'] ?? '';
		$day    = $data['hd'] ?? 0;
		$month  = $data['hm'] ?? '';
		$year   = $data['hy'] ?? 0;
		$month  = is_string( $month ) ? sanitize_text_field( $month ) : '';

		// Validate the Hebrew date and the components of its transliteration before caching.
		if ( ! is_string( $hebrew ) || '' === trim( $hebrew ) ||
			! is_int( $day ) || $day < 1 || $day > 30 ||
			'' === $month || ! is_int( $year ) || $year < 1 ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date in API response', 'hebrew-dates-admin' ),
			);
		}

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
			'hebrew'         => $hebrew,
			'transliterated' => sprintf( '%d %s %d', $day, $month, $year ),
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
	 * Uses representative coordinates from the site's timezone. DateTime
	 * applies the site's daylight saving rules when checking the sunset date.
	 *
	 * Coordinates can be overridden using the 'hebrew_dates_admin_latitude'
	 * and 'hebrew_dates_admin_longitude' filters for precise control.
	 *
	 * @param DateTimeImmutable $now  Current time in the site timezone.
	 * @param string            $date Local date in YYYY-MM-DD format.
	 * @return bool True if current time is after sunset, false otherwise.
	 */
	private function is_after_sunset( DateTimeImmutable $now, string $date ) {
		// Get coordinates from the WordPress timezone.
		$coordinates = $this->get_timezone_coordinates( $now->getTimezone() );

		// Allow filter overrides for precise location control.
		$latitude  = apply_filters( 'hebrew_dates_admin_latitude', $coordinates['latitude'] );
		$longitude = apply_filters( 'hebrew_dates_admin_longitude', $coordinates['longitude'] );

		// PHP selects the calculation date in its default timezone (UTC in WordPress).
		// Near the date line, the site's sunset can come from an adjacent calculation date.
		$anchor = new DateTimeImmutable( $date . ' 12:00:00' );
		foreach ( array( $anchor, $anchor->modify( '-1 day' ), $anchor->modify( '+1 day' ) ) as $candidate ) {
			$sunset = date_sun_info( $candidate->getTimestamp(), $latitude, $longitude )['sunset'];
			if ( is_int( $sunset ) && $date === $now->setTimestamp( $sunset )->format( 'Y-m-d' ) ) {
				return $now->getTimestamp() >= $sunset;
			}
		}

		// When there is no sunset on the local date, advance at local midnight.
		return false;
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
