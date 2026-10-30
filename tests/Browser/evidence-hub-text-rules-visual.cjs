const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const baseUrl = process.env.EVIDENCE_HUB_VISUAL_BASE_URL;
const executablePath = process.env.EVIDENCE_HUB_EDGE_EXECUTABLE;
if (!baseUrl || !executablePath) throw new Error('Text-rule browser test configuration is incomplete.');
const hints = {
    en: ["Stretched letters don't count. Add real details instead.", 'The same few words repeat. Describe it in more varied, specific words.', 'This repeats another field of the same project. Write what is specific to this field.'],
    ar: ['الحروف المكررة لا تُحتسب. أضف تفاصيل حقيقية بدلاً منها.', 'تتكرر الكلمات نفسها كثيراً. صِف بكلمات أكثر تنوعاً وتحديداً.', 'هذا النص يكرر حقلاً آخر في المشروع نفسه. اكتب ما يخص هذا الحقل تحديداً.'],
};
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath });
    try {
        for (const locale of ['en','ar']) for (const width of [390,1280]) {
            const context = await browser.newContext({ viewport: { width, height: 1000 }, javaScriptEnabled: false });
            const page = await context.newPage();
            const response = await page.goto(`${baseUrl}/tests/Browser/evidence-hub-text-rules-visual.php?lang=${locale}`);
            assert.equal(response.status(),200,`${locale}/${width}: fixture response`);
            assert.equal(await page.locator('html').getAttribute('dir'),locale === 'ar' ? 'rtl' : 'ltr');
            for (const summary of await page.locator('.evidence-hub-project-disclosure > summary').all()) { if (!await summary.evaluate(node => node.parentElement.open)) await summary.click(); }
            for (const hint of hints[locale]) {
                const message = page.getByText(hint,{ exact: true }).first();
                assert.equal(await message.isVisible(),true,`${locale}/${width}: localized hint visible without JavaScript`);
            }
            assert.equal(await page.locator('.evidence-hub-project-card').count(),3);
            assert.equal(await page.evaluate(() => document.body.scrollWidth <= innerWidth),true,`${locale}/${width}: no horizontal overflow`);
            assert.equal((await page.locator('body').textContent()).includes('sooooo'),false);
            await context.close();
        }
        console.log('PASS Evidence Hub text rules localized responsive hints');
    } finally { await browser.close(); }
})().catch(() => { console.error('Evidence Hub text-rule browser gate failed.'); process.exitCode = 1; });
