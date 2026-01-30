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
 *   "action": "submit" | "save" | "test",
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
 * Fill a text field by various selector strategies
 */
async function fillField(page, selectors, value, fieldName) {
    if (value === null || value === undefined || value === '') {
        log(`Skipping empty field: ${fieldName}`, 'debug');
        return false;
    }
    
    const selectorList = Array.isArray(selectors) ? selectors : [selectors];
    
    for (const selector of selectorList) {
        try {
            const element = await page.$(selector);
            if (element) {
                // Check if field is editable
                const isDisabled = await element.getAttribute('disabled');
                const isReadonly = await element.getAttribute('readonly');
                
                if (isDisabled || isReadonly) {
                    log(`Field ${fieldName} is readonly/disabled, skipping`, 'debug');
                    return false;
                }
                
                await element.fill(String(value));
                log(`Filled ${fieldName}: ${value}`);
                return true;
            }
        } catch (e) {
            // Try next selector
        }
    }
    
    log(`Could not find field ${fieldName} with selectors: ${selectorList.join(', ')}`, 'warn');
    result.warnings.push(`Field not found: ${fieldName}`);
    return false;
}

/**
 * Select a dropdown value
 */
async function selectField(page, selectors, value, fieldName) {
    if (value === null || value === undefined || value === '') {
        return false;
    }
    
    const selectorList = Array.isArray(selectors) ? selectors : [selectors];
    
    for (const selector of selectorList) {
        try {
            const element = await page.$(selector);
            if (element) {
                await element.selectOption(String(value));
                log(`Selected ${fieldName}: ${value}`);
                return true;
            }
        } catch (e) {
            // Try next selector
        }
    }
    
    log(`Could not find dropdown ${fieldName}`, 'warn');
    return false;
}

/**
 * Fill header section (Summary Detail + Header Detail)
 */
