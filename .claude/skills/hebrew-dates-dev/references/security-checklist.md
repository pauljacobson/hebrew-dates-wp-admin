# Security Checklist

Security verification for the Hebrew Dates Admin plugin.

## Pre-Submission Security Audit

### 1. Direct Access Prevention

**Every PHP file must check for ABSPATH:**

```php
// At the top of every PHP file (after opening <?php tag)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

Files to check:
- [ ] `hebrew-dates-admin.php`
- [ ] `includes/class-hebrew-date.php`
- [ ] Any other PHP files added

### 2. Output Escaping

**All output must be properly escaped:**

| Data Type | Escaping Function |
|-----------|-------------------|
| HTML content | `esc_html()` |
| HTML attributes | `esc_attr()` |
| URLs | `esc_url()` |
| JavaScript | `esc_js()` |
| Translated strings | `esc_html__()`, `esc_attr__()` |

Example:
```php
// CORRECT
echo '<p>' . esc_html( $hebrew_date ) . '</p>';

// INCORRECT - raw output
echo '<p>' . $hebrew_date . '</p>';
```

### 3. Input Handling

For this plugin, we don't accept user input, which is the safest approach. However, if you add settings later:

- [ ] Sanitize all input: `sanitize_text_field()`, `absint()`, etc.
- [ ] Use nonces for form submissions
- [ ] Verify capabilities before processing

### 4. SQL Safety

This plugin doesn't use direct database queries. If you add them later:

- [ ] Always use `$wpdb->prepare()` for queries with variables
- [ ] Never concatenate user input into SQL strings

### 5. File Operations

- [ ] No file includes based on user input
- [ ] No dynamic code execution functions
- [ ] No deprecated regex modifiers that execute code

## Quick Security Scan

Run these checks before submission:

```bash
# Check for missing ABSPATH guards
grep -L "ABSPATH" *.php includes/*.php 2>/dev/null

# Check for raw echo without escaping (may have false positives)
grep -n "echo \$" *.php includes/*.php 2>/dev/null
```

## Security Best Practices Applied

This plugin follows security best practices by:

1. **Minimal attack surface**: No user input, no settings pages, no database queries
2. **Pure display**: Widget only outputs data, doesn't process anything
3. **WordPress functions**: Uses WordPress timezone (`current_time()`) and escaping functions
4. **No external calls**: All calculations happen locally using PHP's built-in functions
5. **Direct access blocked**: Every PHP file has ABSPATH check

## What This Plugin Doesn't Need

Because of its simplicity, this plugin doesn't require:

- **Nonces**: No forms or AJAX
- **Capability checks**: Only displays in admin (inherently restricted)
- **Sanitization**: No user input
- **Prepared statements**: No database queries

This minimal surface area is itself a security feature.

## If Adding Features Later

If you expand the plugin to include settings:

```php
// Settings page registration
add_action( 'admin_init', 'hebrew_dates_register_settings' );

function hebrew_dates_register_settings() {
    register_setting(
        'hebrew_dates_options',
        'hebrew_dates_display_format',
        array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'english',
        )
    );
}

// Form with nonce
wp_nonce_field( 'hebrew_dates_save_settings', 'hebrew_dates_nonce' );

// Verification
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized' );
}

if ( ! wp_verify_nonce( $_POST['hebrew_dates_nonce'], 'hebrew_dates_save_settings' ) ) {
    wp_die( 'Invalid nonce' );
}
```
