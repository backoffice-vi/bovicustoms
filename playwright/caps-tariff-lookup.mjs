/**
 * CAPS Tariff Lookup
 *
 * Logs into CAPS, opens an existing TD, opens the Tariff Lookup popup
 * for a chosen item record, and returns every code/description visible
 * for one or more search prefixes. No fields are saved or modified.
 *
 * Input JSON:
 * {
 *   "credentials": { "username": "...", "password": "..." },
 *   "td_number": "004496360",
 *   "record_index": 33,                 // record whose Tariff lookup link we click
 *   "prefixes": ["1104", "0813"],
 *   "screenshotDir": "./storage/app/playwright-screenshots",
 *   "outputFile": "./storage/app/caps-tariff-lookup-results.json"
 * }
 */

import { chromium } from 'playwright-core';
import { existsSync, readFileSync, writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';

let input = {};
const args = process.argv.slice(2);
for (const arg of args) {
    if (arg.startsWith('--input-file=')) {
        input = JSON.parse(readFileSync(arg.substring('--input-file='.length), 'utf-8'));
        break;
    } else if (arg.startsWith('{')) {
        input = JSON.parse(arg);
        break;
    }
}

const config = {
    loginUrl: input.loginUrl || 'https://caps.gov.vg/CAPSWeb/TraderLogin.jsp',
    credentials: input.credentials || {},
    td_number: input.td_number,
    record_index: Number.parseInt(input.record_index || '1', 10),
    prefixes: Array.isArray(input.prefixes) ? input.prefixes : [],
    headless: input.headless !== false,
    screenshotDir: input.screenshotDir || './storage/app/playwright-screenshots',
    outputFile: input.outputFile || null,
    timeout: input.timeout || 60000,
};

const result = {
    success: false,
    td_number: null,
    record_index: config.record_index,
    lookups: {},
    logs: [],
    errors: [],
};

function log(msg, level = 'info') {
    const ts = new Date().toISOString();
    const line = `[${ts}] [${level.toUpperCase()}] ${msg}`;
    result.logs.push({ ts, level, msg });
    console.error(line);
}

function findChrome() {
    const candidates = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    ];
    for (const p of candidates) if (existsSync(p)) return p;
    return null;
}

async function login(page) {
    log('Navigating to CAPS login page...');
    await page.goto(config.loginUrl, { waitUntil: 'networkidle', timeout: config.timeout });

    if (page.url().includes('RetrieveTDList')) {
        log('Already logged in');
        return;
    }

    log('Filling login credentials...');
    await page.fill('input[type="text"]:first-of-type', config.credentials.username);
    await page.fill('input[type="password"]', config.credentials.password);

    const loginBtn = await page.$('input[value*="Login"]') || await page.$('button:has-text("Login")');
    if (!loginBtn) throw new Error('Login button not found');
    await loginBtn.click();

    await page.waitForLoadState('networkidle', { timeout: config.timeout });
    await page.waitForTimeout(2000);
    log('Login successful');
}

async function openTD(page, tdNumber) {
    log(`Opening TD ${tdNumber}...`);
    const url = `https://caps.gov.vg/CAPSWeb/TDDataEntryServlet?method=tddataentry.RetrieveTD&bcdNumber=${tdNumber}&isWebTrader=Y`;
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 240000 });

    try {
        await page.waitForSelector('input[name="head_TraderReference"]', { timeout: 240000 });
    } catch (e) {
        await page.waitForTimeout(15000);
        const field = await page.$('input[name="head_TraderReference"]');
        if (!field) throw new Error(`TD ${tdNumber} did not load`);
    }

    result.td_number = tdNumber;
    log(`Opened TD ${tdNumber}`);
}

