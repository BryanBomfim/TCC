<?php
/**
 * audit/index.php
 * ---------------------------------------------------------------
 * Histórico de ações importantes realizadas no SmartControl.
 *
 * Acesso exclusivo para administradores.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../config/config.php';

exigirAdministrador();
/**
 * Retorna um nome mais amigável para os campos
 * armazenados na auditoria.
 */
function rotuloCampoAuditoria(string $campo): string
{
    $rotulos = [
        'nome' => 'Nome',
        'descricao' => 'Descrição',
        'categoria_id' => 'Categoria',
        'fornecedor_id' => 'Fornecedor',
        'preco_custo' => 'Preço de custo',
        'preco_venda' => 'Preço de venda',
        'quantidade_estoque' => 'Quantidade em estoque',
        'estoque_minimo' => 'Estoque mínimo',
        'unidade' => 'Unidade',

        'produto_id' => 'ID do produto',
        'produto' => 'Produto',

        'estoque' => 'Estoque',
        'quantidade_entrada' => 'Quantidade de entrada',
        'quantidade_saida' => 'Quantidade de saída',

        'observacao' => 'Observação'
    ];

    return $rotulos[$campo] ?? ucfirst(
        str_replace('_', ' ', $campo)
    );
}

/**
 * Formata os valores antes de exibi-los.
 */
function formatarValorAuditoria(
    string $campo,
    mixed $valor
): string {

    if ($valor === null || $valor === '') {
        return '—';
    }

    /*
     * Valores monetários.
     */
    if (
        $campo === 'preco_custo'
        ||
        $campo === 'preco_venda'
    ) {

        return 'R$ ' . number_format(
            (float) $valor,
            2,
            ',',
            '.'
        );
    }

    /*
     * Valores booleanos.
     */
    if (is_bool($valor)) {
        return $valor ? 'Sim' : 'Não';
    }

    /*
     * Arrays eventualmente armazenados.
     */
    if (is_array($valor)) {

        return json_encode(
            $valor,
            JSON_UNESCAPED_UNICODE
            |
            JSON_UNESCAPED_SLASHES
        );
    }

    return (string) $valor;
}

/*
 * ---------------------------------------------------------------
 * 1. Filtros
 * ---------------------------------------------------------------
 */
$filtroAcao = trim($_GET['acao'] ?? '');
$filtroModulo = trim($_GET['modulo'] ?? '');
$filtroUsuario = (int) ($_GET['usuario_id'] ?? 0);
$dataInicio = trim($_GET['data_inicio'] ?? '');
$dataFim = trim($_GET['data_fim'] ?? '');

/*
 * ---------------------------------------------------------------
 * 2. Montagem da consulta
 * ---------------------------------------------------------------
 */
$sql = "
    SELECT
        id,
        usuario_id,
        usuario_nome,
        acao,
        modulo,
        registro_id,
        descricao,
        dados_anteriores,
        dados_novos,
        ip,
        criado_em

    FROM logs_auditoria

    WHERE 1 = 1
";

$parametros = [];

/*
 * Filtro por ação.
 */
if ($filtroAcao !== '') {

    $sql .= "
        AND acao = :acao
    ";

    $parametros[':acao'] = $filtroAcao;
}

/*
 * Filtro por módulo.
 */
if ($filtroModulo !== '') {

    $sql .= "
        AND modulo = :modulo
    ";

    $parametros[':modulo'] = $filtroModulo;
}

/*
 * Filtro por usuário.
 */
if ($filtroUsuario > 0) {

    $sql .= "
        AND usuario_id = :usuario_id
    ";

    $parametros[':usuario_id'] = $filtroUsuario;
}

/*
 * Data inicial.
 */
if ($dataInicio !== '') {

    $sql .= "
        AND DATE(criado_em) >= :data_inicio
    ";

    $parametros[':data_inicio'] = $dataInicio;
}

/*
 * Data final.
 */
if ($dataFim !== '') {

    $sql .= "
        AND DATE(criado_em) <= :data_fim
    ";

    $parametros[':data_fim'] = $dataFim;
}

/*
 * Registros mais recentes aparecem primeiro.
 */
$sql .= "
    ORDER BY criado_em DESC, id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute($parametros);

$logs = $stmt->fetchAll();

