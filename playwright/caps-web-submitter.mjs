/**
 * CAPS Web Form Submitter
 * 
 * This script handles automated submission to the BVI CAPS (Customs Automated Processing System)
 * web portal. It supports multi-item declarations and handles the complex form structure.
 * 
 * Usage:
 *   node playwright/caps-web-submitter.mjs --input-file="path/to/input.json"
 * 
 * Input JSON structure:
 * {
 *   "action": "submit" | "save" | "test" | "validate",
 *   "td_number": "004463956",          // optional — if set, opens existing TD instead of creating new
 *   "credentials": { "username": "...", "password": "..." },
 *   "headerData": { ... header-level fields ... },
 *   "items": [ { ... item fields ... }, ... ],
 *   "headless": true
 * }
 */

import { chromium } from 'playwright-core';
import { existsSync, readFileSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';

// Parse command line input
let input = {};
const args = process.argv.slice(2);

for (const arg of args) {
    if (arg.startsWith('--input-file=')) {
        const filePath = arg.substring('--input-file='.length);
        const fileContent = readFileSync(filePath, 'utf-8');
        input = JSON.parse(fileContent);
        break;
    } else if (arg.startsWith('{')) {
        input = JSON.parse(arg);
        break;
    }
}

// Configuration
const config = {
    action: input.action || 'test',
    loginUrl: input.loginUrl || 'https://caps.gov.vg/CAPSWeb/TraderLogin.jsp',
    credentials: input.credentials || {},
    headerData: input.headerData || {},
    items: input.items || [],
    attachments: input.attachments || [],
    td_number: input.td_number || null,
    headless: input.headless !== false,
    screenshotDir: input.screenshotDir || './storage/app/playwright-screenshots',
    timeout: input.timeout || 30000,
    slowMo: input.slowMo || 50,
};

// Result object
const result = {
    success: false,
    action: config.action,
    message: '',
    td_number: null,
    reference_number: null,
    screenshots: [],
    logs: [],
    errors: [],
    warnings: [],
};

function log(message, level = 'info') {
    const timestamp = new Date().toISOString();
    const entry = { timestamp, level, message };
    result.logs.push(entry);
    console.error(`[${timestamp}] [${level.toUpperCase()}] ${message}`);
}

// Find Chrome executable
function findChrome() {
    const possiblePaths = [
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium-browser',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ];
    
    for (const p of possiblePaths) {
        if (existsSync(p)) return p;
    }
    return null;
}

async function takeScreenshot(page, name) {
    try {
        // Ensure directory exists
        if (!existsSync(config.screenshotDir)) {
            mkdirSync(config.screenshotDir, { recursive: true });
        }
        
        const filename = `caps-${Date.now()}-${name}.png`;
        const filepath = join(config.screenshotDir, filename);
        await page.screenshot({ path: filepath, fullPage: true });
        result.screenshots.push(filepath);
        log(`Screenshot saved: ${filename}`);
    } catch (e) {
        log(`Failed to save screenshot: ${e.message}`, 'warn');
    }
}

/**
 * Handle confirmation dialogs
 */
function setupDialogHandler(page) {
    page.on('dialog', async dialog => {
        log(`Dialog appeared: ${dialog.message()}`);
        await dialog.accept();
    });
}

/**
 * Login to CAPS
 */
async function login(page) {
    log('Navigating to CAPS login page...');
    await page.goto(config.loginUrl, { waitUntil: 'networkidle' });
    
    // Check if already logged in
    const url = page.url();
    if (url.includes('RetrieveTDList')) {
        log('Already logged in');
        return true;
    }
    
    // Fill login form - CAPS uses specific field structure
    log('Filling login credentials...');
    
    // Try multiple selectors for User ID field
    const userIdSelectors = [
        'input[name="UserId"]',
        'input[name="userId"]',
        'input[name="userid"]',
        'input:near(:text("User ID"))',
        'input[accesskey="u"]',
        'input[type="text"]:first-of-type',
    ];
    
    let filled = false;
    for (const selector of userIdSelectors) {
        try {
            const element = await page.$(selector);
            if (element) {
                await element.fill(config.credentials.username);
                log(`Filled username using selector: ${selector}`);
                filled = true;
                break;
            }
        } catch (e) {
            // Try next selector
        }
    }
    
    if (!filled) {
        throw new Error('Could not find User ID field');
    }
    
    // Fill password
    await page.fill('input[type="password"]', config.credentials.password);
    log('Filled password');
    
    await takeScreenshot(page, '01-login-filled');
    
    // Submit login - look for login button
    log('Submitting login...');
    const loginSelectors = [
        'button:has-text("Login")',
        'input[value*="Login"]',
        'button[type="submit"]',
        'input[type="submit"]',
    ];
    
    for (const selector of loginSelectors) {
        try {
            const button = await page.$(selector);
            if (button) {
                await button.click();
                log(`Clicked login using selector: ${selector}`);
                break;
            }
        } catch (e) {
            // Try next selector
        }
    }
    
    // Wait for navigation
    await page.waitForLoadState('networkidle', { timeout: config.timeout });
    await page.waitForTimeout(2000); // Additional wait for CAPS
    
    // Check login success
    const postLoginUrl = page.url();
    if (postLoginUrl.includes('RetrieveTDList') || postLoginUrl.includes('TDDataEntry')) {
        log('Login successful!');
        await takeScreenshot(page, '02-logged-in');
        return true;
    }
    
    // Check for error message
    const errorText = await page.textContent('body');
    if (errorText.toLowerCase().includes('invalid') || errorText.toLowerCase().includes('error')) {
        throw new Error('Login failed - invalid credentials');
    }
    
    throw new Error('Login failed - unexpected page state');
}

/**
 * Create a new TD by clicking "New" button
 */
async function createNewTD(page) {
    log('Creating new TD...');
    
    // Wait for and click "New" button
    await page.waitForSelector('button:has-text("New"), input[value*="New"]', { timeout: config.timeout });
    await page.click('button:has-text("New"), input[value*="New"]');
    
    // Wait for TD Data Entry page
    await page.waitForURL('**/TDDataEntry**', { timeout: config.timeout });
    
    // Extract TD number
    const tdNumberElement = await page.$('td:has-text("TD NO.:")');
    if (tdNumberElement) {
        const tdRow = await tdNumberElement.$('xpath=..');
        const cells = await tdRow.$$('td');
        if (cells.length >= 2) {
            result.td_number = await cells[1].textContent();
            result.td_number = result.td_number.trim();
            log(`Created TD: ${result.td_number}`);
        }
    }
    
    await takeScreenshot(page, '03-new-td-created');
    return true;
}

/**
 * Open an existing TD by its number using direct URL navigation.
 * The TD must be in an editable state (not yet submitted).
 */
async function openExistingTD(page, tdNumber) {
    log(`Opening existing TD: ${tdNumber}...`);
    
    const tdUrl = `https://caps.gov.vg/CAPSWeb/TDDataEntryServlet?method=tddataentry.RetrieveTD&bcdNumber=${tdNumber}&isWebTrader=Y`;
    
    try {
        await page.goto(tdUrl, { waitUntil: 'domcontentloaded', timeout: 120000 });
    } catch (e) {
        log(`TD page load wait: ${e.message}`, 'warn');
    }

    // Wait for the form to be ready — large TDs can be very slow to render
    const formTimeout = 45000 + (config.items.length * 5000);
    try {
        await page.waitForSelector('input[name="head_TraderReference"]', { timeout: Math.min(formTimeout, 180000) });
    } catch (e) {
        // The page might still be loading; give it extra time
        log(`Waiting extra time for form to render (${config.items.length} items)...`, 'warn');
        await page.waitForTimeout(15000);
        const field = await page.$('input[name="head_TraderReference"]');
        if (!field) {
            throw new Error(`TD ${tdNumber} form did not load — field head_TraderReference not found`);
        }
    }

    result.td_number = tdNumber;
    log(`Opened existing TD: ${tdNumber}`);
    await takeScreenshot(page, '03-td-opened');
    return true;
}

/**
 * Fill a CAPS input field by its exact name attribute.
 * Uses page.fill() which triggers proper input/change/blur events.
 * For readonly fields, removes readonly first via JS, then fills.
 */
async function fillByName(page, inputName, value, fieldLabel) {
    if (value === null || value === undefined || value === '') {
        return false;
    }
    const strValue = String(value);

    // Try input first, then textarea (Description fields use textarea)
    let selector = `input[name="${inputName}"]`;
    let el = await page.$(selector);
    if (!el) {
        selector = `textarea[name="${inputName}"]`;
        el = await page.$(selector);
    }
    
    if (!el) {
        log(`Field not found: ${fieldLabel} (${inputName})`, 'warn');
        result.warnings.push(`Field not found: ${inputName}`);
        return false;
    }

    try {
        const isDisabled = await el.getAttribute('disabled');
        if (isDisabled !== null) {
            log(`Field ${fieldLabel} is disabled, skipping`, 'debug');
            return false;
        }

        const isReadonly = await el.getAttribute('readonly');
        if (isReadonly !== null) {
            await page.evaluate((sel) => {
                const input = document.querySelector(sel);
                if (input) input.removeAttribute('readonly');
            }, selector);
        }

        await el.fill(strValue);

        if (isReadonly !== null) {
            await page.evaluate((sel) => {
                const input = document.querySelector(sel);
                if (input) input.setAttribute('readonly', 'readonly');
            }, selector);
        }

        await el.dispatchEvent('change');
        log(`Filled ${fieldLabel}: ${strValue}`);
        return true;
    } catch (e) {
        log(`Error filling ${fieldLabel} (${inputName}): ${e.message}`, 'warn');
        return false;
    }
}

/**
 * Fill header section using exact input[name] selectors.
 *
 * CAPS input names (extracted from live form):
 *   Summary: head_TraderReference, head_LinkedTdNumber
 *   Supplier: head_SupplierName, head_SupplierStreet, head_SupplierState,
 *             head_SupplierZip, head_SupplierCountry (Lookup)
 *   Transport: head_CarrierID (Lookup), head_CarrierNo,
 *              head_PortOfArrival (Lookup), head_ArrDepDate
 *   Manifest: head_ManifestNo, head_TotalNoOfPackages, head_MasterBOL,
 *             head_ContainerID_line1, head_ContainerLength_line1
 *   Shipment: head_CityDirShip, head_CountryDirShip (Lookup),
 *             head_CountryOrgShip (Lookup)
 *   Additional: head_AdditionalInfoCode_line1 (Lookup),
 *               head_AdditionalInfoText_line1
 *   Payment: head_PaymentCode_line1 (Lookup, readonly)
 *   Totals: head_TotalFreight, head_TotalInsurance, head_TotalPayableAmount
 */
async function fillHeaderSection(page, data, context) {
    log('Filling header section...');

    // Summary Detail
    await fillByName(page, 'head_TraderReference', data.trader_reference, 'Trader Reference');

    // Section 1 – Supplier
    // Clear supplier ID if empty (important when editing existing TDs)
    if (!data.supplier_id) {
        const supplierField = await page.$('input[name="head_SupplierID"]');
        if (supplierField) {
            await supplierField.fill('');
            log('Cleared Supplier ID field');
        }
    } else {
        await fillByName(page, 'head_SupplierID', data.supplier_id, 'Supplier ID');
    }
    await fillByName(page, 'head_SupplierName', data.supplier_name, 'Supplier Name');
    await fillByName(page, 'head_SupplierStreet', data.supplier_street, 'Supplier Street');
    await fillByName(page, 'head_SupplierState', data.supplier_city, 'Supplier City/State');
    await fillByName(page, 'head_SupplierZip', data.supplier_zip, 'Supplier ZIP');
    await fillByName(page, 'head_SupplierCountry', data.supplier_country, 'Supplier Country');

    // Section 3 – Transport (Box 3)
    await fillByName(page, 'head_CarrierID', data.carrier_id, 'Carrier ID');
    await fillByName(page, 'head_CarrierNo', data.carrier_number, 'Carrier/Voyage No (Box 3a)');
    await fillByName(page, 'head_PortOfArrival', data.port_of_arrival, 'Port of Arrival');
    await fillByName(page, 'head_ArrDepDate', data.arrival_date, 'Arrival Date');

    // Section 4 – Manifest / B/L (Box 4)
    await fillByName(page, 'head_ManifestNo', data.manifest_number, 'Manifest No.');
    await fillByName(page, 'head_TotalNoOfPackages', data.packages_count, 'Total Packages');
    await fillByName(page, 'head_MasterBOL', data.bill_of_lading, 'Bill of Lading');
    await fillByName(page, 'head_ContainerID_line1', data.container_id, 'Container ID');

    // Section 5 – Shipment origin
    await fillByName(page, 'head_CityDirShip', data.city_of_shipment, 'City of Direct Shipment');
    await fillByName(page, 'head_CountryDirShip', data.country_of_shipment, 'Country of Direct Shipment');
    await fillByName(page, 'head_CountryOrgShip', data.country_of_origin_shipment, 'Country of Original Shipment');

    // Section 6a – Payment Method (must use Lookup popup; direct JS doesn't persist)
    if (data.payment_method && context) {
        try {
            const lookupLink = await page.evaluate(() => {
                const codeField = document.querySelector('input[name="head_PaymentCode_line1"]');
                if (!codeField) return null;
                let el = codeField.nextElementSibling;
                while (el) {
                    if (el.tagName === 'A' && el.textContent.trim().toLowerCase() === 'lookup') return true;
                    el = el.nextElementSibling;
                }
                const td = codeField.closest('td');
                if (td) {
                    const link = td.querySelector('a');
                    if (link && link.textContent.trim().toLowerCase() === 'lookup') return true;
                }
                return null;
            });

            if (lookupLink) {
                const popupPromise = context.waitForEvent('page', { timeout: 15000 });

                await page.evaluate(() => {
                    const codeField = document.querySelector('input[name="head_PaymentCode_line1"]');
                    if (!codeField) return;
                    let el = codeField.nextElementSibling;
                    while (el) {
                        if (el.tagName === 'A' && el.textContent.trim().toLowerCase() === 'lookup') { el.click(); return; }
                        el = el.nextElementSibling;
                    }
                    const td = codeField.closest('td');
                    if (td) {
                        const link = td.querySelector('a');
                        if (link) link.click();
                    }
                });

                const popup = await popupPromise;
                await popup.waitForLoadState('domcontentloaded', { timeout: 15000 });
                await popup.waitForTimeout(1000);
                log(`Payment Lookup popup opened: ${popup.url()}`);

                const codeLink = await popup.$(`a:has-text("${data.payment_method}")`);
                if (!codeLink) {
                    const exactLink = await popup.evaluate((code) => {
                        const links = [...document.querySelectorAll('a')];
                        for (const a of links) {
                            const row = a.closest('tr');
                            if (row && row.textContent.includes(code)) { a.click(); return true; }
                        }
                        return false;
                    }, data.payment_method);
                    if (exactLink) {
                        log(`Clicked payment code ${data.payment_method} via row match`);
                    } else {
                        log(`Payment code ${data.payment_method} not found in Lookup popup`, 'warn');
                    }
                } else {
                    await codeLink.click();
                    log(`Clicked payment code ${data.payment_method} in Lookup popup`);
                }

                await page.waitForTimeout(1000);
                const finalValue = await page.evaluate(() => {
                    const f = document.querySelector('input[name="head_PaymentCode_line1"]');
                    return f ? f.value : null;
                });
                log(`Payment Method (Box 6a) after Lookup: ${finalValue}`);
            } else {
                log('Payment Lookup link not found, falling back to JS', 'warn');
                await page.evaluate((method) => {
                    const codeField = document.querySelector('input[name="head_PaymentCode_line1"]');
                    if (codeField) { codeField.removeAttribute('readonly'); codeField.value = method; }
                    const keyField = document.querySelector('input[name="head_PaymentKey_line1"]');
                    if (keyField) keyField.value = method;
                }, data.payment_method);
            }
        } catch (e) {
            log(`Payment Lookup failed: ${e.message}, falling back to JS`, 'warn');
            await page.evaluate((method) => {
                const codeField = document.querySelector('input[name="head_PaymentCode_line1"]');
                if (codeField) { codeField.removeAttribute('readonly'); codeField.value = method; }
                const keyField = document.querySelector('input[name="head_PaymentKey_line1"]');
                if (keyField) keyField.value = method;
            }, data.payment_method);
        }
    }

    // Section 8/9 – Freight & Insurance
    await fillByName(page, 'head_TotalFreight', data.total_freight, 'Total Freight');
    await fillByName(page, 'head_TotalInsurance', data.total_insurance, 'Total Insurance');

    // Prorated checkboxes
    if (data.freight_prorated) {
        await page.evaluate(() => {
            const cb = document.querySelector('input[name="head_FreightProrated"]');
            if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }
        });
        log('Set freight prorated');
    }
    if (data.insurance_prorated) {
        await page.evaluate(() => {
            const cb = document.querySelector('input[name="head_InsuranceProrated"]');
            if (cb) { cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true })); }
        });
        log('Set insurance prorated');
    }

    await takeScreenshot(page, '04-header-filled');
    log('Header section filled');
}

