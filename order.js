'use strict';

const orderAmount = document.getElementById('order-total');
if (orderAmount) {
    const selectZero = () => {
        if (orderAmount.value === '0') orderAmount.select();
    };
    orderAmount.addEventListener('focus', selectZero);
    orderAmount.addEventListener('click', selectZero);
}
