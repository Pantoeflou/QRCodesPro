# Pro Features

Complete documentation for QR Codes - Made Simple Pro.

---

## Installation & Licensing

### Requirements

- QR Codes - Made Simple (free plugin) version 1.0.0 or higher, installed and active
- WordPress 6.0+
- PHP 8.0+

### Installation

1. Purchase a license at [example.com/pricing](https://example.com/pricing)
2. Download the `qrc-ms-pro.zip` file from your account
3. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
4. Upload the ZIP file and click **Install Now**
5. Activate the plugin

If the free plugin is not active, you'll see an admin notice prompting you to install it.

### License Activation

1. Go to **QR Codes → Settings → License**
2. Enter your license key
3. Click **Activate**

The license validates against the update server and unlocks all pro features. Your license key is tied to a single site (or multiple sites depending on your plan).

### License Status

| Status | Meaning |
|--------|---------|
| Active | License is valid, all features unlocked |
| Expired | License expired, features still work but no updates |
| Invalid | Key not recognized or deactivated |
| Not Activated | Pro plugin installed but no key entered |

### Automatic Updates

With an active license, the pro plugin receives automatic updates through the WordPress update system. Updates are delivered from the self-hosted update server — no WordPress.org account needed.

### What Happens When a License Expires

- All pro features continue to work (no feature lockout)
- Dynamic QR code redirects continue to function (printed codes never break)
- You stop receiving plugin updates and new features
- Renew your license to resume updates

---

## Dynamic QR Codes

Dynamic QR codes encode a redirect URL instead of the final destination. This means you can change where the QR code points after it's been printed.

### How They Work

```
Printed QR Code → your-site.com/qr/abc123 → Final Destination URL
                  (redirect URL you control)   (changeable at any time)
```

1. When you enable dynamic mode on a QR code, the plugin generates a unique short code (e.g., `abc123`)
2. The QR code encodes `your-site.com/qr/abc123` instead of the final URL
3. When someone scans the code, they hit your site's redirect handler
4. The handler looks up the current destination and performs a 302 redirect
5. You can change the destination at any time without reprinting

### Enabling Dynamic Mode

1. Edit any QR code
2. In the **Dynamic QR Code** meta box, check "Enable Dynamic Mode"
3. A unique short code is generated automatically
4. Enter the destination URL
5. Save the QR code

The QR code preview updates to show the redirect URL instead of the direct destination.

### Changing the Destination

1. Edit the QR code
2. Update the destination URL in the Dynamic QR Code panel
3. Save — the change takes effect immediately

You can also update the destination via AJAX without reloading the page (click "Update Destination").

### Redirect History

Every destination change is logged with:
- Previous URL
- New URL
- User who made the change
- Timestamp

View the history in the Dynamic QR Code meta box to audit changes.

### Redirect Behavior

- Redirects use HTTP 302 (temporary redirect) to avoid browser caching
- The redirect handler loads before WordPress fully initializes for speed
- If the destination URL is empty or invalid, the redirect fails gracefully (404)
- Redirects work even if the pro license expires (printed codes never break)

### Dynamic Mode via AJAX

You can toggle dynamic mode and update destinations without saving the post:

- **Toggle dynamic mode** — Instantly enables/disables with confirmation
- **Update destination** — Changes the URL immediately with success feedback

---

## Scan Analytics

Track how often your QR codes are scanned, what devices are used, and when scans happen.

### Requirements

Scan analytics only work with dynamic QR codes. Static QR codes link directly to the destination and bypass your server, so scans can't be tracked.

### Dashboard

Access the analytics dashboard at **QR Codes → Analytics**.

The dashboard shows:

- **Summary cards** — Total scans, today's scans, this week, this month
- **Time chart** — Scans over time (7, 30, or 90 day view)
- **Top QR codes** — Ranked by scan count with sortable columns
- **Period filter** — Switch between 7 days, 30 days, 90 days, or all time

### Per-Code Analytics

Each QR code's edit screen shows a **Scan Analytics** meta box in the sidebar with:

- Total scan count
- Scans this week
- Last scan date and time
- Device breakdown (mobile, tablet, desktop percentages)

### Data Collected

For each scan event, the following is recorded:

| Field | Description |
|-------|-------------|
| QR Code ID | Which QR code was scanned |
| Short Code | The redirect short code |
| Scanned At | Timestamp of the scan |
| IP Hash | SHA-256 hash of IP + salt (not the raw IP) |
| Device Type | mobile, tablet, or desktop |
| User Agent | Browser/device identifier |
| Referer | Referring URL (if available) |

**Privacy note:** Raw IP addresses are never stored. Only a salted hash is kept for unique visitor estimation.

### List Table Integration

The QR codes list table shows a **Scans** column for dynamic codes, sortable by scan count. Static codes show a dash.

### Campaign Filtering

When viewing analytics, you can filter by campaign to see aggregated stats for a group of QR codes. Select a campaign from the dropdown to narrow the view.

---

## Campaigns

Campaigns let you group related QR codes together for organized management and aggregated analytics.

### Creating a Campaign

Campaigns are implemented as a custom taxonomy on the `qrc_ms_code` post type.

1. Edit any QR code
2. In the **Campaigns** panel, add or select a campaign
3. Save the QR code

You can also manage campaigns at **QR Codes → Campaigns** (taxonomy management screen).

### Campaign Analytics

From the analytics dashboard, select a campaign from the filter dropdown to see:

- Combined scan count across all QR codes in the campaign
- Time chart showing aggregated scans
- Individual QR code performance within the campaign

### Use Cases

- **Event marketing** — Group all QR codes for a conference under one campaign
- **Product launches** — Track all QR codes related to a product release
- **Location-based** — Group codes by physical location (store A, store B)
- **A/B testing** — Compare performance of different QR code placements

---

## Bulk Generation

Generate multiple QR codes at once from a CSV file or WooCommerce products.

### CSV Upload

1. Go to **QR Codes → Bulk Generate**
2. Upload a CSV file with your data
3. Map CSV columns to QR code fields
4. Configure shared styling options
5. Click **Generate**

**CSV format example:**

```csv
title,type,url
Homepage,url,https://example.com
Contact,url,https://example.com/contact
Support,email,support@example.com
```

**Supported CSV columns:**

| Column | Description |
|--------|-------------|
| `title` | QR code title (post title) |
| `type` | QR code type (url, text, email, phone, etc.) |
| `url` | URL value (for url type) |
| `text` | Text value (for text type) |
| `email` | Email address (for email type) |
| `phone` | Phone number (for phone/sms type) |
| `ssid` | WiFi network name (for wifi type) |
| `password` | WiFi password (for wifi type) |

### WooCommerce Batch

Generate QR codes for all (or selected) WooCommerce products at once:

1. Go to **QR Codes → Bulk Generate → WooCommerce**
2. Select products (or "All Products")
3. Configure styling
4. Click **Generate**

Each product gets a QR code linking to its product page URL.

### ZIP Download

After bulk generation, download all generated QR codes as a ZIP file:

- Choose PNG or SVG format
- Files are named by QR code title (sanitized)
- Useful for sending to print shops

---

## Advanced Branding

Customize QR codes beyond basic colors with logos, gradients, eye styles, and frames.

### Center Logos

Add a logo image to the center of your QR codes:

1. Edit a QR code (or template)
2. In the **Branding** panel, upload or select a logo
3. Adjust logo size (percentage of QR code area, default 20%)
4. The error correction level should be set to H (30%) when using logos

The logo is embedded in the SVG output and overlaid on the PNG output. The QR code's error correction compensates for the obscured modules.

### Gradients

Apply gradient fills to QR code modules:

| Option | Description |
|--------|-------------|
| Gradient Type | Linear or radial |
| Start Color | First gradient color |
| End Color | Second gradient color |
| Angle | Gradient direction (linear only, in degrees) |

### Eye Styles

Customize the three finder patterns (eyes) in the corners:

| Style | Description |
|-------|-------------|
| Square | Default square eyes |
| Rounded | Rounded corner eyes |
| Circle | Circular eyes |
| Dot | Dot-style eyes |

Eye color can be set independently from the module color.

### Frames

Add a frame around the QR code with a text label:

| Option | Description |
|--------|-------------|
| Frame Style | None, simple border, rounded border, banner |
| Frame Color | Border/background color |
| Label Text | Text displayed below the QR code (e.g., "Scan Me") |
| Label Font Size | Text size in pixels |
| Label Color | Text color |

### Templates with Branding

All branding options can be saved as part of a template. When you save a QR code's styling as a template, branding options (logo, gradient, eye style, frame) are included.

### Per-Code Overrides

Even when a template is applied, you can override branding options on individual QR codes. The override takes precedence over the template value.

---

## Automation Rules

Automatically create or update QR codes based on WordPress events.

### Auto-Create on Publish

Automatically generate a QR code when a post or page is published:

1. Go to **QR Codes → Settings → Automation** (pro tab)
2. Enable "Auto-create QR on publish"
3. Select which post types trigger auto-creation
4. Choose a default template for auto-created codes
5. Optionally enable dynamic mode for auto-created codes

When a post is published, a QR code is created with:
- Title: "QR: {Post Title}"
- Type: URL
- Data: The post's permalink
- Template: Your selected default

### Auto-Update on URL Change

When a post's permalink changes (slug edit, category change), automatically update the associated QR code:

- For static QR codes: regenerates the QR code with the new URL
- For dynamic QR codes: updates the redirect destination (no regeneration needed)

### Rule Configuration

| Setting | Description |
|---------|-------------|
| Enabled Post Types | Which post types trigger automation (post, page, product, custom) |
| Default Template | Template applied to auto-created QR codes |
| Enable Dynamic | Whether auto-created codes use dynamic mode |
| Auto-Update URLs | Update QR codes when permalinks change |

---

## Elementor Widgets

Native Elementor widgets for embedding QR codes in Elementor-built pages.

### QR Code Widget

A full-featured widget with all QR code options available in the Elementor editor:

- Type selection
- Data fields (contextual based on type)
- Styling controls (size, colors, margin, error correction)
- Branding options (logo, gradient, eye style, frame)
- Template selection
- Responsive size controls

### Current Page QR Widget

Automatically generates a QR code for the current page URL. Minimal configuration — just styling options.

### Widget Controls

All widgets integrate with Elementor's:
- Responsive controls (different sizes per breakpoint)
- Advanced tab (margin, padding, CSS classes)
- Motion effects
- Custom CSS (Pro Elementor)

---

## Team & Permissions

Control who can create, edit, and manage QR codes with role-based permissions.

### Capabilities

The pro plugin registers custom capabilities:

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `qrc_ms_create` | Create new QR codes | Administrator, Editor |
| `qrc_ms_edit_own` | Edit own QR codes | Administrator, Editor, Author |
| `qrc_ms_edit_others` | Edit other users' QR codes | Administrator, Editor |
| `qrc_ms_delete` | Delete QR codes | Administrator |
| `qrc_ms_manage_templates` | Create and edit templates | Administrator, Editor |
| `qrc_ms_view_analytics` | View scan analytics | Administrator, Editor |
| `qrc_ms_bulk_generate` | Use bulk generation | Administrator |
| `qrc_ms_manage_settings` | Access plugin settings | Administrator |

### Configuring Permissions

1. Go to **QR Codes → Settings → Team** (pro tab)
2. For each role, toggle capabilities on or off
3. Save changes

### Audit Logging

All significant actions are logged:

| Event | Logged Data |
|-------|-------------|
| QR code created | User, timestamp, QR code ID |
| QR code edited | User, timestamp, fields changed |
| QR code deleted | User, timestamp, QR code title |
| Destination changed (dynamic) | User, timestamp, old URL, new URL |
| Template created/edited | User, timestamp, template ID |
| Bulk generation | User, timestamp, count generated |
| Settings changed | User, timestamp, settings diff |

View the audit log at **QR Codes → Audit Log**.

---

## Export & Reporting

Export QR code data and generate printable materials.

### CSV Export

Export QR code data as a CSV file:

1. Go to **QR Codes → Export**
2. Select which fields to include
3. Optionally filter by campaign, date range, or type
4. Click **Export CSV**

**Exported fields:**

| Field | Description |
|-------|-------------|
| ID | Post ID |
| Title | QR code title |
| Type | QR code type |
| Data | Encoded data (URL, phone, etc.) |
| Created | Creation date |
| Template | Associated template name |
| Dynamic | Whether dynamic mode is enabled |
| Scans | Total scan count (if dynamic) |
| Last Scan | Last scan timestamp |
| Campaign | Associated campaign(s) |

### Printable QR Sheets

Generate print-ready PDF or HTML sheets with multiple QR codes:

1. Go to **QR Codes → Export → Print Sheets**
2. Select QR codes to include (or filter by campaign)
3. Choose layout (grid: 2×2, 3×3, 4×4, or list)
4. Configure label display (title, URL, both, none)
5. Click **Generate Sheet**

The output is a print-optimized HTML page (or downloadable PDF) suitable for:
- Business card inserts
- Product label sheets
- Event handout materials
- Office signage

### Analytics Export

Export scan analytics data:

1. Go to **QR Codes → Analytics**
2. Apply your desired filters (period, campaign)
3. Click **Export CSV**

Exported analytics include: QR code ID, title, scan count, last scan date, and device breakdown.
