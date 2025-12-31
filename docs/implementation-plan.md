### Hebrew Dates Admin Widget - Implementation Plan

#### Overview

WordPress Admin dashboard widget displaying the current Hebrew date using the Hebcal API.

**Display format:**
- Hebrew characters primary (e.g., "א׳ בְּטֵבֵת תשפ״ה")
- Site-language transliteration below (e.g., "1 Tevet 5785")
- Jewish holidays/events when applicable (e.g., "Chanukah: 7 Candles")

**Data source:** Hebcal REST API with 24-hour transient caching

---

#### File Structure

```
hebrew-dates-wp-admin/
├── hebrew-dates-admin.php           # Main plugin file (bootstrap + widget registration)
├── includes/
│   └── class-hebcal-api.php         # API client with caching logic
├── README.md                        # Required documentation
└── .claude/
    └── process-journal.md           # Development log (exists)
```

---

#### Implementation Steps

##### Step 1: Create Main Plugin File

**File:** `hebrew-dates-admin.php`

**Purpose:**
- Plugin header metadata
- Define constants (version, path)
- Include the Hebcal API class
- Register dashboard widget on `wp_dashboard_setup` hook
- Widget display callback that renders the Hebrew date

**Key code patterns:**
```php
// Security: Prevent direct access
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Hook registration
add_action( 'wp_dashboard_setup', 'hebrew_dates_admin_add_widget' );

// Widget registration using wp_add_dashboard_widget()
// Display callback fetches from Hebcal_API class and renders output
```

##### Step 2: Create Hebcal API Class

**File:** `includes/class-hebcal-api.php`

**Purpose:**
- Build API URL with current date (from WordPress timezone)
- Fetch data using `wp_remote_get()`
- Cache response in transient for 24 hours
- Return structured data (Hebrew string, transliterated string, events array)

**API endpoint:**
```
https://www.hebcal.com/converter?cfg=json&date={YYYY-MM-DD}&g2h=1
```

**Caching strategy:**
- Transient key: `hebrew_date_{Y-m-d}` (date-specific)
- Expiration: 24 hours (`DAY_IN_SECONDS`)
- On cache hit: return cached data immediately
- On cache miss: fetch from API, cache result, return data
- On API error: return cached data if exists, otherwise return error state

**Response parsing:**
```php
// From Hebcal response:
$data['hebrew']      // "א׳ בְּטֵבֵת תשפ״ה" (Hebrew characters)
$data['hd']          // 1 (day number)
$data['hm']          // "Tevet" (month name - transliterated)
$data['hy']          // 5785 (year)
$data['events']      // ["Chanukah: 7 Candles"] (array, may be empty)
```

##### Step 3: Widget Display Logic

**In:** `hebrew_dates_admin_widget_display()` callback

**Output structure:**
```html
<div class="hebrew-date-widget">
  <!-- Primary: Hebrew characters (RTL) -->
  <p class="hebrew-date-hebrew" style="...">א׳ בְּטֵבֵת תשפ״ה</p>

  <!-- Secondary: Transliterated -->
  <p class="hebrew-date-transliterated" style="...">1 Tevet 5785</p>

  <!-- Events (if any) -->
  <p class="hebrew-date-events" style="...">Chanukah: 7 Candles</p>
</div>
```

**Styling (inline):**
- Hebrew text: `font-size: 1.5em; direction: rtl; text-align: center;`
- Transliterated: `font-size: 1.1em; color: #666; text-align: center;`
- Events: `font-size: 0.95em; color: #0073aa; text-align: center; font-style: italic;`

**Error state:**
- If no cached data and API fails: display "Unable to load Hebrew date"

##### Step 4: Timezone Handling

**Approach:**
- Use `wp_date( 'Y-m-d' )` to get current date in site's configured timezone
- This respects Settings → General → Timezone
- Pass this date to Hebcal API

**Why `wp_date()` over `date()`:**
- `wp_date()` is timezone-aware using WordPress settings
- `date()` uses server timezone, which may differ from site setting

---

#### Security Checklist

- [ ] `ABSPATH` check at top of every PHP file
- [ ] All output escaped with `esc_html()`
- [ ] API URL built with `esc_url()`
- [ ] No user input processed
- [ ] Uses WordPress HTTP API (`wp_remote_get()`)

---

#### Hebcal API Details

**Request:**
```
GET https://www.hebcal.com/converter?cfg=json&date=2024-12-31&g2h=1
```

**Response (relevant fields):**
```json
{
  "hy": 5785,
  "hm": "Tevet",
  "hd": 1,
  "hebrew": "א׳ בְּטֵבֵת תשפ״ה",
  "events": ["Rosh Chodesh Tevet"]
}
```

**Rate limit:** 90 requests/10 seconds (24h caching makes this a non-issue)

---

#### Testing Plan

1. **Activation:** `wp plugin activate hebrew-dates-admin` - no errors
2. **Widget visible:** Dashboard shows "Hebrew Date" widget
3. **Correct date:** Verify Hebrew date matches https://www.hebcal.com
4. **Caching works:** Check transient exists after first load
5. **Timezone:** Change WP timezone, verify date updates correctly
6. **Error handling:** Disconnect network, verify graceful degradation

---

#### Files to Create/Modify

| File | Action | Lines (est.) |
|------|--------|--------------|
| `hebrew-dates-admin.php` | Create | ~60 |
| `includes/class-hebcal-api.php` | Create | ~80 |
| `README.md` | Create | ~100 |
| `.claude/process-journal.md` | Update | +20 |

---

#### Post-Implementation

1. Update process journal with implementation notes
2. Complete README.md using template
3. Test in WordPress environment
4. Verify security checklist items
