const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

// Owner technology review in a real browser: map an unknown label from the
// page, see it counted as "mapped by you", then remove the mapping so the
// later HTTP check starts from the seeded unmapped state.
const baseUrl = process.env.EVIDENCE_HUB_ACTION_TEST_URL;
const edgeExecutable = process.env.EVIDENCE_HUB_EDGE_EXECUTABLE;
const profile = process.env.EVIDENCE_HUB_BROWSER_PROFILE;
const sessionCookie = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
if (!baseUrl || !edgeExecutable || !profile || !sessionCookie) throw new Error('Technology review browser test configuration is incomplete.');

const sessionCookieFor = () => ({ name: 'portfolio_owner_session', value: sessionCookie, url: baseUrl, httpOnly: true });
const localeCookie = locale => ({ name: 'ather_owner_locale', value: locale, url: baseUrl });
const seededLabel = 'h1-unmapped-a';
const stepTimeout = 15000;
let checkpoint = 'initialization';
let context;

const mark = value => { checkpoint = value; console.log(`CHECKPOINT ${value}`); };
const expectState = (condition, reason) => { if (!condition) throw new Error('expected=true actual=false; ' + reason); };
const reviewItem = page => page.locator('.evidence-hub-technology-review-item').filter({ has: page.locator('bdi', { hasText: seededLabel }) });
const feedback = page => page.locator('#evidence-hub-action-feedback').textContent();

(async () => {
    try {
        mark('launch');
        context = await chromium.launchPersistentContext(profile, { headless: true, executablePath: edgeExecutable, viewport: { width: 390, height: 1000 }, reducedMotion: 'reduce' });
        context.setDefaultTimeout(stepTimeout);
        await context.addCookies([sessionCookieFor(), localeCookie('en')]);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', () => errors.push('page-error'));
        page.on('console', message => { if (message.type() === 'error') errors.push('console-error'); });

        mark('review-visible');
        const response = await page.goto(`${baseUrl}/owner/evidence-hub#technologies`, { waitUntil: 'domcontentloaded' });
        expectState(response?.status() === 200, `hub returned ${response?.status() ?? 'none'}`);
        expectState(await reviewItem(page).count() === 1, 'the seeded unknown label is not listed once with its own name');
        expectState(await reviewItem(page).locator('a[href^="/owner_projects.php?edit="]').count() >= 1, 'fix-in-project links are missing');
        expectState(await page.locator('#progress').getByText('0 of 3 projects are public.', { exact: true }).count() === 1, 'the progress section does not show the seeded Owner project publication count');
        expectState(!(await page.locator('#progress').textContent()).includes('Public projects appear to visitors only after the portfolio is published.'), 'publication help appeared without any public projects');

        mark('map');
        await reviewItem(page).locator('select[name="canonical_id"]').selectOption({ label: 'React' });
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), reviewItem(page).getByRole('button', { name: 'Map technology' }).click()]);
        expectState(new URL(page.url()).hash === '#technologies', 'mapping did not return to the technologies section');
        expectState(await feedback(page) === 'Technology mapped.', 'mapping feedback is missing');
        expectState(await reviewItem(page).count() === 0, 'a mapped label is still offered for review');
        const ownerMapped = page.locator('.evidence-hub-technology-owner-mapped li').filter({ hasText: seededLabel });
        expectState(await ownerMapped.count() === 1 && (await ownerMapped.textContent()).includes('React'), 'the mapping is not listed under technologies you mapped');
        expectState(await page.locator('.evidence-hub-technology-pill').filter({ hasText: 'React' }).locator('.evidence-hub-technology-owner-tag').isVisible(), 'the technology map does not mark the Owner mapping');

        mark('reflow');
        for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            expectState(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth), `hub overflowed at ${width}px`);
        }

        mark('unmap');
        await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), ownerMapped.getByRole('button', { name: 'Remove mapping' }).click()]);
        expectState(await feedback(page) === 'Technology mapping removed.', 'unmapping feedback is missing');
        expectState(await reviewItem(page).count() === 1, 'unmapping did not return the label to review');

        mark('arabic-and-no-javascript');
        const arabicContext = await context.browser().newContext({ javaScriptEnabled: false });
        await arabicContext.addCookies([sessionCookieFor(), localeCookie('ar')]);
        const arabic = await arabicContext.newPage();
        await arabic.goto(`${baseUrl}/owner/evidence-hub#technologies`, { waitUntil: 'domcontentloaded' });
        expectState(await arabic.locator('html').getAttribute('dir') === 'rtl', 'the Arabic Owner page is not right-to-left');
        expectState(await arabic.locator('#progress').getByText('0 من أصل 3 مشاريع منشورة للزوار.', { exact: true }).count() === 1, 'the Arabic published count or number agreement is wrong without JavaScript');
        const arabicItem = reviewItem(arabic);
        expectState(await arabicItem.getByText('اربطها بتقنية معروفة').isVisible() && await arabicItem.getByRole('button', { name: 'ربط التقنية' }).isVisible(), 'the Arabic review controls are missing without JavaScript');
        expectState(await arabicItem.locator('summary').textContent() === 'غير موجودة في القائمة؟ اقترح إضافتها', 'the Arabic proposal disclosure is missing');
        await arabicContext.close();

        expectState(errors.length === 0, `expected no browser errors; received ${errors.length}`);
        console.log('PASS Evidence Hub Owner technology review');
    } finally {
        mark('cleanup');
        await context?.close();
    }
})().catch(error => {
    const firstLine = String(error?.message ?? 'operation failed').split(/\r?\n/, 1)[0]
        .replace(/https?:\/\/\S+/g, '[url]')
        .replace(/\b[a-f0-9]{24,}\b/gi, '[redacted]')
        .slice(0, 240);
    console.error(`FAILED at ${checkpoint}: ${firstLine}`);
    process.exitCode = 1;
});
