const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const baseUrl = process.env.EVIDENCE_HUB_ACTION_TEST_URL;
const edgeExecutable = process.env.EVIDENCE_HUB_EDGE_EXECUTABLE;
const profile = process.env.EVIDENCE_HUB_BROWSER_PROFILE;
const sessionCookie = process.env.EVIDENCE_HUB_ACTION_SESSION_COOKIE;
if (!baseUrl || !edgeExecutable || !profile || !sessionCookie) throw new Error('R4 browser test configuration is incomplete.');

const routePath = '/owner/evidence-hub';
const stepTimeout = 15000;
let checkpoint = 'initialization';
let failureCheckpoint = null;
let failureReason = 'operation failed';
let context;
let watchdogTimedOut = false;

const mark = value => { checkpoint = value; console.log(`CHECKPOINT ${value}`); };
const fail = (identifier, reason) => {
    failureCheckpoint = identifier;
    failureReason = reason;
    throw new Error(reason);
};
const expectState = (condition, identifier, reason) => {
    if (!condition) fail(identifier, reason);
};
const withinStepTimeout = (operation, label) => new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error(`${label} timed out`)), stepTimeout);
    operation.then(value => { clearTimeout(timer); resolve(value); }, error => { clearTimeout(timer); reject(error); });
});
const recommendationName = 'Evidence Hub recommendation';
const snoozeName = 'Snooze for 14 days';
const dismissName = 'Dismiss';
const snoozeRecommendationTitle = 'Review technology evidence';
const dismissRecommendationTitle = 'Prepare your portfolio for publishing';
const actionForm = (scope, action) => scope.locator(`form[action="${routePath}"]:has(input[type="hidden"][name="action"][value="${action}"])`);
const actionableCards = page => page.getByRole('article', { name: recommendationName }).filter({ has: page.locator(`form[action="${routePath}"]`) });
const recommendationCard = (page, title) => page.getByRole('article', { name: recommendationName }).filter({ has: page.getByRole('heading', { name: title }) });
const routePathFromLocation = location => {
    if (typeof location !== 'string') return '';
    try { return new URL(location, baseUrl).pathname; } catch { return ''; }
};
const responseMatchesRoutePost = response => {
    try { return response.request().method() === 'POST' && new URL(response.url()).pathname === routePath; } catch { return false; }
};
const currentPathname = page => {
    try { return new URL(page.url()).pathname; } catch { return ''; }
};

const watchdog = setTimeout(async () => {
    watchdogTimedOut = true;
    failureCheckpoint ??= checkpoint;
    failureReason = 'step watchdog timed out';
    console.error(`TIMEOUT at ${checkpoint}`);
    await context?.close();
}, 55000);

