# Segurança

## Controles implementados

### Autenticação e sessão

- Senhas com `password_hash()` e validação com `password_verify()`.
- Novas senhas exigem no mínimo 10 caracteres e no máximo 72 bytes.
- Regeneração do ID após login e logout somente por POST com CSRF.
- Cookies `HttpOnly`, `SameSite=Lax` e `Secure` quando HTTPS está ativo.
- Modo estrito, somente cookies e expiração por inatividade configurável.
- Bloqueio temporário após tentativas inválidas e alerta de acesso suspeito.
- Verificação de e-mail e recuperação usam tokens aleatórios de 256 bits, armazenados como hash, com validade e uso único.

### Autorização

- `exigirLogin()` protege rotas autenticadas.
- `exigirAdministrador()` valida o perfil no servidor.
- Administração de usuários, auditoria, configurações, tokens, ajustes e finalização de inventário exigem administrador.
- Ocultar o item de menu é apenas UX; a decisão de acesso ocorre no backend.

### Entrada, banco e saída

- PDO em modo de exceção, prepared statements nativos e parâmetros vinculados.
- Validação server-side para tipos, tamanhos, faixas, e-mails, CEP, documento e IDs.
- `htmlspecialchars` com UTF-8 para conteúdo exibido.
- CSRF de 256 bits em formulários e requisições Fetch autenticadas.
- Transações e bloqueios pessimistas nas alterações de estoque.
- CSV neutraliza valores que poderiam ser interpretados como fórmulas.

### HTTP e arquivos

- CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` e COOP.
- Listagem de diretórios desativada.
- Acesso web bloqueado a `.env`, configuração, banco, documentação, logs, código-fonte, testes e `vendor`.
- Páginas 403, 404 e 500 não mostram stack trace nem SQL.
- Upload valida tamanho, extensão e MIME real, gera nome aleatório e é servido por rota controlada.
- Logs técnicos ficam em `storage/logs/app.log`; produção não exibe detalhes.

### API e integrações

- Bearer tokens armazenados apenas como SHA-256, com expiração e revogação.
- Rate limiting na API e em ações públicas sensíveis.
- API v1 somente leitura e respostas sem segredos.
- ViaCEP/BrasilAPI são consultadas no servidor, com TLS validado, timeout, limite de resposta e fallback.

## Cabeçalhos esperados

```text
Content-Security-Policy: default-src 'self'; ...; script-src 'self'; connect-src 'self'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Cross-Origin-Opener-Policy: same-origin
```

O CSS ainda permite estilos inline por compatibilidade com estilos de impressão e componentes existentes; scripts inline não são permitidos.

## Configuração de produção

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://estoque.exemplo.com
APP_BASE_URL=
APP_FORCE_HTTPS=true
```

- Use usuário de banco exclusivo, senha forte e privilégios mínimos.
- Instale certificado TLS e redirecione HTTP para HTTPS no servidor.
- Mantenha `.env` fora do Git e faça rotação de credenciais após qualquer exposição.
- Restrinja permissões de escrita a `storage/uploads` e `storage/logs`.
- Troque a senha temporária do administrador imediatamente.
- Mantenha PHP, MariaDB, Apache e dependências atualizados.

## Dados que não devem ser auditados

Senha, hash de senha, token bruto, chave de API, segredo SMTP e conteúdo de sessão nunca devem entrar em `logs_auditoria` ou logs de aplicação.

## Limitações e risco residual

- O rate limiting persiste em banco e foi dimensionado para uma pequena ou média empresa, não para tráfego de internet em larga escala.
- CSP permite `'unsafe-inline'` apenas em estilos; uma futura evolução pode usar nonce/hash e remover essa exceção.
- O controle de acesso possui dois perfis fixos. Permissões granulares podem ser adicionadas se o domínio exigir.
- Backups, firewall, atualizações e monitoramento do sistema operacional continuam sendo responsabilidade do ambiente de hospedagem.

## Comunicação de vulnerabilidade

Em um ambiente institucional, comunique a falha ao responsável pelo projeto sem publicar credenciais ou dados. Registre versão, rota, impacto e passos mínimos de reprodução; depois invalide tokens e credenciais afetadas.

## Revisão de 24/09 — controles adicionais

A assinatura de sessão considera senha, e-mail, perfil e versão de autenticação. `revalidarSessao()` consulta situação e verificação de e-mail em cada requisição web. A API ignora cookies. Senha/e-mail/perfil alterados e bloqueios administrativos invalidam sessões anteriores; tokens auxiliares são revogados nas operações sensíveis.

`UserService` serializa alterações administrativas e protege o último administrador ativo e verificado. Alteração de e-mail por administrador é atestada por ele; em Minha Conta continua exigindo confirmação por e-mail. Auditoria dentro de transações é obrigatória: falha no registro cancela a operação.

Somente `DomainException` de regras conhecidas pode fornecer mensagem ao usuário. `PDOException` nunca é tratada como erro de negócio. Logs técnicos registram classe, código, arquivo e linha, sem mensagem/argumentos da exceção. `APP_FORCE_HTTPS=true` recusa HTTP; cabeçalhos encaminhados são aceitos somente de IPs configurados em `APP_TRUSTED_PROXIES`.

O limitador existente usa contagem e inserção por identificador/IP; não garante limite exato sob chamadas paralelas. Para exposição pública, complemente com limitação no proxy. Não houve auditoria remota de versões das dependências nem teste de invasão nesta entrega.
