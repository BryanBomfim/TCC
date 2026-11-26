'use strict';
const menu = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navigation');
if (menu && navigation) {
    menu.hidden = false;
    document.documentElement.classList.add('has-js');
    const close = () => { menu.setAttribute('aria-expanded', 'false'); navigation.classList.remove('is-open'); };
    menu.addEventListener('click', () => {
        const open = menu.getAttribute('aria-expanded') !== 'true';
        menu.setAttribute('aria-expanded', String(open));
        navigation.classList.toggle('is-open', open);
    });
    navigation.addEventListener('click', e => { if (e.target.closest('a')) close(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && menu.getAttribute('aria-expanded') === 'true') { close(); menu.focus(); }
    });
}

const themeToggle = document.querySelector('.theme-toggle');
if (themeToggle) {
    const updateThemeButton = () => {
        const dark = document.documentElement.dataset.theme === 'dark';
        themeToggle.textContent = dark ? 'Tema claro' : 'Tema escuro';
        themeToggle.setAttribute('aria-pressed', String(dark));
        document.querySelector('meta[name="theme-color"]').content = dark ? '#010b21' : '#ffffff';
    };
    themeToggle.hidden = false;
    updateThemeButton();
    themeToggle.addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = theme;
        try { localStorage.setItem('smartcontrol-landing-theme', theme); } catch (_) {}
        updateThemeButton();
    });
}
