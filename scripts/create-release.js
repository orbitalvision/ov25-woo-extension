#!/usr/bin/env node

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

// Get the bump type from command line args
const bumpType = process.argv[2] || 'patch';

if (!['patch', 'minor', 'major'].includes(bumpType)) {
  console.error('❌ Invalid bump type. Use: patch, minor, or major');
  process.exit(1);
}

function runCommand(command, description) {
  console.log(`🔄 ${description}...`);
  try {
    execSync(command, { stdio: 'inherit' });
    console.log(`✅ ${description} completed`);
  } catch (error) {
    console.error(`❌ ${description} failed:`, error.message);
    process.exit(1);
  }
}

try {
  // Read current version from package.json
  const packagePath = path.join(__dirname, '..', 'package.json');
  const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
  const currentVersion = packageJson.version;
  
  console.log(`📦 Current version: ${currentVersion}`);
  
  // Calculate new version
  const [major, minor, patch] = currentVersion.split('.').map(Number);
  let newVersion;
  
  switch (bumpType) {
    case 'major':
      newVersion = `${major + 1}.0.0`;
      break;
    case 'minor':
      newVersion = `${major}.${minor + 1}.0`;
      break;
    case 'patch':
      newVersion = `${major}.${minor}.${patch + 1}`;
      break;
  }
  
  console.log(`🚀 New version: ${newVersion}`);
  console.log(`\n📋 This will build, zip, and release everything automatically!`);
  
  // Check if git working directory is clean
  try {
    execSync('git diff-index --quiet HEAD --', { stdio: 'pipe' });
  } catch (error) {
    console.error('\n❌ Git working directory is not clean. Please commit your changes first.');
    process.exit(1);
  }
  
  // Step 1: Build the plugin
  runCommand('npm run build', 'Building plugin assets');
  
  // Step 2: Create the zip
  runCommand('npm run plugin-zip', 'Creating plugin zip');
  
  // Step 3: Update version in files locally (for consistency)
  console.log(`🔄 Updating version to ${newVersion} in local files...`);
  
  // Update PHP plugin header
  const phpFile = path.join(__dirname, '..', 'ov25-woo-extension.php');
  let phpContent = fs.readFileSync(phpFile, 'utf8');
  phpContent = phpContent.replace(/Version: .*/, `Version: ${newVersion}`);
  fs.writeFileSync(phpFile, phpContent);
  
  // Update package.json version
  packageJson.version = newVersion;
  fs.writeFileSync(packagePath, JSON.stringify(packageJson, null, '\t') + '\n');
  
  console.log(`✅ Version updated to ${newVersion} in local files`);
  
  // Step 4: Commit version bump
  runCommand('git add ov25-woo-extension.php package.json', 'Staging version changes');
  runCommand(`git commit -m "Bump version to ${newVersion}"`, 'Committing version bump');
  
  // Step 5: Create and push tag
  runCommand(`git tag v${newVersion}`, `Creating tag v${newVersion}`);
  runCommand(`git push origin main`, 'Pushing version bump to main');
  runCommand(`git push origin v${newVersion}`, 'Pushing tag to GitHub');
  
  console.log(`\n🎉 Release ${newVersion} completed successfully!`);
  console.log(`🔄 GitHub Actions will now create the official release with zip file.`);
  console.log(`📦 Plugin Update Checker will detect the new version automatically.`);
  console.log(`\n🔗 Monitor progress: https://github.com/orbitalvision/ov25-woo-extension/actions`);
  console.log(`📋 View releases: https://github.com/orbitalvision/ov25-woo-extension/releases`);
  
} catch (error) {
  console.error('❌ Error:', error.message);
  process.exit(1);
} 