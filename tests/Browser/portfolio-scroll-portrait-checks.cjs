const assert = require('node:assert/strict');
const path = require('node:path');

// Same 2:3 ratio and head/shoulder positions as the inspected source, without owner imagery.
const portraitSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="960"><rect width="640" height="960" fill="#163629"/><circle cx="320" cy="280" r="140" fill="#b8d4aa"/><path d="M90 820V510Q320 380 550 510V820" fill="#87968b"/><text x="55" y="910" fill="white" font-size="23" font-family="sans-serif">Synthetic portrait framing fixture</text></svg>';

const geometry = (page) => page.evaluate(() => {
    const rect = (selector) => {
        const r = document.querySelector(selector).getBoundingClientRect();
        return { top: r.top, documentTop: r.top + scrollY, width: r.width, height: r.height };
    };
    return {
        y: scrollY, height: document.documentElement.scrollHeight,
        header: rect('.portfolio-header'), contact: rect('#contact'), footer: rect('.portfolio-footer'),
        portrait: rect('.portfolio-hero-profile-visual'),
        offset: parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pf-header-offset')),
    };
});

async function runScrollPortraitChecks(browser, output, routes) {
    for (const width of [390, 768, 1280, 1920]) {
        for (const height of [360, 900]) {
            for (const colorScheme of ['light', 'dark']) {
                for (const variant of ['public-portrait', 'preview-private-portrait']) {
                    const page = await browser.newPage({ viewport: { width, height }, colorScheme, reducedMotion: 'reduce' });
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    await routes(page, variant);
                    await page.goto(variant.startsWith('preview') ? 'http://public.test/owner_preview.php' : 'http://public.test/p/synthetic-owner');
                    await page.evaluate(() => document.fonts.ready);
                    await page.waitForFunction(() => document.querySelector('.portfolio-hero-profile-visual img')?.naturalHeight > 0);
                    const initial = await geometry(page);
                    assert.equal(initial.offset, Math.ceil(initial.header.height) + 16, 'Offset follows actual responsive header height');
                    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior), 'auto', 'Reduced motion disables smooth scrolling');
                    assert.equal(await page.locator('.portfolio-hero-profile-visual img').getAttribute('alt'), 'Sami Ahmad portrait');
                    const crop = await page.locator('.portfolio-hero-profile-visual img').evaluate(img => {
                        const frame = img.parentElement;
                        const scale = Math.max(frame.clientWidth / img.naturalWidth, frame.clientHeight / img.naturalHeight);
                        const offset = (frame.clientHeight - img.naturalHeight * scale) * .2;
                        return { head: 140 * scale + offset, shoulders: 650 * scale + offset, height: frame.clientHeight,
                            fit: getComputedStyle(img).objectFit, position: getComputedStyle(img).objectPosition };
                    });
                    assert.equal(crop.fit, 'cover', 'Natural image proportions preserved by object-fit');
                    assert.equal(crop.position, '50% 20%');
                    assert(crop.head > 16 && crop.shoulders < crop.height, 'Headroom, full face and shoulders fit the inspected portrait proportions');

                    // Include tiny reversals and repeated attempts beyond the bottom, then scroll back up.
                    const maximum = initial.height - height;
                    for (const y of [450, maximum - 10, maximum, maximum + 80, maximum - 1, maximum, maximum - 2, maximum, maximum - 150, 450]) {
                        await page.evaluate(top => scrollTo({ top, behavior: 'instant' }), y);
                        await page.waitForTimeout(25);
                        const current = await geometry(page);
                        assert.equal(current.height, initial.height, 'Scrolling never changes document height');
                        assert(Math.abs(current.contact.documentTop - initial.contact.documentTop) < 1, 'Contact stays at the same document position');
                        assert(Math.abs(current.footer.documentTop - initial.footer.documentTop) < 1, 'Footer stays at the same document position');
                        assert(Math.abs(current.header.top) < 1, 'Sticky bar remains visible, including bottom micro-scrolls');
                        assert(Math.abs(current.y - Math.max(0, Math.min(y, maximum))) < 1, 'No unexpected scroll correction');
                    }
                    if (width <= 768) {
                        await page.evaluate(top => scrollTo({ top, behavior: 'instant' }), maximum);
                        const before = await geometry(page);
                        await page.getByRole('button', { name: 'Open navigation', exact: true }).focus();
                        await page.keyboard.press('Enter');
                        const opened = await geometry(page);
                        assert.equal(opened.height, before.height, 'Menu scroll-lock keeps page height');
                        assert.equal(opened.contact.documentTop, before.contact.documentTop, 'Menu scroll-lock keeps content position');
                        assert.equal(opened.y, before.y, 'Opening drawer does not scroll the page');
                        await page.keyboard.press('Escape');
                        assert.equal((await geometry(page)).y, before.y, 'Escape and focus restoration do not scroll the page');
                        assert.equal(await page.getByRole('button', { name: 'Open navigation', exact: true }).evaluate(node => node === document.activeElement), true);
                    }
                    for (const id of ['projects', 'experience', 'contact']) {
                        if (width <= 768) {
                            await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
                            await page.locator(`.portfolio-mobile-nav-links a[href="#${id}"]`).focus();
                        } else await page.locator(`.portfolio-nav-links a[href="#${id}"]`).focus();
                        await page.keyboard.press('Enter');
                        await page.waitForTimeout(60);
                        const bounds = await page.locator(`#${id} h2, .portfolio-header`).evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect().toJSON()));
                        assert(bounds[1].top >= bounds[0].bottom, `${width}/${height}/${variant}: complete ${id} heading below header`);
                    }
                    if (variant === 'public-portrait') {
                        await page.locator('#portfolio-contact-email').focus();
                        assert(await page.locator('#portfolio-contact-email').evaluate(node => node === document.activeElement && node.getBoundingClientRect().top >= document.querySelector('.portfolio-header').getBoundingClientRect().bottom), 'Keyboard focus clears sticky bar');
                    }
                    await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
                    assert.equal(await page.locator('[style]').count(), 0, 'Measured offset uses no inline style');
                    if ([390, 1280].includes(width) && height === 900) await page.screenshot({ path: path.join(output, `portrait-after-${variant}-${width}-${colorScheme}.png`), fullPage: true });
                    await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
                    await page.waitForFunction(() => parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--pf-header-offset')) === Math.ceil(document.querySelector('.portfolio-header').getBoundingClientRect().height) + 16);
                    const enlarged = await geometry(page);
                    assert.equal(enlarged.offset, Math.ceil(enlarged.header.height) + 16, 'ResizeObserver follows large-text header wrapping');
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Large-text portrait and header never overflow');
                    assert.deepEqual(errors, []);
                    await page.close();
                }
            }
        }
        console.log(`PASS scroll/portrait: public + Arabic-owner preview, light/dark, ${width}px, 360/900px heights, bottom reversals, keyboard anchors, 200% text`);
    }

    const delayed = await browser.newPage({ viewport: { width: 390, height: 900 }, reducedMotion: 'reduce' });
    await routes(delayed, 'public-portrait');
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    await delayed.route('**/media/profile', async route => { await pending; await route.fulfill({ contentType: 'image/svg+xml', body: portraitSvg }); });
    await delayed.goto('http://public.test/p/synthetic-owner', { waitUntil: 'domcontentloaded' });
    await delayed.evaluate(() => document.fonts.ready);
    const beforeLoad = await geometry(delayed);
    release();
    await delayed.waitForFunction(() => document.querySelector('.portfolio-hero-profile-visual img').naturalHeight > 0);
    const afterLoad = await geometry(delayed);
    assert.deepEqual(afterLoad, beforeLoad, 'Delayed portrait loading changes neither frame, content, page height nor scroll');
    await delayed.close();
    console.log('PASS portrait: delayed image load reserves frame and creates no geometry or scroll shift');

    for (const width of [390, 768, 1280, 1920]) {
        for (const colorScheme of ['light', 'dark']) {
            for (const variant of ['public-portrait', 'preview-private-portrait']) {
                const page = await browser.newPage({ viewport: { width, height: 360 }, javaScriptEnabled: false, colorScheme, reducedMotion: 'reduce' });
                page.setDefaultTimeout(15000);
                await routes(page, variant);
                await page.goto(variant.startsWith('preview') ? 'http://public.test/owner_preview.php' : 'http://public.test/p/synthetic-owner');
                await page.waitForTimeout(200);
                // Synchronous injection: a style load callback does not run with page scripts disabled.
                await page.evaluate(() => {
                    const style = document.createElement('style');
                    style.textContent = 'html { font-size: 200% !important; }';
                    document.head.append(style);
                });
                await page.locator('.portfolio-nav-links a[href="#contact"]').click();
                assert(await page.locator('#contact h2').evaluate(node => node.getBoundingClientRect().top >= 0), 'No-JS large-text native heading is unobscured');
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No-JS large text fits');
                if (width === 390 && variant === 'preview-private-portrait') await page.screenshot({ path: path.join(output, `portrait-after-no-js-390-${colorScheme}.png`), fullPage: true });
                await page.close();
            }
        }
    }
    console.log('PASS scroll/portrait: public/preview, light/dark, native no-JS navigation, portrait and 200% text at all four widths');

    for (const width of [390, 1280]) {
        const context = await browser.newContext({ viewport: { width, height: 720 }, colorScheme: 'light', reducedMotion: 'no-preference', recordVideo: { dir: output, size: { width, height: 720 } } });
        const page = await context.newPage();
        await routes(page, 'public-portrait');
        await page.goto('http://public.test/p/synthetic-owner');
        await page.evaluate(() => document.fonts.ready);
        await page.evaluate(() => {
            window.scrollSamples = [];
            const sample = () => {
                window.scrollSamples.push({ y: scrollY, height: document.documentElement.scrollHeight,
                    header: document.querySelector('.portfolio-header').getBoundingClientRect().top,
                    contact: document.querySelector('#contact').getBoundingClientRect().top + scrollY });
                if (window.scrollSamples) window.scrollFrame = requestAnimationFrame(sample);
            };
            sample();
        });
        await page.waitForTimeout(600);
        if (width === 390) {
            await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
            await page.locator('.portfolio-mobile-nav-links a[href="#contact"]').focus();
        } else await page.locator('.portfolio-nav-links a[href="#contact"]').focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(1100);
        await page.mouse.wheel(0, 1200);
        await page.waitForTimeout(400);
        for (const delta of [-2, 2, -1, 1, 300, -300]) {
            await page.mouse.wheel(0, delta);
            await page.waitForTimeout(120);
        }
        if (width === 390) {
            await page.getByRole('button', { name: 'Open navigation', exact: true }).click();
            await page.waitForTimeout(350);
            await page.keyboard.press('Escape');
        }
        await page.mouse.wheel(0, -1200);
        await page.waitForTimeout(500);
        await page.locator('.portfolio-brand').focus();
        await page.keyboard.press('Enter');
        await page.waitForTimeout(1100);
        const samples = await page.evaluate(() => { cancelAnimationFrame(window.scrollFrame); return window.scrollSamples; });
        assert(new Set(samples.map(sample => Math.round(sample.y))).size > 6, 'Normal-motion navigation animates scrolling over multiple frames');
        assert(samples.every(sample => sample.height === samples[0].height && Math.abs(sample.contact - samples[0].contact) < 1 && Math.abs(sample.header) < 1), 'Every smooth-scroll and native wheel frame preserves page height, content positions and sticky header');
        const video = page.video();
        await context.close();
        await video.saveAs(path.join(output, `stable-scroll-${width}.webm`));
        console.log(`PASS scroll/portrait: ${width}px normal-motion keyboard/wheel/scroll-lock recording; every frame stable`);
    }
}

module.exports = { runScrollPortraitChecks, portraitSvg };
