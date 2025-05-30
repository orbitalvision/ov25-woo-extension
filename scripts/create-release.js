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
  console.log(`\n📋 This will create a release tag (using existing zip with matching version)`);
  
  // Check if git working directory is clean
  try {
    execSync('git diff-index --quiet HEAD --', { stdio: 'pipe' });
  } catch (error) {
    console.error('\n❌ Git working directory is not clean. Please commit your changes first.');
    process.exit(1);
  }
  
  // Check if zip file exists
  const zipFile = path.join(__dirname, '..', 'ov25-woo-extension.zip');
  if (!fs.existsSync(zipFile)) {
    console.error('\n❌ ov25-woo-extension.zip not found! Please run "npm run plugin-zip" first.');
    process.exit(1);
  }
  console.log('✅ Found existing ov25-woo-extension.zip');
  
  // Update package.json version only (for npm consistency)
  packageJson.version = newVersion;
  fs.writeFileSync(packagePath, JSON.stringify(packageJson, null, '\t') + '\n');
  
  console.log(`✅ Updated package.json to ${newVersion} (zip should already contain this version)`);
  
  // Commit version bump and existing zip
  runCommand('git add package.json ov25-woo-extension.zip', 'Staging package.json and zip');
  runCommand(`git commit -m "Release ${newVersion}"`, 'Committing release');
  
  // Create and push tag
  runCommand(`git tag v${newVersion}`, `Creating tag v${newVersion}`);
  runCommand(`git push origin main`, 'Pushing release to main');
  runCommand(`git push origin v${newVersion}`, 'Pushing tag to GitHub');
  
  console.log(`\n🎉 Release ${newVersion} completed successfully!`);
  console.log(`🔄 GitHub Actions will now create the official release with existing zip file.`);
  console.log(`📦 Plugin Update Checker will detect the new version automatically.`);
  console.log(`\n🔗 Monitor progress: https://github.com/orbitalvision/ov25-woo-extension/actions`);
  console.log(`📋 View releases: https://github.com/orbitalvision/ov25-woo-extension/releases`);
  
} catch (error) {
  console.error('❌ Error:', error.message);
  process.exit(1);
}