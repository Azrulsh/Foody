'use strict';

(() => {
    const theme = document.getElementById('theme');
    const background = document.getElementById('panel-background-group');
    if (!theme || !background) return;
    const updateBackgroundChoice = () => {
        background.hidden = theme.value === 'dark';
    };
    theme.addEventListener('change', updateBackgroundChoice);
    updateBackgroundChoice();
})();
