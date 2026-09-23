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
if (!edgeExecutable || !baseUrl || !browserProfile) throw new Error('R4 browser visual configuration is incomplete.');
fs.mkdirSync(output, { recursive: true });

const recommendationName = 'Evidence Hub recommendation';
const visualUrl = variant => `${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=${encodeURIComponent(variant)}`;
const noOverflow = page => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth);
const shellText = page => page.evaluate(() => {
    const clone = document.body.cloneNode(true);
    clone.querySelectorAll('[dir="auto"]').forEach(node => node.remove());
    return clone.textContent || '';
});
const assertNoBrowserErrors = (errors, label) => assert.deepEqual(errors, [], `${label}: no uncaught browser errors`);
const captureBrowserErrors = page => {
    const errors = [];
    page.on('pageerror', () => errors.push('pageerror'));
    page.on('console', message => { if (message.type() === 'error') errors.push('console-error'); });
    return errors;
};

(async () => {
    const browser = await chromium.launchPersistentContext(browserProfile, { headless: true, executablePath: edgeExecutable });
    const pageAt = async viewport => {
        const page = await browser.newPage();
        await page.setViewportSize(viewport);
        return page;
    };
    try {
        const visualViewports = [
            { width: 1440, height: 900 }, { width: 1280, height: 800 }, { width: 1101, height: 768 },
            { width: 1100, height: 768 }, { width: 1024, height: 768 }, { width: 768, height: 1024 },
            { width: 390, height: 844 }, { width: 360, height: 800 }, { width: 320, height: 568 }
        ];
        for (const viewport of visualViewports) {
            const page = await pageAt(viewport);
            const errors = captureBrowserErrors(page);
            await page.goto(visualUrl('actions'));
            await page.getByRole('heading', { name: 'Build a portfolio people can trust.' }).waitFor();
            await page.getByRole('heading', { name: 'Recommended next steps' }).waitFor();
            assert.equal(await page.title(), 'Evidence Hub — My Portfolio', `${viewport.width}: English browser title`);
            assert.equal(await page.locator('html').getAttribute('lang'), 'en', `${viewport.width}: document language`);
            assert.equal(await page.locator('html').getAttribute('dir'), 'ltr', `${viewport.width}: document direction`);
            assert.equal(/[\u0600-\u06ff]/u.test(await shellText(page)), false, `${viewport.width}: no Arabic application chrome`);
            assert.equal(await page.getByRole('option', { name: /arabic/i }).count(), 0, `${viewport.width}: no Arabic language option`);
            const active = page.locator('#evidence-hub-mobile-drawer a[href="/owner/evidence-hub"]');
            assert.equal(await active.getAttribute('aria-current'), 'page', `${viewport.width}: active Evidence Hub navigation`);
            const card = page.getByRole('article', { name: recommendationName }).first();
            assert.equal(await card.count(), 1, `${viewport.width}: semantic recommendation row`);
            assert.equal(await card.getByRole('link', { name: 'Add project' }).getAttribute('href'), '/owner_projects.php?add=1', `${viewport.width}: authorized add-project route`);
            assert.equal(await noOverflow(page), true, `${viewport.width}: no horizontal overflow`);
            await page.locator('.admin-skip-link').focus();
            assert.equal(await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle !== 'none'), true, `${viewport.width}: visible keyboard focus`);
            await page.keyboard.press('Enter');
            assert.equal(await page.evaluate(() => document.activeElement?.id), 'main-content', `${viewport.width}: skip-link target`);
            await page.locator('.admin-content').screenshot({ path: path.join(output, `viewport-${viewport.width}.png`) });
            assertNoBrowserErrors(errors, `viewport-${viewport.width}`);
            await page.close();
        }

        const noJsBrowser = await chromium.launch({ headless: true, executablePath: edgeExecutable });
        const noJsContext = await noJsBrowser.newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
        const noJs = await noJsContext.newPage();
        await noJs.goto(visualUrl('actions'));
        assert.equal(await noJs.getByRole('article', { name: recommendationName }).getByRole('link', { name: 'Add project' }).isVisible(), true, 'no-JavaScript: recommendation navigation remains usable');
        await noJsBrowser.close();

        const desktop = await pageAt({ width: 1440, height: 900 });
        const desktopErrors = captureBrowserErrors(desktop);
        await desktop.goto(visualUrl('actions'));
        await desktop.evaluate(() => {
            localStorage.setItem('ather.evidenceHub.language', 'ar');
            localStorage.setItem('ather.evidenceHub.theme', 'ar');
            localStorage.setItem('ather.evidenceHub.sidebarCollapsed', 'false');
        });
        await desktop.reload();
        assert.equal(await desktop.locator('html').getAttribute('lang'), 'en', 'stored Arabic preference: language falls back to English');
        assert.equal(await desktop.locator('html').getAttribute('dir'), 'ltr', 'stored Arabic preference: direction remains LTR');
        assert.equal(await desktop.locator('body').getAttribute('data-evidence-hub-theme'), 'light', 'invalid persisted preference: safe light fallback');
        const rail = desktop.locator('.evidence-hub-sidebar');
        const railBox = await rail.boundingBox();
        assert.ok(railBox && Math.round(railBox.x) === 0 && Math.round(railBox.width) >= 224 && Math.round(railBox.width) <= 240, 'desktop: expanded left rail is 224–240px');
        const firstCardBox = await desktop.getByRole('article', { name: recommendationName }).first().boundingBox();
        if (firstCardBox) console.log(`FIRST_RECOMMENDATION_BOX=${Math.round(firstCardBox.x)},${Math.round(firstCardBox.y)},${Math.round(firstCardBox.width)},${Math.round(firstCardBox.height)}`);
        assert.ok(firstCardBox && firstCardBox.y + firstCardBox.height <= 900, 'desktop: first actionable recommendation is complete above the fold');
        const overviewBox = await desktop.locator('.evidence-hub-status').boundingBox();
        const recommendationsHeadingBox = await desktop.getByRole('heading', { name: 'Recommended next steps' }).boundingBox();
        assert.ok(overviewBox && recommendationsHeadingBox && recommendationsHeadingBox.y - (overviewBox.y + overviewBox.height) <= 64, 'desktop: no excessive gap before recommendations');
        const recommendationGrid = await desktop.locator('.evidence-hub-recommendation-grid').evaluate(node => getComputedStyle(node).gridTemplateColumns);
        assert.equal(recommendationGrid.split(' ').length, 1, 'desktop: recommendations use full-width rows');
        const primaryBackground = await desktop.locator('.evidence-hub-primary-cta').first().evaluate(node => getComputedStyle(node).backgroundColor);
        assert.notEqual(primaryBackground, 'rgb(37, 99, 235)', 'desktop: evidence actions do not inherit the shared blue primary');
        await desktop.locator('.admin-content').screenshot({ path: path.join(output, 'desktop-expanded-light.png') });
        await desktop.locator('#evidence-hub-sidebar-toggle').click();
        assert.equal(await desktop.locator('body').evaluate(node => node.classList.contains('evidence-hub-sidebar-collapsed')), true, 'desktop: rail collapses');
        await desktop.waitForFunction(() => Math.round(document.querySelector('.evidence-hub-sidebar').getBoundingClientRect().width) === 82);
        assert.equal(Math.round((await rail.boundingBox()).width), 82, 'desktop: collapsed rail is 82px');
        await desktop.locator('#evidence-hub-theme-toggle').focus();
        await desktop.screenshot({ path: path.join(output, 'desktop-collapsed-tooltip.png') });
        assert.equal(await desktop.locator('#evidence-hub-sidebar-toggle').getAttribute('aria-label'), 'Expand navigation', 'desktop: collapsed rail label');
        await desktop.locator('#evidence-hub-theme-toggle').click();
        assert.equal(await desktop.locator('body').getAttribute('data-evidence-hub-theme'), 'dark', 'desktop: dark theme applies');
        assert.equal(await desktop.locator('#evidence-hub-theme-toggle').getAttribute('aria-label'), 'Enable light theme', 'desktop: dark theme control label');
        await desktop.locator('.admin-content').screenshot({ path: path.join(output, 'desktop-expanded-dark.png') });
        await desktop.locator('#evidence-hub-sidebar-toggle').focus();
        await desktop.screenshot({ path: path.join(output, 'keyboard-focus.png') });
        await desktop.setViewportSize({ width: 1101, height: 768 });
        assert.equal(await desktop.locator('#evidence-hub-mobile-drawer').evaluate(node => !node.inert), true, '1101: desktop rail remains available');
        await desktop.setViewportSize({ width: 1100, height: 768 });
        await desktop.waitForFunction(() => document.querySelector('#evidence-hub-mobile-drawer').inert === true);
        assert.equal(await desktop.locator('#evidence-hub-mobile-toggle').isVisible(), true, '1100: mobile drawer becomes authoritative');
        assert.equal(await desktop.locator('#evidence-hub-mobile-drawer').evaluate(node => node.inert), true, '1100: closed mobile drawer is inert');
        assertNoBrowserErrors(desktopErrors, 'desktop');
        await desktop.close();

        for (const viewport of [{ width: 1100, height: 768 }, { width: 390, height: 844 }, { width: 360, height: 800 }, { width: 320, height: 568 }]) {
            const page = await pageAt(viewport);
            const errors = captureBrowserErrors(page);
            await page.goto(visualUrl('actions'));
            const opener = page.locator('#evidence-hub-mobile-toggle');
            const drawer = page.locator('#evidence-hub-mobile-drawer');
            const backdrop = page.locator('#evidence-hub-mobile-backdrop');
            await opener.click();
            await page.waitForFunction(() => Math.round(document.querySelector('#evidence-hub-mobile-drawer').getBoundingClientRect().x) === 0);
            const drawerBox = await drawer.boundingBox();
            assert.ok(drawerBox && Math.round(drawerBox.x) === 0, `${viewport.width}: drawer originates from the left`);
            assert.equal(await page.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-scroll-lock')), true, `${viewport.width}: background is inert/scroll-locked`);
            assert.equal(await page.locator('#main-content').evaluate(node => node.inert && node.getAttribute('aria-hidden') === 'true'), true, `${viewport.width}: main content is inert while drawer is open`);
            assert.equal(await backdrop.isVisible(), true, `${viewport.width}: backdrop appears`);
            await page.keyboard.press('Escape');
            assert.equal(await opener.evaluate(node => node === document.activeElement), true, `${viewport.width}: Escape restores focus`);
            assert.equal(await page.locator('#main-content').evaluate(node => !node.inert && !node.hasAttribute('aria-hidden')), true, `${viewport.width}: main content is restored after Escape`);
            await opener.click();
            await page.mouse.click(viewport.width - 2, 2);
            assert.equal(await opener.evaluate(node => node === document.activeElement), true, `${viewport.width}: backdrop restores focus`);
            assert.equal(await noOverflow(page), true, `${viewport.width}: drawer has no horizontal overflow`);
            if (viewport.width === 390) {
                await opener.click();
                await page.screenshot({ path: path.join(output, 'mobile-drawer.png') });
                await page.keyboard.press('Escape');
                await page.locator('.admin-content').screenshot({ path: path.join(output, 'mobile-390.png') });
            }
            assertNoBrowserErrors(errors, `drawer-${viewport.width}`);
            await page.close();
        }

        const reduced = await pageAt({ width: 768, height: 1024 });
        const reducedErrors = captureBrowserErrors(reduced);
        await reduced.emulateMedia({ reducedMotion: 'reduce' });
        await reduced.goto(visualUrl('partial'));
        assert.equal(await reduced.getByText('All recommendations reviewed', { exact: true }).isVisible(), true, 'complete state: English copy');
        assert.equal(await reduced.locator('.evidence-hub-sidebar').evaluate(node => getComputedStyle(node).transitionDuration), '0s', 'reduced motion disables transitions');
        assertNoBrowserErrors(reducedErrors, 'reduced-motion');
        await reduced.close();

        const safe = await pageAt({ width: 1440, height: 900 });
        await safe.goto(visualUrl('error'));
        assert.equal(await safe.getByRole('alert').textContent(), 'Evidence Hub is temporarily unavailable.Please try again shortly.', 'safe error is English');
        await safe.close();
        console.log('PASS Evidence Hub R4 English/LTR visual contract');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
