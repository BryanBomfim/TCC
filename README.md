# SmartControl — Sistema de Gerenciamento de Estoque (TCC)

Projeto acadêmico desenvolvido em **PHP 8 puro** (sem frameworks) + **MySQL**,
sem bibliotecas de front-end (sem Bootstrap/Tailwind/Chart.js). O CSS/JS
inclusos são mínimos, apenas para tornar as telas utilizáveis — o foco
deste momento do projeto é a funcionalidade de back-end.

## Prioridade atual testada/revisada
Login, controle de usuários (admin/funcionário) e dashboard. Os demais
módulos (categorias, fornecedores, produtos, estoque, relatórios,
configurações) já estão implementados e seguem o mesmo padrão de código,
mas ainda não passaram por uma segunda rodada de revisão.

## Requisitos
- PHP 8.0+ com extensão PDO MySQL habilitada
- MySQL 5.7+ ou MariaDB
- Servidor local: XAMPP, WAMP, Laragon ou similar

## Instalação

1. Copie a pasta `smartcontrol` para o diretório público do seu servidor
   (ex.: `htdocs/smartcontrol` no XAMPP).

2. Crie o banco de dados executando o script `database/smartcontrol.sql`
   pelo phpMyAdmin (aba "Importar" ou "SQL").

3. Ajuste as credenciais do banco em `config/database.php`, se necessário
   (por padrão: host `localhost`, usuário `root`, sem senha).

4. **Defina a senha do administrador** acessando pelo navegador:

   ```
   http://localhost/smartcontrol/database/install_admin.php
   ```

   Isso vai gerar corretamente o hash bcrypt da senha `admin123` para o
   usuário `admin@smartcontrol.com`, usando a versão do PHP do seu
   ambiente (por segurança, o script não vem com um hash fixo embutido).
   **Apague esse arquivo depois de rodar.**

5. Acesse o sistema:

   ```
   http://localhost/smartcontrol/
   ```

   - **E-mail:** admin@smartcontrol.com
   - **Senha:** admin123

6. Se a pasta do projeto tiver outro nome (não `smartcontrol`), ajuste a
   constante `BASE_URL` em `config/config.php`.

## Estrutura de pastas

```
smartcontrol/
├── auth/            → login.php, logout.php
├── categories/       → CRUD de categorias
├── config/          → config.php, database.php
├── dashboard/        → painel principal
├── database/         → script SQL + instalador do admin
├── includes/         → header, sidebar, footer, funções auxiliares
├── products/          → CRUD de produtos + visualização detalhada
├── reports/           → relatórios (produtos, movimentações, etc.)
├── settings/          → configurações gerais (admin)
├── stock/            → entrada/saída de estoque + histórico
├── suppliers/         → CRUD de fornecedores
├── users/            → CRUD de usuários (admin)
├── assets/           → css/style.css e js/script.js
└── index.php         → redireciona para login ou dashboard
```

## Perfis de acesso

| Ação                          | Administrador | Funcionário |
|-------------------------------|:--------------:|:-----------:|
| Login / logout                | ✔              | ✔           |
| Ver dashboard                  | ✔              | ✔           |
| Cadastrar/editar produtos       | ✔              | ✔           |
| Cadastrar/editar categorias      | ✔              | ✔           |
| Cadastrar/editar fornecedores     | ✔              | ✔           |
| Registrar entrada/saída de estoque | ✔              | ✔           |
| Consultar relatórios            | ✔              | ✔           |
| Gerenciar usuários              | ✔              | ✘           |
| Acessar configurações            | ✔              | ✘           |

O controle é feito pelas funções `exigirLogin()` e `exigirAdministrador()`
em `includes/functions.php`, chamadas no topo de cada página.

## Roteiro sugerido de testes (foco atual)

1. Acesse `/auth/login.php` e entre com o usuário admin.
2. Vá em **Usuários** → cadastre um novo usuário com perfil "Funcionário".
3. Faça logout e entre com o novo usuário — confirme que o menu **não**
   mostra "Usuários" nem "Configurações".
4. Tente acessar diretamente `/users/list.php` logado como funcionário —
   deve aparecer a página de erro 403 (acesso negado).
5. Volte a logar como admin, edite o usuário criado, altere o perfil para
   "Administrador" e confirme que o acesso muda.
6. Confira o **Dashboard**: cartões de indicadores, produtos com estoque
   baixo, últimas movimentações e o gráfico de barras dos últimos 7 dias
   (não haverá dados de movimentação até que produtos e movimentações
   sejam cadastrados).

## Observações técnicas

- Senhas são armazenadas com `password_hash()` (bcrypt) e verificadas com
  `password_verify()`.
- Todos os formulários usam token CSRF (`gerarTokenCSRF()` /
  `validarTokenCSRF()`).
- Todas as consultas usam PDO com *prepared statements* (proteção contra
  SQL Injection).
- Saídas de dados no HTML passam por `htmlspecialchars()` via a função
  `limpar()` (proteção contra XSS).
- Um administrador não pode inativar a própria conta (`users/toggle.php`).
