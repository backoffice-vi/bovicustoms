/**
 * Playwright Web Form Submitter
 * 
 * This script demonstrates automated form submission using Playwright.
 * It can be called from Laravel via a shell command with JSON input.
 * 
 * Usage:
 *   node playwright/web-form-submitter.mjs '{"action":"test","baseUrl":"http://127.0.0.1:8010"}'
 *   node playwright/web-form-submitter.mjs '{"action":"submit","baseUrl":"http://127.0.0.1:8010","data":{...}}'
 */

import { chromium } from 'playwright-core';
import { existsSync, readFileSync } from 'fs';
import { join } from 'path';

// Parse command line input - support both direct JSON and --input-file
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

if (Object.keys(input).length === 0 && args.length > 0 && !args[0].startsWith('--')) {
    // Try parsing the first arg as JSON
    try {
        input = JSON.parse(args[0]);
    } catch (e) {
        // Ignore parsing errors, use defaults
    }
}
const action = input.action || 'test';
const baseUrl = input.baseUrl || 'http://127.0.0.1:8010';
const credentials = input.credentials || { username: 'testuser', password: 'testpass123' };
const formData = input.data || null;
const headless = input.headless !== false; // Default to headless
const screenshotDir = input.screenshotDir || './storage/app/playwright-screenshots';

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
        if (existsSync(p)) {
            return p;
        }
    }
    return null;
}

// Result object to return to Laravel
const result = {
    success: false,
    action: action,
    message: '',
    reference_number: null,
    screenshots: [],
    logs: [],
    error: null,
};

function log(message) {
    const timestamp = new Date().toISOString();
    const entry = `[${timestamp}] ${message}`;
    result.logs.push(entry);
    console.error(entry); // Log to stderr so stdout stays clean for JSON
}

async function takeScreenshot(page, name) {
    try {
        const filename = `${Date.now()}-${name}.png`;
        const filepath = join(screenshotDir, filename);
        await page.screenshot({ path: filepath, fullPage: true });
        result.screenshots.push(filepath);
        log(`Screenshot saved: ${filename}`);
    } catch (e) {
        log(`Failed to save screenshot: ${e.message}`);
    }
}

async function login(page) {
    log('Navigating to login page...');
    await page.goto(`${baseUrl}/test/external-form`);
    
    // Check if already logged in
    const loginForm = await page.$('input[name="username"]');
    if (!loginForm) {
        log('Already logged in or login form not found');
        return true;
    }
    
    log('Filling login credentials...');
    await page.fill('input[name="username"]', credentials.username);
    await page.fill('input[name="password"]', credentials.password);
    
    await takeScreenshot(page, '01-login-filled');
    
    log('Submitting login...');
    await page.click('button[data-testid="login-btn"]');
    
    // Wait for navigation
    await page.waitForTimeout(1000);
    
    // Check if login was successful
    const errorMessage = await page.$('.alert-error');
    if (errorMessage) {
        const errorText = await errorMessage.textContent();
        throw new Error(`Login failed: ${errorText}`);
    }
    
    log('Login successful!');
    await takeScreenshot(page, '02-logged-in');
    return true;
}

