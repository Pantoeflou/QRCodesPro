---
inclusion: auto
---

# Pro Add-On Development Guide

This steering file is ALWAYS active when working in the pro add-on repository.

## Architecture

The pro add-on is a LIGHTWEIGHT extension of the free plugin. It does NOT duplicate free plugin infrastructure.

### What the Pro Add-On Owns
- License key management (validation, activation, deactivation)
- Self-hosted update delivery
- Pro feature modules
- Pro-specific views (license tab UI)

### What the Pro Add-On Does NOT Own
- Settings page (hooks into the free plugin's)
- Database tables (uses the free plugin's DB class via hooks)
- Template loader (registers templates through the free plugin's system)
- Admin UI framework (extends, doesn't duplicate)
- Public asset loading (hooks into the free plugin's enqueue)

## How Pro Hooks Into Free

The free plugin provides extension points. Pro attaches to them:

```php
// Free plugin provides:
do_action( 'wp_forever/settings_tabs', $tabs );
do_action( 'wp_forever/settings_tab_content_{tab}' );
do_action( 'wp_forever/after_output', $data );
apply_filters( 'wp_forever/feature_list', $features );
apply_filters( 'wp_forever/feature_access', $allowed, $feature );

// Pro hooks in:
add_filter( 'wp_forever/settings_tabs', array( $this, 'add_license_tab' ) );
add_action( 'wp_forever/settings_tab_content_license', array( $this, 'render_license_tab' ) );
add_filter( 'wp_forever/feature_list', array( $this, 'add_pro_features' ) );
```

## Adding a New Pro Module

1. Create `modules/class-{feature-name}.php`
2. Follow the pattern in `modules/class-example-module.php`
3. Add `require_once` in `includes/class-pro-loader.php::load_modules()`
4. Add initialization in `includes/class-pro-loader.php::init_modules()`
5. The module hooks into the free plugin's extension points

## Module Pattern

Every pro module follows this structure:

```php
class WP_Forever_Pro_{Feature_Name} {
    public static function init(): void {
        // Hook into free plugin extension points
        add_filter( 'wp_forever/{hook}', array( __CLASS__, 'method' ) );
        add_action( 'wp_forever/{hook}', array( __CLASS__, 'method' ) );
    }
    // ... feature methods
}
```

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Classes | `WP_Forever_Pro_{Name}` | `WP_Forever_Pro_Analytics` |
| Functions | `wp_forever_pro_{name}` | `wp_forever_pro_get_stats` |
| Constants | `WP_FOREVER_PRO_{NAME}` | `WP_FOREVER_PRO_VERSION` |
| Hooks | `wp_forever_pro/{name}` | `wp_forever_pro/loaded` |
| Options | `wp_forever_pro_{name}` | `wp_forever_pro_license_key` |
| Text domain | `wp-forever-pro` | All `__()` calls |

## Dependency Management

The pro add-on MUST check that:
1. The free plugin is installed and active
2. The free plugin meets the minimum version requirement

If either check fails, show an admin notice and bail. Never fatal.

```php
if ( ! defined( 'WP_FOREVER_VERSION' ) ) {
    // Free plugin not active — show notice, return
}
if ( version_compare( WP_FOREVER_VERSION, WP_FOREVER_PRO_MIN_FREE_VERSION, '<' ) ) {
    // Free plugin too old — show notice, return
}
```

## License Behavior

- License check happens once per day (cached in transient)
- On network error, assume valid (don't punish user for server issues)
- If license expires, pro features disable gracefully (no fatal, no data loss)
- License tab always shows (even when expired) so user can renew
- Updater only delivers packages when license is valid

## File Structure

```
wp-forever-pro/
├── wp-forever-pro.php          # Entry point + dependency check
├── uninstall.php               # Cleanup pro data on deletion
├── includes/
│   ├── class-license-manager.php   # License validation
│   ├── class-updater.php           # Self-hosted updates
│   └── class-pro-loader.php        # Module bootstrapper
├── modules/                    # Pro feature modules
│   ├── class-example-module.php
│   └── index.php
├── views/                      # Pro admin views
│   └── license-tab.php
└── languages/                  # Translations
```
