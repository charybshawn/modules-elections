#!/usr/bin/env node
// Read public pages in a real browser (Playwright) when plain fetches are
// refused and Browserless isn't available. Fallback only: Browserless via the
// browser-scrape skill is the default (see browser-access.md).
//
//   node playwright-fetch.cjs [--links] [--max=<chars>] [--wait=<ms>] <url> [<url> ...]
//
// Prints one JSON array: { url, finalUrl, status, title, ogImage, text, links?, challenge }.
// It does NOT try to get past bot checks. If a page answers with a security
// challenge, `challenge` is true and nothing else is attempted: report it and
// use another route (a web-archive copy, or the user's own browser).
//
// Playwright is resolved from the cultpantry app's node_modules; override with
// PLAYWRIGHT_PATH. Pages are loaded one at a time with a pause between them.

const path = require('path');
const root = process.env.PLAYWRIGHT_PATH || '/Users/shawn/Documents/code/cultpantry/node_modules/playwright';
const { chromium } = require(root);

const args = process.argv.slice(2);
const flag = (name, fallback) => {
  const hit = args.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.split('=')[1] : fallback;
};
const withLinks = args.includes('--links');
const max = parseInt(flag('max', '6000'), 10);
const wait = parseInt(flag('wait', '2500'), 10);
const urls = args.filter((a) => /^https?:\/\//.test(a));

if (urls.length === 0) {
  console.error('usage: node playwright-fetch.cjs [--links] [--max=6000] [--wait=2500] <url> [<url> ...]');
  process.exit(2);
}

const CHALLENGE = /just a moment|performing security verification|verify you are human|attention required|access denied|captcha|checking your browser/i;

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ locale: 'en-CA', timezoneId: 'America/Vancouver', viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const results = [];
  try {
    for (const [i, url] of urls.entries()) {
      if (i > 0) await page.waitForTimeout(3000 + Math.floor(Math.random() * 4000));
      const row = { url };
      try {
        const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await page.waitForTimeout(wait);
        row.finalUrl = page.url();
        row.status = response ? response.status() : null;
        row.title = await page.title();
        const body = (await page.locator('body').innerText()).replace(/\s+/g, ' ').trim();
        row.challenge = CHALLENGE.test(`${row.title} ${body.slice(0, 400)}`);
        row.ogImage = await page.locator('meta[property="og:image"]').first().getAttribute('content').catch(() => null);
        row.text = body.slice(0, max);
        if (withLinks) {
          row.links = await page.$$eval('a[href]', (as) => as.map((a) => ({ t: a.innerText.trim().replace(/\s+/g, ' ').slice(0, 80), h: a.href })).filter((x) => x.t).slice(0, 80));
        }
      } catch (e) {
        row.error = String(e).slice(0, 240);
      }
      results.push(row);
    }
  } finally {
    await browser.close();
  }
  console.log(JSON.stringify(results, null, 1));
})();