async function fillHeaderSection(page, data) {
    log('Filling header section...');
    
    // Fill all header fields at once using direct name-based selectors
    // CAPS uses predictable input names that we can target directly
    const headerResult = await page.evaluate((headerData) => {
        const results = [];
        
        // Helper to fill a field by trying multiple selectors
        function fillByName(namePattern, value, fieldName) {
            if (!value) return null;
            
            // Try exact name match first
            const inputs = document.querySelectorAll(`input[name="${namePattern}"], input[name*="${namePattern}"]`);
            for (const input of inputs) {
                if (!input.readOnly && input.type !== 'hidden' && input.type !== 'checkbox' && input.type !== 'button') {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    return { field: fieldName, success: true };
                }
            }
            return { field: fieldName, success: false };
        }
        
        // Helper to find input in a specific section (by section number)
        function fillInSection(sectionNumber, subField, value, fieldName) {
            if (!value) return null;
            
            // Find the section header (e.g., "1  SUPPLIER ID:" or "3  TRANSPORT DETAILS")
            const cells = document.querySelectorAll('td');
            let sectionTable = null;
            
            for (const cell of cells) {
                const text = cell.textContent.trim();
                // Match section headers like "1  SUPPLIER ID:" or "3  TRANSPORT DETAILS"
                if (text.match(new RegExp(`^${sectionNumber}\\s+`)) || 
                    text.includes(`${sectionNumber} `) && text.includes(':')) {
                    sectionTable = cell.closest('table');
                    break;
                }
            }
            
            if (!sectionTable) {
                // Fallback: search entire page for the subfield label
                sectionTable = document;
            }
            
            // Now find the specific subfield within this section
            const sectionCells = sectionTable.querySelectorAll('td');
            for (const cell of sectionCells) {
                if (cell.textContent.includes(subField)) {
                    const row = cell.closest('tr');
                    if (row) {
                        const inputs = row.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="button"]):not([readonly])');
                        // Find the input that's closest to the label cell (in the same visual column)
                        const cellRect = cell.getBoundingClientRect();
                        let bestInput = null;
                        let bestDistance = Infinity;
                        
                        for (const input of inputs) {
                            if (input.offsetParent !== null) {
                                const inputRect = input.getBoundingClientRect();
                                // Check if input is to the right of the label and close to it
                                if (inputRect.left >= cellRect.left - 100) {
                                    const distance = Math.abs(inputRect.left - cellRect.right);
                                    if (distance < bestDistance) {
                                        bestDistance = distance;
                                        bestInput = input;
                                    }
                                }
                            }
                        }
                        
                        if (bestInput) {
                            bestInput.value = value;
                            bestInput.dispatchEvent(new Event('input', { bubbles: true }));
                            bestInput.dispatchEvent(new Event('change', { bubbles: true }));
                            return { field: fieldName, success: true };
                        }
                    }
                }
            }
            return { field: fieldName, success: false };
        }
        
        // Summary Detail - Trader Reference (right side of summary table)
        if (headerData.trader_reference) {
            const traderRefCells = document.querySelectorAll('td');
            for (const cell of traderRefCells) {
                if (cell.textContent.includes('Trader Reference:')) {
                    // The input should be in the same row, to the right
                    const row = cell.closest('tr');
                    if (row) {
                        const inputs = row.querySelectorAll('input:not([type="hidden"]):not([readonly])');
                        if (inputs.length > 0) {
                            inputs[0].value = headerData.trader_reference;
                            inputs[0].dispatchEvent(new Event('change', { bubbles: true }));
                            results.push({ field: 'Trader Reference', success: true });
                        }
                    }
                    break;
                }
            }
        }
        
        // Section 1 - SUPPLIER (left column)
        results.push(fillInSection(1, 'a. NAME:', headerData.supplier_name, 'Supplier Name'));
        results.push(fillInSection(1, 'b. STREET:', headerData.supplier_street, 'Supplier Street'));
        results.push(fillInSection(1, 'c. CITY', headerData.supplier_city, 'Supplier City'));
        results.push(fillInSection(1, 'd. ZIP', headerData.supplier_zip, 'Supplier ZIP'));
        results.push(fillInSection(1, 'e. COUNTRY:', headerData.supplier_country, 'Supplier Country'));
        
        // Section 3 - TRANSPORT DETAILS
        results.push(fillInSection(3, 'a. CARRIER ID', headerData.carrier_id, 'Carrier ID'));
        results.push(fillInSection(3, 'b. PORT OF ARRIVAL:', headerData.port_of_arrival, 'Port of Arrival'));
        results.push(fillInSection(3, 'c. ARRIVAL DATE:', headerData.arrival_date, 'Arrival Date'));
        
        // Section 4 - MANIFEST
        results.push(fillInSection(4, 'a. NO. OF PACKAGES:', headerData.packages_count, 'Number of Packages'));
        results.push(fillInSection(4, 'b. BILL OF LADING', headerData.bill_of_lading, 'Bill of Lading'));
        results.push(fillInSection(4, 'c. CONTAINER ID', headerData.container_id, 'Container ID'));
        
        // Section 5 - Shipment Details (right column)
        results.push(fillInSection(5, 'a. CITY OF DIR', headerData.city_of_shipment, 'City of Direct Shipment'));
        results.push(fillInSection(5, 'b. COUNTRY OF DIR', headerData.country_of_direct_shipment, 'Country of Direct Shipment'));
        results.push(fillInSection(5, 'c. COUNTRY OF ORIG', headerData.country_of_origin_shipment, 'Country of Original Shipment'));
        
        // Section 8 - TOTAL FREIGHT (right column)
        if (headerData.total_freight) {
            const cells = document.querySelectorAll('td');
            for (const cell of cells) {
                if (cell.textContent.includes('TOTAL FREIGHT:') && cell.textContent.includes('8')) {
                    const row = cell.closest('tr');
                    if (row) {
                        const inputs = row.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([readonly])');
                        for (const input of inputs) {
                            if (input.offsetParent !== null) {
                                input.value = headerData.total_freight;
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                                results.push({ field: 'Total Freight', success: true });
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }
        
        // Section 9 - TOTAL INSURANCE (right column)
        if (headerData.total_insurance) {
            const cells = document.querySelectorAll('td');
            for (const cell of cells) {
                if (cell.textContent.includes('TOTAL INSURANCE:') && cell.textContent.includes('9')) {
                    const row = cell.closest('tr');
                    if (row) {
                        const inputs = row.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([readonly])');
                        for (const input of inputs) {
                            if (input.offsetParent !== null) {
                                input.value = headerData.total_insurance;
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                                results.push({ field: 'Total Insurance', success: true });
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }
        
        return results.filter(r => r !== null);
    }, data);
    
    // Log results
    for (const r of headerResult) {
        if (r.success) {
            log(`Filled ${r.field}`);
        } else {
            log(`Could not fill ${r.field}`, 'warn');
        }
    }
    
    // Handle prorated checkboxes
    if (data.freight_prorated) {
        await page.evaluate(() => {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            for (const cb of checkboxes) {
                const row = cb.closest('tr');
                if (row && row.textContent.includes('FREIGHT') && row.textContent.includes('Prorated')) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                    break;
                }
            }
        });
    }
    if (data.insurance_prorated) {
        await page.evaluate(() => {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            for (const cb of checkboxes) {
                const row = cb.closest('tr');
                if (row && row.textContent.includes('INSURANCE') && row.textContent.includes('Prorated')) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                    break;
                }
            }
        });
    }
    
    await takeScreenshot(page, '04-header-filled');
    log('Header section filled');
}

/**
 * Fill a single item record using nth-of-type selectors
 * CAPS form uses complex table structures, so we select fields by their position
 */
async function fillItemRecord(page, item, recordIndex) {
    log(`Filling item record #${recordIndex + 1}...`);
    
    // For multi-item forms, recordIndex determines which record section we're in
    // First record is always present, additional records are added via "Add Record" button
    const recNum = recordIndex + 1;
    
    // Scroll to the record section first
    await page.evaluate((num) => {
        const recordHeader = document.evaluate(
            `//td[contains(text(), 'RECORD #000${num}')]`,
            document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null
        ).singleNodeValue;
        if (recordHeader) {
            recordHeader.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, recNum);
    await page.waitForTimeout(500);
    
    // CPC (Section 12)
    if (item.cpc) {
        await fillFieldByLabel(page, 'CPC:', item.cpc, `Item ${recNum} CPC`, recNum);
    }
    
    // Tariff Number (Section 13)
    if (item.tariff_number) {
        await fillFieldByLabel(page, 'TARIFF NO.:', item.tariff_number, `Item ${recNum} Tariff`, recNum);
    }
    
    // Country of Origin (Section 14)
    if (item.country_of_origin) {
        await fillFieldByLabel(page, 'COUNTRY OF ORIGIN:', item.country_of_origin, `Item ${recNum} Country of Origin`, recNum);
    }
    
    // Number of Packages (Section 15) - first input after label
    if (item.packages_number) {
        await fillFieldByLabel(page, 'NO. AND TYPE OF PACKAGES:', item.packages_number, `Item ${recNum} No. of Packages`, recNum, 0);
    }
    // Type of Packages - second input after label
    if (item.packages_type) {
        await fillFieldByLabel(page, 'NO. AND TYPE OF PACKAGES:', item.packages_type, `Item ${recNum} Package Type`, recNum, 1);
    }
    
    // Description (Section 16)
    if (item.description) {
        await fillFieldByLabel(page, 'DESCRIPTION:', item.description, `Item ${recNum} Description`, recNum);
    }
    
    // Net Weight (Section 17a)
    if (item.net_weight) {
        await fillFieldByLabel(page, 'NET WEIGHT', item.net_weight, `Item ${recNum} Net Weight`, recNum);
    }
    
    // Quantity (Section 17b) - first input
    if (item.quantity) {
        await fillFieldByLabel(page, 'QUANTITY / UNITS:', item.quantity, `Item ${recNum} Quantity`, recNum, 0);
    }
    // Units - second input
    if (item.units) {
        await fillFieldByLabel(page, 'QUANTITY / UNITS:', item.units, `Item ${recNum} Units`, recNum, 1);
    }
    
    // FOB Value (Section 18)
    if (item.fob_value) {
        await fillFieldByLabel(page, 'F.O.B. VALUE:', item.fob_value, `Item ${recNum} FOB Value`, recNum);
    }
    
    // Currency (Section 19) - Try direct value set for readonly field
    // Must be in the item record section (RECORD #00XX), not header
    if (item.currency) {
        const currencyFilled = await page.evaluate((args) => {
            const { currency, recNum } = args;
            
            // First, find the RECORD section for this item
            const recordHeaders = document.querySelectorAll('td');
            let recordSection = null;
            
            for (const header of recordHeaders) {
                // Match "RECORD #0001" or similar
                if (header.textContent.includes(`RECORD #000${recNum}`) || 
                    header.textContent.includes('RECORD #00')) {
                    recordSection = header.closest('table');
                    break;
                }
            }
            
            if (!recordSection) {
                // Fallback: look in the entire document but prioritize section 19
                recordSection = document;
            }
            
            // Now find CURRENCY within that section
            const cells = recordSection.querySelectorAll('td');
            for (const cell of cells) {
                // Must match "a. CURRENCY:" or "19 a. CURRENCY:" specifically
                if (cell.textContent.match(/\bCURRENCY:/i) && 
                    !cell.textContent.includes('Declarant')) {
                    const row = cell.closest('tr');
                    if (row) {
                        // Find the small readonly input (usually 3 chars for currency codes)
                        const inputs = row.querySelectorAll('input[readonly]');
                        for (const input of inputs) {
                            // Currency inputs are usually size="3" or maxlength="3"
                            if (input.size <= 5 || input.maxLength <= 5) {
                                input.removeAttribute('readonly');
                                input.value = currency;
                                input.setAttribute('readonly', 'readonly');
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                                return true;
                            }
                        }
                    }
                }
            }
            return false;
        }, { currency: item.currency, recNum });
        
        if (currencyFilled) {
            log(`Set Currency directly: ${item.currency}`);
        } else {
            log(`Could not set Currency field for item ${recNum}`, 'warn');
        }
    }
    
    // Charges/Deductions (Section 20) - these are in rows
    // Freight code and amount
    if (item.freight_code || item.freight_amount) {
        await fillChargeRow(page, recNum, 0, item.freight_code, item.freight_amount);
    }
    
    // Insurance code and amount  
    if (item.insurance_code || item.insurance_amount) {
        await fillChargeRow(page, recNum, 1, item.insurance_code, item.insurance_amount);
    }
    
    // Tax Types (Section 22) - in rows
    if (item.tax_type_1) {
        await fillTaxRow(page, recNum, 0, item.tax_type_1);
    }
    if (item.tax_type_2) {
        await fillTaxRow(page, recNum, 1, item.tax_type_2);
    }
    
    log(`Item record #${recNum} filled`);
}

/**
 * Fill field by finding label text within a specific record section
 * recordNum: 1 = first record (RECORD #0001), 2 = second record (RECORD #0002), etc.
 */
async function fillFieldByLabel(page, labelText, value, fieldName, recordNum = 1, inputIndex = 0) {
    if (value === null || value === undefined || value === '') {
        return false;
    }
    
    // Truncate value if too long (CAPS has field length limits)
    const maxLengths = {
        'DESCRIPTION': 200,
        'CPC': 10,
        'TARIFF': 15,
        'COUNTRY': 3,
        'CURRENCY': 3,
        'UNITS': 5,
        'default': 50
    };
    
    let maxLen = maxLengths.default;
    for (const [key, len] of Object.entries(maxLengths)) {
        if (fieldName.toUpperCase().includes(key)) {
            maxLen = len;
            break;
        }
    }
    
    const truncatedValue = String(value).substring(0, maxLen);
    
    try {
        const filled = await page.evaluate((args) => {
            const { labelText, value, inputIndex, recordNum } = args;
            
            // First, find the record section for this item
            // CAPS uses "11 RECORD #0001" format for the header
            const recordPattern = recordNum.toString().padStart(4, '0');
            let recordSection = null;
            
            // Find all potential record headers
            const allCells = document.querySelectorAll('td');
            for (const cell of allCells) {
                if (cell.textContent.includes(`RECORD #${recordPattern}`)) {
                    // Found the record header - get its containing table
                    recordSection = cell.closest('table');
                    // Walk up to find a larger container that includes all the record's fields
                    if (recordSection) {
                        // The record section typically spans a large part of the page
                        // Look for the next RECORD header to determine bounds
                        break;
                    }
                }
            }
            
            // Determine search scope
            const searchScope = recordSection || document;
            
            // Find all cells containing this label within the scope
            const cells = searchScope.querySelectorAll('td');
            let foundCount = 0;
            
            for (const cell of cells) {
                if (cell.textContent.includes(labelText)) {
                    // For multi-record forms, we need to find the Nth occurrence
                    // where N corresponds to our record number
                    foundCount++;
                    
                    // If we have a record section, use the first match within it
                    // Otherwise, use the Nth match globally
                    if (recordSection || foundCount === recordNum) {
                        const row = cell.closest('tr');
                        if (row) {
                            const inputs = row.querySelectorAll('input:not([type="hidden"]):not([disabled]):not([readonly]), textarea');
                            if (inputs.length > inputIndex) {
                                const input = inputs[inputIndex];
                                input.value = value;
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                                return { success: true, recordSection: !!recordSection };
                            }
                        }
                    }
                }
            }
            return { success: false, recordSection: !!recordSection };
        }, { labelText, value: truncatedValue, inputIndex, recordNum });
        
        if (filled.success) {
            log(`Filled ${fieldName}: ${truncatedValue}`);
            return true;
        }
    } catch (e) {
        log(`Error filling ${fieldName}: ${e.message}`, 'debug');
    }
    
    log(`Could not find field ${fieldName} by label "${labelText}"`, 'warn');
    result.warnings.push(`Field not found: ${fieldName}`);
    return false;
}

/**
 * Fill a readonly field via Lookup popup
 * CAPS Lookup fields work by:
 * 1. Clicking "Lookup" link next to the field
 * 2. A popup window opens with a list
 * 3. Search/select the value
 * 4. Popup closes and fills the field
 */
async function fillLookupField(page, labelText, value, fieldName) {
    if (!value) return false;
    
    try {
        log(`Opening Lookup for ${fieldName}...`);
        
        // Find the row with this label and click its Lookup link
        const lookupClicked = await page.evaluate((args) => {
            const { labelText, value } = args;
            const cells = document.querySelectorAll('td');
            
            for (const cell of cells) {
                if (cell.textContent.includes(labelText)) {
                    const row = cell.closest('tr');
                    if (row) {
                        // Find the Lookup link in this row
                        const lookupLink = row.querySelector('a[href*="javascript"]');
                        if (lookupLink && lookupLink.textContent.includes('Lookup')) {
                            lookupLink.click();
                            return true;
                        }
                    }
                }
            }
            return false;
        }, { labelText, value });
        
        if (!lookupClicked) {
            log(`Could not find Lookup link for ${fieldName}`, 'warn');
            return false;
        }
        
        // Wait for popup to appear
        await page.waitForTimeout(1000);
        
        // Handle the popup - CAPS uses window.open() popups
        // We need to listen for new pages
        const [popup] = await Promise.race([
            Promise.all([page.waitForEvent('popup', { timeout: 5000 })]),
            new Promise(resolve => setTimeout(() => resolve([null]), 5000))
        ]);
        
        if (popup) {
            // New popup window opened
            await popup.waitForLoadState('domcontentloaded');
            
            // Look for search field or list in popup
            const searchField = await popup.$('input[type="text"]');
            if (searchField) {
                await searchField.fill(value);
                await popup.waitForTimeout(500);
            }
            
            // Try to find and click the matching option
            const optionClicked = await popup.evaluate((searchValue) => {
                // Look for links or rows containing the value
                const links = document.querySelectorAll('a, td');
                for (const el of links) {
                    if (el.textContent.includes(searchValue)) {
                        el.click();
                        return true;
                    }
                }
                // Try clicking submit/select button
                const buttons = document.querySelectorAll('input[type="submit"], button');
                for (const btn of buttons) {
                    if (btn.value?.toLowerCase().includes('select') || 
                        btn.textContent?.toLowerCase().includes('select')) {
                        btn.click();
                        return true;
                    }
                }
                return false;
            }, value);
            
            await page.waitForTimeout(1000);
            log(`Selected ${value} from Lookup popup for ${fieldName}`);
            return true;
        } else {
            // No popup - might be inline dropdown or different mechanism
            // Try selecting from any dropdown that appeared
            log(`No popup detected for ${fieldName}, trying inline selection`, 'debug');
            return false;
        }
        
    } catch (e) {
        log(`Error handling Lookup for ${fieldName}: ${e.message}`, 'warn');
        return false;
    }
}

/**
 * Fill a charges/deductions row
 * Charge codes (FRT, INS) use Lookup popups, amounts are direct input
 */
async function fillChargeRow(page, recordNum, rowIndex, code, amount) {
    try {
        // First fill the amount field (this is usually direct input)
        if (amount) {
            const amountFilled = await page.evaluate((args) => {
                const { rowIndex, amount } = args;
                
                // Find the CHARGES / DEDUCTIONS section
                const headers = document.querySelectorAll('td');
                for (const header of headers) {
                    if (header.textContent.includes('CHARGES / DEDUCTIONS') && 
                        header.textContent.includes('AMOUNT')) {
                        const table = header.closest('table');
                        if (table) {
                            const rows = table.querySelectorAll('tr');
                            let chargeRowCount = 0;
                            for (const row of rows) {
                                const inputs = row.querySelectorAll('input:not([type="hidden"]):not([readonly])');
                                const hasLookup = row.textContent.includes('Lookup');
                                
                                if (inputs.length >= 1 && hasLookup) {
                                    if (chargeRowCount === rowIndex) {
                                        // Amount is usually the last editable input in the row
                                        const amountInput = inputs[inputs.length - 1];
                                        if (amountInput) {
                                            amountInput.value = amount;
                                            amountInput.dispatchEvent(new Event('input', { bubbles: true }));
                                            amountInput.dispatchEvent(new Event('change', { bubbles: true }));
                                            return true;
                                        }
                                    }
                                    chargeRowCount++;
                                }
                            }
                        }
                    }
                }
                return false;
            }, { rowIndex, amount });
            
            if (amountFilled) {
                log(`Filled charge amount row ${rowIndex + 1}: ${amount}`);
            }
        }
        
        // For charge codes like FRT/INS, we may need to use Lookup
        // For now, try direct fill first
        if (code) {
            const codeFilled = await page.evaluate((args) => {
                const { rowIndex, code } = args;
                
                const headers = document.querySelectorAll('td');
                for (const header of headers) {
                    if (header.textContent.includes('CHARGES / DEDUCTIONS')) {
                        const table = header.closest('table');
                        if (table) {
                            const rows = table.querySelectorAll('tr');
                            let chargeRowCount = 0;
                            for (const row of rows) {
                                const hasLookup = row.textContent.includes('Lookup');
                                const inputs = row.querySelectorAll('input:not([type="hidden"])');
                                
                                if (inputs.length >= 1 && hasLookup) {
                                    if (chargeRowCount === rowIndex) {
                                        // Code is usually the first input
                                        const codeInput = inputs[0];
                                        if (codeInput && !codeInput.readOnly) {
                                            codeInput.value = code;
                                            codeInput.dispatchEvent(new Event('input', { bubbles: true }));
                                            codeInput.dispatchEvent(new Event('change', { bubbles: true }));
                                            return true;
                                        }
                                    }
                                    chargeRowCount++;
                                }
                            }
                        }
                    }
                }
                return false;
            }, { rowIndex, code });
            
            if (codeFilled) {
                log(`Filled charge code row ${rowIndex + 1}: ${code}`);
            } else {
                log(`Charge code field ${rowIndex + 1} may be readonly - use Lookup`, 'debug');
            }
        }
        
    } catch (e) {
        log(`Could not fill charge row ${rowIndex + 1}: ${e.message}`, 'warn');
    }
}

/**
 * Fill a tax type row
 */
async function fillTaxRow(page, recordNum, rowIndex, taxType) {
    try {
        await page.evaluate((args) => {
            const { rowIndex, taxType } = args;
            
            // Find the TAX TYPE header
            const headers = document.querySelectorAll('td');
            for (const header of headers) {
                if (header.textContent.includes('TAX TYPE') && header.textContent.includes('EXEMPT')) {
                    const table = header.closest('table');
                    if (table) {
                        const rows = table.querySelectorAll('tr');
                        let taxRowCount = 0;
                        for (const row of rows) {
                            const inputs = row.querySelectorAll('input:not([type="hidden"])');
                            // Tax rows have multiple inputs (type, exempt, value, rate, amount)
                            if (inputs.length >= 4) {
                                if (taxRowCount === rowIndex) {
                                    inputs[0].value = taxType;
                                    inputs[0].dispatchEvent(new Event('input', { bubbles: true }));
                                    return true;
                                }
                                taxRowCount++;
                            }
                        }
                    }
                }
            }
            return false;
        }, { rowIndex, taxType });
        
        log(`Filled tax type row ${rowIndex + 1}: ${taxType}`);
    } catch (e) {
        log(`Could not fill tax row ${rowIndex + 1}`, 'warn');
    }
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
        // Try finding by text content
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
    
    // Click the button
    await addButton.click();
    
    // Wait for the page to process and new record section to appear
    // CAPS may reload the page or dynamically add the new section
    await page.waitForTimeout(2000);
    
    // Wait for the new record section to be visible
    const nextRecordNum = (currentRecordCount + 1).toString().padStart(4, '0');
    try {
        await page.waitForFunction((recordNum) => {
            const cells = document.querySelectorAll('td');
            for (const cell of cells) {
                if (cell.textContent.includes(`RECORD #${recordNum}`)) {
                    return true;
                }
            }
            return false;
        }, nextRecordNum, { timeout: 10000 });
        log(`New item record #${currentRecordCount + 1} added`);
    } catch (e) {
        log(`Warning: Could not confirm new record section appeared`, 'warn');
        // Continue anyway - the record might have been added differently
    }
    
    // Scroll to the new record section
    await page.evaluate((recordNum) => {
        const cells = document.querySelectorAll('td');
        for (const cell of cells) {
            if (cell.textContent.includes(`RECORD #${recordNum}`)) {
                cell.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            }
        }
    }, nextRecordNum);
    
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
        // For items beyond the first, need to click "Add Record"
        if (i > 0) {
            await addNewRecord(page, i); // Pass current count (0-indexed, so i = number of records already added)
        }
        
        await fillItemRecord(page, items[i], i);
        await takeScreenshot(page, `05-item-${i + 1}-filled`);
    }
    
    log(`All ${items.length} item(s) filled`);
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
    
    await saveButton.click();
    
    // Wait for save to complete
    await page.waitForLoadState('networkidle', { timeout: config.timeout });
    await page.waitForTimeout(2000);
    
    // Check for success/error messages
    const pageText = await page.textContent('body');
    if (pageText.toLowerCase().includes('error') && !pageText.toLowerCase().includes('no error')) {
        result.warnings.push('Page may contain errors after save');
    }
    
    await takeScreenshot(page, '06-saved');
    log('TD saved successfully');
    return true;
}

/**
 * Validate the TD
 */
async function validateTD(page) {
    log('Validating TD...');
    
    // Scroll to bottom where buttons are
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
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
    
    await validateButton.click();
    
    // Wait for validation to complete
    await page.waitForLoadState('networkidle', { timeout: config.timeout });
    await page.waitForTimeout(2000);
    
    // Check for validation errors
    const pageText = await page.textContent('body');
    const hasErrors = pageText.toLowerCase().includes('error') && 
                      !pageText.toLowerCase().includes('no error');
    
    if (hasErrors) {
        log('Validation found errors on the form', 'warn');
        result.warnings.push('Validation errors detected');
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
 * Main submission flow
 */
async function runSubmission(browser) {
    const context = await browser.newContext();
    const page = await context.newPage();
    
    setupDialogHandler(page);
    
    try {
        // Step 1: Login
        await login(page);
        
        // Step 2: Create new TD
        await createNewTD(page);
        
        // Step 3: Fill header section
        await fillHeaderSection(page, config.headerData);
        
        // Step 4: Fill item records
        await fillItems(page, config.items);
        
        // Step 5: Save and optionally validate based on action
        if (config.action === 'submit' || config.action === 'save' || config.action === 'validate') {
            await saveTD(page);
            
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
