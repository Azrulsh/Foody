export async function loginForTests(request) {
    const email = process.env.FOODY_TEST_EMAIL;
    const password = process.env.FOODY_TEST_PASSWORD;
    if (!email || !password) {
        throw new Error('Run HTTP suites through: php tests/run_authenticated_tests.php');
    }
    const page = await request('login.php');
    const token = page.body.match(/name="csrf_token" value="([a-f0-9]+)"/)?.[1];
    if (!token) throw new Error('Login form is unavailable.');
    const result = await request('login.php', { email, password, csrf_token: token });
    if (result.status !== 303) throw new Error(`Test login failed (${result.status}).`);
}

/** Keep session and appearance cookies independently, like a browser does. */
export function updateTestCookies(response, existing) {
    const cookies = new Map(existing.split('; ').filter(Boolean).map((pair) => {
        const separator = pair.indexOf('=');
        return [pair.slice(0, separator), pair.slice(separator + 1)];
    }));
    for (const header of response.headers.getSetCookie()) {
        const pair = header.split(';')[0];
        const separator = pair.indexOf('=');
        const key = pair.slice(0, separator);
        const value = pair.slice(separator + 1);
        if (value === '' || /;\s*Max-Age=0(?:;|$)/i.test(header)) cookies.delete(key);
        else cookies.set(key, value);
    }
    return [...cookies].map(([key, value]) => `${key}=${value}`).join('; ');
}