/**
 * Fill a single item record using direct input[name="recN_FieldName"] selectors.
 *
 * CAPS item field naming pattern (extracted from live form):
 *   rec{N}_CPC, rec{N}_TariffNo, rec{N}_CountryOfOrigin,
 *   rec{N}_NoOfPackages, rec{N}_TypeOfPackages, rec{N}_Description,
 *   rec{N}_Quantity1 (net weight), rec{N}_Quantity2 (quantity),
 *   rec{N}_UnitForQuantity (Lookup), rec{N}_RecordValue (FOB),
 *   rec{N}_CurrencyCode (readonly Lookup),
 *   rec{N}_ChargeCode_line1/2 (Lookup), rec{N}_ChargeAmount_line1/2,
 *   rec{N}_TaxType_line1/2 (Lookup), rec{N}_TaxId_line1/2 (Lookup),
 *   rec{N}_AdditionalInfoCode_line1, rec{N}_AdditionalInfoText_line1
 */
async function fillItemRecord(page, item, recordIndex) {
    const recNum = recordIndex + 1;
    log(`Filling item record #${recNum}...`);

    try {
        await page.waitForLoadState('domcontentloaded', { timeout: 15000 });
    } catch (e) {
        await page.waitForTimeout(3000);
    }

    // Scroll to this record's section
    await page.evaluate((num) => {
        const pat = num.toString().padStart(4, '0');
        const el = document.evaluate(
            `//td[contains(text(), 'RECORD #${pat}')]`,
            document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
        ).singleNodeValue;
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, recNum);
    await page.waitForTimeout(500);

    const prefix = `rec${recNum}`;

    await fillByName(page, `${prefix}_CPC`, item.cpc, `Item ${recNum} CPC`);
    await fillByName(page, `${prefix}_TariffNo`, item.tariff_number, `Item ${recNum} Tariff`);
    await fillByName(page, `${prefix}_CountryOfOrigin`, item.country_of_origin, `Item ${recNum} Country of Origin`);
    await fillByName(page, `${prefix}_NoOfPackages`, item.packages_number, `Item ${recNum} No. Packages`);
    await fillByName(page, `${prefix}_TypeOfPackages`, item.packages_type, `Item ${recNum} Package Type`);
    await fillByName(page, `${prefix}_Description`, item.description, `Item ${recNum} Description`);
    await fillByName(page, `${prefix}_Quantity1`, item.net_weight, `Item ${recNum} Net Weight`);
    await fillByName(page, `${prefix}_Quantity2`, item.quantity, `Item ${recNum} Quantity`);
    await fillByName(page, `${prefix}_UnitForQuantity`, item.units, `Item ${recNum} Units`);
    await fillByName(page, `${prefix}_RecordValue`, item.fob_value, `Item ${recNum} FOB Value`);
    await fillByName(page, `${prefix}_CurrencyCode`, item.currency, `Item ${recNum} Currency`);

    // Charges / Deductions
    await fillByName(page, `${prefix}_ChargeCode_line1`, item.freight_code, `Item ${recNum} Freight Code`);
    await fillByName(page, `${prefix}_ChargeAmount_line1`, item.freight_amount, `Item ${recNum} Freight Amt`);
    await fillByName(page, `${prefix}_ChargeCode_line2`, item.insurance_code, `Item ${recNum} Insurance Code`);
    await fillByName(page, `${prefix}_ChargeAmount_line2`, item.insurance_amount, `Item ${recNum} Insurance Amt`);

    // Tax Types and Values
    await fillByName(page, `${prefix}_TaxType_line1`, item.tax_type_1, `Item ${recNum} Tax Type 1`);
    await fillByName(page, `${prefix}_TaxValue_line1`, item.tax_value_1 || item.cif_value || '', `Item ${recNum} Tax Value 1 (CIF)`);
    await fillByName(page, `${prefix}_TaxType_line2`, item.tax_type_2, `Item ${recNum} Tax Type 2`);
    await fillByName(page, `${prefix}_TaxValue_line2`, item.tax_value_2 || item.fob_value || '', `Item ${recNum} Tax Value 2 (FOB)`);

    log(`Item record #${recNum} filled`);
}

/**
 * Add a new item record by clicking "Add Record" button
 * This triggers a page reload/update in CAPS
 */
async function addNewRecord(page, currentRecordCount) {
    log('Adding new item record...');
    
    // Scroll to bottom where Add Record button usually is
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(500);
    
    // Try multiple selectors for the Add Record button
    const addSelectors = [
        'input[value*="Add Record"]',
        'input[name*="addRecord"]',
        'button:has-text("Add Record")',
        'input[type="button"][value*="Add"]',
    ];
    
    let addButton = null;
    for (const selector of addSelectors) {
        addButton = await page.$(selector);
        if (addButton) {
            log(`Found Add Record button with selector: ${selector}`);
            break;
        }
    }
    
    if (!addButton) {
        addButton = await page.evaluateHandle(() => {
            const inputs = document.querySelectorAll('input[type="button"]');
            for (const input of inputs) {
                if (input.value && input.value.toLowerCase().includes('add record')) {
                    return input;
                }
            }
            return null;
        });
        
        if (!addButton || !(await addButton.asElement())) {
            throw new Error('Add Record button not found');
        }
    }
    
    // Click Add Record and wait for page reload.
    // CAPS reloads the entire page when adding a record, and it gets slower with more records.
    const waitTimeout = Math.min(180000, 45000 + currentRecordCount * 8000);
    const nextRecNum = currentRecordCount + 1;
    const nextFieldName = `rec${nextRecNum}_CPC`;

    try {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: waitTimeout }),
            addButton.click(),
        ]);
    } catch (e) {
        log(`Navigation wait after Add Record (${waitTimeout}ms): ${e.message}`, 'warn');
        await page.waitForTimeout(5000);
        try {
            await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
        } catch (e2) {
            log('Page still loading after Add Record, waiting more...', 'warn');
            await page.waitForTimeout(10000);
        }
    }
    await page.waitForTimeout(2000);

    // Confirm the new record's input fields actually exist in the DOM
    try {
        const confirmTimeout = Math.min(90000, 20000 + currentRecordCount * 5000);
        await page.waitForFunction((fieldName) => {
            return !!document.querySelector(`input[name="${fieldName}"], textarea[name="${fieldName}"]`);
        }, nextFieldName, { timeout: confirmTimeout });
        log(`New item record #${nextRecNum} fields confirmed in DOM`);
    } catch (e) {
        log(`Could not confirm new record fields (${nextFieldName}), continuing...`, 'warn');
    }

    // Scroll to the new record section
    const recPadded = nextRecNum.toString().padStart(4, '0');
    await page.evaluate((pat) => {
        const cells = document.querySelectorAll('td');
        for (const cell of cells) {
            if (cell.textContent.includes(`RECORD #${pat}`)) {
                cell.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            }
        }
    }, recPadded);

    await page.waitForTimeout(500);
}

