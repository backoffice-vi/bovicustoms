/**
 * AI-Assisted Web Form Submitter
 * 
 * Uses Claude AI to intelligently handle errors, interpret page states,
 * and make decisions during automated form submission.
 * 
 * Usage:
 *   node playwright/ai-web-form-submitter.mjs --input-file="path/to/input.json"
 */

import { chromium } from 'playwright-core';
import { existsSync, readFileSync } from 'fs';
import { join } from 'path';

// Parse command line input
let input = {};
const args = process.argv.slice(2);

for (const arg of args) {
    if (arg.startsWith('--input-file=')) {
        const filePath = arg.substring('--input-file='.length);
        const fileContent = readFileSync(filePath, 'utf-8');
        input = JSON.parse(fileContent);
        break;
    }
}

const action = input.action || 'submit';
const baseUrl = input.baseUrl || 'http://127.0.0.1:8010';
const credentials = input.credentials || {};
const formData = input.data || {};
const fieldMappings = input.fieldMappings || null; // Stored mappings from database
const headless = input.headless !== false;
const screenshotDir = input.screenshotDir || './storage/app/playwright-screenshots';
const claudeApiKey = input.claudeApiKey || process.env.CLAUDE_API_KEY || process.env.ANTHROPIC_API_KEY;
const maxRetries = input.maxRetries || 3;

// Result object
const result = {
    success: false,
    action: action,
    message: '',
    reference_number: null,
    screenshots: [],
    logs: [],
    ai_decisions: [],
    errors_handled: [],
    error: null,
};

function log(message) {
    const timestamp = new Date().toISOString();
    const entry = `[${timestamp}] ${message}`;
    result.logs.push(entry);
    console.error(entry);
}

function logAiDecision(situation, decision, reasoning) {
    result.ai_decisions.push({ situation, decision, reasoning, timestamp: new Date().toISOString() });
    log(`🤖 AI Decision: ${decision}`);
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
        const filename = `${Date.now()}-${name}.png`;
        const filepath = join(screenshotDir, filename);
        await page.screenshot({ path: filepath, fullPage: true });
        result.screenshots.push(filepath);
        log(`Screenshot saved: ${filename}`);
        return filepath;
    } catch (e) {
        log(`Failed to save screenshot: ${e.message}`);
        return null;
    }
}

/**
 * Call Claude AI for assistance
 */
async function askClaude(prompt, context = {}) {
    if (!claudeApiKey) {
        log('⚠️ No Claude API key - skipping AI assistance');
        return null;
    }

    try {
        const response = await fetch('https://api.anthropic.com/v1/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'x-api-key': claudeApiKey,
                'anthropic-version': '2023-06-01',
            },
            body: JSON.stringify({
                model: 'claude-sonnet-4-20250514',
                max_tokens: 1024,
                messages: [{
                    role: 'user',
                    content: prompt,
                }],
            }),
        });

        if (!response.ok) {
            const error = await response.text();
            log(`Claude API error: ${error}`);
            return null;
        }

        const data = await response.json();
        return data.content[0].text;
    } catch (e) {
        log(`Claude API call failed: ${e.message}`);
        return null;
    }
}

/**
 * Get a simplified description of the current page state
 */
