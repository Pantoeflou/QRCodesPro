# Pro Add-On — New Plugin Checklist

Follow this checklist to create a new pro add-on from this template.

---

## 1. Copy Template

```bash
cp -r wp-forever-pro my-plugin-pro
cd my-plugin-pro
rm -rf .git
git init
```

---

## 2. Rename Everything

### 2.1 Rename Files

- [ ] `wp-forever-pro.php` → `my-plugin-pro.php`

### 2.2 Update Plugin Header

- [ ] Plugin Name
- [ ] Plugin URI
- [ ] Description
- [ ] Author / Author URI
- [ ] Text Domain: `my-plugin-pro`

### 2.3 Find and Replace (Case-Sensitive)

- [ ] `wp-forever-pro` → `my-plugin-pro`
- [ ] `WP_FOREVER_PRO_` → `MY_PLUGIN_PRO_`
- [ ] `wp_forever_pro_` → `my_plugin_pro_`
- [ ] `WP_Forever_Pro_` → `My_Plugin_Pro_`
- [ ] `wp_forever_pro/` → `my_plugin_pro/`
- [ ] `WP Forever Pro` → `My Plugin Pro`

### 2.4 Update Dependency Check

- [ ] Change `WP_FOREVER_VERSION` to your free plugin's version constant
- [ ] Change `WP_FOREVER_PRO_MIN_FREE_VERSION` value to match

### 2.5 Update Server URLs

- [ ] `API_URL` in `includes/class-license-manager.php`
- [ ] `UPDATE_URL` in `includes/class-updater.php`

---

## 3. Update Free Plugin Extension Points

Ensure your free plugin provides the hooks that pro needs:

- [ ] `{prefix}/settings_tabs` filter (for License tab)
- [ ] `{prefix}/settings_tab_content_{tab}` action (for tab content)
- [ ] `{prefix}/feature_list` filter (for pro feature badges)
- [ ] `{prefix}/feature_access` filter (for feature gating)
- [ ] Any feature-specific hooks your pro modules need

---

## 4. Build Pro Modules

- [ ] Remove `modules/class-example-module.php`
- [ ] Create your actual pro module classes
- [ ] Register them in `includes/class-pro-loader.php`

---

## 5. Set Up Server Infrastructure

- [ ] License server endpoint (activate/deactivate/validate)
- [ ] Update server endpoint (version check + download URL)
- [ ] Test license activation flow end-to-end
- [ ] Test update delivery flow end-to-end

---

## 6. Test

- [ ] Pro activates when free plugin is active
- [ ] Pro shows error notice when free plugin is missing
- [ ] Pro shows error notice when free plugin version is too old
- [ ] License activation works
- [ ] License deactivation works
- [ ] Pro features load when licensed
- [ ] Pro features don't load when unlicensed
- [ ] Updates are detected and installable
- [ ] Uninstall cleans up pro data only (doesn't touch free plugin)

---

## 7. Package for Distribution

Create a ZIP for your website's download:

```bash
zip -r my-plugin-pro-1.0.0.zip my-plugin-pro/ \
  --exclude="*.git*" \
  --exclude="*docs/*" \
  --exclude="*.kiro/*" \
  --exclude="*tests/*"
```

Upload to your update server so licensed users receive it.
