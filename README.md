# SmartControl - Sistema de Gerenciamento de Estoque

Projeto de TCC desenvolvido em **PHP 8 puro**, **MySQL/MariaDB**, HTML, CSS e JavaScript, sem framework PHP. O sistema reúne cadastro de produtos, categorias, fornecedores, movimentações, inventário físico, relatórios, usuários, auditoria, anexos, notificações e autenticação.

## Requisitos

- PHP 8.0 ou superior
- MySQL 5.7+ ou MariaDB 10.4+
- Extensões PHP: PDO MySQL, mbstring, openssl, ctype, filter e hash
- Composer, para instalar o PHPMailer
- XAMPP, WAMP, Laragon ou servidor equivalente

## Instalação nova no XAMPP

1. Coloque a pasta do projeto em `C:\xampp\htdocs\smartcontrol`.
2. Inicie **Apache** e **MySQL** no XAMPP.
3. Abra o phpMyAdmin e importe `database/smartcontrol.sql`.
4. Na raiz do projeto, execute:

   ```bash
   composer install
   ```

5. Confira `config/database.php`. O padrão do XAMPP é:
   - banco: `smartcontrol`
   - usuário: `root`
   - senha: vazia
6. Acesse `http://localhost/smartcontrol/`.

### Acesso inicial

- E-mail: `admin@smartcontrol.com`
- Senha: `admin123`

Troque essa senha após o primeiro acesso em **Minha Conta**.

## Atualizando um banco antigo

Se você já usava uma versão anterior do SmartControl, **não importe o arquivo de instalação nova por cima do seu banco**, pois ele recria as tabelas.

1. Faça backup do banco no phpMyAdmin.
2. Importe `database/migrations/2026_09_08_atualizar_schema.sql`.
3. Teste login, produtos, inventário, notificações e anexos.

## Configuração de e-mail

O envio de e-mail é opcional para o restante do sistema funcionar.

1. Execute `composer install`.
2. Copie `config/email.local.example.php` para `config/email.local.php`.
3. Preencha os dados SMTP e a senha de app.
4. `config/email.local.php` está no `.gitignore` e não deve ser enviado ao GitHub.

Se o Composer ou o SMTP ainda não estiverem configurados, o sistema registra o problema no log e evita interromper a aplicação com erro fatal.

## Acessibilidade

A barra global de acessibilidade está disponível nas páginas internas e nas telas de autenticação. Ela oferece:

- aumento, redução e restauração do tamanho do texto;
- alto contraste;
- destaque visual de links;
- leitura do conteúdo em voz alta usando Web Speech quando suportado pelo navegador;
- pausar, retomar e parar a leitura;
- preferências persistidas em `localStorage`;
- atalho `Alt + A` para abrir o painel;
- foco de teclado visível;
- link para pular diretamente ao conteúdo principal;
- cabeçalhos de tabela com associação semântica;
- alternativa textual acessível para o gráfico do dashboard;
- suporte a `prefers-reduced-motion`.

A leitura em voz alta é um recurso auxiliar do navegador e não substitui leitores de tela completos como NVDA, JAWS ou VoiceOver.

Mais detalhes: `docs/ACESSIBILIDADE.md`.

## Estrutura principal

```text
smartcontrol/
├── account/                  # Conta do usuário
├── assets/
│   ├── css/
│   │   ├── style.css         # Layout e componentes globais
│   │   └── accessibility.css # Estados e painel de acessibilidade
│   └── js/
│       ├── script.js         # Menu, senhas e gráfico
│       └── accessibility.js  # Preferências e leitura em voz alta
├── attachments/              # Upload, visualização e exclusão de anexos
├── audit/                    # Histórico de auditoria
├── auth/                     # Login, logout e recuperação/verificação
├── categories/               # Categorias
├── config/                   # Banco, aplicação e e-mail
├── dashboard/                # Painel principal
├── database/
│   ├── smartcontrol.sql      # Instalação nova
│   └── migrations/           # Atualizações de banco existente
├── docs/                     # Documentação técnica
├── includes/                 # Componentes PHP compartilhados
├── inventory/                # Inventário físico
├── notifications/            # Notificações
├── products/                 # Produtos
├── reports/                  # Relatórios
├── settings/                 # Configurações administrativas
├── stock/                    # Entradas, saídas e histórico
├── storage/uploads/          # Arquivos enviados pelo sistema
├── suppliers/                # Fornecedores
├── users/                    # Usuários
├── composer.json
├── composer.lock
└── index.php
```

## Segurança já aplicada

- `password_hash()` e `password_verify()` para senhas;
- PDO com prepared statements;
- tokens CSRF nos formulários sensíveis;
- sanitização de saída com `htmlspecialchars()` pela função `limpar()`;
- limitação de tentativas de login;
- tokens de recuperação e verificação armazenados como hash SHA-256;
- controle de acesso por perfil;
- validação de upload por tamanho, extensão e MIME real;
- auditoria de operações importantes.

## Teste rápido depois da instalação

1. Entre como administrador.
2. Cadastre uma categoria e um fornecedor.
3. Cadastre um produto.
4. Registre uma entrada e uma saída.
5. Abra o inventário físico e salve uma contagem.
6. Confira notificações e auditoria.
7. Teste o painel de acessibilidade, inclusive por teclado.
8. Teste a versão responsiva reduzindo a largura do navegador.

Consulte também `docs/TESTES.md`.