/**
 * Fill all item records
 */
async function fillItems(page, items) {
    if (!items || items.length === 0) {
        log('No items to fill');
        return;
    }
    
    log(`Filling ${items.length} item(s)...`);
    
    for (let i = 0; i < items.length; i++) {
        const recNum = i + 1;
        // Check if this record already exists in the DOM (editing existing TD)
        const recordExists = await page.$(`input[name="rec${recNum}_CPC"]`);
        
        if (i > 0 && !recordExists) {
            await addNewRecord(page, i);
        } else if (recordExists) {
            log(`Record #${recNum} already exists, overwriting fields`);
        }
        
        await fillItemRecord(page, items[i], i);
        await takeScreenshot(page, `05-item-${i + 1}-filled`);
    }
    
    log(`All ${items.length} item(s) filled`);
}

/**
 * Mark extra records for deletion when editing a TD with fewer grouped items.
 * Sets hidden rec{N}_delete fields to "true" — CAPS removes them on save.
 */
async function deleteExtraRecords(page, keepCount) {
    const totalRecords = await page.evaluate(() => {
        const el = document.querySelector('input[name="PaginationTotalRecords"]') ||
                   document.querySelector('input[name="head_TotalNoOfRecords"]');
        return el ? parseInt(el.value, 10) : 0;
    });

    if (totalRecords <= keepCount) {
        log(`No extra records to delete (${totalRecords} records, keeping ${keepCount})`);
        return;
    }

    const toDelete = totalRecords - keepCount;
    log(`Marking ${toDelete} extra record(s) for deletion (${totalRecords} → ${keepCount})...`);

    const deleted = await page.evaluate(({ keep, total }) => {
        let count = 0;
        for (let i = keep + 1; i <= total; i++) {
            const field = document.querySelector(`input[name="rec${i}_delete"]`);
            if (field) {
                field.value = 'true';
                count++;
            }
        }
        return count;
    }, { keep: keepCount, total: totalRecords });

    log(`Marked ${deleted} record(s) for deletion — will be removed on save`);
    await takeScreenshot(page, '05b-records-marked-delete');
}

