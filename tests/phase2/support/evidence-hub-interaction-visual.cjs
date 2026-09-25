'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const output = process.env.ATHER_VISUAL_OUT || path.join(process.env.TEMP || '/tmp', 'ather-evidence-hub-interaction-review');
const hub = `${base}/tests/phase2/support/evidence-hub-page-visual.php?variant=partial`;
const zero = `${base}/tests/phase2/support/evidence-hub-page-visual.php?variant=zero`;
const editor = `${base}/tests/phase2/support/owner-project-evidence-visual.php`;

async function colors(page) {
    return page.evaluate(() => {
        const content = document.querySelector('.admin-content');
        const sample = document.createElement('span');
        sample.style.cssText = 'position:absolute;pointer-events:none;background:var(--eh-interaction-hover-bg);color:var(--eh-interaction-hover-ink);border:1px solid var(--eh-interaction-hover-border);outline:3px solid var(--eh-primary)';
        content.append(sample);
        const style = getComputedStyle(sample);
        const result = { bg: style.backgroundColor, ink: style.color, border: style.borderColor, focus: style.outlineColor };
        sample.remove();
        return result;
    });
}

async function visual(locator) {
    return locator.evaluate(element => {
        const style = getComputedStyle(element);
        const bounds = element.getBoundingClientRect();
        return {
            bg: style.backgroundColor, ink: style.color, border: style.borderColor,
            outline: style.outlineColor, outlineWidth: style.outlineWidth,
            shadow: style.boxShadow, transform: style.transform,
            bounds: { x: bounds.x, y: bounds.y, width: bounds.width, height: bounds.height },
        };
    });
}

function sameBounds(before, after, label) {
    for (const key of ['x', 'y', 'width', 'height']) {
        assert.ok(Math.abs(before.bounds[key] - after.bounds[key]) < 1, `${label} shifted on hover (${key})`);
    }
}

async function hover(page, locator, expected, label, screenshot) {
    await locator.scrollIntoViewIfNeeded();
    const before = await visual(locator);
    await locator.hover();
    const after = await visual(locator);
    sameBounds(before, after, label);
    assert.equal(after.bg, expected.bg, `${label} hover background`);
    assert.equal(after.ink, expected.ink, `${label} hover text`);
    assert.equal(after.border, expected.border, `${label} hover border`);
    assert.equal(documentOverflow(await page.evaluate(() => [document.documentElement.scrollWidth, innerWidth])), false, `${label} page overflow`);
    if (screenshot) await page.screenshot({ path: path.join(output, screenshot) });
    return after;
}

function documentOverflow([scrollWidth, viewportWidth]) { return scrollWidth > viewportWidth; }

async function keyboardFocus(page, locator, expected, label, screenshot) {
    await page.keyboard.press('Tab');
    await locator.focus();
    const state = await visual(locator);
    assert.equal(state.outline, expected.focus, `${label} focus ring color`);
    assert.equal(state.outlineWidth, '3px', `${label} focus ring width`);
    assert.ok(!state.shadow.includes('29, 78, 216'), `${label} has a blue focus shadow`);
    if (screenshot) await page.screenshot({ path: path.join(output, screenshot) });
}

async function auditVisibleFocusTargets(page, expected, theme) {
    const targets = page.locator('.admin-skip-link, .admin-content :is(a[href],button,summary,textarea)');
    let checked = 0;
    for (let index = 0; index < await targets.count(); index++) {
        const target = targets.nth(index);
        if (!await target.isVisible() || await target.isDisabled()) continue;
        await page.keyboard.press('Tab');
        await target.focus();
        const state = await visual(target);
        assert.equal(state.outline, expected.focus, `${theme} focus target ${index} ring color`);
        assert.equal(state.outlineWidth, '3px', `${theme} focus target ${index} ring width`);
        assert.ok(!state.shadow.includes('29, 78, 216'), `${theme} focus target ${index} has a blue shadow`);
        checked++;
    }
    return checked;
}

