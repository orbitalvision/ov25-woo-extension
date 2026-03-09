#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const VERSION_FILE = path.join(ROOT, 'version.json');
const PHP_FILE = path.join(ROOT, 'ov25-woo-extension.php');
const PACKAGE_FILE = path.join(ROOT, 'package.json');

const SEMVER = /^\d+\.\d+\.\d+$/;

function getVersion() {
	if (fs.existsSync(VERSION_FILE)) {
		const data = JSON.parse(fs.readFileSync(VERSION_FILE, 'utf8'));
		return data.version;
	}
	const php = fs.readFileSync(PHP_FILE, 'utf8');
	const m = php.match(/\* Version: (\d+\.\d+\.\d+)/);
	if (m) return m[1];
	throw new Error('Could not determine current version');
}

function bumpVersion(version, type) {
	const [major, minor, patch] = version.split('.').map(Number);
	switch (type) {
		case 'major': return `${major + 1}.0.0`;
		case 'minor': return `${major}.${minor + 1}.0`;
		case 'patch': return `${major}.${minor}.${patch + 1}`;
		default: throw new Error(`Invalid bump type: ${type}`);
	}
}

function writeVersion(version) {
	fs.writeFileSync(VERSION_FILE, JSON.stringify({ version }, null, '\t') + '\n');
}

function updatePhp(version) {
	let content = fs.readFileSync(PHP_FILE, 'utf8');
	content = content.replace(/\* Version: \d+\.\d+\.\d+/, `* Version: ${version}`);
	content = content.replace(/public \$version = '\d+\.\d+\.\d+';/, `public $version = '${version}';`);
	fs.writeFileSync(PHP_FILE, content);
}

function updatePackage(version) {
	const pkg = JSON.parse(fs.readFileSync(PACKAGE_FILE, 'utf8'));
	pkg.version = version;
	fs.writeFileSync(PACKAGE_FILE, JSON.stringify(pkg, null, '\t') + '\n');
}

function sync(version) {
	writeVersion(version);
	updatePhp(version);
	updatePackage(version);
	console.log(`✓ Version ${version} synced to version.json, ov25-woo-extension.php, package.json`);
}

const arg = process.argv[2];
const current = getVersion();

if (!arg || arg === 'sync') {
	sync(current);
	process.exit(0);
}

let newVersion;
if (['patch', 'minor', 'major'].includes(arg)) {
	newVersion = bumpVersion(current, arg);
	console.log(`${current} → ${newVersion} (${arg})`);
} else if (SEMVER.test(arg)) {
	newVersion = arg;
	console.log(`${current} → ${newVersion}`);
} else {
	console.error('Usage: npm run bump-version [patch|minor|major|X.Y.Z|sync]');
	process.exit(1);
}

sync(newVersion);
