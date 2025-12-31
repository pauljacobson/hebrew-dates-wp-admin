# Development Workflow

Step-by-step guide for implementing the Hebrew Dates WordPress Admin widget.

## Step 1: Create Main Plugin File

Create `hebrew-dates-admin.php` in the repository root:

```php
<?php
/**
 * Plugin Name: Hebrew Dates Admin
 * Plugin URI: https://github.com/YOUR_USERNAME/hebrew-dates-wp-admin
 * Description: Displays the current Hebrew date in a WordPress Admin dashboard widget.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-site.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: hebrew-dates-admin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'HEBREW_DATES_ADMIN_VERSION', '1.0.0' );
define( 'HEBREW_DATES_ADMIN_PATH', plugin_dir_path( __FILE__ ) );

// Include the Hebrew date class
require_once HEBREW_DATES_ADMIN_PATH . 'includes/class-hebrew-date.php';

// Hook into dashboard setup
add_action( 'wp_dashboard_setup', 'hebrew_dates_admin_add_widget' );

/**
 * Register the dashboard widget.
 */
function hebrew_dates_admin_add_widget() {
    wp_add_dashboard_widget(
        'hebrew_dates_widget',           // Widget ID
        __( 'Hebrew Date', 'hebrew-dates-admin' ), // Widget title
        'hebrew_dates_admin_widget_display'        // Callback function
    );
}

/**
 * Display the widget content.
 */
function hebrew_dates_admin_widget_display() {
    $hebrew_date = new Hebrew_Date();
    $formatted_date = $hebrew_date->get_formatted_date();

    echo '<div class="hebrew-date-widget">';
    echo '<p style="font-size: 1.4em; text-align: center; margin: 1em 0;">';
    echo esc_html( $formatted_date );
    echo '</p>';
    echo '</div>';
}
```

## Step 2: Create Hebrew Date Class

Create `includes/class-hebrew-date.php`:

```php
<?php
/**
 * Hebrew Date calculation class.
 *
 * @package Hebrew_Dates_Admin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Hebrew_Date
 *
 * Handles conversion from Gregorian to Hebrew calendar dates.
 */
class Hebrew_Date {

    /**
     * Hebrew month names.
     *
     * @var array
     */
    private $hebrew_months = array(
        1  => 'Tishrei',
        2  => 'Cheshvan',
        3  => 'Kislev',
        4  => 'Tevet',
        5  => 'Shevat',
        6  => 'Adar',
        7  => 'Nisan',
        8  => 'Iyar',
        9  => 'Sivan',
        10 => 'Tammuz',
        11 => 'Av',
        12 => 'Elul',
        13 => 'Adar II', // Leap year month
    );

    /**
     * Get the current Hebrew date formatted as a string.
     *
     * @return string The formatted Hebrew date.
     */
    public function get_formatted_date() {
        // Get current timestamp respecting WordPress timezone
        $current_time = current_time( 'timestamp' );

        // Convert to Julian Day Count
        $julian_day = gregoriantojd(
            (int) date( 'n', $current_time ),  // month
            (int) date( 'j', $current_time ),  // day
            (int) date( 'Y', $current_time )   // year
        );

        // Convert to Jewish/Hebrew calendar
        $hebrew_date = jdtojewish( $julian_day );

        // Parse the returned date (format: month/day/year)
        list( $month, $day, $year ) = explode( '/', $hebrew_date );

        // Get Hebrew month name
        $month_name = $this->get_month_name( (int) $month );

        // Format: "15 Tevet 5785"
        return sprintf( '%d %s %d', (int) $day, $month_name, (int) $year );
    }

    /**
     * Get the Hebrew month name.
     *
     * @param int $month The month number (1-13).
     * @return string The month name.
     */
    private function get_month_name( $month ) {
        return isset( $this->hebrew_months[ $month ] )
            ? $this->hebrew_months[ $month ]
            : 'Unknown';
    }
}
```

## Step 3: Create Includes Directory

```bash
mkdir -p includes
```

## Step 4: Verify File Structure

Your plugin should have this structure:
```
hebrew-dates-wp-admin/
├── hebrew-dates-admin.php
├── includes/
│   └── class-hebrew-date.php
├── README.md
└── .claude/
    ├── skills/
    └── process-journal.md
```

## Step 5: Test Activation

1. Copy plugin to WordPress plugins directory or use wp-env
2. Activate via WP Admin or WP-CLI:
   ```bash
   wp plugin activate hebrew-dates-admin
   ```
3. Visit Dashboard and verify widget appears

## Common Modifications

### Add Hebrew Characters

To display Hebrew month names in Hebrew characters:

```php
private $hebrew_months = array(
    1  => 'תשרי',
    2  => 'חשוון',
    3  => 'כסלו',
    4  => 'טבת',
    5  => 'שבט',
    6  => 'אדר',
    7  => 'ניסן',
    8  => 'אייר',
    9  => 'סיוון',
    10 => 'תמוז',
    11 => 'אב',
    12 => 'אלול',
    13 => 'אדר ב׳',
);
```

### Add Styling

For better visual presentation, add inline styles or enqueue a stylesheet:

```php
echo '<p style="font-size: 1.4em; text-align: center; direction: rtl; font-family: \'David\', \'Times New Roman\', serif;">';
```

### Handle Leap Years

The class already handles Adar II (month 13) for leap years. The `jdtojewish()` function automatically returns the correct month number.
