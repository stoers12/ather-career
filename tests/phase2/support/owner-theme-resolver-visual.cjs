'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const hub = `${base}/tests/phase2/support/evidence-hub-page-visual.php?variant=partial`;
const editor = `${base}/tests/phase2/support/owner-project-evidence-visual.php`;
const legacy = `${base}/tests/phase2/support/owner-theme-legacy-visual.php`;
const output = process.env.ATHER_VISUAL_OUT || path.join(process.env.TEMP || '/tmp', 'ather-owner-theme-review');
const storageKey = 'ather.evidenceHub.theme';

async function newContext(browser, options) {
    const context = await browser.newContext({
        viewport: { width: options.width || 1440, height: options.height || 900 },
        colorScheme: options.system,
        reducedMotion: 'reduce',
    });
    if (options.saved !== undefined) {
        await context.addInitScript(({ key, saved }) => localStorage.setItem(key, saved), { key: storageKey, saved: options.saved });
    }
    if (options.storageUnavailable) {
        await context.addInitScript(() => Object.defineProperty(window, 'localStorage', {
            configurable: true,
            get() { throw new Error('Storage unavailable for test'); },
        }));
    }
    await context.addInitScript(() => {
        window.__ownerThemeAtFirstLayout = undefined;
        const observer = new MutationObserver(() => {
            if (window.__ownerThemeAtFirstLayout !== undefined || !document.querySelector('.admin-layout')) return;
            window.__ownerThemeAtFirstLayout = document.body.getAttribute('data-evidence-hub-theme');
            observer.disconnect();
        });
        observer.observe(document, { childList: true, subtree: true });
    });
    return context;
}