async function fillDeclarationForm(page, data) {
    log('Filling declaration form...');
    
    // Shipment Information
    if (data.vessel_name) {
        await page.fill('input[data-testid="vessel_name"]', data.vessel_name);
    }
    if (data.voyage_number) {
        await page.fill('input[data-testid="voyage_number"]', data.voyage_number);
    }
    if (data.bill_of_lading) {
        await page.fill('input[data-testid="bill_of_lading"]', data.bill_of_lading);
    }
    if (data.manifest_number) {
        await page.fill('input[data-testid="manifest_number"]', data.manifest_number);
    }
    if (data.port_of_loading) {
        await page.selectOption('select[data-testid="port_of_loading"]', data.port_of_loading);
    }
    if (data.arrival_date) {
        await page.fill('input[data-testid="arrival_date"]', data.arrival_date);
    }
    
    // Shipper Information
    if (data.shipper_name) {
        await page.fill('input[data-testid="shipper_name"]', data.shipper_name);
    }
    if (data.shipper_country) {
        await page.selectOption('select[data-testid="shipper_country"]', data.shipper_country);
    }
    if (data.shipper_address) {
        await page.fill('textarea[data-testid="shipper_address"]', data.shipper_address);
    }
    
    // Consignee Information
    if (data.consignee_name) {
        await page.fill('input[data-testid="consignee_name"]', data.consignee_name);
    }
    if (data.consignee_id) {
        await page.fill('input[data-testid="consignee_id"]', data.consignee_id);
    }
    
    // Goods Information
    if (data.hs_code) {
        await page.fill('input[data-testid="hs_code"]', data.hs_code);
    }
    if (data.country_of_origin) {
        await page.selectOption('select[data-testid="country_of_origin"]', data.country_of_origin);
    }
    if (data.goods_description) {
        await page.fill('textarea[data-testid="goods_description"]', data.goods_description);
    }
    if (data.quantity) {
        await page.fill('input[data-testid="quantity"]', String(data.quantity));
    }
    if (data.gross_weight) {
        await page.fill('input[data-testid="gross_weight"]', String(data.gross_weight));
    }
    if (data.total_packages) {
        await page.fill('input[data-testid="total_packages"]', String(data.total_packages));
    }
    
    // Values
    if (data.fob_value) {
        await page.fill('input[data-testid="fob_value"]', String(data.fob_value));
    }
    if (data.freight_value) {
        await page.fill('input[data-testid="freight_value"]', String(data.freight_value));
    }
    if (data.insurance_value !== undefined) {
        await page.fill('input[data-testid="insurance_value"]', String(data.insurance_value));
    }
    
    log('Form filled successfully');
    await takeScreenshot(page, '03-form-filled');
}

async function submitForm(page) {
    log('Submitting declaration form...');
    
    await page.click('button[data-testid="submit-btn"]');
    
    // Wait for result page
    await page.waitForTimeout(2000);
    
    // Check for success
    const referenceElement = await page.$('[data-testid="reference-number"]');
    if (referenceElement) {
        const referenceNumber = await referenceElement.textContent();
        result.reference_number = referenceNumber.trim();
        log(`Submission successful! Reference: ${result.reference_number}`);
        await takeScreenshot(page, '04-submission-success');
        return true;
    }
    
    // Check for error
    const errorElement = await page.$('.alert-error');
    if (errorElement) {
        const errorText = await errorElement.textContent();
        throw new Error(`Submission failed: ${errorText}`);
    }
    
    throw new Error('Unknown submission result');
}

async function runTest(browser) {
    log('Running connection test...');
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        // Test 1: Can we reach the server?
        log('Test 1: Checking server connectivity...');
        const response = await page.goto(`${baseUrl}/test/external-form`);
        if (!response.ok()) {
            throw new Error(`Server returned ${response.status()}`);
        }
        log('Server is reachable');
        
        // Test 2: Can we login?
        log('Test 2: Testing login flow...');
        await login(page);
        
        // Test 3: Is the form visible?
        log('Test 3: Checking form availability...');
        const vesselField = await page.$('input[data-testid="vessel_name"]');
        if (!vesselField) {
            throw new Error('Declaration form not found after login');
        }
        log('Form is available');
        
        result.success = true;
        result.message = 'All tests passed! Playwright automation is working correctly.';
        
    } finally {
        await context.close();
    }
}

async function runSubmission(browser) {
    if (!formData) {
        throw new Error('No form data provided for submission');
    }
    
    log('Running form submission...');
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        // Login
        await login(page);
        
        // Fill form
        await fillDeclarationForm(page, formData);
        
        // Submit
        await submitForm(page);
        
        result.success = true;
        result.message = `Declaration submitted successfully. Reference: ${result.reference_number}`;
        
    } finally {
        await context.close();
    }
}

async function main() {
    const chromePath = findChrome();
    if (!chromePath) {
        result.error = 'Chrome executable not found. Please install Chrome or specify the path.';
        console.log(JSON.stringify(result, null, 2));
        process.exit(1);
    }
    
    log(`Using Chrome at: ${chromePath}`);
    log(`Action: ${action}`);
    log(`Base URL: ${baseUrl}`);
    log(`Headless: ${headless}`);
    
    let browser;
    try {
        browser = await chromium.launch({
            headless: headless,
            executablePath: chromePath,
        });
        
        if (action === 'test') {
            await runTest(browser);
        } else if (action === 'submit') {
            await runSubmission(browser);
        } else {
            throw new Error(`Unknown action: ${action}`);
        }
        
    } catch (error) {
        result.success = false;
        result.error = error.message;
        log(`Error: ${error.message}`);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
    
    // Output result as JSON to stdout
    console.log(JSON.stringify(result, null, 2));
    process.exit(result.success ? 0 : 1);
}

main();
