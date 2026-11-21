import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const inputs = {};
for (const name of ['commissions', 'penalties', 'services', 'totals']) {
    inputs[name] = {
        value: '0', events: {}, selected: false,
        addEventListener(event, handler) { this.events[event] = handler; },
        select() { this.selected = true; },
    };
}
const events = {};
const form = {
    elements: { namedItem: (name) => inputs[name] },
    addEventListener: (event, handler) => { events[event] = handler; },
};
const source = readFileSync(new URL('../commission.js', import.meta.url), 'utf8');
runInNewContext(source, { document: { querySelector: () => form }, setTimeout: (handler) => handler() });
let checks = 0;
function total(gross, penalties, delivery, expected) {
    [inputs.commissions.value, inputs.penalties.value, inputs.services.value] = [gross, penalties, delivery];
    inputs.commissions.events.input();
    assert.equal(inputs.totals.value, expected);
    checks++;
}
total('100', '10', '5', '85.00');
total('0.30', '0.10', '0.20', '0.00');
total('0', '0.10', '0.20', '-0.30');
total('9999999.99', '0', '0', '9999999.99');
total('0', '9999999.99', '9999999.99', '-19999999.98');
total('1e2', '0', '0', '');
total('1.001', '0', '0', '');
total('0', '0', '0', '0.00');
inputs.commissions.events.focus();
assert.equal(inputs.commissions.selected, true);
checks++;
inputs.commissions.value = '5';
events.reset();
assert.equal(inputs.totals.value, '5.00');
checks++;
runInNewContext(source, { document: { querySelector: () => null } });
console.log(`Passed ${checks} commission JavaScript checks.`);
