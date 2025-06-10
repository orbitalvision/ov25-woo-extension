# Release Process

This plugin uses a **simplified release system** where you manually manage versions before zipping.

## 🚀 Release Workflow

```bash
# 1. Update versions manually in both places
# - Update "Version: X.X.X" in ov25-woo-extension.php header
# - Update "public $version = 'X.X.X';" in ov25-woo-extension.php class
#If you want to update the UI package, run:
npm install ov25-ui@latest --legacy-peer-deps

# 2. Build and zip with correct versions
npm run build
npm run zip

# 3. Push new ZIP to git hub
git add .
git commmit -m {message}
git push -u origin main

# 4. Release (only updates package.json for npm consistency)
npm run release:patch   # or release:minor, release:major
```

## 📋 What Happens Automatically

### **Prerequisites:**
- ✅ **Manually update** both versions in `ov25-woo-extension.php`
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

# 2. Manually update BOTH versions in ov25-woo-extension.php:
#    - Header: Version: 0.1.8
#    - Class:  public $version = '0.1.8';

# 3. Build with correct versions
npm run build
npm run plugin-zip

# 4. Release (auto-detects version from package.json bump)
npm run release:patch

# Done! ✨
```

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
2. Check that `ov25-woo-extension.zip` exists (`npm run plugin-zip` if missing)
3. Check you have push permissions to repo
4. Verify GitHub Actions logs

**If GitHub Action fails:**
- Check that `ov25-woo-extension.zip` was committed to the repo
- The action just grabs the existing zip, no rebuilding

**If Plugin Update Checker doesn't detect:**
1. Check GitHub release was created successfully
2. Make repository public (recommended)
3. Verify version was bumped in plugin header 