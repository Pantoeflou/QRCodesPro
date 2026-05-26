# QR Codes - Made Simple Pro — Add-On Template

This is the template for pro add-on plugins that extend a free plugin built from WP-Framework.

## Overview

The pro add-on:
- Requires the free plugin to be installed and active
- Hooks into the free plugin's extension points to add features
- Manages its own license key validation and self-hosted updates
- Is sold on your website (not distributed on wp.org)

## Quick Start

1. Copy this template → `my-plugin-pro/`
2. Rename everything (see Checklist below)
3. Build your pro modules in `modules/`
4. Set up your license server endpoint
5. Set up your update server endpoint

## Checklist

### Find and Replace

| Find | Replace With |
|------|--------------|
| `qrc-ms-pro` | `my-plugin-pro` (text domain, slug) |
| `QRC_MS_PRO_` | `MY_PLUGIN_PRO_` (constants) |
| `qrc_ms_pro_` | `my_plugin_pro_` (functions, hooks, options) |
| `QRC_MS_Pro_` | `My_Plugin_Pro_` (classes) |
| `qrc_ms_pro/` | `my_plugin_pro/` (hook namespace) |
| `QR Codes - Made Simple Pro` | `My Plugin Pro` (display name) |

### Also Update

- [ ] Plugin header in main file (name, description, author, URIs)
- [ ] `QRC_MS_PRO_MIN_FREE_VERSION` — set to your free plugin's current version
- [ ] `API_URL` in license manager — your actual license server
- [ ] `UPDATE_URL` in updater — your actual update server
- [ ] Dependency check references (free plugin constant name)

## How It Works

```
WordPress loads plugins
  → Free plugin loads first (priority 10 on plugins_loaded)
  → Pro add-on loads second (priority 20 on plugins_loaded)
  → Pro checks: is free plugin active? correct version?
  → If yes: load license manager, updater, and pro modules
  → If no: show admin notice, bail gracefully
```

## Adding Pro Features

1. Create a module class in `modules/`
2. Hook into the free plugin's `do_action()` / `apply_filters()` calls
3. Register the module in `includes/class-pro-loader.php`

See `modules/class-example-module.php` for the pattern.

## Server Requirements

You need two server endpoints:

### License Server
- `POST /license/v1/activate` — Activate a key for a site
- `POST /license/v1/deactivate` — Deactivate a key
- `POST /license/v1/validate` — Check if a key is valid

### Update Server
- `GET /updates/v1/check` — Return latest version info + download URL

These can be built with:
- WooCommerce + WooCommerce Software Add-on
- Easy Digital Downloads + Software Licensing
- LemonSqueezy
- Custom WordPress REST endpoints on your site
