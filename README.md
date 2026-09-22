# SmartControl

O **SmartControl** é um sistema web de gerenciamento de estoque desenvolvido como Trabalho de Conclusão de Curso. A aplicação mantém a proposta original em PHP puro e MySQL/MariaDB, mas organiza o fluxo de produtos, movimentações, inventários, relatórios, auditoria, notificações e integrações de forma rastreável e adequada para demonstração acadêmica.

## Principais recursos

- autenticação com perfis de administrador e funcionário;
- recuperação de senha e verificação de e-mail;
- proteção contra tentativas repetidas de login;
- cadastro de produtos, categorias e fornecedores;
- SKU e código de barras para localização de produtos;
- entrada, saída e ajuste administrativo de estoque;
- saldo anterior/posterior e origem em cada nova movimentação;
- inventário físico com contagem, divergências e ajuste ao finalizar;
- dashboard com indicadores reais do banco;
- relatórios com filtros, impressão/PDF pelo navegador e CSV;
- API REST v1 autenticada;
- consulta opcional de CEP (ViaCEP com fallback) e CNPJ;
- notificações internas e auditoria administrativa;
- anexos de produtos com validação de MIME e limite de tamanho;
- recursos de acessibilidade e layout responsivo.

## Stack

- PHP 8+
- MySQL 5.7+ ou MariaDB 10.4+
- HTML5, CSS3 e JavaScript
- Apache/XAMPP
- Composer
- PHPMailer

Não é necessário Laravel, React, Node.js ou outro framework para executar o sistema.

## Instalação nova no XAMPP

1. Copie a pasta para `C:\xampp\htdocs\smartcontrol`.
2. Inicie Apache e MySQL.
3. Importe `database/smartcontrol.sql` pelo phpMyAdmin.
4. Na raiz do projeto, execute `composer install`.
5. Copie `.env.example` para `.env`.
6. Ajuste somente o `.env` com as configurações do seu ambiente.
7. Crie o primeiro administrador pelo terminal com `php scripts/create_admin.php`. No XAMPP/Windows, se `php` não estiver no PATH, use `C:\\xampp\\php\\php.exe scripts\\create_admin.php`.
8. Acesse a URL configurada em `APP_URL`.

Exemplo local:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/smartcontrol
APP_BASE_URL=/smartcontrol

