# SmartControl

Sistema web profissional de gerenciamento de estoque para pequenas e médias empresas, desenvolvido como TCC com PHP 8.2, MariaDB, HTML, CSS e JavaScript. O projeto combina controle transacional de saldo, inventário físico, relatórios, API REST, integrações, alertas, auditoria, segurança e acessibilidade sem depender de um framework pesado.

> Atualizando a versão enviada em 24/09? Leia primeiro `LEIA-ME-ATUALIZACAO.md`. Não reaplique migrações já executadas.

## Funcionalidades

- usuários com perfis administrador e funcionário;
- produtos, categorias, fornecedores e anexos;
- SKU automático, código de barras e seleção por leitor USB;
- entradas, saídas e ajustes com saldo anterior/posterior;
- bloqueio de estoque negativo e transações com `FOR UPDATE`;
- inventário físico com divergências e ajustes automáticos;
- dashboard com indicadores e gráfico semanal;
- relatórios filtráveis, impressão e CSV seguro;
- API REST v1 somente leitura com Bearer token;
- consulta opcional de CEP e CNPJ com fallback manual;
- alertas de estoque, inventário, volume e segurança;
- auditoria administrativa e páginas 403/404/500;
- interface responsiva e painel de acessibilidade.

## Requisitos

- PHP 8.2 ou superior;
- MariaDB 10.4+ ou MySQL equivalente;
- Apache com `mod_rewrite` e `mod_headers`;
- extensões PHP: ctype, curl, fileinfo, filter, hash, mbstring, openssl, PDO e pdo_mysql;
- Composer;
- XAMPP, WAMP, Laragon ou ambiente compatível.

## Instalação no XAMPP

1. Coloque o projeto em `C:\xampp\htdocs\smartcontrol`.
2. Inicie Apache e MariaDB/MySQL. Evite outra pilha web usando as mesmas portas.
3. Importe `database/smartcontrol.sql` em uma base nova.
4. Na raiz do projeto, instale as dependências     :

   ```bash
   composer install --no-dev
   ```

5. Copie `.env.example` para `.env` e ajuste o ambiente:

   ```dotenv
   APP_ENV=development
   APP_DEBUG=false
   APP_URL=http://localhost/smartcontrol
   APP_BASE_URL=/smartcontrol
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=smartcontrol
   DB_USER=root
   DB_PASSWORD=
   SMTP_ENABLED=false
   ```

6. Confirme que `storage/uploads/products` e `storage/logs` têm permissão de escrita.
7. Acesse `http://localhost/smartcontrol/`.

### Acesso inicial da instalação limpa

- E-mail: `admin@smartcontrol.com`
- Senha temporária: `SmartControl@2026!`

O primeiro acesso exige definir uma senha exclusiva em Minha Conta antes de usar o estoque. Configure SMTP antes de cadastrar outros usuários.

## Atualização de uma base existente

Não importe `smartcontrol.sql` sobre dados reais. O arquivo cria o esquema de uma instalação nova; não é um atualizador e não contém DROP.

1. Faça backup completo.
2. Para a versão do ZIP enviado em 24/09/2026, aplique uma única vez apenas `2026_09_24_conclusao.sql`. Para versões mais antigas, confira o esquema e identifique as migrações ainda não aplicadas.
3. Execute `composer install`.
4. Revise o `.env`.
5. Execute os testes e o checklist de regressão.

## Variáveis de ambiente

| Variável | Uso |
|---|---|
| `APP_ENV` | `development`, `testing` ou `production` |
| `APP_DEBUG` | exibe detalhes somente fora de produção |
| `APP_URL` | URL pública completa para links enviados |
| `APP_BASE_URL` | subdiretório HTTP, por exemplo `/smartcontrol` |
| `APP_SESSION_TIMEOUT` | inatividade em segundos |
| `APP_FORCE_HTTPS` | recusa HTTP e exige cookie seguro |
| `APP_TRUSTED_PROXIES` | IPs dos proxies confiáveis, separados por vírgula; vazio por padrão |
| `EXTERNAL_API_TIMEOUT` | timeout de ViaCEP/BrasilAPI |
| `DB_*` | conexão MariaDB/MySQL |
| `SMTP_*` | servidor, autenticação, remetente e criptografia |

O `.env` é ignorado pelo Git. Nunca versione credenciais reais, dumps, tokens ou logs.

## E-mail

O SMTP é opcional. Configure `SMTP_ENABLED=true` e as variáveis `SMTP_*` para verificação e recuperação por e-mail. Sem SMTP, o restante do sistema e os alertas internos continuam funcionando; a falha é registrada tecnicamente sem expor segredo ao usuário.

## API REST

Um administrador emite e revoga tokens em **Configurações > Tokens da API**.

```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://localhost/smartcontrol/api/v1/products?stock_status=low"
```

Endpoints: `products`, `categories`, `suppliers`, `inventory`, `movements` e `reports`. Consulte `docs/API.md`.

## Testes

```bash
composer validate --no-check-publish
composer test
composer run-script test:http
```

O teste HTTP exige Apache apontando para esta cópia do projeto. A consulta externa real é opcional com `RUN_EXTERNAL_LOOKUP=1`. Instruções e checklist completo: `docs/TESTES.md`.

## Estrutura principal

```text
smartcontrol/
├── api/v1/          # API REST versionada
├── assets/          # CSS e JavaScript
├── auth/            # autenticação e recuperação
├── config/          # bootstrap, banco e e-mail
├── database/        # instalação e migrations
├── docs/            # documentação técnica e do TCC
├── includes/        # componentes e helpers compartilhados
├── integrations/    # gateway para serviços externos
├── inventory/       # inventário físico
├── reports/         # relatórios e exportação
├── src/             # serviços PSR-4 e infraestrutura HTTP
├── stock/           # entrada, saída, ajuste e histórico
├── storage/         # uploads e logs protegidos
├── tests/           # suíte automatizada
└── demais módulos   # produtos, fornecedores, usuários etc.
```

## Segurança

O projeto usa hash de senha, CSRF, sessão endurecida, autorização server-side, prepared statements, validação de upload, tokens em hash, rate limiting, CSP, proteção de arquivos internos, tratamento seguro de erros e auditoria sem segredos. Para implantação: `docs/SECURITY.md`.

## Documentação

- `docs/ARCHITECTURE.md`: camadas, módulos e fluxos.
- `docs/API.md`: autenticação e endpoints.
- `docs/DATABASE.md`: tabelas, relações, integridade e migrations.
- `docs/SECURITY.md`: controles e checklist de produção.
- `docs/TESTES.md`: automação e casos manuais.
- `docs/TCC.md`: problema, objetivos, requisitos, regras e roteiro da banca.
- `docs/FINAL_AUDIT.md`: achados, correções, evidências e riscos residuais.
- `docs/ACESSIBILIDADE.md`: recursos assistivos.

## Licença

Projeto acadêmico de uso proprietário. Consulte o autor antes de redistribuir ou explorar comercialmente.
