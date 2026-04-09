/**
 * CAPS TD Downloader
 * 
 * Downloads all Trade Declarations from the BVI CAPS portal, including:
 * - Full-page screenshots of each declaration
 * - Structured data scraped from the HTML (saved as data.json)
 * - All attachment files (B/L, invoices, etc.)
 * 
 * Usage:
 *   node playwright/caps-td-downloader.mjs --all
 *   node playwright/caps-td-downloader.mjs --td=002841062
 *   node playwright/caps-td-downloader.mjs --td=002841062 --headless=false
 */

import { chromium } from 'playwright-core';
import { existsSync, readFileSync, mkdirSync, writeFileSync } from 'fs';
import { join } from 'path';
import https from 'https';
import http from 'http';

// ── CLI args ────────────────────────────────────────────────────────────────

const args = process.argv.slice(2);
const flags = {};
for (const arg of args) {
    const m = arg.match(/^--(\w[\w-]*)(?:=(.*))?$/);
    if (m) flags[m[1]] = m[2] ?? 'true';
}

const SINGLE_TD = flags.td || null;
const RUN_ALL = flags.all === 'true';
const HEADLESS = flags.headless !== 'false';

const BASE_URL = flags.url || 'https://caps.gov.vg';
const USERNAME = flags.username || readEnvValue('CAPS_USERNAME') || '';
const PASSWORD = flags.password || readEnvValue('CAPS_PASSWORD') || '';
const OUTPUT_DIR = flags.output || './storage/app/caps-downloads';

if (!SINGLE_TD && !RUN_ALL) {
    console.error('Usage: node playwright/caps-td-downloader.mjs --all  OR  --td=002841062');
    process.exit(1);
}

// ── Helpers ─────────────────────────────────────────────────────────────────

function readEnvValue(key) {
    try {
        const envPath = join(process.cwd(), '.env');
        const lines = readFileSync(envPath, 'utf-8').split('\n');
        for (const line of lines) {
            const match = line.match(new RegExp(`^${key}=(.*)$`));
            if (match) return match[1].trim();
        }
    } catch { /* ignore */ }
    return null;
}

function findChrome() {
    const paths = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium-browser',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];
    for (const p of paths) {
        if (existsSync(p)) return p;
    }
    return null;
}

function ensureDir(dir) {
    if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
}

function log(msg) {
    console.error(`[${new Date().toISOString()}] ${msg}`);
}

/**
 * Download a file from a URL using Node built-in http(s), preserving the
 * browser session cookies so authenticated resources can be fetched.
 */
function downloadFile(url, destPath, cookies) {
    return new Promise((resolve, reject) => {
        const proto = url.startsWith('https') ? https : http;
        const cookieHeader = cookies.map(c => `${c.name}=${c.value}`).join('; ');

        const req = proto.get(url, {
            headers: { Cookie: cookieHeader },
            rejectUnauthorized: false,
        }, res => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                return downloadFile(res.headers.location, destPath, cookies).then(resolve, reject);
            }
            if (res.statusCode !== 200) {
                return reject(new Error(`HTTP ${res.statusCode} for ${url}`));
            }
            const chunks = [];
            res.on('data', d => chunks.push(d));
            res.on('end', () => {
                writeFileSync(destPath, Buffer.concat(chunks));
                resolve(destPath);
            });
            res.on('error', reject);
        });
        req.on('error', reject);
    });
}

// ── Login ───────────────────────────────────────────────────────────────────

