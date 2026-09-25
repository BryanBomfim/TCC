<?php
declare(strict_types=1);

namespace SmartControl\Services;

use DomainException;
use PDO;
use Throwable;

/**
 * Centraliza as regras que alteram saldo de estoque.
 *
 * Todas as operações bloqueiam o produto com FOR UPDATE e registram o
 * movimento na mesma transação da atualização do saldo.
 */
final class StockService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed> */
    public function registrarEntrada(
        int $usuarioId,
        int $produtoId,
        int $quantidade,
        ?int $fornecedorId,
        ?float $valorUnitario,
        ?string $observacao
    ): array {
        if ($quantidade <= 0 || $quantidade > 2147483647) {
            throw new DomainException('A quantidade da entrada deve ser maior que zero.');
        }

        if ($valorUnitario !== null && (!is_finite($valorUnitario) || $valorUnitario < 0 || $valorUnitario > 99999999.99)) {
            throw new DomainException('O valor unitário deve estar entre 0 e 99.999.999,99.');
        }

        $observacao = $this->normalizarTextoOpcional($observacao, 255, 'observação');

        return $this->transacionar(function () use (
            $usuarioId,
            $produtoId,
            $quantidade,
            $fornecedorId,
            $valorUnitario,
            $observacao
        ): array {
            if ($fornecedorId !== null) {
                $stmtFornecedor = $this->pdo->prepare("
                    SELECT id
                    FROM fornecedores
                    WHERE id = :id AND ativo = 1
                    LIMIT 1
                ");
                $stmtFornecedor->execute([':id' => $fornecedorId]);

                if (!$stmtFornecedor->fetchColumn()) {
                    throw new DomainException('Fornecedor não encontrado ou inativo.');
                }
            }

            $produto = $this->bloquearProduto($produtoId);
            $saldoAnterior = (int) $produto['quantidade_estoque'];
            $saldoPosterior = $saldoAnterior + $quantidade;
            if ($saldoPosterior > 2147483647) {
                throw new DomainException('O saldo ultrapassa o limite permitido.');
            }

            $movimentacaoId = $this->inserirMovimentacao([
                'produto_id' => $produtoId,
                'usuario_id' => $usuarioId,
                'fornecedor_id' => $fornecedorId,
                'tipo' => 'entrada',
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'valor_unitario' => $valorUnitario,
                'motivo' => 'Entrada de estoque',
                'origem' => 'entrada',
                'observacao' => $observacao,
            ]);

            $this->atualizarSaldo($produtoId, $saldoPosterior);

            return $this->resultado($movimentacaoId, $produto, $quantidade, $saldoAnterior, $saldoPosterior);
        });
    }

    /** @return array<string, mixed> */
    public function registrarSaida(
        int $usuarioId,
        int $produtoId,
        int $quantidade,
        string $motivo,
        ?string $observacao
    ): array {
        if ($quantidade <= 0 || $quantidade > 2147483647) {
            throw new DomainException('A quantidade da saída deve ser maior que zero.');
        }

        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 100) {
            throw new DomainException('Informe um motivo válido com até 100 caracteres.');
        }

        $observacao = $this->normalizarTextoOpcional($observacao, 255, 'observação');

        return $this->transacionar(function () use (
            $usuarioId,
            $produtoId,
            $quantidade,
            $motivo,
            $observacao
        ): array {
            $produto = $this->bloquearProduto($produtoId);
            $saldoAnterior = (int) $produto['quantidade_estoque'];

            if ($quantidade > $saldoAnterior) {
                throw new DomainException(
                    'Quantidade insuficiente em estoque. Estoque atual: '
                    . $saldoAnterior . ' ' . $produto['unidade'] . '.'
                );
            }

            $saldoPosterior = $saldoAnterior - $quantidade;
            $movimentacaoId = $this->inserirMovimentacao([
                'produto_id' => $produtoId,
                'usuario_id' => $usuarioId,
                'fornecedor_id' => null,
                'tipo' => 'saida',
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $saldoPosterior,
                'valor_unitario' => null,
                'motivo' => $motivo,
                'origem' => 'saida',
                'observacao' => $observacao,
            ]);

            $this->atualizarSaldo($produtoId, $saldoPosterior);

            return $this->resultado($movimentacaoId, $produto, $quantidade, $saldoAnterior, $saldoPosterior);
        });
    }

    /** @return array<string, mixed> */
    public function ajustarSaldo(
        int $usuarioId,
        int $produtoId,
        int $novoSaldo,
        string $justificativa
    ): array {
        if ($novoSaldo < 0 || $novoSaldo > 2147483647) {
            throw new DomainException('O novo saldo não pode ser negativo.');
        }

        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 10 || mb_strlen($justificativa) > 255) {
            throw new DomainException('A justificativa deve possuir entre 10 e 255 caracteres.');
        }

        return $this->transacionar(function () use (
            $usuarioId,
            $produtoId,
            $novoSaldo,
            $justificativa
        ): array {
            $produto = $this->bloquearProduto($produtoId);
            $saldoAnterior = (int) $produto['quantidade_estoque'];
            $diferenca = $novoSaldo - $saldoAnterior;

            if ($diferenca === 0) {
                throw new DomainException('O novo saldo é igual ao saldo atual. Nenhum ajuste é necessário.');
            }

            $quantidade = abs($diferenca);
            $tipo = $diferenca > 0 ? 'entrada' : 'saida';
            $movimentacaoId = $this->inserirMovimentacao([
                'produto_id' => $produtoId,
                'usuario_id' => $usuarioId,
                'fornecedor_id' => null,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior' => $novoSaldo,
                'valor_unitario' => null,
                'motivo' => 'Ajuste administrativo',
                'origem' => 'ajuste',
                'observacao' => $justificativa,
            ]);

            $this->atualizarSaldo($produtoId, $novoSaldo);

            return $this->resultado($movimentacaoId, $produto, $quantidade, $saldoAnterior, $novoSaldo)
                + ['tipo' => $tipo, 'justificativa' => $justificativa];
        });
    }

    /** @return array<string, mixed> */
    private function bloquearProduto(int $produtoId): array
    {
        if ($produtoId <= 0) {
            throw new DomainException('Selecione um produto válido.');
        }

        $stmt = $this->pdo->prepare("
            SELECT id, nome, quantidade_estoque, estoque_minimo, unidade
            FROM produtos
            WHERE id = :id AND ativo = 1
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':id' => $produtoId]);
        $produto = $stmt->fetch();

        if (!$produto) {
            throw new DomainException('Produto não encontrado ou inativo.');
        }

        return $produto;
    }

    /** @param array<string, mixed> $dados */
    private function inserirMovimentacao(array $dados): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO movimentacoes
                (produto_id, usuario_id, fornecedor_id, tipo, quantidade,
                 saldo_anterior, saldo_posterior, valor_unitario, motivo, origem, observacao)
            VALUES
                (:produto_id, :usuario_id, :fornecedor_id, :tipo, :quantidade,
                 :saldo_anterior, :saldo_posterior, :valor_unitario, :motivo, :origem, :observacao)
        ");
        $stmt->execute([
            ':produto_id' => $dados['produto_id'],
            ':usuario_id' => $dados['usuario_id'],
            ':fornecedor_id' => $dados['fornecedor_id'],
            ':tipo' => $dados['tipo'],
            ':quantidade' => $dados['quantidade'],
            ':saldo_anterior' => $dados['saldo_anterior'],
            ':saldo_posterior' => $dados['saldo_posterior'],
            ':valor_unitario' => $dados['valor_unitario'],
            ':motivo' => $dados['motivo'],
            ':origem' => $dados['origem'],
            ':observacao' => $dados['observacao'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function atualizarSaldo(int $produtoId, int $saldo): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE produtos
            SET quantidade_estoque = :saldo
            WHERE id = :id
        ");
        $stmt->execute([':saldo' => $saldo, ':id' => $produtoId]);
    }

    private function normalizarTextoOpcional(?string $texto, int $limite, string $campo): ?string
    {
        $texto = trim((string) $texto);
        if (mb_strlen($texto) > $limite) {
            throw new DomainException('A ' . $campo . ' deve possuir no máximo ' . $limite . ' caracteres.');
        }

        return $texto !== '' ? $texto : null;
    }

    /** @return array<string, mixed> */
    private function resultado(
        int $movimentacaoId,
        array $produto,
        int $quantidade,
        int $saldoAnterior,
        int $saldoPosterior
    ): array {
        return [
            'movimentacao_id' => $movimentacaoId,
            'produto_id' => (int) $produto['id'],
            'produto_nome' => (string) $produto['nome'],
            'unidade' => (string) $produto['unidade'],
            'estoque_minimo' => (int) $produto['estoque_minimo'],
            'quantidade' => $quantidade,
            'saldo_anterior' => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
        ];
    }

    /** @template T @param callable(): T $operacao @return T */
    private function transacionar(callable $operacao): mixed
    {
        $transacaoPropria = !$this->pdo->inTransaction();

        if ($transacaoPropria) {
            $this->pdo->beginTransaction();
        }

        try {
            $resultado = $operacao();
            if ($transacaoPropria) {
                $this->pdo->commit();
            }
            return $resultado;
        } catch (Throwable $e) {
            if ($transacaoPropria && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
