# Release Process

This plugin uses a **simplified release system** that uses existing builds and deploys to GitHub.

## 🚀 Release Commands

```bash
# Build when needed (separate step)
npm run build
npm run plugin-zip

# Patch release (0.1.0 → 0.1.1) - Bug fixes
npm run release:patch

# Minor release (0.1.1 → 0.2.0) - New features
npm run release:minor

# Major release (0.2.0 → 1.0.0) - Breaking changes
npm run release:major
```

## 📋 What Happens Automatically

### **Prerequisites:**
- ✅ `ov25-woo-extension.zip` must exist (run `npm run plugin-zip` if needed)

### **Release Command Steps:**
1. ✅ **Verifies** zip file exists
2. ✅ **Updates** version in `ov25-woo-extension.php`
3. ✅ **Updates** version in `package.json`
4. ✅ **Commits** version bump + existing zip file to git
5. ✅ **Creates** version tag (e.g., `v0.2.0`)
6. ✅ **Pushes** everything to GitHub

### **GitHub Actions:**
7. ✅ **Verifies** zip file exists in repo
8. ✅ **Creates** GitHub release with the committed zip file
9. ✅ **Plugin Update Checker** detects new version
10. ✅ **Users** get update notifications

## 🎯 Simple Workflow

```bash
# Make your changes
git add .
git commit -m "Add new feature"

# Build when needed (only if assets changed)
npm run build
npm run plugin-zip

# Release it (uses existing zip)
npm run release:minor

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