# QR Codes - Made Simple Pro

Pro add-on for [QR Codes - Made Simple](../QRCodes/). Unlocks advanced features for businesses that print QR codes and need to track their performance.

## Requirements

- QR Codes - Made Simple (free plugin) v1.0.0+
- WordPress 6.0+
- PHP 8.0+

## Installation

1. Install and activate the free plugin first
2. Upload `qrc-ms-pro.zip` via Plugins → Add New → Upload
3. Activate the plugin
4. Go to QR Codes → Settings → License and enter your key

## Pro Features

| Module | Description |
|--------|-------------|
| **Dashboard** | Landing page with stats, recent scans, top performers |
| **Dynamic QR** | Change destinations after printing, redirect management, expiry |
| **Analytics** | Scan tracking with device breakdown, time charts, campaign filtering |
| **Campaigns** | Group QR codes, aggregated stats, links to filtered analytics |
| **Bulk Generate** | CSV upload, WooCommerce batch, ZIP download |
| **Branding** | Center logos, gradients, eye styles, frames with text labels |
| **Automation** | Auto-create QR on publish, auto-update on URL change |
| **Elementor** | Native Elementor widgets |
| **Team** | Role-based permissions, audit logging |
| **Export** | CSV export, printable QR sheets |

## Development

See `docs/PRO-FEATURES.md` for detailed module documentation.

### Adding a Module

1. Create `modules/class-{name}.php`
2. Register in `includes/class-pro-loader.php`
3. Hook into free plugin via `qrc_ms/*` filters/actions
4. Register feature via `qrc_ms/feature_list` filter

### Testing

```bash
# In WP-TestEnv:
Copy-Item -Recurse "path/to/QRCodesPro" "plugins/QRCodesPro"
podman exec wp-test-cli wp plugin activate QRCodesPro --allow-root

# Simulate license:
podman exec wp-test-cli wp option update qrc_ms_pro_license_key test-key-123 --allow-root
podman exec wp-test-cli wp option update _transient_qrc_ms_pro_license_status valid --allow-root
```

## File Structure

```
qrc-ms-pro/
├── qrc-ms-pro.php              # Entry point + dependency check
├── uninstall.php               # Cleanup
├── includes/
│   ├── class-license-manager.php
│   ├── class-updater.php
│   ├── class-pro-loader.php
│   ├── class-redirect-handler.php  # Always active (printed codes must work)
│   └── class-analytics-db.php
├── modules/
│   ├── class-dashboard.php
│   ├── class-dynamic-qr.php
│   ├── class-analytics.php
│   ├── class-campaigns.php
│   ├── class-bulk-generator.php
│   ├── class-branding.php
│   ├── class-automation.php
│   ├── class-elementor.php
│   ├── class-team.php
│   ├── class-export.php
│   ├── elementor/              # Elementor widget classes
│   └── views/                  # Admin page templates
├── views/
│   └── license-tab.php
├── assets/
│   ├── css/
│   └── js/
└── languages/
```
