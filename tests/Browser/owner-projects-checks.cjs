'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
module.exports = async function (browser, base, out, contrast) {
    const fixture = (locale, state = '') => `${base}/tests/Browser/owner-projects-visual.php?lang=${locale}&state=${state}`;
    const step = (page, number) => page.locator(`[data-project-step-link="${number}"]`).click();
    const capture = async (page, name) => { await page.evaluate(async () => { await document.fonts.ready; document.activeElement.blur(); window.scrollTo(0, 0); }); await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true }); };
    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const c = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: theme, reducedMotion: 'reduce' });
        await c.addInitScript(theme => localStorage.setItem('ather.evidenceHub.theme', theme), theme);
        const page = await c.newPage(); const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.goto(fixture(locale));
        assert.equal(await page.locator('[data-project-card]:visible').count(), 4);
        assert.deepEqual(await page.locator('[data-project-count]').allTextContents(), ['4', '3', '1']);
        await page.locator('[data-project-filter="draft"]').click();
        assert.equal(await page.locator('[data-project-card]:visible').count(), 1);
        await page.locator('[data-project-filter="all"]').click();
        await page.locator('#project-search').fill('pandas');
        assert.equal(await page.locator('[data-project-card]:visible').count(), 1, 'search includes technology hidden behind +N');
        assert.deepEqual(await page.locator('[data-project-count]').allTextContents(), ['1', '1', '0']);
        await page.locator('#project-search').fill('no matching project');
        assert.equal(await page.locator('[data-project-no-results]').isVisible(), true);
        await page.locator('[data-project-reset]').click();
        const menu = page.locator('.owner-project-menu').first();
        await menu.locator('summary').focus(); await page.keyboard.press('Enter');
        await page.keyboard.press('Tab');
        assert.equal(await menu.locator('a').first().evaluate(n => n === document.activeElement), true);
        await page.keyboard.press('Tab');
        assert.equal(await menu.locator('a').last().evaluate(n => n === document.activeElement), true);
        await page.keyboard.press('Escape'); assert.equal(await menu.evaluate(n => n.open), false);
        assert.equal(await menu.locator('summary').evaluate(n => n === document.activeElement), true);
        assert.equal(await page.locator('script:not([src]), [style]').count(), 0);
        await contrast(page);
        if (locale === 'ar' || theme === 'light') await capture(page, `projects-${locale}-${theme}-1440`);
        for (const view of ['grid', 'list']) {
            await page.locator(`[data-project-view="${view}"]`).click();
            for (const size of ['default', 'large']) {
                await page.evaluate(size => document.body.dataset.ownerTextSize = size, size);
                for (const width of [390, 768, 1280, 1920, 2560]) {
                    await page.setViewportSize({ width, height: 1000 });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${locale}/${theme}/${view}/${size}/${width}`);
                    if (view === 'grid' && size === 'default' && width === 390 && locale === 'ar') await capture(page, `projects-ar-${theme}-390`);
                }
            }
        }
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto(fixture(locale, 'edit'));
        assert.equal(await page.locator('[data-project-step]:visible').count(), 1);
        await page.locator('[data-project-step-link="1"]').focus(); await page.keyboard.press('Tab'); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#project-step-2').isVisible(), true);
        for (const number of [1, 2, 3]) {
            await step(page, number); await contrast(page);
            if (locale === 'ar' && theme === 'light') await capture(page, `project-editor-ar-light-step-${number}-1440`);
            if (locale === 'en' && theme === 'light' && number === 1) await capture(page, 'project-editor-en-light-1440');
            for (const size of ['default', 'large']) {
                await page.evaluate(size => document.body.dataset.ownerTextSize = size, size);
                for (const width of [390, 768, 1280, 1920, 2560]) {
                    await page.setViewportSize({ width, height: 1000 });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `editor ${locale}/${theme}/${number}/${size}/${width}`);
                    if (number === 1 && size === 'default' && width === 390 && locale === 'ar') await capture(page, `project-editor-ar-${theme}-390`);
                }
            }
            await page.evaluate(() => document.body.dataset.ownerTextSize = 'default');
            await page.setViewportSize({ width: 1440, height: 1000 });
        }
        await step(page, 1);
        await page.locator('#title').fill('<img src=x onerror=alert(1)>');
        assert.equal(await page.locator('[data-project-preview-title]').textContent(), '<img src=x onerror=alert(1)>');
        assert.equal(await page.locator('[data-project-preview] img').count(), 0, 'preview uses text nodes');
        await page.locator('#title').fill(''); await step(page, 2);
        await page.locator('.owner-project-form button[type="submit"]').click();
        assert.equal(await page.locator('#project-step-1').isVisible(), true, 'native required validation reveals step 1');
        await page.goto(fixture(locale, 'error'));
        assert.equal(await page.locator('#project-step-2').isVisible(), true);
        await page.waitForFunction(() => document.activeElement.id === 'live_url');
        assert.equal(await page.locator('#live_url').getAttribute('aria-invalid'), 'true');
        if (locale === 'ar' && theme === 'light') await capture(page, 'project-validation-ar-light-1440');
        await page.goto(fixture(locale, 'new'));
        if (locale === 'ar' && theme === 'light') await capture(page, 'project-new-ar-light-1440');
        assert.deepEqual(errors, []); await c.close();
        const native = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 }, colorScheme: theme });
        const p = await native.newPage();
        await p.goto(fixture(locale));
        assert.equal(await p.locator('[data-project-tools]').isVisible(), false);
        assert.equal(await p.locator('[data-project-card]:visible').count(), 4);
        await p.locator('.owner-project-menu > summary').first().focus(); await p.keyboard.press('Enter');
        assert.equal(await p.locator('.owner-project-menu a').first().isVisible(), true, 'native menu works without JavaScript');
        assert.equal(await p.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        if (locale === 'ar') await capture(p, `projects-no-js-ar-${theme}-390`);
        await p.goto(fixture(locale, 'edit'));
        assert.equal(await p.locator('[data-project-step]:visible').count(), 3);
        assert.equal(await p.locator('[data-project-preview]').isVisible(), false);
        assert.equal(await p.locator('[data-project-next]').isVisible(), false);
        assert.equal(await p.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        if (locale === 'ar') await capture(p, `project-editor-no-js-ar-${theme}-390`);
        await native.close();
    }
    console.log('OWNER_PROJECTS_VISUAL_PASSED: real filter/search counts; +N search; grid/list; keyboard menu; native no-JS; steps; escaped preview; 422 focus; AA contrast; ar/en themes and large text at 390 768 1280 1920 2560.');
    const session = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
    if (process.env.EVIDENCE_HUB_ACTION_TEST_URL) assert.ok(session);
    if (!session) return;
    assert.equal(process.env.EVIDENCE_HUB_ACTION_TEST_URL, base, 'writes require the disposable gate server');
    const c = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    await c.addCookies([{ name: 'portfolio_owner_session', value: session, url: base }]);
    const p = await c.newPage();
    const url = `${base}/owner_projects.php?lang=en`;
    let id; let csrf;
    async function submit(expected = 303) {
        const response = p.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/owner_projects.php');
        await p.locator('.owner-project-form button[type="submit"]').click();
        assert.equal((await response).status(), expected);
        await p.waitForLoadState('load');
    }
    async function edit() { await p.goto(url + '&edit=' + id); }
    try {
        await p.goto(url + '&add=1');
        csrf = await p.locator('.owner-project-form [name="csrf_token"]').inputValue();
        const title = 'Synthetic FE-04 ' + Date.now();
        await p.locator('#title').fill(title); await p.locator('#category').fill('Data Analysis');
        await p.locator('#description').fill('Synthetic FE-04 description retained across steps.');
        await step(p, 2); await p.locator('#technologies').fill('PHP\nSQL\nCSS\nJavaScript');
        await p.locator('#live_url').fill('https://example.invalid/demo');
        await submit();
        const card = p.locator('[data-project-card]').filter({ has: p.getByRole('heading', { name: title, exact: true }) });
        id = new URL(await card.locator('.owner-project-edit').getAttribute('href'), base).searchParams.get('edit');
        await edit();
        assert.equal(await p.locator('#title').inputValue(), title);
        assert.equal(await p.locator('#description').inputValue(), 'Synthetic FE-04 description retained across steps.');
        assert.equal(await p.locator('#live_url').inputValue(), 'https://example.invalid/demo');
        let fields = await p.locator('.owner-project-form').evaluate(f => Object.fromEntries(new FormData(f)));
        delete fields.project_image; delete fields.live_url;
        assert.equal((await c.request.post(url, { form: fields })).status(), 200, 'legacy form without live_url keeps it');
        await edit(); assert.equal(await p.locator('#live_url').inputValue(), 'https://example.invalid/demo');
        await step(p, 2); await p.locator('#live_url').fill(''); await submit();
        await edit(); assert.equal(await p.locator('#live_url').inputValue(), '');
        await step(p, 2); await p.locator('#live_url').fill('ftp://example.invalid/rejected');
        await p.locator('.owner-project-form').evaluate(f => f.noValidate = true); await submit(422);
        assert.equal(await p.locator('#project-step-2').isVisible(), true);
        assert.equal(await p.locator('#live_url').inputValue(), 'ftp://example.invalid/rejected');
        assert.equal(await p.locator('#title').inputValue(), title);
        await p.locator('#live_url').fill('https://example.invalid/final');
        await p.locator('#project_image').setInputFiles({ name: 'invalid.png', mimeType: 'image/png', buffer: Buffer.from('invalid image') });
        await submit(422);
        assert.equal(await p.locator('#project-step-2').isVisible(), true);
        assert.equal(await p.locator('#title').inputValue(), title);
        assert.equal(await p.locator('#live_url').inputValue(), 'https://example.invalid/final');
        assert.equal(await p.locator('#project_image').getAttribute('aria-invalid'), 'true');
        const png = await p.evaluate(() => { const canvas = document.createElement('canvas'); canvas.width = 1200; canvas.height = 675; const ctx = canvas.getContext('2d'); ctx.fillStyle = '#235d45'; ctx.fillRect(0, 0, 1200, 675); return canvas.toDataURL('image/png').split(',')[1]; });
        await p.locator('#project_image').setInputFiles({ name: 'synthetic-fe04.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
        await submit(); await edit(); await step(p, 2);
        assert.equal(await p.locator('.current-project-image img').evaluate(n => n.complete && n.naturalWidth > 0), true);
        await p.locator('[name="remove_image"]').click();
        const remove = p.locator('.confirm-ok'); await remove.click();
        assert.equal(await p.locator('[name="remove_image"]').isChecked(), true);
        await submit(); await edit(); await step(p, 2);
        assert.equal(await p.locator('.current-project-image img').count(), 0);
        assert.equal(await p.locator('#live_url').inputValue(), 'https://example.invalid/final');
        console.log('OWNER_PROJECTS_ACTIONS_PASSED: step 2 saves step 1; live_url save/keep/clear/reject; 422 field retention and correct step; upload rejection retention; real image upload/remove.');
    } finally {
        if (id) assert.equal((await c.request.post(url, { form: { action: 'delete', id, csrf_token: csrf } })).status(), 200, 'remove only the new synthetic project');
        await c.close();
    }
};
