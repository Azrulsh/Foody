import assert from 'node:assert/strict';

const base = process.argv[2] || 'http://localhost/Foody/';
let cookie = '';
let checks = 0;
async function request(path, data) {
    const response = await fetch(new URL(path, base), {
        method: data ? 'POST' : 'GET',
        headers: { Cookie: cookie },
        body: data ? new URLSearchParams(data) : undefined,
        redirect: 'manual',
    });
    const sessionCookie = response.headers.get('set-cookie');
    if (sessionCookie) cookie = sessionCookie.split(';')[0];
    return { status: response.status, body: await response.text(), headers: response.headers };
}
function check(condition, message) { assert.ok(condition, message); checks++; }

for (const module of ['service', 'report']) {
    for (const period of ['weekly', 'monthly']) {
        const selection = `restaurant=lepak&module=${module}&period=${period}`;
        const page = await request(`restaurant.php?${selection}`);
        check(page.status === 200, 'Commission page loads.');
        check(page.body.includes(`${module}${period === 'weekly' ? 'w' : 'm'}.php?restaurant=lepak`), 'Back preserves selection.');
        check(page.body.includes('name="totals" readonly'), 'NETT is read-only.');
        const token = page.body.match(/name="commission_token" value="([a-f0-9]+)"/)[1];
        for (const endpoint of ['insert.php', 'kemas.php']) {
            check((await request(endpoint)).status === 405, 'Write endpoints reject GET.');
            check((await request(endpoint, { commission_token: 'wrong' })).status === 403, 'Reject invalid CSRF.');
            for (const invalid of ['-1', '1.001', '1e2', '10000000']) {
                check((await request(endpoint, { commission_token: token, commissions: invalid, penalties: '0', services: '0' })).status === 422, 'Reject invalid money.');
            }
            const escaped = await request(endpoint, { commission_token: token, commissions: '<script>alert(1)</script>', penalties: '0', services: '0' });
            check(escaped.status === 422 && escaped.body.includes('&lt;script&gt;') && !escaped.body.includes('<script>alert(1)</script>'), 'Escape reflected input.');
            check((await request(endpoint, { commission_token: token, 'commissions[nested]': '1' })).status === 422, 'Reject nested payload.');
        }
    }
}
check((await request('restaurant.php?restaurant[]=bad')).status === 400, 'Reject array selection.');
check((await request('restaurant.php?module=unknown')).status === 400, 'Reject unknown selection.');
check((await request('delete.php?ID=7')).status === 410, 'Retired delete endpoint cannot mutate data.');
check((await request('commission_controller.php')).status === 404, 'Controller is not a public endpoint.');
check((await request('tests/commission_test.php')).status === 404, 'Database tests cannot execute over HTTP.');
console.log(`Passed ${checks} commission HTTP checks; no records written.`);
