import {createRequire} from 'node:module';
import {execFileSync} from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import assert from 'node:assert/strict';

const require = createRequire(import.meta.url);
const {chromium} = require(process.env.ATHERCAR_PLAYWRIGHT_MODULE || 'playwright');
const image = process.env.ATHERCAR_LEGAL_IMAGE;
if (process.env.APP_ENV !== 'test' || process.env.ATHERCAR_TEST_MODE !== '1' || !image) process.exit(2);
const output = process.env.ATHERCAR_LEGAL_EVIDENCE_DIR || fs.mkdtempSync(path.join(os.tmpdir(), 'ather-legal-'));
fs.mkdirSync(output, {recursive: true});
const containers = [], source = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
let browser, stage = 'startup';
const docker = (...args) => execFileSync('docker', args, {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']}).trim();
function start(published, contact = true, mode = 'invite', sourceImage = image) {
    const name = `ather-legal-${crypto.randomUUID()}`;
    const args = ['run', '--rm', '-d', '--name', name, '--entrypoint', 'php', '-p', '127.0.0.1::80',
        '-e', 'APP_ENV=test', '-e', 'ATHERCAR_TEST_MODE=1', '-e', 'SESSION_COOKIE_SECURE=0',
        '-e', 'EXPECTED_OIDC_ISSUER=https://legal-test.invalid/', '-e', `ATHERCAR_REGISTRATION_ENABLED=${mode}`];
    if (contact) args.push('-e', 'PRIVACY_CONTACT_EMAIL=privacy@example.invalid');
    args.push(sourceImage, 'tests/Browser/run-legal-pages-browser-fixture.php');
    if (published) args.push('--published');
    docker(...args); containers.push(name);
    return `http://127.0.0.1:${docker('port', name, '80/tcp').split(':').at(-1)}`;
}
async function ready(url) {
    for (let i = 0; i < 100; i++) {
        try { if ((await fetch(`${url}/signup.php`)).status === 200) return; } catch {}
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    throw new Error('Disposable legal server unavailable');
}
const normalize = text => text.replace(/\s+/gu, ' ').trim();
function expectedCopy(document, locale, contact = true) {
    return normalize(fs.readFileSync(path.join(source, `resources/legal/${document}.${locale}.md`), 'utf8')
        .split('\n').filter(line => contact || !line.includes('{{contact}}'))
        .filter(line => !/^\|[-| ]+\|$/.test(line))
        .map(line => line.replace(/^### /, '').replace(/^ *- /, '').replaceAll('**', '').replace(/\[([^\]]+)\]\(\/(privacy|terms)\)/g, '$1').replaceAll('|', ' ')).join(' ')
        .replaceAll('{{version}}', '1.0').replaceAll('{{effective_date}}', '2026-11-01').replaceAll('{{contact}}', 'privacy@example.invalid'));
}
function contrast(a, b) {
    const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
    const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (light + .05) / (dark + .05);
}
async function noOverflow(page) { assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `Overflow at ${stage}`); }
async function settled(page) { await page.evaluate(() => document.fonts.ready); }
async function legalLinks(page, count) { assert.equal(await page.locator('.legal-links a').count(), count); }
try {
    browser = await chromium.launch({headless: true, ...(process.env.ATHERCAR_CHROMIUM_EXECUTABLE ? {executablePath: process.env.ATHERCAR_CHROMIUM_EXECUTABLE} : {})});
    const off = start(false), on = start(true), noContact = start(true, false), open = start(true, true, '1');
    await Promise.all([off, on, noContact, open].map(ready));
    for (const document of ['privacy', 'terms']) {
        stage = `gate-off/${document}`;
        const offContext = await browser.newContext();
        const response = await offContext.request.get(`${off}/${document}`);
        assert.equal(response.status(), 404);
        assert.equal(await response.text(), await (await offContext.request.get(`${off}/unknown-legal-path`)).text());
        await offContext.close();
        for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({colorScheme: theme, reducedMotion: 'reduce'}), page = await context.newPage();
            for (const width of [320, 390, 768, 1280, 1440]) {
                stage = `${document}/${locale}/${theme}/${width}`;
                await page.setViewportSize({width, height: 1000});
                const response = await page.goto(`${on}/${document}?lang=${locale}`); await settled(page);
                assert.equal(response.status(), 200); assert.match(response.headers()['cache-control'], /public/);
                assert.equal(await page.locator('html').getAttribute('lang'), locale);
                assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
                assert.equal(await page.locator('meta[name="robots"]').getAttribute('content'), 'index,follow');
                assert.equal(await page.locator('h1').count(), 1);
                assert.equal(await page.locator('time').getAttribute('datetime'), '2026-11-01');
                assert.match(await page.locator('.legal-metadata').innerText(), /1\.0/);
                const actual = normalize(await page.locator('article > header, article > section').evaluateAll(nodes => nodes.map(node => {
                    const copy = node.cloneNode(true);
                    for (const block of copy.querySelectorAll('p,h1,h2,li,th,td')) block.append('\n');
                    for (const list of copy.querySelectorAll('ul,ol,table')) list.prepend('\n');
                    return copy.textContent;
                }).join(' ')));
                assert.equal(actual, expectedCopy(document, locale), 'Published wording must exactly match the approved copy substitutions');
                assert.equal(await page.locator('article > section').count(), document === 'privacy' ? 12 : 13);
                assert.equal(await page.locator('.legal-contents a').count(), document === 'privacy' ? 12 : 13);
                for (const link of await page.locator('.legal-contents a').all()) {
                    const target = await link.getAttribute('href'); assert.equal(await page.locator(target).count(), 1);
                    assert((await link.boundingBox()).height >= 44);
                }
                for (const selector of ['.legal-document', '.legal-contents a', '.legal-metadata']) {
                    const colors = await page.locator(selector).first().evaluate(el => {
                        let parent = el; while (getComputedStyle(parent).backgroundColor === 'rgba(0, 0, 0, 0)' && parent.parentElement) parent = parent.parentElement;
                        return [getComputedStyle(el).color, getComputedStyle(parent).backgroundColor];
                    });
                    assert(contrast(...colors) >= 4.5, `${selector} AA text contrast`);
                }
                await noOverflow(page);
                assert.equal(await page.locator('script:not([src]), style, [style], [onclick]').count(), 0);
                if ([390, 1440].includes(width)) await page.screenshot({path: path.join(output, `${document}-${locale}-${theme}-${width}.png`), fullPage: true});
            }
            await page.locator('.legal-contents a').first().focus(); await page.keyboard.press('Enter');
            // Following an in-page link updates the URL asynchronously; wait for it instead of racing it.
            await page.waitForURL(/#section-1$/, {timeout: 5000});
            assert.equal(new URL(page.url()).hash, '#section-1');
            await context.close();
        }
        const context = await browser.newContext(), page = await context.newPage();
        for (const locale of ['ar', 'en']) {
            await page.goto(`${noContact}/${document}?lang=${locale}`);
            assert.equal(await page.locator('a[href^="mailto:"]').count(), 0);
            assert(!(await page.locator('article').innerText()).includes('{{contact}}'));
            assert.equal(await page.locator('article > section').count(), document === 'privacy' ? 12 : 13);
        }
        assert.equal((await context.request.head(`${on}/${document}`)).status(), 200);
        assert.equal((await context.request.post(`${on}/${document}`)).status(), 405);
        await context.close();
        console.log(`PASS legal ${document}: gate-off normal 404; gate-on exact approved AR/EN wording, version/date, configured/null contact, anchors, AA, CSP and five widths`);
    }
    for (const published of [false, true]) for (const locale of ['ar', 'en']) {
        const base = published ? on : off, context = await browser.newContext(), page = await context.newPage();
        for (const route of [`/signin.php?lang=${locale}`, `/signup.php?lang=${locale}`, '/tests/Browser/legal-portfolio-preview.html', `/tests/Browser/owner-settings-visual.php?lang=${locale}#help`]) {
            stage = `links/${published}/${locale}/${route}`; await page.goto(base + route);
            await legalLinks(page, published ? 2 : 0);
            assert.equal(await page.locator('[name="accept_legal"]').count(), published && route.startsWith('/signup') ? 1 : 0);
            if (published && route.includes('settings')) assert.equal(await page.locator('#help .legal-links a').count(), 2);
            if (published && route.includes('portfolio')) assert.equal(await page.locator('footer .legal-links a').count(), 2);
            await page.setViewportSize({width: 320, height: 1000}); await noOverflow(page);
        }
        await context.close();
    }
    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const context = await browser.newContext({colorScheme: theme, reducedMotion: 'reduce'}), page = await context.newPage();
        for (const width of [320, 390, 768, 1280, 1440]) for (const invalid of [false, true]) {
            stage = `signup/${locale}/${theme}/${width}/${invalid}`;
            await page.setViewportSize({width, height: 1000});
            await page.goto(`${on}/signup.php?lang=${locale}${invalid ? '&legal=required' : ''}`); await settled(page);
            const checkbox = page.locator('#accept-legal');
            assert.equal(await checkbox.getAttribute('value'), '1'); assert.equal(await checkbox.getAttribute('required'), '');
            assert.equal(await checkbox.isChecked(), false);
            assert((await page.locator('.legal-consent-label').boundingBox()).height >= 44);
            assert.equal(await page.locator('#legal-error[role="alert"]').count(), invalid ? 1 : 0);
            assert.equal(await checkbox.getAttribute('aria-invalid'), invalid ? 'true' : null);
            if (invalid) {
                assert.equal(await checkbox.getAttribute('aria-describedby'), 'legal-error');
                const colors = await page.locator('#legal-error').evaluate(el => [getComputedStyle(el).color, getComputedStyle(el.closest('.af-card')).backgroundColor]);
                assert(contrast(...colors) >= 4.5);
            }
            await noOverflow(page);
            if ([390, 1440].includes(width)) await page.screenshot({path: path.join(output, `signup${invalid ? '-error' : ''}-${locale}-${theme}-${width}.png`), fullPage: true});
        }
        await page.locator('#invitation-code').focus(); await page.keyboard.press('Tab');
        assert(await page.locator('#accept-legal').evaluate(el => el === document.activeElement));
        assert.equal(await page.locator('#accept-legal').evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
        await context.close();
    }
    for (const [base, invite] of [[on, true], [open, false]]) {
        stage = `native-post/${invite}`;
        const context = await browser.newContext({javaScriptEnabled: false}), page = await context.newPage(); let posted;
        await page.route('**/owner_register.php', async route => {
            posted = new URLSearchParams(route.request().postData());
            await route.fulfill({status: 303, headers: {location: '/signup.php?legal=required'}});
        });
        await page.goto(`${base}/signup.php?lang=en`);
        if (invite) await page.locator('#invitation-code').fill('ABCD-EFGH-JKMN');
        await page.locator('button[type="submit"]').click(); assert.equal(posted, undefined, 'Unchecked consent must block native submission');
        await page.locator('#accept-legal').check();
        await Promise.all([page.waitForURL('**/signup.php?legal=required'), page.locator('button[type="submit"]').click()]);
        assert.deepEqual([...posted.keys()].sort(), invite ? ['accept_legal', 'csrf_token', 'invitation_code'] : ['accept_legal', 'csrf_token']);
        assert.equal(posted.get('accept_legal'), '1');
        await context.close();
    }
    stage = 'gate-off visual proof';
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}, reducedMotion: 'reduce', colorScheme: 'light'}), page = await context.newPage();
    await page.goto(`${off}/signup.php?lang=en`); await settled(page); const signup = await page.screenshot({fullPage: true});
    await page.goto(`${off}/tests/Browser/legal-portfolio-preview.html`); await settled(page); const footer = await page.locator('.portfolio-footer').screenshot();
    if (process.env.ATHERCAR_LEGAL_BASELINE_URL) {
        const baseline = process.env.ATHERCAR_LEGAL_BASELINE_URL;
        await page.goto(`${baseline}/signup.php?lang=en`); await settled(page); assert.deepEqual(await page.screenshot({fullPage: true}), signup);
        await page.goto(`${baseline}/tests/Browser/legal-portfolio-preview.html`); await settled(page); assert.deepEqual(await page.locator('.portfolio-footer').screenshot(), footer);
    }
    await page.setContent(`<h1>Publication gate off</h1><p>Sign-up and public footer: no legal links or checkbox. Pixel comparison against origin/main recorded separately.</p><img width="1400" alt="Unchanged signup" src="data:image/png;base64,${signup.toString('base64')}"><h2>Public portfolio footer</h2><img width="1400" alt="Unchanged footer" src="data:image/png;base64,${footer.toString('base64')}">`);
    await page.screenshot({path: path.join(output, 'gate-off-unchanged.png'), fullPage: true}); await context.close();
    console.log('PASS legal links on/off at signup, signin, public footer and Settings Help; invite/open exact native consent POSTs; AR/EN checkbox/error, keyboard, AA and no overflow');
    console.log(`PASS legal screenshots and publication-off proof${process.env.ATHERCAR_LEGAL_BASELINE_URL ? '; baseline pixel equality' : ''}`);
} catch (error) {
    console.error(`FAIL legal browser ${stage}: ${error instanceof assert.AssertionError ? error.message : error.name}`);
    process.exitCode = 1;
} finally {
    if (browser) await browser.close();
    for (const name of containers.reverse()) docker('rm', '-f', name);
}
