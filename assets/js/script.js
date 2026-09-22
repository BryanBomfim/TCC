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
        configurarConsultasFornecedor();
        configurarCopiarCampos();
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

    function configurarCopiarCampos() {
        document.querySelectorAll('[data-copy-target]').forEach(function (botao) {
            botao.addEventListener('click', async function () {
                var alvo = document.getElementById(botao.getAttribute('data-copy-target'));
                if (!alvo) return;

                try {
                    await navigator.clipboard.writeText(alvo.value || alvo.textContent || '');
                    var textoOriginal = botao.textContent;
                    botao.textContent = 'Copiado';
                    window.setTimeout(function () { botao.textContent = textoOriginal; }, 1400);
                } catch (erro) {
                    alvo.select();
                    document.execCommand('copy');
                }
            });
        });
    }

    function configurarConsultasFornecedor() {
        var formulario = document.querySelector('[data-supplier-form]');
        if (!formulario) return;

        var baseUrl = document.body.getAttribute('data-base-url') || '';
        var status = formulario.querySelector('[data-supplier-status]');
        var botaoCep = formulario.querySelector('[data-consultar-cep]');
        var botaoCnpj = formulario.querySelector('[data-consultar-cnpj]');

        function campo(nome) {
            return formulario.querySelector('[name="' + nome + '"]');
        }

        function preencher(nome, valor, sobrescrever) {
            var elemento = campo(nome);
            if (!elemento || valor === null || typeof valor === 'undefined' || valor === '') return;
            if (sobrescrever || !elemento.value.trim()) elemento.value = valor;
        }

        async function consultar(url, botao) {
            var textoOriginal = botao.textContent;
            botao.disabled = true;
            botao.textContent = 'Consultando...';
            if (status) status.textContent = '';

            try {
                var resposta = await fetch(url, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                var json = await resposta.json();
                if (!resposta.ok || !json.success) throw new Error(json.message || 'Consulta indisponível.');
                return json.data;
            } catch (erro) {
                if (status) status.textContent = erro.message + ' Você pode preencher os dados manualmente.';
                return null;
            } finally {
                botao.disabled = false;
                botao.textContent = textoOriginal;
            }
        }

        if (botaoCep) {
            botaoCep.addEventListener('click', async function () {
                var cep = (campo('cep').value || '').replace(/\D/g, '');
                if (cep.length !== 8) {
                    if (status) status.textContent = 'Informe um CEP com 8 dígitos.';
                    return;
                }
                var dados = await consultar(baseUrl + '/api/v1/external/cep.php?cep=' + encodeURIComponent(cep), botaoCep);
                if (!dados) return;
                preencher('cep', dados.cep, true);
                preencher('logradouro', dados.logradouro, true);
                preencher('complemento', dados.complemento, false);
                preencher('bairro', dados.bairro, true);
                preencher('cidade', dados.cidade, true);
                preencher('uf', dados.uf, true);
                if (status) status.textContent = 'Endereço preenchido pela ' + dados.fonte + '.';
                var numero = campo('numero');
                if (numero) numero.focus();
            });
        }

        if (botaoCnpj) {
            botaoCnpj.addEventListener('click', async function () {
                var cnpj = (campo('cnpj_cpf').value || '').replace(/\D/g, '');
                if (cnpj.length !== 14) {
                    if (status) status.textContent = 'Para consulta automática, informe um CNPJ com 14 dígitos.';
                    return;
                }
                var dados = await consultar(baseUrl + '/api/v1/external/cnpj.php?cnpj=' + encodeURIComponent(cnpj), botaoCnpj);
                if (!dados) return;
                preencher('nome', dados.razao_social || dados.nome_fantasia, false);
                preencher('telefone', dados.telefone, false);
                preencher('email', dados.email, false);
                preencher('cep', dados.cep, false);
                preencher('logradouro', dados.logradouro, false);
                preencher('numero', dados.numero, false);
                preencher('complemento', dados.complemento, false);
                preencher('bairro', dados.bairro, false);
                preencher('cidade', dados.cidade, false);
                preencher('uf', dados.uf, false);
                if (status) status.textContent = 'Dados empresariais preenchidos pela BrasilAPI.';
            });
        }
    }

}());
