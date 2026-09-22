# Segurança do SmartControl

## Controles implementados

### Autenticação e senha

- senhas persistidas com `password_hash()`;
- comparação com `password_verify()`;
- novas senhas exigem no mínimo 8 caracteres nas telas de criação/edição/conta/recuperação;
- regeneração do ID de sessão após login;
- encerramento da sessão no logout;
- expiração por inatividade configurável por `SESSION_TIMEOUT`.

### Força bruta

`tenta­tivas_login` registra sucesso/falha por e-mail e IP. Cinco falhas recentes bloqueiam temporariamente novas tentativas para o mesmo par e-mail/IP. Uma sequência suspeita para uma conta existente cria alerta interno para administradores.

### Sessão e cookies

- `session.use_strict_mode`;
- cookies apenas para sessão;
- `HttpOnly`;
- `SameSite=Lax`;
- `Secure` quando a requisição está em HTTPS.

### CSRF e autorização

Formulários de alteração validam token CSRF. Permissões administrativas são verificadas no backend com `exigirAdministrador()`, não apenas escondendo botões.

O sistema também impede que o último administrador ativo seja rebaixado por edição de perfil.

### SQL Injection e XSS

Consultas com entrada do usuário utilizam PDO/prepared statements. Saídas HTML dinâmicas usam `limpar()`, baseada em `htmlspecialchars()`.

### Tokens

Tokens de recuperação, verificação de e-mail e API são criados com bytes aleatórios criptograficamente seguros. O banco armazena somente hashes SHA-256 dos valores utilizados como segredo.

O valor bruto de um token da API é apresentado somente uma vez no momento da criação.

### Uploads

Anexos:

- exigem autenticação e CSRF;
- possuem limite de 5 MB;
- aceitam PDF, JPEG, PNG e WEBP;
- validam MIME real com `finfo`;
- recebem nome aleatório no disco;
- não executam PHP no diretório de uploads.

### Headers e erros

A aplicação envia `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` e `Permissions-Policy`.

Em produção, `APP_DEBUG=false` deve ser obrigatório. Erros técnicos são registrados no servidor, enquanto o usuário recebe uma mensagem genérica. O Apache também bloqueia acesso web direto a `.env`, SQL, logs e arquivos Composer.

### Exportação CSV

Textos iniciados por caracteres interpretáveis como fórmulas (`=`, `+`, `-`, `@`) recebem neutralização antes da exportação.

## Credenciais

Credenciais reais nunca devem aparecer no código. Use `.env`, que está no `.gitignore`. O projeto contém apenas `.env.example` com valores fictícios.

Em produção:

```env
APP_ENV=production
APP_DEBUG=false
```

Use HTTPS e uma conta de banco exclusiva com apenas os privilégios necessários ao schema do SmartControl.

## Limitações conhecidas

- não há MFA;
- não há WAF próprio ou proteção distribuída contra ataques de grande escala;
- a aplicação depende da configuração correta de HTTPS/Apache em produção;
- consultas ViaCEP/BrasilAPI são serviços externos opcionais e devem respeitar limites/políticas dos provedores;
- a política de senha usa comprimento mínimo, sem medidor de senha comprometida.

Esses itens não impedem o objetivo acadêmico do sistema, mas são pontos legítimos de evolução futura.
