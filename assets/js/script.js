/**
 * assets/js/script.js
 * JavaScript geral do sistema, escrito em JS puro (sem bibliotecas).
 * Responsável por:
 *   1. Abrir/fechar o menu lateral em telas pequenas.
 *   2. Desenhar o gráfico de movimentações da semana no dashboard,
 *      usando a API nativa de Canvas (sem Chart.js ou similares).
 */

document.addEventListener('DOMContentLoaded', function () {

    // ---------- 1. Menu lateral (responsivo) ----------
    var botaoMenu = document.getElementById('botaoMenu');
    var sidebar = document.getElementById('sidebar');

    if (botaoMenu && sidebar) {
        botaoMenu.addEventListener('click', function () {
            sidebar.classList.toggle('aberta');
        });
    }

    // ---------- 2. Gráfico de movimentações (Canvas puro) ----------
    var canvas = document.getElementById('graficoSemana');

    if (canvas && typeof labelsSemana !== 'undefined') {
        desenharGraficoBarras(canvas, labelsSemana, entradasSemana, saidasSemana);
    }
});

/**
 * Desenha um gráfico de barras agrupadas (entradas x saídas) em um
 * elemento <canvas>, usando apenas a API 2D nativa do navegador.
 */
function desenharGraficoBarras(canvas, labels, serieEntradas, serieSaidas) {
    var ctx = canvas.getContext('2d');

    // Ajusta a resolução do canvas ao tamanho real exibido em tela
    var largura = canvas.clientWidth || 400;
    var altura = canvas.clientHeight || 220;
    canvas.width = largura;
    canvas.height = altura;

    var margemEsquerda = 35;
    var margemInferior = 30;
    var margemSuperior = 20;
    var margemDireita = 10;

    var areaLargura = largura - margemEsquerda - margemDireita;
    var areaAltura = altura - margemSuperior - margemInferior;

    var valorMaximo = Math.max(1, Math.max.apply(null, serieEntradas.concat(serieSaidas)));

    ctx.clearRect(0, 0, largura, altura);

    // Eixos
    ctx.strokeStyle = '#ccc';
    ctx.beginPath();
    ctx.moveTo(margemEsquerda, margemSuperior);
    ctx.lineTo(margemEsquerda, altura - margemInferior);
    ctx.lineTo(largura - margemDireita, altura - margemInferior);
    ctx.stroke();

    // Linhas guia horizontais + valores do eixo Y
    ctx.fillStyle = '#999';
    ctx.font = '10px Arial';
    ctx.textAlign = 'right';
    var passos = 4;
    for (var i = 0; i <= passos; i++) {
        var valor = Math.round((valorMaximo / passos) * i);
        var y = altura - margemInferior - (areaAltura * (i / passos));
        ctx.fillText(valor, margemEsquerda - 6, y + 3);
        ctx.strokeStyle = '#eee';
        ctx.beginPath();
        ctx.moveTo(margemEsquerda, y);
        ctx.lineTo(largura - margemDireita, y);
        ctx.stroke();
    }

    // Barras (entrada e saída lado a lado, para cada dia)
    var totalDias = labels.length;
    var larguraGrupo = areaLargura / totalDias;
    var larguraBarra = larguraGrupo * 0.32;

    ctx.textAlign = 'center';

    for (var d = 0; d < totalDias; d++) {
        var xGrupo = margemEsquerda + (d * larguraGrupo) + (larguraGrupo / 2);

        var alturaEntrada = (serieEntradas[d] / valorMaximo) * areaAltura;
        var alturaSaida = (serieSaidas[d] / valorMaximo) * areaAltura;

        // Barra de entradas (verde)
        ctx.fillStyle = '#2e7d32';
        ctx.fillRect(
            xGrupo - larguraBarra - 2,
            altura - margemInferior - alturaEntrada,
            larguraBarra,
            alturaEntrada
        );

        // Barra de saídas (vermelho)
        ctx.fillStyle = '#b71c1c';
        ctx.fillRect(
            xGrupo + 2,
            altura - margemInferior - alturaSaida,
            larguraBarra,
            alturaSaida
        );

        // Rótulo do dia (eixo X)
        ctx.fillStyle = '#666';
        ctx.fillText(labels[d], xGrupo, altura - margemInferior + 14);
    }

    // Legenda
    ctx.fillStyle = '#2e7d32';
    ctx.fillRect(margemEsquerda, 4, 10, 10);
    ctx.fillStyle = '#333';
    ctx.textAlign = 'left';
    ctx.fillText('Entradas', margemEsquerda + 14, 13);

    ctx.fillStyle = '#b71c1c';
    ctx.fillRect(margemEsquerda + 80, 4, 10, 10);
    ctx.fillStyle = '#333';
    ctx.fillText('Saídas', margemEsquerda + 94, 13);
}