(async () => {
    let page;
    try {
        mark('launch');
        context = await withinStepTimeout(chromium.launchPersistentContext(profile, { headless: true, executablePath: edgeExecutable, viewport: { width: 360, height: 1000 }, reducedMotion: 'reduce' }), 'browser launch');
        context.setDefaultTimeout(stepTimeout);
        await context.addCookies([{ name: 'portfolio_owner_session', value: sessionCookie, url: baseUrl, httpOnly: true }]);
        page = await context.newPage();
        const errors = [];
        page.on('pageerror', () => errors.push('page-error'));
        page.on('console', message => { if (message.type() === 'error') errors.push('console-error'); });

        mark('authenticated-rendering');
        const response = await page.goto(`${baseUrl}${routePath}`, { waitUntil: 'domcontentloaded', timeout: stepTimeout });
        expectState(response?.status() === 200, 'authenticated-response-status', `expected 200; received ${response?.status() ?? 'none'}`);
        expectState(await page.getByRole('main').count() === 1, 'main-landmark-present', 'expected one main landmark');
        expectState(await page.getByRole('heading').count() > 0, 'heading-present', 'expected a heading');
        expectState(await page.locator('a[href="#main-content"]').count() > 0, 'skip-link-present', 'expected a skip link');

        mark('fixture-two-actionable-candidates');
        const initialCards = actionableCards(page);
        const initialCount = await initialCards.count();
        console.log(`ACTIONABLE_CANDIDATE_COUNT=${initialCount}`);
        expectState(initialCount >= 2, 'fixture-two-actionable-candidates', `expected at least two actionable recommendations; received ${initialCount}`);

        mark('no-javascript');
        const noJsContext = await context.browser().newContext({ javaScriptEnabled: false });
        await noJsContext.addCookies([{ name: 'portfolio_owner_session', value: sessionCookie, url: baseUrl, httpOnly: true }]);
        const noJs = await noJsContext.newPage();
        await noJs.goto(`${baseUrl}${routePath}`, { waitUntil: 'domcontentloaded', timeout: stepTimeout });
        expectState(await noJs.locator(`form[action="${routePath}"]`).count() > 0, 'no-javascript-forms', 'no-JavaScript action forms were unavailable');
        await noJs.locator('.evidence-hub-options summary').first().click();
        expectState(await noJs.locator('.evidence-hub-options').first().evaluate(node => node.open), 'no-javascript-options', 'native recommendation options did not open without JavaScript');
        await noJsContext.close();

        mark('snooze-form-present');
        const snoozeCard = recommendationCard(page, snoozeRecommendationTitle);
        await snoozeCard.locator('.evidence-hub-options summary').click();
        const snoozeForm = actionForm(snoozeCard, 'snooze');
        expectState(await snoozeForm.count() === 1, 'snooze-form-present', 'expected snooze form was not found');
        const snoozeControl = snoozeForm.getByRole('button', { name: snoozeName });
        expectState(await snoozeControl.count() === 1, 'snooze-control-present', 'expected snooze control was not found');
        expectState(await snoozeControl.isVisible(), 'snooze-control-visible', 'expected snooze control was not visible');
        expectState(await snoozeControl.isEnabled(), 'snooze-control-enabled', 'expected snooze control was disabled');
        await snoozeControl.focus();
        expectState(await page.evaluate(() => document.activeElement?.tagName === 'BUTTON'), 'snooze-control-focus', 'expected snooze control did not receive focus');

        mark('snooze-submit-start');
        const snoozePost = withinStepTimeout(page.waitForResponse(responseMatchesRoutePost, { timeout: stepTimeout }), 'snooze response');
        const snoozeNavigation = withinStepTimeout(page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: stepTimeout }), 'snooze navigation');
        await page.keyboard.press('Enter');
        mark('snooze-response-received');
        const snoozePostResponse = await snoozePost;
        expectState(snoozePostResponse.status() === 303, 'snooze-response-status', `expected 303; received ${snoozePostResponse.status()}`);
        expectState(routePathFromLocation(snoozePostResponse.headers().location) === routePath, 'snooze-redirect-path', 'expected route redirect pathname');
        const snoozeNavigationResponse = await snoozeNavigation;
        expectState(snoozeNavigationResponse?.status() === 200, 'snooze-navigation-status', `expected 200; received ${snoozeNavigationResponse?.status() ?? 'none'}`);
        expectState(currentPathname(page) === routePath, 'snooze-prg-complete', 'expected route pathname after redirect');
        expectState(await page.getByRole('status').textContent() === 'Recommendation snoozed for 14 days.', 'snooze-feedback-visible', 'expected Snooze feedback was not visible');

        mark('dismiss-candidate-remains');
        const postSnoozeCards = actionableCards(page);
        const postSnoozeCount = await postSnoozeCards.count();
        console.log(`POST_SNOOZE_ACTIONABLE_CANDIDATE_COUNT=${postSnoozeCount}`);
        expectState(postSnoozeCount === initialCount - 1, 'dismiss-candidate-remains', `expected ${initialCount - 1} remaining actionable recommendations; received ${postSnoozeCount}`);

        mark('dismiss-form-present');
        const dismissCard = recommendationCard(page, dismissRecommendationTitle);
        await dismissCard.locator('.evidence-hub-options summary').click();
        const dismissForm = actionForm(dismissCard, 'dismiss');
        expectState(await dismissForm.count() === 1, 'dismiss-form-present', 'expected dismiss form was not found');
        const dismissTokenInput = dismissForm.locator('input[type="hidden"][name="action_token"]');
        expectState(await dismissTokenInput.count() === 1 && await dismissTokenInput.inputValue() !== '', 'dismiss-fresh-token-present', 'expected fresh dismiss token was not available');
        const dismissControl = dismissForm.getByRole('button', { name: dismissName });
        expectState(await dismissControl.count() === 1, 'dismiss-control-present', 'expected dismiss control was not found');

        mark('dismiss-control-visible');
        expectState(await dismissControl.isVisible(), 'dismiss-control-visible', 'expected dismiss control was not visible');
        mark('dismiss-control-enabled');
        expectState(await dismissControl.isEnabled(), 'dismiss-control-enabled', 'expected dismiss control was disabled');
        await dismissControl.focus();
        expectState(await page.evaluate(() => document.activeElement?.tagName === 'BUTTON'), 'dismiss-control-focus', 'expected dismiss control did not receive focus');

        mark('dismiss-submit-start');
        const dismissPost = withinStepTimeout(page.waitForResponse(responseMatchesRoutePost, { timeout: stepTimeout }), 'dismiss response');
        const dismissNavigation = withinStepTimeout(page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: stepTimeout }), 'dismiss navigation');
        await page.keyboard.press('Enter');
        mark('dismiss-response-received');
        const dismissPostResponse = await dismissPost;
        expectState(dismissPostResponse.status() === 303, 'dismiss-response-status', `expected 303; received ${dismissPostResponse.status()}`);
        expectState(routePathFromLocation(dismissPostResponse.headers().location) === routePath, 'dismiss-redirect-path', 'expected route redirect pathname');
        const dismissNavigationResponse = await dismissNavigation;
        expectState(dismissNavigationResponse?.status() === 200, 'dismiss-navigation-status', `expected 200; received ${dismissNavigationResponse?.status() ?? 'none'}`);
        expectState(currentPathname(page) === routePath, 'dismiss-prg-complete', 'expected route pathname after redirect');

        mark('dismiss-feedback-visible');
        expectState(await page.getByRole('status').textContent() === 'Recommendation dismissed.', 'dismiss-feedback-visible', 'expected Dismiss feedback was not visible');
        mark('dismiss-recommendation-removed');
        const postDismissCount = await actionableCards(page).count();
        console.log(`POST_DISMISS_ACTIONABLE_CANDIDATE_COUNT=${postDismissCount}`);
        expectState(postDismissCount === postSnoozeCount - 1, 'dismiss-recommendation-removed', `expected ${postSnoozeCount - 1} remaining actionable recommendations; received ${postDismissCount}`);
        expectState(errors.length === 0, 'dismiss-browser-errors', `expected no browser errors; received ${errors.length}`);

        await page.setViewportSize({ width: 1280, height: 800 });
        expectState(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth), 'desktop-reflow', 'desktop reflow overflowed');
        await page.setViewportSize({ width: 360, height: 1000 });
        expectState(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth), 'mobile-reflow', 'mobile reflow overflowed');
        const text = await page.locator('body').innerText();
        expectState(!/recommendation_key|evidence_fingerprint|opaque_target_ref|action_token|[a-f0-9]{64}/i.test(text), 'visible-disclosure', 'response leaked recommendation internals');
        expectState(errors.length === 0, 'browser-errors', `expected no browser errors; received ${errors.length}`);

        if (watchdogTimedOut) fail('watchdog', 'step watchdog timed out');
        console.log('PASS Evidence Hub R4 real Edge actions');
    } catch (error) {
        failureCheckpoint ??= checkpoint;
        throw error;
    } finally {
        mark('cleanup');
        try {
            await page?.close();
            await context?.close();
        } catch (error) {
            if (failureCheckpoint === null) {
                failureCheckpoint = 'cleanup';
                failureReason = 'cleanup failed';
                throw error;
            }
            console.error('CLEANUP_FAILED');
        }
        clearTimeout(watchdog);
    }
})().then(() => {
    if (watchdogTimedOut) process.exitCode = 1;
}).catch(() => { console.error(`FAILED at ${failureCheckpoint ?? checkpoint}: ${watchdogTimedOut ? 'step watchdog timed out' : failureReason}`); process.exitCode = 1; });
