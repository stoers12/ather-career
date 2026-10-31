// Synthetic renderer fixtures; real mutations are also checked by the disposable publication browser suite.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { runScrollPortraitChecks, portraitSvg } = require('./portfolio-scroll-portrait-checks.cjs');
const root = path.resolve(__dirname, '../..');
const output = process.argv[2];
if (!output) throw new Error('Screenshot directory required');
fs.mkdirSync(output, { recursive: true });
const html = (variant) => execFileSync('php', [path.join(__dirname, 'public-portfolio-visual.php'), variant], { encoding: 'utf8' });
const picture = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675"><rect width="1200" height="675" fill="#e8f0e6"/><rect x="120" y="90" width="960" height="495" rx="8" fill="#235d45"/><text x="190" y="300" fill="#fff" font-size="48" font-family="sans-serif">Synthetic project screenshot</text><path d="M190 470H940M190 430H700" stroke="#b8d4aa" stroke-width="16"/></svg>';
async function routes(page, variant) {
    await page.route('http://public.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname.startsWith('/assets/')) {
            const file = path.join(root, 'public', url.pathname);
            if (fs.existsSync(file)) return route.fulfill({ body: fs.readFileSync(file), contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.js') ? 'application/javascript' : 'font/woff2' });
        }
        if (url.pathname.endsWith('/media/profile') || url.searchParams.get('type') === 'profile') return route.fulfill({ contentType: 'image/svg+xml', body: portraitSvg });
        if (url.pathname.includes('/media/project/') || url.pathname === '/owner_media.php') return route.fulfill({ contentType: 'image/svg+xml', body: picture });
        const selected = url.pathname.includes('/projects/') || url.searchParams.has('project') ? (variant.startsWith('preview') ? 'preview-case' : 'case') : variant;
        return route.fulfill({ contentType: 'text/html', body: html(selected) });
    });
}
async function settled(page) {
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(100);
}
function luminance(rgb) {
    const c = rgb.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { v /= 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; });
    return c[0] * .2126 + c[1] * .7152 + c[2] * .0722;
}
async function contrast(page) {
    const text = await page.evaluate(() => [...document.querySelectorAll('body.portfolio-page *')].filter(n => {
        const r = n.getBoundingClientRect();
        return r.width && r.height && !n.closest('[inert], button:disabled') && [...n.childNodes].some(t => t.nodeType === Node.TEXT_NODE && t.textContent.trim()) && getComputedStyle(n).visibility !== 'hidden';
    }).map(n => {
        let p = n, bg;
        while (p) { bg = getComputedStyle(p).backgroundColor; if (bg !== 'rgba(0, 0, 0, 0)') break; p = p.parentElement; }
        const s = getComputedStyle(n);
        return { text: n.textContent.trim().slice(0, 40), color: s.color, bg, large: parseFloat(s.fontSize) >= 24 || (parseFloat(s.fontSize) >= 18.66 && Number(s.fontWeight) >= 700) };
    }));
    for (const t of text) {
        const a = luminance(t.color), b = luminance(t.bg);
        const ratio = (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
        assert.ok(ratio >= (t.large ? 3 : 4.5), `AA text contrast ${ratio.toFixed(2)}: ${t.text}`);
    }
}

(async () => {
    const browser = await chromium.launch({ headless: true, ignoreDefaultArgs: ['--hide-scrollbars'] });
    try {
        await runScrollPortraitChecks(browser, output, routes);
        for (const width of [320, 390, 768, 1280, 1440, 1920, 2560]) {
            for (const theme of ['light', 'dark']) {
                for (const variant of ['public', 'preview-private', 'case', 'preview-case', 'case-empty', 'draft', 'long']) {
                    const page = await browser.newPage({ viewport: { width, height: 1000 }, colorScheme: theme, reducedMotion: 'reduce' });
                    const errors = []; page.on('pageerror', e => errors.push(e.message));
                    await routes(page, variant); await page.goto('http://public.test/'); await settled(page);
                    assert.equal(await page.locator('html').getAttribute('lang'), 'en', `${variant}: English with Arabic locale`);
                    assert.equal(await page.locator('.portfolio-preview-bar').count(), variant.startsWith('preview') || variant === 'draft' ? 1 : 0, `${variant}: preview boundary`);
                    assert.equal(await page.locator('a[href^="javascript:"]').count(), 0, 'Unsafe links omitted');
                    assert.equal(await page.getByText('Insights', { exact: true }).count(), 0, 'No placeholder navigation');
                    assert.equal(await page.locator('script:not([src]), [style]').count(), 0, 'CSP: external scripts and styles only');
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}/${theme}/${variant}: overflow`);
                    if (width === 1440 && ['public', 'preview-private', 'case'].includes(variant)) await contrast(page);
                    if (variant === 'public') {
                        assert.equal(await page.locator('.portfolio-project-visual img').count(), 1, 'Only real image');
                        assert.equal(await page.locator('.portfolio-neutral-cover').count(), 3, 'Neutral CSS covers');
                        assert.equal(await page.locator('.portfolio-live-link').count(), 1, 'Only safe live link');
                        assert.equal(await page.locator('.portfolio-case-link').first().getAttribute('href'), '/p/synthetic-owner/projects/1');
                        assert.equal(await page.locator('.portfolio-experience-item:visible').count(), 5, 'Five-entry Experience page');
                        const expected = width <= 620 ? 1 : width <= 1100 ? 2 : 4;
                        assert.equal(await page.locator('.portfolio-project-card:visible').count(), expected);
                        if (width === 1440) {
                            const grid = await page.locator('.portfolio-project-grid').boundingBox();
                            assert(grid.width > 1200, 'Desktop cards use available width');
                            await page.locator('.portfolio-case-link').first().click();
                            assert.equal(new URL(page.url()).pathname, '/p/synthetic-owner/projects/1');
                            assert.equal(await page.locator('h1').textContent(), 'Operations Dashboard');
                            await page.goBack(); await settled(page);
                        }
                    }
                    if (variant === 'preview-private') {
                        assert.equal(await page.locator('.portfolio-preview-bar').getAttribute('lang'), 'ar');
                        assert((await page.locator('.portfolio-preview-bar').textContent()).includes('خاص'));
                        assert.equal(await page.locator('.portfolio-case-link').first().getAttribute('href'), '/owner_preview.php?project=1');
                        assert.equal(await page.locator('link[rel="canonical"]').count(), 0);
                        if (width === 1440) {
                            await page.locator('.portfolio-case-link').first().click();
                            assert.equal(new URL(page.url()).search, '?project=1');
                            assert.equal(await page.locator('meta[name="robots"]').getAttribute('content'), 'noindex,nofollow');
                            await page.goBack(); await settled(page);
                        }
                    }
                    if (variant === 'case-empty') assert.equal(await page.getByText('No public link has been added for this project.', { exact: true }).count(), 1);
                    if (variant === 'draft') assert.equal(await page.locator('.portfolio-draft-note').count(), 1);
                    if (width === 1440 || width === 390) await page.screenshot({ path: path.join(output, `${variant}-${width}-${theme}.png`), fullPage: true });
                    await page.addStyleTag({ content: 'html { font-size: 200% !important; }' });
                    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}/${theme}/${variant}: large text overflow`);
                    assert.deepEqual(errors, []);
                    await page.close();
                }
                console.log(`PASS FE08 English public/preview/case privacy, safe links, real images, neutral covers, CSP and 200% text reflow ${width}/${theme}`);
            }
        }
        const touch = await browser.newContext({viewport:{width:390,height:1000},hasTouch:true,reducedMotion:'reduce'});
        const keyboard = await touch.newPage();
        await routes(keyboard, 'public'); await keyboard.goto('http://public.test/');
        const toggle = keyboard.getByRole('button',{name:'Open navigation',exact:true});
        await toggle.focus(); await keyboard.keyboard.press('Enter');
        assert.equal(await keyboard.locator('.portfolio-mobile-nav-layer').getAttribute('aria-hidden'), 'false');
        await keyboard.keyboard.press('Escape');
        assert.equal(await toggle.evaluate(n=>n===document.activeElement),true);
        assert.equal(await toggle.getAttribute('aria-expanded'),'false');
        const themeButton=keyboard.locator('button[data-portfolio-theme]');
        const themeBefore=await themeButton.getAttribute('aria-pressed');
        await themeButton.click();
        assert.notEqual(await themeButton.getAttribute('aria-pressed'),themeBefore);
        const short=await keyboard.locator('a:visible,button:visible').evaluateAll(nodes=>nodes.filter(n=>n.getBoundingClientRect().height<44 || n.getBoundingClientRect().width<44).map(n=>n.outerHTML.slice(0,100)));
        assert.deepEqual(short,[],'Coarse-pointer targets meet 44px');
        await touch.close();
        console.log('PASS FE08 AA text contrast in both themes, keyboard drawer and Escape focus return, theme control, coarse-pointer 44px targets');
        for (const variant of ['public', 'preview-private', 'case', 'draft', 'error', 'success']) {
            const page = await browser.newPage({ viewport: { width: 390, height: 1000 }, javaScriptEnabled: false, colorScheme: 'light' });
            await routes(page, variant); await page.goto('http://public.test/'); await settled(page);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `no-JS ${variant}: overflow`);
            if (variant === 'public') {
                assert.equal(await page.locator('.portfolio-project-card:visible').count(), 4);
                assert.equal(await page.locator('.portfolio-experience-item:visible').count(), 6);
                assert.equal(await page.locator('.portfolio-nav-links a[href="#contact"]').isVisible(), true);
                assert.equal(await page.locator('.portfolio-skill-label').count(), 2);
                assert.equal(await page.locator('.portfolio-skill-control').count(), 0);
                assert.equal(await page.locator('button[data-portfolio-theme]').isHidden(), true);
            }
            await page.screenshot({ path: path.join(output, `${variant}-390-no-js.png`), fullPage: true }); await page.close();
        }
        for (const variant of ['error', 'success']) {
            const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
            await routes(page, variant); await page.goto('http://public.test/');
            if (variant === 'error') {
                await page.waitForFunction(() => document.activeElement.hasAttribute('data-portfolio-error-summary'));
                assert.equal(await page.locator('[name="name"]').inputValue(), '<Visitor>');
                assert.equal(await page.locator('[name="email"]').inputValue(), 'invalid');
                assert.equal(await page.locator('[name="email"]').getAttribute('aria-invalid'), 'true');
            } else assert.equal(await page.locator('.portfolio-form-status[role="status"]').textContent(), 'Message submitted successfully.');
            await page.locator('#contact').screenshot({ path: path.join(output, `${variant}-1440.png`) }); await page.close();
        }
        console.log('PASS FE08 native no-JS navigation/content, retained escaped error values, focused announced errors and server success feedback');
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
