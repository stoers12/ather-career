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

const expectations = {
    full: { metrics: 3, about: true },
    one: { metrics: 1, about: true },
    two: { metrics: 2, about: true },
    long: { metrics: 3, about: true },
    'no-about': { metrics: 3, about: false },
};

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const width of [1440, 768, 360]) {
            for (const [variant, expected] of Object.entries(expectations)) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const html = execFileSync('php', [path.join(__dirname, 'professional-overview-visual.php'), variant], { encoding: 'utf8' });
                await page.route('http://overview.test/**', route => {
                    const url = new URL(route.request().url());
                    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
                    if (url.pathname === '/portfolio.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'portfolio.css')) });
                    if (url.pathname === '/assets/images/ather-navbar-logo.png') return route.fulfill({ contentType: 'image/png', body: fs.readFileSync(path.join(root, 'assets/images/ather-navbar-logo.png')) });
                    return route.fulfill({ status: 204, body: '' });
                });
                await page.goto('http://overview.test/');

                const metrics = page.locator('.portfolio-metric');
                const about = page.locator('.portfolio-about');
                assert.equal(await metrics.count(), expected.metrics, `${width}/${variant}: metric count`);
                assert.equal(await about.count(), expected.about ? 1 : 0, `${width}/${variant}: About visibility`);
                assert.equal(await page.locator('.portfolio-about-location').count(), 0, `${width}/${variant}: duplicate About location`);
                assert.equal(await page.locator('.portfolio-hero-profile-location').count(), 1, `${width}/${variant}: Hero location visibility`);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}/${variant}: page overflow`);

                if (expected.metrics > 0) {
                    const metricBoxes = await metrics.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect()));
                    if (width >= 768 && metricBoxes.length > 1) {
                        const widths = metricBoxes.map(box => box.width);
                        assert(Math.max(...widths) - Math.min(...widths) < 1, `${width}/${variant}: unequal desktop metric columns`);
                    }
                    if (expected.metrics === 1 && width <= 620) {
                        const [metricBox] = metricBoxes;
                        const stripBox = await page.locator('.portfolio-metrics-strip').evaluate(node => node.getBoundingClientRect());
                        assert(Math.abs(metricBox.width - stripBox.width) <= 2, `${width}/${variant}: single metric does not fill the compact strip`);
                    }
                }

                if (expected.about) {
                    const heading = page.locator('.portfolio-about-heading');
                    const copy = page.locator('.portfolio-about-copy');
                    assert(!(await about.textContent()).includes('Based in '), `${width}/${variant}: duplicate About location text`);
                    const headingBox = await heading.evaluate(node => node.getBoundingClientRect());
                    const copyBox = await copy.evaluate(node => node.getBoundingClientRect());
                    if (width >= 768) {
                        assert(copyBox.x > headingBox.x, `${width}/${variant}: desktop About did not retain its two-column hierarchy`);
                        assert.equal(await heading.evaluate(node => getComputedStyle(node).borderRightWidth), '1px', `${width}/${variant}: desktop About divider missing`);
                    } else {
                        assert(copyBox.y > headingBox.y, `${width}/${variant}: mobile About did not stack`);
                        assert.equal(await heading.evaluate(node => getComputedStyle(node).borderRightWidth), '0px', `${width}/${variant}: mobile retained desktop divider`);
                    }
                }

                const overflow = await page.evaluate(() => [...document.querySelectorAll('.portfolio-metrics, .portfolio-metrics *, .portfolio-about, .portfolio-about *')]
                    .filter(node => node.scrollWidth > node.clientWidth + 1)
                    .map(node => `${node.tagName}.${node.className}`));
                assert.deepEqual(overflow, [], `${width}/${variant}: overview content overflow`);

                const regionBoxes = await page.locator('.portfolio-metrics, .portfolio-about').evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect()));
                const top = Math.max(0, Math.min(...regionBoxes.map(box => box.top)) - 12);
                const bottom = Math.max(...regionBoxes.map(box => box.bottom)) + 12;
                await page.setViewportSize({ width, height: Math.max(1000, Math.ceil(bottom) + 12) });
                await page.screenshot({
                    path: path.join(output, `${width}-${variant}.png`),
                    clip: { x: 0, y: top, width, height: bottom - top },
                });
                console.log(`PASS professional overview ${width}/${variant}`);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
