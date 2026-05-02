/**
 * Release packager for OnlineSched.
 *
 * Revised by: Kurst Hyperyote for Furry Migration
 */

const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const root = path.resolve(__dirname, '..');
const distDir = path.join(root, 'dist');
const stagingDir = path.join(distDir, 'OnlineSched');

const excludedRootPhpFiles = new Set([
    'google_test.php',
    'icalbyroomx.php',
    'icalbyroom-backup.php',
]);

const requiredRuntimeFiles = [
    'OnlineSched.php',
    'vendor/autoload.php',
    'build/bundle.js',
    'build/main.css',
    'build/admin-badge-types.css',
    'admin-badge-types.js',
    'LICENSE',
    'README.md',
    'readme.txt',
    'CHANGELOG.md',
    'composer.json',
    'composer.lock',
];

const topLevelFiles = [
    'admin-badge-types.js',
    'LICENSE',
    'README.md',
    'readme.txt',
    'CHANGELOG.md',
    'composer.json',
    'composer.lock',
];

const topLevelDirs = [
    'templates',
    'lib',
    'includes',
    'vendor',
];

const html2TextFiles = [
    'html2text/html2text.php',
    'html2text/LICENSE.md',
    'html2text/README.md',
    'html2text/CHANGELOG.md',
    'html2text/composer.json',
];

function relativePath(fullPath) {
    return path.relative(root, fullPath).split(path.sep).join('/');
}

function assertRequiredFiles() {
    const missing = requiredRuntimeFiles.filter((file) => !fs.existsSync(path.join(root, file)));

    if (missing.length > 0) {
        throw new Error(`Release cannot be packaged. Missing required files:\n- ${missing.join('\n- ')}`);
    }
}

function readPluginVersion() {
    const pluginFile = fs.readFileSync(path.join(root, 'OnlineSched.php'), 'utf8');
    const versionMatch = pluginFile.match(/^Version:\s*(.+)$/im);

    if (!versionMatch || !versionMatch[1].trim()) {
        throw new Error('Could not read Version header from OnlineSched.php.');
    }

    return versionMatch[1].trim();
}

function shouldSkipBuildFile(sourcePath) {
    return sourcePath.endsWith('.map');
}

function shouldSkipDirectoryEntry(sourcePath) {
    const relative = relativePath(sourcePath);
    const basename = path.basename(sourcePath);

    if (basename === '.DS_Store') {
        return true;
    }

    if (basename === '.git' || basename === '.github' || basename === 'examples') {
        return true;
    }

    if (basename.startsWith('.')) {
        return true;
    }

    if ([
        'phpunit.xml',
        'ISSUE_TEMPLATE.md',
        'PULL_REQUEST_TEMPLATE.md',
    ].includes(basename)) {
        return true;
    }

    return relative.includes('/tests/')
        || relative.includes('/test-results/')
        || relative.includes('/playwright-report/');
}

function copyFile(source, destination) {
    fs.mkdirSync(path.dirname(destination), { recursive: true });
    fs.copyFileSync(source, destination);
}

function copyDirectory(source, destination, shouldSkip = () => false) {
    if (!fs.existsSync(source)) {
        return;
    }

    fs.mkdirSync(destination, { recursive: true });

    for (const entry of fs.readdirSync(source, { withFileTypes: true })) {
        const sourcePath = path.join(source, entry.name);
        const destinationPath = path.join(destination, entry.name);

        if (shouldSkipDirectoryEntry(sourcePath) || shouldSkip(sourcePath, entry)) {
            continue;
        }

        if (entry.isDirectory()) {
            copyDirectory(sourcePath, destinationPath, shouldSkip);
        } else if (entry.isFile()) {
            copyFile(sourcePath, destinationPath);
        }
    }
}

function copyRelativeFile(relative) {
    const source = path.join(root, relative);

    if (!fs.existsSync(source)) {
        return;
    }

    copyFile(source, path.join(stagingDir, relative));
}

function copyRootPhpFiles() {
    for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
        if (!entry.isFile() || path.extname(entry.name) !== '.php') {
            continue;
        }

        if (excludedRootPhpFiles.has(entry.name)) {
            continue;
        }

        copyRelativeFile(entry.name);
    }
}

function createCrcTable() {
    const table = new Uint32Array(256);

    for (let i = 0; i < 256; i += 1) {
        let value = i;

        for (let bit = 0; bit < 8; bit += 1) {
            value = (value & 1) ? (0xedb88320 ^ (value >>> 1)) : (value >>> 1);
        }

        table[i] = value >>> 0;
    }

    return table;
}

const crcTable = createCrcTable();

