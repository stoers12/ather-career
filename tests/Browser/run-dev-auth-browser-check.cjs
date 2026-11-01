'use strict';

// End-to-end check of local development without Auth0 (L-15). It drives a real
// browser through the OIDC mock of docker-compose.dev-auth.yml, which must be
// running. It touches only that stack (project ather_dev, ports 8444 and 9443).
//
//   ATHERCAR_PLAYWRIGHT_MODULE=<path to playwright> node tests/Browser/run-dev-auth-browser-check.cjs
//
// The application allows five sign-in starts per five minutes per address; the
// check clears the dev stack's rate-limit counters before each group of starts.

const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.ATHERCAR_PLAYWRIGHT_MODULE || 'playwright');

const app = 'https://localhost:8444';
const issuerHost = 'auth.localhost:9443';
const web = 'ather_dev-web-1';
const docker = (...args) => execFileSync('docker', args, {encoding: 'utf8'}).trim();
const pass = (message) => console.log(`PASS ${message}`);

async function signIn(page, start, subject, decision = 'allow') {
    await page.goto(app + start);
    await page.waitForURL((url) => url.host === issuerHost);
    assert.equal(await page.title(), 'Development sign-in (not Auth0)');
    if (subject.startsWith('custom:')) await page.fill('input[name="custom_subject"]', subject.slice(7));
    else await page.check(`input[name="subject"][value="${subject}"]`);
    // The callback either redirects into the application or renders its recovery page.
    await Promise.all([page.waitForURL((url) => url.host === 'localhost:8444'), page.click(`button[value="${decision}"]`)]);
    await page.waitForLoadState('domcontentloaded');
}

(async () => {
    assert.match(docker('inspect', '-f', '{{.State.Health.Status}}', web), /^healthy$/, 'Start docker-compose.dev-auth.yml first.');
    const resetRateLimits = () => docker('exec', web, 'sh', '-c', 'find /var/www/app/runtime/rate-limit -type f ! -name .gitkeep -delete');
    resetRateLimits();
    const browser = await chromium.launch();
    try {
        const context = await browser.newContext({ignoreHTTPSErrors: true});
        const page = await context.newPage();

        await signIn(page, '/owner_login.php?method=google', 'dev|owner');
        assert.equal(new URL(page.url()).pathname, '/owner.php');
        pass('the sample owner signs in through the mock and reaches the dashboard');

        // A fresh sign-in hides the "confirm it is you" button, so post the same form directly.
        await page.goto(app + '/owner_settings.php');
        await Promise.all([page.waitForURL((url) => url.host === issuerHost), page.evaluate(() => {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '/owner_reauthenticate.php';
            for (const [name, value] of [['csrf_token', document.querySelector('input[name="csrf_token"]').value], ['return', 'data']]) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = name; input.value = value;
                form.append(input);
            }
            document.body.append(form);
            form.submit();
        })]);
        await page.check('input[name="subject"][value="dev|owner"]');
        await Promise.all([page.waitForURL(`${app}/owner_settings.php#data`), page.click('button[value="allow"]')]);
        pass('confirming identity (prompt=login) returns to Settings → Your data');

        // The logout button sits in the account menu; submit its form as the menu would.
        await Promise.all([page.waitForURL(`${app}/signin.php`), page.evaluate(() => document.querySelector('form[action="/owner_logout.php"]').requestSubmit())]);
        pass('logout goes through the mock and returns to sign-in');

        await signIn(page, '/owner_login.php?method=google', 'dev|owner', 'deny');
        assert.equal(new URL(page.url()).pathname, '/owner_oidc_callback.php');
        assert.equal(await page.locator('form[action="/owner_logout.php"]').count(), 0, 'A cancelled sign-in has no session.');
        pass('cancelling at the mock ends in the sign-in recovery page without a session');

        await signIn(page, '/owner_login.php?method=google', 'custom:dev|stranger');
        assert.equal(new URL(page.url()).pathname, '/owner_oidc_callback.php');
        await page.goto(app + '/owner.php');
        assert.notEqual(new URL(page.url()).pathname, '/owner.php', 'An unknown identity reached the workspace.');
        pass('an identity without an account is refused');

        resetRateLimits();
        const code = docker('exec', web, 'php', '/var/www/app/scripts/ops/registration-invitations.php', 'create', '--count', '1', '--label', 'dev-auth check')
            .split('\n').pop().trim();
        await page.goto(app + '/signup.php');
        await page.fill('input[name="invitation_code"]', code);
        await Promise.all([page.waitForURL((url) => url.host === issuerHost), page.click('form[action="/owner_register.php"] button[type="submit"]')]);
        await page.fill('input[name="custom_subject"]', 'google-oauth2|dev-' + Date.now());
        await Promise.all([page.waitForURL((url) => url.host === 'localhost:8444' && url.pathname !== '/owner_oidc_callback.php'), page.click('button[value="allow"]')]);
        assert.match(new URL(page.url()).pathname, /^\/owner_onboarding/);
        pass('a new person registers with an invitation code and starts onboarding');
    } finally {
        await browser.close();
    }
    console.log('DEV_AUTH_BROWSER_CHECK_PASSED');
})().catch((error) => {
    console.error(`FAIL ${error.message.split('\n')[0]}`);
    process.exit(1);
});