async function auditVisibleHoverTargets(page, expected, theme) {
    const targets = page.locator('.admin-content :is(a[href],button,summary)');
    let checked = 0;
    for (let index = 0; index < await targets.count(); index++) {
        const target = targets.nth(index);
        if (!await target.isVisible() || await target.isDisabled()) continue;
        if (await target.getAttribute('aria-current') === 'true' || await target.getAttribute('aria-pressed') === 'true') continue;
        if (await target.evaluate(node => node.classList.contains('evidence-hub-menu-dismiss'))) continue;
        await hover(page, target, expected, `${theme} visible control ${index}`);
        checked++;
    }
    return checked;
}

async function main() {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.ATHER_CHROMIUM_PATH });
    const checks = [];
    try {
        for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
            if (theme === 'dark') await context.addInitScript(() => localStorage.setItem('ather.evidenceHub.theme', 'dark'));
            const page = await context.newPage();
            await page.goto(hub, { waitUntil: 'networkidle' });
            const expected = await colors(page);
            assert.equal(await page.locator('#evidence-hub-theme-toggle').count(), 0);
            const recommendations = page.locator('.evidence-hub-recommendation-card');
            const review = recommendations.nth(1).locator('.evidence-hub-primary-cta');
            await hover(page, review, expected, `${theme} Review technology`, theme === 'light' ? 'review-technology-hover-light.png' : 'review-technology-hover-dark.png');
            await keyboardFocus(page, review, expected, `${theme} primary action`, theme === 'light' ? 'primary-keyboard-focus-light.png' : 'primary-keyboard-focus-dark.png');
            await hover(page, page.locator('.evidence-hub-readiness-panel a'), expected, `${theme} View next steps`);
            await page.locator('h1').click();
            const options = recommendations.first().locator('.evidence-hub-options');
            await hover(page, options.locator('summary'), expected, `${theme} More options`, theme === 'light' ? 'secondary-hover-light.png' : null);
            await options.locator('summary').click();
            const menuBounds = await options.locator('div').first().boundingBox();
            await hover(page, options.getByRole('button', { name: 'Snooze for 14 days' }), expected, `${theme} Snooze`, theme === 'light' ? 'menu-snooze-hover-light.png' : null);
            const dismiss = options.getByRole('button', { name: 'Dismiss' });
            await dismiss.hover();
            const destructive = await visual(dismiss);
            const danger = await page.evaluate(() => {
                const sample = document.createElement('span');
                sample.style.cssText = 'color:var(--eh-danger);background:var(--eh-danger-soft)';
                document.body.append(sample);
                const style = getComputedStyle(sample);
                const result = { ink: style.color, bg: style.backgroundColor };
                sample.remove();
                return result;
            });
            assert.equal(destructive.ink, danger.ink, `${theme} Dismiss lost destructive text`);
            assert.equal(destructive.bg, danger.bg, `${theme} Dismiss lost destructive surface`);
            assert.deepEqual(await options.locator('div').first().boundingBox(), menuBounds, `${theme} menu moved on hover`);
            if (theme === 'light') await page.screenshot({ path: path.join(output, 'menu-dismiss-hover-light.png') });
            await keyboardFocus(page, dismiss, expected, `${theme} Dismiss`);
            await page.keyboard.press('Escape');
            assert.equal(await options.evaluate(node => node.open), false, `${theme} menu did not close with Escape`);

            const nav = page.locator('.evidence-hub-section-nav');
            const active = nav.getByRole('link', { name: 'Next steps' });
            await hover(page, nav.getByRole('link', { name: 'Overview' }), expected, `${theme} inactive Navbar link`);
            assert.equal(await active.getAttribute('aria-current'), 'true', `${theme} Navbar active state changed on hover`);
            assert.notEqual((await visual(active)).shadow, 'none', `${theme} active Navbar link is indistinguishable from hover`);
            await keyboardFocus(page, nav.getByRole('link', { name: 'Overview' }), expected, `${theme} Navbar link`);

            const filters = page.locator('.evidence-hub-project-filters');
            const selected = filters.getByRole('button', { name: 'All' });
            await page.locator('.evidence-hub-page-body h2').first().click();
            await hover(page, filters.getByRole('button', { name: 'Needs attention' }), expected, `${theme} unselected filter`, theme === 'light' ? 'selected-filter-hover-light.png' : null);
            assert.equal(await selected.getAttribute('aria-pressed'), 'true');
            assert.notEqual((await visual(selected)).shadow, 'none', `${theme} selected filter is indistinguishable from hover`);
            await keyboardFocus(page, filters.getByRole('button', { name: 'Needs attention' }), expected, `${theme} filter`);
            const firstProject = page.locator('#project-8');
            await hover(page, firstProject.locator('summary'), expected, `${theme} project disclosure`);
            await keyboardFocus(page, firstProject.locator('summary'), expected, `${theme} project disclosure`);
            await hover(page, firstProject.locator('.evidence-hub-project-edit'), expected, `${theme} project edit`);
            await firstProject.locator('summary').click();
            await hover(page, firstProject.locator('.evidence-hub-field a').first(), expected, `${theme} field edit`);
            await hover(page, page.locator('.evidence-hub-show-more'), expected, `${theme} Show more`);

            const hubFocusTargets = await auditVisibleFocusTargets(page, expected, `${theme} Hub`);
            const hubHoverTargets = await auditVisibleHoverTargets(page, expected, `${theme} Hub`);

            await page.goto(zero, { waitUntil: 'networkidle' });
            await hover(page, page.locator('.evidence-hub-zero-path a').first(), expected, `${theme} zero-state link`);
            await page.goto(editor, { waitUntil: 'networkidle' });
            const save = page.getByRole('button', { name: 'Save evidence' });
            const cancel = page.getByRole('link', { name: 'Cancel' });
            await hover(page, save, expected, `${theme} Save evidence`, theme === 'light' ? 'editor-save-hover-light.png' : 'editor-save-hover-dark.png');
            await keyboardFocus(page, save, expected, `${theme} Save evidence`, theme === 'light' ? 'editor-save-focus-light.png' : null);
            await hover(page, cancel, expected, `${theme} Cancel`, theme === 'light' ? 'editor-cancel-hover-light.png' : null);
            await keyboardFocus(page, cancel, expected, `${theme} Cancel`);
            const textarea = page.locator('.evidence-hub-editor textarea').first();
            await keyboardFocus(page, textarea, expected, `${theme} evidence textarea`);
            assert.equal((await visual(textarea)).border, expected.focus, `${theme} textarea border is not green`);
            const editorFocusTargets = await auditVisibleFocusTargets(page, expected, `${theme} editor`);
            const editorHoverTargets = await auditVisibleHoverTargets(page, expected, `${theme} editor`);
            checks.push(`${theme}: selected and destructive states passed; ${hubFocusTargets} Hub and ${editorFocusTargets} editor keyboard targets, ${hubHoverTargets} Hub and ${editorHoverTargets} editor hover targets checked`);
            await context.close();
        }

        for (const width of [390, 320]) {
            const context = await browser.newContext({ viewport: { width, height: width === 390 ? 844 : 700 }, hasTouch: true, isMobile: true, reducedMotion: 'reduce' });
            const page = await context.newPage();
            await page.goto(hub, { waitUntil: 'networkidle' });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} initial overflow`);
            await page.locator('.evidence-hub-options summary').first().tap();
            assert.equal(await page.locator('.evidence-hub-options').first().evaluate(node => node.open), true, `${width} touch menu did not open`);
            await page.getByRole('button', { name: 'Complete', exact: true }).tap();
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} interaction overflow`);
            await page.goto(editor, { waitUntil: 'networkidle' });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width} editor overflow`);
            checks.push(`${width}px touch: menus, filters, Hub and editor overflow passed`);
            await context.close();
        }

        console.log(JSON.stringify({ result: 'PASS', checks, screenshots: output }, null, 2));
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
