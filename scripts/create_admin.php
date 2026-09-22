<?php
/**
 * Cria o primeiro administrador do SmartControl pelo terminal.
 * A senha é definida durante a execução e nunca é gravada no código.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado pelo terminal.\n");
    exit(1);
}

require_once __DIR__ . '/../config/config.php';

function lerEntrada(string $rotulo): string
{
    fwrite(STDOUT, $rotulo);
    $valor = fgets(STDIN);
    return trim($valor === false ? '' : $valor);
}

function lerSenha(string $rotulo): string
{
    // Mantém o script portátil entre Windows/XAMPP, Linux e macOS.
    // Dependendo do terminal, os caracteres podem ficar visíveis durante a digitação.
    fwrite(STDOUT, $rotulo);
    $senha = fgets(STDIN);
    return rtrim($senha === false ? '' : $senha, "\r\n");
}

try {
    $totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'administrador' AND ativo = 1")->fetchColumn();

    if ($totalAdmins > 0) {
        fwrite(STDERR, "Já existe pelo menos um administrador ativo. Use a tela de usuários para gerenciar novas contas.\n");
        exit(1);
    }

    $nome = lerEntrada('Nome do administrador: ');
    $email = strtolower(lerEntrada('E-mail do administrador: '));
    $senha = lerSenha('Senha (mínimo 8 caracteres): ');
    $confirmacao = lerSenha('Confirme a senha: ');

    if ($nome === '') {
        throw new RuntimeException('O nome é obrigatório.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }

    if (strlen($senha) < 8) {
        throw new RuntimeException('A senha deve ter no mínimo 8 caracteres.');
    }

    if (!hash_equals($senha, $confirmacao)) {
        throw new RuntimeException('As senhas não conferem.');
    }

    $stmtExiste = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $stmtExiste->execute([':email' => $email]);
    if ($stmtExiste->fetchColumn()) {
        throw new RuntimeException('Já existe um usuário com esse e-mail.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO usuarios
            (nome, email, email_verificado_em, senha, perfil, ativo)
        VALUES
            (:nome, :email, NOW(), :senha, 'administrador', 1)
    ");
    $stmt->execute([
        ':nome' => mb_substr($nome, 0, 100),
        ':email' => mb_substr($email, 0, 150),
        ':senha' => password_hash($senha, PASSWORD_DEFAULT),
    ]);

    fwrite(STDOUT, "Administrador criado com sucesso. Agora você pode entrar no SmartControl.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
