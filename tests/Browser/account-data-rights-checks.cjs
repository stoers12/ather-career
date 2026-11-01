/* L-03 presentation contract, native forms, theme/locale and screenshot evidence. */
'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');

async function contrast(page) {
    const samples = await page.locator('#data, [data-account-deletion-scheduled], [data-account-notice]').evaluateAll(roots => roots.flatMap(root => [root, ...root.querySelectorAll('*')]).filter(n => {
        const r = n.getBoundingClientRect();
        return r.width && r.height && [...n.childNodes].some(t => t.nodeType === Node.TEXT_NODE && t.textContent.trim());
    }).map(n => {
        let parent = n, bg = 'rgb(255, 255, 255)';
        while (parent) {
            const value = getComputedStyle(parent).backgroundColor;
            if (value !== 'rgba(0, 0, 0, 0)') { bg = value; break; }
            parent = parent.parentElement;
        }
        const s = getComputedStyle(n);
        return { text: n.textContent.slice(0, 60), color: s.color, bg, large: parseFloat(s.fontSize) >= 24 || (parseFloat(s.fontSize) >= 18.66 && Number(s.fontWeight) >= 700) };
    }));
    const luminance = rgb => rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => v / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
    for (const sample of samples) {
        const a = luminance(sample.color), b = luminance(sample.bg);
        const ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
        assert.ok(ratio >= (sample.large ? 3 : 4.5), `L-03 contrast ${ratio.toFixed(2)}: ${sample.text}`);
    }
}

