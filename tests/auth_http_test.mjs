import assert from 'node:assert/strict';

const base = process.argv[2] || 'http://localhost/Foody/';
const email = process.env.FOODY_TEST_EMAIL;
const password = process.env.FOODY_TEST_PASSWORD;
if (!email || !password) throw new Error('Use php tests/run_authenticated_tests.php');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
function client() {
    let cookie = '';
    return {
        cookie: () => cookie,
        async request(path, data) {
            const response = await fetch(new URL(path, base), {
                method: data ? 'POST' : 'GET', headers: { Cookie: cookie },
                body: data ? new URLSearchParams(data) : undefined, redirect: 'manual',
            });
            for (const value of response.headers.getSetCookie()) cookie = value.split(';')[0];
            return { status: response.status, body: await response.text(), headers: response.headers };
        },
    };
}
function field(html, name) {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = html.match(new RegExp(`name="${escaped}"[^>]*value="([^"]*)"`));
    assert.ok(match, `Missing field ${name}`);
    return match[1];
}
async function signIn(browser, secret = password) {
    const page = await browser.request('login.php');
    return browser.request('login.php', { email, password: secret, csrf_token: field(page.body, 'csrf_token') });
}
const browser = client();
for (const path of ['main.php', 'sidebar.php', 'profile.php', 'my_restaurants.php', 'help.php', 'orders.php', 'servicew.php', 'servicem.php', 'reportw.php', 'reportm.php', 'restaurant.php', 'insert.php', 'kemas.php', 'data.php', 'delete.php']) {
    const response = await browser.request(path);
    check(response.status === 303 && response.headers.get('location') === 'login.php', 'Anonymous requests must redirect to login.');
}
for (const path of ['servicew.php', 'reportw.php', 'insert.php', 'kemas.php']) {
    check((await browser.request(path, { commissions: '999' })).status === 303, 'Anonymous writes cannot execute.');
}
check((await browser.request('logout.php')).status === 405, 'GET cannot log out.');
const login = await browser.request('login.php');
check(login.status === 200, 'Login loads.');
check(login.headers.get('set-cookie')?.includes('HttpOnly') || browser.cookie().startsWith('PHPSESSID='), 'Session cookie is issued.');
check((await browser.request('login.php', { csrf_token: 'wrong', email, password })).status === 403, 'Login CSRF protection.');
check((await signIn(browser, 'wrong-password')).status === 401, 'Wrong password rejected.');
const oldCookie = browser.cookie();
check((await signIn(browser)).status === 303 && browser.cookie() !== oldCookie, 'Login rotates session ID.');
const main = await browser.request('main.php');
check(main.status === 200 && main.body.includes('Foody Test User'), 'Sidebar shows current account.');
check(main.body.includes("document.location='main.php'"), 'Main Page is linked.');
check(main.body.includes("document.location='help.php'"), 'Help Center is linked.');
const help = await browser.request('help.php');
check(help.status === 200 && help.body.includes('HELP CENTER'), 'Help Center loads after login.');
check(help.body.includes('help-commission') && help.body.includes('help-errors'), 'Usage and troubleshooting guidance is available.');
check((await browser.request('help.php', { action: 'save' })).status === 405, 'Help Center does not accept writes.');
const another = client();
check((await signIn(another)).status === 303, 'Second browser login.');
let page = await browser.request('profile.php');
const form = { csrf_token: field(page.body, 'csrf_token'), revision: field(page.body, 'revision'), name: 'Foody Test User', email };
check((await browser.request('profile.php', { ...form, csrf_token: 'bad' })).status === 403, 'Profile CSRF protection.');
check((await browser.request('profile.php', { ...form, email: 'changed@example.invalid' })).status === 422, 'Email changes require current password.');
check((await browser.request('profile.php', { ...form, email: process.env.FOODY_TEST_OTHER_EMAIL, current_password: password })).status === 422, 'Duplicate email rejected.');
check((await browser.request('profile.php', { ...form, new_password: 'short', current_password: password })).status === 422, 'Weak password rejected.');
check((await browser.request('profile.php', { ...form, name: '<script>alert(1)</script>' })).status === 303, 'Name update saves.');
const renamed = await browser.request('main.php');
check(renamed.body.includes('&lt;script&gt;') && !renamed.body.includes('<script>alert(1)</script>'), 'Sidebar escapes stored profile data.');
check((await browser.request('profile.php', form)).status === 409, 'Stale profile form rejected.');
page = await browser.request('profile.php');
const next = { csrf_token: field(page.body, 'csrf_token'), revision: field(page.body, 'revision'), name: 'Foody Test User', email, current_password: password, new_password: `${password}A`, password_confirmation: `${password}A` };
check((await browser.request('profile.php', next)).status === 303, 'Password change succeeds.');
check((await another.request('main.php')).status === 303, 'Password change revokes older sessions.');
const oldPasswordBrowser = client();
check((await signIn(oldPasswordBrowser)).status === 401, 'Old password no longer works.');
page = await browser.request('profile.php');
check((await browser.request('profile.php', { ...next, csrf_token: field(page.body, 'csrf_token'), revision: field(page.body, 'revision'), current_password: `${password}A`, new_password: password, password_confirmation: password })).status === 303, 'Restore fixture password.');
page = await browser.request('profile.php');
check((await browser.request('logout.php', { csrf_token: 'wrong' })).status === 403, 'Logout CSRF protection.');
check((await browser.request('main.php')).status === 200, 'Bad logout token preserves session.');
check((await browser.request('logout.php', { csrf_token: field(page.body, 'csrf_token') })).status === 303, 'Logout succeeds.');
check((await browser.request('main.php')).status === 303, 'Logged-out requests are denied.');
check((await browser.request('scripts/create_account.php')).status === 404, 'Account setup cannot run over HTTP.');
console.log(`Passed ${checks} authentication HTTP checks.`);