async function getPageState(page) {
    try {
        const state = await page.evaluate(() => {
            const getVisibleText = (el) => {
                if (!el) return '';
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden') return '';
                return el.innerText || el.textContent || '';
            };

            // Get page info
            const title = document.title;
            const url = window.location.href;
            
            // Get any error messages
            const errors = [];
            document.querySelectorAll('.error, .alert-error, .alert-danger, .validation-error, [class*="error"]').forEach(el => {
                const text = getVisibleText(el).trim();
                if (text && text.length < 500) errors.push(text);
            });

            // Get any success messages
            const successes = [];
            document.querySelectorAll('.success, .alert-success, [class*="success"]').forEach(el => {
                const text = getVisibleText(el).trim();
                if (text && text.length < 500) successes.push(text);
            });

            // Get visible form fields and their states
            const formFields = [];
            document.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.offsetParent !== null) { // Is visible
                    formFields.push({
                        type: el.type || el.tagName.toLowerCase(),
                        name: el.name || el.id,
                        value: el.value ? (el.value.length > 50 ? el.value.substring(0, 50) + '...' : el.value) : '',
                        required: el.required,
                        disabled: el.disabled,
                        validationMessage: el.validationMessage || '',
                    });
                }
            });

            // Get any dialogs/modals
            const dialogs = [];
            document.querySelectorAll('[role="dialog"], .modal, [class*="modal"], [class*="dialog"]').forEach(el => {
                if (el.offsetParent !== null) {
                    const text = getVisibleText(el).trim();
                    if (text) dialogs.push(text.substring(0, 500));
                }
            });

            // Get main content summary
            const mainContent = getVisibleText(document.body).substring(0, 1000);

            return { title, url, errors, successes, formFields, dialogs, mainContent };
        });
        return state;
    } catch (e) {
        return { error: e.message };
    }
}

/**
 * AI-assisted error handling
 */
async function handleErrorWithAI(page, error, context) {
    log(`🔍 Analyzing error with AI: ${error}`);
    
    const pageState = await getPageState(page);
    await takeScreenshot(page, 'error-state');

    const prompt = `You are helping with automated web form submission. An error occurred.

ERROR: ${error}

CURRENT PAGE STATE:
- URL: ${pageState.url}
- Title: ${pageState.title}
- Error messages on page: ${JSON.stringify(pageState.errors)}
- Success messages: ${JSON.stringify(pageState.successes)}
- Dialogs/modals visible: ${JSON.stringify(pageState.dialogs)}
- Form fields: ${JSON.stringify(pageState.formFields?.slice(0, 10))}

CONTEXT:
- Action attempted: ${context.action}
- Form data being submitted: ${JSON.stringify(context.formData ? Object.keys(context.formData) : [])}

Analyze the situation and respond with a JSON object:
{
    "diagnosis": "Brief explanation of what went wrong",
    "recoverable": true/false,
    "action": "One of: retry, wait_and_retry, fill_missing_field, click_button, dismiss_dialog, navigate, skip, abort",
    "action_details": { /* depends on action type */ },
    "reasoning": "Why this action"
}

For fill_missing_field: action_details = { "field_name": "...", "suggested_value": "..." }
For click_button: action_details = { "button_text": "..." }
For wait_and_retry: action_details = { "wait_seconds": N }
For navigate: action_details = { "url": "..." }

Respond ONLY with valid JSON.`;

    const aiResponse = await askClaude(prompt);
    
    if (!aiResponse) {
        return { recoverable: false, action: 'abort', reasoning: 'AI unavailable' };
    }

    try {
        // Extract JSON from response
        const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
            const decision = JSON.parse(jsonMatch[0]);
            logAiDecision(error, decision.action, decision.reasoning);
            result.errors_handled.push({ error, decision });
            return decision;
        }
    } catch (e) {
        log(`Failed to parse AI response: ${e.message}`);
    }

    return { recoverable: false, action: 'abort', reasoning: 'Could not parse AI response' };
}

/**
 * AI-assisted element finding when standard selectors fail
 */
