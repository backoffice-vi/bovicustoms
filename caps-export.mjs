import fs from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer';

const BASE_URL = 'https://caps.gov.vg/CAPSWeb';
const LOGIN_URL = `${BASE_URL}/TraderLogin.jsp`;
const TD_LIST_URL = `${BASE_URL}/WebTraderServlet?method=webtrader.RetrieveTDList`;

const OUT_BASE = path.resolve('exports', 'caps-trade-declarations');

function requireEnv(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Missing required env var: ${name}`);
  return v;
}

function sanitizeFilename(name) {
  // Keep it Windows-safe
  return name
    .replace(/[<>:"/\\|?*\u0000-\u001F]/g, '_')
    .replace(/\s+/g, ' ')
    .trim();
}

function cookieHeaderFrom(cookies) {
  return cookies.map((c) => `${c.name}=${c.value}`).join('; ');
}

async function downloadFile(url, destPath, cookieHeader) {
  // Use the browser itself to fetch with cookies (avoids cross-origin/cert issues).
  const res = await fetch(url, {
    headers: cookieHeader ? { Cookie: cookieHeader } : undefined,
  });
  if (!res.ok) {
    throw new Error(`Download failed (${res.status}) ${url}`);
  }
  const buf = Buffer.from(await res.arrayBuffer());
  await fs.writeFile(destPath, buf);
}

async function clickLinkByText(page, text) {
  const [a] = await page.$x(`//a[normalize-space(.)='${text}']`);
  if (!a) throw new Error(`Could not find pagination link: ${text}`);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => null),
    a.click(),
  ]);
}

async function getTdNumbersOnCurrentPage(page) {
  return await page.evaluate(() => {
    const tds = [];
    for (const a of Array.from(document.querySelectorAll('table a'))) {
      const txt = (a.textContent || '').trim();
      if (/^\d{9}$/.test(txt)) tds.push(txt);
    }
    // preserve order, de-dupe
    return Array.from(new Set(tds));
  });
}

async function getAttachmentsForTd(page, tdNo) {
  // Use the same endpoint CAPS uses; it returns: file1,file2,...,TRADER_ID
  const text = await page.evaluate(async (td) => {
    const url = `TDDataEntryServlet?method=tddataentry.AttachFile&action=attachment&bcdNumberA=${td}`;
    const resp = await fetch(url, { credentials: 'include' });
    return await resp.text();
  }, tdNo);

  // If not logged in, CAPS responds with HTML login page
  if (/j_security_check|Internet Trader\s+Login/i.test(text)) {
    return { traderId: null, files: [] };
  }

  const parts = text
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);

  if (parts.length < 2) return { traderId: null, files: [] };

  const traderId = parts[parts.length - 1];
  const files = parts.slice(0, -1).filter((name) => /\.[a-z0-9]{2,5}$/i.test(name));

  return { traderId, files };
}

async function exportTdPreviewPdf(context, tdNo) {
  const outDir = path.join(OUT_BASE, tdNo);
  await fs.mkdir(outDir, { recursive: true });

  const url = `${BASE_URL}/WebTraderServlet?method=webtrader.PreviewTD&tdNumber=${tdNo}`;
  const page = await context.newPage();
  await page.goto(url, { waitUntil: 'networkidle2' });

  // This is the “pop up” Trade Declaration.
  await page.pdf({
    path: path.join(outDir, 'trade-declaration.pdf'),
    format: 'A4',
    printBackground: true,
    margin: { top: '10mm', right: '10mm', bottom: '10mm', left: '10mm' },
  });

  await page.close();
}

async function exportTdAttachments(page, tdNo) {
  const outDir = path.join(OUT_BASE, tdNo);
  await fs.mkdir(outDir, { recursive: true });

  const { traderId, files } = await getAttachmentsForTd(page, tdNo);
  if (!traderId || files.length === 0) return;

  const cookies = await page.cookies(BASE_URL);
  const cookieHeader = cookieHeaderFrom(cookies);

  for (const originalName of files) {
    const safeName = sanitizeFilename(originalName);
    const fileUrl =
      `${BASE_URL}/uploadFile?fileName=${encodeURIComponent(originalName)}` +
      `&traderId=${encodeURIComponent(traderId)}` +
      `&tdNumber=${encodeURIComponent(tdNo)}`;

    // Heuristic naming for convenience while keeping original filename.
    const lower = originalName.toLowerCase();
    const prefix =
      lower.includes('bill') || lower.includes('lading') || lower.includes('awb')
        ? 'bill-of-lading__'
        : lower.includes('invoice')
          ? 'invoice__'
          : 'attachment__';

    const dest = path.join(outDir, `${prefix}${safeName}`);
    await downloadFile(fileUrl, dest, cookieHeader);
  }
}

async function main() {
  const username = requireEnv('CAPS_USERNAME');
  const password = requireEnv('CAPS_PASSWORD');

  await fs.mkdir(OUT_BASE, { recursive: true });

  const browser = await puppeteer.launch({
    headless: 'new',
    // Use system Chrome if available; puppeteer will fall back if bundled exists.
    executablePath: process.env.CHROME_PATH,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const context = await browser.createIncognitoBrowserContext();
  const page = await context.newPage();

  // Login
  await page.goto(LOGIN_URL, { waitUntil: 'networkidle2' });
  await page.type('input[name="j_username"]', username, { delay: 20 });
  await page.type('input[name="j_password"]', password, { delay: 20 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
    page.click('input[type="submit"][value*="Login"]'),
  ]);

  // Ensure we're at the TD list.
  await page.goto(TD_LIST_URL, { waitUntil: 'networkidle2' });

  const allTdNumbers = [];

  for (let pageNum = 1; pageNum <= 5; pageNum++) {
    // Click pagination when needed (page 1 is usually default)
    if (pageNum !== 1) {
      await clickLinkByText(page, String(pageNum));
    }

    const tds = await getTdNumbersOnCurrentPage(page);
    for (const tdNo of tds) {
      allTdNumbers.push(tdNo);
    }

    // Export each TD on this page
    for (const tdNo of tds) {
      await exportTdPreviewPdf(context, tdNo);
      await exportTdAttachments(page, tdNo);
    }
  }

  // De-dupe final list for logs if needed
  const unique = Array.from(new Set(allTdNumbers));
  await fs.writeFile(path.join(OUT_BASE, '_td_numbers.txt'), unique.join('\n') + '\n', 'utf8');

  await browser.close();
}

main().catch((err) => {
  // eslint-disable-next-line no-console
  console.error(err);
  process.exitCode = 1;
});

