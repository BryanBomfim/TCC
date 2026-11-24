<?php
declare(strict_types=1);
require_once __DIR__ . '/validation.php';

use SmartControl\Services\StockService;

final class MobileApi
{
    private PDO $db;
    private array $config;
    private array $columns = [];
    private ?int $tokenId = null;
    private const USER_FIELDS = 'id, nome, email, perfil, ativo, email_verificado_em, auth_version, data_cadastro';

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    private function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!isset($this->columns[$table])) {
            // Os nomes vêm exclusivamente de constantes internas, nunca de uma requisição.
            $this->columns[$table] = array_column($this->query("SHOW COLUMNS FROM `$table`")->fetchAll(), 'Field');
        }
        return in_array($column, $this->columns[$table], true);
    }

    private function publicUser(array $user): array
    {
        return array_intersect_key($user, array_flip(['id', 'nome', 'email', 'perfil', 'ativo', 'data_cadastro']));
    }

    public function authenticate(string $authorization): array
    {
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/Di', $authorization, $match)) {
            throw new ApiError('Faça login para continuar.', 401);
        }
        $user = $this->query(
            'SELECT u.*, t.id AS api_token_id FROM mobile_api_tokens t JOIN usuarios u ON u.id = t.usuario_id '
            . 'WHERE t.token_hash = ? AND t.auth_version = u.auth_version '
            . 'AND t.revogado_em IS NULL AND t.expira_em > UTC_TIMESTAMP() LIMIT 1',
            [hash('sha256', $match[1])]
        )->fetch();
        if (!$user || (int) $user['ativo'] !== 1
            || (array_key_exists('email_verificado_em', $user) && $user['email_verificado_em'] === null)) {
            throw new ApiError('Sessão expirada ou acesso desativado. Entre novamente.', 401);
        }
        $this->tokenId = (int) $user['api_token_id'];
        return $this->publicUser($user);
    }

    public function login(array $body, string $ip): array
    {
        $email = Input::email($body);
        $password = $body['password'] ?? '';
        // Login aceita senhas antigas de seis caracteres do site; a regra nova vale só ao definir senha.
        if (!is_string($password) || $password === '' || strlen($password) > 1024 || str_contains($password, "\0")) {
            throw new ApiError('E-mail ou senha inválidos.', 401);
        }
        $emailHash = hash('sha256', $email);
        $ipHash = hash('sha256', $ip);
        $emailCount = (int) $this->query(
            'SELECT COUNT(*) FROM mobile_api_login_attempts WHERE email_hash = ? '
            . 'AND criado_em >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)', [$emailHash]
        )->fetchColumn();
        $ipCount = (int) $this->query(
            'SELECT COUNT(*) FROM mobile_api_login_attempts WHERE ip_hash = ? '
            . 'AND criado_em >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)', [$ipHash]
        )->fetchColumn();
        if ($emailCount >= 5 || $ipCount >= 30) {
            throw new ApiError('Muitas tentativas. Aguarde 15 minutos e tente novamente.', 429);
        }
        $user = $this->query('SELECT * FROM usuarios WHERE email = ? LIMIT 1', [$email])->fetch();
        // Hash dummy evita uma resposta imediata para e-mails inexistentes.
        $hash = $user['senha'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $hash);
        if (!$user || !$valid || (int) $user['ativo'] !== 1) {
            $this->query('INSERT INTO mobile_api_login_attempts (email_hash, ip_hash, criado_em) VALUES (?, ?, UTC_TIMESTAMP())', [$emailHash, $ipHash]);
            throw new ApiError('E-mail ou senha inválidos.', 401);
        }
        if (array_key_exists('email_verificado_em', $user) && $user['email_verificado_em'] === null) {
            throw new ApiError('Confirme seu e-mail pelo site antes de entrar no aplicativo.', 403);
        }
        if (password_verify('SmartControl@2026!', (string) $user['senha'])) {
            throw new ApiError('Defina uma senha própria em Minha Conta no site antes de usar o aplicativo.', 403);
        }
        $token = bin2hex(random_bytes(32));
        $hours = (int) $this->config['token_hours'];
        $this->query(
            "INSERT INTO mobile_api_tokens (usuario_id, token_hash, auth_version, criado_em, expira_em) "
            . "VALUES (?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL $hours HOUR))",
            [(int) $user['id'], hash('sha256', $token), (int) ($user['auth_version'] ?? 0)]
        );
        $this->query('DELETE FROM mobile_api_login_attempts WHERE email_hash = ? AND ip_hash = ?', [$emailHash, $ipHash]);
        return ['token' => $token, 'user' => $this->publicUser($user), 'expires_in' => $hours * 3600];
    }

    public function logout(): array
    {
        $this->query('UPDATE mobile_api_tokens SET revogado_em = UTC_TIMESTAMP() WHERE id = ?', [$this->tokenId]);
        return ['message' => 'Sessão encerrada.'];
    }

    public function bootstrap(array $user): array
    {
        $current = $this->query('SELECT ' . self::USER_FIELDS . ' FROM usuarios WHERE id = ?', [(int) $user['id']])->fetch();
        if (!$current || (int) $current['ativo'] !== 1) {
            throw new ApiError('Usuário desativado.', 401);
        }
        $settings = ['nome_sistema' => 'SmartControl', 'nome_empresa' => 'Minha Empresa', 'estoque_minimo_padrao' => '5'];
        foreach ($this->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('nome_sistema', 'nome_empresa', 'estoque_minimo_padrao')")->fetchAll() as $setting) {
            $settings[$setting['chave']] = $setting['valor'];
        }

        $inventories = $this->query(
            'SELECT i.id, i.revisao, i.titulo, i.status, i.observacao, i.criado_por_nome, i.criado_em, '
            . 'i.finalizado_por_nome, i.finalizado_em, COUNT(ii.id) AS total_itens, '
            . 'COALESCE(SUM(ii.quantidade_contada IS NOT NULL), 0) AS itens_contados, '
            . 'COALESCE(SUM(ii.diferenca <> 0), 0) AS divergencias '
            . 'FROM inventarios i LEFT JOIN inventario_itens ii ON ii.inventario_id = i.id '
            . 'GROUP BY i.id ORDER BY i.criado_em DESC LIMIT 100'
        )->fetchAll();
        $openInventory = null;
        foreach ($inventories as $inventory) {
            if ($inventory['status'] === 'aberto') {
                $openInventory = $inventory;
                break;
            }
        }
        $inventoryItems = [];
        if ($openInventory !== null) {
            $inventoryItems = $this->query(
                'SELECT ii.id, ii.inventario_id, ii.produto_id, p.sku, p.codigo_barras, p.nome AS produto, '
                . 'ii.estoque_sistema, ii.quantidade_contada, ii.diferenca, ii.contado_por_nome, ii.contado_em, ii.ajustado '
                . 'FROM inventario_itens ii JOIN produtos p ON p.id = ii.produto_id '
                . 'WHERE ii.inventario_id = ? ORDER BY p.nome, p.id',
                [(int) $openInventory['id']]
            )->fetchAll();
        }

        $notifications = $this->query(
            'SELECT id, tipo, titulo, mensagem, entidade, entidade_id, link, lida_em, criado_em '
            . 'FROM notificacoes WHERE usuario_id = ? ORDER BY criado_em DESC, id DESC LIMIT 100',
            [(int) $current['id']]
        )->fetchAll();
        $audit = $current['perfil'] === 'administrador'
            ? $this->query(
                'SELECT id, usuario_id, usuario_nome, acao, modulo, registro_id, descricao, criado_em '
                . 'FROM logs_auditoria ORDER BY criado_em DESC, id DESC LIMIT 100'
            )->fetchAll()
            : [];

        return [
            'user' => $current,
            'products' => $this->query('SELECT p.*, c.nome AS categoria, f.nome AS fornecedor FROM produtos p '
                . 'LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN fornecedores f ON f.id = p.fornecedor_id ORDER BY p.nome, p.id')->fetchAll(),
            'categories' => $this->query('SELECT * FROM categorias ORDER BY nome, id')->fetchAll(),
            'suppliers' => $this->query('SELECT * FROM fornecedores ORDER BY nome, id')->fetchAll(),
            'users' => $current['perfil'] === 'administrador'
                ? $this->query('SELECT ' . self::USER_FIELDS . ' FROM usuarios ORDER BY nome, id')->fetchAll() : [],
            'movements' => $this->query('SELECT m.*, p.nome AS produto, u.nome AS usuario FROM movimentacoes m '
                . 'JOIN produtos p ON p.id = m.produto_id JOIN usuarios u ON u.id = m.usuario_id '
                . 'ORDER BY m.data_movimentacao DESC, m.id DESC LIMIT 500')->fetchAll(),
            'settings' => $settings,
            'inventories' => $inventories,
            'inventory_items' => $inventoryItems,
            'notifications' => $notifications,
            'audit' => $audit,
        ];
    }

    public function mutate(string $resource, array $body, array $user): array
    {
        $this->db->beginTransaction();
        try {
            // Releitura de perfil/ativo impede confiar em valores enviados pelo aplicativo.
            $current = $this->query('SELECT ' . self::USER_FIELDS . ' FROM usuarios WHERE id = ?', [(int) $user['id']])->fetch();
            if (!$current || (int) $current['ativo'] !== 1) {
                throw new ApiError('Usuário desativado.', 401);
            }
            if ($resource === 'users' || $resource === 'users/toggle' || $resource === 'settings'
                || (($body['tipo'] ?? '') === 'ajuste' && $resource === 'movements')) {
                Input::admin($current);
            }
            switch ($resource) {
                case 'products': $this->saveProduct($body, $current); break;
                case 'categories': $this->saveCategory($body); break;
                case 'suppliers': $this->saveSupplier($body); break;
                case 'users': $this->saveUser($body, $current); break;
                case 'movements': $this->movement($body, $current); break;
                case 'settings': $this->settings($body); break;
                case 'notifications/read': $this->readNotification($body, $current); break;
                case 'notifications/read-all': $this->readAllNotifications($current); break;
                case 'products/toggle': $this->toggle('produtos', $body); break;
                case 'categories/toggle': $this->toggle('categorias', $body); break;
                case 'suppliers/toggle': $this->toggle('fornecedores', $body); break;
                case 'users/toggle': $this->toggleUser($body, $current); break;
                default: throw new ApiError('Recurso não encontrado.', 404);
            }
            if (!in_array($resource, ['movements', 'notifications/read', 'notifications/read-all'], true)) {
                $this->audit(
                    $current,
                    str_ends_with($resource, '/toggle') ? 'ALTERAR_STATUS' : 'SALVAR',
                    'API Mobile',
                    isset($body['id']) && is_numeric($body['id']) ? (int) $body['id'] : null,
                    'Operação realizada pelo aplicativo em ' . $resource . '.'
                );
            }
            // Monta resposta antes do commit: uma falha de leitura também desfaz a mutação.
            $result = $this->bootstrap($current);
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function row(string $table, int $id): array
    {
        $row = $this->query("SELECT * FROM `$table` WHERE id = ? FOR UPDATE", [$id])->fetch();
        if (!$row) {
            throw new ApiError('Registro não encontrado.', 404);
        }
        return $row;
    }

    private function write(string $table, array $values, ?int $id): int
    {
        // Tabelas e colunas vêm apenas dos métodos internos abaixo.
        $columns = array_keys($values);
        if ($id !== null) {
            $sets = implode(', ', array_map(static fn(string $key): string => "`$key` = ?", $columns));
            $this->query("UPDATE `$table` SET $sets WHERE id = ?", [...array_values($values), $id]);
            return $id;
        }
        $names = '`' . implode('`, `', $columns) . '`';
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $this->query("INSERT INTO `$table` ($names) VALUES ($placeholders)", array_values($values));
        return (int) $this->db->lastInsertId();
    }

    private function activeReference(string $table, ?int $id, ?int $oldId): void
    {
        if ($id === null) {
            return;
        }
        $row = $this->row($table, $id);
        if ((int) $row['ativo'] !== 1 && $oldId !== $id) {
            throw new ApiError('Selecione uma categoria/fornecedor ativo.');
        }
    }

    private function saveProduct(array $body, array $user): void
    {
        $id = Input::id($body, 'id', true);
        $old = $id === null ? null : $this->row('produtos', $id);
        $values = [
            'nome' => Input::text($body, 'nome', 150, true),
            'descricao' => Input::text($body, 'descricao', 10000),
            'categoria_id' => Input::id($body, 'categoria_id', true),
            'fornecedor_id' => Input::id($body, 'fornecedor_id', true),
            'preco_custo' => Input::money($body, 'preco_custo'),
            'preco_venda' => Input::money($body, 'preco_venda'),
            'estoque_minimo' => Input::integer($body, 'estoque_minimo'),
            'unidade' => Input::text($body, 'unidade', 20, true, 'UN'),
        ];
        $this->activeReference('categorias', $values['categoria_id'], isset($old['categoria_id']) ? (int) $old['categoria_id'] : null);
        $this->activeReference('fornecedores', $values['fornecedor_id'], isset($old['fornecedor_id']) ? (int) $old['fornecedor_id'] : null);
        if ($this->hasColumn('produtos', 'codigo_barras')) {
            // Omissão pelo Flutter mantém o código de barras cadastrado no site.
            $barcode = Input::text($body, 'codigo_barras', 50, false, $old['codigo_barras'] ?? '');
            $values['codigo_barras'] = $barcode === '' ? null : $barcode;
            if ($barcode !== '' && $this->query('SELECT id FROM produtos WHERE codigo_barras = ? AND id <> ?', [$barcode, $id ?? 0])->fetch()) {
                throw new ApiError('Código de barras já cadastrado.', 409);
            }
        }
        if ($id !== null && array_key_exists('quantidade_estoque', $body)
            && Input::integer($body, 'quantidade_estoque') !== (int) $old['quantidade_estoque']) {
            throw new ApiError('Altere o saldo pela tela de movimentações de estoque.');
        }
        $initialStock = $id === null ? Input::integer($body, 'quantidade_estoque') : 0;
        if ($id === null) {
            $values['quantidade_estoque'] = $initialStock;
            $values['ativo'] = 1;
        }
        $saved = $this->write('produtos', $values, $id);
        if ($id === null && $this->hasColumn('produtos', 'sku')) {
            $this->query('UPDATE produtos SET sku = ? WHERE id = ?', ['SC-' . str_pad((string) $saved, 6, '0', STR_PAD_LEFT), $saved]);
        }
        if ($id === null && $initialStock > 0) {
            (new StockService($this->db))->registrarEntrada(
                (int) $user['id'],
                $saved,
                $initialStock,
                $values['fornecedor_id'],
                (float) $values['preco_custo'],
                'Estoque inicial cadastrado pelo aplicativo'
            );
        }
    }

    private function saveCategory(array $body): void
    {
        $id = Input::id($body, 'id', true);
        if ($id !== null) { $this->row('categorias', $id); }
        $this->write('categorias', ['nome' => Input::text($body, 'nome', 100, true),
            'descricao' => Input::text($body, 'descricao', 255)], $id);
    }

    private function saveSupplier(array $body): void
    {
        $id = Input::id($body, 'id', true);
        if ($id !== null) { $this->row('fornecedores', $id); }
        $values = [
            'nome' => Input::text($body, 'nome', 150, true),
            'razao_social' => Input::text($body, 'razao_social', 180),
            'cnpj_cpf' => Input::text($body, 'cnpj_cpf', 20),
            'telefone' => Input::text($body, 'telefone', 20),
            'email' => Input::email($body, false),
            'cep' => Input::text($body, 'cep', 8),
            'logradouro' => Input::text($body, 'logradouro', 180),
            'numero' => Input::text($body, 'numero', 20),
            'complemento' => Input::text($body, 'complemento', 100),
            'bairro' => Input::text($body, 'bairro', 100),
            'cidade' => Input::text($body, 'cidade', 100),
            'uf' => strtoupper(Input::text($body, 'uf', 2)),
            'endereco' => Input::text($body, 'endereco', 255),
        ];
        foreach (['razao_social', 'cnpj_cpf', 'telefone', 'email', 'cep', 'logradouro', 'numero',
                     'complemento', 'bairro', 'cidade', 'uf', 'endereco'] as $optional) {
            if ($values[$optional] === '') {
                $values[$optional] = null;
            }
        }
        $this->write('fornecedores', $values, $id);
    }

    private function lockAdministrators(): void
    {
        // Serializa alterações de administradores para preservar ao menos um ativo.
        $this->query("SELECT id FROM usuarios WHERE perfil = 'administrador' ORDER BY id FOR UPDATE")->fetchAll();
    }

    private function preserveAdministrator(array $old, string $profile, int $active): void
    {
        if ($old['perfil'] === 'administrador' && (int) $old['ativo'] === 1
            && ($profile !== 'administrador' || $active !== 1)) {
            $count = (int) $this->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'administrador' AND ativo = 1")->fetchColumn();
            if ($count <= 1) {
                throw new ApiError('Mantenha pelo menos um administrador ativo.', 409);
            }
        }
    }

    private function revokeUserTokens(int $id): void
    {
        $this->query('UPDATE mobile_api_tokens SET revogado_em = UTC_TIMESTAMP() WHERE usuario_id = ? AND id <> ?', [$id, $this->tokenId ?? 0]);
    }

    private function saveUser(array $body, array $current): void
    {
        $this->lockAdministrators();
        $id = Input::id($body, 'id', true);
        $old = $id === null ? null : $this->row('usuarios', $id);
        $values = ['nome' => Input::text($body, 'nome', 100, true), 'email' => Input::email($body),
            'perfil' => Input::choice($body, 'perfil', ['administrador', 'funcionario'], 'funcionario')];
        if ($id === (int) $current['id'] && $values['perfil'] !== $current['perfil']) {
            throw new ApiError('Você não pode remover seu próprio perfil de administrador.', 409);
        }
        if ($old) {
            $this->preserveAdministrator($old, $values['perfil'], (int) $old['ativo']);
            if ($this->hasColumn('usuarios', 'email_verificado_em') && $values['email'] !== strtolower($old['email'])) {
                // O processo de confirmação é controlado pelo site; não contornamos sua política.
                throw new ApiError('Altere o e-mail pelo site para manter o processo de verificação.');
            }
        }
        if ($this->query('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$values['email'], $id ?? 0])->fetch()) {
            throw new ApiError('Este e-mail já está cadastrado.', 409);
        }
        $password = Input::password($body, 'senha', $id === null);
        if ($password !== '') {
            $values['senha'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $saved = $this->write('usuarios', $values, $id);
        if ($id !== null && ($password !== '' || $old['perfil'] !== $values['perfil'])) {
            $this->query('UPDATE usuarios SET auth_version = auth_version + 1 WHERE id = ?', [$saved]);
            revogarCredenciaisUsuario($saved);
            $this->revokeUserTokens($saved);
        }
    }

    private function toggle(string $table, array $body): void
    {
        $id = Input::id($body);
        $row = $this->row($table, $id);
        $this->query("UPDATE `$table` SET ativo = ? WHERE id = ?", [(int) $row['ativo'] === 1 ? 0 : 1, $id]);
    }

    private function toggleUser(array $body, array $current): void
    {
        $this->lockAdministrators();
        $id = Input::id($body);
        if ($id === (int) $current['id']) {
            throw new ApiError('Você não pode desativar sua própria conta.', 409);
        }
        $old = $this->row('usuarios', $id);
        $active = (int) $old['ativo'] === 1 ? 0 : 1;
        $this->preserveAdministrator($old, $old['perfil'], $active);
        $this->query('UPDATE usuarios SET ativo = ?, auth_version = auth_version + 1 WHERE id = ?', [$active, $id]);
        revogarCredenciaisUsuario($id);
        $this->revokeUserTokens($id);
    }

    private function movement(array $body, array $user): void
    {
        $id = Input::id($body, 'produto_id');
        $type = Input::choice($body, 'tipo', ['entrada', 'saida', 'ajuste']);
        $note = Input::text($body, 'observacao', 255);
        $service = new StockService($this->db);

        try {
            if ($type === 'entrada') {
                $quantity = Input::integer($body, 'quantidade', 1);
                $supplierId = Input::id($body, 'fornecedor_id', true);
                $unitValue = null;
                if (isset($body['valor_unitario']) && $body['valor_unitario'] !== '') {
                    $unitValue = (float) Input::money($body, 'valor_unitario');
                }
                $result = $service->registrarEntrada(
                    (int) $user['id'], $id, $quantity, $supplierId, $unitValue, $note
                );
            } elseif ($type === 'saida') {
                $quantity = Input::integer($body, 'quantidade', 1);
                $reason = Input::text($body, 'motivo', 100, false, 'Saída pelo aplicativo');
                if ($reason === '') {
                    $reason = 'Saída pelo aplicativo';
                }
                $result = $service->registrarSaida((int) $user['id'], $id, $quantity, $reason, $note);
            } else {
                Input::admin($user);
                $newBalance = Input::integer($body, 'novo_saldo');
                $reason = Input::text($body, 'justificativa', 255, true);
                $result = $service->ajustarSaldo((int) $user['id'], $id, $newBalance, $reason);
            }
        } catch (DomainException $error) {
            throw new ApiError($error->getMessage(), 422);
        }

        $this->audit(
            $user,
            $type === 'ajuste' ? 'AJUSTAR' : strtoupper($type),
            'Estoque',
            (int) $result['movimentacao_id'],
            'Movimentação registrada pelo aplicativo.',
            ['saldo' => $result['saldo_anterior']],
            ['saldo' => $result['saldo_posterior'], 'produto_id' => $id, 'origem' => 'mobile']
        );
        if ($type !== 'ajuste') {
            notificarMovimentacaoIncomum($result, $type);
        }
    }

    private function audit(
        array $user,
        string $action,
        string $module,
        ?int $recordId,
        string $description,
        ?array $before = null,
        ?array $after = null
    ): void {
        $this->query(
            'INSERT INTO logs_auditoria '
            . '(usuario_id, usuario_nome, acao, modulo, registro_id, descricao, dados_anteriores, dados_novos, ip) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $user['id'], (string) $user['nome'], strtoupper($action), $module, $recordId,
                $description,
                $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    private function readNotification(array $body, array $user): void
    {
        $id = Input::id($body);
        $stmt = $this->query(
            'UPDATE notificacoes SET lida_em = COALESCE(lida_em, NOW()) WHERE id = ? AND usuario_id = ?',
            [$id, (int) $user['id']]
        );
        if ($stmt->rowCount() === 0) {
            $exists = $this->query(
                'SELECT id FROM notificacoes WHERE id = ? AND usuario_id = ?',
                [$id, (int) $user['id']]
            )->fetchColumn();
            if (!$exists) {
                throw new ApiError('Notificação não encontrada.', 404);
            }
        }
    }

    private function readAllNotifications(array $user): void
    {
        $this->query(
            'UPDATE notificacoes SET lida_em = NOW() WHERE usuario_id = ? AND lida_em IS NULL',
            [(int) $user['id']]
        );
    }

    private function settings(array $body): void
    {
        $settings = ['nome_sistema' => Input::text($body, 'nome_sistema', 100, true),
            'nome_empresa' => Input::text($body, 'nome_empresa', 150, true),
            'estoque_minimo_padrao' => (string) Input::integer($body, 'estoque_minimo_padrao')];
        foreach ($settings as $key => $value) {
            $this->query('INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$key, $value]);
        }
    }
}
