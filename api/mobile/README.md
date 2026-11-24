# API móvel v2

Integra o aplicativo Flutter ao mesmo banco e às mesmas regras da versão web 4.0.

## Instalação no XAMPP

1. Mantenha esta pasta em `C:\xampp\htdocs\smartcontrol\api\mobile`.
2. Aplique uma única vez `database/migrations/2026_09_24_mobile_api.sql` no banco configurado no `.env`.
3. Inicie Apache e MySQL no XAMPP.
4. Abra `http://localhost/smartcontrol/api/mobile/index.php?resource=health`.

Resposta esperada:

```json
{"data":{"status":"ok","service":"SmartControl Mobile API","version":2}}
```

## Autenticação

O aplicativo envia e-mail e senha para `POST ?resource=login`. A API devolve um Bearer token temporário e armazena apenas o hash. Alterações de senha, perfil, status ou versão de autenticação invalidam o acesso.

Usuários precisam estar ativos, com e-mail verificado e senha própria. A senha temporária da instalação inicial deve ser alterada no site.

## Recursos

- `health`, `login`, `logout` e `bootstrap`;
- produtos, categorias, fornecedores e usuários;
- entrada, saída e ajuste administrativo usando `StockService`;
- configurações gerais;
- leitura de inventários, itens da contagem, notificações e auditoria;
- marcação individual ou coletiva de notificações como lidas.

As operações administrativas são verificadas novamente no servidor. Movimentações atualizam saldo, histórico, auditoria e alertas na mesma transação.

## Variáveis opcionais do `.env`

```dotenv
MOBILE_TOKEN_HOURS=24
MOBILE_ALLOWED_ORIGINS=
```

Aplicativos Android/iOS nativos normalmente não enviam `Origin`. Preencha `MOBILE_ALLOWED_ORIGINS` apenas se também houver um cliente web autorizado.

## Testes seguros

```powershell
php api/mobile/tests/test_validation.php
php api/mobile/tests/test_http.php
```

O teste de banco é opt-in porque altera dados e deve usar exclusivamente uma base descartável.
