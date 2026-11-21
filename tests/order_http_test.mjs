import assert from 'node:assert/strict';
import { loginForTests } from './http_auth.mjs';
const base = process.argv[2] || 'http://localhost/Foody/';
const marker = process.env.FOODY_TEST_ORDER_MARKER;
assert.ok(marker, 'Use php tests/run_authenticated_tests.php');
let cookie = '';
let checks = 0;
async function request(path, data, method) {
    const response = await fetch(new URL(path, base), {
        method: method || (data ? 'POST' : 'GET'), headers: { Cookie: cookie },
        body: data ? new URLSearchParams(data) : undefined, redirect: 'manual',
    });
    for (const item of response.headers.getSetCookie()) cookie = item.split(';')[0];
    return { status: response.status, body: await response.text(), headers: response.headers };
}
function check(condition, message) { assert.ok(condition, message); checks++; }
function fields(html) {
    return Object.fromEntries([...html.matchAll(/<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"[^>]*>/g)].map((match) => [match[1], match[2]]));
}
function createForm(html) {
    const form = html.match(/<form\b[^>]*id="order-create"[\s\S]*?<\/form>/)?.[0];
    assert.ok(form, 'Expected add form.');
    return fields(form);
}
const escape = (value) => value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
function orderRow(html, customer) {
    const row = (html.match(/<tr>[\s\S]*?<\/tr>/g) || []).find((item) => item.includes(escape(customer)));
    assert.ok(row, 'Expected saved order row.');
    return row;
}
check((await request('orders.php')).status === 303, 'Anonymous list blocked.');
check((await request('orders.php', { action: 'create' })).status === 303, 'Anonymous creation blocked.');
await loginForTests(request);
const page = await request('orders.php');
check(page.status === 200 && page.body.includes('ORDER LIST'), 'Order List loads.');
const input = { ...createForm(page.body), restaurant: 'jamilah', order_date: '9999-12-31', customer_name: marker, total: '0.30' };
check((await request('orders.php', { ...input, csrf_token: 'bad' })).status === 403, 'CSRF blocked.');
for (const changed of [{ restaurant: 'unknown' }, { order_date: '2026-02-29' }, { total: '1e2' }, { total: '-1' }, { total: '1.001' }, { customer_name: '' }, { action: 'delete' }]) {
    check((await request('orders.php', { ...input, ...changed })).status === 422, 'Invalid input rejected.');
}
check((await request('orders.php', { ...input, customer_name: undefined, 'customer_name[nested]': 'x' })).status === 422, 'Nested input rejected.');
check((await request('orders.php', undefined, 'DELETE')).status === 405, 'No delete endpoint.');
check((await request('orders.php?restaurant[]=bad')).status === 400, 'Invalid filter rejected.');
check((await request('orders.php?page=0')).status === 400, 'Invalid page rejected.');
check((await request('orders.php', { ...input, request_key: 'unknown' })).status === 409, 'Unknown creation form rejected.');
check((await request('orders.php', { ...input, status: 'completed' })).status === 303, 'Create redirects.');
check((await request('orders.php', input)).status === 409, 'Double submission cannot create another order.');
let list = await request('orders.php?restaurant=jamilah');
let row = orderRow(list.body, marker);
check(row.includes('Pending') && row.includes('0.30'), 'Server chooses Pending and preserves cents.');
check(!list.body.includes(marker) && list.body.includes(escape(marker)), 'Stored customer name escaped.');
const statusForm = fields(row);
check((await request('orders.php', { ...statusForm, status: 'pending' })).status === 422, 'Invalid transition blocked.');
check((await request('orders.php', { ...statusForm, csrf_token: 'wrong', status: 'completed' })).status === 403, 'Status CSRF blocked.');
check((await request('orders.php', { ...statusForm, status: 'completed' })).status === 303, 'Complete succeeds.');
check((await request('orders.php', { ...statusForm, status: 'cancelled' })).status === 409, 'Stale status update rejected.');
list = await request('orders.php?restaurant=jamilah');
row = orderRow(list.body, marker);
check(row.includes('Completed') && row.includes('Final') && !row.includes('<form'), 'Completed order is final.');
const cancelCustomer = marker + ' Cancel';
const second = { ...createForm(list.body), restaurant: 'lepak', order_date: '9999-12-31', customer_name: cancelCustomer, total: '10.50' };
check((await request('orders.php', second)).status === 303, 'Second restaurant creation succeeds.');
list = await request('orders.php?restaurant=lepak');
const cancelForm = fields(orderRow(list.body, cancelCustomer));
check((await request('orders.php', { ...cancelForm, status: 'cancelled' })).status === 303, 'Cancel succeeds.');
check((await request('orders.php?restaurant=greenview')).body.includes(escape(marker)) === false, 'Restaurant filter excludes other records.');
check((await request('order_controller.php')).status === 404, 'Controller cannot execute directly.');
console.log(`Passed ${checks} order HTTP checks.`);