DB_HOST=localhost
DB_PORT=3306
DB_NAME=smartcontrol
DB_USER=root
DB_PASSWORD=
```

O `.env` é ignorado pelo Git e **não deve ser enviado ao repositório**.

### Primeiro administrador

A instalação limpa **não contém usuário ou senha administrativa padrão**. Depois de configurar o banco e o `.env`, execute `php scripts/create_admin.php` e informe nome, e-mail e uma senha própria. Isso evita credenciais conhecidas dentro do repositório.

## Atualizando uma instalação existente

Faça backup antes de qualquer alteração no banco.

Execute as migrações em ordem:

1. `database/migrations/2026_09_08_atualizar_schema.sql`
2. `database/migrations/2026_09_22_profissionalizacao.sql`

Não importe `database/smartcontrol.sql` sobre um banco com dados, pois esse arquivo é destinado a uma instalação nova.

Após a migração, execute `database/check_integrity.sql` para conferir inconsistências sem alterar dados e teste principalmente login, produtos, entradas, saídas, ajustes, inventário, relatórios e tokens da API.

## Configuração de e-mail

A configuração recomendada é pelo `.env`:

```env
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=usuario@example.com
SMTP_PASSWORD=senha-de-app-ficticia
SMTP_FROM_EMAIL=nao-responda@example.com
SMTP_FROM_NAME=SmartControl
```

O arquivo legado `config/email.local.php` continua compatível para instalações antigas, mas também é ignorado pelo Git. Nunca envie credenciais reais ao repositório.

## API REST

A API interna fica em `api/v1/`. Ela aceita a sessão autenticada do SmartControl ou um token Bearer criado por administrador em **Configurações > API REST**.

Exemplos de recursos disponíveis:

```text
GET /api/v1/products
GET /api/v1/categories
GET /api/v1/suppliers
GET /api/v1/inventory
GET /api/v1/movements
GET /api/v1/reports
GET /api/v1/external/cep?cep=11740000
GET /api/v1/external/cnpj?cnpj=00000000000000
```

A API é deliberadamente de leitura para os recursos de negócio nesta versão. As movimentações continuam centralizadas nas telas transacionais do sistema, reduzindo o risco de criar caminhos paralelos que alterem estoque sem as mesmas regras de negócio.

Veja `docs/API.md`.

## Estrutura principal

```text
smartcontrol/
├── account/              # Conta do usuário
├── api/v1/               # API REST versionada
├── assets/               # CSS e JavaScript
├── attachments/          # Anexos de produtos
├── audit/                # Auditoria administrativa
├── auth/                 # Login e recuperação/verificação
├── categories/           # Categorias
├── config/               # Ambiente, banco e e-mail
├── dashboard/            # Indicadores
├── database/             # Instalação e migrações SQL
├── docs/                 # Documentação técnica e acadêmica
├── errors/               # Páginas 403/404/500
├── includes/             # Funções e componentes compartilhados
├── inventory/            # Inventário físico
├── notifications/        # Central de notificações
├── products/             # Produtos
├── reports/              # Relatórios e exportações
├── services/             # Integrações externas
├── settings/             # Configurações e tokens de API
├── stock/                # Entrada, saída, ajuste e histórico
├── storage/uploads/      # Arquivos enviados
├── suppliers/            # Fornecedores
└── users/                # Usuários
```

## Regras importantes de estoque

- entradas, saídas, ajustes e finalização de inventário usam transações;
- operações críticas bloqueiam o produto com `SELECT ... FOR UPDATE` antes da alteração;
- uma saída não pode produzir saldo negativo;
- o ajuste administrativo exige justificativa;
- novas movimentações guardam saldo anterior, saldo posterior, origem, responsável e data/hora;
- finalização de inventário preserva a contagem e registra os ajustes gerados.

## Segurança

Entre as proteções aplicadas estão:

- `password_hash()` e `password_verify()`;
- PDO com prepared statements e emulação de prepares desabilitada;
- CSRF em formulários de alteração;
- escaping de saída HTML;
- `session_regenerate_id()` no login;
- cookies `HttpOnly`, `SameSite=Lax` e `Secure` sob HTTPS;
- expiração da sessão por inatividade;
- rate limiting de login;
- autorização por perfil no backend;
- hashes para tokens de recuperação, verificação e API;
- headers HTTP de segurança;
- erros técnicos apenas em log/ambiente de desenvolvimento;
- validação de upload por MIME real;
- proteção de `.env`, SQL, logs e arquivos Composer via Apache;
- mitigação de fórmula em exportações CSV.

Veja `docs/SECURITY.md`.

## Documentação

- `docs/ARCHITECTURE.md`: arquitetura e fluxos.
- `docs/API.md`: autenticação, endpoints e exemplos.
- `docs/DATABASE.md`: modelo e integridade do banco.
- `docs/SECURITY.md`: controles e limitações de segurança.
- `docs/TESTES.md`: checklist de testes manuais e verificações automatizáveis.
- `docs/TCC.md`: material técnico para relatório/apresentação acadêmica.
- `docs/AUDITORIA_FINAL.md`: síntese da auditoria e pendências conhecidas.
- `docs/ACESSIBILIDADE.md`: recursos de acessibilidade.

## Validação antes da apresentação

Depois de instalar/migrar em uma cópia do banco, execute o checklist de `docs/TESTES.md`. Em especial, valide no XAMPP o ciclo completo **produto → entrada → saída → ajuste → inventário → histórico → relatório**, porque testes estáticos de código não substituem um banco MySQL/MariaDB real e um navegador.
