'use strict';

(() => {
    const form = document.querySelector('[data-commission-form]');
    if (!form) return;

    const inputs = ['commissions', 'penalties', 'services'].map((name) => form.elements.namedItem(name));
    const total = form.elements.namedItem('totals');
    const updateTotal = () => {
        const amounts = inputs.map((input) => {
            const match = /^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?$/.exec(input.value);
            return match ? Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')) : null;
        });
        if (amounts.includes(null)) {
            total.value = '';
            return;
        }
        const nett = amounts[0] - amounts[1] - amounts[2];
        const absolute = Math.abs(nett);
        total.value = `${nett < 0 ? '-' : ''}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
    };
    inputs.forEach((input) => {
        const selectZero = () => {
            if (input.value === '0') input.select();
        };
        input.addEventListener('focus', selectZero);
        input.addEventListener('click', selectZero);
        input.addEventListener('input', updateTotal);
    });
    form.addEventListener('reset', () => setTimeout(updateTotal, 0));
    updateTotal();
})();
