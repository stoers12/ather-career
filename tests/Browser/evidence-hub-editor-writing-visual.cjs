const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

// Evidence editor writing aids in a real browser: the sentence starter and the
// live non-blocking hints. Nothing is submitted, so the later editor HTTP check
// starts from unchanged evidence.
const baseUrl = process.env.EVIDENCE_HUB_ACTION_TEST_URL;
const edgeExecutable = process.env.EVIDENCE_HUB_EDGE_EXECUTABLE;
const profile = process.env.EVIDENCE_HUB_BROWSER_PROFILE;
const sessionCookie = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
if (!baseUrl || !edgeExecutable || !profile || !sessionCookie) throw new Error('Editor writing browser test configuration is incomplete.');

const englishLocaleCookie = { name: 'ather_owner_locale', value: 'en', url: baseUrl };
const ownerCookies = [{ name: 'portfolio_owner_session', value: sessionCookie, url: baseUrl, httpOnly: true }, englishLocaleCookie];
const outcomeStarter = '[Metric]: [before] → [after]';
const stepTimeout = 15000;
let checkpoint = 'initialization';
let context;

const mark = value => { checkpoint = value; console.log(`CHECKPOINT ${value}`); };
const expectState = (condition, reason) => { if (!condition) throw new Error('expected=true actual=false; ' + reason); };

const hintVisible = (page, id) => page.locator(`#measurable-outcome [data-evidence-writing-hint="${id}"]`).isVisible();

(async () => {
    try {
        mark('launch');
        context = await chromium.launchPersistentContext(profile, { headless: true, executablePath: edgeExecutable, viewport: { width: 390, height: 1000 }, reducedMotion: 'reduce' });
        context.setDefaultTimeout(stepTimeout);
        await context.addCookies(ownerCookies);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', () => errors.push('page-error'));
        page.on('console', message => { if (message.type() === 'error') errors.push('console-error'); });

        mark('editor-route');
        await page.goto(`${baseUrl}/owner/evidence-hub`, { waitUntil: 'domcontentloaded' });
        const editHref = await page.locator('a[href^="/owner/projects/"][href$="/evidence"]').first().getAttribute('href');
        expectState(/^\/owner\/projects\/[1-9][0-9]*\/evidence$/.test(editHref || ''), 'hub exposes a canonical evidence edit link');
        const response = await page.goto(`${baseUrl}${editHref}?mode=write&part=measurable_outcome`, { waitUntil: 'domcontentloaded' });
        expectState(response?.status() === 200, `editor returned ${response?.status() ?? 'none'}`);

        await page.locator('[data-evidence-step=measurable_outcome]').click();
        mark('starter-insert');
        const textarea = page.locator('#evidence-measurable-outcome');
        const insert = page.locator('#measurable-outcome [data-evidence-insert-starter]');
        await textarea.fill('');
        expectState(await insert.isVisible() && await insert.isEnabled(), 'starter control is available for an empty field');
        expectState(!await hintVisible(page, 'unfilled_slot') && !await hintVisible(page, 'outcome_without_number'), 'an empty field shows no hints');
        await insert.click();
        expectState(await textarea.inputValue() === outcomeStarter, 'starter was not inserted verbatim');
        const selection = await textarea.evaluate(node => [document.activeElement === node, node.value.slice(node.selectionStart, node.selectionEnd)]);
        expectState(selection[0] && selection[1] === '[Metric]', 'focus and first-slot selection were not applied');
        expectState(await insert.isDisabled(), 'starter control must not overwrite written text');
        expectState(await hintVisible(page, 'unfilled_slot') && await hintVisible(page, 'outcome_without_number'), 'unedited starter must raise both hints');

        mark('live-hints');
        await textarea.fill('Improved reporting speed for the whole team');
        expectState(!await hintVisible(page, 'unfilled_slot') && await hintVisible(page, 'outcome_without_number'), 'text without digits must raise only the number hint');
        await textarea.fill('Cut weekly reporting time from 6 hours to 1 hour.');
        expectState(!await hintVisible(page, 'unfilled_slot') && !await hintVisible(page, 'outcome_without_number'), 'a measured outcome must clear every hint');
        const describedBy = (await textarea.getAttribute('aria-describedby') || '').split(' ');
        expectState(describedBy.includes('evidence-help-measurable-outcome') && describedBy.includes('evidence-hints-measurable-outcome'), 'textarea is not described by its guidance and hints');

        mark('reflow');
        for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            expectState(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth), `editor overflowed at ${width}px`);
        }

        mark('no-javascript');
        const noJsContext = await context.browser().newContext({ javaScriptEnabled: false });
        await noJsContext.addCookies(ownerCookies);
        const noJs = await noJsContext.newPage();
        await noJs.goto(`${baseUrl}${editHref}?mode=write&part=measurable_outcome`, { waitUntil: 'domcontentloaded' });
        expectState(await noJs.locator('#measurable-outcome [data-evidence-starter] bdi').textContent() === outcomeStarter, 'starter text is not readable without JavaScript');
        expectState(!await noJs.locator('#measurable-outcome [data-evidence-insert-starter]').isVisible(), 'insert control must stay hidden without JavaScript');
        await noJsContext.close();

        expectState(errors.length === 0, `expected no browser errors; received ${errors.length}`);
        console.log('PASS Evidence editor writing aids');
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