/*
 * ---------------------------------------------------------------
 * 3. Dados usados nos filtros
 * ---------------------------------------------------------------
 */

$acoes = $pdo->query("
    SELECT DISTINCT acao

    FROM logs_auditoria

    ORDER BY acao ASC
")->fetchAll();

$modulos = $pdo->query("
    SELECT DISTINCT modulo

    FROM logs_auditoria

    ORDER BY modulo ASC
")->fetchAll();

$usuarios = $pdo->query("
    SELECT DISTINCT
        usuario_id,
        usuario_nome

    FROM logs_auditoria

    WHERE usuario_id IS NOT NULL

    ORDER BY usuario_nome ASC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="conteudo" id="conteudo-principal" tabindex="-1">

    <div class="topo-pagina">

        <div>

            <h1>
                Auditoria
            </h1>

            <p class="descricao-pagina">
                Histórico das principais ações realizadas no sistema.
            </p>

        </div>

    </div>

    <!-- =========================================================
         FILTROS
         ========================================================= -->

    <form method="GET" action="" class="form-filtros">

        <!-- Ação -->
        <select name="acao" aria-label="Filtrar por ação">

            <option value="">
                Todas as ações
            </option>

            <?php foreach ($acoes as $acao): ?>

                <option value="<?php echo limpar($acao['acao']); ?>" <?php
                   echo $filtroAcao === $acao['acao']
                       ? 'selected'
                       : '';
                   ?>>
                    <?php echo limpar($acao['acao']); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <!-- Módulo -->
        <select name="modulo" aria-label="Filtrar por módulo">

            <option value="">
                Todos os módulos
            </option>

            <?php foreach ($modulos as $modulo): ?>

                <option value="<?php echo limpar($modulo['modulo']); ?>" <?php
                   echo $filtroModulo === $modulo['modulo']
                       ? 'selected'
                       : '';
                   ?>>
                    <?php echo limpar($modulo['modulo']); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <!-- Usuário -->
        <select name="usuario_id" aria-label="Filtrar por usuário">

            <option value="0">
                Todos os usuários
            </option>

            <?php foreach ($usuarios as $usuario): ?>

                <option value="<?php echo (int) $usuario['usuario_id']; ?>" <?php
                    echo $filtroUsuario === (int) $usuario['usuario_id']
                        ? 'selected'
                        : '';
                    ?>>
                    <?php echo limpar($usuario['usuario_nome']); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <!-- Data inicial -->
        <label class="rotulo-inline">

            De:

            <input type="date" name="data_inicio" value="<?php echo limpar($dataInicio); ?>" aria-label="Data inicial">>

        </label>

        <!-- Data final -->
        <label class="rotulo-inline">

            Até:

            <input type="date" name="data_fim" value="<?php echo limpar($dataFim); ?>" aria-label="Data final">>

        </label>

        <button type="submit" class="botao">
            Filtrar
        </button>

        <a href="<?php echo BASE_URL; ?>/audit/index.php" class="botao botao-secundario">
            Limpar
        </a>

    </form>

    <!-- =========================================================
         RESULTADOS
         ========================================================= -->

    <?php if (empty($logs)): ?>

        <p class="texto-vazio">
            Nenhum registro de auditoria encontrado.
        </p>

    <?php else: ?>

        <table class="tabela">

            <thead>

                <tr>

                    <th scope="col">
                        Data
                    </th>

                    <th scope="col">
                        Usuário
                    </th>

                    <th scope="col">
                        Ação
                    </th>

                    <th scope="col">
                        Módulo
                    </th>

                    <th scope="col">
                        Descrição
                    </th>

                    <th scope="col">
                        Detalhes
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php foreach ($logs as $log): ?>

                    <tr>

                        <!-- Data -->
                        <td>
                            <?php
                            echo formatarDataHora(
                                $log['criado_em']
                            );
                            ?>
                        </td>

                        <!-- Usuário -->
                        <td>

                            <?php
                            echo limpar(
                                $log['usuario_nome']
                                ?? 'Usuário removido'
                            );
                            ?>

                        </td>

                        <!-- Ação -->
                        <td>

                            <strong>
                                <?php echo limpar($log['acao']); ?>
                            </strong>

                        </td>

                        <!-- Módulo -->
                        <td>

                            <?php echo limpar($log['modulo']); ?>

                        </td>

                        <!-- Descrição -->
                        <td>

                            <?php echo limpar($log['descricao']); ?>

                        </td>

                        <!-- Detalhes -->
                        <td>

                            <?php
                            $possuiDetalhes =
                                !empty($log['dados_anteriores'])
                                ||
                                !empty($log['dados_novos']);
                            ?>

                            <?php if ($possuiDetalhes): ?>

                                <details>

                                    <summary class="detalhes-clicavel">
                                        Ver detalhes
                                    </summary>

                                    <?php

                                    /*
                                     * Converte os JSONs novamente para arrays PHP.
                                     */
                                    $dadosAntes = [];

                                    $dadosDepois = [];

                                    if (!empty($log['dados_anteriores'])) {

                                        $jsonAntes = json_decode(
                                            $log['dados_anteriores'],
                                            true
                                        );

                                        if (is_array($jsonAntes)) {
                                            $dadosAntes = $jsonAntes;
                                        }
                                    }

                                    if (!empty($log['dados_novos'])) {

                                        $jsonDepois = json_decode(
                                            $log['dados_novos'],
                                            true
                                        );

                                        if (is_array($jsonDepois)) {
                                            $dadosDepois = $jsonDepois;
                                        }
                                    }

                                    /*
                                     * Junta todos os campos existentes no antes e depois.
                                     */
                                    $campos = array_unique(
                                        array_merge(
                                            array_keys($dadosAntes),
                                            array_keys($dadosDepois)
                                        )
                                    );

                                    ?>

                                    <?php if (!empty($campos)): ?>

                                        <table class="tabela">

                                            <thead>

                                                <tr>

                                                    <th scope="col">
                                                        Campo
                                                    </th>

                                                    <th scope="col">
                                                        Antes
                                                    </th>

                                                    <th scope="col">
                                                        Depois
                                                    </th>

                                                </tr>

                                            </thead>

                                            <tbody>

                                                <?php foreach ($campos as $campo): ?>

                                                    <?php

                                                    $existeAntes =
                                                        array_key_exists(
                                                            $campo,
                                                            $dadosAntes
                                                        );

                                                    $existeDepois =
                                                        array_key_exists(
                                                            $campo,
                                                            $dadosDepois
                                                        );

                                                    $valorAntes =
                                                        $existeAntes
                                                        ? $dadosAntes[$campo]
                                                        : null;

                                                    $valorDepois =
                                                        $existeDepois
                                                        ? $dadosDepois[$campo]
                                                        : null;

                                                    /*
                                                     * Se o campo existe dos dois lados
                                                     * e não mudou, não precisamos mostrar.
                                                     *
                                                     * Assim a auditoria fica muito mais limpa.
                                                     */
                                                    if (
                                                        $existeAntes
                                                        &&
                                                        $existeDepois
                                                        &&
                                                        $valorAntes == $valorDepois
                                                    ) {
                                                        continue;
                                                    }

                                                    ?>

                                                    <tr>

                                                        <td>

                                                            <strong>
                                                                <?php
                                                                echo limpar(
                                                                    rotuloCampoAuditoria(
                                                                        $campo
                                                                    )
                                                                );
                                                                ?>
                                                            </strong>

                                                        </td>

                                                        <td>

                                                            <?php

                                                            echo limpar(
                                                                formatarValorAuditoria(
                                                                    $campo,
                                                                    $valorAntes
                                                                )
                                                            );

                                                            ?>

                                                        </td>

                                                        <td>

                                                            <?php

                                                            echo limpar(
                                                                formatarValorAuditoria(
                                                                    $campo,
                                                                    $valorDepois
                                                                )
                                                            );

                                                            ?>

                                                        </td>

                                                    </tr>

                                                <?php endforeach; ?>

                                            </tbody>

                                        </table>

                                    <?php endif; ?>

                                    <?php if (!empty($log['ip'])): ?>

                                        <p>

                                            <strong>
                                                IP:
                                            </strong>

                                            <?php echo limpar($log['ip']); ?>

                                        </p>

                                    <?php endif; ?>

                                </details>
                            <?php else: ?>

                                <span class="texto-esmaecido">
                                    -
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
