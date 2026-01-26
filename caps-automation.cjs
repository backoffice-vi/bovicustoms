const { chromium } = require('playwright-core');

// Item data for shipment 2601SJU1053
const items = [
  { hsCode: '1905', desc: 'BARBARAS CHEESE PUFF ORIGINAL 7 OZ', qty: 12, fob: 37.08, freight: 2.97, ins: 0.37, cif: 40.42, dutyRate: 20, cudAmt: 8.08, whaAmt: 0.74 },
  { hsCode: '1905', desc: 'BARBARAS CHEESE PUFF JALPENO 7 OZ', qty: 12, fob: 37.08, freight: 2.97, ins: 0.37, cif: 40.42, dutyRate: 20, cudAmt: 8.08, whaAmt: 0.74 },
  { hsCode: '1905', desc: 'BARBARAS CHEESE PUFF BKD 5.50 OZ', qty: 24, fob: 74.16, freight: 5.95, ins: 0.74, cif: 80.85, dutyRate: 20, cudAmt: 16.17, whaAmt: 1.48 },
  { hsCode: '2103', desc: 'JOYVA SESAME CRUNCH 20 LB', qty: 1, fob: 105, freight: 8.42, ins: 1.05, cif: 114.47, dutyRate: 10, cudAmt: 11.45, whaAmt: 2.1 },
  { hsCode: '2106', desc: 'CHATFIELDS CAROB CHIPS DF SF 12 OZ', qty: 12, fob: 57.12, freight: 4.58, ins: 0.57, cif: 62.27, dutyRate: 5, cudAmt: 3.11, whaAmt: 1.14 },
  { hsCode: '1905', desc: 'JENNIES MACAROON CCNUT POUCH 8 OZ', qty: 12, fob: 38.76, freight: 3.11, ins: 0.39, cif: 42.26, dutyRate: 20, cudAmt: 8.45, whaAmt: 0.78 },
  { hsCode: '120799', desc: 'NATURES EARTHLY CHOICE CHIA 12 OZ', qty: 6, fob: 25.8, freight: 2.07, ins: 0.26, cif: 28.13, dutyRate: 5, cudAmt: 1.41, whaAmt: 0.52 },
  { hsCode: '2202', desc: 'LAKEWOOD JUICE CRANBERRY PURE 32 FO', qty: 6, fob: 70.68, freight: 5.67, ins: 0.71, cif: 77.06, dutyRate: 15, cudAmt: 11.56, whaAmt: 1.41 },
  { hsCode: '2101', desc: 'KAFFREE ROMA BEV COFFEE KAFFREE ROMA', qty: 6, fob: 42.9, freight: 3.44, ins: 0.43, cif: 46.77, dutyRate: 10, cudAmt: 4.68, whaAmt: 0.86 },
  { hsCode: '120400', desc: 'BOBS RED MILL FLAXSEED BRWN 13 OZ', qty: 8, fob: 16.96, freight: 1.36, ins: 0.17, cif: 18.49, dutyRate: 5, cudAmt: 0.92, whaAmt: 0.34 },
  { hsCode: '1101', desc: 'BOBS RED MILL FLOUR SPELT 22 OZ', qty: 4, fob: 15.24, freight: 1.22, ins: 0.15, cif: 16.61, dutyRate: 5, cudAmt: 0.83, whaAmt: 0.3 },
  { hsCode: '121299', desc: 'BULK EB SEEDS SUNFLOWER HULD ORG 25', qty: 1, fob: 74.16, freight: 5.95, ins: 0.74, cif: 80.85, dutyRate: 5, cudAmt: 4.04, whaAmt: 1.48 },
  { hsCode: '2501', desc: 'CELTIC SALT GOURMET 1 LB', qty: 6, fob: 24.3, freight: 1.95, ins: 0.24, cif: 26.49, dutyRate: 5, cudAmt: 1.32, whaAmt: 0.49 },
  { hsCode: '0801', desc: 'ELAN NUTS RAW BRAZIL ORGANIC 6.50 OZ', qty: 8, fob: 37.84, freight: 3.03, ins: 0.38, cif: 41.25, dutyRate: 5, cudAmt: 2.06, whaAmt: 0.76 },
  { hsCode: '1206009', desc: 'FERRIS EB SEEDS RAW PUMPKIN 9 OZ', qty: 12, fob: 60, freight: 4.81, ins: 0.6, cif: 65.41, dutyRate: 5, cudAmt: 3.27, whaAmt: 1.2 },
  { hsCode: '2106', desc: 'HIPPEAS PUFFS WHITE CHEDDAR 6PK', qty: 12, fob: 59.76, freight: 4.79, ins: 0.6, cif: 65.15, dutyRate: 5, cudAmt: 3.26, whaAmt: 1.2 },
  { hsCode: '1905', desc: 'HIPPEAS PUFFS WHITE CHEDDAR 1.50 OZ', qty: 12, fob: 15.84, freight: 1.27, ins: 0.16, cif: 17.27, dutyRate: 20, cudAmt: 3.45, whaAmt: 0.32 },
  { hsCode: '1904', desc: 'HIPPEAS PUFFS NACHO VIBES 1.50 OZ', qty: 6, fob: 7.92, freight: 0.64, ins: 0.08, cif: 8.64, dutyRate: 20, cudAmt: 1.73, whaAmt: 0.16 },
  { hsCode: '0801', desc: 'JOOLIES DATES PIT FREE MEDJOOL 9 OZ', qty: 12, fob: 68.76, freight: 5.51, ins: 0.69, cif: 74.96, dutyRate: 5, cudAmt: 3.75, whaAmt: 1.38 },
  { hsCode: '0904', desc: 'SIMPLY ORGANIC SPICE CAYENNE PEPPER', qty: 6, fob: 28.08, freight: 2.25, ins: 0.28, cif: 30.61, dutyRate: 5, cudAmt: 1.53, whaAmt: 0.56 },
  { hsCode: '0801', desc: 'BULK EB CASHEWS RAW 25 LB', qty: 1, fob: 187.06, freight: 15, ins: 1.87, cif: 203.93, dutyRate: 5, cudAmt: 10.2, whaAmt: 3.74 },
  { hsCode: '3307', desc: 'ALVERA DEOD ALOE ALMOND 3 OZ', qty: 24, fob: 64.8, freight: 5.2, ins: 0.65, cif: 70.65, dutyRate: 20, cudAmt: 14.13, whaAmt: 1.3 },
  { hsCode: '3307', desc: 'ALVERA DEOD ALOE HERB 3 OZ', qty: 6, fob: 16.2, freight: 1.3, ins: 0.16, cif: 17.66, dutyRate: 20, cudAmt: 3.53, whaAmt: 0.32 },
  { hsCode: '3307', desc: 'ALVERA DEOD ALOE UNSCNTD 3 OZ', qty: 6, fob: 16.2, freight: 1.3, ins: 0.16, cif: 17.66, dutyRate: 20, cudAmt: 3.53, whaAmt: 0.32 },
  { hsCode: '2106', desc: 'HONEES LOZENGE EUCALYPTUS 20PC', qty: 24, fob: 16.08, freight: 1.29, ins: 0.16, cif: 17.53, dutyRate: 5, cudAmt: 0.88, whaAmt: 0.32 },
  { hsCode: '2105', desc: 'HONEES HONEY BAR 1.62 OZ', qty: 24, fob: 16.08, freight: 1.29, ins: 0.16, cif: 17.53, dutyRate: 15, cudAmt: 2.63, whaAmt: 0.32 },
  { hsCode: '170310', desc: 'PLANTATION MOLASSES BLACKSTRAP ORG', qty: 3, fob: 18.03, freight: 1.45, ins: 0.18, cif: 19.66, dutyRate: 15, cudAmt: 2.95, whaAmt: 0.36 },
  { hsCode: '2106', desc: 'HEALTH PLUS COLON CLNS PWDR SUPER', qty: 6, fob: 69.66, freight: 5.59, ins: 0.7, cif: 75.95, dutyRate: 5, cudAmt: 3.8, whaAmt: 1.39 },
  { hsCode: '2106', desc: 'HEALTH PLUS COLON CLNS SUPER 120 CP', qty: 3, fob: 33.33, freight: 2.67, ins: 0.33, cif: 36.33, dutyRate: 5, cudAmt: 1.82, whaAmt: 0.67 },
  { hsCode: '130210', desc: 'SPIKE SSNNG SPIKE 14 OZ', qty: 1, fob: 8.19, freight: 0.66, ins: 0.08, cif: 8.93, dutyRate: 10, cudAmt: 0.89, whaAmt: 0.16 },
  { hsCode: '2106', desc: 'NUTRITION NOW PB8 ACIDOPHILUS VEG', qty: 3, fob: 44.43, freight: 3.56, ins: 0.44, cif: 48.43, dutyRate: 5, cudAmt: 2.42, whaAmt: 0.89 },
  { hsCode: '2103', desc: 'FOLLOW YOUR HEART VEGENAISE ORIG', qty: 4, fob: 101, freight: 8.1, ins: 1.01, cif: 110.11, dutyRate: 10, cudAmt: 11.01, whaAmt: 2.02 },
  { hsCode: '2106', desc: 'DAIYA CHEESE SLICES AMERCN STYL', qty: 8, fob: 31.92, freight: 2.56, ins: 0.32, cif: 34.8, dutyRate: 5, cudAmt: 1.74, whaAmt: 0.64 },
  { hsCode: '2106', desc: 'DAIYA CHEESE MZRLA DF SHREDDED', qty: 12, fob: 38.4, freight: 3.08, ins: 0.38, cif: 41.86, dutyRate: 5, cudAmt: 2.09, whaAmt: 0.77 },
];

