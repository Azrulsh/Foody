import assert from 'node:assert/strict';

const base = process.argv[2] || 'http://127.0.0.1:8097/';
let cookie = '';
let checks = 0;

async function request(path, data, method) {
    const response = await fetch(new URL(path, base), {
        method: method || (data ? 'POST' : 'GET'),
        headers: { Cookie: cookie },
        body: data ? new URLSearchParams(data) : undefined,
        redirect: 'manual',
    });
    const sessionCookie = response.headers.get('set-cookie');
    if (sessionCookie) {
        cookie = sessionCookie.split(';')[0];
    }
    return { status: response.status, body: await response.text(), headers: response.headers };
}

function check(condition, message) {
    assert.ok(condition, message);
    checks++;
}

for (const [page, slots] of [['servicew.php', 7], ['servicem.php', 4]]) {
    const loaded = await request(`${page}?restaurant=greenview`);
    check(loaded.status === 200, `${page} loads`);
    check((loaded.body.match(/data-service-amount/g) || []).length === slots, 'Expected slot count');
    check(!loaded.body.includes('role="alert"'), 'No database error');
    check(loaded.headers.get('cache-control') === 'no-store', 'Financial data is not cached');
    const token = loaded.body.match(/name="csrf_token" value="([a-f0-9]+)"/)[1];
    check((await request(`${page}?restaurant=greenview`, { csrf_token: 'wrong' })).status === 403, 'Reject invalid CSRF');
    const payload = { csrf_token: token };
    for (let index = 0; index < slots; index++) {
        payload[`amounts[${index}]`] = '0.00';
    }
    for (const invalid of ['-1.00', '1e2', '10000000', '1.001']) {
        payload['amounts[0]'] = invalid;
        check((await request(`${page}?restaurant=greenview`, payload)).status === 422, 'Reject invalid money');
    }
    payload['amounts[0]'] = '<script>alert(1)</script>';
    const rejected = await request(`${page}?restaurant=greenview`, payload);
    check(rejected.status === 422 && !rejected.body.includes('<script>alert(1)</script>') && rejected.body.includes('&lt;script&gt;'), 'Escape rejected input');
    check((await request(`${page}?restaurant=greenview`, { csrf_token: token, amounts: 'invalid' })).status === 422, 'Handle scalar payload safely');
    check((await request(`${page}?restaurant=greenview`, { csrf_token: token, 'amounts[0][nested]': '1' })).status === 422, 'Handle nested payload safely');
}

check((await request('servicew.php?restaurant[]=bad')).status === 400, 'Reject array restaurant');
check((await request('servicew.php?restaurant=unknown')).status === 400, 'Reject unknown restaurant');
check((await request('servicew.php', undefined, 'PUT')).status === 405, 'Reject unsupported methods');
check((await request('service_controller.php')).status === 404, 'Controller is not a public page');
check((await request('tests/service_test.php')).status === 404, 'PHP tests cannot execute over HTTP');
const destination = await request('restaurant.php');
check(destination.status === 200 && !destination.body.includes('Warning:') && !destination.body.includes('Fatal error:'), 'Commission destination loads safely');

console.log(`Passed ${checks} HTTP checks; no service data written.`);