async function findElementWithAI(page, description, fallbackSelectors = []) {
    // Try standard selectors first
    for (const selector of fallbackSelectors) {
        try {
            const element = await page.$(selector);
            if (element) {
                const isVisible = await element.isVisible();
                if (isVisible) return element;
            }
        } catch (e) {
            // Continue to next selector
        }
    }

    // Use AI to find the element
    log(`🔍 Using AI to find element: ${description}`);
    
    const pageState = await getPageState(page);
    
    const prompt = `I need to find an element on a web page for automated form filling.

ELEMENT I'M LOOKING FOR: ${description}

PAGE STATE:
- URL: ${pageState.url}
- Title: ${pageState.title}
- Visible form fields: ${JSON.stringify(pageState.formFields)}
- Main content preview: ${pageState.mainContent?.substring(0, 500)}

Based on the form fields available, which field name/ID should I target?

Respond with JSON:
{
    "found": true/false,
    "selector": "CSS selector or null",
    "field_name": "name attribute value or null", 
    "reasoning": "explanation"
}

Respond ONLY with valid JSON.`;

    const aiResponse = await askClaude(prompt);
    
    if (aiResponse) {
        try {
            const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
            if (jsonMatch) {
                const suggestion = JSON.parse(jsonMatch[0]);
                if (suggestion.found && suggestion.selector) {
                    try {
                        const element = await page.$(suggestion.selector);
                        if (element) {
                            logAiDecision(`Find: ${description}`, `Use selector: ${suggestion.selector}`, suggestion.reasoning);
                            return element;
                        }
                    } catch (e) {
                        // Selector didn't work
                    }
                }
                if (suggestion.found && suggestion.field_name) {
                    try {
                        const element = await page.$(`[name="${suggestion.field_name}"]`) || 
                                       await page.$(`#${suggestion.field_name}`);
                        if (element) {
                            logAiDecision(`Find: ${description}`, `Use field: ${suggestion.field_name}`, suggestion.reasoning);
                            return element;
                        }
                    } catch (e) {
                        // Field name didn't work
                    }
                }
            }
        } catch (e) {
            log(`Failed to parse AI element suggestion: ${e.message}`);
        }
    }

    return null;
}

/**
 * Smart form filling with AI fallback
 */
async function smartFillField(page, fieldConfig, value) {
    const { name, selectors, type } = fieldConfig;
    
    // Try each selector
    for (const selector of selectors || []) {
        try {
            const element = await page.$(selector);
            if (element && await element.isVisible()) {
                if (type === 'select') {
                    await element.selectOption(value);
                } else {
                    await element.fill(String(value));
                }
                log(`✓ Filled ${name} using ${selector}`);
                return true;
            }
        } catch (e) {
            // Try next selector
        }
    }

    // Use AI to find the field
    const element = await findElementWithAI(page, name, selectors || []);
    if (element) {
        try {
            if (type === 'select') {
                await element.selectOption(value);
            } else {
                await element.fill(String(value));
            }
            log(`✓ Filled ${name} using AI-found element`);
            return true;
        } catch (e) {
            log(`Failed to fill AI-found element: ${e.message}`);
        }
    }

    log(`⚠️ Could not fill field: ${name}`);
    return false;
}

/**
 * AI-assisted validation of submission result
 */