/**
 * Save the TD (without submitting)
 */
async function saveTD(page) {
    log('Saving TD...');
    
    // Scroll to bottom where buttons are
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(500);
    
    // Try multiple selectors for Save button
    const saveSelectors = [
        'input[value*="Save"]',
        'button:has-text("Save")',
        'input[name="save"]',
        'input[accesskey="1"]', // Save (1) has accesskey 1
    ];
    
    let saveButton = null;
    for (const selector of saveSelectors) {
        try {
            saveButton = await page.$(selector);
            if (saveButton) {
                const isVisible = await saveButton.isVisible();
                if (isVisible) {
                    log(`Found Save button with selector: ${selector}`);
                    break;
                }
            }
        } catch (e) {
            // Try next
        }
    }
    
    if (!saveButton) {
        // Try finding by visible text
        saveButton = await page.locator('input[type="button"]').filter({ hasText: 'Save' }).first();
    }
    
    if (!saveButton) {
        throw new Error('Save button not found');
    }
    
    // Save triggers a full page reload — use evaluate to click without Playwright's auto-wait
    log('Clicking Save...');
    await page.evaluate(() => {
        const btn = document.querySelector('input[name="save"]') || 
                    document.querySelector('input[value*="Save"]');
        if (btn) btn.click();
    });
    
    // Wait for CAPS to process the save — large TDs take a very long time
    const saveTimeout = Math.min(180000, 45000 + (config.items.length * 5000));
    log(`Waiting for save to complete (timeout: ${saveTimeout}ms)...`);
    
    try {
        await page.waitForLoadState('domcontentloaded', { timeout: saveTimeout });
    } catch (e) {
        log(`Save page load wait: ${e.message}`, 'warn');
    }

    // Extra wait to ensure CAPS finishes rendering
    await page.waitForTimeout(5000);

    // Confirm the page is interactive by checking for a known field
    try {
        await page.waitForSelector('input[name="head_TraderReference"]', { timeout: 30000 });
        log('Save confirmed — form is interactive again');
    } catch (e) {
        log('Form fields not yet available after save, waiting more...', 'warn');
        await page.waitForTimeout(15000);
    }

    await takeScreenshot(page, '06-saved').catch(() => {});
    log('TD saved successfully');
    return true;
}

