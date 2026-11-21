import assert from 'node:assert/strict';
import { loginForTests, updateTestCookies } from './http_auth.mjs';
const base = process.argv[2] || 'http://localhost/Foody/';
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
function client() {
    let cookie = '';
    return async (path, data, method) => {
        const response = await fetch(new URL(path, base), {
            method: method || (data ? 'POST' : 'GET'), headers: { Cookie: cookie },
            body: data ? new URLSearchParams(data) : undefined, redirect: 'manual',
        });
        cookie = updateTestCookies(response, cookie);
        return { status: response.status, body: await response.text(), headers: response.headers };
    };
}
function form(html, id) {
    const selected = (html.match(/<form\b[\s\S]*?<\/form>/g) || []).find((item) => item.includes(`id="${id}"`));
    assert.ok(selected, 'Expected form.');
    return Object.fromEntries([...selected.matchAll(/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/g)].map((match) => [match[1], match[2]]));
}
const request = client();
check((await request('settings.php')).status === 303, 'Anonymous read blocked.');
check((await request('settings.php', { action: 'reset' })).status === 303, 'Anonymous reset blocked.');
await loginForTests(request);
let page = await request('settings.php');
check(page.status === 200, 'Settings loads.');
const initial = form(page.body, 'settings-form');
const chosen = { ...initial, action: 'save', default_restaurant: 'lepak', default_duration: 'monthly', orders_per_page: '10', date_format: 'd/m/Y', theme: 'dark', panel_background: 'solid', user_id: '1' };
check((await request('settings.php', { ...chosen, csrf_token: 'wrong' })).status === 403, 'CSRF rejected.');
for (const invalid of [{ default_restaurant: 'unknown' }, { default_duration: 'daily' }, { orders_per_page: '100000' }, { date_format: '<script>' }, { revision: '-1' }, { theme: 'invalid' }, { panel_background: 'invalid' }]) {
    check((await request('settings.php', { ...chosen, ...invalid })).status === 422, 'Invalid option rejected.');
}
check((await request('settings.php', { ...chosen, orders_per_page: undefined, 'orders_per_page[nested]': '10' })).status === 422, 'Nested payload rejected.');
check((await request('settings.php', undefined, 'DELETE')).status === 405, 'Unsupported methods rejected.');
check((await request('settings.php', chosen)).status === 303, 'Settings saved.');
check((await request('settings.php', chosen)).status === 409, 'Stale initial form rejected.');
check((await request('main.php')).body.includes('servicem.php?restaurant=jamilah'), 'Main Page uses Monthly.');
for (const pageName of ['main.php', 'servicew.php', 'servicem.php', 'reportw.php', 'reportm.php', 'restaurant.php', 'profile.php', 'my_restaurants.php', 'orders.php', 'help.php', 'settings.php']) {
    const themed = await request(pageName);
    check(themed.status === 200 && themed.body.includes('data-theme="dark" data-panel="solid"') && themed.body.includes('href="theme.css"'), 'Every protected page uses saved appearance.');
}
let orders = await request('orders.php');
check(/id="order-restaurant"[\s\S]*?<option value="lepak" selected/.test(orders.body), 'New order selects preferred restaurant.');
check(/id="restaurant-filter"[\s\S]*?<option value="lepak" selected/.test(orders.body), 'List selects preferred restaurant.');
const all = await request('orders.php?restaurant=');
const filter = all.body.match(/<select id="restaurant-filter"[\s\S]*?<\/select>/)[0];
check(!filter.includes('value="lepak" selected'), 'Explicit All overrides preference.');
const second = client();
const login = await second('login.php');
const token = login.body.match(/name="csrf_token" value="([a-f0-9]+)"/)[1];
check((await second('login.php', { csrf_token: token, email: process.env.FOODY_TEST_OTHER_EMAIL, password: process.env.FOODY_TEST_PASSWORD })).status === 303, 'Second account login.');
check((await second('main.php')).body.includes('servicew.php?restaurant=jamilah'), 'Other account keeps Weekly.');
check((await second('main.php')).body.includes('data-theme="light" data-panel="transparent"'), 'Appearance is isolated by account.');
const secondSettings = form((await second('settings.php')).body, 'settings-form');
check(secondSettings.revision === '0', 'Forged user_id cannot alter another account.');
const marker = process.env.FOODY_TEST_ORDER_MARKER + ' Settings';
for (let index = 0; index < 11; index++) {
    orders = await request('orders.php');
    const input = { ...form(orders.body, 'order-create'), restaurant: 'lepak', order_date: '9999-12-31', customer_name: `${marker} ${index}`, total: '1.20' };
    check((await request('orders.php', input)).status === 303, 'Create pagination fixture.');
}
orders = await request('orders.php');
check((orders.body.match(/<td>FD-/g) || []).length === 10, 'Preference limits first page to ten orders.');
check(orders.body.includes('31/12/9999'), 'Preferred date display applied.');
check((await request('orders.php?page=2')).body.includes(' Settings 0</td>'), 'Remaining order appears on page two.');
check((await request('my_restaurants.php')).body.match(/name="code"/g).length === 2, 'Restaurant page size unchanged.');
page = await request('settings.php');
const current = form(page.body, 'settings-form');
check((await request('settings.php', { ...current, action: 'reset', csrf_token: 'bad' })).status === 403, 'Reset requires CSRF.');
check((await request('settings.php', { ...current, action: 'reset' })).status === 303, 'Reset saves defaults.');
check((await request('settings.php', { ...chosen, revision: current.revision })).status === 409, 'Reset does not permit stale overwrite.');
check((await request('main.php')).body.includes('servicew.php?restaurant=jamilah'), 'Reset restores Weekly.');
check((await request('orders.php?restaurant=lepak')).body.includes('9999-12-31'), 'Reset restores ISO dates.');
check((await request('main.php')).body.includes('data-theme="light" data-panel="transparent"'), 'Reset restores default appearance.');
for (const theme of ['light', 'dark']) {
    for (const panel_background of ['solid', 'transparent']) {
        const latest = form((await request('settings.php')).body, 'settings-form');
        check((await request('settings.php', { ...chosen, ...latest, theme, panel_background })).status === 303, 'Save each appearance combination.');
        check((await request('main.php')).body.includes(`data-theme="${theme}" data-panel="${panel_background}"`), 'Each appearance combination renders.');
    }
}
const beforeLogout = form((await request('settings.php')).body, 'settings-form');
check((await request('logout.php', { csrf_token: beforeLogout.csrf_token })).status === 303, 'Logout with appearance cookie succeeds.');
check((await request('login.php')).body.includes('data-theme="dark" data-panel="transparent"'), 'Login remembers last browser appearance after logout.');
await loginForTests(request);
check((await request('main.php')).body.includes('data-theme="dark" data-panel="transparent"'), 'Account preference survives a new login.');
const finalForm = form((await request('settings.php')).body, 'settings-form');
check((await request('settings.php', { ...finalForm, action: 'reset' })).status === 303, 'Restore fixture defaults for other suites.');
console.log(`Passed ${checks} settings HTTP checks.`);
