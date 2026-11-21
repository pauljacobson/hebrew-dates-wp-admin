<?php
/**
 * Exercise the Hebcal client without a WordPress installation or live HTTP.
 * Run with: php tests/hebcal-api.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
date_default_timezone_set( 'UTC' ); // WordPress sets this during bootstrap.

function apply_filters( $hook, $value ) {
	return $GLOBALS['test_filters'][ $hook ] ?? $value;
}

function get_transient( $key ) {
	return $GLOBALS['test_cache'][ get_current_blog_id() ][ $key ]['value'] ?? false;
}

function set_transient( $key, $value, $expiration ) {
	$GLOBALS['test_cache'][ get_current_blog_id() ][ $key ] = array(
		'value'      => $value,
		'expiration' => $expiration,
	);
	return true;
}

function get_current_blog_id() {
	return $GLOBALS['test_blog_id'];
}

function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args );
}

function esc_url_raw( $url ) {
	return $url;
}

function wp_remote_get( $url, $args ) {
	$GLOBALS['test_requests'][] = array( 'url' => $url, 'args' => $args );
	return $GLOBALS['test_response'];
}

function is_wp_error( $response ) {
	return false;
}

function wp_remote_retrieve_response_code( $response ) {
	return $response['status'];
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'];
}

function __( $text, $domain ) {
	return $text;
}

function sanitize_text_field( $text ) {
	return trim( strip_tags( $text ) );
}

require __DIR__ . '/../plugin/includes/class-hebcal-api.php';

function reset_fixture() {
	// Each case uses a new site ID so request-local results do not leak between cases.
	$GLOBALS['test_blog_id']   = ( $GLOBALS['test_blog_id'] ?? 0 ) + 1;
	$GLOBALS['test_filters']   = array();
	$GLOBALS['test_cache']     = array();
	$GLOBALS['test_requests'] = array();
	$GLOBALS['test_response'] = array(
		'status' => 200,
		'body'   => '{"hebrew":"א׳ בְּטֵבֵת תשפ״ה","hd":1,"hm":"Tevet","hy":5785}',
	);
}

function expect_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			sprintf( '%s. Expected %s, got %s', $message, var_export( $expected, true ), var_export( $actual, true ) )
		);
	}
}

$date_cases = array(
	'Jerusalem before sunset'         => array( 'Asia/Jerusalem', '2026-11-21 16:30:00', false ),
	'Jerusalem after sunset'          => array( 'Asia/Jerusalem', '2026-11-21 16:45:00', true ),
	'Jerusalem before local midnight' => array( 'Asia/Jerusalem', '2026-11-20 23:59:59', true ),
	'Jerusalem at local midnight'     => array( 'Asia/Jerusalem', '2026-11-21 00:00:00', false ),
	'New York after UTC midnight'     => array( 'America/New_York', '2026-11-21 21:30:00', true ),
	'New York before DST begins'      => array( 'America/New_York', '2026-03-07 18:30:00', true ),
	'New York after DST begins'       => array( 'America/New_York', '2026-03-08 18:30:00', false ),
	'Auckland at local noon'           => array( 'Pacific/Auckland', '2026-11-21 12:00:00', false ),
	'Kiritimati after sunset'         => array( 'Pacific/Kiritimati', '2026-11-21 21:30:00', true ),
	'Fixed offset fallback'           => array( '+02:00', '2026-11-21 00:30:00', false ),
	'Polar day without sunset'        => array( 'Arctic/Longyearbyen', '2026-06-21 23:59:00', false ),
	'Polar night without sunset'      => array( 'Arctic/Longyearbyen', '2026-12-21 23:59:00', false ),
);

$failures = 0;

function run_case( $label, callable $test ) {
	reset_fixture();
	try {
		$test();
		echo "PASS: $label\n";
	} catch ( RuntimeException $error ) {
		++$GLOBALS['failures'];
		fwrite( STDERR, "FAIL: $label. {$error->getMessage()}\n" );
	}
}

foreach ( $date_cases as $label => list( $zone, $local, $after_sunset ) ) {
	run_case( $label, function () use ( $zone, $local, $after_sunset ) {
		$now    = new DateTimeImmutable( $local, new DateTimeZone( $zone ) );
		$result = ( new Hebcal_API() )->get_hebrew_date( $now );
		expect_same( true, $result['success'], 'Valid conversion succeeds' );
		expect_same( 1, count( $GLOBALS['test_requests'] ), 'One request for an uncached date' );

		$query = parse_url( $GLOBALS['test_requests'][0]['url'], PHP_URL_QUERY );
		parse_str( $query, $params );
		expect_same( substr( $local, 0, 10 ), $params['date'], 'Request uses the local date' );
		expect_same( $after_sunset ? 'on' : null, $params['gs'] ?? null, 'Request uses the correct sunset flag' );
	} );
}

$noon = new DateTimeImmutable( '2026-11-21 12:00:00', new DateTimeZone( 'Asia/Jerusalem' ) );

run_case( 'Switch cache entries at sunset and local midnight', function () use ( $noon ) {
	$api = new Hebcal_API();
	// The site's calendar date and UTC date agree at noon in Jerusalem.
	$sunset = date_sun_info( $noon->getTimestamp(), 31.7667, 35.2333 )['sunset'];
	$GLOBALS['test_filters'] = array(
		'hebrew_dates_admin_latitude'  => 31.7667,
		'hebrew_dates_admin_longitude' => 35.2333,
	);
	$api->get_hebrew_date( $noon->setTimestamp( $sunset - 1 ) );
	$api->get_hebrew_date( $noon->setTimestamp( $sunset ) );
	$api->get_hebrew_date( $noon->modify( 'tomorrow' ) );

	expect_same( 3, count( $GLOBALS['test_requests'] ), 'Each date or sunset boundary uses a separate cache entry' );
	$queries = array();
	foreach ( $GLOBALS['test_requests'] as $request ) {
		parse_str( parse_url( $request['url'], PHP_URL_QUERY ), $params );
		$queries[] = array( $params['date'], $params['gs'] ?? null );
	}
	expect_same(
		array( array( '2026-11-21', null ), array( '2026-11-21', 'on' ), array( '2026-11-22', null ) ),
		$queries,
		'Hebcal receives the correct date and flag at each boundary'
	);
} );

run_case( 'Reuse a successful response across block instances', function () use ( $noon ) {
	$first = ( new Hebcal_API() )->get_hebrew_date( $noon );
	$second = ( new Hebcal_API() )->get_hebrew_date( $noon );
	expect_same( true, $first['success'], 'Valid conversion succeeds' );
	expect_same( '1 Tevet 5785', $first['transliterated'], 'Conversion includes transliteration' );
	expect_same( $first, $second, 'Both instances receive the same conversion' );
	expect_same( 1, count( $GLOBALS['test_requests'] ), 'Both instances share one upstream request' );

	$archive = array_values( $GLOBALS['test_cache'][ get_current_blog_id() ] );
	expect_same( DAY_IN_SECONDS, $archive[0]['expiration'], 'Successful conversions cache for one day' );
	expect_same( Hebcal_API::MAX_RESPONSE_SIZE + 1, $GLOBALS['test_requests'][0]['args']['limit_response_size'] ?? null, 'Transport stops receiving oversized responses' );
} );

run_case( 'Reuse failures and back off per site and date', function () use ( $noon ) {
	$GLOBALS['test_response']['status'] = 503;
	$first = ( new Hebcal_API() )->get_hebrew_date( $noon );
	$second = ( new Hebcal_API() )->get_hebrew_date( $noon );
	expect_same( false, $first['success'], 'HTTP 503 returns a failure' );
	expect_same( $first, $second, 'Both instances receive the same failure' );
	expect_same( 1, count( $GLOBALS['test_requests'] ), 'Instances do not retry during the same request' );

	$archive = array_values( $GLOBALS['test_cache'][ get_current_blog_id() ] );
	expect_same( 30, $archive[0]['expiration'], 'Failures expire after a short backoff' );

	( new Hebcal_API() )->get_hebrew_date( $noon->modify( '+1 day' ) );
	expect_same( 2, count( $GLOBALS['test_requests'] ), 'The following date has its own cache' );

	++$GLOBALS['test_blog_id'];
	( new Hebcal_API() )->get_hebrew_date( $noon );
	expect_same( 3, count( $GLOBALS['test_requests'] ), 'Another site has its own cache' );
} );

foreach ( array(
	'Missing transliteration fields' => '{"hebrew":"א׳ בְּטֵבֵת תשפ״ה"}',
	'Empty Hebrew date'               => '{"hebrew":" ","hd":1,"hm":"Tevet","hy":5785}',
	'Invalid Hebrew day'              => '{"hebrew":"א׳ בְּטֵבֵת תשפ״ה","hd":31,"hm":"Tevet","hy":5785}',
	'Invalid Hebrew month'            => '{"hebrew":"א׳ בְּטֵבֵת תשפ״ה","hd":1,"hm":[],"hy":5785}',
	'Invalid Hebrew year'             => '{"hebrew":"א׳ בְּטֵבֵת תשפ״ה","hd":1,"hm":"Tevet","hy":0}',
) as $label => $body ) {
	run_case( $label, function () use ( $noon, $body ) {
		$GLOBALS['test_response']['body'] = $body;
		$result = ( new Hebcal_API() )->get_hebrew_date( $noon );
		expect_same( false, $result['success'], 'Invalid responses do not produce a date' );
	} );
}

exit( $failures ? 1 : 0 );
