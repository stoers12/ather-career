'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
module.exports = async function (browser, base, out, contrast) {
    const fixture = (locale, state = '') => `${base}/tests/Browser/owner-experiences-visual.php?lang=${locale}&state=${state}`;
    const capture = async (page, name) => { await page.evaluate(async () => { await document.fonts.ready; document.activeElement.blur(); scrollTo(0, 0); }); await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true }); };
    const types = ['employment', 'training', 'internship', 'volunteer', 'leadership'];
    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const c = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: theme, reducedMotion: 'reduce', hasTouch: true });
        await c.addInitScript(theme => localStorage.setItem('ather.evidenceHub.theme', theme), theme);
        const page = await c.newPage(); const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        for (const state of ['', 'edit', 'add']) {
            await page.goto(fixture(locale, state));
            const view = state || 'list';
            assert.equal(await page.locator('script:not([src]), [style]').count(), 0);
            if (!state) {
                assert.deepEqual(await page.locator('.owner-experience-card h2').allTextContents(), ['AI Engineering', 'Data Analysis', 'CEO', 'Data Analysis', 'Ambassador', 'Student'], 'keep the supplied list order');
                assert.deepEqual([...new Set(await page.locator('[data-experience-type]').evaluateAll(ns => ns.map(n => n.dataset.experienceType)))].sort(), [...types].sort());
                assert.equal(await page.locator('.owner-experience-card').first().locator('.owner-experience-dates').textContent(), `2026-09 — ${locale === 'ar' ? 'حتى الآن' : 'Present'}`);
                assert.equal(await page.locator('.owner-experience-card form[data-confirm]').count(), 6);
            } else {
                assert.deepEqual(await page.locator('#experience_type option').evaluateAll(ns => ns.map(n => n.value)), ['', ...types]);
                assert.equal(await page.locator('#description').getAttribute('maxlength'), '1200');
                assert.equal(await page.locator('.owner-experience-saved').count(), 0, 'never claim saved without server success');
                if (state === 'edit') {
                    assert.equal(await page.locator('#end_month').isDisabled(), true);
                    await page.locator('#is_current').uncheck();
                    assert.equal(await page.locator('#end_month').isEnabled(), true);
                    assert.equal(await page.locator('#end_month').getAttribute('required'), '');
                    await page.locator('#end_month').fill('2026-10');
                    await page.locator('#is_current').check();
                    assert.equal(await page.locator('#end_month').inputValue(), '');
                    assert.equal(await page.locator('#end_month').isDisabled(), true);
                    assert.equal(await page.locator('.owner-experience-editor-delete form[data-confirm]').count(), 1);
                }
            }
            await contrast(page);
            if (locale === 'ar' || theme === 'light') await capture(page, `experience-${view}-${locale}-${theme}-1440`);
            for (const size of ['default', 'large']) {
                await page.evaluate(size => document.body.dataset.ownerTextSize = size, size);
                for (const width of [390, 768, 1280, 1920, 2560]) {
                    await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${locale}/${theme}/${view}/${size}/${width}`);
                    if (width === 390 && size === 'default' && locale === 'ar') await capture(page, `experience-${view}-ar-${theme}-390`);
                    const small = await page.locator('.owner-experiences a:visible, .owner-experiences button:visible, .owner-experiences .checkbox-field:visible').evaluateAll(ns => ns.filter(n => { const r = n.getBoundingClientRect(); return r.width < 43.5 || r.height < 43.5; }).map(n => n.outerHTML.slice(0, 150)));
                    assert.deepEqual(small, [], `44px targets ${locale}/${theme}/${view}/${size}/${width}`);
                }
            }
            await page.setViewportSize({ width: 1440, height: 1000 });
            await page.evaluate(() => document.body.dataset.ownerTextSize = 'default');
        }
        await page.goto(fixture(locale, 'error'));
        await page.waitForFunction(() => document.activeElement.id === 'organization');
        assert.equal(await page.locator('[role="alert"]').count(), 1);
        assert.equal(await page.locator('#role_title').inputValue(), 'AI Engineering');
        if (locale === 'ar' && theme === 'light') await capture(page, 'experience-validation-ar-light-1440');
        await page.goto(fixture(locale, 'conflict'));
        await page.waitForFunction(() => document.activeElement.id === 'end_month');
        assert.equal(await page.locator('#end_month').isEnabled(), true, 'a rejected end month stays correctable even if current is checked');
        assert.equal(await page.locator('#end_month').inputValue(), '2026-10');
        await page.goto(fixture(locale, 'saved'));
        assert.equal(await page.locator('.owner-experience-saved').textContent(), locale === 'ar' ? 'تم الحفظ' : 'Saved');
        await page.goto(fixture(locale, 'empty'));
        assert.equal(await page.locator('.empty-state a[href="owner_experiences.php?add=1"]').count(), 1);
        assert.deepEqual(errors, []); await c.close();
        const native = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, colorScheme: theme, hasTouch: true });
        const p = await native.newPage();
        for (const state of ['', 'edit']) {
            await p.goto(fixture(locale, state));
            if (state) {
                assert.equal(await p.locator('#end_month').isEnabled(), true);
                await p.locator('#is_current').uncheck(); await p.locator('#end_month').fill('2026-10');
                assert.equal(await p.locator('.owner-experience-form').evaluate(f => Object.fromEntries(new FormData(f)).end_month), '2026-10');
                await p.locator('#end_month').fill(''); await p.locator('#is_current').check();
                assert.equal(await p.locator('.owner-experience-form').evaluate(f => Object.fromEntries(new FormData(f)).is_current), '1');
            }
            assert.equal(await p.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            if (locale === 'ar' && theme === 'light') await capture(p, `experience-${state || 'list'}-no-js-ar-390`);
        }
        await native.close();
    }
    console.log('OWNER_EXPERIENCES_VISUAL_PASSED: ordered list; all five types; ar/en light/dark; AA contrast; large text at all five widths; 44px targets; native no-JS current/end controls; retained conflict and 422 focus; honest saved status.');
    const session = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
    if (process.env.EVIDENCE_HUB_ACTION_TEST_URL) assert.ok(session);
    if (!session) return;
    assert.equal(process.env.EVIDENCE_HUB_ACTION_TEST_URL, base, 'write only to the disposable gate server');
    for (const javaScriptEnabled of [true, false]) {
        const c = await browser.newContext({ javaScriptEnabled, viewport: { width: 1440, height: 1000 } });
        await c.addCookies([{ name: 'portfolio_owner_session', value: session, url: base }]);
        const p = await c.newPage(); const url = `${base}/owner_experiences.php?lang=en`;
        let id; let csrf;
        async function submit(expected = 303) {
            const response = p.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/owner_experiences.php');
            await p.locator('.owner-experience-form button[type="submit"]').click();
            assert.equal((await response).status(), expected); await p.waitForLoadState('load');
        }
        try {
            await p.goto(url + '&add=1');
            csrf = await p.locator('.owner-experience-form [name="csrf_token"]').inputValue();
            const title = 'Synthetic FE-05 ' + Date.now() + '-' + javaScriptEnabled;
            await p.locator('#role_title').fill(title); await p.locator('#organization').fill('Synthetic organization');
            await p.locator('#location').fill('Amman'); await p.locator('#experience_type').selectOption('volunteer');
            await p.locator('#start_month').fill('2025-01'); await p.locator('#is_current').check();
            await p.locator('#description').fill('Synthetic optional description.'); await submit();
            let card = p.locator('.owner-experience-card').filter({ has: p.getByRole('heading', { name: title, exact: true }) });
            id = new URL(await card.locator('a').getAttribute('href'), base).searchParams.get('edit');
            assert.equal(await card.locator('.owner-experience-dates').textContent(), '2025-01 — Present');
            await card.locator('a').click();
            assert.equal(await p.locator('#description').inputValue(), 'Synthetic optional description.');
            await p.locator('#is_current').uncheck(); await p.locator('#end_month').fill('2025-06');
            await p.locator('#experience_type').selectOption('leadership'); await p.locator('#role_title').fill(title + ' edited');
            await submit();
            card = p.locator('.owner-experience-card').filter({ has: p.getByRole('heading', { name: title + ' edited', exact: true }) });
            assert.equal(await card.getAttribute('data-experience-type'), 'leadership');
            assert.equal(await card.locator('.owner-experience-dates').textContent(), '2025-01 — 2025-06');
            await card.locator('a').click();
            await p.locator('#end_month').fill('2024-12'); await submit(422);
            for (const [field, value] of Object.entries({ role_title: title + ' edited', organization: 'Synthetic organization', location: 'Amman', experience_type: 'leadership', start_month: '2025-01', end_month: '2024-12', description: 'Synthetic optional description.' })) assert.equal(await p.locator('#' + field).inputValue(), value, `422 retains ${field}`);
            assert.equal(await p.locator('#end_month').getAttribute('aria-invalid'), 'true');
            if (javaScriptEnabled) await p.waitForFunction(() => document.activeElement.id === 'end_month');
            else assert.equal(await p.locator('[role="alert"]').count(), 1);
            if (!javaScriptEnabled) {
                await p.locator('#end_month').fill('2025-06'); await p.locator('#is_current').check(); await submit(422);
                assert.equal(await p.locator('#end_month').inputValue(), '2025-06', 'no-JS current/end conflict is retained by the server');
                assert.equal(await p.locator('#is_current').isChecked(), true);
            }
            await p.locator('#is_current').check();
            if (!javaScriptEnabled) await p.locator('#end_month').fill('');
            await submit();
            card = p.locator('.owner-experience-card').filter({ has: p.getByRole('heading', { name: title + ' edited', exact: true }) });
            assert.equal(await card.locator('.owner-experience-dates').textContent(), '2025-01 — Present');
            if (javaScriptEnabled) {
                await card.locator('form button').focus(); await p.keyboard.press('Enter');
                assert.equal(await p.locator('[role="dialog"]').isVisible(), true);
                await p.locator('.confirm-cancel').click(); assert.equal(await card.count(), 1);
                await card.locator('a').click();
                await p.locator('.owner-experience-editor-delete button').click();
                const removed = p.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/owner_experiences.php');
                await p.locator('.confirm-ok').click(); assert.equal((await removed).status(), 303); await p.waitForLoadState('load');
            } else {
                const removed = p.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/owner_experiences.php');
                await card.locator('form button').click(); assert.equal((await removed).status(), 303); await p.waitForLoadState('load');
            }
            assert.equal(await p.getByRole('heading', { name: title + ' edited', exact: true }).count(), 0); id = null;
            await p.goto(url + '&edit=4294967295'); assert.equal(await p.locator('[role="alert"]').textContent().then(t => t.includes('Experience record not found.')), true);
        } finally {
            if (id) assert.equal((await c.request.post(url, { form: { action: 'delete', id, csrf_token: csrf } })).status(), 200, 'remove only the synthetic record');
            await c.close();
        }
    }
    console.log('OWNER_EXPERIENCES_ACTIONS_PASSED: authenticated native add/edit/delete with and without JS; optional description; current/ended transitions; 422 retained values and error focus/announcement; keyboard delete/cancel from card and editor; missing id; synthetic cleanup.');
};