module.exports = async function dataRightsChecks(browser, base, out) {
    const fixture = options => `${base}/tests/Browser/account-data-rights-visual.php?${new URLSearchParams(options)}${(options.view || 'settings') === 'settings' ? '#data' : ''}`;
    const cases = [
        ['settings-locked', { view: 'settings' }],
        ['settings-recent', { view: 'settings', recent: '1' }],
        ['settings-confirmation', { view: 'settings', recent: '1', notice: 'confirmation' }],
        ['settings-reauthenticate', { view: 'settings', notice: 'reauthenticate' }],
        ['scheduled-recent', { view: 'scheduled', recent: '1' }],
        ['scheduled-locked', { view: 'scheduled', notice: 'reauthenticate' }],
        ['signin-notice', { view: 'signin', account: 'deletion_scheduled' }],
        ['dashboard-notice', { view: 'dashboard', account: 'deletion_cancelled' }],
    ];
    for (const lang of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const context = await browser.newContext({ colorScheme: theme, reducedMotion: 'reduce', timezoneId: 'Asia/Amman' });
        await context.addInitScript(theme => localStorage.setItem('ather.evidenceHub.theme', theme), theme);
        const page = await context.newPage();
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        for (const [name, options] of cases) {
            await page.goto(fixture({ lang, ...options }));
            await page.evaluate(() => document.fonts.ready);
            assert.equal(await page.locator('html').getAttribute('dir'), lang === 'ar' ? 'rtl' : 'ltr');
            assert.equal(await page.locator('script:not([src]), [style]').count(), 0);
            if (options.view === 'settings') {
                await page.locator('#data:visible').waitFor();
                assert.equal(await page.locator('[data-owner-settings-link="data"]').getAttribute('aria-current'), 'location');
                if (options.recent === '1') {
                    const summary = page.locator('.owner-data-confirmation summary');
                    if (!options.notice) {
                        assert.equal(await page.locator('[name="confirmation"]').isVisible(), false);
                        await summary.focus(); await page.keyboard.press('Enter');
                    }
                    assert.equal(await page.locator('[name="confirmation"]').isVisible(), true);
                    if (options.notice) assert.equal(await page.locator('[name="confirmation"]').getAttribute('aria-describedby'), 'data-error');
                }
            }
            if (options.view === 'scheduled') {
                assert.equal(await page.locator('aside, .admin-sidebar').count(), 0);
                assert.equal(await page.locator('form').count(), 3);
                assert.equal(await page.locator('form').first().getAttribute('action'), '/owner_account_deletion.php');
                const expected = await page.evaluate(() => new Intl.DateTimeFormat(document.documentElement.lang, { dateStyle: 'long', calendar: 'gregory' }).format(new Date('2026-12-01T23:30:00Z')));
                assert.equal(await page.locator('time').textContent(), expected);
            }
            await contrast(page);
            for (const width of [320, 390, 768, 1280, 1440]) {
                await page.setViewportSize({ width, height: width <= 390 ? 844 : 1000 });
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${name}/${lang}/${theme}/${width}`);
                const small = await page.locator('#data button, #data summary, #data input:not([type="hidden"]), [data-account-deletion-scheduled] button').evaluateAll(nodes => nodes.filter(n => n.getBoundingClientRect().height && (n.getBoundingClientRect().height < 44 || n.getBoundingClientRect().width < 44)).map(n => n.outerHTML));
                assert.deepEqual(small, [], '44px controls');
                if (width === 390 || width === 1440) {
                    await page.mouse.move(0, 0);
                    await page.screenshot({ path: path.join(out, `l03-${name}-${lang}-${theme}-${width}.png`), fullPage: true });
                }
            }
            if (options.view === 'settings') {
                await page.evaluate(() => { localStorage.setItem('ather.owner.textSize', 'large'); });
                await page.reload();
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'large text');
                await page.evaluate(() => localStorage.removeItem('ather.owner.textSize'));
            }
        }
        for (const view of ['signin', 'dashboard']) {
            for (const account of ['unknown', 'deletion_scheduled', 'deletion_cancelled']) {
                await page.goto(fixture({ lang, view, account }));
                assert.equal(await page.locator('[data-account-notice]').count(), account === (view === 'signin' ? 'deletion_scheduled' : 'deletion_cancelled') ? 1 : 0);
            }
        }
        assert.deepEqual(errors, []);
        await context.close();
    }
    // Native submissions work with JavaScript disabled, with exactly the contract fields.
    for (const lang of ['ar', 'en']) {
        const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const page = await context.newPage();
        const token = 'a'.repeat(64);
        const actions = [
            [{ view: 'settings' }, '/owner_reauthenticate.php', { csrf_token: token, return: 'data' }],
            [{ view: 'settings', recent: '1' }, '/owner_data_export.php', { csrf_token: token }],
            [{ view: 'settings', recent: '1' }, '/owner_account_deletion.php', { csrf_token: token, action: 'request', confirmation: 'sami-ahmad' }],
            [{ view: 'scheduled' }, '/owner_account_deletion.php', { csrf_token: token, action: 'cancel' }],
            [{ view: 'scheduled' }, '/owner_reauthenticate.php', { csrf_token: token, return: 'deletion' }],
            [{ view: 'scheduled', recent: '1' }, '/owner_data_export.php', { csrf_token: token }],
            [{ view: 'scheduled' }, '/owner_logout.php', { csrf_token: token }],
        ];
        for (const [options, url, expected] of actions) {
            await page.goto(fixture({ lang, ...options }));
            if (expected.confirmation) {
                await page.locator('summary').filter({ hasText: lang === 'ar' ? 'مراجعة طلب الحذف' : 'Review account deletion' }).click();
                await page.locator('[name="confirmation"]').fill(expected.confirmation);
            }
            const request = page.waitForRequest(r => r.url() === base + url && r.method() === 'POST');
            await page.route(base + url, route => route.fulfill({ status: 200, body: 'Synthetic submission received' }));
            await page.locator(`form[action="${url}"] button`).click();
            const submitted = await request;
            assert.deepEqual(Object.fromEntries(new URLSearchParams(submitted.postData())), expected);
            await page.unroute(base + url);
        }
        await context.close();
    }
    console.log('L03_DATA_RIGHTS_BROWSER_PASSED: ar/en, light/dark, 320/390/768/1280/1440, keyboard, AA contrast, 44px, local dates, strict notices, all native POST field sets without JavaScript.');
};
