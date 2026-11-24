# Mapa da conversão web → Flutter

Referência principal: [BryanBomfim/TCC](https://github.com/BryanBomfim/TCC), SmartControl em PHP/MySQL. O app fica em `TCC/mobile`; a API está em `TCC/tcc Código web/api/mobile` e utiliza o modelo de dados do web.

## Módulos do repositório principal

| Módulo web | Arquivos Flutter | Comportamento mobile |
| --- | --- | --- |
| `auth/login.php`, `auth/logout.php` | `lib/screens/login_screen.dart`, `lib/app.dart` | Login por e-mail/senha, sessão na API e saída. |
| `dashboard/index.php` | `lib/screens/dashboard_screen.dart` | Indicadores de estoque, produtos com estoque baixo e movimentações recentes. |
| `products/` | `lib/screens/products_screen.dart` | Consulta, detalhes, criação e edição de produtos. |
| `categories/` | `lib/screens/directory_screen.dart` | Lista, criação, edição e ativação/inativação de categorias. |
| `suppliers/` | `lib/screens/directory_screen.dart` | Lista e manutenção de fornecedores com seus campos próprios. |
| `stock/entry.php`, `stock/exit.php`, `stock/history.php` | `lib/screens/stock_screen.dart` | Entradas, saídas e histórico; saldo validado pelo servidor. |
| `users/` | `lib/screens/users_screen.dart` | Gestão de usuários restrita ao administrador. |
| `reports/` | `lib/screens/reports_screen.dart` | Consultas e relatórios em telas Flutter. |
| `settings/index.php` | `lib/screens/settings_screen.dart` | Configurações gerais restritas ao administrador. |
| CSS, cabeçalho e menu PHP | `lib/core/app_colors.dart`, `lib/app.dart`, `lib/screens/home_shell.dart`, `lib/widgets/common.dart` | Tema e navegação refeitos com componentes Flutter. |
| `config/database.php`, `includes/functions.php` | `api/mobile/`, no servidor | Acesso ao banco, autenticação, autorização e validação permanecem no PHP. |

Os diretórios PHP da última linha são relativos à pasta web, não a `mobile/lib`.

## Extensões da cópia local examinada

Além dos módulos do repositório, `tcc Código web` contém extensões que não são convertidas nesta etapa:

| Extensão local | Situação nesta entrega |
| --- | --- |
| `account/` — conta pessoal | Sem tela Flutter correspondente. |
| `attachments/` — anexos | Sem upload, visualização ou exclusão de arquivos pelo mobile. |
| `audit/` — auditoria central | Sem painel Flutter; o histórico de estoque não substitui essa auditoria. |
| `inventory/` — inventário físico | Sem fluxo de contagem/finalização Flutter. “Estoque” no mobile significa entrada/saída e saldo. |
| `notifications/` | Sem central de notificações ou push mobile. |
| `auth/esqueci_senha.php`, `auth/redefinir_senha.php` | Recuperação de senha continua no web. |
| Verificação e reenvio de e-mail | Fluxo continua no web; o login da API respeita a verificação quando presente no banco. |

## Dados compartilhados e demonstração

No modo real, produtos, categorias, fornecedores, usuários, saldo, movimentações e configurações são lidos/gravados no MySQL. O app busca os dados após operações e pela atualização; não implementa sincronização automática em tempo real entre dispositivos.

No modo demonstração, são exemplos em memória, ativados explicitamente com `--dart-define=DEMO_MODE=true`. Não se trata de uma cópia persistente ou de um cache offline do banco.

O token da sessão autentica as chamadas à API. O PHP verifica a senha contra o hash no banco. As permissões administrativas são verificadas pelo servidor, além da restrição do menu Flutter.

## Endpoints da integração

O endereço base termina em `api/mobile/index.php`; o parâmetro `resource` seleciona o recurso, como em `?resource=health`. O cliente Flutter monta as chamadas, então não é necessário editar cada tela para trocar o servidor.

| Recurso | Finalidade |
| --- | --- |
| `health` | Disponibilidade HTTP da API; não testa o banco. |
| `login`, `logout` | Abrir e encerrar sessão. |
| `bootstrap` | Carregar dados permitidos ao usuário autenticado. |
| `products` | Manutenção de produtos. |
| `categories` | Manutenção de categorias. |
| `suppliers` | Manutenção de fornecedores. |
| `users` | Manutenção administrativa de usuários. |
| `movements` | Registrar movimentações de estoque. |
| `settings` | Salvar configurações gerais. |
| `products/toggle`, `categories/toggle`, `suppliers/toggle`, `users/toggle` | Alternar ativo/inativo do registro; usuários exigem administrador. |

Relatórios são apresentados a partir dos dados disponíveis ao aplicativo. Impressão e exportações do web não devem ser presumidas equivalentes sem conferir os recursos apresentados na tela Flutter.

Para ampliar o escopo, crie os endpoints e regras do módulo no servidor e depois seus modelos, métodos de dados e telas Flutter. Consulte [GUIA_PASSO_A_PASSO.md](../GUIA_PASSO_A_PASSO.md) para arquivos e execução.
