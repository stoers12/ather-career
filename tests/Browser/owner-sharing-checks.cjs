'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
module.exports = async function (browser, base, out, contrast) {
    const fixture = (page, state, locale) => `${base}/tests/Browser/owner-sharing-visual.php?page=${page}&state=${state}&lang=${locale}`;
    const capture = async (p, name) => { await p.evaluate(async () => { await document.fonts.ready; document.activeElement.blur(); scrollTo(0, 0); }); await p.screenshot({ path: path.join(out, name + '.png'), fullPage: true }); };
    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const c = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: theme, hasTouch: true, reducedMotion: 'reduce' });
        await c.addInitScript(theme => {
            localStorage.setItem('ather.evidenceHub.theme', theme);
            Object.defineProperty(navigator, 'clipboard', { value: { writeText(text) { window.copied = text; return window.copyFails ? Promise.reject(new Error('Synthetic failure')) : Promise.resolve(); } } });
        }, theme);
        const p = await c.newPage(); const errors = []; p.on('pageerror', e => errors.push(e.message));
        for (const [page, states] of [['publication', ['no_slug', 'reserved', 'published', 'offline', 'configuration', 'error']], ['messages', ['selected', 'list', 'empty']]]) for (const state of states) {
            const response = await p.goto(fixture(page, state, locale));
            assert.equal(response.status(), state === 'configuration' ? 503 : 200);
            assert.equal(await p.locator('[style], script:not([src])').count(), 0);
            if (page === 'publication') {
                assert.equal(await p.locator('a[target="_blank"]').count(), state === 'published' ? 1 : 0, 'only a live URL has a View action');
                if (['no_slug', 'reserved', 'error'].includes(state)) assert.equal(await p.locator('form:has([name="action"][value="set_slug"])').count(), 1);
                if (state === 'error') {
                    assert.equal(await p.locator('#public_slug').inputValue(), 'Rejected <slug> & retained');
                    await p.waitForFunction(() => document.activeElement.hasAttribute('data-error-summary'));
                    assert.equal(await p.locator('#public_slug').getAttribute('aria-invalid'), 'true');
                }
                if (state === 'configuration') assert.match(await p.locator('[role="alert"]').textContent(), /PUBLIC_BASE_URL/);
                if (state === 'published') {
                    const button = p.locator('[data-copy-public-url]'); await button.focus(); await p.keyboard.press('Enter');
                    await p.waitForFunction(() => document.querySelector('#publication-copy-feedback').textContent !== '');
                    assert.equal(await p.evaluate(() => window.copied), 'https://portfolio.example.test/p/synthetic-portfolio');
                    assert.equal(await p.locator('#publication-copy-feedback').textContent(), locale === 'ar' ? 'تم نسخ رابط البورتفوليو.' : 'Portfolio link copied.');
                    await p.evaluate(() => window.copyFails = true); await button.click();
                    await p.locator('#publication-copy-feedback.error').waitFor();
                    assert.equal(await p.locator('#publication-copy-feedback').textContent(), locale === 'ar' ? 'تعذر نسخ الرابط. حدده وانسخه يدويًا.' : 'Copying the Portfolio link failed. Select and copy the link manually.');
                    await p.evaluate(() => window.copyFails = false);
                    await p.reload();
                }
            } else {
                assert.equal(await p.locator('textarea, [name="reply"]').count(), 0, 'no unsupported reply form');
                if (state === 'selected') {
                    assert.match(await p.locator('.owner-message-item').first().getAttribute('aria-label') || await p.locator('.owner-message-item').first().innerText(), locale === 'ar' ? /غير مقروءة/ : /Unread/);
                    const href = await p.locator('a[href^="mailto:"]').getAttribute('href');
                    assert.equal(href, `mailto:sender%2Bportfolio%40example.test?subject=${encodeURIComponent(locale === 'ar' ? 'رد: رسالة البورتفوليو' : 'Re: Portfolio message')}`);
                    assert.match(await p.locator('.owner-message-body').textContent(), /\n\n/);
                }
            }
            await contrast(p);
            if (locale === 'ar' && theme === 'light' || ['published', 'selected'].includes(state) && (locale === 'ar' || theme === 'light')) await capture(p, `${page}-${state}-${locale}-${theme}-1440`);
            for (const size of ['default', 'large']) {
                await p.evaluate(size => document.body.dataset.ownerTextSize = size, size);
                for (const width of [390, 768, 1280, 1920, 2560]) {
                    await p.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
                    assert.equal(await p.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${page}/${state}/${locale}/${theme}/${size}/${width}`);
                    const small = await p.locator(`.owner-${page} a:visible,.owner-${page} button:visible`).evaluateAll(ns => ns.filter(n => { const r = n.getBoundingClientRect(); return r.width < 43.5 || r.height < 43.5; }).map(n => n.outerHTML));
                    assert.deepEqual(small, [], '44px controls');
                    if (width === 390 && size === 'default' && locale === 'ar' && ['published', 'selected'].includes(state)) await capture(p, `${page}-${state}-ar-${theme}-390`);
                    if (width === 390 && state === 'selected') assert.ok(await p.locator('.owner-message-detail').evaluate(n => n.getBoundingClientRect().top >= document.querySelector('.owner-message-list').getBoundingClientRect().bottom));
                }
            }
            await p.setViewportSize({ width: 1440, height: 1000 });
        }
        assert.deepEqual(errors, []); await c.close();
        const native = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, colorScheme: theme });
        const n = await native.newPage();
        for (const [page, state] of [['publication', 'published'], ['publication', 'no_slug'], ['messages', 'selected']]) {
            await n.goto(fixture(page, state, locale));
            if (page === 'publication' && state === 'published') { assert.equal(await n.locator('[data-copy-public-url]').isHidden(), true); assert.equal(await n.locator('#publication-public-url').isVisible(), true); }
            assert.equal(await n.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            if (locale === 'ar' && theme === 'light') await capture(n, `${page}-${state}-no-js-ar-390`);
        }
        await native.close();
    }
    console.log('OWNER_SHARING_VISUAL_PASSED: five publication states; retained slug error; localized keyboard copy success/failure; messages selected/unread/empty; encoded mailto; no reply form; ar/en light/dark AA; all widths and large text; 44px targets; mobile list first; no-JS.');
    const session = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
    if (process.env.EVIDENCE_HUB_ACTION_TEST_URL) assert.ok(session);
    if (!session) return;
    assert.equal(process.env.EVIDENCE_HUB_ACTION_TEST_URL, base, 'write only to the disposable gate');
    for (const javaScriptEnabled of [true, false]) {
        const c = await browser.newContext({ javaScriptEnabled, viewport: { width: 1440, height: 1000 } });
        await c.addCookies([{ name: 'portfolio_owner_session', value: session, url: base }]);
        const p = await c.newPage(); let seeded = false; let csrf;
        const url = `${base}/owner_publication.php?lang=en`;
        try {
            await p.goto(url); csrf = await p.locator('[name="csrf_token"]').first().inputValue();
            const seed = await c.request.post(`${base}/tests/Browser/owner-sharing-actions.php`, { form: { action: 'seed', csrf_token: csrf } });
            assert.equal(seed.status(), 200); seeded = true; const { id } = await seed.json();
            await p.goto(url); assert.equal(await p.locator('[data-publication-state]').getAttribute('data-publication-state'), 'no_slug');
            await p.locator('#public_slug').fill('invalid--slug');
            await p.locator('#public_slug').evaluate(n => n.form.noValidate = true);
            await p.locator('.publication-slug-form button').click(); await p.waitForLoadState('load');
            assert.equal(await p.locator('#public_slug').inputValue(), 'invalid--slug');
            assert.equal(await p.locator('[role="alert"]').count(), 1);
            await p.locator('#public_slug').fill('synthetic-fe06-' + Date.now());
            await p.locator('.publication-slug-form button').click(); await p.waitForLoadState('load');
            assert.equal(await p.locator('[data-publication-state]').getAttribute('data-publication-state'), 'reserved');
            async function mutation(action, state) {
                const form = p.locator(`form:has([name="action"][value="${action}"])`);
                await form.locator('button').click(); if (javaScriptEnabled) await p.locator('.confirm-ok').click();
                await p.waitForLoadState('load'); assert.equal(await p.locator('[data-publication-state]').getAttribute('data-publication-state'), state);
            }
            await mutation('publish', 'published'); await mutation('unpublish', 'offline');
            assert.equal(await p.locator('a[target="_blank"]').count(), 0);
            await p.goto(`${base}/owner_messages.php?lang=en`);
            const item = p.locator(`a[href="owner_messages.php?message=${id}"]`);
            assert.equal(await item.getAttribute('data-message-state'), 'unread'); assert.match(await item.innerText(), /Unread/);
            await item.click(); await p.waitForLoadState('load');
            assert.equal(await p.locator(`a[href="owner_messages.php?message=${id}"]`).getAttribute('data-message-state'), 'read');
            assert.equal(await p.locator('.owner-message-body').textContent(), 'Synthetic <body> & text.\nSecond line.');
            assert.equal(await p.locator('.owner-message-body body, .owner-message-detail script, textarea').count(), 0);
            assert.equal(await p.locator('a[href^="mailto:"]').getAttribute('href'), 'mailto:fe06%2Breply%40example.invalid?subject=Re%3A%20Portfolio%20message');
            await p.locator('form:has([name="action"][value="mark_unread"]) button').click(); await p.waitForLoadState('load');
            assert.equal(await p.locator(`a[href="owner_messages.php?message=${id}"]`).getAttribute('data-message-state'), 'unread');
            assert.equal((await p.goto(`${base}/owner_messages.php?message=2147483647&lang=en`)).status(), 404);
            assert.match(await p.locator('[role="alert"]').textContent(), /Message not found/);
        } finally {
            if (seeded) assert.equal((await c.request.post(`${base}/tests/Browser/owner-sharing-actions.php`, { form: { action: 'cleanup', csrf_token: csrf } })).status(), 200);
            await c.close();
        }
    }
    console.log('OWNER_SHARING_ACTIONS_PASSED: real forms with/without JS; slug reject/retain/reserve; publish/unpublish; messages GET read and POST unread; missing-message 404; escaped content and encoded mailto; synthetic state restored.');
};
