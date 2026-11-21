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
