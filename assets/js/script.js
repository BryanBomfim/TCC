/**
 * SmartControl | JavaScript global
 * ---------------------------------------------------------------
 * 1. Menu lateral responsivo.
 * 2. Mostrar/ocultar senhas.
 * 3. Gráfico semanal em Canvas puro.
 * ---------------------------------------------------------------
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        configurarMenuLateral();
        configurarCamposSenha();
        configurarGraficoSemana();
    });

    function configurarMenuLateral() {
        var botaoMenu = document.getElementById('botaoMenu');
        var sidebar = document.getElementById('sidebar');

        if (!botaoMenu || !sidebar) {
            return;
        }

        function definirMenuAberto(aberto) {
            sidebar.classList.toggle('aberta', aberto);
            botaoMenu.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            botaoMenu.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
        }

        botaoMenu.addEventListener('click', function () {
            definirMenuAberto(!sidebar.classList.contains('aberta'));
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('aberta')) {
                definirMenuAberto(false);
                botaoMenu.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth > 900 || !sidebar.classList.contains('aberta')) {
                return;
            }

            if (!sidebar.contains(event.target) && !botaoMenu.contains(event.target)) {
                definirMenuAberto(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) {
                definirMenuAberto(false);
            }
        });
    }

    function configurarCamposSenha() {
        var botoesSenha = document.querySelectorAll('.botao-mostrar-senha[data-target]');

        botoesSenha.forEach(function (botao) {
            botao.addEventListener('click', function () {
                var campoId = botao.getAttribute('data-target');
                var campoSenha = document.getElementById(campoId);

                if (!campoSenha) {
                    return;
                }

                var estavaVisivel = campoSenha.type === 'text';
                campoSenha.type = estavaVisivel ? 'password' : 'text';
                botao.textContent = estavaVisivel ? 'Mostrar' : 'Ocultar';
                botao.setAttribute('aria-pressed', estavaVisivel ? 'false' : 'true');
            });
        });
    }

    function configurarGraficoSemana() {
        var canvas = document.getElementById('graficoSemana');

        if (
            !canvas
            || typeof window.labelsSemana === 'undefined'
            || typeof window.entradasSemana === 'undefined'
            || typeof window.saidasSemana === 'undefined'
        ) {
            return;
        }

        function redesenhar() {
            desenharGraficoBarras(
                canvas,
                window.labelsSemana,
                window.entradasSemana,
                window.saidasSemana
            );
        }

        redesenhar();

        var timeoutResize = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(timeoutResize);
            timeoutResize = window.setTimeout(redesenhar, 120);
        });
    }

    /**
     * Desenha barras agrupadas de entradas e saídas usando Canvas 2D.
     */
    function desenharGraficoBarras(canvas, labels, serieEntradas, serieSaidas) {
        var ctx = canvas.getContext('2d');

        if (!ctx || !labels.length) {
            return;
        }

        var largura = Math.max(320, Math.floor(canvas.clientWidth || 400));
        var altura = 240;
        var escala = window.devicePixelRatio || 1;

        canvas.width = Math.floor(largura * escala);
        canvas.height = Math.floor(altura * escala);
        canvas.style.height = altura + 'px';
        ctx.setTransform(escala, 0, 0, escala, 0, 0);

        var margemEsquerda = 40;
        var margemInferior = 34;
        var margemSuperior = 28;
        var margemDireita = 12;
        var areaLargura = largura - margemEsquerda - margemDireita;
        var areaAltura = altura - margemSuperior - margemInferior;
        var combinada = serieEntradas.concat(serieSaidas);
        var valorMaximo = Math.max(1, Math.max.apply(null, combinada));
        var passos = 4;

        ctx.clearRect(0, 0, largura, altura);
        ctx.font = '11px Arial';

        for (var i = 0; i <= passos; i += 1) {
            var valor = Math.round((valorMaximo / passos) * i);
            var y = altura - margemInferior - (areaAltura * (i / passos));

            ctx.fillStyle = '#667085';
            ctx.textAlign = 'right';
            ctx.fillText(String(valor), margemEsquerda - 7, y + 4);

            ctx.strokeStyle = '#e4e9ef';
            ctx.beginPath();
            ctx.moveTo(margemEsquerda, y);
            ctx.lineTo(largura - margemDireita, y);
            ctx.stroke();
        }

        var totalDias = labels.length;
        var larguraGrupo = areaLargura / totalDias;
        var larguraBarra = Math.max(8, larguraGrupo * 0.28);

        for (var d = 0; d < totalDias; d += 1) {
            var xGrupo = margemEsquerda + (d * larguraGrupo) + (larguraGrupo / 2);
            var alturaEntrada = (serieEntradas[d] / valorMaximo) * areaAltura;
            var alturaSaida = (serieSaidas[d] / valorMaximo) * areaAltura;

            ctx.fillStyle = '#237a3b';
            ctx.fillRect(
                xGrupo - larguraBarra - 2,
                altura - margemInferior - alturaEntrada,
                larguraBarra,
                alturaEntrada
            );

            ctx.fillStyle = '#b42318';
            ctx.fillRect(
                xGrupo + 2,
                altura - margemInferior - alturaSaida,
                larguraBarra,
                alturaSaida
            );

            ctx.fillStyle = '#475467';
            ctx.textAlign = 'center';
            ctx.fillText(labels[d], xGrupo, altura - 12);
        }

        desenharLegenda(ctx, margemEsquerda, 6, '#237a3b', 'Entradas');
        desenharLegenda(ctx, margemEsquerda + 92, 6, '#b42318', 'Saídas');
    }

    function desenharLegenda(ctx, x, y, cor, texto) {
        ctx.fillStyle = cor;
        ctx.fillRect(x, y, 11, 11);
        ctx.fillStyle = '#344054';
        ctx.textAlign = 'left';
        ctx.fillText(texto, x + 16, y + 10);
    }
}());
