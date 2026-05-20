/**
 * SkyHelper-Networth writes node_modules/skyhelper-networth/.itemsBackup.json (see
 * node_modules/skyhelper-networth/constants/itemsMap.js). After npm ci as root,
 * php-fpm (www-data) often cannot write there → EACCES and bazaar fallback networth.
 *
 * This script symlinks that path to storage/app/skyhelper-networth/.itemsBackup.json
 * so the web user can persist the backup like other Laravel storage files.
 *
 * When postinstall runs as root (typical deploy), it also chowns that storage
 * directory to www-data so the symlink target is writable by php-fpm.
 *
 * Override owner: SKYHELPER_NETWORTH_CHOWN=www-data:www-data (default). Set to
 * empty to skip chown.
 *
 * Runs from package.json "postinstall". Safe to re-run; no-op if already linked.
 */

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const projectRoot = path.join(__dirname, '..');
const pkgDir = path.join(projectRoot, 'node_modules', 'skyhelper-networth');
const linkPath = path.join(pkgDir, '.itemsBackup.json');
const storageDir = path.join(projectRoot, 'storage', 'app', 'skyhelper-networth');
const storageFile = path.join(storageDir, '.itemsBackup.json');

function resolveTarget(p) {
    return path.resolve(p);
}

/** After root npm ci, the backup file in storage must be owned by the FPM user. */
function chownStorageToWebUser() {
    if (process.platform === 'win32') {
        return;
    }
    let uid;
    try {
        uid = typeof process.getuid === 'function' ? process.getuid() : -1;
    } catch {
        return;
    }
    if (uid !== 0) {
        return;
    }
    const spec = (process.env.SKYHELPER_NETWORTH_CHOWN || 'www-data:www-data').trim();
    if (spec === '') {
        return;
    }
    if (!fs.existsSync(storageDir)) {
        return;
    }
    const r = spawnSync('chown', ['-R', spec, storageDir], { encoding: 'utf8' });
    if (r.status !== 0) {
        console.warn(
            '[skyhelper-networth] chown failed (status %s). Run: sudo chown -R %s %s',
            r.status,
            spec,
            storageDir,
        );
        if (r.stderr) {
            console.warn(r.stderr.trim());
        }
    } else {
        console.log('[skyhelper-networth] chown %s → %s (root postinstall)', spec, storageDir);
    }
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

    try {
        fs.mkdirSync(storageDir, { recursive: true });

        const targetAbs = resolveTarget(storageFile);

        if (!fs.existsSync(storageFile)) {
            fs.writeFileSync(storageFile, '[]', { mode: 0o664 });
        }

        if (fs.existsSync(linkPath)) {
            const st = fs.lstatSync(linkPath);
            if (st.isSymbolicLink()) {
                const cur = fs.readlinkSync(linkPath);
                const resolvedCur = resolveTarget(path.isAbsolute(cur) ? cur : path.join(path.dirname(linkPath), cur));
                if (resolvedCur === targetAbs) {
                    chownStorageToWebUser();
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

                    chownStorageToWebUser();
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

    chownStorageToWebUser();
}

main();
