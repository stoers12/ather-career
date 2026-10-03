'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const url = `${base}/tests/phase2/support/owner-project-evidence-visual.php`;
const output = process.env.ATHER_VISUAL_OUT || path.join(process.env.TEMP || '/tmp', 'ather-evidence-hub-editor-action-review');

function channels(value) {
    const match = value.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    assert.ok(match, `Cannot read color ${value}`);
    return match.slice(1, 4).map(Number);
}

function luminance(color) {
    const [red, green, blue] = channels(color).map(channel => {
        const value = channel / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    return red * 0.2126 + green * 0.7152 + blue * 0.0722;
}

function contrast(foreground, background) {
    const light = Math.max(luminance(foreground), luminance(background));
    const dark = Math.min(luminance(foreground), luminance(background));
    return Math.round(((light + 0.05) / (dark + 0.05)) * 100) / 100;
}

async function appearance(locator) {
    return locator.evaluate(element => {
        const style = getComputedStyle(element);
        let surface = element;
        let background = 'rgba(0, 0, 0, 0)';
        while (surface) {
            const candidate = getComputedStyle(surface).backgroundColor;
            if (!candidate.startsWith('rgba(0, 0, 0, 0)')) {
                background = candidate;
                break;
            }
            surface = surface.parentElement;
        }
        const rect = element.getBoundingClientRect();
        return {
            text: style.color, background, outline: style.outlineColor,
            outlineWidth: style.outlineWidth, shadow: style.boxShadow,
            bounds: { x: rect.x, y: rect.y, width: rect.width, height: rect.height },
        };
    });
}

function stable(before, after, label) {
    for (const key of ['x', 'y', 'width', 'height']) {
        assert.ok(Math.abs(before.bounds[key] - after.bounds[key]) < 1, `${label} moved on hover (${key})`);
    }
}

async function screenshot(page, filename) {
    await page.screenshot({ path: path.join(output, filename) });
}

async function darkEditor(browser, width, height, mode, capture) {
    const context = await browser.newContext({ viewport: { width, height }, colorScheme: mode === 'system' ? 'dark' : 'light', reducedMotion: 'reduce' });
    if (mode === 'saved') await context.addInitScript(() => localStorage.setItem('ather.evidenceHub.theme', 'dark'));
    const page = await context.newPage();
    const screenshotPrefix = mode === 'system' ? 'system-dark-editor' : 'dark-editor';
    try {
        await page.goto(url, { waitUntil: 'networkidle' });
        assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'), 'dark', `${mode} dark theme did not apply`);
        const form = page.locator('.evidence-hub-editor form');
        const actions = page.locator('.evidence-hub-editor-actions');
        const save = actions.getByRole('button', { name: 'Save evidence' });
        const cancel = actions.getByRole('link', { name: 'Cancel' });
        await save.scrollIntoViewIfNeeded();
        await page.mouse.move(250, 100);
        const formBackground = await form.evaluate(element => getComputedStyle(element).backgroundColor);
        const darkSurface = await page.locator('body').evaluate(element => {
            const sample = document.createElement('div');
            sample.style.background = 'var(--eh-surface)';
            element.append(sample);
            const value = getComputedStyle(sample).backgroundColor;
            sample.remove();
            return value;
        });
        assert.equal(formBackground, darkSurface, `${width}px dark editor form surface`);
        const cancelDefault = await appearance(cancel);
        const saveDefault = await appearance(save);
        assert.equal(cancelDefault.background, formBackground, `${width}px Cancel does not sit on the dark form`);
        if (capture) await screenshot(page, `${screenshotPrefix}-actions-default.png`);

        const saveBefore = await appearance(save);
        await save.hover();
        const saveHover = await appearance(save);
        stable(saveBefore, saveHover, 'Save');
        if (capture) await screenshot(page, `${screenshotPrefix}-save-hover.png`);
        await page.mouse.move(250, 100);
        const lastTextarea = page.locator('.evidence-hub-editor textarea').last();
        await lastTextarea.focus();
        await page.keyboard.press('Tab');
        assert.equal(await save.evaluate(element => document.activeElement === element), true, 'Tab from last field must focus Save');
        const focusColor = await page.locator('body').evaluate(element => {
            const sample = document.createElement('div');
            sample.style.outline = '3px solid var(--eh-primary)';
            element.append(sample);
            const value = getComputedStyle(sample).outlineColor;
            sample.remove();
            return value;
        });
        const saveFocus = await appearance(save);
        assert.equal(saveFocus.outline, focusColor, 'Save must have green focus');
        assert.equal(saveFocus.outlineWidth, '3px');
        assert.ok(!saveFocus.shadow.includes('29, 78, 216'), 'Save has a blue focus shadow');
        if (capture) await screenshot(page, `${screenshotPrefix}-save-focus.png`);
        await page.keyboard.press('Tab');
        assert.equal(await cancel.evaluate(element => document.activeElement === element), true, 'Tab from Save must focus Cancel');
        const cancelFocus = await appearance(cancel);
        assert.equal(cancelFocus.outline, focusColor, 'Cancel must have green focus');
        assert.equal(cancelFocus.outlineWidth, '3px');
        if (capture) await screenshot(page, `${screenshotPrefix}-cancel-focus.png`);
        await page.keyboard.press('Shift+Tab');
        assert.equal(await save.evaluate(element => document.activeElement === element), true, 'Shift+Tab from Cancel must focus Save');
        await page.keyboard.press('Shift+Tab');
        assert.equal(await lastTextarea.evaluate(element => document.activeElement === element), true, 'Shift+Tab from Save must focus the last field');
        await page.locator('h1').click();
        await cancel.scrollIntoViewIfNeeded();
        const cancelBefore = await appearance(cancel);
        await cancel.hover();
        const cancelHover = await appearance(cancel);
        stable(cancelBefore, cancelHover, 'Cancel');
        assert.equal(cancelHover.background, saveHover.background, 'Cancel must use the mint hover surface');
        assert.equal(cancelHover.text, saveHover.text, 'Cancel must use readable dark-green hover text');
        if (capture) await screenshot(page, `${screenshotPrefix}-cancel-hover.png`);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${width}px dark editor horizontal overflow`);
        if (width === 320) {
            await page.mouse.move(250, 100);
            await screenshot(page, `${screenshotPrefix}-actions-320.png`);
        }
        const ratios = {
            cancelDefault: contrast(cancelDefault.text, cancelDefault.background),
            cancelHover: contrast(cancelHover.text, cancelHover.background),
            saveDefault: contrast(saveDefault.text, saveDefault.background),
            saveHover: contrast(saveHover.text, saveHover.background),
        };
        for (const [state, ratio] of Object.entries(ratios)) assert.ok(ratio >= 4.5, `${width}px ${mode} ${state} contrast ${ratio}:1`);
        return { width, mode, formBackground, ratios };
    } finally {
        await context.close();
    }
}

async function main() {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.ATHER_CHROMIUM_PATH });
    try {
        const results = [];
        for (const [width, height] of [[1440, 900], [390, 844], [320, 700]]) {
            results.push(await darkEditor(browser, width, height, 'saved', width === 1440));
        }
        results.push(await darkEditor(browser, 1440, 900, 'system', true));
        const light = await browser.newContext({ viewport: { width: 1440, height: 900 }, colorScheme: 'light', reducedMotion: 'reduce' });
        const lightPage = await light.newPage();
        await lightPage.goto(url, { waitUntil: 'networkidle' });
        await lightPage.getByRole('button', { name: 'Save evidence' }).scrollIntoViewIfNeeded();
        assert.equal(await lightPage.locator('.evidence-hub-editor form').evaluate(element => getComputedStyle(element).backgroundColor), 'rgb(255, 255, 255)', 'Light editor form surface changed');
        await screenshot(lightPage, 'light-editor-actions-default.png');
        await light.close();
        const noScript = await browser.newContext({ viewport: { width: 320, height: 700 }, javaScriptEnabled: false });
        const noScriptPage = await noScript.newPage();
        await noScriptPage.goto(url, { waitUntil: 'networkidle' });
        assert.equal(await noScriptPage.getByRole('button', { name: 'Save evidence' }).isVisible(), true);
        assert.equal(await noScriptPage.getByRole('link', { name: 'Cancel' }).isVisible(), true);
        assert.equal(await noScriptPage.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'No-JavaScript editor overflow');
        await noScript.close();
        console.log(JSON.stringify({ result: 'PASS', results, noJavaScript: 'Save and Cancel accessible at 320px', screenshots: output }, null, 2));
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
