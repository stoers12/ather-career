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
    const pageAtViewport = async viewport => {
        const page = await browser.newPage();
        await page.setViewportSize(viewport);
        return page;
    };
    try {
        for (const width of [1440, 768, 360]) {
            const page = await pageAtViewport({ width, height: 1000 });
            const errors = captureBrowserErrors(page);
            await page.goto(visualUrl('actions'));
            await page.getByText('تغطية التوثيق', { exact: true }).waitFor();
            await page.getByRole('heading', { name: 'خريطة الأدلة التقنية' }).waitFor();
            await page.getByRole('heading', { name: 'تقدم ملف الأعمال' }).waitFor();
            assert.equal(await page.getByText('جاهزية الملف', { exact: true }).isVisible(), true, `${width}: portfolio status section`);
            const activeNavigation = width >= 1101
                ? page.getByRole('link', { name: 'مركز الأدلة' })
                : page.locator('#evidence-hub-mobile-drawer a[href="/owner/evidence-hub"]');
            assert.equal(await activeNavigation.getAttribute('aria-current'), 'page', `${width}: active navigation state`);
            assert.equal(await page.getByRole('article', { name: 'توصية مركز الأدلة' }).getByRole('link', { name: 'إضافة مشروع' }).getAttribute('href'), '/owner_projects.php?add=1', `${width}: authorized add-project destination`);
            assert.equal(await page.locator('[aria-label="توصية مركز الأدلة"]').count(), 1, `${width}: recommendation semantics`);
            assert.equal(await page.locator('html').getAttribute('dir'), 'rtl', `${width}: Arabic RTL document`);
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

        const noJavaScriptBrowser = await chromium.launch({ headless: true, executablePath: edgeExecutable });
        const noJavaScriptContext = await noJavaScriptBrowser.newContext({ viewport: { width: 360, height: 1000 }, javaScriptEnabled: false });
        const noJavaScript = await noJavaScriptContext.newPage();
        await noJavaScript.goto(visualUrl('actions'));
        assert.equal(await noJavaScript.getByRole('article', { name: 'توصية مركز الأدلة' }).getByRole('link', { name: 'إضافة مشروع' }).isVisible(), true, 'no-JavaScript: recommendation navigation remains usable');
        await noJavaScriptBrowser.close();

        const navigation = await pageAtViewport({ width: 1440, height: 1000 });
        const navigationErrors = captureBrowserErrors(navigation);
        await navigation.goto(visualUrl('actions'));
        await navigation.locator('#evidence-hub-sidebar-toggle').click();
        assert.equal(await navigation.locator('body').evaluate(node => node.classList.contains('evidence-hub-sidebar-collapsed')), true, 'desktop: collapsed sidebar preference');
        await navigation.setViewportSize({ width: 360, height: 1000 });
        await navigation.locator('#evidence-hub-mobile-toggle').click();
        assert.equal(await navigation.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-open')), true, 'mobile: drawer opens');
        await navigation.keyboard.press('Escape');
        assert.equal(await navigation.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-open')), false, 'mobile: drawer closes with Escape');
        await assertNoBrowserErrors(navigationErrors, 'navigation');
        await navigation.close();

        for (const viewport of [{ width: 1100, height: 768 }, { width: 1024, height: 768 }, { width: 390, height: 844 }, { width: 320, height: 568 }]) {
            const drawer = await pageAtViewport(viewport);
            const drawerErrors = captureBrowserErrors(drawer);
            await drawer.goto(visualUrl('actions'));
            const opener = drawer.locator('#evidence-hub-mobile-toggle');
            const close = drawer.getByRole('button', { name: 'إغلاق القائمة' });
            const backdrop = drawer.locator('#evidence-hub-mobile-backdrop');
            assert.equal(await opener.getAttribute('aria-expanded'), 'false', `${viewport.width}: drawer starts closed`);
            assert.equal(await backdrop.isVisible(), false, `${viewport.width}: backdrop starts hidden`);
            await opener.click();
            assert.equal(await opener.getAttribute('aria-expanded'), 'true', `${viewport.width}: opener expands drawer`);
            assert.equal(await close.evaluate(node => node === document.activeElement), true, `${viewport.width}: focus moves to close control`);
            assert.equal(await backdrop.isVisible(), true, `${viewport.width}: backdrop opens`);
            assert.equal(await drawer.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-scroll-lock')), true, `${viewport.width}: background scroll locks`);
            await close.click();
            assert.equal(await opener.evaluate(node => node === document.activeElement), true, `${viewport.width}: close restores opener focus`);
            assert.equal(await drawer.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-scroll-lock')), false, `${viewport.width}: close releases scroll lock`);
            await opener.click();
            await backdrop.click({ position: { x: 2, y: 2 } });
            assert.equal(await opener.evaluate(node => node === document.activeElement), true, `${viewport.width}: backdrop restores opener focus`);
            await opener.click();
            await drawer.keyboard.press('Escape');
            assert.equal(await opener.evaluate(node => node === document.activeElement), true, `${viewport.width}: Escape restores opener focus`);
            await opener.click();
            await drawer.locator('#evidence-hub-mobile-drawer a').first().evaluate(link => link.addEventListener('click', event => event.preventDefault(), { once: true }));
            await drawer.locator('#evidence-hub-mobile-drawer a').first().click();
            assert.equal(await drawer.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-open')), false, `${viewport.width}: navigation closes drawer`);
            assert.equal(await drawer.locator('body').evaluate(node => node.classList.contains('evidence-hub-mobile-scroll-lock')), false, `${viewport.width}: navigation releases scroll lock`);
            await opener.click();
            await drawer.setViewportSize({ width: 1440, height: 1000 });
            assert.equal(await drawer.locator('body').evaluate(node => !node.classList.contains('evidence-hub-mobile-open') && !node.classList.contains('evidence-hub-mobile-scroll-lock')), true, `${viewport.width}: desktop resize clears drawer state`);
            assert.equal(await backdrop.isVisible(), false, `${viewport.width}: desktop resize hides backdrop`);
            assert.equal(await drawer.locator('#evidence-hub-mobile-drawer').evaluate(node => !node.inert), true, `${viewport.width}: visible desktop sidebar is not inert`);
            await drawer.setViewportSize(viewport);
            assert.equal(await opener.getAttribute('aria-expanded'), 'false', `${viewport.width}: mobile resize returns closed`);
            assert.equal(await drawer.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${viewport.width}: no horizontal overflow`);
            await assertNoBrowserErrors(drawerErrors, `drawer-${viewport.width}`);
            await drawer.close();
        }

        const reducedMotion = await pageAtViewport({ width: 768, height: 1000 });
        const reducedMotionErrors = captureBrowserErrors(reducedMotion);
        await reducedMotion.emulateMedia({ reducedMotion: 'reduce' });
        await reducedMotion.goto(visualUrl('partial'));
        assert.equal(await reducedMotion.getByText('لا توجد توصيات حالية', { exact: true }).isVisible(), true, 'partial: no-current-actions state');
        assert.equal(await reducedMotion.locator('.evidence-hub-sidebar').evaluate(node => getComputedStyle(node).transitionDuration), '0s', 'reduced motion disables sidebar transitions');
        await assertNoBrowserErrors(reducedMotionErrors, 'reduced motion');
        await reducedMotion.close();

        const desktop = await pageAtViewport({ width: 1440, height: 1000 });
        const desktopErrors = captureBrowserErrors(desktop);
        await desktop.goto(visualUrl('actions'));
        await desktop.evaluate(() => localStorage.setItem('ather.evidenceHub.sidebarCollapsed', 'false'));
        await desktop.reload();
        assert.equal(await desktop.locator('#evidence-hub-mobile-backdrop').isVisible(), false, 'desktop: backdrop does not intercept input');
        assert.equal(await desktop.locator('body').evaluate(node => !node.classList.contains('evidence-hub-mobile-scroll-lock')), true, 'desktop: body remains scrollable');
        await desktop.locator('#evidence-hub-sidebar-toggle').click();
        assert.equal(await desktop.locator('body').evaluate(node => node.classList.contains('evidence-hub-sidebar-collapsed')), true, 'desktop: collapse remains independent');
        await desktop.waitForFunction(() => Math.round(document.querySelector('.evidence-hub-sidebar').getBoundingClientRect().width) === 82);
        assert.equal(await desktop.locator('.evidence-hub-sidebar').evaluate(node => Math.round(node.getBoundingClientRect().width)), 82, 'desktop: collapsed rail is 82px');
        assert.equal(await desktop.locator('#evidence-hub-sidebar-toggle').evaluate(node => Math.round(node.getBoundingClientRect().width)), 44, 'desktop: collapse control is 44px');
        assert.equal(await desktop.locator('#evidence-hub-sidebar-toggle').getAttribute('aria-label'), 'توسيع الشريط الجانبي', 'desktop: collapse label changes');
        await desktop.locator('#evidence-hub-theme-toggle').click();
        assert.equal(await desktop.locator('body').getAttribute('data-evidence-hub-theme'), 'dark', 'desktop: theme switches on the Evidence Hub root');
        assert.equal(await desktop.locator('#evidence-hub-theme-toggle').getAttribute('aria-pressed'), 'true', 'desktop: dark theme is programmatically exposed');
        assert.equal(await desktop.locator('#evidence-hub-theme-toggle').getAttribute('aria-label'), 'تفعيل المظهر الفاتح', 'desktop: theme label changes');
        await desktop.setViewportSize({ width: 1101, height: 1000 });
        assert.equal(await desktop.locator('#evidence-hub-mobile-drawer').evaluate(node => !node.inert), true, '1101: desktop rail is never inert');
        await desktop.setViewportSize({ width: 1100, height: 1000 });
        assert.equal(await desktop.locator('#evidence-hub-mobile-toggle').isVisible(), true, '1100: mobile drawer authority is active');
        assert.equal(await desktop.locator('#evidence-hub-mobile-drawer').evaluate(node => node.inert), true, '1100: closed mobile drawer is inert');
        await assertNoBrowserErrors(desktopErrors, 'desktop');
        await desktop.close();

        const safeError = await pageAtViewport({ width: 1440, height: 1000 });
        const safeErrorErrors = captureBrowserErrors(safeError);
        await safeError.goto(visualUrl('error'));
        assert.equal(await safeError.getByRole('alert').textContent(), 'مركز الأدلة غير متاح مؤقتًا.يرجى المحاولة لاحقًا.', 'safe error state');
        await assertNoBrowserErrors(safeErrorErrors, 'safe error');
        await safeError.close();
        console.log('PASS Evidence Hub Owner browser contract');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