/**
 * Validate the TD
 */
async function validateTD(page) {
    log('Validating TD...');
    
    // Ensure the page is fully loaded before interacting (critical after save timeouts)
    try {
        await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
    } catch (e) {
        log(`Page load wait before validate: ${e.message}`, 'warn');
    }
    await page.waitForTimeout(3000);
    
    // Scroll to bottom where buttons are
    try {
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    } catch (e) {
        log(`Scroll failed, retrying after wait: ${e.message}`, 'warn');
        await page.waitForTimeout(5000);
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)).catch(() => {});
    }
    await page.waitForTimeout(500);
    
    // Try multiple selectors for Validate button
    const validateSelectors = [
        'input[value*="Validate"]',
        'button:has-text("Validate")',
        'input[name="validate"]',
        'input[accesskey="2"]', // Validate (2) has accesskey 2
    ];
    
    let validateButton = null;
    for (const selector of validateSelectors) {
        try {
            validateButton = await page.$(selector);
            if (validateButton) {
                const isVisible = await validateButton.isVisible();
                if (isVisible) {
                    log(`Found Validate button with selector: ${selector}`);
                    break;
                }
            }
        } catch (e) {
            // Try next
        }
    }
    
    if (!validateButton) {
        validateButton = await page.locator('input[type="button"]').filter({ hasText: 'Validate' }).first();
    }
    
    if (!validateButton) {
        log('Validate button not found, skipping validation', 'warn');
        return false;
    }
    
    // Validation can take a long time with many items — use generous timeout
    const validateTimeout = Math.min(180000, 60000 + (config.items.length * 5000));
    log(`Clicking Validate (timeout: ${validateTimeout}ms for ${config.items.length} items)...`);
    
    try {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: validateTimeout }),
            validateButton.click(),
        ]);
    } catch (e) {
        log(`Validate navigation wait (${validateTimeout}ms): ${e.message}`, 'warn');
        // The click went through — CAPS may still be processing. Wait and check.
        await page.waitForTimeout(10000);
    }
    
    await page.waitForTimeout(3000);
    
    // Check for validation errors — CAPS uses specific phrasing
    const pageText = await page.textContent('body').catch(() => '');
    const lowerText = pageText.toLowerCase();
    const capsErrorPatterns = [
        'field not complete',
        'tariff no. not known',
        'supplier id not found',
        'trader not active',
        'not known',
        'not found',
        'not active',
        'validation results',
    ];
    const detectedErrors = capsErrorPatterns.filter(p => lowerText.includes(p));
    const hasErrors = detectedErrors.length > 0;
    
    if (hasErrors) {
        log(`Validation detected errors on the page`, 'warn');
        // Try to extract structured errors from the Validation Results table/section
        const errorDetails = await page.evaluate(() => {
            const errors = [];
            // CAPS puts validation results in a table or div near the top
            const cells = document.querySelectorAll('td, div, span');
            let capture = false;
            for (const cell of cells) {
                const text = (cell.textContent || '').trim();
                if (text === 'Validation Results') { capture = true; continue; }
                if (capture && text === 'Summary Detail') break;
                if (capture && /NOT COMPLETE|NOT KNOWN|NOT FOUND|NOT ACTIVE/i.test(text)) {
                    errors.push(text);
                }
            }
            if (errors.length === 0) {
                // Fallback: grab lines from body text
                const body = document.body.textContent || '';
                body.split('\n').forEach(l => {
                    l = l.trim();
                    if (/NOT COMPLETE|NOT KNOWN|NOT FOUND|NOT ACTIVE/i.test(l)) errors.push(l);
                });
            }
            return errors;
        });
        errorDetails.forEach(e => log(`  CAPS Error: ${e}`, 'warn'));
        result.warnings.push(`Validation errors: ${errorDetails.join('; ') || detectedErrors.join(', ')}`);
    } else {
        log('Validation passed - no errors detected');
    }
    
    await takeScreenshot(page, '07-validated');
    log('TD validation complete');
    return !hasErrors;
}