function crc32(buffer) {
    let crc = 0xffffffff;

    for (const byte of buffer) {
        crc = crcTable[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    }

    return (crc ^ 0xffffffff) >>> 0;
}

function dosDateTime(date) {
    const year = Math.max(date.getFullYear(), 1980);
    const dosTime = (date.getHours() << 11)
        | (date.getMinutes() << 5)
        | Math.floor(date.getSeconds() / 2);
    const dosDate = ((year - 1980) << 9)
        | ((date.getMonth() + 1) << 5)
        | date.getDate();

    return { dosTime, dosDate };
}

function collectZipFiles(directory, zipPrefix) {
    const files = [];

    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const sourcePath = path.join(directory, entry.name);
        const zipPath = path.posix.join(zipPrefix, entry.name);

        if (entry.isDirectory()) {
            files.push(...collectZipFiles(sourcePath, zipPath));
        } else if (entry.isFile()) {
            files.push({ sourcePath, zipPath });
        }
    }

    return files.sort((a, b) => a.zipPath.localeCompare(b.zipPath));
}

function uint16(value) {
    const buffer = Buffer.alloc(2);
    buffer.writeUInt16LE(value);
    return buffer;
}

function uint32(value) {
    const buffer = Buffer.alloc(4);
    buffer.writeUInt32LE(value >>> 0);
    return buffer;
}

function createZipArchive(sourceDirectory, zipPath) {
    const fileRecords = [];
    const output = [];
    let offset = 0;

    for (const file of collectZipFiles(sourceDirectory, 'OnlineSched')) {
        const data = fs.readFileSync(file.sourcePath);
        const compressedData = zlib.deflateRawSync(data, { level: 9 });
        const name = Buffer.from(file.zipPath, 'utf8');
        const stat = fs.statSync(file.sourcePath);
        const { dosTime, dosDate } = dosDateTime(stat.mtime);
        const checksum = crc32(data);

        if (data.length > 0xffffffff || offset > 0xffffffff) {
            throw new Error('Release zip is too large for the built-in packager.');
        }

        const localHeader = Buffer.concat([
            uint32(0x04034b50),
            uint16(20),
            uint16(0),
            uint16(8),
            uint16(dosTime),
            uint16(dosDate),
            uint32(checksum),
            uint32(compressedData.length),
            uint32(data.length),
            uint16(name.length),
            uint16(0),
            name,
        ]);

        output.push(localHeader, compressedData);
        fileRecords.push({
            name,
            checksum,
            compressedSize: compressedData.length,
            size: data.length,
            dosTime,
            dosDate,
            offset,
        });
        offset += localHeader.length + compressedData.length;
    }

    const centralDirectoryOffset = offset;

    for (const record of fileRecords) {
        const centralHeader = Buffer.concat([
            uint32(0x02014b50),
            uint16(20),
            uint16(20),
            uint16(0),
            uint16(8),
            uint16(record.dosTime),
            uint16(record.dosDate),
            uint32(record.checksum),
            uint32(record.compressedSize),
            uint32(record.size),
            uint16(record.name.length),
            uint16(0),
            uint16(0),
            uint16(0),
            uint16(0),
            uint32(0),
            uint32(record.offset),
            record.name,
        ]);

        output.push(centralHeader);
        offset += centralHeader.length;
    }

    const centralDirectorySize = offset - centralDirectoryOffset;
    const endOfCentralDirectory = Buffer.concat([
        uint32(0x06054b50),
        uint16(0),
        uint16(0),
        uint16(fileRecords.length),
        uint16(fileRecords.length),
        uint32(centralDirectorySize),
        uint32(centralDirectoryOffset),
        uint16(0),
    ]);

    output.push(endOfCentralDirectory);
    fs.writeFileSync(zipPath, Buffer.concat(output));
}

function zipRelease(version) {
    const zipPath = path.join(distDir, `OnlineSched-${version}.zip`);
    createZipArchive(stagingDir, zipPath);

    return zipPath;
}

function main() {
    assertRequiredFiles();
    const version = readPluginVersion();

    fs.rmSync(distDir, { recursive: true, force: true });
    fs.mkdirSync(stagingDir, { recursive: true });

    copyRootPhpFiles();
    topLevelFiles.forEach(copyRelativeFile);
    topLevelDirs.forEach((directory) => {
        copyDirectory(path.join(root, directory), path.join(stagingDir, directory));
    });
    copyDirectory(path.join(root, 'build'), path.join(stagingDir, 'build'), shouldSkipBuildFile);
    html2TextFiles.forEach(copyRelativeFile);
    copyDirectory(path.join(root, 'html2text', 'src'), path.join(stagingDir, 'html2text', 'src'));

    const zipPath = zipRelease(version);
    console.log(`Packaged ${path.relative(root, zipPath)}`);
}

main();
