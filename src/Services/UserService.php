<?php
declare(strict_types=1);

namespace SmartControl\Services;

use DomainException;
use PDO;
use Throwable;

/** Serializa alterações administrativas e preserva ao menos um administrador ativo. */
final class UserService
{
    public function __construct(private readonly PDO $pdo) {}

    public function editar(int $atorId, int $id, string $nome, string $email, string $senha, string $perfil): void
    {
        $nome = trim($nome);
        $email = strtolower(trim($email));
        if ($nome === '' || mb_strlen($nome) > 100 || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Informe nome (até 100 caracteres) e e-mail válidos.');
        }
        if (!in_array($perfil, ['administrador', 'funcionario'], true)) {
            throw new DomainException('Perfil inválido.');
        }
        if ($senha !== '' && ($erro = \validarNovaSenha($senha)) !== null) {
            throw new DomainException($erro);
        }
        $this->alterar($atorId, $id, function (array $usuario) use ($nome, $email, $senha, $perfil): array {
            return array_merge($usuario, [
                'nome' => $nome, 'email' => $email, 'perfil' => $perfil,
                'senha' => $senha !== '' ? password_hash($senha, PASSWORD_DEFAULT) : $usuario['senha'],
            ]);
        });
    }

    public function alternar(int $atorId, int $id): void
    {
        if ($atorId === $id) {
            throw new DomainException('Você não pode alterar o status da sua própria conta.');
        }
        $this->alterar($atorId, $id, static fn (array $u): array => array_merge($u, ['ativo' => (int) !$u['ativo']]));
    }

    private function alterar(int $atorId, int $id, callable $alteracao): void
    {
        $this->pdo->beginTransaction();
        try {
            // Volume pequeno e administrativo: bloqueio ordenado evita desativação concorrente do último admin.
            $usuarios = $this->pdo->query('SELECT * FROM usuarios ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_UNIQUE);
            $ator = $usuarios[$atorId] ?? null;
            $anterior = $usuarios[$id] ?? null;
            if (!$ator || !$ator['ativo'] || $ator['perfil'] !== 'administrador') {
                throw new DomainException('Sua permissão administrativa foi alterada. Entre novamente.');
            }
            if (!$anterior) {
                throw new DomainException('Usuário não encontrado.');
            }
            $novo = $alteracao($anterior);
            $usuarios[$id] = $novo;
            $admins = array_filter($usuarios, static fn (array $u): bool => (bool) $u['ativo'] && $u['perfil'] === 'administrador' && $u['email_verificado_em'] !== null);
            if ($admins === []) {
                throw new DomainException('Mantenha pelo menos um administrador ativo com e-mail verificado.');
            }
            $sensivel = $novo['senha'] !== $anterior['senha'] || $novo['email'] !== $anterior['email']
                || $novo['perfil'] !== $anterior['perfil'] || $novo['ativo'] !== $anterior['ativo'];
            $stmt = $this->pdo->prepare('UPDATE usuarios SET nome=:nome, email=:email, senha=:senha, perfil=:perfil, ativo=:ativo, auth_version=auth_version+:revogar WHERE id=:id');
            $stmt->execute([':nome'=>$novo['nome'], ':email'=>$novo['email'], ':senha'=>$novo['senha'], ':perfil'=>$novo['perfil'], ':ativo'=>$novo['ativo'], ':revogar'=>(int) $sensivel, ':id'=>$id]);
            if ($sensivel) {
                \revogarCredenciaisUsuario($id);
            }
            // Alteração pelo administrador é atestada por ele; alteração em Minha Conta exige link de e-mail.
            \registrarAuditoria('EDITAR', 'Usuários', $id, 'Conta atualizada pelo administrador.',
                array_intersect_key($anterior, array_flip(['nome','email','perfil','ativo'])),
                array_intersect_key($novo, array_flip(['nome','email','perfil','ativo'])) + ['senha_alterada' => $novo['senha'] !== $anterior['senha']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
