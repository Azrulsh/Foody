import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../service.js', import.meta.url), 'utf8');
let checks = 0;
for (const count of [7, 4]) {
    const events = {};
    const inputs = Array.from({ length: count }, () => ({
        value: '0.00',
        hasAttribute: (name) => name === 'data-service-amount',
        addEventListener(name, handler) { this[name] = handler; },
        select() { this.selected = true; },
    }));
    const total = { value: '', hasAttribute: (name) => name === 'data-service-total' };
    const form = {
        elements: [...inputs, total],
        addEventListener: (name, handler) => { events[name] = handler; },
    };
    runInNewContext(source, {
        document: { querySelector: () => form },
        setTimeout: (handler) => handler(),
    });
    assert.equal(total.value, '0.00');
    checks++;
    inputs[0].value = '0';
    inputs[0].focus();
    assert.equal(inputs[0].selected, true);
    checks++;
    inputs[0].selected = false;
    inputs[0].click();
    assert.equal(inputs[0].selected, true);
    checks++;
    inputs[0].selected = false;
    inputs[0].value = '12';
    inputs[0].click();
    assert.equal(inputs[0].selected, false);
    checks++;
    inputs[0].value = '0.10';
    inputs[1].value = '0.20';
    events.input();
    assert.equal(total.value, '0.30');
    checks++;
    inputs[0].value = '1e2';
    events.input();
    assert.equal(total.value, '');
    checks++;
    inputs.forEach((input) => { input.value = '9999999.99'; });
    events.input();
    assert.equal(total.value, count === 7 ? '69999999.93' : '39999999.96');
    checks++;
    inputs.forEach((input) => { input.value = '0.00'; });
    events.reset();
    assert.equal(total.value, '0.00');
    checks++;
}
runInNewContext(source, { document: { querySelector: () => null } });
checks++;
console.log(`Passed ${checks} JavaScript checks.`);
