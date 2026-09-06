// Uses an already installed Playwright; no application/browser dependency added.
// PLAYWRIGHT_MODULE=<module path> node this-file <output directory>
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../../..');
const output = process.argv[2];
if (!output) throw new Error('A screenshot output directory is required.');
fs.mkdirSync(output, { recursive: true });

const variants = {
    full: { navigation: ['About', 'Projects', 'Experience', 'Skills', 'Contact'], social: 5, owner: 'Jordan Lee' },
    partial: { navigation: ['Skills', 'Contact'], social: 1, owner: 'Jordan Lee' },
    minimal: { navigation: ['Contact'], social: 0, owner: 'Jordan Lee' },
    'long-name': { navigation: ['Skills', 'Contact'], social: 2, owner: 'Jordan Alexandra Lee-Montgomery, International Operations and Service Reliability Collaboration Specialist' },
    'empty-name': { navigation: ['Contact'], social: 0, owner: '' },
    preview: { navigation: ['About', 'Projects', 'Experience', 'Skills', 'Contact'], social: 5, owner: 'Jordan Lee' },
};

const installRoutes = async (page, html) => {
    await page.route('http://work.test/**', route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname === '/portfolio.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'portfolio.css')) });
        if (url.pathname === '/portfolio.js') return route.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(path.join(root, 'portfolio.js')) });
        if (url.pathname === '/assets/images/ather-navbar-logo.png') return route.fulfill({ contentType: 'image/png', body: fs.readFileSync(path.join(root, 'assets/images/ather-navbar-logo.png')) });
        return route.fulfill({ status: 204, body: '' });
    });
};

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const width of [1440, 1100, 768, 360]) {
            for (const [variant, expected] of Object.entries(variants)) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error));
                const html = execFileSync('php', [path.join(__dirname, 'footer-visual.php'), variant], { encoding: 'utf8' });
                await installRoutes(page, html);
                await page.goto('http://work.test/');

                const footer = page.locator('.portfolio-footer');
                const primary = page.locator('.portfolio-footer-primary');
                const secondary = page.locator('.portfolio-footer-secondary');
                const navigation = page.locator('.portfolio-footer-navigation a');
                const social = page.locator('.portfolio-footer-social a');
                const copyright = page.locator('.portfolio-footer-copyright');
                const supportingCopy = page.locator('.portfolio-footer-brand > p');
                assert.equal(await footer.count(), 1, `${width}/${variant}: single footer`);
                assert.equal(await primary.count(), 1, `${width}/${variant}: primary footer`);
                assert.equal(await secondary.count(), 1, `${width}/${variant}: secondary footer`);
                assert.equal(await page.locator('.portfolio-footer-logo').count(), 1, `${width}/${variant}: one ATHER logo`);
                assert.equal(await page.locator('.portfolio-footer-brand').getByText('A professional portfolio built with ATHER.', { exact: true }).count(), 1, `${width}/${variant}: supporting sentence`);
                if (width >= 1100) {
                    const supportingCopyMetrics = await supportingCopy.evaluate(node => {
                        const style = getComputedStyle(node);
                        return { height: node.getBoundingClientRect().height, lineHeight: parseFloat(style.lineHeight) };
                    });
                    assert(supportingCopyMetrics.height <= supportingCopyMetrics.lineHeight + 1, `${width}/${variant}: desktop supporting sentence must not orphan ATHER.`);
                }
                assert.deepEqual(await navigation.allTextContents(), expected.navigation, `${width}/${variant}: conditional footer navigation`);
                assert.equal(await social.count(), expected.social, `${width}/${variant}: conditional social controls`);
                assert.equal(await social.locator('svg[aria-hidden="true"]').count(), expected.social, `${width}/${variant}: decorative social icons`);
                assert.equal(await social.evaluateAll(links => links.every(link => link.target === '_blank' && link.rel === 'noopener noreferrer' && link.getAttribute('aria-label'))), true, `${width}/${variant}: secure accessible social links`);
                assert.equal(await footer.locator('input, button, [aria-disabled="true"]').count(), 0, `${width}/${variant}: no inactive footer controls`);
                assert.equal(await footer.getByText(/Quick Links|Resources|Stay Updated|Coming soon|Subscribe|Send a message|Planned resources/i).count(), 0, `${width}/${variant}: no obsolete footer content`);
                assert.equal(await footer.locator('a[href^="javascript:"]').count(), 0, `${width}/${variant}: unsafe social links omitted`);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}/${variant}: page overflow`);
                assert.equal(await footer.evaluate(node => [...node.querySelectorAll('*')].every(child => child.scrollWidth <= child.clientWidth + 1)), true, `${width}/${variant}: footer content overflow`);

                const copyrightText = await copyright.textContent();
                assert(copyrightText.startsWith(`© ${new Date().getFullYear()}`), `${width}/${variant}: dynamic copyright year`);
                if (expected.owner) {
                    assert(copyrightText.includes(expected.owner), `${width}/${variant}: dynamic owner copyright`);
                } else {
                    assert.equal(copyrightText.includes('Your Portfolio'), false, `${width}/${variant}: empty owner fallback absent`);
                }
                const top = page.locator('.portfolio-footer-back-to-top');
                assert.equal(await top.getAttribute('href'), '#top', `${width}/${variant}: native back-to-top target`);
                await navigation.first().focus();
                await page.keyboard.press('Tab');
                assert.equal(await page.evaluate(() => document.activeElement.matches(':focus-visible')), true, `${width}/${variant}: visible keyboard focus`);

                const primaryBox = await primary.evaluate(node => node.getBoundingClientRect());
                const brandBox = await page.locator('.portfolio-footer-brand').evaluate(node => node.getBoundingClientRect());
                const navBox = await page.locator('.portfolio-footer-navigation').evaluate(node => node.getBoundingClientRect());
                if (width >= 1100) {
                    assert(navBox.x > brandBox.x, `${width}/${variant}: desktop navigation follows brand`);
                    if (expected.social > 0) {
                        const socialBox = await page.locator('.portfolio-footer-social').evaluate(node => node.getBoundingClientRect());
                        assert(socialBox.x > navBox.x, `${width}/${variant}: desktop social follows navigation`);
                    }
                } else {
                    assert(navBox.y > brandBox.y, `${width}/${variant}: stacked navigation follows brand`);
                    if (expected.social > 0) {
                        const socialBox = await page.locator('.portfolio-footer-social').evaluate(node => node.getBoundingClientRect());
                        assert(socialBox.y > navBox.y, `${width}/${variant}: stacked social follows navigation`);
                    }
                    const secondaryBox = await secondary.evaluate(node => node.getBoundingClientRect());
                    assert(secondaryBox.y > primaryBox.y, `${width}/${variant}: secondary follows primary`);
                }

                const footerBox = await footer.evaluate(node => node.getBoundingClientRect());
                await page.setViewportSize({ width, height: Math.max(1000, Math.ceil(footerBox.bottom) + 12) });
                await footer.screenshot({ path: path.join(output, `${width}-${variant}-footer.png`) });
                await top.click();
                assert.equal(new URL(page.url()).hash, '#top', `${width}/${variant}: functional back-to-top anchor`);
                assert.deepEqual(errors, [], `${width}/${variant}: JavaScript errors`);
                console.log(`PASS footer ${width}/${variant}`);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
