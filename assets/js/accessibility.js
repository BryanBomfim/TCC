/**
 * SmartControl | Acessibilidade
 * ---------------------------------------------------------------
 * Preferências persistentes, contraste, tamanho do texto, destaque
 * de links e leitura do conteúdo com a API Web Speech do navegador.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    var STORAGE_KEY = 'smartcontrol_accessibility';
    var DEFAULTS = {
        fontScale: 1,
        highContrast: false,
        highlightLinks: false
    };

    var state = loadState();
    var currentUtterance = null;

    document.addEventListener('DOMContentLoaded', function () {
        var widget = document.querySelector('[data-a11y-widget]');

        applyState();
        prepareMainContent();

        if (!widget) {
            return;
        }

        var toggle = widget.querySelector('[data-a11y-toggle]');
        var panel = widget.querySelector('[data-a11y-panel]');
        var close = widget.querySelector('[data-a11y-close]');
        var status = widget.querySelector('[data-a11y-status]');

        syncPressedStates(widget);

        if (toggle && panel) {
            toggle.addEventListener('click', function () {
                setPanelOpen(panel.hidden, toggle, panel);
            });
        }

        if (close && toggle && panel) {
            close.addEventListener('click', function () {
                setPanelOpen(false, toggle, panel);
                toggle.focus();
            });
        }

        widget.addEventListener('click', function (event) {
            var button = event.target.closest('[data-a11y-action]');

            if (!button) {
                return;
            }

            handleAction(button.getAttribute('data-a11y-action'), widget, status);
        });

        document.addEventListener('keydown', function (event) {
            if (event.altKey && event.key.toLowerCase() === 'a' && toggle && panel) {
                event.preventDefault();
                setPanelOpen(panel.hidden, toggle, panel);
                return;
            }

            if (event.key === 'Escape' && toggle && panel && !panel.hidden) {
                setPanelOpen(false, toggle, panel);
                toggle.focus();
            }
        });
    });

    function prepareMainContent() {
        var mainContent = document.querySelector('.conteudo');

        if (mainContent && !mainContent.id) {
            mainContent.id = 'conteudo-principal';
        }
    }

    function handleAction(action, widget, status) {
        switch (action) {
            case 'increase-font':
                state.fontScale = clamp(roundScale(state.fontScale + 0.125), 0.875, 1.5);
                saveAndApply();
                announce(status, 'Texto aumentado para ' + Math.round(state.fontScale * 100) + '%.');
                break;

            case 'decrease-font':
                state.fontScale = clamp(roundScale(state.fontScale - 0.125), 0.875, 1.5);
                saveAndApply();
                announce(status, 'Texto reduzido para ' + Math.round(state.fontScale * 100) + '%.');
                break;

            case 'reset-font':
                state.fontScale = 1;
                saveAndApply();
                announce(status, 'Tamanho do texto restaurado.');
                break;

            case 'contrast':
                state.highContrast = !state.highContrast;
                saveAndApply();
                syncPressedStates(widget);
                announce(status, state.highContrast ? 'Alto contraste ativado.' : 'Alto contraste desativado.');
                break;

            case 'links':
                state.highlightLinks = !state.highlightLinks;
                saveAndApply();
                syncPressedStates(widget);
                announce(status, state.highlightLinks ? 'Destaque de links ativado.' : 'Destaque de links desativado.');
                break;

            case 'read':
                readPage(status);
                break;

            case 'pause':
                toggleSpeechPause(status);
                break;

            case 'stop':
                stopSpeech(status);
                break;

            case 'reset-all':
                state = Object.assign({}, DEFAULTS);
                saveAndApply();
                syncPressedStates(widget);
                stopSpeech(status);
                announce(status, 'Preferências de acessibilidade restauradas.');
                break;
        }
    }

    function readPage(status) {
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') {
            announce(status, 'A leitura em voz alta não é suportada por este navegador.');
            return;
        }

        var source = document.querySelector('.conteudo, .caixa-login, main') || document.body;
        var clone = source.cloneNode(true);
        var ignored = clone.querySelectorAll('script, style, form, button, [aria-hidden="true"], .sr-only, .acessibilidade');

        ignored.forEach(function (element) {
            element.remove();
        });

        var text = clone.innerText.replace(/\s+/g, ' ').trim();

        if (!text) {
            announce(status, 'Não há conteúdo de texto disponível para leitura.');
            return;
        }

        window.speechSynthesis.cancel();

        currentUtterance = new SpeechSynthesisUtterance(text);
        currentUtterance.lang = 'pt-BR';
        currentUtterance.rate = 1;
        currentUtterance.pitch = 1;

        currentUtterance.onend = function () {
            announce(status, 'Leitura concluída.');
            currentUtterance = null;
        };

        currentUtterance.onerror = function () {
            announce(status, 'A leitura em voz alta foi interrompida.');
            currentUtterance = null;
        };

        window.speechSynthesis.speak(currentUtterance);
        announce(status, 'Leitura em voz alta iniciada.');
    }

    function toggleSpeechPause(status) {
        if (!('speechSynthesis' in window)) {
            announce(status, 'A leitura em voz alta não é suportada por este navegador.');
            return;
        }

        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
            announce(status, 'Leitura retomada.');
            return;
        }

        if (window.speechSynthesis.speaking) {
            window.speechSynthesis.pause();
            announce(status, 'Leitura pausada.');
            return;
        }

        announce(status, 'Nenhuma leitura está em andamento.');
    }

    function stopSpeech(status) {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }

        currentUtterance = null;
        announce(status, 'Leitura parada.');
    }

    function setPanelOpen(open, toggle, panel) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            var firstControl = panel.querySelector('button');
            if (firstControl) {
                firstControl.focus();
            }
        }
    }

    function syncPressedStates(widget) {
        var contrast = widget.querySelector('[data-a11y-action="contrast"]');
        var links = widget.querySelector('[data-a11y-action="links"]');

        if (contrast) {
            contrast.setAttribute('aria-pressed', state.highContrast ? 'true' : 'false');
        }

        if (links) {
            links.setAttribute('aria-pressed', state.highlightLinks ? 'true' : 'false');
        }
    }

    function applyState() {
        document.documentElement.style.fontSize = (16 * state.fontScale) + 'px';
        document.body.classList.toggle('a11y-high-contrast', state.highContrast);
        document.body.classList.toggle('a11y-highlight-links', state.highlightLinks);
    }

    function saveAndApply() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (error) {
            // O recurso continua funcionando durante a sessão mesmo sem localStorage.
        }

        applyState();
    }

    function loadState() {
        try {
            var stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');

            return {
                fontScale: clamp(Number(stored.fontScale) || DEFAULTS.fontScale, 0.875, 1.5),
                highContrast: stored.highContrast === true,
                highlightLinks: stored.highlightLinks === true
            };
        } catch (error) {
            return Object.assign({}, DEFAULTS);
        }
    }

    function announce(element, message) {
        if (!element) {
            return;
        }

        element.textContent = '';
        window.setTimeout(function () {
            element.textContent = message;
        }, 20);
    }

    function roundScale(value) {
        return Math.round(value * 1000) / 1000;
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }
}());