async function fillRecord(page, recordNum, item) {
  const prefix = `rec${recordNum}_`;
  
  // Fill basic fields
  await page.evaluate((data) => {
    const { prefix, item } = data;
    
    // CPC
    const cpc = document.querySelector(`input[name="${prefix}CPC"]`);
    if (cpc) cpc.value = 'C400';
    
    // Tariff No
    const tariff = document.querySelector(`input[name="${prefix}TariffNo"]`);
    if (tariff) tariff.value = item.hsCode;
    
    // Country of Origin
    const country = document.querySelector(`input[name="${prefix}CountryOfOrigin"]`);
    if (country) country.value = 'US';
    
    // Number of packages
    const packages = document.querySelector(`input[name="${prefix}NoOfPackages"]`);
    if (packages) packages.value = item.qty.toString();
    
    // Type of packages
    const pkgType = document.querySelector(`input[name="${prefix}TypeOfPackages"]`);
    if (pkgType) pkgType.value = 'CTN';
    
    // Description
    const desc = document.querySelector(`textarea[name="${prefix}Description"]`) || 
                 document.querySelector(`input[name="${prefix}Description"]`);
    if (desc) desc.value = item.desc;
    
    // Quantity
    const qty = document.querySelector(`input[name="${prefix}Quantity2"]`);
    if (qty) qty.value = item.qty.toFixed(2);
    
    // FOB Value
    const fob = document.querySelector(`input[name="${prefix}RecordValue"]`);
    if (fob) fob.value = item.fob.toFixed(2);
    
    // Currency (readonly, set via JS)
    const currency = document.querySelector(`input[name="${prefix}CurrencyCode"]`);
    if (currency) {
      currency.readOnly = false;
      currency.value = 'USD';
      currency.readOnly = true;
    }
    
    // Charges - FRT
    const frtCode = document.querySelector(`input[name="${prefix}ChargeCode_line1"]`);
    const frtAmt = document.querySelector(`input[name="${prefix}ChargeAmount_line1"]`);
    if (frtCode) frtCode.value = 'FRT';
    if (frtAmt) frtAmt.value = item.freight.toFixed(2);
    
    // Charges - INS
    const insCode = document.querySelector(`input[name="${prefix}ChargeCode_line2"]`);
    const insAmt = document.querySelector(`input[name="${prefix}ChargeAmount_line2"]`);
    if (insCode) insCode.value = 'INS';
    if (insAmt) insAmt.value = item.ins.toFixed(2);
    
    // Tax - CUD
    const cudType = document.querySelector(`input[name="${prefix}TaxType_line1"]`);
    const cudValue = document.querySelector(`input[name="${prefix}TaxValue_line1"]`);
    const cudRate = document.querySelector(`input[name="${prefix}TaxRate_line1"]`);
    const cudAmount = document.querySelector(`input[name="${prefix}TaxAmount_line1"]`);
    if (cudType) cudType.value = 'CUD';
    if (cudValue) cudValue.value = item.cif.toFixed(2);
    if (cudRate) cudRate.value = item.dutyRate.toFixed(2);
    if (cudAmount) cudAmount.value = item.cudAmt.toFixed(2);
    
    // Tax - WHA
    const whaType = document.querySelector(`input[name="${prefix}TaxType_line2"]`);
    const whaValue = document.querySelector(`input[name="${prefix}TaxValue_line2"]`);
    const whaRate = document.querySelector(`input[name="${prefix}TaxRate_line2"]`);
    const whaAmount = document.querySelector(`input[name="${prefix}TaxAmount_line2"]`);
    if (whaType) whaType.value = 'WHA';
    if (whaValue) whaValue.value = item.fob.toFixed(2);
    if (whaRate) whaRate.value = '2.00';
    if (whaAmount) whaAmount.value = item.whaAmt.toFixed(2);
    
  }, { prefix, item });
}

