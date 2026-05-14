/**
 * Node.js script to calculate SkyBlock profile networth using SkyHelper-Networth.
 *
 * Production: skyhelper-networth writes `node_modules/skyhelper-networth/.itemsBackup.json`
 * after fetching Hypixel items. php-fpm (www-data) must be able to write that directory.
 * If `npm ci` ran as root: `sudo chown -R www-data:www-data node_modules/skyhelper-networth`
 * (or the whole app tree the web user owns.)
 *
 * Called from PHP via:
 *   echo $json | node scripts/networth.cjs
 *
 * Input (JSON on stdin):
 *   { profileData, museumData, bankBalance }
 *
 * Output (JSON on stdout):
 *   {
 *     networth, unsoulboundNetworth, purse, bank, personalBank, noInventory,
 *     categories: { armor: { total, unsoulboundTotal }, ... },
 *     itemPricesByUuid: { "<uuid>": { price, soulbound }, ... },
 *     itemPricesById: { "<skyblock_id>": [{ price, soulbound }], ... }
 *   }
 */

const fs = require('fs');
const path = require('path');

// The NetworthManager singleton auto-starts updateItems() HTTP fetch on require().
// If backup items exist (loaded automatically by the library), skip the HTTP wait
// to avoid 5-15s delays when Hypixel API is slow.
const backupPath = path.join(__dirname, '..', 'node_modules', 'skyhelper-networth', '.itemsBackup.json');
const hasBackup = fs.existsSync(backupPath);

const { ProfileNetworthCalculator, NetworthManager, getPrices } = require('skyhelper-networth');

if (hasBackup) {
    // Backup was loaded on require(). Replace the pending HTTP promise with
    // an already-resolved one so calculations don't block on network.
    NetworthManager.itemsPromise = Promise.resolve();
}

// ── Local prices cache ──────────────────────────────────────────────
// getPrices() fetches from GitHub which can be slow (5-15s). Cache locally.
const pricesCachePath = path.join(__dirname, '..', 'storage', 'app', '.skyhelper-prices-cache.json');
const PRICES_CACHE_TTL = 5 * 60 * 1000; // 5 minutes

function loadCachedPrices() {
    try {
        if (!fs.existsSync(pricesCachePath)) return null;
        const stat = fs.statSync(pricesCachePath);
        if (Date.now() - stat.mtimeMs > PRICES_CACHE_TTL) return null;
        const data = JSON.parse(fs.readFileSync(pricesCachePath, 'utf8'));
        return data && typeof data === 'object' ? data : null;
    } catch {
        return null;
    }
}

function savePricesCache(prices) {
    try {
        const dir = path.dirname(pricesCachePath);
        if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(pricesCachePath, JSON.stringify(prices), 'utf8');
    } catch {}
}

async function getOrFetchPrices() {
    // Try local cache first (instant, no HTTP)
    const cached = loadCachedPrices();
    if (cached) return cached;

    // Fetch fresh prices with a 5s timeout
    const prices = await Promise.race([
        getPrices(false),
        new Promise((_, reject) => setTimeout(() => reject(new Error('prices timeout')), 5000)),
    ]);

    // Save to local file cache
    savePricesCache(prices);
    return prices;
}

// Hard safety timeout – must stay above PHP's networth wait + cold-start work
// (first-run item/price fetch can take 10–40s on slow hosts).
const _forceExitTimer = setTimeout(() => {
    process.stderr.write('Force exit: hard timeout\n');
    process.exit(2);
}, 90_000);
_forceExitTimer.unref();

// Read input from file (argv[3]) or stdin
const inputFilePath = process.argv[3] || '';

function readInput() {
    if (inputFilePath && fs.existsSync(inputFilePath)) {
        const data = fs.readFileSync(inputFilePath, 'utf8');
        try { fs.unlinkSync(inputFilePath); } catch {}
        return Promise.resolve(data);
    }
    // Fallback: read from stdin
    return new Promise((resolve) => {
        let buf = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { buf += chunk; });
        process.stdin.on('end', () => resolve(buf));
    });
}

readInput().then(async (input) => {
    try {
        // Some dependencies print progress/logging to stdout, which breaks
        // the PHP side expecting pure JSON. Temporarily suppress stdout writes
        // until we emit the final payload.
        const originalStdoutWrite = process.stdout.write.bind(process.stdout);
        process.stdout.write = () => true;

        const { profileData, museumData, bankBalance } = JSON.parse(input);

        if (!profileData) {
            throw new Error('profileData is required');
        }

        if (!hasBackup) {
            // No backup exists - must fetch items (first run only)
            await NetworthManager.updateItems();
        }

        // Pre-fetch prices from local cache (or GitHub with timeout)
        const prices = await getOrFetchPrices();

        // Create calculator and compute networth
        const calculator = new ProfileNetworthCalculator(
            profileData,
            museumData || {},
            bankBalance ?? 0
        );

        const result = await calculator.getNetworth({
            prices,
            stackItems: false,
            sortItems: false,
            cachePrices: true,
            includeItemData: true,
        });

        // Build item value maps:
        // 1. By UUID (most reliable, unique per item instance)
        // 2. By skyblock_id as fallback (for items without UUID, e.g. pets)
        const itemPricesByUuid = {};
        const itemPricesById = {};

        for (const [category, data] of Object.entries(result.types || {})) {
            if (!data.items) continue;
            for (const item of data.items) {
                if (!item || item.price <= 0) continue;

                const priceEntry = {
                    price: item.price,
                    soulbound: item.soulbound || false,
                };

                // Try to extract UUID from the original item data
                // SkyHelper-Networth stores raw NBT as item.item (not item.itemData)
                const uuid = item.item?.tag?.ExtraAttributes?.uuid;
                if (uuid) {
                    itemPricesByUuid[uuid] = priceEntry;
                }

                // Also store by skyblock_id as fallback
                if (item.id) {
                    if (!itemPricesById[item.id]) {
                        itemPricesById[item.id] = [];
                    }
                    itemPricesById[item.id].push(priceEntry);
                }
            }
        }

        // Build category totals
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

        // Prefer file output for robust IPC from PHP.
        const outFile = process.argv[2] || process.env.SKYBLOCKHUB_NETWORTH_OUT || '';
        const jsonOutput = JSON.stringify(output);

        if (outFile) {
            fs.writeFileSync(outFile, jsonOutput, 'utf8');
            // File IPC written – skip stdout to avoid pipe buffer deadlocks.
            process.stdout.write = originalStdoutWrite;
            clearTimeout(_forceExitTimer);
            process.exit(0);
        } else {
            // No file output: emit marker-wrapped base64 to stdout.
            process.stdout.write = originalStdoutWrite;
            const payload = Buffer.from(jsonOutput, 'utf8').toString('base64');
            process.stdout.write(`__SKYBLOCKHUB_JSON_START__${payload}__SKYBLOCKHUB_JSON_END__`);
            clearTimeout(_forceExitTimer);
            process.exit(0);
        }
    } catch (err) {
        process.stderr.write(JSON.stringify({ error: err.message || String(err) }));
        process.exit(1);
    }
});
