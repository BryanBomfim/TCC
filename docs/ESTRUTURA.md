# Estrutura do SmartControl

A organização mantém as pastas de negócio como rotas públicas do PHP. Isso evita quebrar URLs existentes enquanto separa recursos compartilhados e documentação.

## Pastas de aplicação

- `auth/`: autenticação, recuperação de senha e verificação de e-mail.
- `dashboard/`: resumo e indicadores.
- `products/`, `categories/`, `suppliers/`: cadastros principais.
- `stock/`: entrada, saída e histórico de estoque.
- `inventory/`: inventário físico e ajustes.
- `attachments/`: anexos de produtos.
- `notifications/`: central de notificações.
- `reports/`: relatórios.
- `users/`, `audit/`, `settings/`: áreas administrativas.
- `account/`: dados do usuário autenticado.

## Recursos compartilhados

- `includes/header.php`: cabeçalho e notificações.
- `includes/sidebar.php`: navegação principal.
- `includes/footer.php`: fechamento do layout e scripts.
- `includes/functions.php`: autenticação, CSRF, auditoria, notificações e utilitários.
- `includes/accessibility.php`: painel global de acessibilidade.
- `includes/email.php`: envio de e-mail com falha controlada.

## Front-end

- `assets/css/style.css`: identidade visual, layout, formulários, tabelas, componentes e responsividade.
- `assets/css/accessibility.css`: foco, contraste, painel e preferências de acessibilidade.
- `assets/js/script.js`: comportamento geral.
- `assets/js/accessibility.js`: recursos assistivos e persistência local.

## Banco

- `database/smartcontrol.sql`: instalação limpa e completa.
- `database/migrations/`: scripts para evoluir instalações existentes sem apagar os dados.
