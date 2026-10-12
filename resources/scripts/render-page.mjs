// Renders a JavaScript-driven public page (e.g. Interfolio adverts) and prints its final HTML.
// Usage: node render-page.mjs <url>
import { lookup } from 'node:dns/promises';
import { isIP } from 'node:net';
import { chromium } from 'playwright';

const privateAddress = (ip) => /^(0\.|10\.|127\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|::1$|::$|f[cd]|fe80|::ffff:(10|127|192\.168|169\.254)\.)/i.test(ip);
const publicHosts = new Map();
const isPublic = async (url) => {
    const { protocol, hostname, port } = new URL(url);
    if (!['http:', 'https:'].includes(protocol) || (port && !['80', '443'].includes(port))) return false;
    if (!publicHosts.has(hostname)) {
        publicHosts.set(hostname, (async () => {
            const addresses = isIP(hostname) ? [{ address: hostname }] : await lookup(hostname, { all: true }).catch(() => []);
            return addresses.length > 0 && addresses.every(({ address }) => !privateAddress(address));
        })());
    }
    return publicHosts.get(hostname);
};

const url = process.argv[2];
if (!url || !(await isPublic(url))) {
    console.error('Only public websites can be rendered.');
    process.exit(1);
}
const browser = await chromium.launch();
try {
    const page = await browser.newPage({ userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36' });
    await page.route('**/*', async (route) => {
        const request = route.request();
        if (['image', 'media', 'font'].includes(request.resourceType()) || !(await isPublic(request.url()))) return route.abort();
        return route.continue();
    });
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    // Single-page apps can keep showing a loading shell after the network settles.
    await page.waitForFunction(() => document.body && document.body.innerText.trim().length > 1500, null, { timeout: 15000 }).catch(() => {});
    process.stdout.write((await page.content()).slice(0, 3_000_000));
} finally {
    await browser.close();
}
