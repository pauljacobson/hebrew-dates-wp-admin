# Hebrew Dates Plugin - Development Journal

## Project Started: 2024-12-31

### Why Hebrew Dates?

The Hebrew calendar is central to Jewish life and practice. Many WordPress users managing Jewish community sites, synagogues, schools, or personal blogs would benefit from seeing the Hebrew date at a glance when they log into their dashboard.

The Hebrew calendar is lunisolar and doesn't align with the Gregorian calendar, making it genuinely useful to have this information readily visible. It's a practical feature that serves a real need while being simple enough to execute well within the project scope.

### Getting Started

My starting point was to create an empty repo at https://github.com/pauljacobson/hebrew-dates-wp-admin that I cloned to my laptop. I then ran `claude` and `/init` to initialize the Claude environment.

A set of [Agent Skills for WordPress](https://aip2.wordpress.com/2025/12/29/agent-skills-for-wordpress/) was released just a couple days ago so I thought I'd take advantage of these skills to help me build the plugin based on best practices from our developers. I copied the skills into my repo's `.claude/skills` directory. I then shared the "Option 1: Dashboard Widget" description and both the "Your Plugin Must" and "Your Repo Must Include" requirements from the [AI Coders Program: Application Project P2 post](https://fusionp2.wordpress.com/2025/12/17/ai-coders-program-application-project/) and asked Claude to help create a project-specific skill that will help guide my plugin development in such a way that it aligns with the WordPress agent skills and the project specifications.

## Development Log

### 2024-12-31: Project Setup & Skill Creation

#### What I did
- Initialized Git repository for the project
- Created project-specific CLAUDE.md with development guidance
- Created custom Claude Code skill (`hebrew-dates-dev`) to guide development
- Set up process journal for documentation tracking

#### What worked
- Using Claude Code to create a structured skill that will guide the entire development process
- Having reference files for workflow, security, and README template

#### What didn't work
- This part of the process went smoothly and I didn't encounter any noticeable issues.

#### What I learned
- Claude Code skills can be project-specific and help maintain consistency
- Documenting as you go is easier than trying to remember everything at the end

#### AI Assistance
- Used Claude Code (Opus 4.5) to:
  - Create the custom development skill with comprehensive documentation
  - Generate reference materials for workflow, security, and README template
  - Set up the process journal structure

### 2024-12-31: Planning & API Research

#### What I did
- Researched Hebcal Developer APIs at hebcal.com/home/developer-apis
- Evaluated three approaches for Hebrew date data:
  - Option A: Hebcal REST API (external, feature-rich)
  - Option B: PHP built-in `jdtojewish()` (local, simple)
  - Option C: Hebcal API only with aggressive caching (chosen)
- Created detailed implementation plan
- Set up feature branch workflow (`feature/hebrew-date-widget`)
- Documented approach options in `docs/hebrew-date-approaches.md`

#### What worked
- Hebcal API provides exactly what we need: Hebrew characters, transliteration, and events
- The API is well-documented with clear JSON response format
- 24-hour caching strategy addresses rate limits and reliability concerns

#### What didn't work
- Initially considered a hybrid approach with PHP fallback, but decided against it to keep code simpler

#### What I learned
- Hebcal API returns pre-formatted Hebrew date strings (`hebrew` field)
- API includes events/holidays automatically - nice bonus feature
- WordPress transients are ideal for daily-refresh data like dates

#### AI Assistance
- Claude Code analyzed the Hebcal API documentation
- Helped evaluate trade-offs between three implementation approaches
- Created implementation plan with caching strategy

### 2024-12-31: Core Plugin Implementation

#### What I did
- Created main plugin file (`hebrew-dates-admin.php`) with:
  - Proper plugin header metadata
  - ABSPATH security check
  - Version and path constants
  - Dashboard widget registration via `wp_dashboard_setup` hook
  - Widget display callback with escaped output
- Created Hebcal API class (`includes/class-hebcal-api.php`) with:
  - WordPress HTTP API (`wp_remote_get`) for API calls
  - 24-hour transient caching using date-specific keys
  - Structured response parsing (Hebrew chars, transliteration, events)
  - Error handling for network failures and invalid responses
- Organized project with `includes/` directory for class files

#### What worked
- Separating API logic into its own class keeps main plugin file clean
- Using WordPress transients provides reliable caching without external dependencies
- The Hebcal API response includes everything we need in one call
- Inline styles keep the widget self-contained (no separate CSS file needed)

#### What didn't work
- I noticed that Claude Code doesn't automatically use versioning for new plugin builds (as in the plugin version numbers). I haven't added this functionality yet, but it's something to consider for future iterations.

#### What I learned
- `wp_date()` is preferred over `date()` for timezone-aware WordPress development
- WordPress transients handle expiration automatically - no cleanup needed
- `add_query_arg()` is the WordPress way to build URLs with parameters safely
- `esc_url_raw()` is used for URLs in HTTP requests (vs `esc_url()` for display)

#### AI Assistance
- Claude Code generated both plugin files with detailed inline comments
- Followed WordPress coding standards (tabs, PHPDoc blocks, escaping patterns)
- Implemented security measures (ABSPATH checks, output escaping)
- Used WordPress HTTP API correctly (`wp_remote_get`, `wp_remote_retrieve_body`)

### 2024-12-31: Repository Restructuring

#### What I did
- Restructured repository to separate distributable plugin from development files
- Created `plugin/` directory containing only the files needed for WordPress installation
- Kept docs/, .claude/, README.md, CLAUDE.md at repository root
- Added packaging instructions to README

#### What worked
- Clean separation makes it obvious what to zip for distribution
- Simple one-line command to create distributable zip file
- No build tools or complex packaging scripts needed

#### What didn't work
- N/A

#### What I learned
- Repository structure should consider both development workflow and distribution needs
- Keeping plugin files in a subdirectory simplifies packaging without needing .distignore

#### AI Assistance
- Claude Code presented three options for repo structure with pros/cons
- Recommended subdirectory approach as simplest for this project

### 2024-12-31: Visual Enhancements

#### What I did
- Increased Hebrew date font size from 1.5em to 2em for better visibility
- Added Hebrew calendar icon ("לוח עברי") to the widget
- Created SVG version of icon for crisp rendering at any size
- Updated layout to use flexbox with `justify-content: space-around`
- Positioned icon to the right of date text, with text centered in its own container
- Added `assets/` directory to plugin for storing images

#### What worked
- SVG icon renders sharply at any display density (no fuzzy edges)
- Flexbox `space-around` provides balanced visual layout
- `plugin_dir_url()` correctly resolves asset paths in WordPress

#### What didn't work
- Initial PNG icon appeared fuzzy on high-DPI displays
- Converted to SVG to resolve the clarity issue
- I noticed that using AI to modify the UX of the plugin can be a bit tricky and requires fairly careful prompting to get the desired results.

#### What I learned
- SVG is preferred for icons in WordPress plugins - scales cleanly
- `plugin_dir_url(__FILE__)` gives the URL path to plugin assets
- Flexbox layout works well for dashboard widget styling

#### AI Assistance
- Claude Code created SVG icon matching the original PNG design
- Updated widget layout code with proper flexbox structure
- Maintained proper escaping (`esc_url()`) for asset URLs

### 2025-01-05: Timezone-Based Sunset Calculation

#### What I did
- Implemented automatic location detection from WordPress timezone setting
- Replaced hardcoded Jerusalem coordinates with dynamic lookup using PHP's `DateTimeZone::getLocation()`
- Updated class documentation to reflect new behavior
- Renamed constants from `DEFAULT_*` to `FALLBACK_*` to better reflect their purpose

#### What worked
- PHP's `DateTimeZone::getLocation()` provides latitude/longitude for all IANA timezones
- This automatically covers 400+ timezone locations without maintaining a hardcoded mapping
- DST is handled automatically by PHP's DateTime functions
- Filter hooks still work for users who need precise coordinate control

#### What didn't work
- Initially considered creating a large hardcoded timezone-to-coordinates mapping array
- Realized PHP already has this data built into the `DateTimeZone` class

#### What I learned
- `DateTimeZone::getLocation()` returns geographic data (latitude, longitude, country_code) for named timezones
- UTC offset timezones (e.g., "UTC+2") have no geographic location and return false
- The IANA timezone database that PHP uses is the same one WordPress uses for its timezone dropdown

#### AI Assistance
- Claude Code analyzed the existing sunset calculation code
- Identified the mismatch between timezone (New York) and coordinates (Jerusalem)
- Suggested using `DateTimeZone::getLocation()` instead of a hardcoded mapping
- Implemented the solution with proper fallback handling and documentation

## Summary of Struggles and Solutions

| Struggle | How I Solved It |
|----------|-----------------|
| Choosing between Hebcal API, PHP built-in, or hybrid approach | Analyzed trade-offs; chose Hebcal API with aggressive caching for feature richness and simplicity |
| Deciding on caching strategy (object cache vs transients) | Selected transients - works out-of-the-box without external dependencies |
| Repository structure for development vs distribution | Created plugin/ subdirectory for clean separation; zip that folder to distribute |
| PNG icon appeared fuzzy on high-DPI displays | Converted to SVG for resolution-independent rendering |
| Sunset calculated for Jerusalem regardless of site timezone | Used `DateTimeZone::getLocation()` to derive coordinates from WordPress timezone automatically |

## Key Learnings

1. `wp_date()` respects WordPress timezone settings; `date()` uses server timezone
2. WordPress transients handle expiration automatically - no cleanup needed
3. `wp_remote_get()` is the correct way to make HTTP requests in plugins
4. Hebcal API returns pre-formatted Hebrew strings, simplifying display logic
5. Separating distributable files into a subdirectory simplifies packaging
6. SVG icons are preferred over PNG for WordPress plugins - they scale cleanly on all displays
7. `plugin_dir_url(__FILE__)` is the correct way to reference plugin assets in WordPress
8. `DateTimeZone::getLocation()` provides geographic coordinates for IANA timezones - no need for hardcoded mappings
9. PHP's DateTime functions handle DST automatically when using proper timezone objects

## AI Tools Used

- **Primary**: Claude Code with Opus 4.5 model (claude-opus-4-5-20251101)
- **Tasks AI helped with**:
  - Created project-specific development skill with references
  - Analyzed Hebcal API documentation
  - Evaluated implementation approaches with trade-off analysis
  - Generated plugin code with inline documentation
  - Applied WordPress security best practices
  - Suggested repository structure options
  - Created SVG icon from PNG reference
  - Implemented flexbox layout for widget styling
  - Maintained process documentation throughout development
