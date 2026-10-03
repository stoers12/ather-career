'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const output = process.env.ATHER_VISUAL_OUT || path.join(process.env.TEMP || '/tmp', 'ather-evidence-hub-review');
const executablePath = process.env.ATHER_CHROMIUM_PATH;

async function check(page, label) {
    const result = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > window.innerWidth,
        width: document.documentElement.scrollWidth,
        viewport: window.innerWidth,
        metrics: document.querySelectorAll('.evidence-hub-page-body .evidence-hub-status-card').length,
        sections: Array.from(document.querySelectorAll('.evidence-hub-section-nav a')).map(link => link.textContent.trim()),
    }));
    if (result.overflow) throw new Error(`${label}: horizontal overflow ${result.width}/${result.viewport}`);
    if (result.metrics && result.metrics !== 3) throw new Error(`${label}: ${result.metrics} metrics`);
    if (result.sections.length && result.sections.join('|') !== 'Next steps|Overview|Project evidence|Technologies|Progress') throw new Error(`${label}: section labels`);
}

async function main() {
    fs.mkdirSync(output, { recursive: true });
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        const shots = [];
        async function shot(name, url, width, height, dark = false, action = null, fullPage = true) {
            const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 1, reducedMotion: 'reduce' });
            if (dark) await context.addInitScript(() => localStorage.setItem('ather.evidenceHub.theme', 'dark'));
            const page = await context.newPage();
            await page.goto(url, { waitUntil: 'networkidle' });
            if (await page.locator('#evidence-hub-theme-toggle').count()) throw new Error(`${name}: visible theme control remains`);
            if (dark && await page.locator('body').getAttribute('data-evidence-hub-theme') !== 'dark') throw new Error(`${name}: saved dark theme was not applied`);
            if (action) await action(page);
            await check(page, name);
            const target = path.join(output, name + '.png');
            await page.screenshot({ path: target, fullPage });
            shots.push(target);
            await context.close();
        }
        const hub = variant => `${base}/tests/phase2/support/evidence-hub-page-visual.php?variant=${variant}`;
        const edit = `${base}/tests/phase2/support/owner-project-evidence-visual.php`;
        await shot('partial-desktop-light-1440x900', hub('partial'), 1440, 900, false, async page => {
            if (await page.locator('.evidence-hub-project-card:visible').count() !== 6) throw new Error('Initial project count is not six');
            if (!(await page.locator('.evidence-hub-show-more').isVisible())) throw new Error('Show more is missing');
        });
        await shot('partial-desktop-dark-1440x900', hub('partial'), 1440, 900, true);
        await shot('zero-desktop-light-1440x900', hub('zero'), 1440, 900);
        await shot('ready-desktop-light-1440x900', hub('ready'), 1440, 900, false, async page => {
            if (await page.locator('.evidence-hub-show-more').isVisible()) {
                const state = await page.locator('.evidence-hub-show-more').evaluate(button => ({ hidden: button.hidden, display: getComputedStyle(button).display }));
                throw new Error(`Show more appears with three projects: ${JSON.stringify(state)}`);
            }
        });
        await shot('partial-mobile-light-390x844', hub('partial'), 390, 844);
        await shot('partial-mobile-dark-390x844', hub('partial'), 390, 844, true);
        await shot('partial-mobile-light-320x700', hub('partial'), 320, 700);
        await shot('project-expanded', hub('partial'), 1440, 900, false, async page => {
            await page.locator('#project-8 summary').click();
            await page.locator('#project-8').scrollIntoViewIfNeeded();
        }, false);
        await shot('project-filter-complete', hub('partial'), 1440, 900, false, async page => {
            await page.getByRole('button', { name: 'Complete', exact: true }).click();
            if (await page.locator('.evidence-hub-project-card:visible').count() !== 1) throw new Error('Complete filter is wrong');
            await page.locator('#project-evidence').scrollIntoViewIfNeeded();
        }, false);
        await shot('recommendation-options', hub('partial'), 1440, 900, false, async page => {
            await page.locator('.evidence-hub-options summary').first().click();
            await page.locator('#next-steps').scrollIntoViewIfNeeded();
        }, false);
        await shot('technologies-and-progress', hub('partial'), 1440, 900, false, async page => {
            await page.locator('#technologies').scrollIntoViewIfNeeded();
        }, false);
        await shot('evidence-edit-desktop-light', edit, 1440, 900);
        await shot('evidence-edit-desktop-dark', edit, 1440, 900, true);
        await shot('evidence-edit-mobile-light-320', edit, 320, 700);

        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false });
        const page = await context.newPage();
        await page.goto(hub('partial'), { waitUntil: 'domcontentloaded' });
        if (await page.locator('.evidence-hub-project-card:visible').count() !== 8) throw new Error('No-JavaScript project access is incomplete');
        await context.close();

        const targetContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        const targetPage = await targetContext.newPage();
        await targetPage.goto(hub('partial') + '#project-1', { waitUntil: 'networkidle' });
        if (!(await targetPage.locator('#project-1').isVisible())) throw new Error('Targeted project was hidden');
        await targetContext.close();
        console.log(JSON.stringify({ result: 'PASS', screenshots: shots }, null, 2));
    } finally {
        await browser.close();
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
