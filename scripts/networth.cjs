/**
 * Node.js script to calculate SkyBlock profile networth using SkyHelper-Networth.
 *
 * Production: skyhelper-networth writes `node_modules/skyhelper-networth/.itemsBackup.json`
 * after fetching Hypixel items. php-fpm (www-data) must be able to write that directory.
 * If `npm ci` ran as root: `sudo chown -R www-data:www-data node_modules/skyhelper-networth`
 *
 * Called from PHP via proc_open with argv[2]=output file, argv[3]=input file.
 */

const fs = require('fs');
const path = require('path');

const backupPath = path.join(__dirname, '..', 'node_modules', 'skyhelper-networth', '.itemsBackup.json');
const hasBackup = fs.existsSync(backupPath);

const { ProfileNetworthCalculator, NetworthManager, UpdateManager, getPrices } = require('skyhelper-networth');

// Stop npm update checks — they keep intervals alive and add latency in short-lived subprocesses.
try {
    UpdateManager.disable();
} catch {
    /* ignore */
}

if (hasBackup) {
    NetworthManager.itemsPromise = Promise.resolve();
}

const pricesCachePath = path.join(__dirname, '..', 'storage', 'app', '.skyhelper-prices-cache.json');
const PRICES_CACHE_TTL = 60 * 60 * 1000; // 1 hour on disk (skyhelper in-memory cache is separate)

function loadCachedPrices() {
    try {
        if (!fs.existsSync(pricesCachePath)) {
            return null;
        }
        const stat = fs.statSync(pricesCachePath);
        if (Date.now() - stat.mtimeMs > PRICES_CACHE_TTL) {
            return null;
        }
        const data = JSON.parse(fs.readFileSync(pricesCachePath, 'utf8'));

        return data && typeof data === 'object' ? data : null;
    } catch {
        return null;
    }
}

function savePricesCache(prices) {
    try {
        const dir = path.dirname(pricesCachePath);
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
        }
        fs.writeFileSync(pricesCachePath, JSON.stringify(prices), 'utf8');
    } catch {
        /* non-fatal */
    }
}

async function getOrFetchPrices() {
    const cached = loadCachedPrices();
    if (cached) {
        return cached;
    }

    const prices = await Promise.race([
        getPrices(true),
        new Promise((_, reject) => setTimeout(() => reject(new Error('prices timeout')), 20_000)),
    ]);

    savePricesCache(prices);

    return prices;
}

function itemUuidFromSkyhelperResult(entry) {
    if (!entry || entry.price <= 0) {
        return null;
    }

    const nbt = entry.item ?? entry.itemData ?? null;
    const uuid = nbt?.tag?.ExtraAttributes?.uuid ?? nbt?.ExtraAttributes?.uuid ?? null;

    return typeof uuid === 'string' && uuid !== '' ? uuid : null;
}

const _forceExitTimer = setTimeout(() => {
    process.stderr.write(JSON.stringify({ error: 'hard timeout' }));
    process.exit(2);
}, 90_000);
_forceExitTimer.unref();

const inputFilePath = process.argv[3] || '';

function readInput() {
    if (!inputFilePath || !fs.existsSync(inputFilePath)) {
        throw new Error('input file missing (argv[3]); PHP must pass a temp JSON path');
    }

    const data = fs.readFileSync(inputFilePath, 'utf8');
    try {
        fs.unlinkSync(inputFilePath);
    } catch {
        /* ignore */
    }

    return Promise.resolve(data);
}

readInput()
    .then(async (input) => {
        const originalStdoutWrite = process.stdout.write.bind(process.stdout);
        process.stdout.write = () => true;

        const { profileData, museumData, bankBalance } = JSON.parse(input);

        if (!profileData) {
            throw new Error('profileData is required');
        }

        await NetworthManager.itemsPromise;

        if (!hasBackup) {
            await NetworthManager.updateItems();
        }

        const prices = await getOrFetchPrices();

        const calculator = new ProfileNetworthCalculator(profileData, museumData || {}, bankBalance ?? 0);

        const result = await calculator.getNetworth({
            prices,
            stackItems: false,
            sortItems: false,
            cachePrices: true,
            includeItemData: true,
        });

        const itemPricesByUuid = {};
        const itemPricesById = {};

        for (const data of Object.values(result.types || {})) {
            if (!data?.items) {
                continue;
            }
            for (const item of data.items) {
                if (!item || item.price <= 0) {
                    continue;
                }

                const priceEntry = {
                    price: item.price,
                    soulbound: item.soulbound || false,
                };

                const uuid = itemUuidFromSkyhelperResult(item);
                if (uuid) {
                    itemPricesByUuid[uuid] = priceEntry;
                }

                if (item.id) {
                    if (!itemPricesById[item.id]) {
                        itemPricesById[item.id] = [];
                    }
                    itemPricesById[item.id].push(priceEntry);
                }
            }
        }

        const categories = {};
        for (const [key, val] of Object.entries(result.types || {})) {
            categories[key] = {
                total: val.total || 0,
                unsoulboundTotal: val.unsoulboundTotal || 0,
            };
        }

        const output = {
            networth: result.networth || 0,
            unsoulboundNetworth: result.unsoulboundNetworth || 0,
            purse: result.purse || 0,
            bank: result.bank || 0,
            personalBank: result.personalBank || 0,
            noInventory: result.noInventory || false,
            categories,
            itemPricesByUuid,
            itemPricesById,
        };

        const outFile = process.argv[2] || process.env.SKYBLOCKHUB_NETWORTH_OUT || '';
        const jsonOutput = JSON.stringify(output);

        if (outFile) {
            fs.writeFileSync(outFile, jsonOutput, 'utf8');
            process.stdout.write = originalStdoutWrite;
            clearTimeout(_forceExitTimer);
            process.exit(0);
        }

        process.stdout.write = originalStdoutWrite;
        const payload = Buffer.from(jsonOutput, 'utf8').toString('base64');
        process.stdout.write(`__SKYBLOCKHUB_JSON_START__${payload}__SKYBLOCKHUB_JSON_END__`);
        clearTimeout(_forceExitTimer);
        process.exit(0);
    })
    .catch((err) => {
        process.stderr.write(JSON.stringify({ error: err.message || String(err) }));
        clearTimeout(_forceExitTimer);
        process.exit(1);
    });
