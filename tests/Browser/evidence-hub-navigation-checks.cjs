'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const sections = ['#next-steps', '#overview', '#project-evidence', '#technologies', '#progress'];
const destinations = ['/owner.php', '/owner_projects.php', '/owner/evidence-hub'];
const fixture = (base, locale) => `${base}/tests/Browser/evidence-hub-page-visual.php?variant=partial&lang=${locale}`;

const style = (locator, properties) => locator.evaluate((node, keys) => Object.fromEntries(keys.map(key => [key, getComputedStyle(node)[key]])), properties);
const inside = async (locator, width) => {
    const box = await locator.boundingBox();
    assert(box && box.x >= 0 && box.x + box.width <= width + 1 && box.y >= 0, 'Navigation popup stays inside viewport');
};

module.exports = async function evidenceNavigationChecks(browser, base, output) {
    // Compare against the actual Dashboard stylesheet and public Portfolio navigation rules.
    const reference = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await reference.goto(`${base}/tests/Browser/owner-workspace-visual.php?lang=en&state=preview`);
    const sidebarReference = await style(reference.locator('.owner-nav-item[aria-current="page"]'), ['fontSize', 'lineHeight', 'borderRadius', 'columnGap', 'backgroundColor', 'color']);
    const iconReference = await style(reference.locator('.owner-nav-item[aria-current="page"] svg'), ['width', 'height', 'strokeWidth']);
    const wordmarkReference = await style(reference.locator('.owner-wordmark'), ['fontSize', 'letterSpacing']);
    await reference.setContent(`<link rel="stylesheet" href="${base}/assets/css/portfolio.css"><body class="portfolio-page"><header class="portfolio-header"><div class="portfolio-header-inner"><span>ATHER</span><nav class="portfolio-nav-links"><a class="is-current" href="#sample">Sample</a></nav><span></span></div></header></body>`);
    const headerReference = await style(reference.locator('.portfolio-header-inner'), ['minHeight', 'columnGap']);
    const linkReference = await style(reference.locator('.portfolio-nav-links a'), ['fontSize', 'lineHeight', 'minHeight', 'textUnderlineOffset']);
    await reference.close();

    for (const locale of ['ar', 'en']) for (const theme of ['light', 'dark']) {
        for (const width of [320, 390, 768, 1100, 1101, 1280, 1920, 2560]) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, colorScheme: theme, reducedMotion: 'reduce', hasTouch: true });
            await context.addInitScript(value => localStorage.setItem('ather.evidenceHub.theme', value), theme);
            const page = await context.newPage();
            const errors = []; page.on('pageerror', error => errors.push(error.message));
            await page.goto(fixture(base, locale)); await page.evaluate(() => document.fonts.ready);
            const top = page.locator('.evidence-hub-section-nav');
            const rail = page.locator('.evidence-hub-sidebar');
            assert.deepEqual(await top.locator('a').evaluateAll(nodes => nodes.map(node => node.getAttribute('href'))), sections, 'Existing section order and destinations stay intact');
            assert.deepEqual(await rail.locator('nav a').evaluateAll(nodes => nodes.map(node => node.getAttribute('href'))), destinations, 'Only existing Evidence workspace destinations');
            assert.equal(await rail.locator('.evidence-hub-future-link[aria-disabled=true]').count(), 1, 'Credentials remains the unavailable placeholder');
            assert.equal(await page.locator('.evidence-hub-topbar').getByText('Let’s talk', { exact: true }).count(), 0);
            assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'), theme);
            assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
            assert.deepEqual(await style(top.locator('a').first(), Object.keys(linkReference)), linkReference, 'Portfolio navigation typography and target geometry');
            if (width >= 1280) assert.deepEqual(await style(page.locator('.evidence-hub-topbar-inner'), Object.keys(headerReference)), headerReference, 'Portfolio header height and spacing');

            for (const target of sections) {
                const link = top.locator(`a[href="${target}"]`);
                await link.focus(); await page.keyboard.press('Enter');
                await page.waitForFunction(id => {
                    const section = document.querySelector(id), header = document.querySelector('.evidence-hub-topbar');
                    return section.getClientRects().length && section.getBoundingClientRect().top >= header.getBoundingClientRect().bottom;
                }, target);
                assert.equal(await link.getAttribute('aria-current'), 'true', 'Current section remains announced');
                assert.equal(await link.evaluate(node => getComputedStyle(node).textDecorationLine), 'underline', 'Current section uses Portfolio underline');
            }

            if (width <= 1100) {
                assert.equal(await rail.evaluate(node => node.inert), true);
                await page.locator('#evidence-hub-mobile-toggle').focus(); await page.keyboard.press('Enter');
                assert.equal(await page.locator('#main-content').evaluate(node => node.inert), true);
            } else {
                assert.equal(Math.round((await rail.boundingBox()).width), 228, 'Dashboard expanded width');
                assert.deepEqual(await style(rail.locator('nav a[aria-current=page]'), Object.keys(sidebarReference)), sidebarReference, 'Dashboard selected state, spacing and typography');
                assert.deepEqual(await style(rail.locator('nav a[aria-current=page] svg'), Object.keys(iconReference)), iconReference, 'Dashboard icon geometry');
                assert.deepEqual(await style(rail.locator('.evidence-hub-brand-logo'), Object.keys(wordmarkReference)), wordmarkReference, 'Dashboard full wordmark typography');
                assert.equal(await rail.locator('[data-evidence-hub-panel-state="close"] svg').evaluate(node => getComputedStyle(node).transform), locale === 'ar' ? 'matrix(-1, 0, 0, 1, 0, 0)' : 'none', 'Collapse icon follows workspace direction');
                await page.locator('#evidence-hub-sidebar-toggle').focus(); await page.keyboard.press('Enter');
                assert.equal(Math.round((await rail.boundingBox()).width), 72, 'Dashboard compact width');
                assert.equal(await rail.locator('[data-evidence-hub-panel-state="open"] svg').evaluate(node => getComputedStyle(node).transform), locale === 'ar' ? 'matrix(-1, 0, 0, 1, 0, 0)' : 'none', 'Expand icon follows workspace direction');
                assert.equal((await rail.locator('.evidence-hub-brand-compact').textContent()).replace(/\s/g, ''), 'A•');
                assert(await rail.locator('.evidence-hub-brand-compact').evaluate(node => node.scrollWidth <= node.clientWidth + 1), 'Compact wordmark is never clipped');
                await page.reload();
                assert.equal(await page.locator('body').evaluate(node => node.classList.contains('evidence-hub-sidebar-collapsed')), true, 'Evidence-specific collapse preference survives reload');
                const current = rail.locator('nav a[aria-current=page]');
                await current.focus();
                await page.locator('#owner-sidebar-tooltip:not([hidden])').waitFor();
                assert((await page.locator('#owner-sidebar-tooltip').textContent()).includes((await current.textContent()).trim()), 'Compact icon description names the destination');
                await inside(page.locator('#owner-sidebar-tooltip'), width);
            }

            const account = rail.locator('.evidence-hub-account-menu summary');
            await account.focus(); await page.keyboard.press('Enter');
            await page.locator('.evidence-hub-account-panel:visible').waitFor();
            await inside(page.locator('.evidence-hub-account-panel'), width);
            assert.deepEqual(await rail.locator('.evidence-hub-account-panel form').evaluateAll(nodes => nodes.map(node => [node.getAttribute('method').toLowerCase(), node.getAttribute('action'), node.querySelector('input[name=csrf_token]') !== null])), [['post', '/owner_switch_account.php', true], ['post', '/owner_logout.php', true]], 'Existing account forms and CSRF stay intact');
            await page.keyboard.press('Escape');
            assert.equal(await account.evaluate(node => node === document.activeElement), true, 'Escape restores account trigger focus');
            if (width <= 1100) {
                await page.keyboard.press('Escape');
                assert.equal(await page.locator('#evidence-hub-mobile-toggle').evaluate(node => node === document.activeElement), true);
            } else {
                await page.locator('#evidence-hub-sidebar-toggle').click();
                assert.equal(await page.locator('.evidence-hub-brand-logo').isVisible(), true);
                assert(await page.locator('.evidence-hub-brand-logo').evaluate(node => node.scrollWidth <= node.clientWidth + 1), 'Full wordmark is never clipped');
            }
            await page.evaluate(() => scrollTo(0, 0));
            await page.addStyleTag({ content: 'html { font-size:200% !important; } body { --eh-text-scale:1.25; }' });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Large text has no horizontal document overflow');
            assert.deepEqual(errors, []);
            await context.close();
        }
        console.log(`PASS Evidence navigation ${locale}/${theme}: Portfolio header rules, Dashboard 228/72px rail, unchanged links, current state, RTL tooltips, account/CSRF, keyboard, responsive and large text`);
    }

    for (const locale of ['ar', 'en']) {
        const short = await browser.newPage({ viewport: { width: 1440, height: 360 }, reducedMotion: 'reduce' });
        await short.goto(fixture(base, locale));
        await short.locator('#evidence-hub-sidebar-toggle').click();
        for (const control of await short.locator('.evidence-hub-sidebar nav a, .evidence-hub-future-link').all()) {
            await control.focus();
            assert(await control.evaluate(node => {
                const box = node.getBoundingClientRect(), rail = node.closest('nav').getBoundingClientRect();
                return box.bottom > rail.top && box.top < rail.bottom;
            }), 'Short-window navigation scrolls keyboard focus into view');
        }
        await short.locator('.evidence-hub-account-menu summary').focus(); await short.keyboard.press('Enter');
        await inside(short.locator('.evidence-hub-account-panel'), 1440);
        await short.close();
        const context = await browser.newContext({ viewport: { width: 390, height: 500 }, javaScriptEnabled: false });
        const page = await context.newPage(); await page.goto(fixture(base, locale));
        assert.equal(await page.locator('.evidence-hub-sidebar nav a').count(), 3);
        await page.locator('.evidence-hub-account-menu summary').click();
        assert.equal(await page.locator('.evidence-hub-account-panel').isVisible(), true);
        await page.locator('.evidence-hub-section-nav a[href="#technologies"]').focus(); await page.keyboard.press('Enter');
        assert.equal(new URL(page.url()).hash, '#technologies');
        assert(await page.locator('#technologies').evaluate(node => node.getBoundingClientRect().top >= 0));
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
        await page.screenshot({ path: path.join(output, `navigation-no-js-${locale}-390.png`) });
        await context.close();
    }
    console.log('PASS Evidence navigation native no-JS Arabic/English section links and account disclosure');
};
