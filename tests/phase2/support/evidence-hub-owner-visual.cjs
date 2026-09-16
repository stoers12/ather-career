// Uses an already installed Playwright; no browser dependency is added.
// PLAYWRIGHT_MODULE=<module path> node this-file <output directory>
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const output = process.argv[2];
if (!output) throw new Error('A screenshot output directory is required.');
const edgeExecutable = process.env.EVIDENCE_HUB_EDGE_EXECUTABLE;
const baseUrl = process.env.EVIDENCE_HUB_VISUAL_BASE_URL;
const browserProfile = process.env.EVIDENCE_HUB_BROWSER_PROFILE;
if (!edgeExecutable) throw new Error('EVIDENCE_HUB_EDGE_EXECUTABLE is required for real Edge acceptance.');
if (!baseUrl) throw new Error('EVIDENCE_HUB_VISUAL_BASE_URL is required for the disposable visual server.');
if (!browserProfile) throw new Error('EVIDENCE_HUB_BROWSER_PROFILE is required for task-owned browser state.');
fs.mkdirSync(output, { recursive: true });

function visualUrl(variant) {
    return `${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=${encodeURIComponent(variant)}`;
}

function captureBrowserErrors(page) {
    const errors = [];
    page.on('pageerror', error => errors.push(`pageerror:${error.message}`));
    page.on('console', message => {
        if (message.type() === 'error') errors.push(`console:${message.text()}`);
    });
    return errors;
}

async function assertNoBrowserErrors(errors, label) {
    assert.deepEqual(errors, [], `${label}: no uncaught browser errors`);
}

(async () => {
    const browser = await chromium.launchPersistentContext(browserProfile, { headless: true, executablePath: edgeExecutable });
    try {
        for (const width of [1440, 768, 360]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            const errors = captureBrowserErrors(page);
            await page.goto(visualUrl('actions'));
            await page.getByText('Documentation coverage', { exact: true }).waitFor();
            await page.getByRole('heading', { name: 'Technology evidence map' }).waitFor();
            await page.getByRole('heading', { name: 'Portfolio progress' }).waitFor();
            assert.equal(await page.getByText('Maturity', { exact: true }).isVisible(), true, `${width}: maturity section`);
            assert.equal(await page.getByRole('link', { name: 'Evidence Hub' }).getAttribute('aria-current'), 'page', `${width}: active navigation state`);
            assert.equal(await page.getByRole('link', { name: 'Open project management' }).getAttribute('href'), '/owner_projects.php?add=1', `${width}: generic recommendation destination`);
            assert.equal(await page.locator('[aria-label="Evidence Hub recommendation"]').count(), 1, `${width}: recommendation semantics`);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}: responsive reflow`);
            await page.locator('.admin-skip-link').focus();
            assert.equal(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle !== 'none'), true, `${width}: keyboard-visible focus`);
            await page.keyboard.press('Enter');
            assert.equal(await page.evaluate(() => document.activeElement?.id), 'main-content', `${width}: skip-link keyboard target`);
            const headings = await page.locator('h1, h2').evaluateAll(nodes => nodes.map(node => node.tagName));
            assert.equal(headings[0], 'H1', `${width}: heading order begins at h1`);
            const body = await page.locator('body').innerText();
            assert.equal(/recommendation_key|evidence_fingerprint|opaque_target_ref|visual_tenant|visual_portfolio|raw_label|auth0|@/i.test(body), false, `${width}: forbidden data is absent`);
            await page.locator('.admin-content').screenshot({ path: path.join(output, `${width}.png`) });
            await assertNoBrowserErrors(errors, `${width}`);
            await page.close();
        }

        const noJavaScript = await browser.newPage({ viewport: { width: 360, height: 1000 }, javaScriptEnabled: false });
        await noJavaScript.goto(visualUrl('actions'));
        assert.equal(await noJavaScript.getByRole('link', { name: 'Open project management' }).isVisible(), true, 'no-JavaScript: recommendation navigation remains usable');
        await noJavaScript.close();

        const reducedMotion = await browser.newPage({ viewport: { width: 768, height: 1000 } });
        const reducedMotionErrors = captureBrowserErrors(reducedMotion);
        await reducedMotion.emulateMedia({ reducedMotion: 'reduce' });
        await reducedMotion.goto(visualUrl('partial'));
        assert.equal(await reducedMotion.getByText('No current actions', { exact: true }).isVisible(), true, 'partial: no-current-actions state');
        assert.equal(await reducedMotion.locator('.admin-sidebar a').first().evaluate(node => getComputedStyle(node).transitionDuration), '0s', 'reduced motion disables navigation transitions');
        await assertNoBrowserErrors(reducedMotionErrors, 'reduced motion');
        await reducedMotion.close();

        const safeError = await browser.newPage();
        const safeErrorErrors = captureBrowserErrors(safeError);
        await safeError.goto(visualUrl('error'));
        assert.equal(await safeError.getByRole('alert').textContent(), 'Evidence Hub is temporarily unavailable.Please try again later.', 'safe error state');
        await assertNoBrowserErrors(safeErrorErrors, 'safe error');
        await safeError.close();
        console.log('PASS Evidence Hub Owner browser contract');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
