<?php
/**
 * notifications/index.php
 * -----------------------------------------------------------------
 * Central de Notificações do SmartControl.
 *
 * Cada usuário visualiza somente suas próprias notificações.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirLogin();

$usuarioId = (int) $_SESSION['usuario_id'];

/*
 * ================================================================
 * FILTRO
 * ================================================================
 */

$filtro = trim(
    $_GET['filtro']
    ?? 'todas'
);

$filtrosPermitidos = [
    'todas',
    'nao_lidas',
    'lidas'
];

if (
    !in_array(
        $filtro,
        $filtrosPermitidos,
        true
    )
) {

    $filtro = 'todas';
}

/*
 * ================================================================
 * CONSULTA DAS NOTIFICAÇÕES
 * ================================================================
 */

$sql = "
    SELECT
        id,
        tipo,
        titulo,
        mensagem,
        entidade,
        entidade_id,
        link,
        lida_em,
        criado_em

    FROM notificacoes

    WHERE usuario_id = :usuario_id
";

/*
 * Somente não lidas.
 */
if ($filtro === 'nao_lidas') {

    $sql .= "
        AND lida_em IS NULL
    ";

    /*
     * Somente lidas.
     */
} elseif ($filtro === 'lidas') {

    $sql .= "
        AND lida_em IS NOT NULL
    ";
}

/*
 * Mais recentes primeiro.
 */
$sql .= "
    ORDER BY criado_em DESC, id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':usuario_id' => $usuarioId
]);

$notificacoes = $stmt->fetchAll();

/*
 * ================================================================
 * QUANTIDADE DE NOTIFICAÇÕES NÃO LIDAS
 * ================================================================
 */

$stmtContagem = $pdo->prepare("
    SELECT COUNT(*)

    FROM notificacoes

    WHERE usuario_id = :usuario_id
      AND lida_em IS NULL
");

$stmtContagem->execute([
    ':usuario_id' => $usuarioId
]);

$totalNaoLidas =
    (int) $stmtContagem->fetchColumn();

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">
    <?php renderizarBreadcrumbs(); ?>

    <!-- =========================================================
         CABEÇALHO
         ========================================================= -->

    <div class="topo-pagina">

        <div>

            <h1>
                Notificações
            </h1>

            <p>

                <?php if ($totalNaoLidas === 0): ?>

                    Você não possui notificações não lidas.

                <?php elseif ($totalNaoLidas === 1): ?>

                    Você possui
                    <strong>1</strong>
                    notificação não lida.

                <?php else: ?>

                    Você possui
                    <strong>
                        <?php echo $totalNaoLidas; ?>
                    </strong>
                    notificações não lidas.

                <?php endif; ?>

            </p>

        </div>

    </div>

    <!-- =========================================================
         FILTROS
         ========================================================= -->

    <div class="acoes-formulario">

        <!-- Todas -->

        <a href="<?php echo BASE_URL; ?>/notifications/index.php?filtro=todas" class="botao <?php echo $filtro === 'todas'
               ? ''
               : 'botao-secundario'; ?>">
            Todas
        </a>

        <!-- Não lidas -->

        <a href="<?php echo BASE_URL; ?>/notifications/index.php?filtro=nao_lidas" class="botao <?php echo $filtro === 'nao_lidas'
               ? ''
               : 'botao-secundario'; ?>">
            Não lidas
        </a>

        <!-- Lidas -->

        <a href="<?php echo BASE_URL; ?>/notifications/index.php?filtro=lidas" class="botao <?php echo $filtro === 'lidas'
               ? ''
               : 'botao-secundario'; ?>">
            Lidas
        </a>

        <!-- Marcar todas como lidas -->

        <?php if ($totalNaoLidas > 0): ?>

            <form method="POST" action="<?php echo BASE_URL; ?>/notifications/mark_all_read.php" class="form-inline">

                <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                <button type="submit" class="botao botao-secundario">
                    Marcar todas como lidas
                </button>

            </form>

        <?php endif; ?>

    </div>

    <br>

    <!-- =========================================================
         LISTA DE NOTIFICAÇÕES
         ========================================================= -->

    <?php if (empty($notificacoes)): ?>

        <div class="painel">

            <p class="texto-vazio">

                <?php if ($filtro === 'nao_lidas'): ?>

                    Você não possui notificações não lidas.

                <?php elseif ($filtro === 'lidas'): ?>

                    Você não possui notificações lidas.

                <?php else: ?>

                    Nenhuma notificação encontrada.

                <?php endif; ?>

            </p>

        </div>

    <?php else: ?>

        <?php foreach ($notificacoes as $notificacao): ?>

            <div class="painel">

                <!-- =================================================
                     TÍTULO / TIPO
                     ================================================= -->

                <div>

                    <?php if ($notificacao['tipo'] === 'critico'): ?>

                        <strong>
                            <span class="notificacao-marcador notificacao-critico" aria-hidden="true"></span>
                            <?php
                            echo limpar(
                                $notificacao['titulo']
                            );
                            ?>
                        </strong>

                    <?php elseif ($notificacao['tipo'] === 'alerta'): ?>

                        <strong>
                            <span class="notificacao-marcador notificacao-alerta" aria-hidden="true"></span>
                            <?php
                            echo limpar(
                                $notificacao['titulo']
                            );
                            ?>
                        </strong>

                    <?php elseif ($notificacao['tipo'] === 'sucesso'): ?>

                        <strong>
                            <span class="notificacao-marcador notificacao-sucesso" aria-hidden="true"></span>
                            <?php
                            echo limpar(
                                $notificacao['titulo']
                            );
                            ?>
                        </strong>

                    <?php else: ?>

                        <strong>
                            <span class="notificacao-marcador notificacao-info" aria-hidden="true"></span>
                            <?php
                            echo limpar(
                                $notificacao['titulo']
                            );
                            ?>
                        </strong>

                    <?php endif; ?>

                    <!-- Nova -->

                    <?php if ($notificacao['lida_em'] === null): ?>

                        <span class="etiqueta etiqueta-ativo">
                            Nova
                        </span>

                    <?php endif; ?>

                </div>

                <!-- =================================================
                     MENSAGEM
                     ================================================= -->

                <p>

                    <?php
                    echo limpar(
                        $notificacao['mensagem']
                    );
                    ?>

                </p>

                <!-- =================================================
                     DATA
                     ================================================= -->

                <small>

                    <?php
                    echo formatarDataHora(
                        $notificacao['criado_em']
                    );
                    ?>

                </small>

                <!-- =================================================
                     VER DETALHES
                     ================================================= -->

                <?php if (!empty($notificacao['link'])): ?>

                    <div class="acoes-formulario">

                        <form method="POST" action="<?php echo BASE_URL; ?>/notifications/open.php">

                            <input type="hidden" name="csrf_token" value="<?php echo gerarTokenCSRF(); ?>">

                            <input type="hidden" name="notificacao_id" value="<?php echo (int) $notificacao['id']; ?>">

                            <button type="submit" class="botao botao-secundario">
                                Ver detalhes
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
