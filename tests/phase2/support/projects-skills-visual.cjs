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
const portfolioScript = fs.readFileSync(path.join(root, 'portfolio.js'), 'utf8');

const expectations = {
    'fallback-many': { projects: 3, images: 0, skills: 12, links: 1 },
    'image-many': { projects: 3, images: 3, skills: 12, links: 1 },
    'mixed-many': { projects: 3, images: 2, skills: 12, links: 1 },
    'one-few': { projects: 1, images: 0, skills: 2, links: 1 },
    'two-projects': { projects: 2, images: 0, skills: 12, links: 1 },
    'projects-only': { projects: 3, images: 0, skills: 0, links: 1 },
    'skills-only': { projects: 0, images: 0, skills: 12, links: 0 },
    long: { projects: 3, images: 1, skills: 13, links: 1 },
    'invalid-link': { projects: 3, images: 0, skills: 12, links: 0 },
};

const projectSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900"><rect width="1600" height="900" fill="#294765"/><path d="M0 690 390 430l270 170 290-320 650 410v210H0Z" fill="#527b9f"/><circle cx="1210" cy="220" r="94" fill="#a5cae8"/></svg>';

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const multiplePanelsPage = await browser.newPage();
        const multiplePanelsErrors = [];
        multiplePanelsPage.on('pageerror', error => multiplePanelsErrors.push(error));
        await multiplePanelsPage.setContent(`
            <ul class="portfolio-skills-list"><li><button class="portfolio-skill-control" type="button" aria-pressed="false">First panel</button></li></ul>
            <ul class="portfolio-skills-list"><li><button class="portfolio-skill-control" type="button" aria-pressed="false">Second panel</button></li></ul>
            <script>${portfolioScript}</script>
        `);
        const firstPanelSkill = multiplePanelsPage.locator('.portfolio-skills-list').nth(0).locator('.portfolio-skill-control');
        const secondPanelSkill = multiplePanelsPage.locator('.portfolio-skills-list').nth(1).locator('.portfolio-skill-control');
        await firstPanelSkill.click();
        assert.equal(await firstPanelSkill.getAttribute('aria-pressed'), 'true', 'multiple panels: first panel selection');
        assert.equal(await secondPanelSkill.getAttribute('aria-pressed'), 'false', 'multiple panels: selection must stay scoped');
        await secondPanelSkill.click();
        assert.equal(await firstPanelSkill.getAttribute('aria-pressed'), 'true', 'multiple panels: first panel selection remains independent');
        assert.equal(await secondPanelSkill.getAttribute('aria-pressed'), 'true', 'multiple panels: second panel selection');
        assert.deepEqual(multiplePanelsErrors, [], 'multiple panels: JavaScript errors');
        await multiplePanelsPage.close();

        for (const width of [1440, 768, 360]) {
            for (const [variant, expected] of Object.entries(expectations)) {
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                const pageErrors = [];
                page.on('pageerror', error => pageErrors.push(error));
                const html = execFileSync('php', [path.join(__dirname, 'projects-skills-visual.php'), variant], { encoding: 'utf8' });
                await page.route('http://work.test/**', route => {
                    const url = new URL(route.request().url());
                    if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
                    if (url.pathname === '/portfolio.css') return route.fulfill({ contentType: 'text/css', body: fs.readFileSync(path.join(root, 'portfolio.css')) });
                    if (url.pathname === '/portfolio.js') return route.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(path.join(root, 'portfolio.js')) });
                    if (url.pathname === '/assets/images/ather-navbar-logo.png') return route.fulfill({ contentType: 'image/png', body: fs.readFileSync(path.join(root, 'assets/images/ather-navbar-logo.png')) });
                    if (url.pathname.startsWith('/synthetic-project-')) return route.fulfill({ contentType: 'image/svg+xml', body: projectSvg });
                    return route.fulfill({ status: 204, body: '' });
                });
                await page.goto('http://work.test/');

                const cards = page.locator('.portfolio-project-card');
                const skills = page.locator('.portfolio-skill-control');
                const projectGrid = page.locator('.portfolio-project-grid');
                const skillsPanel = page.locator('.portfolio-skills-panel');
                assert.equal(await cards.count(), expected.projects, `${width}/${variant}: project count`);
                assert.equal(await page.locator('.portfolio-project-card--image').count(), expected.images, `${width}/${variant}: real image count`);
                assert.equal(await page.locator('.portfolio-project-card--fallback').count(), expected.projects - expected.images, `${width}/${variant}: fallback count`);
                assert.equal(await page.locator('.portfolio-project-card--fallback .portfolio-project-visual').count(), 0, `${width}/${variant}: fallback visual band`);
                assert.equal(await skills.count(), expected.skills, `${width}/${variant}: skill count`);
                assert.equal(await page.locator('.portfolio-skill-control[type="button"][aria-pressed="false"]').count(), expected.skills, `${width}/${variant}: initial skill button semantics`);
                assert.equal(await page.locator('.portfolio-project-link').count(), expected.links, `${width}/${variant}: validated GitHub actions`);
                assert.equal(await page.locator('a[href^="javascript:"]').count(), 0, `${width}/${variant}: unsafe link`);
                assert.equal(await page.locator('.portfolio-project-link svg[aria-hidden="true"]').count(), expected.links, `${width}/${variant}: GitHub icon`);
                assert.equal(await page.locator('.portfolio-project-link').evaluateAll(links => links.some(link => link.textContent.includes('↗'))), false, `${width}/${variant}: obsolete project-link arrow`);
                assert.equal(await page.locator('.portfolio-project-technologies button').count(), 0, `${width}/${variant}: interactive project technology tags`);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}/${variant}: page overflow`);

                if (expected.projects > 0) {
                    assert.equal(await projectGrid.count(), 1, `${width}/${variant}: project grid visibility`);
                    const boxes = await cards.evaluateAll(nodes => nodes.map(node => node.getBoundingClientRect()));
                    if (width === 1440 && expected.projects === 3) {
                        const widths = boxes.map(box => box.width);
                        assert(Math.max(...widths) - Math.min(...widths) < 1, `${width}/${variant}: unequal desktop project columns`);
                        assert(Math.max(...boxes.map(box => box.height)) - Math.min(...boxes.map(box => box.height)) < 1, `${width}/${variant}: unequal desktop card heights`);
                    }
                    if (width === 768 && expected.projects === 3) {
                        assert(new Set(boxes.map(box => Math.round(box.x))).size === 2, `${width}/${variant}: tablet does not use two project columns`);
                        assert(boxes[2].y > boxes[0].y && Math.abs(boxes[2].width - boxes[0].width) < 1, `${width}/${variant}: tablet third card stretched or did not flow naturally`);
                    }
                    if (width === 360 && expected.projects > 1) {
                        assert(new Set(boxes.map(box => Math.round(box.x))).size === 1, `${width}/${variant}: mobile does not use one project column`);
                    }
                    if (expected.links > 0) {
                        const link = page.locator('.portfolio-project-link').first();
                        const linkBox = await link.evaluate(node => node.getBoundingClientRect());
                        const cardBox = await link.locator('xpath=ancestor::article').evaluate(node => node.getBoundingClientRect());
                        assert(cardBox.bottom - linkBox.bottom < 28, `${width}/${variant}: GitHub action is not aligned near card bottom`);
                    }
                } else {
                    assert.equal(await projectGrid.count(), 0, `${width}/${variant}: empty project grid`);
                }

                if (expected.skills > 0) {
                    assert.equal(await skillsPanel.count(), 1, `${width}/${variant}: skills panel visibility`);
                    const panelBox = await skillsPanel.evaluate(node => node.getBoundingClientRect());
                    const workBox = await page.locator('.portfolio-work-content').evaluate(node => node.getBoundingClientRect());
                    assert(Math.abs(panelBox.width - workBox.width) < 1, `${width}/${variant}: skills panel is not full width`);
                } else {
                    assert.equal(await skillsPanel.count(), 0, `${width}/${variant}: empty skills panel`);
                }

                const overflow = await page.evaluate(() => [...document.querySelectorAll('.portfolio-work, .portfolio-work *')]
                    .filter(node => node.scrollWidth > node.clientWidth + 1)
                    .map(node => `${node.tagName}.${node.className}`));
                assert.deepEqual(overflow, [], `${width}/${variant}: work-region content overflow`);

                const work = page.locator('.portfolio-work');
                const workBox = await work.evaluate(node => node.getBoundingClientRect());
                await page.setViewportSize({ width, height: Math.max(1000, Math.ceil(workBox.bottom) + 12) });
                await work.screenshot({ path: path.join(output, `${width}-${variant}.png`) });

                if (expected.skills > 0) {
                    const firstSkill = skills.first();
                    await firstSkill.click();
                    assert.equal(await firstSkill.getAttribute('aria-pressed'), 'true', `${width}/${variant}: first skill selection`);
                    if (expected.skills > 1) {
                        const secondSkill = skills.nth(1);
                        await secondSkill.click();
                        assert.equal(await firstSkill.getAttribute('aria-pressed'), 'false', `${width}/${variant}: previous skill reset`);
                        assert.equal(await secondSkill.getAttribute('aria-pressed'), 'true', `${width}/${variant}: next skill selection`);
                        await secondSkill.click();
                        assert.equal(await secondSkill.getAttribute('aria-pressed'), 'false', `${width}/${variant}: active skill deselection`);
                    }
                    await firstSkill.focus();
                    await page.keyboard.press('Enter');
                    assert.equal(await firstSkill.getAttribute('aria-pressed'), 'true', `${width}/${variant}: Enter selection`);
                    await page.keyboard.press('Space');
                    assert.equal(await firstSkill.getAttribute('aria-pressed'), 'false', `${width}/${variant}: Space deselection`);
                    if (width === 1440 && variant === 'fallback-many') {
                        await firstSkill.click();
                        await firstSkill.evaluate(node => node.blur());
                        await work.screenshot({ path: path.join(output, '1440-fallback-many-selected.png') });
                    }
                    if (width === 360 && variant === 'long') {
                        const longSkill = skills.last();
                        await longSkill.click();
                        await longSkill.evaluate(node => node.blur());
                        await work.screenshot({ path: path.join(output, '360-long-selected.png') });
                    }
                }
                assert.deepEqual(pageErrors, [], `${width}/${variant}: JavaScript errors`);
                console.log(`PASS projects and skills ${width}/${variant}`);
                await page.close();
            }
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
