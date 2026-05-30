#!/usr/bin/env node
/**
 * Optional: Screenshots von YouTube-Watch-Seite und Kanal (Einblendung / Kanalheader).
 * Voraussetzung: npm install puppeteer (im Projektordner laravel12).
 * .env: PUBLICATION_EVIDENCE_PLAYWRIGHT_SCRIPT=scripts/youtube-evidence-screenshots.mjs
 */
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const args = Object.fromEntries(
    process.argv.slice(2).map((a) => {
        const [k, v] = a.replace(/^--/, '').split('=');
        return [k, v ?? ''];
    })
);

const watchUrl = args.url;
const overlayPath = args.overlay;
const channelPath = args.channel;

if (!watchUrl || !overlayPath || !channelPath) {
    console.error('Usage: node youtube-evidence-screenshots.mjs --url=... --overlay=... --channel=...');
    process.exit(2);
}

let puppeteer;
try {
    puppeteer = await import('puppeteer');
} catch {
    console.error('puppeteer nicht installiert. Bitte: cd laravel12 && npm install puppeteer');
    process.exit(1);
}

mkdirSync(dirname(overlayPath), { recursive: true });
mkdirSync(dirname(channelPath), { recursive: true });

const browser = await puppeteer.default.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
});

try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    await page.goto(watchUrl, { waitUntil: 'networkidle2', timeout: 120000 });
    await new Promise((resolve) => setTimeout(resolve, 4000));
    await page.screenshot({ path: overlayPath, fullPage: false });

    const channelHref = await page.$eval('#channel-name a', (a) => a.href).catch(() => null);
    if (channelHref) {
        const channelPage = await browser.newPage();
        await channelPage.setViewport({ width: 1920, height: 1080 });
        await channelPage.goto(channelHref, { waitUntil: 'networkidle2', timeout: 120000 });
        await new Promise((resolve) => setTimeout(resolve, 3000));
        await channelPage.screenshot({ path: channelPath, fullPage: false });
        await channelPage.close();
    }
} finally {
    await browser.close();
}
