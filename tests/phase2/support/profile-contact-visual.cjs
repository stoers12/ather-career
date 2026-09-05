// Uses an already installed Playwright; no application/browser dependency added.
// PLAYWRIGHT_MODULE=<module path> node this-file <output directory>
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const output = process.argv[2];
if (!output) throw new Error('A screenshot output directory is required.');
fs.mkdirSync(output, { recursive: true });
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const width of [1440, 768, 360]) {
            for (const variant of ['image', 'long', 'fallback', 'empty']) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const html = execFileSync('php', [path.join(__dirname, 'profile-contact-visual.php'), variant], { encoding: 'utf8' });
                await page.route('http://profile.test/**', route => {
                    const url = new URL(route.request().url());
                    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
                    if (url.pathname === '/portfolio.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'portfolio.css')) });
                    if (url.pathname === '/assets/images/ather-navbar-logo.png') return route.fulfill({ contentType: 'image/png', body: fs.readFileSync(path.join(root, 'assets/images/ather-navbar-logo.png')) });
                    if (url.pathname === '/synthetic-portrait.svg') return route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="800"><rect width="640" height="800" fill="#284666"/><circle cx="320" cy="270" r="110" fill="#b7c7d6"/><path d="M80 800V660a240 240 0 0 1 480 0v140" fill="#718ba5"/></svg>' });
                    return route.fulfill({ status: 204, body: '' });
                });
                await page.goto('http://profile.test/');
                const card = page.locator('.portfolio-hero-profile-card');
                const problems = await card.evaluate(card => {
                    const nodes = [card, ...card.querySelectorAll('.portfolio-hero-profile-details, .portfolio-hero-profile-details *')];
                    return nodes.filter(node => node.scrollWidth > node.clientWidth + 1 || node.scrollHeight > node.clientHeight + 1).map(node => node.tagName + '.' + node.className);
                });
                assert.deepEqual(problems, [], `${width}/${variant}: clipped contact content`);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}/${variant}: page overflow`);
                if (variant === 'empty') assert.equal(await card.locator('.portfolio-hero-profile-contact').count(), 0);
                else {
                    assert.equal(await card.locator('a[href^="tel:"]').count(), 1);
                    await card.locator('a[href^="mailto:"]').focus();
                    assert(await card.locator('a[href^="mailto:"]').evaluate(el => getComputedStyle(el).outlineStyle !== 'none'), 'Missing focus indicator');
                }
                await card.screenshot({ path: path.join(output, `${width}-${variant}.png`), style: '.portfolio-header { visibility: hidden; }' });
                console.log(`PASS visual layout ${width}/${variant}`);
                await page.close();
            }
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
