const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

module.exports = async function ownerSidebarR11Contract(browser, baseUrl, output) {
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=actions`);
    await page.evaluate(() => { localStorage.removeItem('ather.evidenceHub.sidebarCollapsed'); localStorage.removeItem('ather.evidenceHub.theme'); });
    await page.reload();
    const root = page.locator('body');
    const aside = page.locator('#evidence-hub-mobile-drawer');
    const nav = aside.locator('nav');
    const toggle = page.locator('#evidence-hub-sidebar-toggle');
    const back = page.getByRole('link', { name: 'Back to dashboard' });
    const tooltip = page.getByRole('tooltip');
    const same = async (promise, value, label) => assert.equal(await promise, value, label);
    const metrics = () => page.evaluate(() => {
        const rect = node => { const r = node.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height }; };
        const rail = document.querySelector('.evidence-hub-sidebar');
        const header = rail.querySelector('.evidence-hub-sidebar-head');
        const navigation = rail.querySelector('nav');
        return { rail: rect(rail), header: rect(header), items: [...navigation.querySelectorAll(':scope > a, :scope > button')].map(rect),
            gap: navigation.querySelector('.nav-group-label').getBoundingClientRect().top - header.getBoundingClientRect().bottom,
            overflow: document.documentElement.scrollWidth > innerWidth || document.body.scrollWidth > innerWidth };
    });

    const layoutSource = fs.readFileSync(path.join(process.cwd(), 'includes/owner_layout.php'), 'utf8');
    for (const icon of ['layout-dashboard', 'folder-kanban', 'blocks', 'badge-check', 'arrow-left', 'panel-left-close', 'panel-left-open', 'user-round', 'lock-keyhole', 'log-out', 'menu']) {
        assert.ok(layoutSource.includes(`'${icon}' =>`), `central Lucide renderer contains ${icon}`);
    }
    assert.match(layoutSource, /href="<\?php echo ownerEscapeHtml\(\$dashboardHref\); \?>" aria-label="Back to dashboard"/);
    assert.match(layoutSource, /action="\/owner_logout\.php"/);
    assert.match(layoutSource, /name="csrf_token"/);
    assert.match(layoutSource, /Copyright \(c\) 2022 Lucide Contributors/);

    await same(aside.locator('nav .nav-group-label').count(), 1, 'exactly one navigation group');
    await same(page.locator('body > .admin-layout > .admin-sidebar').count(), 1, 'Evidence Hub route renders exactly one sidebar');
    await same(page.locator('.admin-sidebar:not(.evidence-hub-sidebar)').count(), 0, 'primary blue sidebar is replaced on Evidence Hub');
    await same(aside.locator('nav .nav-group-label').innerText(), 'WORKSPACE', 'workspace group heading');
    const destinations = nav.locator(':scope > a, :scope > button');
    await same(destinations.allTextContents().then(xs => xs.map(x => x.trim()).join('|')), 'Overview|Projects|Evidence Hub|Credentials', 'navigation order and labels');
    await same(nav.getByRole('link', { name: 'Evidence Hub', exact: true }).getAttribute('href'), '/owner/evidence-hub', 'canonical Evidence Hub route');
    await same(nav.locator('[aria-current="page"]').count(), 1, 'one current page');
    await same(nav.locator('[aria-current="page"]').innerText(), 'Evidence Hub', 'Evidence Hub is the active item');
    for (const item of ['Overview', 'Projects', 'Credentials']) {
        const future = nav.getByRole('button', { name: item, exact: true });
        await same(future.getAttribute('aria-disabled'), 'true', `${item} is disabled`);
        await same(future.getAttribute('tabindex'), '0', `${item} remains keyboard focusable`);
        await same(future.getAttribute('href'), null, `${item} has no route`);
        await same(future.getAttribute('title'), 'Coming soon', `${item} has a no-JS tooltip`);
        await future.hover(); await same(tooltip.innerText(), 'Coming soon', `${item} hover tooltip`);
        await future.focus(); await same(tooltip.isVisible(), true, `${item} focus tooltip`);
        const url = page.url();
        await future.press('Enter'); await same(page.url(), url, `${item} Enter does not navigate`);
        await future.press('Space'); await same(page.url(), url, `${item} Space does not navigate`);
    }
    await same(nav.getByText(/Dashboard|Project Management|Personal Information|Experience|Messages|Publishing|Settings|ACCOUNT/).count(), 0, 'system destinations and account group are absent from green navigation');
    await same(page.locator('.evidence-hub-sidebar-back').getAttribute('href'), '/owner.php', 'canonical dashboard destination');
    await same(back.getAttribute('aria-label'), 'Back to dashboard', 'back link accessible name');
    await back.hover(); await same(tooltip.innerText(), 'Back to dashboard', 'back link tooltip');
    await same(aside.locator('nav a[href="/owner.php"]').count(), 0, 'dashboard is not a green navigation destination');
    await same(aside.locator('.evidence-hub-owner-context').innerText().then(x => x.replace(/\s+/g, ' ').trim()), 'Portfolio owner Private workspace', 'safe footer context only');
    await same(aside.locator('.evidence-hub-owner-context').isVisible(), true, 'owner workspace details are visible in the expanded sidebar');
    await same(aside.getByRole('button', { name: 'Log out' }).isVisible(), true, 'Log out is visible in the expanded sidebar');
    await same(aside.locator('.sidebar-logout-form').getAttribute('action'), '/owner_logout.php', 'existing secure sign-out destination');
    await same(aside.locator('.sidebar-logout-form input[name="csrf_token"]').count(), 1, 'existing CSRF-protected sign-out');
    await same(aside.locator('.evidence-hub-sidebar-footer').innerText().then(x => /@|auth0|tenant|subject|database|account id/i.test(x)), false, 'footer contains no identity-bearing details');
    await same(aside.locator('.evidence-hub-sidebar nav svg').evaluateAll(nodes => nodes.every(n => n.getAttribute('aria-hidden') === 'true' && n.getAttribute('viewBox') === '0 0 24 24' && n.getAttribute('stroke') === 'currentColor')), true, 'Lucide SVG semantics and style');

    const expanded = await metrics();
    assert.ok(Math.abs(expanded.rail.width - 232) < 1, 'expanded rail is 232px');
    assert.ok(expanded.header.height >= 116 && expanded.header.height <= 124, 'expanded header gives the collapse control its own space');
    assert.ok(expanded.gap >= 10 && expanded.gap <= 18, 'workspace starts below the header');
    for (const item of expanded.items) assert.ok(item.height >= 46 && item.height <= 50, '48px navigation rows');
    const brand = aside.locator('.evidence-hub-brand');
    await same(brand.locator('small').innerText(), 'Evidence Hub', 'brand secondary line');
    await same(brand.getAttribute('aria-label'), 'Ather — Evidence Hub', 'accessible brand label');
    const logo = brand.locator('.evidence-hub-brand-logo');
    await same(logo.getAttribute('src'), '/assets/images/ather-navbar-logo.png', 'official sidebar logo asset');
    await same(logo.getAttribute('alt'), '', 'logo is decorative because its link has an accessible brand name');
    await same(logo.evaluate(n => n.complete && n.naturalWidth === 880 && n.naturalHeight === 155), true, 'official logo loads at its natural 880×155 aspect ratio');
    const [logoBox, subtitleBox, groupBox, backBox, collapseBox] = await Promise.all([
        logo.boundingBox(), brand.locator('small').boundingBox(), nav.locator('.nav-group-label').boundingBox(), back.boundingBox(), toggle.boundingBox(),
    ]);
    assert.ok(Math.abs(logoBox.x + logoBox.width * 9 / 880 - groupBox.x) <= 2, 'visible logo edge aligns with workspace inset after PNG transparency');
    assert.ok(Math.abs(subtitleBox.x - groupBox.x) <= 1, 'subtitle aligns with workspace inset');
    assert.ok(collapseBox.x >= logoBox.x + logoBox.width + 12, 'collapse control clears the logo');
    assert.ok(collapseBox.y >= backBox.y + backBox.height + 8, 'collapse control clears the back link');
    assert.ok(collapseBox.y + collapseBox.height <= groupBox.y - 12, 'collapse control clears WORKSPACE');
    await same(brand.innerText(), 'Evidence Hub', 'logo wordmark is not duplicated as text');
    await same(root.evaluate(n => n.getAttribute('data-evidence-hub-theme')), 'light', 'light theme baseline');
    const railColor = () => aside.evaluate(n => getComputedStyle(n).backgroundColor);
    const active = nav.locator('[aria-current="page"]');
    const activeStyle = () => active.evaluate(n => ({ color: getComputedStyle(n).color, background: getComputedStyle(n).backgroundColor, height: n.getBoundingClientRect().height }));
    const selectedLight = await activeStyle();
    await page.evaluate(() => localStorage.setItem('ather.evidenceHub.theme', 'dark'));
    await page.reload();
    await same(root.evaluate(n => n.getAttribute('data-evidence-hub-theme')), 'dark', 'dark theme');
    await same(railColor(), 'rgb(18, 43, 32)', 'sidebar stays dark green in dark theme');
    assert.deepEqual(await activeStyle(), selectedLight, 'selected-state parity across themes');
    await page.waitForTimeout(200);
    await page.screenshot({ path: path.join(output, 'r11-expanded-dark.png') });
    await page.evaluate(() => localStorage.setItem('ather.evidenceHub.theme', 'light'));
    await page.reload();
    await same(railColor(), 'rgb(18, 43, 32)', 'sidebar stays dark green in light theme');
    await page.screenshot({ path: path.join(output, 'r11-expanded-light.png') });

    await page.evaluate(() => document.activeElement?.blur());
    for (let attempt = 0; attempt < 12 && !(await toggle.evaluate(n => n === document.activeElement)); attempt += 1) await page.keyboard.press('Tab');
    assert.ok(await toggle.evaluate(n => n.matches(':focus-visible') && getComputedStyle(n).outlineStyle === 'solid'), 'collapse control has visible keyboard focus');
    await same(toggle.getAttribute('aria-label'), 'Collapse navigation', 'expanded collapse label');
    await same(toggle.getAttribute('aria-expanded'), 'true', 'expanded state');
    await same(toggle.locator('[data-evidence-hub-panel-state="close"] svg rect').count(), 1, 'Lucide PanelLeftClose icon');
    const toggleBox = await toggle.boundingBox(); assert.ok(toggleBox.width >= 44 && toggleBox.height >= 44, 'collapse target meets 44px');
    await toggle.click(); await page.waitForTimeout(200);
    const collapsed = await metrics();
    assert.ok(Math.abs(collapsed.rail.width - 78) < 1, 'collapsed rail is 78px');
    assert.equal(collapsed.overflow, false, 'collapsed rail does not create horizontal overflow');
    await same(toggle.getAttribute('aria-label'), 'Expand navigation', 'collapsed expand label');
    await same(toggle.getAttribute('aria-expanded'), 'false', 'collapsed state');
    await same(toggle.locator('[data-evidence-hub-panel-state="open"] svg rect').count(), 1, 'Lucide PanelLeftOpen icon');
    await same(page.evaluate(() => localStorage.getItem('ather.evidenceHub.sidebarCollapsed')), 'true', 'collapsed preference is browser local');
    await same(brand.getAttribute('data-sidebar-tooltip'), 'Ather — Evidence Hub', 'collapsed brand tooltip');
    for (const item of ['Overview', 'Projects', 'Credentials']) {
        const future = nav.getByRole('button', { name: item, exact: true });
        await future.hover(); await same(tooltip.innerText(), 'Coming soon', 'collapsed future tooltip');
        await future.focus(); await same(tooltip.isVisible(), true, 'collapsed future keyboard tooltip');
    }
    await back.focus(); await same(tooltip.innerText(), 'Back to dashboard', 'collapsed dashboard tooltip');
    await aside.locator('.evidence-hub-owner-context').focus(); await same(tooltip.innerText(), 'Portfolio owner — Private workspace', 'collapsed owner tooltip');
    await aside.getByRole('button', { name: 'Log out' }).hover(); await same(tooltip.innerText(), 'Log out', 'collapsed log-out tooltip');
    await toggle.hover(); await same(tooltip.innerText(), 'Expand navigation', 'collapsed toggle tooltip');
    await page.keyboard.press('Escape'); await same(tooltip.isVisible(), false, 'Escape dismisses tooltip');
    await toggle.focus();
    await same(page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'collapsed focus does not overflow the document');
    const collapsedFooter = await aside.locator('.evidence-hub-sidebar-footer').boundingBox();
    assert.ok(collapsedFooter && collapsedFooter.y >= 0 && collapsedFooter.y + collapsedFooter.height <= 900, 'full-viewport capture includes the collapsed footer');
    await aside.locator('.evidence-hub-owner-context').focus();
    await same(tooltip.innerText(), 'Portfolio owner — Private workspace', 'light capture shows the owner keyboard-focus tooltip');
    await page.screenshot({ path: path.join(output, 'r11-collapsed-light.png') });
    await page.evaluate(() => localStorage.setItem('ather.evidenceHub.theme', 'dark'));
    await page.reload();
    await same(root.evaluate(n => n.getAttribute('data-evidence-hub-theme')), 'dark', 'dark theme persists for collapsed capture');
    await same(page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'collapsed dark page has no tooltip overflow');
    const darkContext = await browser.browser().newContext({ viewport: { width: 1440, height: 900 } });
    const darkPage = await darkContext.newPage();
    await darkPage.goto(`${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=actions`);
    await darkPage.evaluate(() => {
        localStorage.setItem('ather.evidenceHub.sidebarCollapsed', 'true');
        localStorage.setItem('ather.evidenceHub.theme', 'dark');
    });
    await darkPage.reload();
    await same(darkPage.locator('body').evaluate(n => n.classList.contains('evidence-hub-sidebar-collapsed') && n.getAttribute('data-evidence-hub-theme') === 'dark'), true, 'fresh dark capture uses collapsed sidebar');
    await darkPage.getByRole('button', { name: 'Log out' }).hover();
    await same(darkPage.getByRole('tooltip').innerText(), 'Log out', 'dark capture shows the log-out hover tooltip');
    await darkPage.waitForTimeout(200);
    await darkPage.screenshot({ path: path.join(output, 'r11-collapsed-dark.png') });
    await darkContext.close();
    await page.reload();
    await same(root.evaluate(n => n.classList.contains('evidence-hub-sidebar-collapsed')), true, 'collapse preference persists across reload');
    await same(page.evaluate(() => document.documentElement.scrollWidth <= innerWidth && document.body.scrollWidth <= innerWidth), true, 'no desktop horizontal overflow while collapsed');
    await page.locator('#evidence-hub-sidebar-toggle').click(); await page.waitForTimeout(200);

    for (const width of [1440, 1280, 1101]) {
        await page.setViewportSize({ width, height: 900 });
        const rail = await aside.boundingBox(); assert.ok(rail && Math.abs(rail.width - 232) < 1, `${width}px uses expanded desktop rail`);
        await same(page.locator('#evidence-hub-mobile-toggle').isVisible(), false, `${width}px is desktop`);
    }
    for (const width of [1100, 1024, 768, 390, 360, 320]) {
        await page.setViewportSize({ width, height: width < 400 ? 568 : 768 });
        await same(aside.evaluate(n => n.inert), true, `${width}px drawer begins closed and inert`);
        await same(root.evaluate(n => n.classList.contains('evidence-hub-sidebar-collapsed')), false, `${width}px does not inherit desktop collapse state`);
        await same(page.evaluate(() => document.documentElement.scrollWidth <= innerWidth && document.body.scrollWidth <= innerWidth), true, `${width}px has no horizontal overflow`);
        const opener = page.getByRole('button', { name: 'Open navigation' });
        await opener.click(); await page.waitForTimeout(200);
        const box = await aside.boundingBox();
        assert.ok(box && Math.abs(box.x) < 1, `${width}px drawer opens from physical left`);
        assert.ok(box.width <= 300 && Math.abs(box.width - Math.min(300, width - 32)) < 1, `${width}px drawer respects maximum width`);
        await same(aside.locator('.evidence-hub-owner-context').isVisible(), true, `${width}px drawer shows owner workspace details`);
        await same(aside.getByRole('button', { name: 'Log out' }).isVisible(), true, `${width}px drawer shows Log out`);
        await same(page.locator('#main-content').evaluate(n => n.inert), true, `${width}px background is inert`);
        await same(page.locator('#evidence-hub-mobile-backdrop').isVisible(), true, `${width}px backdrop is visible and hit-testable`);
        const future = nav.getByRole('button', { name: 'Credentials' });
        const currentUrl = page.url(); await future.focus(); await page.keyboard.press('Enter'); await page.keyboard.press('Space');
        await same(page.url(), currentUrl, `${width}px future item does not navigate`);
        await same(root.evaluate(n => n.classList.contains('evidence-hub-mobile-open')), true, `${width}px disabled item leaves drawer open`);
        if (width === 390) {
            await page.screenshot({ path: path.join(output, 'r11-mobile-drawer-390.png') });
        }
        const tabbables = aside.locator('a, button, [tabindex="0"]');
        await tabbables.first().focus(); await page.keyboard.press('Shift+Tab');
        await same(tabbables.last().evaluate(n => n === document.activeElement), true, `${width}px reverse focus wraps in drawer`);
        await tabbables.last().focus();
        await page.keyboard.press('Tab');
        await same(brand.evaluate(n => n === document.activeElement), true, `${width}px forward focus wraps in drawer`);
        await page.keyboard.press('Escape');
        await same(opener.evaluate(n => n === document.activeElement), true, `${width}px Escape restores opener focus`);
        await same(page.locator('#main-content').evaluate(n => n.inert), false, `${width}px main content restored`);
        if (width === 390) {
            await opener.click();
            await page.evaluate(() => document.addEventListener('click', event => { if (event.target.closest('.evidence-hub-sidebar nav a.active')) event.preventDefault(); }, { once: true }));
            await nav.getByRole('link', { name: 'Evidence Hub', exact: true }).click();
            await same(root.evaluate(n => n.classList.contains('evidence-hub-mobile-open')), false, 'valid destination closes mobile drawer');
        }
        await opener.click(); await page.locator('#evidence-hub-mobile-backdrop').click({ position: { x: width - 2, y: 400 } });
        await same(opener.evaluate(n => n === document.activeElement), true, `${width}px backdrop restores opener focus`);
        await same(aside.evaluate(n => n.inert), true, `${width}px closed drawer is inert`);
    }

    await page.setViewportSize({ width: 1440, height: 400 });
    const scrolling = await nav.evaluate(n => ({ client: n.clientHeight, scroll: n.scrollHeight, overflow: getComputedStyle(n).overflowY }));
    await same(Promise.resolve(scrolling.overflow), 'auto', 'short desktop scrolls only workspace navigation');
    assert.ok(scrolling.scroll > scrolling.client, 'short desktop workspace region scrolls internally');
    await nav.evaluate(n => { n.scrollTop = n.scrollHeight; });
    await same(nav.getByRole('button', { name: 'Credentials' }).isVisible(), true, 'last future destination remains reachable');
    const footerBox = await aside.locator('.evidence-hub-sidebar-footer').boundingBox();
    assert.ok(footerBox.y + footerBox.height <= 400, 'footer remains reachable at short desktop height');
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await same(aside.evaluate(n => getComputedStyle(n).transitionDuration), '0s', 'reduced motion disables sidebar transition');
    await page.close();

    const fallbackContext = await browser.browser().newContext({ viewport: { width: 390, height: 844 }, javaScriptEnabled: false });
    const fallback = await fallbackContext.newPage();
    await fallback.goto(`${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=actions`);
    await same(fallback.getByRole('link', { name: 'Evidence Hub', exact: true }).getAttribute('href'), '/owner/evidence-hub', 'no-JS current route remains valid');
    await same(fallback.getByRole('button', { name: 'Credentials' }).getAttribute('aria-disabled'), 'true', 'no-JS future destination remains disabled');
    await fallbackContext.close();
    console.log('PASS OWNER-SIDEBAR-R1.1 focused visual contract');
};