async function login(page) {
    log('Navigating to CAPS login…');
    await page.goto(`${BASE_URL}/CAPSWeb/TraderLogin.jsp`, { waitUntil: 'networkidle' });

    if (page.url().includes('RetrieveTDList')) {
        log('Already logged in');
        return;
    }

    const userSelectors = [
        'input[name="UserId"]', 'input[name="userId"]', 'input[name="userid"]',
        'input[accesskey="u"]', 'input[type="text"]:first-of-type',
    ];

    let filled = false;
    for (const sel of userSelectors) {
        const el = await page.$(sel);
        if (el) { await el.fill(USERNAME); filled = true; break; }
    }
    if (!filled) throw new Error('Could not find User ID field');

    await page.fill('input[type="password"]', PASSWORD);

    const loginBtns = [
        'button:has-text("Login")', 'input[value*="Login"]',
        'button[type="submit"]', 'input[type="submit"]',
    ];
    for (const sel of loginBtns) {
        const btn = await page.$(sel);
        if (btn) { await btn.click(); break; }
    }

    await page.waitForLoadState('networkidle', { timeout: 30000 });
    await page.waitForTimeout(2000);

    if (!page.url().includes('RetrieveTDList') && !page.url().includes('TDDataEntry')) {
        throw new Error('Login failed – unexpected URL: ' + page.url());
    }
    log('Login successful');
}

// ── TD List Collection ──────────────────────────────────────────────────────

async function collectTDNumbers(page) {
    log('Collecting TD numbers from list…');

    // Make sure we're on the TD list page
    if (!page.url().includes('RetrieveTDList')) {
        await page.goto(
            `${BASE_URL}/CAPSWeb/WebTraderServlet?method=webtrader.RetrieveTDList`,
            { waitUntil: 'networkidle' }
        );
    }

    await page.waitForTimeout(1000);

    // CAPS TD links are <a> tags whose text is a 9-digit number
    const tdNumbers = await page.evaluate(() => {
        const anchors = document.querySelectorAll('a');
        const nums = [];
        for (const a of anchors) {
            const text = a.textContent.trim();
            if (/^\d{7,}$/.test(text)) nums.push(text);
        }
        return nums;
    });
    log(`  Page 1: found ${tdNumbers.length} TDs`);

    // Handle pagination - CAPS uses Next/Last buttons
    let pageNum = 1;
    while (true) {
        const nextBtn = await page.$('input[value*="Next"], button:has-text("Next")');
        if (!nextBtn) break;
        const isDisabled = await nextBtn.getAttribute('disabled');
        if (isDisabled) break;

        await nextBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);
        pageNum++;

        const moreTDs = await page.evaluate(() => {
            const anchors = document.querySelectorAll('a');
            return Array.from(anchors)
                .map(a => a.textContent.trim())
                .filter(t => /^\d{7,}$/.test(t));
        });
        if (moreTDs.length === 0) break;
        tdNumbers.push(...moreTDs);
        log(`  Page ${pageNum}: found ${moreTDs.length} more TDs`);
    }

    const unique = [...new Set(tdNumbers)];
    log(`Total TDs found: ${unique.length}`);
    return unique;
}

// ── Scrape Single TD ────────────────────────────────────────────────────────

