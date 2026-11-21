'use strict';

(() => {
    const sidebar = document.getElementById('sidebar');
    const sticky = sidebar?.closest('.sidebar-sticky');
    if (!sticky) return;

    // Measure the actual height so wrapped names and font changes stay centered.
    const updateHeight = () => {
        sticky.parentElement.style.setProperty('--sidebar-height', `${sidebar.offsetHeight}px`);
    };
    updateHeight();
    if ('ResizeObserver' in window) {
        new ResizeObserver(updateHeight).observe(sidebar);
    } else {
        window.addEventListener('resize', updateHeight);
    }
})();
