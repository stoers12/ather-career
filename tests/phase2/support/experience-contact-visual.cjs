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
    'three-public': { experiences: 3, social: 3, preview: false, errors: false },
    'no-descriptions': { experiences: 3, social: 3, preview: false, errors: false },
    'long-content': { experiences: 3, social: 3, preview: false, errors: false },
    'one-current': { experiences: 1, social: 3, preview: false, errors: false },
    'no-experience': { experiences: 0, social: 3, preview: false, errors: false },
    'validation-errors': { experiences: 3, social: 3, preview: false, errors: true },
    preview: { experiences: 3, social: 3, preview: true, errors: false },
    'social-minimal': { experiences: 3, social: 1, preview: false, errors: false },
};

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const width of [1440, 1100, 768, 360]) {
            for (const [variant, expected] of Object.entries(variants)) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error));
                const html = execFileSync('php', [path.join(__dirname, 'experience-contact-visual.php'), variant], { encoding: 'utf8' });
                await page.route('http://work.test/**', route => {
                    const url = new URL(route.request().url());
                    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
                    if (url.pathname === '/portfolio.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'portfolio.css')) });
                    if (url.pathname === '/portfolio.js') return route.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(path.join(root, 'portfolio.js')) });
                    if (url.pathname === '/assets/images/ather-navbar-logo.png') return route.fulfill({ contentType: 'image/png', body: fs.readFileSync(path.join(root, 'assets/images/ather-navbar-logo.png')) });
                    return route.fulfill({ status: 204, body: '' });
                });
                await page.goto('http://work.test/');

                const experience = page.locator('#experience');
                const contact = page.locator('#contact');
                const items = page.locator('.portfolio-experience-item');
                const social = page.locator('.portfolio-contact-social a');
                const form = page.locator('#contact form');
                assert.equal(await experience.count(), expected.experiences > 0 ? 1 : 0, `${width}/${variant}: Experience visibility`);
                assert.equal(await contact.count(), 1, `${width}/${variant}: Contact visibility`);
                assert.equal(await items.count(), expected.experiences, `${width}/${variant}: Experience item count`);
                assert.equal(await social.count(), expected.social, `${width}/${variant}: validated social control count`);
                assert.equal(await social.locator('svg[aria-hidden="true"]').count(), expected.social, `${width}/${variant}: social icon count`);
                assert.equal(await social.evaluateAll(links => links.some(link => link.textContent.includes('↗'))), false, `${width}/${variant}: social arrows`);
                assert.equal(await page.locator('a[href^="javascript:"]').count(), 0, `${width}/${variant}: unsafe social link`);
                assert.equal(await form.locator('h3').count(), 0, `${width}/${variant}: form heading must remain outside form fields`);
                assert.equal(await page.locator('#portfolio-contact-form-title').textContent(), 'Send a message', `${width}/${variant}: form heading`);
                assert.equal(await page.locator('.portfolio-contact-card button[type="submit"] svg[aria-hidden="true"]').count(), 1, `${width}/${variant}: email submit icon`);
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `${width}/${variant}: page overflow`);

                if (expected.experiences > 0) {
                    assert.equal(await page.locator('.portfolio-experience-item:last-child').evaluate(node => getComputedStyle(node, '::after').content), 'none', `${width}/${variant}: final connector tail`);
                    if (expected.experiences === 1) {
                        assert.equal(await items.first().evaluate(node => getComputedStyle(node, '::after').content), 'none', `${width}/${variant}: single Experience connector`);
                    } else {
                        assert.notEqual(await items.first().evaluate(node => getComputedStyle(node, '::after').content), 'none', `${width}/${variant}: connecting Experience segment`);
                    }
                } else {
                    assert.equal(await page.locator('.portfolio-closing-layout--contact-only').count(), 1, `${width}/${variant}: full-width Contact layout`);
                }

                if (expected.preview) {
                    assert.equal(await page.locator('.portfolio-preview-note--contact[role="status"]').count(), 1, `${width}/${variant}: preview status row`);
                    assert.equal(await form.locator(':disabled').count() > 0, true, `${width}/${variant}: disabled preview form`);
                } else {
                    assert.equal(await form.locator(':disabled').count(), 0, `${width}/${variant}: active public form`);
                }
                if (expected.errors) {
                    assert.equal(await form.locator('[aria-invalid="true"]').count(), 3, `${width}/${variant}: field errors`);
                    assert.equal(await page.locator('.portfolio-contact-form-error[role="alert"]').count(), 1, `${width}/${variant}: form error`);
                }
                if (variant === 'no-descriptions') {
                    assert.equal(await page.locator('.portfolio-experience-description').count(), 0, `${width}/${variant}: empty descriptions`);
                }

                if (width === 1440 && expected.experiences > 0) {
                    const experienceBox = await experience.evaluate(node => node.getBoundingClientRect());
                    const contactBox = await contact.evaluate(node => node.getBoundingClientRect());
                    assert(Math.abs(experienceBox.width - contactBox.width) < 1, `${width}/${variant}: equal columns`);
                    assert(Math.abs(experienceBox.top - contactBox.top) < 1, `${width}/${variant}: aligned panels`);
                }
                if (width <= 1100 && expected.experiences > 0) {
                    const experienceBox = await experience.evaluate(node => node.getBoundingClientRect());
                    const contactBox = await contact.evaluate(node => node.getBoundingClientRect());
                    assert(contactBox.top > experienceBox.top && Math.abs(contactBox.left - experienceBox.left) < 1, `${width}/${variant}: stacked Experience then Contact`);
                }
                if (width === 1100 || width === 768 || width === 360) {
                    const fields = page.locator('.portfolio-contact-field');
                    const nameBox = await fields.nth(0).evaluate(node => node.getBoundingClientRect());
                    const emailBox = await fields.nth(1).evaluate(node => node.getBoundingClientRect());
                    assert(emailBox.top > nameBox.top, `${width}/${variant}: safe stacked Name and Email fields`);
                }

                const closing = page.locator('.portfolio-closing-region');
                const closingBox = await closing.evaluate(node => node.getBoundingClientRect());
                await page.setViewportSize({ width, height: Math.max(1000, Math.ceil(closingBox.bottom) + 12) });
                await closing.screenshot({ path: path.join(output, `${width}-${variant}.png`) });

                if (width === 1440 && variant === 'three-public') {
                    await page.locator('.portfolio-header-cta').click();
                    await page.waitForTimeout(350);
                    assert.equal(await page.evaluate(() => location.hash), '#contact', 'Contact CTA hash target');
                    assert.equal(await page.locator('[data-portfolio-section="contact"]').first().evaluate(node => node.classList.contains('is-current')), true, 'Contact CTA scrollspy target');
                    const contactTop = await contact.evaluate(node => node.getBoundingClientRect().top);
                    assert(contactTop >= 64, 'Contact anchor must clear sticky header');
                    await page.locator('.portfolio-nav-links a[href="#experience"]').click();
                    await page.waitForTimeout(350);
                    assert.equal(await page.locator('[data-portfolio-section="experience"]').first().evaluate(node => node.classList.contains('is-current')), true, 'Experience anchor scrollspy target');
                }

                assert.deepEqual(errors, [], `${width}/${variant}: JavaScript errors`);
                console.log(`PASS experience/contact ${width}/${variant}`);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
