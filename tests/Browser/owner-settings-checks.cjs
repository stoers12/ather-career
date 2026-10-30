/* FE-02 extends the registered Owner workspace gate with Settings and B-03. */
'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
module.exports = async function settingsChecks(browser, base, out, contrast) {
    const settings = locale => `${base}/tests/Browser/owner-settings-visual.php?lang=${locale}`;
    const noOverflow = async (page, label) => assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, label);
    async function sidebarCurrent(page, locale, section) {
        const href = `/owner_settings.php?lang=${locale}`;
        const current = section === 'help' ? href + '#help' : href;
        const other = section === 'help' ? href : href + '#help';
        await page.locator(`.owner-nav-item[href="${current}"][aria-current="page"]`).waitFor({ state: 'attached' });
        assert.equal(await page.locator('.owner-nav-item[aria-current="page"]').count(), 1, 'exactly one sidebar item is current');
        assert.equal(await page.locator(`.owner-nav-item[href="${other}"]`).getAttribute('aria-current'), null, 'the other Settings destination is not current');
    }
    const session = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
    async function context(options = {}) {
        const c = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1440, height: 1000 }, ...options });
        if (session) await c.addCookies([{ name: 'portfolio_owner_session', value: session, url: base }]);
        return c;
    }
    async function capture(page, name) {
        await page.mouse.move(0, 0); await page.evaluate(() => document.fonts.ready);
        await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true });
    }
    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const c = await context({ colorScheme: theme });
        await c.addInitScript(theme => {
            if (!sessionStorage.getItem('fe02-initialized')) {
                localStorage.setItem('ather.evidenceHub.theme', theme);
                sessionStorage.setItem('fe02-initialized', '1');
            }
            const observer = new MutationObserver(() => {
                if (window.__settingsFirstLayout || !document.querySelector('.admin-layout')) return;
                window.__settingsFirstLayout = [document.body.getAttribute('data-owner-text-size'), document.body.getAttribute('data-owner-motion')];
                observer.disconnect();
            });
            observer.observe(document, { childList: true, subtree: true });
        }, theme);
        const page = await c.newPage();
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await page.goto(settings(locale));
        assert.equal(await page.locator('.owner-settings-nav a').count(), 2);
        assert.equal(await page.locator('.owner-settings-nav a[href="#appearance"]').getAttribute('aria-current'), 'location');
        assert.equal(await page.locator('.owner-nav-item[href="/owner_settings.php?lang=' + locale + '"]').getAttribute('aria-current'), 'page');
        assert.equal(await page.locator('.owner-nav-item[href="/owner_settings.php?lang=' + locale + '#help"]').count(), 1);
        assert.equal(await page.locator('.owner-toolbar .owner-language').count(), 0);
        assert.deepEqual(await page.evaluate(() => window.__settingsFirstLayout), ['default', 'system']);
        await contrast(page);
        await capture(page, `settings-${locale}-${theme}-1440`);
        await page.goto(`${base}/owner_settings.php?lang=${locale}#help`);
        await sidebarCurrent(page, locale, 'help');
        await page.locator('[data-owner-settings-link="appearance"]').click();
        await sidebarCurrent(page, locale, 'appearance');
        await page.locator(`.owner-nav-item[href="/owner_settings.php?lang=${locale}#help"]`).click();
        await sidebarCurrent(page, locale, 'help');
        if (locale === 'ar') {
            await page.waitForFunction(() => document.querySelector('#appearance').hidden);
            assert.equal(await page.locator('#help').isVisible(), true);
            assert.equal(await page.locator('.owner-settings-question').count(), 4);
            await contrast(page); await capture(page, `help-ar-${theme}-1440`);
            const question = page.locator('.owner-settings-question').first();
            await question.locator('summary').focus(); await page.keyboard.press('Enter');
            assert.equal(await question.evaluate(n => n.open), true);
        }
        await page.locator('[data-owner-settings-link="appearance"]').click();
        await sidebarCurrent(page, locale, 'appearance');
        for (const size of ['default', 'large']) {
            await page.locator('[data-owner-preference="text_size"]').selectOption(size);
            for (const width of [390, 768, 1280, 1920, 2560]) {
                await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
                await noOverflow(page, `Settings ${locale}/${theme}/${size}/${width}`);
                if (locale === 'ar' && width === 390 && size === 'default') await capture(page, `settings-ar-${theme}-390`);
            }
            if (size === 'large') {
                await page.setViewportSize({ width: 1440, height: 1000 });
                if (locale === 'ar') await capture(page, `settings-ar-large-${theme}-1440`);
            }
        }
        await page.locator('[data-owner-preference="motion"]').selectOption('reduce');
        await page.reload();
        assert.deepEqual(await page.evaluate(() => window.__settingsFirstLayout), ['large', 'reduce'], 'saved text size and motion apply before the first workspace content');
        assert.equal(await page.locator('body').getAttribute('data-owner-text-size'), 'large');
        assert.equal(await page.locator('body').getAttribute('data-owner-motion'), 'reduce');
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        assert.equal(await page.locator('.owner-nav-group > summary svg').first().evaluate(n => getComputedStyle(n).transitionDuration), '0s', 'explicit reduce overrides an OS that allows motion');
        await page.locator('[data-owner-preference=motion]').selectOption('system');
        assert.equal(await page.locator('.owner-nav-group > summary svg').first().evaluate(n => getComputedStyle(n).transitionDuration), '0.16s', 'system restores the shared transition when the OS allows it');
        await page.locator('[data-owner-preference=motion]').selectOption('reduce');
        for (const choice of ['dark', 'light']) {
            await page.locator(`[data-owner-appearance][value="${choice}"]`).check();
            assert.equal(await page.evaluate(() => localStorage.getItem('ather.evidenceHub.theme')), choice);
            await page.reload();
            assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'), choice);
            assert.equal(await page.locator(`[data-owner-appearance][value="${choice}"]`).isChecked(), true);
        }
        await page.locator('[data-owner-appearance][value="system"]').check();
        assert.equal(await page.evaluate(() => localStorage.getItem('ather.evidenceHub.theme')), null, 'system removes the existing theme key');
        await page.reload();
        assert.equal(await page.evaluate(() => localStorage.getItem('ather.evidenceHub.theme')), null);
        assert.equal(await page.locator('[data-owner-appearance][value=system]').isChecked(), true);
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.waitForFunction(() => document.body.getAttribute('data-evidence-hub-theme') === 'dark');
        await page.emulateMedia({ colorScheme: 'light' });
        await page.waitForFunction(() => document.body.getAttribute('data-evidence-hub-theme') === 'light');
        await page.locator('[data-owner-theme="dark"]').click();
        assert.equal(await page.locator('[data-owner-appearance][value="dark"]').isChecked(), true, 'toolbar and cards share one controller');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.locator('[data-owner-nav-toggle]').click();
        await page.locator(`.owner-nav-item[href="/owner_settings.php?lang=${locale}"]`).click();
        await page.locator('.owner-language-options').waitFor({ state: 'visible' });
        assert.equal(await page.locator('.owner-language-options a').count(), 2, 'menu then Settings reaches language in two taps');
        await page.locator('[data-owner-preference="text_size"]').selectOption('large');
        for (const width of [390, 768, 1280, 1920, 2560]) {
            await page.setViewportSize({ width, height: 1000 });
            await page.goto(`${base}/tests/Browser/owner-workspace-visual.php?lang=${locale}`);
            await noOverflow(page, `Overview ${locale}/${theme}/large/${width}`);
        }
        assert.deepEqual(errors, []);
        await c.close();
    }
    for (const locale of ['ar', 'en']) {
        const c = await context({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const page = await c.newPage(); await page.goto(`${base}/owner_settings.php?lang=${locale}#help`);
        await sidebarCurrent(page, locale, 'appearance');
        assert.equal(await page.locator('#appearance').isVisible(), true);
        assert.equal(await page.locator('#help').isVisible(), true);
        assert.equal(await page.locator('[data-owner-browser-preference] input:visible,[data-owner-browser-preference] select:visible,[data-owner-theme]:visible').count(), 0, 'no non-working preference control without JavaScript');
        const alternate = locale === 'ar' ? 'en' : 'ar';
        await page.locator(`.owner-language-options a[hreflang="${alternate}"]`).click();
        await page.waitForLoadState('domcontentloaded');
        assert.equal(await page.locator('html').getAttribute('lang'), alternate, 'native GET language link works');
        await page.reload();
        assert.equal(await page.locator('html').getAttribute('lang'), alternate);
        await page.locator('.owner-settings-nav a[href="#help"]').click();
        assert.equal(new URL(page.url()).hash, '#help');
        await sidebarCurrent(page, alternate, 'appearance');
        await page.locator('.owner-settings-question').first().locator('summary').click();
        assert.equal(await page.locator('.owner-settings-question').first().evaluate(n => n.open), true);
        await noOverflow(page, 'no-JS Settings');
        if (locale === 'en') {
            await page.goto(settings('ar'));
            await page.screenshot({ path: path.join(out, 'settings-ar-no-js-390.png'), fullPage: true });
        }
        await c.close();
    }
    // The full required gate supplies the disposable Apache Owner session.
    if (process.env.EVIDENCE_HUB_ACTION_TEST_URL) assert.ok(session, 'unread lifecycle requires the gate Owner session');
    if (session) {
        const c = await context(); const page = await c.newPage();
        await page.goto(`${base}/owner_settings.php?lang=en`);
        const token = await page.locator('input[name="csrf_token"]').first().inputValue();
        const fixture = `${base}/tests/Browser/owner-unread-visual.php`;
        let seeded = false;
        try {
            assert.equal(await page.locator('.owner-unread-badge').count(), 0, 'no unread badge at zero');
            const response = await c.request.post(fixture, { form: { action: 'seed', csrf_token: token } });
            assert.equal(response.status(), 200); const { id } = await response.json(); seeded = true;
            assert.ok(Number.isInteger(id) && id > 0);
            for (const locale of ['ar', 'en']) {
                await page.goto(`${base}/owner_settings.php?lang=${locale}`);
                assert.equal(await page.locator('.owner-unread-badge').textContent(), '1');
                const name = locale === 'ar' ? 'الرسائل، غير المقروءة: 1' : 'Messages, 1 unread';
                assert.equal(await page.locator(`.owner-nav-item[href="/owner_messages.php?lang=${locale}"]`).getAttribute('aria-label'), name);
                assert.equal(await page.getByRole('link', { name, exact: true }).count(), 1, 'the unread link exposes the exact localized accessible name');
            }
            await page.goto(`${base}/owner_messages.php?lang=en`);
            await page.locator(`a[href="owner_messages.php?message=${id}"]`).click();
            assert.equal(await page.locator('.owner-unread-badge').count(), 0, 'opening the actual message marks it read');
            for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => localStorage.setItem('ather.evidenceHub.theme', theme), theme);
                for (const route of ['/owner_profile.php', '/owner_projects.php', '/owner_experiences.php', '/owner_messages.php', '/owner_publication.php', '/owner_settings.php', '/owner.php']) {
                    await page.setViewportSize({ width: 390, height: 844 });
                    const response = await page.goto(`${base}${route}?lang=${locale}`);
                    assert.equal(response.status(), 200, 'authorized Owner route renders successfully');
                    await page.evaluate(() => localStorage.setItem('ather.owner.textSize', 'large'));
                    await page.reload();
                    assert.equal(await page.locator('.owner-toolbar .owner-language').count(), 0, 'no toolbar language link on Owner routes');
                    assert.equal(await page.locator('body').getAttribute('data-owner-text-size'), 'large');
                    for (const width of [390, 768, 1280, 1920, 2560]) {
                        await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
                        await noOverflow(page, `${route}/${locale}/${theme}/large/${width}`);
                    }
                }
            }
            console.log('OWNER_UNREAD_BADGE_LIFECYCLE_PASSED: zero -> one unread -> opened/read; real Owner routes with large text.');
        } finally {
            if (seeded) assert.equal((await c.request.post(fixture, { form: { action: 'cleanup', csrf_token: token } })).status(), 200);
            await c.close();
        }
    }
    console.log('OWNER_SETTINGS_VISUAL_PASSED: two sections; appearance persistence/system; preferences before paint; no-JS language; ar/en themes and large-text layouts.');
};
