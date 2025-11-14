const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const phpFilePath = path.join(__dirname, '..', 'ov25-woo-extension.php');

function incrementPatchVersion(version) {
    const parts = version.split('.');
    parts[2] = String(parseInt(parts[2]) + 1);
    return parts.join('.');
}

function updateVersions() {
    let content = fs.readFileSync(phpFilePath, 'utf8');
    
    const versionRegex = /\* Version: (\d+\.\d+\.\d+)/;
    const match = content.match(versionRegex);
    
    if (!match) {
        throw new Error('Could not find version in plugin header');
    }
    
    const currentVersion = match[1];
    const newVersion = incrementPatchVersion(currentVersion);
    
    console.log(`Updating version from ${currentVersion} to ${newVersion}`);
    
    content = content.replace(
        /\* Version: \d+\.\d+\.\d+/,
        `* Version: ${newVersion}`
    );
    
    content = content.replace(
        /public \$version = '\d+\.\d+\.\d+';/,
        `public $version = '${newVersion}';`
    );
    
    fs.writeFileSync(phpFilePath, content, 'utf8');
    console.log('✓ Version numbers updated in ov25-woo-extension.php');
    
    return newVersion;
}

function runCommands() {
    const commands = [
        'npm install ov25-ui-react18@latest --legacy-peer-deps',
        'npm i --legacy-peer-deps',
        'npm run build',
        'npm run zip',
        'git add .',
        'git commit -m "updated ui pack"',
        'git push -u origin main',
        'npm run release:patch'
    ];
    
    for (const cmd of commands) {
        console.log(`\n> ${cmd}`);
        try {
            execSync(cmd, { stdio: 'inherit', cwd: path.join(__dirname, '..') });
        } catch (error) {
            console.error(`Error executing: ${cmd}`);
            process.exit(1);
        }
    }
}

try {
    const newVersion = updateVersions();
    console.log('\nRunning update sequence...\n');
    runCommands();
    console.log(`\n✓ Successfully completed UI pack update to version ${newVersion}`);
} catch (error) {
    console.error('Error:', error.message);
    process.exit(1);
}