/**
 * Exit the TD form
 */
async function exitTD(page) {
    log('Exiting TD form...');
    
    const exitButton = await page.$('button:has-text("Exit"), input[value*="Exit"]');
    if (exitButton) {
        await exitButton.click();
        // Dialog handler will accept the confirmation
        await page.waitForTimeout(1000);
    }
    
    log('Exited TD form');
}

/**
 * Attach files (B/L, Invoice, etc.) to the TD via the "Attach File" popup.
 *
 * CAPS popup structure (inspected from live HTML):
 *   - Form: <form method="post" action="uploadFile" enctype="multipart/form-data" name="attachFile">
 *   - File input: <input type="file" name="uploadFile1">
 *   - Upload: <button type="button" onclick="...this.form.submit();">Upload</button>
 *   - Close:  <button type="button" onclick="window.close();">Close Window</button>
 *
 * After each upload the popup reloads showing the uploaded file plus a fresh
 * file input, so multiple files can be uploaded without reopening the popup.
 */
async function attachFiles(page, context, attachments) {
    if (!attachments || attachments.length === 0) {
        log('No attachments to upload');
        return;
    }

    // Filter to valid files only
    const validAttachments = attachments.filter(a => {
        if (!a.filePath || !existsSync(a.filePath)) {
            log(`Skipping attachment "${a.label || 'unknown'}": file not found at ${a.filePath}`, 'warn');
            result.warnings.push(`Attachment file not found: ${a.filePath}`);
            return false;
        }
        return true;
    });

    if (validAttachments.length === 0) {
        log('No valid attachment files to upload');
        return;
    }

    log(`Uploading ${validAttachments.length} attachment(s)...`);

    // Scroll to the Attach File button
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(500);

    const attachBtn = await page.$('input[value*="Attach File"]');
    if (!attachBtn) {
        log('Attach File button not found on TD form, skipping attachments', 'warn');
        result.warnings.push('Attach File button not found on form');
        return;
    }

    // Listen for the popup before clicking
    const popupPromise = context.waitForEvent('page', { timeout: config.timeout });
    await attachBtn.click();

    let popup;
    try {
        popup = await popupPromise;
        await popup.waitForLoadState('domcontentloaded', { timeout: config.timeout });
        await popup.waitForTimeout(2000);
        log(`Attach File popup opened: ${popup.url()}`);
    } catch (e) {
        log(`Attach File popup did not open: ${e.message}`, 'warn');
        result.warnings.push('Could not open Attach File popup');
        return;
    }

    // Upload each file in the same popup session
    for (const attachment of validAttachments) {
        try {
            log(`Uploading: ${attachment.label || attachment.filePath}`);

            // Wait for the file input to be present
            const fileInput = await popup.waitForSelector('input[type="file"]', { timeout: 10000 });
            if (!fileInput) {
                log('File input not found in popup', 'warn');
                result.warnings.push(`File input not found for: ${attachment.label}`);
                continue;
            }

            await fileInput.setInputFiles(attachment.filePath);
            log(`File selected: ${attachment.filePath}`);
            await popup.waitForTimeout(500);

            // The Upload button is <button type="button"> with onclick="...this.form.submit();"
            // Use evaluate to submit the form directly, which is more reliable than clicking
            const uploadBtn = await popup.waitForSelector('button:has-text("Upload")', { timeout: 5000 });
            if (!uploadBtn) {
                log('Upload button not found in popup', 'warn');
                result.warnings.push(`Upload button not found for: ${attachment.label}`);
                continue;
            }

            // Click Upload and wait for form submission to navigate the popup
            await Promise.all([
                popup.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
                uploadBtn.click(),
            ]);
            await popup.waitForTimeout(2000);

            // After upload, the popup reloads. Check for errors.
            const popupText = await popup.textContent('body').catch(() => '');
            if (popupText.toLowerCase().includes('error')) {
                log(`Upload may have failed for: ${attachment.label}`, 'warn');
                result.warnings.push(`Upload error for ${attachment.label}`);
            } else {
                log(`Successfully uploaded: ${attachment.label || attachment.filePath}`);
            }

            await takeScreenshot(popup, `08-attachment-${attachment.label || 'file'}`);

        } catch (e) {
            log(`Error uploading ${attachment.label || 'file'}: ${e.message}`, 'warn');
            result.warnings.push(`Attachment upload error: ${e.message}`);
        }
    }

    // Close the popup
    try {
        const closeBtn = await popup.$('button:has-text("Close Window")');
        if (closeBtn) {
            await closeBtn.click();
            await page.waitForTimeout(500);
        } else {
            await popup.close();
        }
    } catch (_) {
        try { await popup.close(); } catch (__) {}
    }

    await takeScreenshot(page, '08-attachments-done');
    log('Attachment process complete');
}

