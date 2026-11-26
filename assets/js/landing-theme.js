'use strict';
try {
    if (localStorage.getItem('smartcontrol-landing-theme') === 'dark') {
        document.documentElement.dataset.theme = 'dark';
    }
} catch (_) { /* O tema continua disponível quando o armazenamento está bloqueado. */ }
