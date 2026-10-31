import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';

const require = createRequire(import.meta.url);
const {chromium} = require(process.env.ATHERCAR_PLAYWRIGHT_MODULE || 'playwright');
const image = process.env.ATHERCAR_SIGNUP_IMAGE;
if (process.env.APP_ENV !== 'test' || process.env.ATHERCAR_TEST_MODE !== '1' || !image) process.exit(2);
const output = process.env.ATHERCAR_SIGNUP_EVIDENCE_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'ather-signup-'));
fs.mkdirSync(output, {recursive: true});
const containers = [];
let browser;
const docker = (...args) => execFileSync('docker', args, {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']}).trim();
function start(mode, source = image) {
    const name = `ather-signup-${mode}-${crypto.randomUUID()}`;
    docker('run', '--rm', '-d', '--name', name, '--entrypoint', 'apache2-foreground',
        '-p', '127.0.0.1::80', '-e', 'APP_ENV=test', '-e', 'ATHERCAR_TEST_MODE=1',
        '-e', 'SESSION_COOKIE_SECURE=0', '-e', `ATHERCAR_REGISTRATION_ENABLED=${mode}`, source);
    containers.push(name);
    const port = docker('port', name, '80/tcp').split(':').at(-1);
    return `http://127.0.0.1:${port}`;
}
async function ready(url) {
    for (let i = 0; i < 60; i++) {
        try { const response = await fetch(`${url}/signup.php`); if (response.status === 200) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    throw new Error('Disposable signup server unavailable');
}
const ratio = (a, b) => {
    const luminance = rgb => {
        const channels = rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
        return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
    };
    const [x, y] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (x + .05) / (y + .05);
};
try {
    browser = await chromium.launch({headless: true, ...(process.env.ATHERCAR_CHROMIUM_EXECUTABLE ? {executablePath: process.env.ATHERCAR_CHROMIUM_EXECUTABLE} : {})});
    for (const mode of ['invite', '1', '0']) {
        const url = start(mode);
        const baseline = mode !== 'invite' && process.env.ATHERCAR_SIGNUP_BASELINE_IMAGE ? start(mode, process.env.ATHERCAR_SIGNUP_BASELINE_IMAGE) : null;
        await ready(url); if (baseline) await ready(baseline);
        for (const locale of ['ar', 'en']) {
            for (const theme of ['light', 'dark']) {
                const context = await browser.newContext({colorScheme: theme, reducedMotion: 'reduce'});
                const page = await context.newPage();
                for (const width of [320, 390, 768, 1280, 1440]) {
                    await page.setViewportSize({width, height: 1000});
                    await page.goto(`${url}/signup.php?lang=${locale}`);
                    await page.evaluate(() => document.fonts.ready);
                    assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
                    assert.equal(await page.locator('[name="invitation_code"]').count(), mode === 'invite' ? 1 : 0);
                    assert.deepEqual(await page.locator('form [name]').evaluateAll(nodes => nodes.map(node => node.name)), mode === 'invite' ? ['csrf_token', 'invitation_code'] : mode === '1' ? ['csrf_token'] : []);
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Overflow ${mode}/${locale}/${theme}/${width}`);
                    if (baseline) {
                        const before = await context.newPage(); await before.setViewportSize({width, height: 1000});
                        await before.goto(`${baseline}/signup.php?lang=${locale}`); await before.evaluate(() => document.fonts.ready);
                        assert.deepEqual(await page.screenshot({fullPage: true}), await before.screenshot({fullPage: true}), `Open/closed pixels changed ${mode}/${locale}/${theme}/${width}`);
                        await before.close();
                    }
                    if (mode === 'invite') {
                        const field = page.locator('#invitation-code');
                        assert.equal(await field.getAttribute('autocomplete'), 'off');
                        assert.equal(await field.getAttribute('autocapitalize'), 'characters');
                        assert.equal(await field.getAttribute('spellcheck'), 'false');
                        assert.equal(await field.getAttribute('required'), '');
                        assert.equal(await field.inputValue(), '');
                        assert(await page.locator('label[for="invitation-code"]').isVisible());
                        assert.match(await page.locator('#invitation-hint').textContent(), /XXXX-XXXX-XXXX/);
                        assert((await field.boundingBox()).height >= 44);
                        const styles = await field.evaluate(el => ({color: getComputedStyle(el).color, background: getComputedStyle(el).backgroundColor, border: getComputedStyle(el).borderTopColor}));
                        assert(ratio(styles.color, styles.background) >= 4.5);
                        assert(ratio(styles.border, styles.background) >= 3);
                        await page.locator('.af-theme').focus(); await page.keyboard.press('Tab'); await page.keyboard.press('Tab');
                        assert(await field.evaluate(el => el === document.activeElement), 'Keyboard order must reach invitation');
                        assert.equal(await field.evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
                        if ([390, 1440].includes(width)) await page.screenshot({path: path.join(output, `invite-${locale}-${theme}-${width}.png`), fullPage: true});
                        await page.goto(`${url}/signup.php?lang=${locale}&invitation=invalid&invitation_code=PRIVATE_SENTINEL`);
                        assert(await page.locator('#invitation-error[role="alert"]').isVisible());
                        assert.equal(await field.getAttribute('aria-describedby'), 'invitation-hint invitation-error');
                        assert.equal(await field.getAttribute('aria-invalid'), 'true');
                        assert.equal(await field.inputValue(), '');
                        assert(!(await page.content()).includes('PRIVATE_SENTINEL'));
                        const error = page.locator('#invitation-error');
                        const colors = await error.evaluate(el => ({color: getComputedStyle(el).color, background: getComputedStyle(el.closest('.af-card')).backgroundColor}));
                        assert(ratio(colors.color, colors.background) >= 4.5);
                        assert.match(await error.textContent(), locale === 'en' ? /This invitation code isn't valid\. Check it or ask for a new one\./ : /رمز الدعوة هذا غير صالح/);
                        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Error must wrap');
                        if ([390, 1440].includes(width)) await page.screenshot({path: path.join(output, `invalid-${locale}-${theme}-${width}.png`), fullPage: true});
                    } else {
                        await page.goto(`${url}/signup.php?lang=${locale}&invitation=invalid`);
                        assert.equal(await page.locator('[role="alert"]').count(), 0);
                        assert.equal(await page.locator('button[type="submit"]').count(), mode === '1' ? 1 : 0);
                    }
                }
                await context.close();
            }
            // Intercept only the provider start: inspect the browser's actual POST,
            // without sending a synthetic code to an external identity provider.
            const context = await browser.newContext({javaScriptEnabled: false});
            const page = await context.newPage(); let posted;
            await page.route('**/owner_register.php', async route => {
                posted = new URLSearchParams(route.request().postData());
                await route.fulfill({status: 303, headers: {location: '/signup.php?invitation=invalid'}});
            });
            await page.goto(`${url}/signup.php?lang=${locale}`);
            if (mode !== '0') {
                if (mode === 'invite') {
                    await page.locator('button[type="submit"]').click(); assert.equal(posted, undefined);
                    await page.locator('#invitation-code').fill('ABCD-EFGH-JKMN');
                }
                await Promise.all([page.waitForURL('**/signup.php?invitation=invalid'), page.locator('button[type="submit"]').click()]);
                assert.deepEqual([...posted.keys()].sort(), mode === 'invite' ? ['csrf_token', 'invitation_code'] : ['csrf_token']);
                assert.match(posted.get('csrf_token'), /^[a-f0-9]{64}$/);
                if (mode === 'invite') assert.equal(posted.get('invitation_code'), 'ABCD-EFGH-JKMN');
            } else {
                assert.equal((await context.request.post(`${url}/owner_register.php`)).status(), 404);
            }
            assert.equal((await context.request.post(`${url}/signup.php`)).status(), 405);
            await context.close();
        }
        console.log(`PASS signup browser mode=${mode}: AR/EN, light/dark, 320/390/768/1280/1440, exact fields, no-JS, HTTP methods${baseline ? ', baseline pixel equality' : ''}`);
    }
    console.log('PASS signup invitation labels, required validation, keyboard focus, 44px input, AA text/UI contrast, format, announced errors and no echo');
} catch (error) {
    console.error(error instanceof assert.AssertionError ? error.message : 'FAIL signup browser infrastructure');
    process.exitCode = 1;
} finally {
    if (browser) await browser.close();
    for (const name of containers.reverse()) docker('rm', '-f', name);
}
