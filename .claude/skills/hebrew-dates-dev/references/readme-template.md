# README Template

Use this template for the required README.md file.

---

```markdown
# Hebrew Dates Admin Widget

A WordPress plugin that displays the current Hebrew date in the WordPress Admin dashboard.

## Why Hebrew Dates?

[REQUIRED: Explain your choice. Example text below - personalize this:]

I chose to create a Hebrew date widget because:
- The Hebrew calendar is fundamental to Jewish life and observance
- Many WordPress users managing Jewish community sites, synagogues, or personal blogs would benefit from seeing the Hebrew date at a glance
- It's a practical, useful feature that I would personally use
- The implementation presents interesting challenges (calendar conversion) while remaining achievable in scope

## Features

- Displays the current Hebrew date in the WordPress Admin dashboard
- Shows date in format: "Day MonthName Year" (e.g., "15 Tevet 5785")
- Automatically handles leap years and the additional Adar II month
- Respects WordPress timezone settings
- Lightweight with no external dependencies

## Installation

1. Download or clone this repository
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Visit your Dashboard to see the Hebrew Date widget

## Screenshot

[Optional: Add a screenshot of the widget]

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher with the Calendar extension enabled

## AI Tools Used

**Primary Tool**: Claude Code with Opus 4.5 model

### How AI Assisted:
- [List specific ways AI helped, for example:]
- Provided boilerplate for WordPress plugin structure
- Identified PHP's built-in `jdtojewish()` function for calendar conversion
- Suggested security best practices (ABSPATH checks, output escaping)
- Helped debug issues with [specific problem]
- Generated code comments and documentation

## Development Process

### Overview
[Describe your development journey. Example:]

The development followed a structured approach:
1. Started by researching WordPress dashboard widget API
2. Implemented basic plugin structure with proper headers
3. Created a separate class for Hebrew date calculations
4. Added security measures (direct access prevention, output escaping)
5. Tested activation and display in local WordPress environment
6. Documented the process throughout

### What Worked Well
- [Example:] Using PHP's built-in calendar functions eliminated the need for external libraries
- [Example:] Separating the date calculation into its own class made the code clean and testable
- [Example:] Starting with the simplest possible implementation and avoiding feature creep

### What Didn't Work (Struggles & Solutions)

| Challenge | Solution |
|-----------|----------|
| [Example:] Initially tried a complex third-party Hebrew calendar library | Discovered PHP has built-in `jdtojewish()` function - much simpler |
| [Example:] Month names were off by one | Hebrew months are 1-indexed; adjusted array accordingly |
| [Example:] Widget wasn't appearing | Was using wrong hook; changed from `admin_init` to `wp_dashboard_setup` |

### Key Learnings

1. **[Learning 1]**: [Example:] PHP's calendar extension provides robust date conversion functions
2. **[Learning 2]**: [Example:] WordPress dashboard widgets require minimal code - the API is well-designed
3. **[Learning 3]**: [Example:] Starting simple and resisting the urge to add features keeps projects manageable
4. **[Learning 4]**: [Example:] AI tools are most helpful when you give them specific, focused tasks

## File Structure

```
hebrew-dates-wp-admin/
├── hebrew-dates-admin.php     # Main plugin file
├── includes/
│   └── class-hebrew-date.php  # Hebrew date calculation class
├── README.md                  # This file
└── LICENSE                    # GPL-2.0 (optional)
```

## Security

This plugin follows WordPress security best practices:
- All PHP files prevent direct access
- All output is properly escaped
- No user input is processed (minimal attack surface)
- No database queries (uses WordPress functions)

## License

GPL-2.0 or later

## Author

[Your Name]
[Your Website or GitHub profile]

---

*Built as part of [Project/Course Name if applicable]*
```

---

## Customization Notes

### Required Sections (from project requirements):
1. ✅ Which option you chose and **why** - the "Why Hebrew Dates?" section
2. ✅ AI tools and models used - dedicated section with specifics
3. ✅ Development process description - "Development Process" section
4. ✅ Struggles and solutions - table format for clarity

### Personalizing the Template:
1. Replace all `[bracketed text]` with your actual content
2. Draw from your `.claude/process-journal.md` for struggles and learnings
3. Be specific about AI usage - vague statements are less valuable
4. Keep it honest - don't invent struggles you didn't have

### Length Guidelines:
- "Why Hebrew Dates?": 3-5 sentences
- AI Tools Used: List format, 3-6 specific items
- Development Process: 1-2 paragraphs overview, then lists
- Struggles table: 2-5 entries
- Key Learnings: 3-5 bullet points

### Tone:
- Professional but personal
- Honest about challenges
- Specific about tools and techniques
- Concise - this is documentation, not an essay
