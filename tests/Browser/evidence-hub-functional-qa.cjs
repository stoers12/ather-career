'use strict';
const assert = require('node:assert/strict');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const hub = variant => `${base}/tests/Browser/evidence-hub-page-visual.php?variant=${variant}`;
const editor = `${base}/tests/Browser/owner-project-evidence-visual.php`;
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.ATHER_CHROMIUM_PATH });
    try {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        await page.goto(hub('partial'), { waitUntil: 'networkidle' });
        const sections = ['next-steps', 'overview', 'project-evidence', 'technologies', 'progress'];
        for (const id of sections) {
            await page.locator(`.evidence-hub-section-nav a[href="#${id}"]`).click();
            assert.equal(await page.locator(`#${id}`).isVisible(), true, `${id}: cross-layer anchor`);
            const geometry = await page.locator(`#${id}`).evaluate(n => [n.getBoundingClientRect().top, document.querySelector('.evidence-hub-topbar').getBoundingClientRect().bottom]);
            assert.ok(geometry[0] >= geometry[1] - 1, `${id}: anchor not obscured`);
        }
        await page.locator('[data-evidence-layer=insights]').click();
        const readiness = await page.locator('.evidence-hub-readiness-panel small').innerText();
        assert.equal(await page.locator('.evidence-hub-status-card').count(), 3);
        assert.equal(await page.getByRole('progressbar', { name: 'Documentation Coverage' }).getAttribute('value'), '2917');
        assert.equal(await page.getByRole('progressbar', { name: 'Documentation Coverage' }).getAttribute('max'), '10000');
        for (const text of ['29.17%', '7 of 24 evidence fields completed', '1 mapped technology', '1 unmapped technology across 2 projects', '1 / 8']) assert.ok((await page.locator('#overview').innerText()).includes(text), text);
        for (const text of ['JavaScript', '5 projects', 'Unmapped technologies', '2 projects']) assert.ok((await page.locator('#technologies').innerText()).includes(text), text);
        for (const text of ['One evidence-ready project', '1 of 8 eligible projects have all three evidence fields complete.', 'Complete-project activity: 2026-09-11.']) assert.ok((await page.locator('#progress').innerText()).includes(text), text);
        assert.equal((await page.locator('.evidence-hub-page-body').textContent()).includes('2026-01-01'), false);
        let requests = 0; page.on('request', req => { if (req.isNavigationRequest()) requests++; });
        await page.locator('[data-evidence-layer=records]').focus(); await page.keyboard.press('Enter');
        await page.keyboard.press('ArrowRight');
        assert.equal(await page.locator('#insights').isVisible(), true);
        assert.equal(requests, 0, 'Client layer switching must not consume immediate Undo through another GET');
        await page.locator('[data-evidence-layer=records]').click();
        assert.deepEqual(await page.locator('.evidence-hub-project-card').evaluateAll(cards => cards.map(card => card.id)), ['project-8','project-7','project-6','project-5','project-4','project-3','project-2','project-1']);
        assert.equal(await page.locator('.evidence-hub-project-card .evidence-hub-field .evidence-hub-state--complete').count(), 7);
        assert.deepEqual(await page.locator('[data-evidence-filter] span').allTextContents(), ['8','7','1','8']);
        for (const [filter,count] of [['attention',7],['complete',1],['sources',8],['all',8]]) {
            await page.locator(`[data-evidence-filter=${filter}]`).click();
            assert.equal(await page.locator('.evidence-hub-project-card:not([hidden])').count(), count);
            assert.equal(await page.locator('.evidence-hub-readiness-panel small').textContent(), readiness);
        }
        await page.locator('[data-evidence-carousel-next]').focus(); await page.keyboard.press('End');
        assert.equal(await page.locator('#project-1').evaluate(n => !n.inert), true, 'Every record remains reachable through the navigator');
        await page.locator('[data-evidence-carousel-previous]').focus(); await page.keyboard.press('Home');
        await page.waitForTimeout(200); // Native scroll-snap finishes before opening a keyboard menu.
        const menu = page.locator('#project-8 .evidence-hub-project-options');
        await menu.locator('summary').focus(); await page.keyboard.press('Enter');
        await page.locator('#project-8 .evidence-hub-project-options[open]').waitFor();
        assert.equal(await menu.evaluate(n => n.open), true);
        assert.deepEqual(await menu.locator('a').evaluateAll(links => links.map(a => a.getAttribute('href'))), ['/owner/projects/8/evidence','/owner/projects/8/evidence?mode=write','/owner_projects.php?edit=8']);
        await page.keyboard.press('Tab'); await page.keyboard.press('Escape');
        assert.equal(await menu.locator('summary').evaluate(n => n === document.activeElement), true);
        const recommendations = page.locator('.evidence-hub-recommendation-card');
        assert.deepEqual(await recommendations.locator('.evidence-hub-primary-cta').evaluateAll(links => links.map(a => a.getAttribute('href'))), ['/owner/projects/8/evidence?mode=write','#technologies','/owner_publication.php']);
        const options = recommendations.first().locator('details'); await options.locator('summary').click();
        assert.deepEqual(await options.locator('button').allTextContents(), ['Snooze for 14 days','Dismiss']);
        assert.equal(await options.locator('form').evaluateAll(forms => forms.every(f => f.method === 'post' && f.getAttribute('action') === '/owner/evidence-hub' && f.csrf_token.value.length === 64 && f.action_token.value.length > 10)), true);
        await page.keyboard.press('Escape');
        assert.equal(/FIELD_NOT_AVAILABLE|GRAPHEME_THRESHOLD_NOT_MET|problem_statement/.test(await page.locator('.evidence-hub-page-body').innerText()), false);
        for (const variant of ['zero','ready']) {
            await page.goto(hub(variant)); await page.locator('[data-evidence-layer=insights]').click();
            assert.equal(await page.locator('.evidence-hub-status-card').count(), 3);
            if (variant === 'zero') { assert.equal(await page.locator('.evidence-hub-zero-path article').count(), 3); assert.equal(await page.locator('.evidence-hub-status-card progress').count(), 0); }
            else assert.ok((await page.locator('.evidence-hub-status-card').first().innerText()).includes('100%'));
        }
        await page.goto(editor + '?mode=write&part=personal_role');
        assert.deepEqual(await page.locator('textarea').evaluateAll(xs => xs.map(n => n.name)), ['problem','personal_role','measurable_outcome']);
        assert.equal(await page.locator('[data-evidence-text-form]').getAttribute('action'), '/owner/projects/7/evidence');
        assert.equal(await page.getByRole('link', { name: 'Cancel', exact:true }).getAttribute('href'), '/owner/projects/7/evidence');
        assert.equal(await page.locator('textarea').evaluateAll(xs => xs.every(n => n.labels.length && n.getAttribute('aria-describedby').includes('evidence-help-'))), true);
        assert.equal(await page.locator('[data-evidence-step-panel=personal_role]').isVisible(), true);
        await page.locator('#evidence-personal-role').fill('A retained unsaved role');
        await page.locator('[data-evidence-step=sources-after-save]').click();
        assert.equal(await page.locator('#evidence-personal-role').inputValue(), 'A retained unsaved role');
        assert.equal(await page.locator('input[name=action]').count(), 0);
        let warned = false; page.once('dialog', async dialog => { warned=true; await dialog.dismiss(); });
        await page.getByRole('link', { name: 'Cancel', exact:true }).click();
        assert.equal(warned, true); assert.ok(page.url().includes('mode=write'));
        page.once('dialog', dialog => dialog.accept());
        await page.goto(editor + '?scenario=error');
        assert.equal(await page.locator('[data-evidence-step-panel=personal_role]').isVisible(), true);
        await page.waitForFunction(() => document.querySelector('[aria-invalid=true]') === document.activeElement);
        assert.equal(await page.locator('[aria-invalid=true]').evaluate(n => n === document.activeElement), true);
        page.once('dialog', dialog => dialog.accept());
        await page.goto(editor);
        assert.equal(await page.locator('textarea').count(), 0); assert.equal(await page.locator('input[value=add_source]').count(),1);
        assert.deepEqual(await page.locator('.evidence-hub-reading-section a').evaluateAll(links => links.map(link => link.getAttribute('href'))), ['problem','personal_role','measurable_outcome'].map(part => '/owner/projects/7/evidence?mode=write&part=' + part));
        await page.goto(editor + '?scenario=limit'); assert.equal(await page.locator('input[value=add_source]').count(),0); assert.equal(await page.locator('input[value=remove_source]').count(),10);
        await context.close();
        const noJs = await browser.newContext({ javaScriptEnabled:false, viewport:{width:390,height:844} });
        const native = await noJs.newPage(); await native.goto(hub('partial'));
        assert.equal(await native.locator('.evidence-hub-project-card:visible').count(),8);
        assert.equal(await native.locator('[data-evidence-filter]:visible').count(),0);
        assert.equal(await native.locator('#insights').isVisible(),true); assert.equal(await native.locator('#records').isVisible(),true);
        await native.locator('.evidence-hub-section-nav a[href="#technologies"]').focus(); await native.keyboard.press('Enter'); assert.equal(new URL(native.url()).hash,'#technologies');
        await native.goto(editor+'?mode=write'); assert.equal(await native.locator('textarea:visible').count(),3); assert.equal(await native.locator('[data-evidence-step-panel]:visible').count(),4);
        assert.equal(await native.locator('input[name=action]').count(),0); await noJs.close();
        const target = await browser.newPage({viewport:{width:1440,height:900}}); await target.goto(hub('partial')+'#project-1',{waitUntil:'networkidle'});
        assert.equal(await target.locator('#project-1').evaluate(n => !n.inert && n === document.activeElement),true); await target.close();
        assert.deepEqual(errors,[]);
        console.log('PASS Evidence Hub functional metrics, ordering, recommendations, layers, navigator, forms, B-07 separation, retention, dirty guard, 422 focus, and native access');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