async function openTariffLookup(page, context, recIndex) {
    const fieldName = `rec${recIndex}_TariffNo`;
    log(`Looking for Tariff Lookup link near ${fieldName}...`);

    const exists = await page.$(`input[name="${fieldName}"]`);
    if (!exists) {
        throw new Error(`Field ${fieldName} not present on the TD`);
    }

    // Diagnostic: collect every Lookup-style link visible on the page so we
    // know where the tariff lookup actually lives in CAPS.
    const allLookups = await page.evaluate(() => {
        const out = [];
        const links = Array.from(document.querySelectorAll('a, button, input[type="button"], input[type="submit"]'));
        for (const el of links) {
            const text = ((el.textContent || el.value || '') + '').trim();
            if (!/lookup|tariff|schedule|hs code/i.test(text)) continue;
            const onclick = (el.getAttribute('onclick') || '');
            const href = (el.getAttribute('href') || '');
            const td = el.closest('td');
            const tr = el.closest('tr');
            const context = (td?.innerText || tr?.innerText || el.parentElement?.innerText || '').slice(0, 200).replace(/\s+/g, ' ').trim();
            out.push({ tag: el.tagName, text, href, onclick: onclick.slice(0, 200), context });
        }
        return out;
    });
    log(`Found ${allLookups.length} candidate Lookup link(s) on page`);
    result.diagnostic_lookups = allLookups;

    const popupPromise = context.waitForEvent('page', { timeout: 30000 });

    const clicked = await page.evaluate((name) => {
        const f = document.querySelector(`input[name="${name}"]`);
        if (!f) return { ok: false, reason: 'field not found' };

        const containers = [f.parentElement, f.closest('td'), f.closest('tr')];
        for (const container of containers) {
            if (!container) continue;
            const links = container.querySelectorAll('a, input[type="button"]');
            for (const a of links) {
                const text = ((a.textContent || a.value || '') + '').trim().toLowerCase();
                if (text === 'lookup' || text.includes('lookup') || text.includes('tariff')) {
                    a.click();
                    return { ok: true, via: 'container', text };
                }
            }
        }

        // Walk forward siblings
        let el = f.nextElementSibling;
        let walked = 0;
        while (el && walked < 30) {
            if ((el.tagName === 'A' || el.tagName === 'INPUT') && /lookup|tariff/i.test(el.textContent || el.value || '')) {
                el.click();
                return { ok: true, via: 'sibling', text: (el.textContent || el.value || '').trim() };
            }
            el = el.nextElementSibling;
            walked++;
        }

        // Whole-page fallback: any link with "Tariff" text near a tariff field
        const all = Array.from(document.querySelectorAll('a, input[type="button"]'));
        for (const a of all) {
            const text = ((a.textContent || a.value || '') + '').trim().toLowerCase();
            if (text === 'tariff schedule' || text === 'tariff lookup' || text === 'hs lookup' || text === 'hs code lookup') {
                a.click();
                return { ok: true, via: 'whole-page', text };
            }
        }

        return { ok: false, reason: 'no lookup link found' };
    }, fieldName);

    if (!clicked.ok) {
        throw new Error(`No Lookup link found near ${fieldName}: ${clicked.reason || 'unknown'}`);
    }
    log(`Clicked Lookup via ${clicked.via}: '${clicked.text}'`);

    const popup = await popupPromise;
    await popup.waitForLoadState('domcontentloaded', { timeout: 30000 });
    await popup.waitForTimeout(1500);
    log(`Tariff Lookup popup opened: ${popup.url()}`);
    return popup;
}

