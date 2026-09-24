const assert = require('node:assert/strict');
const path = require('node:path');

// Run within the maintained synthetic Evidence Hub browser harness.
module.exports = async function ownerSidebarContract(browser, baseUrl, output) {
    const page = await browser.newPage();
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=actions`);
    await page.evaluate(() => { localStorage.clear(); });
    await page.reload();
    const rail = page.locator('.evidence-hub-sidebar');
    const nav = rail.locator('nav');
    const toggle = rail.locator('#evidence-hub-sidebar-toggle');
    const near = (actual, expected, label) => assert.ok(Math.abs(actual - expected) < 1, `${label}: ${actual}`);
    const geometry = () => rail.evaluate(s => {
        const rect = n => { const b = n.getBoundingClientRect(); return { x: b.x, y: b.y, width: b.width, height: b.height }; };
        const head = s.querySelector('.evidence-hub-sidebar-head');
        const nav = s.querySelector('nav');
        return { rail: rect(s), head: rect(head), gap: nav.querySelector('.nav-group-label').getBoundingClientRect().top - head.getBoundingClientRect().bottom,
            items: [...nav.querySelectorAll('a')].map(n => ({ ...rect(n), label: n.textContent.trim(), icon: rect(n.querySelector('svg')), text: rect(n.querySelector('span')), lineHeight: parseFloat(getComputedStyle(n).lineHeight) })) };
    });
    near((await rail.boundingBox()).width, 232, 'expanded width');
    let g = await geometry();
    assert.ok(g.head.height >= 68 && g.head.height <= 72, 'brand row 68–72px');
    assert.ok(g.gap >= 24 && g.gap <= 32, 'brand/navigation gap 24–32px');
    assert.deepEqual(await nav.locator('.nav-group-label').allTextContents(), ['Workspace', 'Portfolio']);
    assert.deepEqual(g.items.map(n => n.label), ['Dashboard', 'Project Management', 'Evidence Hub', 'Personal Information', 'Experience', 'Messages', 'Publishing']);
    assert.deepEqual(await nav.locator('a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href'))), ['/owner.php', '/owner_projects.php', '/owner/evidence-hub', '/owner_profile.php', '/owner_experiences.php', '/owner_messages.php', '/owner_publication.php']);
    assert.equal(await nav.locator('[aria-current="page"]').count(), 1);
    for (const item of g.items) {
        assert.ok(item.height >= 46 && item.height <= 52, `${item.label}: compact row`);
        assert.ok(item.text.height <= item.lineHeight + 1, `${item.label}: single line`);
        assert.ok(item.text.x + item.text.width <= item.x + item.width, `${item.label}: label fits`);
        assert.ok(item.icon.width >= 20 && item.icon.width <= 22, `${item.label}: icon size`);
    }
    const active = nav.locator('[aria-current="page"]');
    const colors = () => active.evaluate(n => ({ text: getComputedStyle(n).color, surface: getComputedStyle(n).backgroundColor, label: getComputedStyle(n.querySelector('span')).color }));
    assert.deepEqual(await colors(), { text: 'rgb(20, 63, 44)', surface: 'rgb(216, 243, 231)', label: 'rgb(20, 63, 44)' });
    const normal = nav.locator('a').first();
    const beforeHover = await normal.evaluate(n => getComputedStyle(n).backgroundColor);
    await normal.hover(); await page.waitForTimeout(180);
    assert.notEqual(await normal.evaluate(n => getComputedStyle(n).backgroundColor), beforeHover, 'hover differs');
    await toggle.focus();
    assert.equal(await toggle.getAttribute('aria-label'), 'Collapse navigation');
    assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(await toggle.locator('[data-evidence-hub-panel-state="close"] svg path').getAttribute('d'), 'm14 6-6 6 6 6');
    const target = await toggle.boundingBox(); assert.ok(target.width >= 44 && target.height >= 44);
    // Real Tab traversal from the collapse control through all seven destinations.
    for (const link of await nav.locator('a').all()) {
        await page.keyboard.press('Tab');
        assert.equal(await link.evaluate(n => n === document.activeElement), true, 'keyboard navigation order');
        assert.equal(await link.evaluate(n => n.matches(':focus-visible') && getComputedStyle(n).outlineStyle === 'solid' && parseFloat(getComputedStyle(n).outlineWidth) >= 2), true, 'visible keyboard focus');
    }
    await active.focus(); await page.screenshot({ path: path.join(output, 'sidebar-keyboard-focus.png') });
    for (const theme of ['light', 'dark']) {
        if (await page.locator('body').getAttribute('data-evidence-hub-theme') !== theme) await page.locator('#evidence-hub-theme-toggle').click();
        await page.mouse.move(600, 10); await page.locator('#evidence-hub-theme-toggle').blur();
        assert.equal(await rail.evaluate(n => getComputedStyle(n).backgroundColor), 'rgb(18, 44, 33)', 'theme-stable rail');
        await page.screenshot({ path: path.join(output, `sidebar-expanded-${theme}.png`) });
        await active.screenshot({ path: path.join(output, `sidebar-active-${theme}.png`) });
        await toggle.click(); await page.waitForTimeout(250); near((await rail.boundingBox()).width, 78, 'collapsed width');
        assert.equal(await toggle.getAttribute('aria-label'), 'Expand navigation');
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await toggle.locator('[data-evidence-hub-panel-state="open"] svg path').getAttribute('d'), 'm10 6 6 6-6 6');
        assert.equal(await rail.locator('.brand-mark').isVisible(), true, 'collapsed brand remains visible');
        for (const link of await nav.locator('a').all()) {
            await link.hover(); await page.waitForTimeout(30);
            const tooltip = page.getByRole('tooltip'); assert.equal(await tooltip.isVisible(), true, 'hover tooltip');
            assert.equal(await tooltip.textContent(), (await link.textContent()).trim());
            await page.mouse.move(600, 10); await link.focus();
            assert.equal(await tooltip.isVisible(), true, 'focus tooltip');
            const box = await tooltip.boundingBox();
            assert.ok(box.x >= 78 && box.x + box.width < 1440 && box.y >= 0 && box.y + box.height <= 900, 'tooltip outside scroll region and inside viewport');
        }
        await page.screenshot({ path: path.join(output, `sidebar-collapsed-${theme}.png`) });
        await page.keyboard.press('Escape'); assert.equal(await page.getByRole('tooltip').isVisible(), false, 'tooltip Escape');
        await toggle.click(); await page.waitForTimeout(250);
    }
    for (const height of [900, 768, 500]) {
        await page.setViewportSize({ width: 1440, height });
        const navState = await nav.evaluate(n => ({ height: n.clientHeight, scroll: n.scrollHeight, overflow: getComputedStyle(n).overflowY }));
        assert.equal(navState.overflow, 'auto', 'internal scrolling');
        if (height === 500) assert.ok(navState.scroll > navState.height, 'short navigation scrolls');
        await toggle.focus(); await nav.getByRole('link', { name: 'Publishing', exact: true }).focus(); await page.waitForTimeout(250);
        const item = await nav.getByRole('link', { name: 'Publishing', exact: true }).boundingBox();
        assert.ok(item.y >= 0 && item.y + item.height <= height, `Publishing reachable at ${height}: ${JSON.stringify(item)}`);
        const footer = await rail.locator('.sidebar-footer').boundingBox();
        assert.ok(footer.y + footer.height <= height, 'Sign out reachable');
        if (height === 768) await page.screenshot({ path: path.join(output, 'sidebar-height-768.png') });
    }
    for (const viewport of [{ width: 1280, height: 800 }, { width: 1024, height: 768 }, { width: 768, height: 768 }, { width: 390, height: 844 }, { width: 360, height: 800 }, { width: 320, height: 568 }]) {
        await page.setViewportSize(viewport);
        if (viewport.width <= 1100) {
            const opener = page.locator('#evidence-hub-mobile-toggle');
            await opener.click(); await page.waitForTimeout(220);
            const box = await rail.boundingBox(); near(box.x, 0, 'left drawer origin'); near(box.width, Math.min(300, viewport.width - 32), 'drawer width');
            assert.equal(await toggle.isVisible(), false, 'desktop collapse hidden');
            assert.equal(await page.locator('#main-content').evaluate(n => n.inert), true, 'background inert');
            assert.equal(await page.locator('body').evaluate(n => n.classList.contains('evidence-hub-mobile-scroll-lock')), true, 'scroll lock');
            await rail.getByRole('button', { name: 'Sign out', exact: true }).focus(); await page.keyboard.press('Tab');
            assert.equal(await rail.locator('.admin-brand').evaluate(n => document.activeElement === n), true, 'drawer focus contained');
            if (viewport.width === 390) await page.screenshot({ path: path.join(output, 'sidebar-mobile-390.png') });
            await page.keyboard.press('Escape'); assert.equal(await opener.evaluate(n => n === document.activeElement), true, 'Escape restores focus');
            await opener.click(); await page.waitForTimeout(220);
            await page.locator('#evidence-hub-mobile-backdrop').click({ position: { x: viewport.width - 2, y: 400 } });
            assert.equal(await opener.evaluate(n => n === document.activeElement), true, 'backdrop restores focus');
            assert.equal(await page.locator('#main-content').evaluate(n => n.inert), false, 'background restored');
        }
        assert.equal(await page.evaluate(() => document.body.scrollWidth <= innerWidth && document.documentElement.scrollWidth <= innerWidth), true, 'no horizontal overflow');
    }
    await page.close();
    const noJs = await browser.browser().newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
    const fallback = await noJs.newPage();
    await fallback.goto(`${baseUrl}/tests/phase2/support/evidence-hub-owner-visual.php?variant=actions`);
    assert.equal(await fallback.getByRole('link', { name: 'Project Management', exact: true }).isVisible(), true, 'no-JS navigation');
    await noJs.close();
    console.log('PASS Owner Sidebar R1 visual contract');
};
