'use strict';

(() => {
    const form = document.querySelector('[data-service-form]');
    if (!form) {
        return;
    }

    const inputs = Array.from(form.elements).filter((input) => input.hasAttribute('data-service-amount'));
    const total = Array.from(form.elements).find((input) => input.hasAttribute('data-service-total'));
    inputs.forEach((input) => {
        const selectZero = () => {
            if (input.value === '0') {
                input.select();
            }
        };
        input.addEventListener('focus', selectZero);
        input.addEventListener('click', selectZero);
    });
    const updateTotal = () => {
        let cents = 0;
        for (const input of inputs) {
            const match = /^(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,2}))?$/.exec(input.value);
            if (!match) {
                total.value = '';
                return;
            }
            cents += Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'));
        }
        total.value = `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
    };

    form.addEventListener('input', updateTotal);
    form.addEventListener('reset', () => setTimeout(updateTotal, 0));
    updateTotal();
})();