async function visit(page, url, expected, label, screenshot) {
    const errors = [];
    const onError = error => errors.push(error.message);
    const onConsole = message => { if (message.type() === 'error') errors.push(message.text()); };
    page.on('pageerror', onError);
    page.on('console', onConsole);
    const response = await page.goto(url, { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200, `${label} HTTP status`);
    assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'), expected, `${label} resolved theme`);
    assert.equal(await page.evaluate(() => window.__ownerThemeAtFirstLayout), expected, `${label} first workspace content used the wrong theme`);
    assert.equal(await page.locator('#evidence-hub-theme-toggle').count(), 0, `${label} has a theme toggle`);
    assert.equal(await page.locator('button[aria-label*="theme" i], a[aria-label*="theme" i]').count(), 0, `${label} has a focusable theme control`);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${label} horizontal overflow`);
    assert.deepEqual(errors, [], `${label} browser errors`);
    page.off('pageerror', onError);
    page.off('console', onConsole);
    if (screenshot) await page.screenshot({ path: path.join(output, screenshot) });
}

async function savedValue(page) {
    return page.evaluate(key => localStorage.getItem(key), storageKey);
}

function missingMediaFallback() {
    const source = fs.readFileSync(path.join(__dirname, '../../../owner_theme.js'), 'utf8');
    const root = { attributes: {}, setAttribute(name, value) { this.attributes[name] = value; } };
    const sandbox = { document: { body: root }, window: { localStorage: { getItem() { return null; } } } };
    vm.runInNewContext(source, sandbox);
    assert.equal(root.attributes['data-evidence-hub-theme'], 'light', 'Missing matchMedia must safely fall back to Light');
    root.attributes = {};
    sandbox.window.matchMedia = () => { throw new Error('Media query unavailable'); };
    vm.runInNewContext(source, sandbox);
    assert.equal(root.attributes['data-evidence-hub-theme'], 'light', 'Throwing matchMedia must safely fall back to Light');
    root.attributes = {};
    sandbox.window.matchMedia = () => ({ get matches() { throw new Error('System preference unavailable'); } });
    vm.runInNewContext(source, sandbox);
    assert.equal(root.attributes['data-evidence-hub-theme'], 'light', 'Unreadable system preference must safely fall back to Light');
}

async function main() {
    fs.mkdirSync(output, { recursive: true });
    missingMediaFallback();
    const browser = await chromium.launch({ headless: true, executablePath: process.env.ATHER_CHROMIUM_PATH });
    const checks = [];
    try {
        const matrix = [
            { saved: undefined, system: 'light', expected: 'light', screenshot: 'fresh-system-light-hub.png' },
            { saved: undefined, system: 'dark', expected: 'dark', screenshot: 'fresh-system-dark-hub.png' },
            { saved: 'light', system: 'dark', expected: 'light', screenshot: 'saved-light-system-dark-hub.png' },
            { saved: 'dark', system: 'light', expected: 'dark', screenshot: 'saved-dark-system-light-hub.png' },
            { saved: 'invalid', system: 'light', expected: 'light' },
            { saved: 'invalid', system: 'dark', expected: 'dark' },
            { saved: '', system: 'dark', expected: 'dark' },
            { saved: ' dark ', system: 'light', expected: 'light' },
        ];
        for (const scenario of matrix) {
            const context = await newContext(browser, scenario);
            const page = await context.newPage();
            const label = `saved=${JSON.stringify(scenario.saved)} system=${scenario.system}`;
            await visit(page, hub, scenario.expected, label, scenario.screenshot);
            assert.equal(await savedValue(page), scenario.saved === undefined ? null : scenario.saved, `${label} storage mutated`);
            checks.push(label);
            await context.close();
        }

        const unavailable = await newContext(browser, { system: 'dark', storageUnavailable: true });
        const unavailablePage = await unavailable.newPage();
        await visit(unavailablePage, hub, 'dark', 'storage unavailable Hub');
        await visit(unavailablePage, editor, 'dark', 'storage unavailable editor');
        await unavailable.close();
        checks.push('storage unavailable follows system Dark');

        const live = await newContext(browser, { system: 'light' });
        const livePage = await live.newPage();
        await visit(livePage, hub, 'light', 'system Light before live change');
        const initialBounds = await livePage.locator('.admin-layout').boundingBox();
        await livePage.emulateMedia({ colorScheme: 'dark' });
        await livePage.waitForFunction(() => document.body.getAttribute('data-evidence-hub-theme') === 'dark');
        assert.equal(await savedValue(livePage), null, 'System-derived Dark must not be persisted');
        const darkBounds = await livePage.locator('.admin-layout').boundingBox();
        assert.ok(Math.abs(initialBounds.width - darkBounds.width) < 1 && Math.abs(initialBounds.x - darkBounds.x) < 1, 'Live theme change shifted layout');
        await livePage.emulateMedia({ colorScheme: 'light' });
        await livePage.waitForFunction(() => document.body.getAttribute('data-evidence-hub-theme') === 'light');
        await live.close();
        checks.push('live system changes follow Light → Dark → Light without persistence or layout shift');

        const savedLive = await newContext(browser, { system: 'light', saved: 'dark' });
        const savedPage = await savedLive.newPage();
        await visit(savedPage, hub, 'dark', 'saved Dark before live system change');
        await savedPage.emulateMedia({ colorScheme: 'dark' });
        await savedPage.emulateMedia({ colorScheme: 'light' });
        assert.equal(await savedPage.locator('body').getAttribute('data-evidence-hub-theme'), 'dark', 'System change overrode saved Dark');
        await savedLive.close();
        checks.push('saved Dark ignores live system changes');

        const savedLightLive = await newContext(browser, { system: 'dark', saved: 'light' });
        const savedLightLivePage = await savedLightLive.newPage();
        await visit(savedLightLivePage, hub, 'light', 'saved Light before live system change');
        await savedLightLivePage.emulateMedia({ colorScheme: 'light' });
        await savedLightLivePage.emulateMedia({ colorScheme: 'dark' });
        assert.equal(await savedLightLivePage.locator('body').getAttribute('data-evidence-hub-theme'), 'light', 'System change overrode saved Light');
        await savedLightLive.close();
        checks.push('saved Light ignores live system changes');

        const invalidLive = await newContext(browser, { system: 'light', saved: 'invalid' });
        const invalidLivePage = await invalidLive.newPage();
        await visit(invalidLivePage, hub, 'light', 'invalid saved value before live system change');
        await invalidLivePage.emulateMedia({ colorScheme: 'dark' });
        await invalidLivePage.waitForFunction(() => document.body.getAttribute('data-evidence-hub-theme') === 'dark');
        assert.equal(await savedValue(invalidLivePage), 'invalid', 'Invalid stored value should not be rewritten');
        await invalidLive.close();
        checks.push('invalid saved value follows live system changes without storage mutation');

        for (const [width, height] of [[1440, 900], [390, 844], [320, 700]]) {
            const context = await newContext(browser, { system: 'dark', width, height });
            const page = await context.newPage();
            await visit(page, hub, 'dark', `${width}px fresh system-Dark Hub`, width === 320 ? 'fresh-system-dark-hub-320.png' : null);
            await visit(page, legacy, null, `${width}px legacy Owner Light`, width === 1440 ? 'fresh-system-dark-legacy-light.png' : null);
            assert.equal(await page.locator('script[src*="owner_theme.js"]').count(), 0, 'Legacy Owner page loaded theme resolver');
            assert.equal(await page.evaluate(() => getComputedStyle(document.body).backgroundColor), 'rgb(246, 248, 251)', 'Legacy Owner page did not remain Light');
            await page.emulateMedia({ colorScheme: 'light' });
            await page.emulateMedia({ colorScheme: 'dark' });
            assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'), null, 'Legacy Owner page followed live system change');
            await visit(page, hub, 'dark', `${width}px navigation back to Hub`, width === 1440 ? 'navigation-back-restores-dark.png' : null);
            await visit(page, editor, 'dark', `${width}px fresh system-Dark editor`, width === 1440 ? 'fresh-system-dark-editor.png' : null);
            await context.close();
            checks.push(`${width}px system-Dark Hub → Light legacy → Dark Hub → Dark editor`);
        }

        const savedNavigation = await newContext(browser, { system: 'light', saved: 'dark' });
        const navigationPage = await savedNavigation.newPage();
        await visit(navigationPage, hub, 'dark', 'saved-Dark Hub');
        await visit(navigationPage, legacy, null, 'saved-Dark legacy Light');
        assert.equal(await savedValue(navigationPage), 'dark', 'Legacy page overwrote saved Dark');
        await visit(navigationPage, editor, 'dark', 'saved-Dark editor');
        await savedNavigation.close();
        checks.push('saved Dark survives legacy Owner navigation');

        const savedLightNavigation = await newContext(browser, { system: 'dark', saved: 'light' });
        const savedLightPage = await savedLightNavigation.newPage();
        await visit(savedLightPage, hub, 'light', 'saved-Light Hub');
        await visit(savedLightPage, legacy, null, 'saved-Light legacy Light');
        assert.equal(await savedValue(savedLightPage), 'light', 'Legacy page overwrote saved Light');
        await visit(savedLightPage, editor, 'light', 'saved-Light editor');
        await savedLightNavigation.close();
        checks.push('saved Light survives legacy Owner navigation despite system Dark');

        const noScript = await browser.newContext({ viewport: { width: 320, height: 700 }, javaScriptEnabled: false, colorScheme: 'dark' });
        const noScriptPage = await noScript.newPage();
        await noScriptPage.goto(hub, { waitUntil: 'networkidle' });
        assert.equal(await noScriptPage.locator('body').getAttribute('data-evidence-hub-theme'), null, 'No-JavaScript fallback should remain Light');
        assert.equal(await noScriptPage.getByRole('heading', { name: 'Build a portfolio people can trust.' }).isVisible(), true);
        await noScript.close();
        checks.push('no-JavaScript content accessible with safe Light fallback');

        console.log(JSON.stringify({ result: 'PASS', checks, screenshots: output }, null, 2));
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
