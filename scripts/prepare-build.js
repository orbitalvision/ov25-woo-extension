#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Paths
const vendorSrc = path.join(__dirname, '..', 'vendor', 'yahnis-elsts', 'plugin-update-checker', 'vendor');
const vendorDest = path.join(__dirname, '..', 'includes', 'plugin-update-checker', 'vendor');

// Create vendor directory if it doesn't exist
if (!fs.existsSync(vendorDest)) {
    fs.mkdirSync(vendorDest, { recursive: true });
}

// Copy Parsedown files
const filesToCopy = ['Parsedown.php', 'ParsedownModern.php', 'PucReadmeParser.php'];

console.log('📦 Copying vendor files for plugin-update-checker...');

filesToCopy.forEach(file => {
    const src = path.join(vendorSrc, file);
    const dest = path.join(vendorDest, file);
    
    if (fs.existsSync(src)) {
        fs.copyFileSync(src, dest);
        console.log(`✅ Copied ${file}`);
    } else {
        console.warn(`⚠️ Warning: ${file} not found in source`);
    }
});

console.log('✅ Vendor files prepared for build'); 