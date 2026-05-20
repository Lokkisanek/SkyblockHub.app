/**
 * SkyHelper-Networth writes node_modules/skyhelper-networth/.itemsBackup.json (see
 * node_modules/skyhelper-networth/constants/itemsMap.js). After npm ci as root,
 * php-fpm (www-data) often cannot write there → EACCES and bazaar fallback networth.
 *
 * This script symlinks that path to storage/app/skyhelper-networth/.itemsBackup.json
 * so the web user can persist the backup like other Laravel storage files.
 *
 * Runs from package.json "postinstall". Safe to re-run; no-op if already linked.
 */

const fs = require('fs');
const path = require('path');

const projectRoot = path.join(__dirname, '..');
const pkgDir = path.join(projectRoot, 'node_modules', 'skyhelper-networth');
const linkPath = path.join(pkgDir, '.itemsBackup.json');
const storageDir = path.join(projectRoot, 'storage', 'app', 'skyhelper-networth');
const storageFile = path.join(storageDir, '.itemsBackup.json');

function resolveTarget(p) {
    return path.resolve(p);
}

function main() {
    if (process.platform === 'win32') {
        console.log('[skyhelper-networth] Skip items-backup symlink on win32 (not used in prod).');
        return;
    }

    if (!fs.existsSync(pkgDir)) {
        console.log('[skyhelper-networth] Package not installed; skip items-backup symlink.');
        return;
    }

    fs.mkdirSync(storageDir, { recursive: true });

    const targetAbs = resolveTarget(storageFile);

    if (!fs.existsSync(storageFile)) {
        fs.writeFileSync(storageFile, '[]', { mode: 0o664 });
    }

    try {
        if (fs.existsSync(linkPath)) {
            const st = fs.lstatSync(linkPath);
            if (st.isSymbolicLink()) {
                const cur = fs.readlinkSync(linkPath);
                const resolvedCur = resolveTarget(path.isAbsolute(cur) ? cur : path.join(path.dirname(linkPath), cur));
                if (resolvedCur === targetAbs) {
                    return;
                }
                fs.unlinkSync(linkPath);
            } else if (st.isFile()) {
                try {
                    fs.copyFileSync(linkPath, storageFile);
                    fs.unlinkSync(linkPath);
                } catch (e) {
                    console.warn(
                        '[skyhelper-networth] .itemsBackup.json is a regular file; could not replace with symlink:',
                        e.message,
                    );
                    console.warn(
                        '[skyhelper-networth] Fix: remove node_modules/skyhelper-networth/.itemsBackup.json and run npm install, or chown the package dir for www-data.',
                    );

                    return;
                }
            }
        }

        fs.symlinkSync(targetAbs, linkPath);
        console.log('[skyhelper-networth] Linked .itemsBackup.json → storage/app/skyhelper-networth/');
    } catch (e) {
        console.warn('[skyhelper-networth] Could not create items-backup symlink:', e.message);
        console.warn('[skyhelper-networth] Fallback: sudo chown -R www-data:www-data', pkgDir);
    }
}

main();