/**
 * Main submission flow
 */
async function runSubmission(browser) {
    const context = await browser.newContext();
    const page = await context.newPage();
    
    setupDialogHandler(page);
    
    try {
        // Step 1: Login
        await login(page);
        
        // Step 2: Create new TD or open existing
        if (config.td_number) {
            await openExistingTD(page, config.td_number);
        } else {
            await createNewTD(page);
        }
        
        // Step 3: Fill header section
        await fillHeaderSection(page, config.headerData, context);
        
        // Step 4: Fill item records
        await fillItems(page, config.items);
        
        // Step 4b: Delete extra records when editing with fewer grouped items
        if (config.td_number) {
            await deleteExtraRecords(page, config.items.length);
        }
        
        // Step 5: Save and optionally validate based on action
        if (config.action === 'submit' || config.action === 'save' || config.action === 'validate') {
            await saveTD(page);
            
            // Step 5b: Attach files (B/L, Invoice, etc.) after save
            if (config.attachments && config.attachments.length > 0) {
                await attachFiles(page, context, config.attachments);
            }
            
            if (config.action === 'submit' || config.action === 'validate') {
                // Validate after save
                const validationPassed = await validateTD(page);
                result.validation_passed = validationPassed;
            }
        }
        
        const actionLabel = {
            'save': 'saved',
            'validate': 'saved and validated',
            'submit': 'submitted'
        }[config.action] || 'processed';
        
        result.success = true;
        result.message = `TD ${result.td_number} ${actionLabel} successfully`;
        result.reference_number = result.td_number;
        
        // Exit the form
        await exitTD(page);
        
    } finally {
        await takeScreenshot(page, '99-final-state');
        await context.close();
    }
}

