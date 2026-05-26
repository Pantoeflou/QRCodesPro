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
- Pro feature modules (Dynamic QR, Analytics, Campaigns, Bulk Gen, Branding, Automation, Elementor, Team, Export, Dashboard)
- Pro-specific views (license tab UI, module admin pages)
- Redirect handler (always active, even without license — printed QR codes must never break)

### What the Pro Add-On Does NOT Own
- Settings page (hooks into the free plugin's via `qrc_ms/settings_tabs` filter)
- Core QR generation engine (uses free plugin's `QRC_MS_QR_Generator`)
- Template system (extends free plugin's templates with branding options)
- Admin UI framework (extends, doesn't duplicate)
- Public asset loading (hooks into the free plugin's enqueue)

## How Pro Hooks Into Free

```php
// Free plugin provides:
apply_filters( 'qrc_ms/settings_tabs', $tabs );
do_action( 'qrc_ms/settings_tab_content_license' );
apply_filters( 'qrc_ms/feature_list', $features );
apply_filters( 'qrc_ms/has_pro_access', false );
apply_filters( 'qrc_ms/render_options', $options, $data );
apply_filters( 'qrc_ms/qr_code_types', $types );
apply_filters( 'qrc_ms/generate_svg', '', $data, $size );

// Pro hooks in:
add_filter( 'qrc_ms/settings_tabs', 'add_license_tab' );
add_action( 'qrc_ms/settings_tab_content_license', 'render_license_tab' );
add_filter( 'qrc_ms/has_pro_access', '__return_true' ); // When licensed
add_filter( 'qrc_ms/feature_list', 'add_pro_features' );
add_filter( 'qrc_ms/render_options', 'inject_branding_options', 10, 2 );
add_filter( 'qrc_ms/qr_code_types', 'add_dynamic_type' );
```

## Adding a New Pro Module

1. Create `modules/class-{feature-name}.php`
2. Add `require_once` in `includes/class-pro-loader.php::load_modules()`
3. Add initialization in `includes/class-pro-loader.php::init_modules()`
4. The module hooks into the free plugin's extension points
5. Register the feature via `qrc_ms/feature_list` filter
6. Add admin page via `add_submenu_page()` under `edit.php?post_type=qrc_ms_code`

## Module Pattern

```php
class QRC_MS_Pro_{Feature_Name} {
    public static function init(): void {
        add_filter( 'qrc_ms/feature_list', array( __CLASS__, 'register_feature' ) );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 20 );
        // ... feature-specific hooks
    }

    public static function register_feature( array $features ): array {
        $features[] = array(
            'name'        => __( 'Feature Name', 'qrc-ms-pro' ),
            'description' => __( 'What it does.', 'qrc-ms-pro' ),
            'pro'         => true,
        );
        return $features;
    }
}
```

## Naming Conventions

| Type | Pattern | Example |
|------|---------|---------|
| Classes | `QRC_MS_Pro_{Name}` | `QRC_MS_Pro_Analytics` |
| Constants | `QRC_MS_PRO_{NAME}` | `QRC_MS_PRO_VERSION` |
| Hooks | `qrc_ms_pro/{name}` | `qrc_ms_pro/loaded` |
| Options | `qrc_ms_pro_{name}` | `qrc_ms_pro_license_key` |
| Text domain | `qrc-ms-pro` | All `__()` calls |
| Meta keys | `_qrc_ms_pro_{name}` | `_qrc_ms_pro_branding` |

## Current Pro Modules

| Module | Class | Admin Page |
|--------|-------|-----------|
| Dashboard | `QRC_MS_Pro_Dashboard` | qrc-ms-pro-dashboard |
| Dynamic QR | `QRC_MS_Pro_Dynamic_QR` | (meta box) |
| Analytics | `QRC_MS_Pro_Analytics` | qrc-ms-pro-analytics |
| Campaigns | `QRC_MS_Pro_Campaigns` | qrc-ms-pro-campaigns |
| Bulk Generator | `QRC_MS_Pro_Bulk_Generator` | qrc-ms-pro-bulk-generate |
| Branding | `QRC_MS_Pro_Branding` | (meta box) |
| Automation | `QRC_MS_Pro_Automation` | qrc-ms-pro-automation |
| Elementor | `QRC_MS_Pro_Elementor` | (widgets) |
| Team | `QRC_MS_Pro_Team` | qrc-ms-pro-team |
| Export | `QRC_MS_Pro_Export` | qrc-ms-pro-print-sheet (hidden) |

## Plugin Load Order

```
plugins_loaded (priority 10): Free plugin initializes (qrc_ms_init)
plugins_loaded (priority 20): Pro add-on initializes (qrc_ms_pro_init)
  → Checks free plugin active + correct version
  → Loads license manager, updater, pro loader
  → ALWAYS loads redirect handler (printed codes must never break)
  → If licensed: loads all pro modules, hooks into free plugin
  → If not licensed: only license tab shows
```

## Key Principles

- Free plugin NEVER references pro classes directly — only provides hooks
- Pro plugin ALWAYS depends on free plugin — fails gracefully without it
- Redirect handler runs regardless of license (printed QR codes must work)
- Templates include branding options — branding meta box is the per-code override
- Analytics only tracks dynamic QR codes (static codes bypass the server)
- Campaigns are separate from free Categories (campaigns = analytics grouping)
