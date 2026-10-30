/* FE-01: real presentation with a synthetic B-01 contract. No Owner data is read. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.ATHER_VISUAL_BASE || process.env.EVIDENCE_HUB_VISUAL_BASE_URL || 'http://127.0.0.1:18471';
const out = process.env.ATHER_VISUAL_OUT || process.argv[2] || path.join(process.env.TEMP || '/tmp', 'ather-fe01-review');
const url = (locale, state = 'preview') => `${base}/tests/Browser/owner-workspace-visual.php?lang=${locale}&state=${state}`;
const key = 'ather.owner.sidebar';
const overflow = page => page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
async function ready(page) { await page.evaluate(() => document.fonts.ready); }
async function capture(page, name) { await page.mouse.move(0, 0); await ready(page); await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true }); }
function luminance(rgb) {
    const c = rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; });
    return c[0] * .2126 + c[1] * .7152 + c[2] * .0722;
}
async function contrast(page) {
    const text = await page.evaluate(() => [...document.querySelectorAll('.admin-sidebar *, .owner-toolbar *, .admin-content > section *')].filter(n => {
        const r = n.getBoundingClientRect();
        return r.width && r.height && [...n.childNodes].some(t => t.nodeType === Node.TEXT_NODE && t.textContent.trim()) && getComputedStyle(n).visibility !== 'hidden';
    }).map(n => {
        let p = n, bg;
        while (p) { bg = getComputedStyle(p).backgroundColor; if (bg !== 'rgba(0, 0, 0, 0)') break; p = p.parentElement; }
        const s = getComputedStyle(n);
        return { text: n.textContent.trim().slice(0, 40), color: s.color, bg, large: parseFloat(s.fontSize) >= 24 || (parseFloat(s.fontSize) >= 18.66 && Number(s.fontWeight) >= 700) };
    }));
    for (const t of text) {
        const a = luminance(t.color), b = luminance(t.bg);
        const ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
        assert.ok(ratio >= (t.large ? 3 : 4.5), `AA text contrast ${ratio.toFixed(2)}: ${t.text}`);
    }
}
async function main() {
    fs.mkdirSync(out, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.ATHER_CHROMIUM || process.env.EVIDENCE_HUB_EDGE_EXECUTABLE || chromium.executablePath() });
    try {
        for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({ colorScheme: theme, reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 } });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', e => errors.push(e.message));
            await page.goto(url(locale)); await ready(page);
            assert.equal(await page.locator('.owner-nav-item').count(), 9);
            assert.equal(await page.locator('.owner-nav-item[aria-current="page"]').count(), 1);
            assert.equal(await page.locator('[data-owner-sidebar-toggle]').isVisible(), true);
            assert.equal(await page.locator('.owner-themes button:visible').count(), 1);
            assert.equal(await page.locator('script:not([src]), [style]').count(), 0, 'CSP requires external scripts/styles');
            assert.equal(await page.locator('.owner-language').count(), 0, 'FE-02 moves language switching to Settings');
            const arrowTransforms = await page.locator('.dashboard-link svg, .dashboard-next .dashboard-button svg').evaluateAll(nodes => nodes.map(n => {
                const matrix = new DOMMatrix(getComputedStyle(n).transform);
                return [matrix.a, matrix.b, matrix.c, matrix.d];
            }));
            assert.equal(arrowTransforms.length, 6, 'all five dashboard links and the next-step arrow are checked');
            arrowTransforms.forEach(matrix => assert.deepEqual(matrix, [locale === 'en' ? -1 : 1, 0, 0, 1], `${locale}/${theme}: dashboard arrows point forward`));
            const hub = page.locator('.owner-nav-item[href="/owner/evidence-hub"]');
            const hubLabel = await hub.locator(':scope > span').first().evaluate(n => {
                const range = document.createRange();
                range.selectNodeContents(n);
                return { lines: range.getClientRects().length, height: n.getBoundingClientRect().height, lineHeight: parseFloat(getComputedStyle(n).lineHeight) };
            });
            assert.equal(hubLabel.lines, 1, `${locale}/${theme}: Evidence Hub label occupies one line at 1440px`);
            assert.ok(hubLabel.height <= hubLabel.lineHeight + 1, 'Evidence Hub label is not wrapped or clipped');
            assert.equal(await hub.locator('.owner-nav-badge').isVisible(), true, 'Private badge remains visible');
            const hubFits = await hub.evaluate(n => {
                const item = n.getBoundingClientRect();
                return [...n.children].every(child => { const r = child.getBoundingClientRect(); return r.left >= item.left && r.right <= item.right; });
            });
            assert.equal(hubFits, true, 'label, icon and badge fit within the unchanged sidebar width');
            await contrast(page);
            await capture(page, `overview-${locale}-${theme}-1440`);
            if (locale === 'ar') {
                await page.locator('.owner-account').click();
                await contrast(page);
                const panel = await page.locator('.owner-account-popover').boundingBox();
                assert.ok(panel.x >= 0 && panel.x + panel.width <= 1440, 'account panel fits the viewport');
                assert.equal(await page.locator('.admin-sidebar').evaluate(n => getComputedStyle(n).overflowY), 'visible', 'account popover escapes the rail without being clipped');
                await capture(page, `account-${theme}-1440`);
                assert.equal(await page.locator('form[action="/owner_logout.php"] button').isVisible(), true);
                await page.keyboard.press('Escape');
                assert.equal(await page.locator('.owner-account-menu').evaluate(n => n.open), false);
                assert.equal(await page.locator('.owner-account').evaluate(n => n === document.activeElement), true);
            }
            for (const width of [390, 768, 1280, 1920, 2560]) {
                await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
                assert.equal(await overflow(page), false, `${locale}/${theme}/${width} overflow`);
                if (width === 390) {
                    if (locale === 'ar') await capture(page, `overview-ar-${theme}-390`);
                    const menu = page.locator('[data-owner-nav-toggle]');
                    await menu.focus(); await page.keyboard.press('Enter');
                    assert.equal(await menu.getAttribute('aria-expanded'), 'true');
                    assert.equal(await page.locator('.owner-navigation').isVisible(), true);
                    if (locale === 'ar') { await menu.blur(); await capture(page, `mobile-menu-${theme}-390`); }
                    await page.locator('.owner-account').click();
                    assert.equal(await page.locator('form[action="/owner_switch_account.php"] button').isVisible(), true);
                    await page.keyboard.press('Escape');
                    assert.equal(await menu.getAttribute('aria-expanded'), 'true', 'account Escape closes only the account');
                    await page.keyboard.press('Escape');
                    assert.equal(await menu.getAttribute('aria-expanded'), 'false');
                    assert.equal(await menu.evaluate(n => n === document.activeElement), true);
                }
            }
            await page.setViewportSize({ width: 1440, height: 1000 });
            const toggle = page.locator('[data-owner-sidebar-toggle]');
            await toggle.focus(); await page.keyboard.press('Enter');
            assert.equal(await page.evaluate(k => localStorage.getItem(k), key), 'compact');
            assert.equal(await page.locator('.admin-sidebar').evaluate(n => n.getBoundingClientRect().width), 72);
            assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
            for (const link of await page.locator('.owner-nav-item').all()) {
                assert.ok(await link.getAttribute('aria-label'), 'compact links retain their names');
                assert.equal(await link.isVisible(), true);
            }
            if (locale === 'ar') { await toggle.blur(); await capture(page, `compact-${theme}-1440`); }
            await page.reload();
            assert.equal(await page.locator('body').getAttribute('data-owner-sidebar'), 'compact', 'preference survives reload');
            await page.locator('.owner-account').click();
            assert.equal(await page.locator('.owner-account-popover a').isVisible(), true);
            assert.equal(await overflow(page), false, 'compact account fits');
            await page.keyboard.press('Escape');
            await page.setViewportSize({ width: 390, height: 844 });
            await page.waitForFunction(() => document.body.getAttribute('data-owner-sidebar') === 'expanded');
            await page.setViewportSize({ width: 1440, height: 1000 });
            await page.waitForFunction(() => document.body.getAttribute('data-owner-sidebar') === 'compact');
            await toggle.click();
            const themeButton = page.locator('[data-owner-theme]:visible');
            await themeButton.click();
            assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'), theme === 'light' ? 'dark' : 'light');
            assert.deepEqual(errors, []);
            await context.close();
        }
        for (const locale of ['ar', 'en']) {
            const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
            const page = await context.newPage(); await page.goto(url(locale));
            assert.equal(await page.locator('[data-owner-sidebar-toggle]').isVisible(), false);
            assert.equal(await page.locator('[data-owner-nav-toggle]').isVisible(), false);
            assert.equal(await page.locator('.owner-navigation').isVisible(), true);
            assert.equal(await page.locator('.owner-themes').isVisible(), false);
            await page.locator('.owner-account').click();
            for (const action of ['owner_switch_account.php', 'owner_logout.php']) {
                const form = page.locator(`form[action="/${action}"]`);
                assert.equal(await form.getAttribute('method'), 'POST');
                assert.equal(await form.locator('input').count(), 1);
                assert.equal(await form.locator('input').getAttribute('name'), 'csrf_token');
                assert.equal(await form.locator('button').isVisible(), true);
            }
            await page.locator('.owner-account').click();
            await page.mouse.move(0, 0);
            if (locale === 'ar') await page.screenshot({ path: path.join(out, 'overview-ar-no-js-390.png'), fullPage: true });
            assert.equal(await page.locator('.owner-nav-item:visible').count(), 9);
            await context.close();
        }
        const context = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, reducedMotion: 'reduce' });
        const page = await context.newPage();
        for (const state of ['empty', 'write_evidence', 'publish_drafts', 'unavailable', 'long']) for (const locale of ['ar', 'en']) {
            await page.goto(url(locale, state));
            assert.equal(await overflow(page), false, `${state}/${locale} overflow`);
            if (state === 'unavailable') {
                assert.equal(await page.locator('[role="alert"]').count(), 1);
                assert.equal(await page.locator('.dashboard-sharing,.dashboard-next,.dashboard-recent').count(), 0);
            } else {
                const sizes = await page.locator('.dashboard-button,.dashboard-link,.dashboard-icon-button,.dashboard-guide summary,.owner-toolbar a,.owner-themes button:visible').evaluateAll(nodes => nodes.filter(n => n.getClientRects().length).map(n => ({ height: n.getBoundingClientRect().height, label: n.textContent })));
                sizes.forEach(s => assert.ok(s.height >= 44, `coarse target ${s.height}: ${s.label}`));
            }
        }
        await page.setViewportSize({ width: 1280, height: 480 }); await page.goto(url('en'));
        await page.evaluate(() => {
            const nav = document.querySelector('.owner-nav');
            for (let i = 0; i < 12; i++) { const link = nav.querySelector('a').cloneNode(true); link.removeAttribute('aria-current'); link.textContent = `Temporary route ${i}`; nav.append(link); }
        });
        assert.equal(await page.locator('.owner-nav').evaluate(n => n.scrollHeight > n.clientHeight), true, 'long navigation scrolls independently');
        await page.locator('.owner-nav-item').last().scrollIntoViewIfNeeded();
        await page.locator('.owner-account').scrollIntoViewIfNeeded(); await page.locator('.owner-account').click();
        assert.equal(await page.locator('form[action="/owner_logout.php"] button').isVisible(), true, 'growing navigation keeps account accessible');
        assert.equal(await overflow(page), false);
        await context.close();
        const restricted = await browser.newContext({ viewport: { width: 1280, height: 900 } });
        await restricted.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new Error('Storage denied for test'); } }));
        const restrictedPage = await restricted.newPage();
        const restrictedErrors = [];
        restrictedPage.on('pageerror', error => restrictedErrors.push(error.message));
        await restrictedPage.goto(url('en'));
        const group = restrictedPage.locator('.owner-nav-group').first();
        await group.locator('summary').click();
        assert.equal(await group.evaluate(n => n.open), false);
        await restrictedPage.locator('[data-owner-sidebar-toggle]').click();
        assert.equal(await group.evaluate(n => n.open), true, 'compact opens all destinations');
        await restrictedPage.locator('[data-owner-sidebar-toggle]').click();
        assert.equal(await group.evaluate(n => n.open), false, 'expanded restores group choice');
        await restrictedPage.locator('[data-owner-theme=dark]').click();
        assert.equal(await restrictedPage.locator('body').getAttribute('data-evidence-hub-theme'), 'dark');
        assert.deepEqual(restrictedErrors, []);
        await restricted.close();
        await require('./owner-settings-checks.cjs')(browser, base, out, contrast);
        console.log('OWNER_WORKSPACE_VISUAL_PASSED: 20 layout combinations; compact persistence; mobile keyboard; native no-JS forms; B-01 states; AA contrast; coarse targets; short viewport; denied storage; locale arrow direction; single-line Evidence Hub.');
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
