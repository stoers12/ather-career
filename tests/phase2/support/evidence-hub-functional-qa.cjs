'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const output = process.env.ATHER_VISUAL_OUT || path.join(process.env.TEMP || '/tmp', 'ather-evidence-hub-functional-qa');
const hub = variant => `${base}/tests/phase2/support/evidence-hub-page-visual.php?variant=${variant}`;
const editor = `${base}/tests/phase2/support/owner-project-evidence-visual.php`;
const sections = [['Next steps', 'next-steps'], ['Overview', 'overview'], ['Project evidence', 'project-evidence'], ['Technologies', 'technologies'], ['Progress', 'progress']];

async function main() {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath: process.env.ATHER_CHROMIUM_PATH });
    const results = [];
    try {
        for (const [width, height] of [[1440, 900], [390, 844], [320, 700]]) {
            const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
            const page = await context.newPage();
            await page.goto(hub('partial'), { waitUntil: 'networkidle' });
            const nav = page.locator('.evidence-hub-section-nav');
            assert.deepEqual(await nav.locator('a').allTextContents(), sections.map(([label]) => label));
            assert.equal(await page.locator('#evidence-hub-theme-toggle').count(), 0);
            assert.equal(await page.locator('.evidence-hub-topbar-breadcrumb').count(), 0);
            assert.equal(await page.locator('.evidence-hub-topbar-context').innerText(), 'Evidence Hub');
            assert.equal(await page.locator('.evidence-hub-section-nav').count(), 1);
            const geometry = await page.evaluate(() => {
                const bar = document.querySelector('.evidence-hub-topbar').getBoundingClientRect();
                const nav = document.querySelector('.evidence-hub-section-nav');
                const links = Array.from(nav.querySelectorAll('a')).map(a => a.getBoundingClientRect());
                return { barHeight: bar.height, linkTopRange: Math.max(...links.map(r => r.top)) - Math.min(...links.map(r => r.top)), minimumTargetHeight: Math.min(...links.map(r => r.height)), pageOverflow: document.documentElement.scrollWidth > innerWidth, navOverflow: nav.scrollWidth > nav.clientWidth };
            });
            assert.ok(geometry.barHeight <= 58 && geometry.barHeight >= 52, `${width}: Navbar height`);
            assert.ok(geometry.linkTopRange < 2, `${width}: links wrapped`);
            assert.ok(geometry.minimumTargetHeight >= 44, `${width}: Navbar touch target`);
            assert.equal(geometry.pageOverflow, false, `${width}: page overflow`);
            if (width < 500) assert.equal(geometry.navOverflow, true, `${width}: Navbar should scroll`);
            await page.screenshot({ path: path.join(output, `unified-${width}-light.png`) });
            for (const [label, id] of sections) {
                await nav.getByRole('link', { name: label, exact: true }).click();
                await page.waitForFunction(expected => {
                    const active = document.querySelectorAll('.evidence-hub-section-nav [aria-current="true"]');
                    return active.length === 1 && active[0].hash === '#' + expected;
                }, id);
                const state = await page.evaluate(expected => {
                    const bar = document.querySelector('.evidence-hub-topbar').getBoundingClientRect();
                    const nav = document.querySelector('.evidence-hub-section-nav');
                    const active = nav.querySelector('[aria-current="true"]').getBoundingClientRect();
                    const navBox = nav.getBoundingClientRect();
                    return { hash: location.hash, headingTop: document.querySelector(`#${expected} h2`).getBoundingClientRect().top,
                        barBottom: bar.bottom, barTop: bar.top, activeVisible: active.left >= navBox.left - 1 && active.right <= navBox.right + 1,
                        pageOverflow: document.documentElement.scrollWidth > innerWidth };
                }, id);
                assert.equal(state.hash, '#' + id, `${width}: anchor ${id}`);
                assert.ok(state.headingTop > state.barBottom, `${width}: ${id} heading covered`);
                assert.ok(Math.abs(state.barTop) < 1, `${width}: Navbar not sticky`);
                assert.equal(state.activeVisible, true, `${width}: active link clipped`);
                assert.equal(state.pageOverflow, false, `${width}: overflow after ${id}`);
                if (width === 1440 && ['technologies', 'progress'].includes(id)) await page.screenshot({ path: path.join(output, `${id}-active.png`) });
            }
            await page.goBack();
            await page.waitForFunction(() => document.querySelector('.evidence-hub-section-nav [aria-current="true"]')?.hash === '#technologies');
            await page.goForward();
            await page.waitForFunction(() => document.querySelector('.evidence-hub-section-nav [aria-current="true"]')?.hash === '#progress');
            if (width === 1440) {
                await page.waitForTimeout(550);
                await page.evaluate(() => scrollTo(0, 0));
                await page.waitForFunction(() => document.querySelector('.evidence-hub-section-nav [aria-current="true"]')?.hash === '#next-steps');
                await page.evaluate(() => scrollTo(0, document.documentElement.scrollHeight));
                await page.waitForFunction(() => document.querySelector('.evidence-hub-section-nav [aria-current="true"]')?.hash === '#progress');
                await nav.getByRole('link', { name: 'Progress' }).focus();
                await page.keyboard.press('Shift+Tab');
                assert.equal(await nav.getByRole('link', { name: 'Technologies' }).evaluate(a => a === document.activeElement && getComputedStyle(a).outlineStyle !== 'none'), true, 'Navbar keyboard focus is not visible');
            }
            if (width === 320) {
                const options = page.locator('.evidence-hub-options').first();
                await options.locator('summary').click();
                const bounds = await options.locator('div').first().boundingBox();
                assert.ok(bounds && bounds.x >= 0 && bounds.x + bounds.width <= width, 'Mobile options menu exceeds the viewport');
            }
            results.push(`${width}: Navbar links, sticky position, active state, history, focus, and overflow passed`);
            await context.close();
        }

        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        await page.goto(hub('partial'), { waitUntil: 'networkidle' });
        const ctas = await page.locator('.evidence-hub-recommendation-card .evidence-hub-primary-cta').evaluateAll(links => links.map(link => link.getAttribute('href')));
        assert.deepEqual(ctas, ['/owner/projects/8/evidence', '/owner_projects.php?edit=8', '/owner_publication.php']);
        assert.equal(await page.locator('.evidence-hub-recommendation-card').count(), 3);
        const menu = page.locator('.evidence-hub-options').first();
        await menu.locator('summary').click();
        assert.equal(await menu.evaluate(node => node.open), true);
        assert.deepEqual(await menu.locator('button').allTextContents(), ['Snooze for 14 days', 'Dismiss']);
        const forms = await menu.locator('form').evaluateAll(nodes => nodes.map(form => ({
            method: form.method, action: form.getAttribute('action'), csrf: form.querySelector('[name="csrf_token"]')?.value,
            token: form.querySelector('[name="action_token"]')?.value,
        })));
        assert.equal(forms.length, 2);
        assert.ok(forms.every(form => form.method === 'post' && form.action === '/owner/evidence-hub' && form.csrf.length === 64 && form.token.length > 10));
        await menu.locator('button').first().focus();
        await page.keyboard.press('Escape');
        assert.equal(await menu.evaluate(node => node.open), false);
        assert.equal(await menu.locator('summary').evaluate(node => node === document.activeElement), true);
        await page.keyboard.press('Enter');
        assert.equal(await menu.evaluate(node => node.open), true);
        await page.locator('.evidence-hub-section-heading h2').first().click();
        assert.equal(await menu.evaluate(node => node.open), false);
        results.push('Recommendation CTA destinations, options menu, Escape, outside click, and secure form structure passed');

        assert.equal(await page.locator('.evidence-hub-status-card').count(), 3);
        assert.equal(await page.getByRole('progressbar', { name: 'Documentation Coverage' }).getAttribute('value'), '2917');
        assert.equal(await page.getByRole('progressbar', { name: 'Documentation Coverage' }).getAttribute('max'), '10000');
        assert.ok((await page.locator('.evidence-hub-status-card').first().innerText()).includes('29.17%'));
        assert.ok((await page.locator('.evidence-hub-status-card').first().innerText()).includes('7 of 24 evidence fields completed'));
        assert.ok((await page.locator('.evidence-hub-status-card').nth(1).innerText()).includes('1 mapped technology'));
        assert.ok((await page.locator('.evidence-hub-status-card').nth(1).innerText()).includes('1 unmapped technology across 2 projects'));
        assert.ok((await page.locator('.evidence-hub-status-card').nth(2).innerText()).includes('1 / 8'));
        await page.locator('#overview').screenshot({ path: path.join(output, 'evidence-overview.png') });
        assert.equal(await page.locator('.evidence-hub-project-card:visible').count(), 6);
        assert.deepEqual(await page.locator('.evidence-hub-project-card').evaluateAll(cards => cards.map(card => card.id)), ['project-8','project-7','project-6','project-5','project-4','project-3','project-2','project-1']);
        assert.equal(await page.locator('.evidence-hub-project-card').count(), 8);
        assert.equal(await page.locator('.evidence-hub-project-card .evidence-hub-field .evidence-hub-state--complete').count(), 7);
        await page.getByRole('button', { name: 'Needs attention', exact: true }).click();
        assert.equal(await page.locator('.evidence-hub-project-card:visible').count(), 6);
        await page.getByRole('button', { name: 'Complete', exact: true }).click();
        assert.equal(await page.locator('.evidence-hub-project-card:visible').count(), 1);
        await page.getByRole('button', { name: 'All', exact: true }).click();
        await page.getByRole('button', { name: 'Show more' }).click();
        assert.equal(await page.locator('.evidence-hub-project-card:visible').count(), 8);
        await page.locator('#project-8 summary').click();
        await page.locator('#project-7 summary').click();
        assert.equal(await page.locator('#project-8 details').getAttribute('open'), '');
        assert.equal(await page.locator('#project-7 details').getAttribute('open'), '');
        assert.deepEqual(await page.locator('#project-7 .evidence-hub-field a').evaluateAll(links => links.map(a => a.getAttribute('href'))), ['/owner/projects/7/evidence#problem','/owner/projects/7/evidence#personal-role','/owner/projects/7/evidence#measurable-outcome']);
        assert.deepEqual(await page.locator('.evidence-hub-project-edit').evaluateAll(links => links.map(a => a.getAttribute('href'))), Array.from({length:8}, (_,i) => `/owner_projects.php?edit=${8-i}`));
        assert.equal(/FIELD_NOT_AVAILABLE|GRAPHEME_THRESHOLD_NOT_MET|problem_statement/.test(await page.locator('.evidence-hub-page-body').innerText()), false);
        results.push('Three metrics, progress semantics, filters, project ordering, Show more, disclosures, edit links, and safe copy passed');

        assert.ok((await page.locator('#technologies').innerText()).includes('JavaScript'));
        assert.ok((await page.locator('#technologies').innerText()).includes('5 projects'));
        assert.ok((await page.locator('#technologies').innerText()).includes('Unmapped technologies'));
        assert.ok((await page.locator('#technologies').innerText()).includes('2 projects'));
        assert.ok((await page.locator('#progress').innerText()).includes('One evidence-ready project'));
        assert.ok((await page.locator('#progress').innerText()).includes('1 of 8 eligible projects have all three evidence fields complete.'));
        assert.ok((await page.locator('#progress').innerText()).includes('Complete-project activity: 2026-09-11.'));
        assert.equal((await page.locator('.evidence-hub-page-body').innerText()).includes('2026-01-01'), false);
        await page.locator('#technologies').screenshot({ path: path.join(output, 'technologies-section.png') });
        await page.locator('#progress').screenshot({ path: path.join(output, 'portfolio-progress.png') });
        await page.goto(hub('zero'));
        assert.equal(await page.locator('.evidence-hub-status-card').count(), 3);
        assert.equal(await page.locator('.evidence-hub-zero-path article').count(), 3);
        assert.equal(await page.locator('.evidence-hub-status-card progress').count(), 0);
        await page.goto(hub('ready'));
        assert.equal(await page.locator('.evidence-hub-status-card').count(), 3);
        assert.ok((await page.locator('.evidence-hub-status-card').first().innerText()).includes('100%'));
        assert.equal(await page.locator('.evidence-hub-show-more').isVisible(), false);
        results.push('Zero, Partial, Ready, mapped/unmapped Technologies, and factual Progress passed');

        await page.goto(editor);
        assert.equal(await page.locator('#evidence-hub-theme-toggle').count(), 0);
        assert.equal(await page.getByRole('heading', { name: 'Edit project evidence' }).count(), 1);
        assert.deepEqual(await page.locator('.evidence-hub-editor textarea').evaluateAll(controls => controls.map(control => control.name)), ['problem','personal_role','measurable_outcome']);
        assert.equal(await page.locator('.evidence-hub-editor form').getAttribute('action'), '/owner/projects/7/evidence');
        assert.equal(await page.getByRole('link', { name: 'Cancel' }).getAttribute('href'), '/owner/evidence-hub#project-7');
        assert.equal(await page.locator('.evidence-hub-editor textarea').evaluateAll(controls => controls.every(control => control.labels.length > 0 && control.getAttribute('aria-describedby')?.includes('evidence-help-'))), true);
        assert.equal(/FIELD_NOT_AVAILABLE|problem_statement/.test(await page.locator('.evidence-hub-editor').innerText()), false);
        results.push('Evidence editor form, logical fields, Cancel target, and reason-code safety passed');
        await context.close();

        const noJs = await browser.newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false, reducedMotion: 'reduce' });
        const noJsPage = await noJs.newPage();
        await noJsPage.goto(hub('partial'));
        assert.equal(await noJsPage.locator('.evidence-hub-project-card:visible').count(), 8);
        await noJsPage.getByRole('link', { name: 'Overview', exact: true }).click();
        assert.equal(new URL(noJsPage.url()).hash, '#overview');
        await noJsPage.getByRole('link', { name: 'Technologies', exact: true }).focus();
        await noJsPage.keyboard.press('Enter');
        assert.equal(new URL(noJsPage.url()).hash, '#technologies');
        await noJsPage.locator('#project-8 summary').focus();
        await noJsPage.keyboard.press('Enter');
        assert.equal(await noJsPage.locator('#project-8 details').getAttribute('open'), '');
        await noJsPage.goto(editor);
        assert.equal(await noJsPage.locator('textarea').count(), 3);
        await noJs.close();
        results.push('No-JavaScript project access, native disclosure, section links, and editor content passed');

        const target = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const targetPage = await target.newPage();
        await targetPage.goto(hub('partial') + '#project-1', { waitUntil: 'networkidle' });
        assert.equal(await targetPage.locator('#project-1').isVisible(), true);
        await target.close();
        results.push('Target project beyond six revealed by fragment passed');

        const dark = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        await dark.addInitScript(() => localStorage.setItem('ather.evidenceHub.theme', 'dark'));
        const darkPage = await dark.newPage();
        await darkPage.goto(hub('partial'), { waitUntil: 'networkidle' });
        assert.equal(await darkPage.locator('body').getAttribute('data-evidence-hub-theme'), 'dark');
        assert.equal(await darkPage.locator('#evidence-hub-theme-toggle').count(), 0);
        await darkPage.screenshot({ path: path.join(output, 'unified-1440-dark.png') });
        await darkPage.goto(editor);
        assert.equal(await darkPage.locator('body').getAttribute('data-evidence-hub-theme'), 'dark');
        assert.equal(await darkPage.locator('#evidence-hub-theme-toggle').count(), 0);
        await dark.close();
        results.push('Saved dark theme renders in Hub and editor without a visible toggle');

        console.log(JSON.stringify({ result: 'PASS', checks: results, screenshots: output }, null, 2));
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
