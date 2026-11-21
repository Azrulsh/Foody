import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

// Start an isolated headless browser on this port, then run the PHP fixture runner
// with --appearance-browser. No real account credentials are used.
const targetResponse = await fetch('http://127.0.0.1:9237/json/new?about:blank', { method: 'PUT' });
const target = await targetResponse.json();
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
let nextId = 0;
let checks = 0;
const pending = new Map();
const events = new Map();
socket.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id) {
        const operation = pending.get(message.id);
        pending.delete(message.id);
        if (message.error) operation?.reject(new Error(message.error.message));
        else operation?.resolve(message.result);
    } else {
        for (const handler of events.get(message.method) || []) handler(message.params);
        events.delete(message.method);
    }
});
function send(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++nextId;
        pending.set(id, { resolve, reject });
        socket.send(JSON.stringify({ id, method, params }));
    });
}
function loaded() {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('Browser navigation timed out.')), 15000);
        const handler = () => { clearTimeout(timeout); resolve(); };
        events.set('Page.loadEventFired', [handler]);
    });
}
async function evaluate(expression) {
    const result = await send('Runtime.evaluate', { expression, returnByValue: true });
    if (result.exceptionDetails) throw new Error('Browser evaluation failed.');
    return result.result.value;
}
async function navigate(page) {
    const wait = loaded();
    await send('Page.navigate', { url: new URL(page, 'http://localhost/Foody/').href });
    await wait;
}
async function submit(expression) {
    const wait = loaded();
    await evaluate(expression);
    await wait;
}
function check(condition, message) { assert.ok(condition, message); checks++; }
const screenshots = join(tmpdir(), 'foody-theme-screenshots');
await mkdir(screenshots, { recursive: true });
await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 1000, deviceScaleFactor: 1, mobile: false });
try {
    await navigate('login.php');
    await submit(`document.getElementById('email').value=${JSON.stringify(process.env.FOODY_TEST_EMAIL)}; document.getElementById('password').value=${JSON.stringify(process.env.FOODY_TEST_PASSWORD)}; document.querySelector('form').requestSubmit();`);
    check(await evaluate("location.pathname.endsWith('/main.php')"), 'Browser login succeeds.');
    for (const page of ['main.php', 'servicew.php', 'servicem.php', 'reportw.php', 'reportm.php', 'restaurant.php', 'profile.php', 'my_restaurants.php', 'orders.php', 'help.php', 'settings.php']) {
        await navigate(page);
        for (const fraction of [0, 0.5, 1]) {
            await evaluate(`window.scrollTo(0, (document.documentElement.scrollHeight - innerHeight) * ${fraction})`);
            const placement = await evaluate(`(() => {
                const wrapper = document.querySelector('.sidebar-sticky');
                const rect = wrapper.getBoundingClientRect();
                return { top: rect.top, bottom: rect.bottom, center: (rect.top + rect.bottom) / 2, viewport: innerHeight, position: getComputedStyle(wrapper).position };
            })()`);
            check(placement.position === 'sticky' && placement.top >= 10 && placement.bottom <= placement.viewport - 10, 'Sidebar stays visible while scrolling on ' + page);
            check(Math.abs(placement.center - placement.viewport / 2) <= 3, 'Sidebar stays centered in the viewport on ' + page + ': ' + JSON.stringify(placement));
        }
    }
    await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 400, deviceScaleFactor: 1, mobile: false });
    await navigate('help.php');
    check(await evaluate(`(() => {
        const wrapper = document.querySelector('.sidebar-sticky');
        wrapper.scrollTop = wrapper.scrollHeight;
        const rect = wrapper.getBoundingClientRect();
        const logout = wrapper.querySelector('button[type="submit"]').getBoundingClientRect();
        return wrapper.scrollHeight > wrapper.clientHeight && rect.top >= 10 && rect.bottom <= innerHeight - 10 && logout.top >= rect.top && logout.bottom <= rect.bottom;
    })()`), 'Short screens can scroll the sidebar to reach Log Out.');
    await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 1000, deviceScaleFactor: 1, mobile: false });
    for (const page of ['servicew.php', 'servicem.php', 'reportw.php', 'reportm.php']) {
        await navigate(page);
        const totals = await evaluate(`(() => {
            const form = document.getElementById('service-form');
            const inputs = Array.from(form.elements).filter(input => input.hasAttribute('data-service-amount'));
            const total = Array.from(form.elements).find(input => input.hasAttribute('data-service-total'));
            inputs.forEach(input => input.value = '0');
            inputs[0].value = '1.20';
            inputs[0].dispatchEvent(new Event('input', { bubbles: true }));
            return { total: total.value, count: inputs.length, associated: inputs.every(input => input.form === form), inside: inputs.every(input => form.contains(input)) };
        })()`);
        check(totals.associated && totals.count === (page.endsWith('w.php') ? 7 : 4), 'Browser associates every amount with its form.');
        check(totals.total === '1.20', 'Typing a decimal updates the browser total.');
    }
    for (const theme of ['light', 'dark']) {
        for (const panel of ['solid', 'transparent']) {
            await navigate('settings.php');
            check(await evaluate(`(() => {
                const theme = document.getElementById('theme');
                const group = document.getElementById('panel-background-group');
                theme.value = 'dark';
                theme.dispatchEvent(new Event('change', { bubbles: true }));
                const hidden = group.hidden;
                theme.value = 'light';
                theme.dispatchEvent(new Event('change', { bubbles: true }));
                return hidden && !group.hidden;
            })()`), 'Content background choice hides in Dark and returns in Light.');
            await submit(`document.getElementById('theme').value=${JSON.stringify(theme)}; document.getElementById('panel-background').value=${JSON.stringify(panel)}; document.getElementById('settings-form').requestSubmit(document.querySelector('button[value="save"]'));`);
            for (const page of ['main.php', 'servicew.php', 'servicem.php', 'reportw.php', 'reportm.php', 'restaurant.php', 'profile.php', 'my_restaurants.php', 'orders.php', 'help.php', 'settings.php']) {
                await navigate(page);
                const style = await evaluate(`(() => {
                    const panel = document.querySelector('#backgroundmain, .profile-content, .login-box');
                    const computed = getComputedStyle(panel);
                    const sidebar = getComputedStyle(document.getElementById('sidebutton'));
                    return { theme: document.documentElement.dataset.theme, panel: document.documentElement.dataset.panel, opacity: computed.opacity, background: computed.backgroundColor, text: computed.color, sidebar: sidebar.backgroundColor, sidebarOpacity: getComputedStyle(document.getElementById('sidebar')).opacity, buttonOpacities: Array.from(document.querySelectorAll('[id="sidebutton"]'), button => getComputedStyle(button).opacity) };
                })()`);
                check(style.theme === theme && style.panel === panel, 'Saved appearance in browser.');
                check(style.opacity === (theme === 'light' && panel === 'transparent' ? '0.5' : '1'), 'Original Light transparency and solid Dark panels.');
                check(style.background.startsWith('rgb('), 'Solid surface with transparency applied by the selected panel mode.');
                check(style.text === (theme === 'dark' ? 'rgb(237, 240, 245)' : 'rgb(17, 17, 17)'), 'Readable foreground follows theme.');
                check(style.sidebarOpacity === '1' && style.buttonOpacities.length === 7 && style.buttonOpacities.every(opacity => opacity === (theme === 'light' && panel === 'transparent' ? '0.5' : '1')), 'Only sidebar buttons fade in Light Transparent mode.');
                if (page === 'settings.php') {
                    check(await evaluate(`document.getElementById('panel-background-group').hidden === ${theme === 'dark'}`), 'Saved Dark mode hides Content background on load.');
                }
                if (page === 'main.php' || (page === 'settings.php' && theme === 'dark' && panel === 'solid')) {
                    const screenshot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                    await writeFile(join(screenshots, `${page.replace('.php', '')}-${theme}-${panel}.png`), Buffer.from(screenshot.data, 'base64'));
                }
            }
        }
    }
    await navigate('profile.php');
    await submit("document.querySelector('form[action=\"logout.php\"]').requestSubmit();");
    check(await evaluate("document.documentElement.dataset.theme === 'dark' && document.documentElement.dataset.panel === 'transparent' && getComputedStyle(document.querySelector('.login-box')).opacity === '1'"), 'Login retains browser appearance.');
    console.log(`Passed ${checks} browser appearance checks. Screenshots: ${screenshots}`);
} finally {
    await fetch(`http://127.0.0.1:9237/json/close/${target.id}`).catch(() => {});
    socket.close();
}