async function main() {
  console.log('Starting CAPS automation...');
  
  // Connect to existing browser or launch new one
  // You'll need to provide the path to your Chrome/Edge executable
  const browser = await chromium.launch({
    headless: false,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
  });
  
  const context = await browser.newContext();
  const page = await context.newPage();
  
  // Navigate to CAPS login
  console.log('Navigating to CAPS...');
  await page.goto('https://caps.gov.vg/CAPSWeb/');
  
  // Login
  console.log('Logging in...');
  await page.fill('input[name="username"]', 'customs@naturesway.vg');
  await page.fill('input[name="password"]', '?pLmWwk@');
  await page.click('input[type="submit"]');
  await page.waitForTimeout(3000);
  
  // Navigate to the TD
  console.log('Opening TD 004369344...');
  await page.goto('https://caps.gov.vg/CAPSWeb/TDDataEntryServlet?method=tddataentry.RetrieveTD&bcdNumber=004369344&isWebTrader=Y');
  await page.waitForTimeout(2000);
  
  // Add items starting from item 2 (item 1 is already added)
  for (let i = 1; i < items.length; i++) {
    console.log(`Adding item ${i + 1} of ${items.length}: ${items[i].desc}`);
    
    // Click Add Record
    await page.click('input[value="Add Record (5)"]');
    await page.waitForTimeout(1000);
    
    // Fill the new record
    await fillRecord(page, i + 1, items[i]);
    
    // Save
    await page.click('input[value="Save (1)"]');
    await page.waitForTimeout(1500);
    
    console.log(`Item ${i + 1} added successfully`);
  }
  
  console.log('All items added! Now uploading files...');
  
  // Upload files
  // Click Attach File button
  await page.click('input[value="Attach File (7)"]');
  await page.waitForTimeout(2000);
  
  console.log('Automation complete!');
  
  // Keep browser open for manual verification
  // await browser.close();
}

main().catch(console.error);