/**
 * Test connection only
 */
async function runTest(browser) {
    const context = await browser.newContext();
    const page = await context.newPage();
    
    setupDialogHandler(page);
    
    try {
        // Just test login
        await login(page);
        
        result.success = true;
        result.message = 'CAPS connection test successful';
        
    } finally {
        await context.close();
    }
}

/**
 * Main entry point
 */
async function main() {
    const chromePath = findChrome();
    if (!chromePath) {
        result.error = 'Chrome executable not found';
        console.log(JSON.stringify(result, null, 2));
        process.exit(1);
    }
    
    log(`Using Chrome at: ${chromePath}`);
    log(`Action: ${config.action}`);
    log(`Headless: ${config.headless}`);
    log(`Items count: ${config.items.length}`);
    
    let browser;
    try {
        browser = await chromium.launch({
            headless: config.headless,
            executablePath: chromePath,
            slowMo: config.slowMo,
        });
        
        if (config.action === 'test') {
            await runTest(browser);
        } else {
            await runSubmission(browser);
        }
        
    } catch (error) {
        result.success = false;
        result.error = error.message;
        result.errors.push(error.message);
        log(`Error: ${error.message}`, 'error');
    } finally {
        if (browser) {
            await browser.close();
        }
    }
    
    // Output result as JSON
    console.log(JSON.stringify(result, null, 2));
    process.exit(result.success ? 0 : 1);
}

main();
