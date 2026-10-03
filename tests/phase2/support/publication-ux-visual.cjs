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

async function installRoutes(page, variant) {
    const html = execFileSync('php', [path.join(__dirname, 'publication-ux-visual.php'), variant], { encoding: 'utf8' });
    await page.route('http://publication.test/**', route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname === '/style.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'style.css')) });
        if (url.pathname === '/admin.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'admin.css')) });
        if (url.pathname === '/admin.js') return route.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(path.join(root, 'admin.js')) });
        return route.fulfill({ status: 204, body: '' });
    });
}

async function newClipboardPage(browser, shouldFail = false) {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await page.addInitScript(fail => {
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText(value) {
                    if (fail) return Promise.reject(new Error('clipboard failure'));
                    window.__copiedPublicationUrl = value;
                    return Promise.resolve();
                },
            },
        });
    }, shouldFail);
    return page;
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const published = await newClipboardPage(browser);
        await installRoutes(published, 'published');
        await published.goto('http://publication.test/');
        const expectedUrl = 'https://localhost:8443/p/momen-qasim-al-omari';
        const view = published.getByRole('link', { name: 'View Portfolio' });
        const copy = published.getByRole('button', { name: 'Copy Link' });
        const feedback = published.locator('#publication-copy-feedback');
        assert.equal(await view.getAttribute('href'), expectedUrl, 'View Portfolio URL');
        assert.equal(await view.getAttribute('target'), '_blank', 'View Portfolio target');
        assert.equal(await view.getAttribute('rel'), 'noopener noreferrer', 'View Portfolio rel');
        assert.equal(await copy.isVisible(), true, 'Copy Link becomes available with Clipboard API');
        await copy.click();
        assert.equal(await published.evaluate(() => window.__copiedPublicationUrl), expectedUrl, 'Copy Link exact value');
        assert.equal(await feedback.textContent(), 'Portfolio link copied.', 'Copy success announcement');
        await published.evaluate(() => { window.__copiedPublicationUrl = ''; });
        await copy.focus();
        await published.keyboard.press('Enter');
        assert.equal(await published.evaluate(() => window.__copiedPublicationUrl), expectedUrl, 'Copy Link keyboard activation');
        await published.close();

        const failure = await newClipboardPage(browser, true);
        await installRoutes(failure, 'published');
        await failure.goto('http://publication.test/');
        await failure.getByRole('button', { name: 'Copy Link' }).click();
        assert.equal(await failure.locator('#publication-copy-feedback').textContent(), 'Copying the Portfolio link failed. Select and copy the link manually.', 'Copy failure announcement');
        await failure.close();

        const noJavaScript = await browser.newPage({ viewport: { width: 1440, height: 1000 }, javaScriptEnabled: false });
        await installRoutes(noJavaScript, 'published');
        await noJavaScript.goto('http://publication.test/');
        assert.equal(await noJavaScript.locator('#publication-public-url').textContent(), expectedUrl, 'No-JavaScript URL remains readable');
        assert.equal(await noJavaScript.getByRole('link', { name: 'View Portfolio' }).getAttribute('href'), expectedUrl, 'No-JavaScript View Portfolio remains usable');
        assert.equal(await noJavaScript.getByRole('button', { name: 'Copy Link' }).isHidden(), true, 'No-JavaScript Copy control stays hidden');
        await noJavaScript.close();

        for (const width of [1440, 1100, 768, 360]) {
            const page = await newClipboardPage(browser);
            await installRoutes(page, 'long');
            await page.setViewportSize({ width, height: 1000 });
            await page.goto('http://publication.test/');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}: horizontal overflow`);
            assert.equal(await page.locator('.publication-public-url').evaluate(node => node.scrollWidth <= node.clientWidth + 1), true, `${width}: long URL overflow`);
            assert.equal(await page.locator('.publication-actions').evaluate(node => [...node.querySelectorAll('a, button')].every(control => control.getBoundingClientRect().height >= 44)), true, `${width}: touch targets`);
            await page.locator('.publication-card').screenshot({ path: path.join(output, `${width}.png`) });
            await page.close();
        }

        for (const [variant, forbidden] of [['no-slug', 'publication-public-url'], ['reserved', 'View Portfolio'], ['offline', 'View Portfolio']]) {
            const page = await browser.newPage();
            await installRoutes(page, variant);
            await page.goto('http://publication.test/');
            assert.equal((await page.content()).includes(forbidden), false, `${variant}: no misleading action`);
            await page.close();
        }
        console.log('PASS publication URL browser contract');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