async function scrapeTD(page, context, tdNumber, outDir) {
    const tdDir = join(outDir, tdNumber);
    ensureDir(tdDir);
    ensureDir(join(tdDir, 'attachments'));

    log(`─── Processing TD ${tdNumber} ───`);

    const previewUrl = `${BASE_URL}/CAPSWeb/WebTraderServlet?method=webtrader.PreviewTD&tdNumber=${tdNumber}`;
    await page.goto(previewUrl, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(3000);

    // 1. Full-page screenshot
    const screenshotPath = join(tdDir, 'declaration.png');
    await page.screenshot({ path: screenshotPath, fullPage: true });
    log(`  Screenshot saved: declaration.png`);

    // 2. Scrape structured data from the HTML
    const data = await page.evaluate(() => {
        function textOf(label) {
            const cells = document.querySelectorAll('td');
            for (const cell of cells) {
                if (cell.textContent.includes(label)) {
                    const row = cell.closest('tr');
                    if (!row) continue;
                    const tds = row.querySelectorAll('td');
                    for (let i = 0; i < tds.length; i++) {
                        if (tds[i].textContent.includes(label) && i + 1 < tds.length) {
                            return tds[i + 1].textContent.trim();
                        }
                    }
                }
            }
            return null;
        }

        function findValueAfterBold(labelText) {
            const bolds = document.querySelectorAll('b, strong, td.label, td[class*="label"]');
            for (const b of bolds) {
                if (b.textContent.includes(labelText)) {
                    const next = b.nextElementSibling || b.parentElement?.nextElementSibling;
                    if (next) return next.textContent.trim();
                }
            }
            return null;
        }

        // Header data
        const header = {};

        // TD metadata
        const allText = document.body.innerText;
        const tdNoMatch = allText.match(/TD No[.:]\s*([\d]+)/i);
        header.td_number = tdNoMatch ? tdNoMatch[1] : null;

        const typeMatch = allText.match(/Type:\s*(\w+)/i);
        header.type = typeMatch ? typeMatch[1] : null;

        const submittedMatch = allText.match(/Submitted Time:\s*([^\n]+)/i);
        header.submitted_time = submittedMatch ? submittedMatch[1].trim() : null;

        const traderRefMatch = allText.match(/Trader Reference:\s*([^\n]*)/i);
        header.trader_reference = traderRefMatch ? traderRefMatch[1].trim() : null;

        // Declarant
        const declarantIdMatch = allText.match(/DECLARANT ID:\s*([\d]+)/i);
        header.declarant_id = declarantIdMatch ? declarantIdMatch[1] : null;

        const declarantNameMatch = allText.match(/DECLARANT NAME:\s*([^\n]+)/i);
        header.declarant_name = declarantNameMatch ? declarantNameMatch[1].trim() : null;

        // Supplier (Section 1)
        const supplierNameMatch = allText.match(/a\.\s*NAME:\s*([^\n]+?)(?:\s*b\.|$)/is);
        header.supplier_name = supplierNameMatch ? supplierNameMatch[1].trim() : null;

        const supplierCountryMatch = allText.match(/e\.\s*COUNTRY:\s*([A-Z]{2})/i);
        header.supplier_country = supplierCountryMatch ? supplierCountryMatch[1] : null;

        // Importer (Section 2)
        const importerIdMatch = allText.match(/IMPORTER ID:\s*([\d]+)/i);
        header.importer_id = importerIdMatch ? importerIdMatch[1] : null;

        const importerNameMatch = allText.match(/2\s+.*?a\.\s*NAME:\s*([^\n]+)/i);
        header.importer_name = importerNameMatch ? importerNameMatch[1].trim() : null;

        // Transport (Section 3)
        const carrierMatch = allText.match(/CARRIER ID.*?:\s*([^\n]+)/i);
        header.carrier = carrierMatch ? carrierMatch[1].trim() : null;

        const portMatch = allText.match(/PORT OF ARRIVAL:\s*([^\n]+)/i);
        header.port_of_arrival = portMatch ? portMatch[1].trim() : null;

        const arrivalMatch = allText.match(/ARRIVAL DATE:\s*([^\n]+)/i);
        header.arrival_date = arrivalMatch ? arrivalMatch[1].trim() : null;

        // Manifest (Section 4)
        const manifestMatch = allText.match(/MANIFEST NO\.?:\s*([\d]+)/i);
        header.manifest_number = manifestMatch ? manifestMatch[1] : null;

        const packagesMatch = allText.match(/NO\.\s*OF PACKAGES:\s*([\d]+)/i);
        header.total_packages = packagesMatch ? parseInt(packagesMatch[1]) : null;

        // Shipment (Section 5)
        const cityShipMatch = allText.match(/CITY OF DIR SHIP:\s*([^\n]+)/i);
        header.city_of_shipment = cityShipMatch ? cityShipMatch[1].trim() : null;

        const countryDirMatch = allText.match(/COUNTRY OF DIR SHIP:\s*([A-Z]{2})/i);
        header.country_of_direct_shipment = countryDirMatch ? countryDirMatch[1] : null;

        const countryOrigMatch = allText.match(/COUNTRY OF ORIG SHIP:\s*([A-Z]{2})/i);
        header.country_of_origin_shipment = countryOrigMatch ? countryOrigMatch[1] : null;

        // Totals
        const freightMatch = allText.match(/TOTAL FREIGHT:\s*([\d,.]+)/i);
        header.total_freight = freightMatch ? parseFloat(freightMatch[1].replace(/,/g, '')) : null;

        const insuranceMatch = allText.match(/TOTAL INSURANCE:\s*([\d,.]+)/i);
        header.total_insurance = insuranceMatch ? parseFloat(insuranceMatch[1].replace(/,/g, '')) : null;

        const fobTotalMatch = allText.match(/Total FOB:\s*([\d,.]+)/i);
        header.total_fob = fobTotalMatch ? parseFloat(fobTotalMatch[1].replace(/,/g, '')) : null;

        const importDutyMatch = allText.match(/Import Duty:\s*([\d,.]+)/i);
        header.import_duty = importDutyMatch ? parseFloat(importDutyMatch[1].replace(/,/g, '')) : null;

        const wharfageMatch = allText.match(/Wharfage:\s*([\d,.]+)/i);
        header.wharfage = wharfageMatch ? parseFloat(wharfageMatch[1].replace(/,/g, '')) : null;

        // Parse record items
        const items = [];
        const recordRegex = /RECORD\s*#(\d+)/gi;
        let recordMatch;
        const recordPositions = [];

        while ((recordMatch = recordRegex.exec(allText)) !== null) {
            recordPositions.push({
                number: recordMatch[1],
                index: recordMatch.index,
            });
        }

        for (let r = 0; r < recordPositions.length; r++) {
            const start = recordPositions[r].index;
            const end = r + 1 < recordPositions.length
                ? recordPositions[r + 1].index
                : allText.length;
            const block = allText.substring(start, end);

            const item = { record_number: recordPositions[r].number };

            const cpcM = block.match(/CPC:\s*([A-Z0-9]+)/i);
            item.cpc = cpcM ? cpcM[1] : null;

            const tariffM = block.match(/TARIFF NO\.?:\s*(\d+)/i);
            item.tariff_number = tariffM ? tariffM[1] : null;

            const originM = block.match(/COUNTRY OF ORIGIN:\s*([A-Z]{2})/i);
            item.country_of_origin = originM ? originM[1] : null;

            const pkgM = block.match(/NO\.\s*AND TYPE OF PACKAGES:\s*(\d+)\s+(\w+)/i);
            item.packages_count = pkgM ? parseInt(pkgM[1]) : null;
            item.packages_type = pkgM ? pkgM[2] : null;

            const descM = block.match(/DESCRIPTION:\s*\n?\s*([^\n]+)/i);
            item.description = descM ? descM[1].trim() : null;

            const fobM = block.match(/F\.?O\.?B\.?\s*VALUE:\s*([\d,.]+)/i);
            item.fob_value = fobM ? parseFloat(fobM[1].replace(/,/g, '')) : null;

            const cifM = block.match(/C\.?I\.?F\.?\s*VALUE:\s*([\d,.]+)/i);
            item.cif_value = cifM ? parseFloat(cifM[1].replace(/,/g, '')) : null;

            // Charges
            const frtM = block.match(/FRT\s+([\d,.]+)/i);
            item.freight = frtM ? parseFloat(frtM[1].replace(/,/g, '')) : null;

            const insM = block.match(/INS\s+([\d,.]+)/i);
            item.insurance = insM ? parseFloat(insM[1].replace(/,/g, '')) : null;

            // Tax rows
            const taxes = [];
            const taxRegex = /\b(CUD|WHA|EXC|CSC|EST|CUS)\b\s+([\d,.]+)\s+([\d,.]+)\s+([\d,.]+)/gi;
            let taxMatch;
            while ((taxMatch = taxRegex.exec(block)) !== null) {
                taxes.push({
                    type: taxMatch[1].toUpperCase(),
                    value: parseFloat(taxMatch[2].replace(/,/g, '')),
                    rate: parseFloat(taxMatch[3].replace(/,/g, '')),
                    amount: parseFloat(taxMatch[4].replace(/,/g, '')),
                });
            }
            item.taxes = taxes;

            const totalDueM = block.match(/TOTAL DUE:\s*([\d,.]+)/i);
            item.total_due = totalDueM ? parseFloat(totalDueM[1].replace(/,/g, '')) : null;

            items.push(item);
        }

        // Attachments - CAPS uses onclick handlers instead of href:
        //   onclick="javascript: window.open('/CAPSWeb/uploadFile?fileName=X&traderId=Y&tdNumber=Z','_blank')"
        const attachments = [];
        const allAnchors = document.querySelectorAll('a');
        for (const link of allAnchors) {
            const text = link.textContent.trim();
            const onclick = link.getAttribute('onclick') || '';
            const href = link.getAttribute('href') || '';

            // Match onclick pattern: window.open('/CAPSWeb/uploadFile?fileName=...')
            const onclickMatch = onclick.match(/window\.open\(['"]([^'"]+)['"]/);
            if (onclickMatch && text.match(/\.(pdf|doc|docx|xls|xlsx|jpg|jpeg|png)$/i)) {
                attachments.push({
                    filename: text,
                    downloadPath: onclickMatch[1],
                });
                continue;
            }

            // Also check regular href-based links
            if (href && href !== '#' && !href.startsWith('javascript:')) {
                if (text.match(/\.(pdf|doc|docx|xls|xlsx|jpg|jpeg|png)$/i) ||
                    href.match(/uploadFile|attachment|download|getFile/i)) {
                    attachments.push({
                        filename: text,
                        downloadPath: href,
                    });
                }
            }
        }

        return { header, items, attachments };
    });

    // Save scraped data
    writeFileSync(join(tdDir, 'data.json'), JSON.stringify(data, null, 2), 'utf-8');
    log(`  Scraped data saved: data.json (${data.items.length} items, ${data.attachments.length} attachments)`);

    // 3. Download attachments
    if (data.attachments.length > 0) {
        const cookies = await context.cookies();
        for (const att of data.attachments) {
            const filename = att.filename || `attachment_${Date.now()}`;
            const safeName = filename.replace(/[<>:"/\\|?*]/g, '_');

            // Build the full URL for the attachment
            let attUrl = att.downloadPath;
            if (attUrl && !attUrl.startsWith('http')) {
                attUrl = attUrl.startsWith('/')
                    ? `${BASE_URL}${attUrl}`
                    : `${BASE_URL}/CAPSWeb/${attUrl}`;
            }

            const destPath = join(tdDir, 'attachments', safeName);

            if (attUrl) {
                try {
                    await downloadFile(attUrl, destPath, cookies);
                    log(`  Downloaded attachment: ${safeName}`);
                    continue;
                } catch (err) {
                    log(`  WARN: Direct download failed for ${safeName}: ${err.message}`);
                }
            }

            // Fallback: click the link and capture from the popup/download
            try {
                const popupPromise = context.waitForEvent('page', { timeout: 10000 });
                await page.click(`a:has-text("${att.filename}")`);
                const popup = await popupPromise;
                await popup.waitForLoadState('networkidle', { timeout: 15000 });

                // The popup might directly serve the file - try getting its content
                const popupUrl = popup.url();
                if (popupUrl && popupUrl !== 'about:blank') {
                    await downloadFile(popupUrl, destPath, cookies);
                    log(`  Downloaded attachment (via popup URL): ${safeName}`);
                }
                await popup.close();
            } catch (err2) {
                log(`  WARN: Popup download also failed for ${safeName}: ${err2.message}`);
            }
        }
    }

    // 4. Also try downloading the "Payment Smry" PDF if available
    try {
        await page.goto(
            `${BASE_URL}/CAPSWeb/WebTraderServlet?method=webtrader.RetrieveTDList`,
            { waitUntil: 'networkidle' }
        );
        await page.waitForTimeout(1000);

        // Find the Payment Summary link for this TD
        const paymentLink = await page.evaluate((tdNum) => {
            const rows = document.querySelectorAll('tr');
            for (const row of rows) {
                if (row.textContent.includes(tdNum)) {
                    const links = row.querySelectorAll('a');
                    for (const l of links) {
                        if (l.textContent.includes('Payment') || l.textContent.includes('Smry')) {
                            return l.getAttribute('href');
                        }
                    }
                }
            }
            return null;
        }, tdNumber);

        if (paymentLink) {
            let payUrl = paymentLink;
            if (!payUrl.startsWith('http')) {
                payUrl = payUrl.startsWith('/')
                    ? `${BASE_URL}${payUrl}`
                    : `${BASE_URL}/CAPSWeb/${payUrl}`;
            }
            const cookies = await context.cookies();
            const payDest = join(tdDir, 'payment_summary.pdf');
            try {
                await downloadFile(payUrl, payDest, cookies);
                log(`  Downloaded: payment_summary.pdf`);
            } catch {
                // Try via page navigation and screenshot as fallback
                try {
                    const newPage = await context.newPage();
                    await newPage.goto(payUrl, { waitUntil: 'networkidle', timeout: 15000 });
                    await newPage.waitForTimeout(2000);
                    await newPage.screenshot({ path: join(tdDir, 'payment_summary.png'), fullPage: true });
                    await newPage.close();
                    log(`  Captured payment summary as screenshot`);
                } catch (e2) {
                    log(`  WARN: Could not get payment summary: ${e2.message}`);
                }
            }
        }
    } catch (err) {
        log(`  WARN: Error checking payment summary: ${err.message}`);
    }

    return data;
}

// ── Main ────────────────────────────────────────────────────────────────────

async function main() {
    const chromePath = findChrome();
    if (!chromePath) {
        console.error('Chrome not found');
        process.exit(1);
    }

    log(`Chrome: ${chromePath}`);
    log(`Headless: ${HEADLESS}`);
    log(`Output: ${OUTPUT_DIR}`);
    ensureDir(OUTPUT_DIR);

    const browser = await chromium.launch({
        headless: HEADLESS,
        executablePath: chromePath,
        slowMo: 50,
    });

    const context = await browser.newContext({
        acceptDownloads: true,
    });
    const page = await context.newPage();

    // Accept any dialogs
    page.on('dialog', async d => { await d.accept(); });

    const results = { success: [], failed: [], skipped: [] };

    try {
        await login(page);

        let tdNumbers;
        if (SINGLE_TD) {
            tdNumbers = [SINGLE_TD];
        } else {
            tdNumbers = await collectTDNumbers(page);
        }

        log(`\nWill process ${tdNumbers.length} TD(s): ${tdNumbers.join(', ')}\n`);

        for (const tdNum of tdNumbers) {
            // Skip if already downloaded
            const tdDir = join(OUTPUT_DIR, tdNum);
            if (existsSync(join(tdDir, 'data.json'))) {
                log(`Skipping TD ${tdNum} – already downloaded`);
                results.skipped.push(tdNum);
                continue;
            }

            try {
                await scrapeTD(page, context, tdNum, OUTPUT_DIR);
                results.success.push(tdNum);
            } catch (err) {
                log(`ERROR processing TD ${tdNum}: ${err.message}`);
                results.failed.push({ td: tdNum, error: err.message });
            }
        }
    } catch (err) {
        log(`FATAL: ${err.message}`);
        results.fatal = err.message;
    } finally {
        await browser.close();
    }

    // Output summary
    const summary = {
        downloaded: results.success.length,
        failed: results.failed.length,
        skipped: results.skipped.length,
        success: results.success,
        failed_details: results.failed,
        skipped_list: results.skipped,
    };

    console.log(JSON.stringify(summary, null, 2));
    log(`\nDone: ${results.success.length} downloaded, ${results.failed.length} failed, ${results.skipped.length} skipped`);
}

main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
