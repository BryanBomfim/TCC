# Mobile Flutter + API no XAMPP

O aplicativo está em `mobile/` e a API que ele consome está em `api/mobile/`.
Ambos usam o mesmo banco MySQL da aplicação web.

## Preparar uma cópia nova

1. Clone este repositório dentro de `C:\xampp\htdocs\smartcontrol`.
2. Copie `.env.example` para `.env` e preencha apenas as credenciais locais do banco e SMTP quando necessário. Nunca envie `.env` ao Git.
3. Importe `database/smartcontrol.sql` somente em uma instalação nova, ou execute as migrations existentes em uma base já criada.
4. Execute uma vez `database/migrations/2026_09_24_mobile_api.sql` no mesmo banco configurado no `.env`.
5. Inicie Apache e MySQL pelo XAMPP.
6. Confirme a API em `http://localhost/smartcontrol/api/mobile/index.php?resource=health`.

A resposta esperada contém `status: ok` e `version: 2`.

## Deixar o aplicativo acessível fora da rede local

1. Instale e entre no Tailscale no notebook.
2. Dê dois cliques em `INICIAR_MOBILE_INTERNET.cmd` na raiz do repositório.
3. Aceite o pedido de administrador do Windows e aguarde a mensagem verde.
4. Mantenha o notebook ligado, com Apache, MySQL e Tailscale ativos durante o uso do APK.

O script valida o banco e a API, publica somente a rota `api/mobile` por HTTPS e grava o endereço usado localmente em `mobile/ENDERECO_DO_SERVIDOR.txt`. Esse arquivo, logs, builds e APKs não são enviados ao Git.

Para desligar o acesso público, execute `DESLIGAR_MOBILE_INTERNET.cmd`.

## Desenvolver e testar o Flutter

No diretório `mobile/`:

```powershell
flutter pub get
flutter analyze
flutter test
flutter build apk --release --dart-define=API_BASE_URL=https://SEU-HOST/index.php
```

O APK de distribuição exige API HTTPS. O endereço de produção é informado no momento da compilação e não fica gravado no repositório.
