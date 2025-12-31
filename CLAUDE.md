# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin for displaying Hebrew dates in the WordPress admin interface.

## Development Commands

```bash
# WordPress plugin development typically uses:
# - Local WordPress installation or wp-env for testing
# - wp-cli for WordPress operations

# If using wp-env:
npx @wordpress/env start
npx @wordpress/env stop

# If using Composer for dependencies:
composer install
composer update
```

## Architecture

*To be documented as the plugin is developed.*

## WordPress Plugin Structure

Standard WordPress plugin conventions apply:
- Main plugin file with plugin header
- `/includes/` - PHP classes and functions
- `/admin/` - Admin-specific functionality
- `/assets/` - CSS, JS, and images

## Testing

*To be documented once testing infrastructure is set up.*
