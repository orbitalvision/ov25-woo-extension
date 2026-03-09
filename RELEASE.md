# Release Process

This plugin uses a **simplified release system** where you manually manage versions before zipping.

## 🚀 Release Workflow


### **PRE RELEASE TEST CHECKLIST**
    - Check admin page loads (Woocommerce --> settings --> OV25 tab)
    - Check Non OV25 Product (product page --> add to cart --> cart --> checkout --> order summary)
    - Check OV25 product page
        - configurator shown
        - Variant controls are displayed
        - selections can be made
        - pricing comes through and updates with selections
        - consider optional features (gallery, swatches, filters, discounts)
        - check the same on mobile
    - Add to cart
        - correct SKU, prices, selections and thumbnail shown in side-cart
        - correct SKU, prices, selections and thumbnail shown on main cart page
        - can add multiple OV25 items, multiple non-OV25 items, or combinations.
    - Checkout
        - check with multiple OV25 items, multiple non-OV25 items, or combinations.
        - correct SKU, prices, selections and thumbnail shown
        - check that the prices add up manually for at least some scenarios
    - Order Received page
        - order can be made, and "Order Recieved" page is shown.
        - correct prices and selections are shown
        - all necessary informations is shown to identify the order.




```bash
To update the ui package run
npm run update-ui-pack
```



```bash
# 1. Bump version (updates version.json, ov25-woo-extension.php, package.json)
npm run bump-version patch   # or minor, major, or explicit: 0.4.0

npm i
# 2. Build and zip with correct versions
npm run build
npm run zip

# 3. Push new ZIP to GitHub
git add .
git commit -m {message}
git push -u origin main

# 4. Release
npm run release:patch   # or release:minor, release:major
```

## 📋 What Happens Automatically

### **Prerequisites:**
- ✅ Run `npm run bump-version patch` (or minor/major) to update `version.json`, `ov25-woo-extension.php`, and `package.json`
- ✅ `ov25-woo-extension.zip` must exist with correct versions

### **Release Command Steps:**
1. ✅ **Verifies** zip file exists
2. ✅ **Updates** version in `package.json` only (for npm consistency)
3. ✅ **Commits** package.json + existing zip file to git
4. ✅ **Creates** version tag (e.g., `v0.2.0`)
5. ✅ **Pushes** everything to GitHub

### **GitHub Actions:**
6. ✅ **Verifies** zip file exists in repo
7. ✅ **Creates** GitHub release with the committed zip file
8. ✅ **Plugin Update Checker** detects new version
9. ✅ **Users** get update notifications

## 🎯 Complete Workflow Example

```bash
# 1. Make your changes
git add .
git commit -m "Add new feature"

# 2. Bump version (syncs version.json → ov25-woo-extension.php + package.json)
npm run bump-version patch   # or minor, major, or 0.4.0

# 3. Build and zip
npm run build
npm run zip

# 4. Release
npm run release:patch

# Done! ✨
```

### **bump-version usage**
- `npm run bump-version patch` — 0.3.39 → 0.3.40
- `npm run bump-version minor` — 0.3.39 → 0.4.0
- `npm run bump-version major` — 0.3.39 → 1.0.0
- `npm run bump-version 0.5.0` — set explicit version
- `npm run bump-version` or `npm run bump-version sync` — sync from version.json to PHP + package.json

## 📦 What Gets Released

The zip file is built **separately** and includes:
- ✅ `ov25-woo-extension.php` (with updated version)
- ✅ `includes/**` (all plugin classes)
- ✅ `build/**` (compiled JS/CSS)
- ✅ `vendor/**` (PHP dependencies)
- ✅ `languages/**` (translations)
- ❌ `github-token.php` (excluded for security)
- ❌ `node_modules/` (excluded)
- ❌ Development files (excluded)

## 🔍 Monitoring

- **GitHub Actions**: https://github.com/orbitalvision/ov25-woo-extension/actions
- **Releases**: https://github.com/orbitalvision/ov25-woo-extension/releases

## 🛠️ Troubleshooting

**If release fails:**
1. Ensure clean git working directory (`git status`)
2. Check that `ov25-woo-extension.zip` exists (`npm run zip` if missing)
3. Check you have push permissions to repo
4. Verify GitHub Actions logs

**If GitHub Action fails:**
- Check that `ov25-woo-extension.zip` was committed to the repo
- The action just grabs the existing zip, no rebuilding

**If Plugin Update Checker doesn't detect:**
1. Check GitHub release was created successfully
2. Make repository public (recommended)
3. Verify version was bumped in plugin header 