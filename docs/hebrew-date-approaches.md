### Hebrew Date Data Source Options

Analysis of three approaches for obtaining Hebrew date data in the WordPress Admin widget.

---

### Option A: Hebcal REST API (External)

**Endpoint for this use case:**
```
https://www.hebcal.com/converter?cfg=json&date=2024-12-31&g2h=1
```

**Response includes:**
```json
{
  "hy": 5785,
  "hm": "Tevet",
  "hd": 1,
  "hebrew": "א׳ בְּטֵבֵת תשפ״ה",
  "heDateParts": { "y": "תשפ״ה", "m": "טבת", "d": "א׳" },
  "events": ["Rosh Chodesh Tevet"]
}
```

**Pros:**
- Hebrew characters included (`"hebrew"` field)
- Events/holidays included automatically
- Well-maintained, authoritative source
- Creative Commons licensed

**Cons:**
- External dependency (service could be down)
- Network latency on each dashboard load
- Requires caching (transients) to be responsible
- More code for error handling
- Rate limit: 90 requests/10 seconds

---

### Option B: PHP Built-in Functions (Local)

**Uses:** `jdtojewish()` from PHP's calendar extension

**Code pattern:**
```php
$julian_day = gregoriantojd( $month, $day, $year );
$hebrew_date = jdtojewish( $julian_day );
// Returns: "29/Kislev/5785" format
```

**Pros:**
- Zero external dependencies
- Instant (no network call)
- Simpler code, fewer failure modes
- Aligns with "keep it simple" requirement

**Cons:**
- No Hebrew characters (returns transliterated format only)
- No events/holidays
- Manual month name mapping needed

---

### Option C: Hebcal API Only (No Fallback)

**Same as Option A, but without a fallback mechanism.**

**Implementation approach:**
- Use WordPress HTTP API (`wp_remote_get()`)
- Cache results aggressively (24 hours via transients)
- Display friendly error message if API unavailable and no cache exists

**Pros:**
- Rich features (Hebrew characters, events)
- Simpler than hybrid approach (no fallback code)
- 24-hour cache minimizes API dependency

**Cons:**
- If cache expires and API is down, widget shows error
- Slightly more complex than PHP-only approach

---

### Comparison Summary

| Approach | Complexity | Features | Reliability |
|----------|------------|----------|-------------|
| Hebcal API only | Medium | Rich (Hebrew chars, events) | Dependent on API |
| PHP only | Low | Basic (transliterated names) | High |
| Hebcal + fallback | Medium-High | Rich with safety net | High |

---

### Decision

**Selected: Option C (Hebcal API Only)**

**Rationale:**
- Provides Hebrew character display (primary requirement)
- Includes holiday/event information (value-add)
- 24-hour caching makes API dependency minimal
- Simpler than hybrid approach while still feature-rich
- Aligns with project goal of displaying authentic Hebrew dates
