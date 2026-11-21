import assert from 'node:assert/strict';
import { loginForTests, updateTestCookies } from './http_auth.mjs';

const base = process.argv[2] || 'http://localhost/Foody/';
let cookie = '';
let checks = 0;
async function request(path, data, method) {
    const response = await fetch(new URL(path, base), {
        method: method || (data ? 'POST' : 'GET'), headers: { Cookie: cookie },
        body: data ? new URLSearchParams(data) : undefined, redirect: 'manual',
    });
    cookie = updateTestCookies(response, cookie);
    return { status: response.status, body: await response.text(), headers: response.headers };
}
function check(condition, message) { assert.ok(condition, message); checks++; }
function form(html, code) {
    const forms = html.match(/<form\b[\s\S]*?<\/form>/g) || [];
    const selected = forms.find((item) => item.includes(`name="code" value="${code}"`));
    assert.ok(selected, 'Expected restaurant form.');
    const field = (name) => selected.match(new RegExp(`name="${name}"[^>]*value="([^"]*)"`))?.[1];
    return { csrf_token: field('csrf_token'), code, revision: field('revision') };
}
check((await request('my_restaurants.php')).status === 303, 'Anonymous read blocked.');
check((await request('my_restaurants.php', { code: 'jamilah' })).status === 303, 'Anonymous write blocked.');
await loginForTests(request);
const page = await request('my_restaurants.php');
check(page.status === 200, 'Page loads.');
check((page.body.match(/name="code"/g) || []).length === 2, 'Two restaurants on the first page.');
check(page.body.includes('my_restaurants.php?page=2') && page.body.includes('Page 1 of 2'), 'First page links to the next page.');
const secondPage = await request('my_restaurants.php?page=2');
check(secondPage.status === 200 && (secondPage.body.match(/name="code"/g) || []).length === 1, 'Last restaurant is on page two.');
check(secondPage.body.includes('name="code" value="lepak"') && secondPage.body.includes('Page 2 of 2'), 'Stable ordering across pages.');
const secondError = await request('my_restaurants.php?page=2', { ...form(secondPage.body, 'lepak'), name: '', address: '', phone: '' });
check(secondError.status === 422 && secondError.body.includes('Page 2 of 2'), 'Validation errors preserve the current page.');
check((await request('my_restaurants.php?page=999')).body.includes('Page 2 of 2'), 'Out-of-range page is clamped.');
check((await request('my_restaurants.php?page[]=2')).status === 400, 'Array page rejected.');
check((await request('my_restaurants.php?page=0')).status === 400, 'Invalid page rejected.');
check(!page.body.includes('type="file"'), 'No image upload flow.');
const original = JSON.parse(process.env.FOODY_TEST_RESTAURANT_SNAPSHOT || 'null');
const marker = process.env.FOODY_TEST_RESTAURANT_MARKER;
assert.ok(original && marker, 'Use php tests/run_authenticated_tests.php');
const payload = { ...form(page.body, 'jamilah'), name: original.name, address: original.address, phone: original.phone };
check((await request('my_restaurants.php', { ...payload, csrf_token: 'wrong' })).status === 403, 'CSRF rejected.');
for (const changed of [{ code: 'unknown' }, { revision: '0' }, { name: '' }, { phone: '123abc' }, { address: 'a'.repeat(501) }]) {
    check((await request('my_restaurants.php', { ...payload, ...changed })).status === 422, 'Invalid fields rejected.');
}
check((await request('my_restaurants.php', { ...payload, name: undefined, 'name[nested]': 'x' })).status === 422, 'Nested payload rejected.');
check((await request('my_restaurants.php', undefined, 'DELETE')).status === 405, 'Unsupported method rejected.');
const saved = await request('my_restaurants.php', { ...payload, name: marker });
check(saved.status === 303 && saved.headers.get('location') === 'my_restaurants.php?page=1', 'Save redirects to the current page.');
const main = await request('main.php');
const escaped = marker.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
check(main.status === 200 && main.body.includes(escaped) && !main.body.includes(marker), 'Main Page reads and escapes the updated name.');
check(main.body.includes('servicew.php?restaurant=jamilah'), 'Original service identity preserved.');
check((await request('my_restaurants.php', payload)).status === 409, 'Stale form rejected.');
const updated = await request('my_restaurants.php');
check(updated.body.includes(escaped) && !updated.body.includes(marker), 'Restaurant page escapes stored values.');
check((await request('my_restaurants.php', { ...payload, ...form(updated.body, 'jamilah') })).status === 303, 'Restore original restaurant details.');
console.log(`Passed ${checks} restaurant HTTP checks; original details restored.`);
