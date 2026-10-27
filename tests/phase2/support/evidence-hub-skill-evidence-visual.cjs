const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');

const baseUrl = process.env.EVIDENCE_HUB_VISUAL_BASE_URL;
const executablePath = process.env.EVIDENCE_HUB_EDGE_EXECUTABLE;
if (!baseUrl || !executablePath) throw new Error('Skill evidence browser test configuration is incomplete.');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const locale of ['en', 'ar']) {
            for (const width of [390, 1280]) {
                const context = await browser.newContext({ viewport: { width, height: 900 }, javaScriptEnabled: false });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', () => errors.push('pageerror'));
                const response = await page.goto(`${baseUrl}/tests/phase2/support/evidence-hub-skill-evidence-visual.php?lang=${locale}`);
                assert.equal(response.status(), 200, `${locale}/${width}: fixture response`);
                assert.equal(await page.locator('html').getAttribute('dir'), locale === 'ar' ? 'rtl' : 'ltr');
                const card = page.locator('.evidence-hub-recommendation-card');
                assert.equal(await card.count(), 1, `${locale}/${width}: one skill card`);
                assert.equal(await card.locator('h3').textContent(), locale === 'ar' ? 'أثبت مهارة بمشروع' : 'Show evidence for a skill');
                assert.equal(await card.locator('bdi[dir="auto"]').textContent(), 'React');
                assert.equal(await card.getByRole('link', { name: locale === 'ar' ? 'إضافة مشروع' : 'Add project' }).getAttribute('href'), '/owner_projects.php?add=1');
                assert.equal(await card.getByRole('link', { name: locale === 'ar' ? 'راجع المهارات' : 'Review skills' }).getAttribute('href'), '/owner_profile.php#skills');
                await card.locator('summary').click();
                assert.equal(await card.locator('input[name="action"][value="snooze"]').count(), 1);
                assert.equal(await card.locator('input[name="action"][value="dismiss"]').count(), 1);
                assert.equal(await page.evaluate(() => document.body.scrollWidth <= innerWidth), true, `${locale}/${width}: no horizontal overflow`);
                assert.deepEqual(errors, [], `${locale}/${width}: no browser errors`);
                await context.close();
            }
        }
        console.log('PASS Evidence Hub skill evidence responsive Owner card');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(`Skill evidence browser gate failed: ${String(error?.message ?? 'unknown error').split(/\r?\n/, 1)[0]}`);
    process.exitCode = 1;
});
