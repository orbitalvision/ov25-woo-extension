# Release Process

This plugin uses a **one-command release system** that builds, zips, and releases everything automatically.

## 🚀 One-Command Release

```bash
# Patch release (0.1.0 → 0.1.1) - Bug fixes
npm run release:patch

# Minor release (0.1.1 → 0.2.0) - New features
npm run release:minor

# Major release (0.2.0 → 1.0.0) - Breaking changes
npm run release:major
```

**That's it!** One command does everything.

## 📋 What Happens Automatically

When you run **any** release command, it automatically:

### **Local Steps:**
1. ✅ **Builds** plugin assets (`npm run build`)
2. ✅ **Creates** plugin zip (`npm run plugin-zip`)
3. ✅ **Updates** version in `ov25-woo-extension.php`
4. ✅ **Updates** version in `package.json`
5. ✅ **Commits** version bump to git
6. ✅ **Creates** version tag (e.g., `v0.2.0`)
7. ✅ **Pushes** everything to GitHub

### **GitHub Actions:**
8. ✅ **Rebuilds** plugin (clean environment)
9. ✅ **Creates** GitHub release with zip file
10. ✅ **Plugin Update Checker** detects new version
11. ✅ **Users** get update notifications

## 🎯 Simple Workflow

```bash
# Make your changes
git add .
git commit -m "Add new feature"

# Release it (builds, zips, releases everything)
npm run release:minor

# Done! ✨
```

## 📦 What Gets Released

The automated zip includes:
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
2. Check you have push permissions to repo
3. Verify GitHub Actions logs

**If Plugin Update Checker doesn't detect:**
1. Check GitHub release was created successfully
2. Ensure `github-token.php` exists locally (for private repo access)
3. Verify version was bumped in plugin header 