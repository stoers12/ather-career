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
const noOverflow = page => page.evaluate(() => {
    const viewport = innerWidth;
    const closedDrawer = document.querySelector('#evidence-hub-mobile-drawer');
    if (document.body.scrollWidth > viewport + 1 || scrollX !== 0) return false;
    return [...document.body.querySelectorAll('*')].every(node => {
        if (closedDrawer?.inert && node.closest('#evidence-hub-mobile-drawer')) return true;
        const box = node.getBoundingClientRect();
        return box.left >= -1 && box.right <= viewport + 1;
    });
});
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
            const topbar = page.locator('.evidence-hub-topbar');
            const topbarBox = await topbar.boundingBox();
            assert.ok(topbarBox && Math.round(topbarBox.height) >= 60 && Math.round(topbarBox.height) <= 64, `${viewport.width}: distinct 60–64px application top bar`);
            assert.equal(await page.locator('#evidence-hub-theme-toggle').isVisible(), true, `${viewport.width}: theme control is reachable in application chrome`);
            assert.equal(await page.locator('.evidence-hub-presentation .evidence-hub-topbar-breadcrumb, .evidence-hub-presentation .evidence-hub-context-bar').count(), 0, `${viewport.width}: breadcrumb is not duplicated in editorial content`);
            assert.equal(await page.locator('.evidence-hub-hero').getByRole('link', { name: 'Manage projects' }).count(), 0, `${viewport.width}: intro has no global Manage projects action`);
            assert.equal(await page.getByRole('heading', { name: 'Evidence overview' }).count(), 1, `${viewport.width}: overview heading is not duplicated`);
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
        const accent = desktop.locator('.evidence-hub-recommendation-accent').first();
        const accentBox = await accent.boundingBox();
        assert.ok(accentBox && Math.round(accentBox.width) === 4 && firstCardBox && Math.abs(Math.round(accentBox.height) - Math.round(firstCardBox.height)) <= 2, 'desktop: recommendation row has a full-height 4px semantic accent');
        const categoryBox = await desktop.locator('.evidence-hub-recommendation-category').first().boundingBox();
        const iconBox = await desktop.locator('.evidence-hub-recommendation-icon').first().boundingBox();
        const titleBox = await desktop.getByRole('article', { name: recommendationName }).first().getByRole('heading').boundingBox();
        assert.ok(categoryBox && iconBox && titleBox && categoryBox.x > iconBox.x + iconBox.width && categoryBox.y < titleBox.y, 'desktop: category is connected in the metadata row, not detached below the icon');
        const primaryBackground = await desktop.locator('.evidence-hub-primary-cta').first().evaluate(node => getComputedStyle(node).backgroundColor);
        assert.notEqual(primaryBackground, 'rgb(37, 99, 235)', 'desktop: evidence actions do not inherit the shared blue primary');
        await desktop.locator('.admin-content').screenshot({ path: path.join(output, 'desktop-expanded-light.png') });
        await desktop.locator('#evidence-hub-sidebar-toggle').click();
        assert.equal(await desktop.locator('body').evaluate(node => node.classList.contains('evidence-hub-sidebar-collapsed')), true, 'desktop: rail collapses');
        await desktop.waitForFunction(() => Math.round(document.querySelector('.evidence-hub-sidebar').getBoundingClientRect().width) === 78);
        assert.equal(Math.round((await rail.boundingBox()).width), 78, 'desktop: collapsed rail is 78px');
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

        const contexts = await pageAt({ width: 1440, height: 900 });
        await contexts.goto(visualUrl('contexts'));
        const documentationCard = contexts.getByRole('article', { name: recommendationName }).filter({ has: contexts.getByRole('heading', { name: 'Complete project evidence' }) });
        const technologyCard = contexts.getByRole('article', { name: recommendationName }).filter({ has: contexts.getByRole('heading', { name: 'Review technology evidence' }) });
        const publishingCard = contexts.getByRole('article', { name: recommendationName }).filter({ has: contexts.getByRole('heading', { name: 'Prepare your portfolio for publishing' }) });
        assert.equal(await documentationCard.getByText('Project evidence', { exact: true }).count(), 1, 'context: documentation recommendation uses Project evidence');
        assert.equal(await technologyCard.getByText('Technology mapping', { exact: true }).count(), 1, 'context: technology recommendation uses Technology mapping');
        assert.equal(await technologyCard.getByText('Project evidence', { exact: true }).count(), 0, 'context: technology recommendation does not incorrectly use Project evidence');
        assert.equal(await publishingCard.getByText('Publishing readiness', { exact: true }).count(), 2, 'context: publishing recommendation metadata is publishing readiness');
        assert.equal(await publishingCard.getByText('Project evidence', { exact: true }).count(), 0, 'context: publishing recommendation does not incorrectly use Project evidence');
        await contexts.screenshot({ path: path.join(output, 'recommendation-contexts.png') });
        await contexts.close();

        const oneTechnology = await pageAt({ width: 1440, height: 900 });
        await oneTechnology.goto(visualUrl('ready'));
        await oneTechnology.waitForFunction(() => {
            const grid = document.querySelector('.evidence-hub-technology-grid')?.getBoundingClientRect();
            const card = document.querySelector('.evidence-hub-technology-card')?.getBoundingClientRect();
            return Boolean(grid && card && Math.abs(grid.width - card.width) <= 1);
        });
        const oneTechnologyGrid = await oneTechnology.locator('.evidence-hub-technology-grid').boundingBox();
        const oneTechnologyCard = await oneTechnology.locator('.evidence-hub-technology-card').boundingBox();
        assert.ok(oneTechnologyGrid && oneTechnologyCard && Math.abs(Math.round(oneTechnologyGrid.width) - Math.round(oneTechnologyCard.width)) <= 1, 'technology: one mapping uses a full-width compact row');
        await oneTechnology.close();

        const multipleTechnology = await pageAt({ width: 1440, height: 900 });
        await multipleTechnology.goto(visualUrl('multiple'));
        const multipleColumns = await multipleTechnology.locator('.evidence-hub-technology-grid').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length);
        assert.ok(multipleColumns >= 2, 'technology: multiple mappings use available row width');
        await multipleTechnology.close();

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
            await page.waitForFunction(() => {
                const drawer = document.querySelector('#evidence-hub-mobile-drawer');
                return drawer?.inert === true && drawer.getBoundingClientRect().right <= 0;
            });
            assert.equal(await opener.evaluate(node => node === document.activeElement), true, `${viewport.width}: backdrop restores focus`);
            const drawerReflow = await page.evaluate(() => ({
                body: { clientWidth: document.body.clientWidth, scrollWidth: document.body.scrollWidth, width: document.body.getBoundingClientRect().width, classes: document.body.className, overflowX: getComputedStyle(document.body).overflowX },
                innerWidth,
                scrollX,
                rightmost: [...document.querySelectorAll('*')].map(node => { const box = node.getBoundingClientRect(); return { tag: node.tagName, className: typeof node.className === 'string' ? node.className : '', id: node.id, position: getComputedStyle(node).position, left: Math.round(box.left), right: Math.round(box.right), width: Math.round(box.width) }; }).filter(node => node.right > innerWidth + 1).sort((a, b) => b.right - a.right).slice(0, 8),
            }));
            console.log(`DRAWER_REFLOW_${viewport.width}=${JSON.stringify(drawerReflow)}`);
            assert.equal(drawerReflow.body.scrollWidth <= drawerReflow.innerWidth && drawerReflow.scrollX === 0 && drawerReflow.rightmost.length === 0, true, `${viewport.width}: drawer has no horizontal overflow`);
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
