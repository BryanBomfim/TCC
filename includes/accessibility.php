<?php
/**
 * includes/accessibility.php
 * -----------------------------------------------------------------
 * Barra global de acessibilidade do SmartControl.
 * Pode ser incluída tanto nas páginas autenticadas quanto nas telas
 * públicas de autenticação.
 * -----------------------------------------------------------------
 */

if (!defined('SMARTCONTROL_ACCESSIBILITY_RENDERED')) {
    define('SMARTCONTROL_ACCESSIBILITY_RENDERED', true);
    ?>
    <aside class="acessibilidade" data-a11y-widget aria-label="Ferramentas de acessibilidade">
        <button
            class="acessibilidade-toggle"
            type="button"
            data-a11y-toggle
            aria-controls="painel-acessibilidade"
            aria-expanded="false"
            aria-keyshortcuts="Alt+A"
        >
            <span aria-hidden="true">♿</span>
            <span>Acessibilidade</span>
        </button>

        <section
            class="acessibilidade-painel"
            id="painel-acessibilidade"
            data-a11y-panel
            aria-labelledby="titulo-acessibilidade"
            hidden
        >
            <div class="acessibilidade-cabecalho">
                <h2 id="titulo-acessibilidade">Acessibilidade</h2>
                <button
                    class="acessibilidade-fechar"
                    type="button"
                    data-a11y-close
                    aria-label="Fechar ferramentas de acessibilidade"
                >
                    ×
                </button>
            </div>

            <div class="acessibilidade-grupo" role="group" aria-label="Tamanho do texto">
                <span class="acessibilidade-rotulo">Tamanho do texto</span>
                <div class="acessibilidade-acoes">
                    <button type="button" data-a11y-action="decrease-font" aria-label="Diminuir tamanho do texto">A−</button>
                    <button type="button" data-a11y-action="reset-font" aria-label="Restaurar tamanho do texto">A</button>
                    <button type="button" data-a11y-action="increase-font" aria-label="Aumentar tamanho do texto">A+</button>
                </div>
            </div>

            <div class="acessibilidade-grupo">
                <button type="button" class="acessibilidade-opcao" data-a11y-action="contrast" aria-pressed="false">
                    Alto contraste
                </button>

                <button type="button" class="acessibilidade-opcao" data-a11y-action="links" aria-pressed="false">
                    Destacar links
                </button>
            </div>

            <div class="acessibilidade-grupo" role="group" aria-label="Leitura em voz alta">
                <span class="acessibilidade-rotulo">Leitura em voz alta</span>
                <div class="acessibilidade-acoes acessibilidade-acoes-leitura">
                    <button type="button" data-a11y-action="read">Ler página</button>
                    <button type="button" data-a11y-action="pause">Pausar</button>
                    <button type="button" data-a11y-action="stop">Parar</button>
                </div>
            </div>

            <button type="button" class="acessibilidade-restaurar" data-a11y-action="reset-all">
                Restaurar preferências
            </button>

            <p class="acessibilidade-ajuda">
                Atalho: <kbd>Alt</kbd> + <kbd>A</kbd> abre este painel.
            </p>

            <p class="sr-only" id="status-acessibilidade" data-a11y-status aria-live="polite"></p>
        </section>
    </aside>
    <?php
}