async function validateSubmissionResult(page) {
    const pageState = await getPageState(page);
    
    // Quick checks first - look for specific success indicators
    
    // Check for reference number element (our test form uses data-testid)
    try {
        const refElement = await page.$('[data-testid="reference-number"]');
        if (refElement) {
            const refText = await refElement.textContent();
            if (refText && refText.trim()) {
                return { success: true, reference: refText.trim(), message: 'Submission successful' };
            }
        }
    } catch (e) {
        // Element not found, continue
    }
    
    // Check page content for success patterns
    const pageContent = pageState.mainContent || '';
    const successPatterns = [
        /submitted\s+successfully/i,
        /declaration\s+submitted/i,
        /thank\s+you/i,
        /confirmation/i,
        /reference[:\s]+([A-Z0-9-]+)/i,
    ];
    
    for (const pattern of successPatterns) {
        const match = pageContent.match(pattern);
        if (match) {
            // Try to extract reference number
            const refMatch = pageContent.match(/(?:reference|confirmation|number|TD-)[:\s]*([A-Z0-9-]+)/i);
            return { 
                success: true, 
                reference: refMatch ? refMatch[1] : null, 
                message: 'Submission appears successful' 
            };
        }
    }
    
    // Check for explicit success messages
    if (pageState.successes.length > 0) {
        // Look for reference number pattern
        const refPattern = /(?:reference|confirmation|number|id|#|TD-)[:\s]*([A-Z0-9-]+)/i;
        for (const msg of pageState.successes) {
            const match = msg.match(refPattern);
            if (match) {
                return { success: true, reference: match[1], message: msg };
            }
        }
        return { success: true, reference: null, message: pageState.successes[0] };
    }

    if (pageState.errors.length > 0) {
        return { success: false, errors: pageState.errors };
    }

    // Check URL for success indicators
    if (pageState.url && (pageState.url.includes('success') || pageState.url.includes('confirm'))) {
        return { success: true, reference: null, message: 'Redirected to success page' };
    }

    // Use AI to interpret ambiguous result (if available)
    log('🔍 Using AI to interpret submission result');
    
    const prompt = `Analyze this page state after a form submission attempt:

URL: ${pageState.url}
Title: ${pageState.title}
Error messages: ${JSON.stringify(pageState.errors)}
Success messages: ${JSON.stringify(pageState.successes)}
Dialogs: ${JSON.stringify(pageState.dialogs)}
Page content: ${pageState.mainContent?.substring(0, 800)}

Did the form submission succeed? Look for:
- Confirmation messages
- Reference/confirmation numbers
- Error messages
- Validation failures

Respond with JSON:
{
    "success": true/false,
    "confidence": 0.0-1.0,
    "reference_number": "extracted reference or null",
    "message": "human-readable summary",
    "reasoning": "why you think this"
}

Respond ONLY with valid JSON.`;

    const aiResponse = await askClaude(prompt);
    
    if (aiResponse) {
        try {
            const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
            if (jsonMatch) {
                const interpretation = JSON.parse(jsonMatch[0]);
                logAiDecision('Interpret result', 
                    interpretation.success ? 'SUCCESS' : 'FAILURE',
                    interpretation.reasoning);
                return interpretation;
            }
        } catch (e) {
            log(`Failed to parse AI interpretation: ${e.message}`);
        }
    }

    return { success: false, message: 'Could not determine submission result' };
}

/**
 * Execute form submission with AI assistance
 */
async function executeWithAI(page, steps) {
    for (let i = 0; i < steps.length; i++) {
        const step = steps[i];
        let retries = 0;
        let success = false;

        while (!success && retries < maxRetries) {
            try {
                log(`Step ${i + 1}/${steps.length}: ${step.description}`);
                await step.execute(page);
                success = true;
            } catch (error) {
                retries++;
                log(`Step failed (attempt ${retries}/${maxRetries}): ${error.message}`);
                
                const decision = await handleErrorWithAI(page, error.message, {
                    action: step.description,
                    formData: formData,
                });

                if (!decision.recoverable) {
                    throw new Error(`Unrecoverable error: ${decision.diagnosis || error.message}`);
                }

                // Execute recovery action
                switch (decision.action) {
                    case 'wait_and_retry':
                        const waitTime = decision.action_details?.wait_seconds || 2;
                        log(`Waiting ${waitTime}s before retry...`);
                        await page.waitForTimeout(waitTime * 1000);
                        break;
                    
                    case 'click_button':
                        const buttonText = decision.action_details?.button_text;
                        if (buttonText) {
                            log(`Clicking button: ${buttonText}`);
                            await page.click(`text="${buttonText}"`).catch(() => {});
                            await page.waitForTimeout(1000);
                        }
                        break;
                    
                    case 'dismiss_dialog':
                        log('Dismissing dialog...');
                        await page.keyboard.press('Escape');
                        await page.waitForTimeout(500);
                        break;
                    
                    case 'fill_missing_field':
                        const { field_name, suggested_value } = decision.action_details || {};
                        if (field_name && suggested_value) {
                            log(`Filling missing field: ${field_name} = ${suggested_value}`);
                            await page.fill(`[name="${field_name}"]`, suggested_value).catch(() => {});
                        }
                        break;
                    
                    case 'navigate':
                        const navUrl = decision.action_details?.url;
                        if (navUrl) {
                            log(`Navigating to: ${navUrl}`);
                            await page.goto(navUrl);
                        }
                        break;
                    
                    case 'skip':
                        log('Skipping step as advised by AI');
                        success = true;
                        break;
                    
                    case 'abort':
                        throw new Error(`AI advised abort: ${decision.diagnosis}`);
                    
                    default:
                        // Just retry
                        await page.waitForTimeout(1000);
                }
            }
        }

        if (!success) {
            throw new Error(`Step failed after ${maxRetries} retries: ${step.description}`);
        }
    }
}

/**
 * Main submission flow
 */
async function runSubmission(browser) {
    log('Starting AI-assisted form submission...');
    
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        // Define submission steps
        const steps = [
            {
                description: 'Navigate to login page',
                execute: async (p) => {
                    await p.goto(`${baseUrl}/test/external-form`);
                    await p.waitForLoadState('networkidle');
                }
            },
            {
                description: 'Login',
                execute: async (p) => {
                    // Check if already logged in
                    const loginField = await p.$('input[name="username"]');
                    if (!loginField) {
                        log('Already logged in');
                        return;
                    }
                    
                    await p.fill('input[name="username"]', credentials.username || 'testuser');
                    await p.fill('input[name="password"]', credentials.password || 'testpass123');
                    await takeScreenshot(p, '01-login');
                    await p.click('button[type="submit"]');
                    await p.waitForLoadState('networkidle');
                    
                    // Verify login success
                    const stillLoginPage = await p.$('input[name="username"]');
                    if (stillLoginPage) {
                        throw new Error('Login failed - still on login page');
                    }
                    await takeScreenshot(p, '02-logged-in');
                }
            },
            {
                description: 'Fill declaration form',
                execute: async (p) => {
                    // Define field mappings with multiple selector fallbacks
                    const fields = [
                        { name: 'Vessel Name', key: 'vessel_name', selectors: ['[data-testid="vessel_name"]', '#vessel_name', '[name="vessel_name"]'], type: 'text' },
                        { name: 'Voyage Number', key: 'voyage_number', selectors: ['[data-testid="voyage_number"]', '#voyage_number', '[name="voyage_number"]'], type: 'text' },
                        { name: 'Bill of Lading', key: 'bill_of_lading', selectors: ['[data-testid="bill_of_lading"]', '#bill_of_lading', '[name="bill_of_lading"]'], type: 'text' },
                        { name: 'Manifest Number', key: 'manifest_number', selectors: ['[data-testid="manifest_number"]', '#manifest_number', '[name="manifest_number"]'], type: 'text' },
                        { name: 'Port of Loading', key: 'port_of_loading', selectors: ['[data-testid="port_of_loading"]', '#port_of_loading', '[name="port_of_loading"]'], type: 'select' },
                        { name: 'Arrival Date', key: 'arrival_date', selectors: ['[data-testid="arrival_date"]', '#arrival_date', '[name="arrival_date"]'], type: 'text' },
                        { name: 'Shipper Name', key: 'shipper_name', selectors: ['[data-testid="shipper_name"]', '#shipper_name', '[name="shipper_name"]'], type: 'text' },
                        { name: 'Shipper Country', key: 'shipper_country', selectors: ['[data-testid="shipper_country"]', '#shipper_country', '[name="shipper_country"]'], type: 'select' },
                        { name: 'Shipper Address', key: 'shipper_address', selectors: ['[data-testid="shipper_address"]', '#shipper_address', '[name="shipper_address"]'], type: 'text' },
                        { name: 'Consignee Name', key: 'consignee_name', selectors: ['[data-testid="consignee_name"]', '#consignee_name', '[name="consignee_name"]'], type: 'text' },
                        { name: 'Consignee ID', key: 'consignee_id', selectors: ['[data-testid="consignee_id"]', '#consignee_id', '[name="consignee_id"]'], type: 'text' },
                        { name: 'HS Code', key: 'hs_code', selectors: ['[data-testid="hs_code"]', '#hs_code', '[name="hs_code"]'], type: 'text' },
                        { name: 'Country of Origin', key: 'country_of_origin', selectors: ['[data-testid="country_of_origin"]', '#country_of_origin', '[name="country_of_origin"]'], type: 'select' },
                        { name: 'Goods Description', key: 'goods_description', selectors: ['[data-testid="goods_description"]', '#goods_description', '[name="goods_description"]'], type: 'text' },
                        { name: 'Quantity', key: 'quantity', selectors: ['[data-testid="quantity"]', '#quantity', '[name="quantity"]'], type: 'text' },
                        { name: 'Gross Weight', key: 'gross_weight', selectors: ['[data-testid="gross_weight"]', '#gross_weight', '[name="gross_weight"]'], type: 'text' },
                        { name: 'Total Packages', key: 'total_packages', selectors: ['[data-testid="total_packages"]', '#total_packages', '[name="total_packages"]'], type: 'text' },
                        { name: 'FOB Value', key: 'fob_value', selectors: ['[data-testid="fob_value"]', '#fob_value', '[name="fob_value"]'], type: 'text' },
                        { name: 'Freight Value', key: 'freight_value', selectors: ['[data-testid="freight_value"]', '#freight_value', '[name="freight_value"]'], type: 'text' },
                        { name: 'Insurance Value', key: 'insurance_value', selectors: ['[data-testid="insurance_value"]', '#insurance_value', '[name="insurance_value"]'], type: 'text' },
                    ];

                    let filledCount = 0;
                    for (const field of fields) {
                        const value = formData[field.key];
                        if (value !== undefined && value !== null && value !== '') {
                            const filled = await smartFillField(p, field, value);
                            if (filled) filledCount++;
                        }
                    }

                    log(`Filled ${filledCount} fields`);
                    await takeScreenshot(p, '03-form-filled');
                }
            },
            {
                description: 'Submit form',
                execute: async (p) => {
                    // Find and click submit button
                    const submitButton = await p.$('button[data-testid="submit-btn"]') ||
                                        await p.$('button[type="submit"]') ||
                                        await p.$('input[type="submit"]') ||
                                        await findElementWithAI(p, 'Submit button', ['button:has-text("Submit")', 'input[value*="Submit"]']);
                    
                    if (!submitButton) {
                        throw new Error('Could not find submit button');
                    }

                    await submitButton.click();
                    await p.waitForLoadState('networkidle');
                    await p.waitForTimeout(2000); // Extra wait for any JS processing
                    await takeScreenshot(p, '04-after-submit');
                }
            }
        ];

        // Execute all steps with AI assistance
        await executeWithAI(page, steps);

        // Validate the result
        const validationResult = await validateSubmissionResult(page);
        
        if (validationResult.success) {
            result.success = true;
            result.reference_number = validationResult.reference_number || validationResult.reference;
            result.message = validationResult.message || 'Submission completed successfully';
            await takeScreenshot(page, '05-success');
        } else {
            result.success = false;
            result.error = validationResult.message || validationResult.errors?.join(', ') || 'Submission failed';
            await takeScreenshot(page, '05-failure');
        }

    } catch (error) {
        result.success = false;
        result.error = error.message;
        await takeScreenshot(page, 'final-error');
    } finally {
        await context.close();
    }
}

async function main() {
    const chromePath = findChrome();
    if (!chromePath) {
        result.error = 'Chrome executable not found';
        console.log(JSON.stringify(result, null, 2));
        process.exit(1);
    }

    log(`Chrome: ${chromePath}`);
    log(`Action: ${action}`);
    log(`Base URL: ${baseUrl}`);
    log(`Headless: ${headless}`);
    log(`AI Enabled: ${!!claudeApiKey}`);

    let browser;
    try {
        browser = await chromium.launch({
            headless: headless,
            executablePath: chromePath,
        });

        await runSubmission(browser);

    } catch (error) {
        result.success = false;
        result.error = error.message;
        log(`Fatal error: ${error.message}`);
    } finally {
        if (browser) {
            await browser.close();
        }
    }

    console.log(JSON.stringify(result, null, 2));
    process.exit(result.success ? 0 : 1);
}

main();
