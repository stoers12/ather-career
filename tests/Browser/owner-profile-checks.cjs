/* FE-03: synthetic visuals and authenticated writes in the disposable gate only. */
'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
module.exports = async function profileChecks(browser, base, out, contrast) {
    const fixture = locale => `${base}/tests/Browser/owner-profile-visual.php?lang=${locale}`;
    async function capture(page, name) {
        await page.evaluate(() => document.fonts.ready);
        await page.mouse.move(0, 0);
        await page.evaluate(() => { if (document.activeElement.closest('.owner-profile-nav')) document.activeElement.blur(); window.scrollTo(0, 0); });
        await page.screenshot({ path: path.join(out, name + '.png'), fullPage: true });
    }
    async function panel(page, id) {
        await page.locator(`[data-owner-profile-link="${id}"]`).click();
        assert.equal(await page.locator(`[data-owner-profile-panel="${id}"]`).isVisible(), true);
        assert.equal(await page.locator('[data-owner-profile-panel]:visible').count(), 1);
        assert.equal(await page.locator(`[data-owner-profile-link="${id}"]`).getAttribute('aria-current'), 'location');
    }
    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        const c = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: theme, reducedMotion: 'reduce' });
        await c.addInitScript(theme => localStorage.setItem('ather.evidenceHub.theme', theme), theme);
        const page = await c.newPage(); const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.goto(fixture(locale));
        assert.equal(await page.locator('#profile-form [name="action"][value="save_profile"]').count(), 1);
        // These remain native anchors, including their normal Tab/Enter keyboard behavior.
        await page.locator('[data-owner-profile-link="basics"]').focus();
        await page.keyboard.press('Tab');
        assert.equal(await page.locator('[data-owner-profile-link="about"]').evaluate(n => n === document.activeElement), true);
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('#about').isVisible(), true);
        await page.locator('[data-owner-profile-link="about"]').focus();
        await page.keyboard.press('Tab'); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#skills').isVisible(), true);
        await page.locator('[data-owner-profile-link="skills"]').focus();
        await page.keyboard.press('Shift+Tab'); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#about').isVisible(), true);
        for (const id of ['basics', 'about', 'skills']) {
            await panel(page, id); await contrast(page);
            if (locale === 'ar' || (locale === 'en' && theme === 'light' && id === 'basics')) await capture(page, `profile-${locale}-${theme}-${id}-1440`);
            for (const size of ['default', 'large']) {
                await page.evaluate(size => document.body.setAttribute('data-owner-text-size', size), size);
                for (const width of [390, 768, 1280, 1920, 2560]) {
                    await page.setViewportSize({ width, height: width === 390 ? 844 : 1000 });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${locale}/${theme}/${id}/${size}/${width}`);
                    if (width === 390 && locale === 'ar' && size === 'default' && id === 'basics') await capture(page, `profile-ar-${theme}-390`);
                }
                // The optional fields must also fit when their native disclosure is open.
                if (id === 'basics') {
                    await page.locator('.owner-profile-extra > summary').click();
                    for (const width of [390, 768, 1280, 1920, 2560]) {
                        await page.setViewportSize({ width, height: 1000 });
                        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `expanded details ${locale}/${theme}/${size}/${width}`);
                    }
                    await page.locator('.owner-profile-extra > summary').click();
                }
            }
            await page.evaluate(() => document.body.setAttribute('data-owner-text-size', 'default'));
            await page.setViewportSize({ width: 1440, height: 1000 });
        }
        await panel(page, 'basics');
        await page.locator('#full_name').fill('');
        await panel(page, 'about');
        await page.locator('#profile-form button[type="submit"]').click();
        assert.equal(await page.locator('#basics').isVisible(), true, 'native validation reveals a required field in another panel');
        await page.waitForFunction(() => document.activeElement.id === 'full_name');
        await page.locator('#full_name').fill('Sami Ahmad');
        await page.goto(fixture(locale) + '&state=error#basics');
        assert.equal(await page.locator('#about').isVisible(), true, 'first server error overrides the requested panel');
        await page.waitForFunction(() => document.activeElement.id === 'linkedin_url');
        assert.equal(await page.locator('[data-error-summary]').getAttribute('role'), 'alert');
        if (locale === 'ar' && theme === 'light') await capture(page, 'profile-ar-validation-1440');
        assert.deepEqual(errors, []);
        await c.close();
    }
    for (const locale of ['ar', 'en']) {
        const c = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
        const page = await c.newPage(); await page.goto(fixture(locale));
        assert.equal(await page.locator('[data-owner-profile-panel]:visible').count(), 3, 'all three native panels are stacked without JavaScript');
        await page.locator('[data-owner-profile-link="about"]').click();
        assert.equal(new URL(page.url()).hash, '#about');
        assert.equal(await page.locator('#profile-form #full_name').count(), 1);
        assert.equal(await page.locator('#profile-form #about_me').count(), 1);
        await page.locator('.owner-profile-extra > summary').click();
        assert.equal(await page.locator('#phone_secondary').isVisible(), true);
        await page.locator('.owner-profile-extra > summary').click();
        await page.locator('.owner-profile-photo > summary').click();
        assert.equal(await page.locator('#profile_image').isVisible(), true);
        await page.locator('.owner-profile-photo > summary').click();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        if (locale === 'ar') await capture(page, 'profile-ar-no-js-390');
        await c.close();
    }
    const session = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
    if (process.env.EVIDENCE_HUB_ACTION_TEST_URL) assert.ok(session, 'authenticated profile writes require the disposable gate Owner session');
    if (session) {
        assert.equal(process.env.EVIDENCE_HUB_ACTION_TEST_URL, base, 'write only to the gate-supplied disposable server');
        const c = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
        await c.addCookies([{ name: 'portfolio_owner_session', value: session, url: base }]);
        const page = await c.newPage();
        page.on('dialog', dialog => dialog.accept()); // Explicitly discard synthetic unsaved edits during cleanup.
        const url = `${base}/owner_profile.php?lang=en`;
        await page.goto(url);
        const original = await page.locator('#profile-form').evaluate(form => Object.fromEntries(new FormData(form)));
        assert.equal(await page.locator('.owner-profile-avatar img').count(), 0, 'disposable fixture starts without a resident photo');
        let skillId = null; let uploaded = false;
        async function submit(form, expected = 303) {
            const response = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/owner_profile.php');
            await form.locator('button[type="submit"]').click();
            assert.equal((await response).status(), expected);
            await page.waitForLoadState('load');
        }
        async function freshFields() {
            await page.goto(url);
            return await page.locator('#profile-form').evaluate(form => Object.fromEntries(new FormData(form)));
        }
        try {
            await page.locator('#full_name').fill('Synthetic FE-03 Owner');
            await page.locator('#professional_title').fill('AI Engineer');
            await page.locator('#location').fill('Amman, Jordan');
            await page.locator('#email').fill('fe03@example.invalid');
            await page.locator('#public_email_visible').check();
            await page.locator('.owner-profile-extra > summary').click();
            await page.locator('#hero_headline').fill('Reliable synthetic software');
            await page.locator('#phone_primary').fill('+962 790000000');
            await page.locator('#phone_secondary').fill('+962 780000000');
            await page.locator('#public_phone_visible').uncheck();
            await page.locator('#country_code').selectOption('JO');
            await page.locator('#preferred_language').selectOption('en');
            await page.locator('#specialization').fill('Software engineering');
            await panel(page, 'about');
            await page.locator('#about_me').fill('Synthetic FE-03 biography.');
            await page.locator('#work_description').fill('Synthetic FE-03 professional summary.');
            await page.locator('#linkedin_url').fill('https://example.invalid/linkedin');
            await submit(page.locator('#profile-form'));
            await page.waitForURL(/saved=1/);
            assert.equal(await page.locator('#full_name').inputValue(), 'Synthetic FE-03 Owner', 'saving the second panel keeps the first panel values');
            assert.equal(await page.locator('#about_me').inputValue(), 'Synthetic FE-03 biography.');
            for (const [name, value] of Object.entries({ professional_title: 'AI Engineer', location: 'Amman, Jordan', email: 'fe03@example.invalid', hero_headline: 'Reliable synthetic software', phone_secondary: '+962 780000000', country_code: 'JO', preferred_language: 'en', specialization: 'Software engineering' })) assert.equal(await page.locator(`[name="${name}"]`).inputValue(), value);
            assert.equal(await page.locator('#public_email_visible').isChecked(), true);
            assert.equal(await page.locator('#public_phone_visible').isChecked(), false);
            await panel(page, 'about');
            await page.locator('#linkedin_url').fill('ftp://example.invalid/invalid');
            await page.locator('#profile-form').evaluate(form => form.noValidate = true);
            await submit(page.locator('#profile-form'), 422);
            assert.equal(await page.locator('#about').isVisible(), true);
            await page.waitForFunction(() => document.activeElement.id === 'linkedin_url');
            assert.equal(await page.locator('#linkedin_url').getAttribute('aria-invalid'), 'true');
            assert.equal(await page.locator('#linkedin_url').inputValue(), 'ftp://example.invalid/invalid');
            await page.locator('#linkedin_url').fill('https://example.invalid/linkedin');
            await page.locator('#profile-form [name="expected_profile_version"]').evaluate(n => n.value = '0');
            await submit(page.locator('#profile-form'), 409);
            assert.equal(await page.locator('#profile-form [name="expected_profile_version"]').inputValue(), '0', '409 keeps the original stale token');
            assert.equal(await page.locator('#full_name').inputValue(), 'Synthetic FE-03 Owner');
            assert.equal(await page.locator('#public_email_visible').isChecked(), true);
            assert.equal(await page.locator('#public_phone_visible').isChecked(), false);
            assert.equal(await page.locator('.owner-profile-reload a[href="owner_profile.php"]').count(), 1);
            await page.goto(url);
            await panel(page, 'skills');
            await page.locator('#skill_name').fill('Synthetic FE-03 Skill');
            await submit(page.locator('.skill-add-form'));
            await page.waitForURL(/skill_added=1/);
            assert.equal(await page.locator('#skills').isVisible(), true);
            let row = page.locator('.skill-row').filter({ has: page.locator('input[value="Synthetic FE-03 Skill"]') });
            skillId = await row.locator('[name="skill_id"]').first().inputValue();
            await row.locator('[name="skill_name"]').fill('Synthetic FE-03 Skill edited');
            await submit(row.locator('form').first());
            await page.waitForURL(/skill_updated=1/);
            row = page.locator('.skill-row').filter({ has: page.locator('input[value="Synthetic FE-03 Skill edited"]') });
            await row.locator('form').last().locator('button').click();
            assert.equal(await page.locator('[role="dialog"]').isVisible(), true);
            await page.locator('.confirm-cancel').click();
            assert.equal(await row.count(), 1);
            await row.locator('form').last().locator('button').click();
            const removed = page.waitForURL(/skill_deleted=1/);
            await page.locator('.confirm-ok').click(); await removed;
            assert.equal(await page.locator('.skill-row input[value="Synthetic FE-03 Skill edited"]').count(), 0); skillId = null;
            await page.goto(url);
            await page.locator('.owner-profile-photo > summary').click();
            const png = await page.evaluate(() => { const canvas = document.createElement('canvas'); canvas.width = canvas.height = 400; const ctx = canvas.getContext('2d'); ctx.fillStyle = '#235d45'; ctx.fillRect(0, 0, 400, 400); return canvas.toDataURL('image/png').split(',')[1]; });
            await page.locator('#profile_image').setInputFiles({ name: 'synthetic-fe03.png', mimeType: 'image/png', buffer: Buffer.from(png, 'base64') });
            await submit(page.locator('form').filter({ has: page.locator('[name="action"][value="upload_profile_image"]') }));
            await page.waitForURL(/photo_updated=1/); uploaded = true;
            assert.equal(await page.locator('.owner-profile-avatar img').evaluate(n => n.complete && n.naturalWidth >= 400), true);
            await page.locator('.owner-profile-photo > summary').click();
            await page.locator('form').filter({ has: page.locator('[name="action"][value="remove_profile_image"]') }).locator('button').click();
            assert.equal(await page.locator('[role="dialog"]').isVisible(), true);
            const removedPhoto = page.waitForURL(/photo_removed=1/);
            await page.locator('.confirm-ok').click(); await removedPhoto; uploaded = false;
            assert.equal(await page.locator('.owner-profile-avatar img').count(), 0);
            console.log('OWNER_PROFILE_ACTIONS_PASSED: one save preserves both panels; 422 focus; 409 retention; skills CRUD; photo upload/remove.');
        } finally {
            let fields = await freshFields();
            if (skillId) assert.equal((await c.request.post(url, { form: { action: 'delete_skill', skill_id: skillId, csrf_token: fields.csrf_token } })).status(), 200);
            if (uploaded) {
                assert.equal((await c.request.post(url, { form: { action: 'remove_profile_image', expected_profile_version: fields.expected_profile_version, profile_id: fields.profile_id, csrf_token: fields.csrf_token } })).status(), 200);
                fields = await freshFields();
            }
            const restored = await c.request.post(url, { form: { ...original, expected_profile_version: fields.expected_profile_version, csrf_token: fields.csrf_token } });
            assert.equal(restored.status(), 200, 'restore the synthetic profile with its latest version');
            await c.close();
        }
    }
    console.log('OWNER_PROFILE_VISUAL_PASSED: anchors and keyboard; native no-JS panels; connected errors; ar/en themes and large text at all five widths.');
};