async function searchPrefix(popup, prefix) {
    log(`Searching tariff prefix '${prefix}'...`);

    const inputs = await popup.$$('input[type="text"], input:not([type])');
    let searched = false;
    for (const inp of inputs) {
        const name = (await inp.getAttribute('name')) || '';
        const id = (await inp.getAttribute('id')) || '';
        if (/code|tariff|prefix|search/i.test(name) || /code|tariff|prefix|search/i.test(id)) {
            try {
                await inp.fill('');
                await inp.fill(prefix);
                searched = true;
                break;
            } catch (e) {
                // try next
            }
        }
    }

    if (!searched && inputs.length > 0) {
        try {
            await inputs[0].fill('');
            await inputs[0].fill(prefix);
            searched = true;
        } catch (e) {}
    }

    const searchButtons = await popup.$$('input[type="submit"], input[type="button"], button');
    for (const btn of searchButtons) {
        const value = (await btn.getAttribute('value')) || '';
        const text = (await btn.textContent()) || '';
        if (/search|find|go/i.test(value) || /search|find|go/i.test(text)) {
            try {
                await btn.click();
                break;
            } catch (e) {}
        }
    }

    try {
        await popup.waitForLoadState('domcontentloaded', { timeout: 30000 });
    } catch (e) {}
    await popup.waitForTimeout(1500);

    const rows = await popup.evaluate((pfx) => {
        const out = [];
        const seen = new Set();

        const trs = Array.from(document.querySelectorAll('tr'));
        for (const tr of trs) {
            const cells = tr.querySelectorAll('td, th');
            if (cells.length < 2) continue;
            const cellTexts = Array.from(cells).map(c => (c.textContent || '').trim());
            let raw = null;
            for (const txt of cellTexts) {
                const codeMatch = txt.match(/\b(\d{4}\.?\d{0,3})\b/);
                if (!codeMatch) continue;
                const digits = codeMatch[1].replace(/\D/g, '');
                if (digits.startsWith(pfx.replace(/\D/g, ''))) {
                    raw = codeMatch[1];
                    break;
                }
            }
            if (!raw) continue;

            // Extract first percentage / numeric rate found in any cell (skip the code cell itself).
            let rate = null;
            for (const txt of cellTexts) {
                if (txt === raw) continue;
                const pct = txt.match(/(\d{1,3}(?:\.\d{1,3})?)\s*%/);
                if (pct) { rate = pct[1] + '%'; break; }
                // Also catch standalone integers like '10' or '20' in a column labelled rate
                const stand = txt.match(/^\s*(\d{1,3}(?:\.\d{1,3})?)\s*$/);
                if (stand && txt !== raw) { rate = stand[1]; break; }
            }

            const desc = cellTexts.filter(s => s && s !== raw).join(' | ');

            const key = `${raw}|${desc}`;
            if (seen.has(key)) continue;
            seen.add(key);
            out.push({ code: raw, description: desc, rate });
        }
        return out;
    }, prefix);

    log(`Prefix '${prefix}' returned ${rows.length} candidate row(s)`);
    return rows;
}

(async () => {
    const chromePath = findChrome();
    if (!chromePath) {
        result.errors.push('Chrome executable not found');
        console.log(JSON.stringify(result, null, 2));
        process.exit(1);
    }

    if (!existsSync(config.screenshotDir)) {
        try { mkdirSync(config.screenshotDir, { recursive: true }); } catch (e) {}
    }

    const browser = await chromium.launch({
        executablePath: chromePath,
        headless: config.headless,
        args: ['--no-sandbox', '--disable-blink-features=AutomationControlled'],
    });

    try {
        const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
        const page = await context.newPage();
        page.on('dialog', d => d.accept());

        await login(page);
        await openTD(page, config.td_number);

        const popup = await openTariffLookup(page, context, config.record_index);

        for (const prefix of config.prefixes) {
            try {
                const rows = await searchPrefix(popup, prefix);
                result.lookups[prefix] = rows;
            } catch (e) {
                log(`Prefix '${prefix}' failed: ${e.message}`, 'warn');
                result.lookups[prefix] = { error: e.message };
            }
        }

        result.success = true;
    } catch (e) {
        log(`Error: ${e.message}`, 'error');
        result.errors.push(e.message);
        result.success = false;
    } finally {
        await browser.close();
    }

    if (config.outputFile) {
        try { writeFileSync(config.outputFile, JSON.stringify(result, null, 2)); } catch (e) {}
    }

    console.log(JSON.stringify(result, null, 2));
    process.exit(result.success ? 0 : 1);
})();
