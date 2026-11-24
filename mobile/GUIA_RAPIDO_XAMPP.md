# Guia rápido — SmartControl Mobile + XAMPP

## 1. Onde cada parte ficou

| Parte | Caminho |
| --- | --- |
| Projeto Flutter | `C:\Users\danim\OneDrive\Documentos\TCC\mobile` |
| APK gerado localmente | `mobile\build\app\outputs\flutter-apk\app-release.apk` |
| Sistema web usado pelo XAMPP | `C:\xampp\htdocs\smartcontrol` |
| API móvel instalada | `C:\xampp\htdocs\smartcontrol\api\mobile` |
| Migração do banco | `C:\xampp\htdocs\smartcontrol\database\migrations\2026_09_24_mobile_api.sql` |
| Script para iniciar tudo | `C:\Users\danim\OneDrive\Documentos\TCC\INICIAR_MOBILE_INTERNET.cmd` |
| Configuração Flutter da API | `mobile\lib\core\api_config.dart` |
| Cliente HTTP | `mobile\lib\data\api_client.dart` |
| Estado e sincronização | `mobile\lib\data\inventory_store.dart` |
| Modelos Dart | `mobile\lib\models\models.dart` |
| Telas | `mobile\lib\screens` |

## 2. Iniciar antes da apresentação

1. Ligue o notebook e conecte-o a qualquer internet.
2. Dê dois cliques em `INICIAR_MOBILE_INTERNET.cmd`.
3. Aceite a janela de administrador.
4. Espere a confirmação verde.
5. Não feche o XAMPP nem desligue o notebook enquanto usar o aplicativo.

O script inicia o Apache e o MySQL do XAMPP, verifica a API v2 e publica a API HTTPS pelo Tailscale Funnel.

## 3. Instalar no Android

1. Copie `SmartControl-Android-v1.1.0-XAMPP-internet.apk` para o telefone.
2. Toque no arquivo.
3. Se o Android pedir, permita a instalação pelo aplicativo usado para abrir o APK.
4. Se houver uma versão antiga com assinatura incompatível, desinstale o SmartControl antigo e instale esta versão.
5. Abra o SmartControl e entre com a conta do web.

O usuário deve estar ativo, com e-mail verificado e senha definitiva. A senha temporária padrão é recusada pela API atual.

## 4. Confirmar o servidor

Abra no navegador do telefone:

`https://SEU-HOST/index.php?resource=health`

Use o endereço exibido pelo script `INICIAR_MOBILE_INTERNET.cmd` ou salvo em
`mobile\ENDERECO_DO_SERVIDOR.txt`.

O resultado correto contém:

```json
{"data":{"status":"ok","service":"SmartControl Mobile API","version":2}}
```

## 5. Se não conectar

1. Execute `INICIAR_MOBILE_INTERNET.cmd` novamente e aguarde a mensagem verde.
2. Confirme que Apache e MySQL estão verdes no XAMPP.
3. Teste o endereço de saúde acima no navegador do celular.
4. Confirme que o notebook ainda tem internet e que o Tailscale está conectado.
5. Feche e abra o aplicativo após o endereço de saúde responder.

## 6. Gerar outro APK

No PowerShell, dentro de `TCC\mobile`, use uma instalação funcional do Flutter e execute:

```powershell
flutter pub get
flutter analyze
flutter test
flutter build apk --release --dart-define=API_BASE_URL=https://SEU-HOST/index.php
```

O resultado padrão fica em `mobile\build\app\outputs\flutter-apk\app-release.apk`.
