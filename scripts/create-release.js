#!/usr/bin/env node

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const bumpType = process.argv[2] || 'patch';

if (!['patch', 'minor', 'major'].includes(bumpType)) {
  console.error('❌ Invalid bump type. Use: patch, minor, or major');
  process.exit(1);
}

function runCommand(command, description) {
  console.log(`🔄 ${description}...`);
  try {
    execSync(command, { stdio: 'inherit', cwd: path.join(__dirname, '..') });
    console.log(`✅ ${description} completed`);
  } catch (error) {
    console.error(`❌ ${description} failed:`, error.message);
    process.exit(1);
  }
}

const rootDir = path.join(__dirname, '..');

try {
  try {
    execSync('git diff-index --quiet HEAD --', { stdio: 'pipe', cwd: rootDir });
  } catch (error) {
    console.error('\n❌ Git working directory is not clean. Please commit your changes first.');
    process.exit(1);
  }

  runCommand('npm i', 'Installing dependencies');
  runCommand(`node scripts/bump-version.js ${bumpType}`, 'Bumping version (version.json, PHP, package.json)');

  const versionPath = path.join(rootDir, 'version.json');
  const versionData = JSON.parse(fs.readFileSync(versionPath, 'utf8'));
  const newVersion = versionData.version;

  runCommand('npm run zip', 'Building and creating plugin zip');

  runCommand('git add version.json ov25-woo-extension.php package.json ov25-woo-extension.zip', 'Staging version files and zip');
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