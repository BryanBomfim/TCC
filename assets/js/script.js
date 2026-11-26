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
        configurarTabelasResponsivas();
        configurarLeitorCodigoProduto();
        configurarConfirmacoes();
        configurarCopiarCampos();
        configurarConsultaFornecedor();
        configurarImpressao();
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

    function configurarTabelasResponsivas() {
        document.querySelectorAll('table.tabela').forEach(function (tabela) {
            if (tabela.parentElement && tabela.parentElement.classList.contains('tabela-responsiva')) {
                return;
            }

            var contenedor = document.createElement('div');
            contenedor.className = 'tabela-responsiva';
            contenedor.setAttribute('tabindex', '0');
            contenedor.setAttribute('role', 'region');
            contenedor.setAttribute('aria-label', 'Tabela com rolagem horizontal em telas pequenas');
            tabela.parentNode.insertBefore(contenedor, tabela);
            contenedor.appendChild(tabela);
        });
    }

    /**
     * Leitores USB normalmente funcionam como teclado e finalizam a leitura
     * com Enter. O mesmo campo também aceita digitação manual.
     */
    function configurarLeitorCodigoProduto() {
        var campoCodigo = document.querySelector('[data-product-code]');
        var seletorProduto = document.querySelector('[data-product-select]');
        var feedback = document.querySelector('[data-product-code-feedback]');

        if (!campoCodigo || !seletorProduto) {
            return;
        }

        campoCodigo.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            var codigo = campoCodigo.value.trim().toLowerCase();
            var opcaoEncontrada = null;

            Array.prototype.forEach.call(seletorProduto.options, function (opcao) {
                var codigoOpcao = (opcao.getAttribute('data-code') || '').trim().toLowerCase();
                var skuOpcao = (opcao.getAttribute('data-sku') || '').trim().toLowerCase();
                if (codigo !== '' && (codigoOpcao === codigo || skuOpcao === codigo)) {
                    opcaoEncontrada = opcao;
                }
            });

            if (!opcaoEncontrada) {
                if (feedback) {
                    feedback.textContent = 'Nenhum produto ativo foi encontrado com esse código.';
                }
                return;
            }

            seletorProduto.value = opcaoEncontrada.value;
            seletorProduto.dispatchEvent(new Event('change', { bubbles: true }));
            campoCodigo.value = '';

            if (feedback) {
                feedback.textContent = 'Produto selecionado: ' + opcaoEncontrada.text.trim() + '.';
            }

            var campoQuantidade = document.getElementById('quantidade');
            if (campoQuantidade) {
                campoQuantidade.focus();
            }
        });
    }

    function configurarConfirmacoes() {
        var elementos = document.querySelectorAll('[data-confirm]');

        elementos.forEach(function (elemento) {
            var eventoConfirmacao = elemento.tagName === 'FORM' ? 'submit' : 'click';
            elemento.addEventListener(eventoConfirmacao, function (event) {
                var mensagem = elemento.getAttribute('data-confirm');
                if (mensagem && !window.confirm(mensagem)) {
                    event.preventDefault();
                }
            });
        });
    }

    function configurarImpressao() {
        document.querySelectorAll('[data-print]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                window.print();
            });
        });
    }

    function configurarCopiarCampos() {
        document.querySelectorAll('[data-copy-target]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var campo = document.getElementById(botao.getAttribute('data-copy-target'));
                if (!campo) {
                    return;
                }

                campo.select();
                campo.setSelectionRange(0, campo.value.length);

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(campo.value).then(function () {
                        botao.textContent = 'Copiado';
                    });
                } else {
                    document.execCommand('copy');
                    botao.textContent = 'Copiado';
                }
            });
        });
    }

    function configurarConsultaFornecedor() {
        var formulario = document.querySelector('[data-supplier-form]');
        if (!formulario) {
            return;
        }

        var csrf = document.querySelector('meta[name="csrf-token"]');
        var baseUrl = document.querySelector('meta[name="app-base-url"]');
        var feedback = document.querySelector('[data-lookup-feedback]');

        function preencher(dados) {
            Object.keys(dados).forEach(function (campo) {
                if (campo === 'fonte' || dados[campo] === '') {
                    return;
                }
                var input = document.getElementById(campo);
                if (input) {
                    input.value = dados[campo];
                }
            });
        }

        function consultar(acao, campo, botao) {
            var valor = campo.value.trim();
            if (!valor) {
                feedback.textContent = 'Informe o valor antes de consultar.';
                campo.focus();
                return;
            }

            var textoOriginal = botao.textContent;
            botao.disabled = true;
            botao.textContent = 'Consultando...';
            feedback.textContent = 'Consultando serviço externo...';

            fetch((baseUrl ? baseUrl.content : '') + '/integrations/lookup.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrf ? csrf.content : ''
                },
                body: JSON.stringify({ action: acao, value: valor })
            })
                .then(function (resposta) {
                    return resposta.json().then(function (corpo) {
                        return { ok: resposta.ok, corpo: corpo };
                    });
                })
                .then(function (resultado) {
                    if (!resultado.ok || !resultado.corpo.success) {
                        throw new Error(resultado.corpo.message || 'Consulta indisponível.');
                    }
                    preencher(resultado.corpo.data);
                    feedback.textContent = resultado.corpo.message + ' Revise os campos antes de salvar.';
                })
                .catch(function (erro) {
                    feedback.textContent = erro.message + ' Você pode continuar preenchendo manualmente.';
                })
                .finally(function () {
                    botao.disabled = false;
                    botao.textContent = textoOriginal;
                });
        }

        var botaoCep = document.querySelector('[data-lookup-cep]');
        var botaoCnpj = document.querySelector('[data-lookup-cnpj]');
        if (botaoCep) {
            botaoCep.addEventListener('click', function () {
                consultar('cep', document.getElementById('cep'), botaoCep);
            });
        }
        if (botaoCnpj) {
            botaoCnpj.addEventListener('click', function () {
                consultar('cnpj', document.getElementById('cnpj_cpf'), botaoCnpj);
            });
        }
    }

    function configurarGraficoSemana() {
        var canvas = document.getElementById('graficoSemana');

        if (!canvas) {
            return;
        }

        var labelsSemana;
        var entradasSemana;
        var saidasSemana;

        try {
            labelsSemana = JSON.parse(canvas.getAttribute('data-labels') || '[]');
            entradasSemana = JSON.parse(canvas.getAttribute('data-entradas') || '[]');
            saidasSemana = JSON.parse(canvas.getAttribute('data-saidas') || '[]');
        } catch (erro) {
            return;
        }

        function redesenhar() {
            desenharGraficoBarras(
                canvas,
                labelsSemana,
                entradasSemana,
                saidasSemana
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

// Modal nativo: contém o foco, fecha com Escape e devolve o foco ao botão.
document.querySelectorAll('[data-audit-open]').forEach(function (button) {
    var dialog = document.getElementById(button.dataset.auditOpen);
    if (!dialog) return;
    button.addEventListener('click', function () {
        dialog.showModal();
        document.body.classList.add('auditoria-modal-aberto');
    });
    dialog.querySelector('[data-audit-close]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('close', function () {
        document.body.classList.remove('auditoria-modal-aberto');
        button.focus();
    });
});
