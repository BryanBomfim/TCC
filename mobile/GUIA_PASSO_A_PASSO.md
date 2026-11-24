# SmartControl Mobile: passo a passo

## Apresentação com celular e notebook em redes diferentes

O APK `SmartControl-internet-fixo-release.apk` já contém o endereço HTTPS fixo da API. A tela de login continua mostrando somente e-mail e senha. O celular não precisa instalar Tailscale e pode usar Wi-Fi ou dados móveis diferentes da conexão do notebook.

Antes da apresentação:

1. Dê dois cliques em `INICIAR_MOBILE_INTERNET.cmd`, na pasta principal `TCC`.
2. Aceite a confirmação do Windows e espere a mensagem verde **SMARTCONTROL DISPONÍVEL PARA O APLICATIVO**.
3. Mantenha o notebook ligado e conectado à internet.
4. Abra o APK no celular e entre com a mesma conta usada no sistema web.

Quando o MySQL estiver desligado, o iniciador abre o painel do USBWebserver visivelmente. Se o banco já estiver ativo, ele aproveita o processo existente e não abre uma segunda janela.

Execute o iniciador novamente depois de reiniciar o notebook ou trocar a conexão dele. O atalho informa em verde quando o DNS público já está confirmado e em amarelo quando ainda está propagando. A mensagem principal verde aparece assim que banco, API e Funnel estão ligados; não é necessário reinstalar o APK.

Depois da apresentação, dê dois cliques em `DESLIGAR_MOBILE_INTERNET.cmd`. Isso remove o acesso externo sem apagar o banco ou alterar o sistema web local.

O endereço embutido é `https://smartcontrol-tcc.tail495b46.ts.net/index.php`. Somente a pasta da API mobile é publicada; phpMyAdmin, Apache e a porta do MySQL não são expostos pelo Funnel.

## 1. Qual pasta abrir

Abra no VS Code ou Android Studio:

```text
C:\Users\danim\OneDrive\Documentos\TCC\mobile
```

O código já está colocado nos arquivos do projeto. Você não precisa criar outro aplicativo nem copiar tudo para `main.dart`. Para estudar ou alterar uma tela, use a tabela da seção 2.

A pasta principal possui cópias de projetos. Nesta entrega, a relação usada é:

```text
TCC/
├── mobile/                      ← aplicativo Flutter desta entrega
│   ├── lib/
│   ├── test/
│   ├── android/
│   ├── ios/
│   ├── pubspec.yaml
│   ├── README.md
│   ├── GUIA_PASSO_A_PASSO.md
│   └── docs/MAPA_WEB_FLUTTER.md
├── backups/
│   └── mobile_antes_migracao_20260916/  ← fontes anteriores
├── tcc Código web/              ← servidor e API usados pelo aplicativo
│   ├── api/mobile/              ← API adicionada para o Flutter
│   ├── database/
│   │   ├── smartcontrol.sql     ← instalação inicial do banco web
│   │   └── mobile_api.sql       ← tabelas auxiliares da API
│   ├── config/database.php     ← configuração do web existente
│   └── TCC/                    ← outra cópia antiga do web
└── req case/
    └── TCC/mobile/              ← outra cópia; não é o app alterado
```

Use **`TCC/mobile`** para o Flutter e **`TCC/tcc Código web`** para o servidor. Se abrir `req case/TCC/mobile` ou servir `tcc Código web/TCC`, verá outra versão.

O caminho dos dados reais é:

```text
Tela Flutter → API PHP → MySQL do SmartControl
                         ↑
                 Sistema web existente
```

Um produto gravado pelo mobile aparece no web depois de atualizar a página, desde que ambos apontem para o mesmo banco. A atualização entre aparelhos ocorre ao buscar os dados novamente; esta versão não implementa sincronização em tempo real nem operações offline.

## 2. Onde está cada código

Os caminhos desta primeira tabela são relativos a `TCC/mobile`.

| Arquivo ou pasta | O que você altera ali |
| --- | --- |
| `pubspec.yaml` | Nome interno, versão, dependências e configuração Flutter. Depois de mudar dependências, execute `flutter pub get`. |
| `lib/main.dart` | Ponto de entrada que inicia o aplicativo. |
| `lib/app.dart` | Tema e fluxo entre login e área autenticada. |
| `lib/core/app_colors.dart` | Cores da identidade visual. |
| `lib/core/api_config.dart` | Leitura de `API_BASE_URL` e `DEMO_MODE`. Para trocar de servidor, prefira o parâmetro do comando, sem alterar cada tela. |
| `lib/data/api_client.dart` | Chamadas HTTP, autenticação e leitura das respostas da API. |
| `lib/data/inventory_store.dart` | Estado do app, carregamento dos dados e operações de cadastro/estoque. |
| `lib/data/demo_data.dart` | Exemplos usados exclusivamente no modo demonstração. |
| `lib/models/models.dart` | Classes de usuário, produto, categoria, fornecedor, movimentação e configurações. |
| `lib/screens/login_screen.dart` | E-mail, senha, carregamento e mensagens do login. |
| `lib/screens/home_shell.dart` | Menu, navegação principal e saída da conta. |
| `lib/screens/dashboard_screen.dart` | Indicadores de estoque e últimas movimentações. |
| `lib/screens/products_screen.dart` | Busca, listagem, detalhes, cadastro e edição de produtos. |
| `lib/screens/directory_screen.dart` | Categorias e fornecedores, com seus campos próprios. |
| `lib/screens/stock_screen.dart` | Entrada, saída e histórico do estoque. |
| `lib/screens/users_screen.dart` | Gestão de usuários, disponível para administradores. |
| `lib/screens/reports_screen.dart` | Relatórios e filtros de consulta. |
| `lib/screens/settings_screen.dart` | Nome do sistema, empresa e estoque mínimo padrão. |
| `lib/widgets/common.dart` | Cartões, campos e componentes reutilizados. |
| `android/app/src/main/AndroidManifest.xml` | Nome do aplicativo e permissão de internet no Android. |
| `android/app/src/debug/AndroidManifest.xml` | Permissão de conexão HTTP local durante desenvolvimento. |
| `android/app/build.gradle` | Identificador Android e configuração de compilação/assinatura usada pelo projeto. |
| `test/` | Testes automatizados. |

A API fica fora da pasta Flutter, em `TCC/tcc Código web`:

| Arquivo | Finalidade |
| --- | --- |
| `api/mobile/index.php` | Recebe requisições JSON: autenticação, dados e operações do aplicativo. |
| `api/mobile/api.php` | Regras de negócio, autenticação e gravações dentro de transações. |
| `api/mobile/validation.php` | Validação de texto, números, senhas e permissões. |
| `api/mobile/config.php` | Carrega a configuração e cria a conexão PDO com MySQL. |
| `api/mobile/config.local.php.example` | Modelo dos dados de conexão do MySQL. |
| `api/mobile/config.local.php` | Sua configuração local; você a cria copiando o exemplo. |
| `api/mobile/migrate.php` | Cria as tabelas auxiliares da API no banco configurado. |
| `api/mobile/install_admin.php` | Define a primeira senha de um administrador existente cujo campo de senha ainda está vazio. |
| `database/mobile_api.sql` | Estrutura das tabelas de tokens e tentativas de login. |

Não coloque PHP dentro de `mobile/lib`, nem usuário/senha do MySQL no Dart. O aplicativo recebe o endereço da API; o servidor guarda as credenciais do banco.

## 3. Preparar Flutter e Android no Windows

O projeto requer Flutter 3.32 ou superior e Dart 3.8 ou superior. O Dart acompanha o Flutter.

Foi localizado um Flutter 3.32.4 / Dart 3.8.1 neste computador, fora do `Path`, em:

```text
C:\Users\danim\projeto\flutter
```

Para verificar essa instalação sem alterar o `Path`:

```powershell
& 'C:\Users\danim\projeto\flutter\bin\flutter.bat' --version
& 'C:\Users\danim\projeto\flutter\bin\flutter.bat' doctor -v
```

Para usar apenas `flutter` nos comandos seguintes, adicione `C:\Users\danim\projeto\flutter\bin` ao **Path do usuário** nas variáveis de ambiente do Windows e reabra o terminal/editor. Não instale outro SDK se essa instalação estiver funcionando.

Caso esteja preparando outro computador:

1. Instale Git para Windows e o Flutter SDK estável. Um local simples para o SDK é `C:\src\flutter`. Adicione sua pasta `bin` ao `Path` e reabra o terminal. Veja a [instalação oficial do Flutter](https://docs.flutter.dev/install/manual).
2. Instale o Android Studio. Em **SDK Manager**, instale o Android SDK e as ferramentas solicitadas pelo `flutter doctor`, incluindo Command-line Tools, Platform-Tools e Android Emulator. Use o JDK fornecido pelo Android Studio. Veja a [configuração oficial para Android](https://docs.flutter.dev/platform-integration/android/setup).
3. Execute:

```powershell
flutter --version
dart --version
flutter doctor -v
flutter doctor --android-licenses
```

4. Leia e aceite as licenças solicitadas. Execute `flutter doctor -v` novamente e resolva os avisos do **Android toolchain**. Um aviso sobre Visual Studio/C++ afeta a compilação Windows; ele não impede, por si só, o desenvolvimento Android.
5. No Android Studio, abra **Device Manager**, crie um aparelho virtual e inicie-o. Também é possível usar um Android com opções do desenvolvedor e depuração USB ativadas; autorize a conexão no celular.
6. Abra `TCC/mobile` no editor. No terminal do projeto:

```powershell
Set-Location 'C:\Users\danim\OneDrive\Documentos\TCC\mobile'
flutter pub get
flutter devices
```

O projeto já tem `android/` e `ios/`. Não é necessário executar `flutter create` por cima dos arquivos existentes.

## 4. Preparar PHP e o banco de dados

O aplicativo tem dois modos:

- **Dados reais**, padrão: precisa de PHP, MySQL e um usuário do web. Compartilha cadastros e movimentações entre web e celular.
- **Demonstração**, ativada com `--dart-define=DEMO_MODE=true`: usa exemplos em memória. Veja a seção 8.

Para dados reais, use PHP 8.0+ com `pdo_mysql` e MySQL 5.7+ ou MariaDB 10.2+ compatível. As tabelas de estoque devem usar InnoDB, como no script original. Instale/ative `mbstring` se solicitado pela sua instalação PHP.

Pode utilizar o MySQL do XAMPP, Laragon ou outro servidor configurado. Nesta pasta, o USBWebserver foi preparado com Apache na porta `80`, MySQL na porta `3306` e o site publicado em `usbwebserver/root/smartcontrol`.

### 4.0. Usar o USBWebserver desta pasta

1. Abra `TCC/usbwebserver/usbwebserver.exe`.
2. Confirme no painel que Apache e MySQL estão ativos.
3. O site fica em `http://localhost/smartcontrol/`.
4. O phpMyAdmin fica em `http://localhost/phpmyadmin/`.
5. A API fica em `http://localhost/smartcontrol/api/mobile/index.php?resource=health`.
6. Quando o Windows solicitar acesso à rede, permita o Apache somente em **Redes privadas**. Caso o aviso não apareça, crie uma regra de entrada para `usbwebserver/apache2/bin/httpd_usbwv8.exe`, TCP porta `80`, perfil privado e sub-rede local.

A cópia publicada já contém a API, usa o banco `smartcontrol` do próprio USBWebserver e recebeu a migração mobile. Em 16/09/2026, site, phpMyAdmin e API responderam HTTP 200, e o banco continha 5 usuários e 4 produtos. O endereço de rede detectado foi `192.168.18.13`.

O APK `artifacts/SmartControl-usbwebserver-debug.apk` já aponta para esse endereço. Como o IPv4 pode mudar ao reconectar o Wi-Fi, confira com `ipconfig` antes de testar em outro dia.

### 4.1. Conferir PHP e iniciar MySQL

No terminal:

```powershell
php --version
php -m
```

Confira `pdo_mysql` na lista. Se `php` não for reconhecido, adicione ao `Path` a pasta do PHP ou use seu executável completo. Exemplo para uma instalação padrão do XAMPP:

```powershell
& 'C:\xampp\php\php.exe' --version
```

Os próximos comandos usam `php`. Se precisar do caminho completo, substitua `php` por `& 'C:\xampp\php\php.exe'`.

Inicie o MySQL no painel do servidor local. Anote **host, porta, banco, usuário e senha**.

### 4.2. Se o banco já existe e o web funciona

Use o mesmo banco. Consulte `TCC/tcc Código web/config/database.php` para identificar as credenciais do web e confirme a porta no servidor MySQL.

**Não importe `smartcontrol.sql` novamente em um banco que contém seus cadastros.** Esse é um script de instalação inicial, com criação de tabelas e registros iniciais. A integração mobile usa somente a migração específica da seção 4.4.

Antes de alterar um banco com dados importantes, faça uma exportação pelo phpMyAdmin. A migração mobile adiciona tabelas auxiliares e não exige apagar produtos, usuários ou movimentações.

### 4.3. Se este é um banco novo

1. Abra o phpMyAdmin do servidor local.
2. Em **Importar**, escolha `TCC/tcc Código web/database/smartcontrol.sql`.
3. Conclua a importação. O script cria o banco `smartcontrol`, as tabelas principais e `admin@smartcontrol.com` com a senha vazia.
4. Configure a API e depois defina a senha na seção 4.5.

O caminho `database/install_admin.php` citado no README antigo não é o instalador usado nesta integração. Use o comando CLI abaixo.

### 4.4. Configurar a API e aplicar a migração

Abra um terminal na raiz principal `TCC`:

```powershell
Set-Location 'C:\Users\danim\OneDrive\Documentos\TCC'
Copy-Item -LiteralPath '.\tcc Código web\api\mobile\config.local.php.example' -Destination '.\tcc Código web\api\mobile\config.local.php'
```

Faça essa cópia apenas se `config.local.php` ainda não existir. Abra o arquivo criado no editor e preencha host, porta, banco, usuário e senha conforme os comentários do exemplo. Aponte para o **mesmo banco do web**. Se já existe uma configuração local, edite-a sem substituí-la pelo exemplo.

Dentro de `config.local.php`, os campos têm esta correspondência:

| Campo | Valor que você informa |
| --- | --- |
| `host` | Endereço do servidor MySQL, normalmente `127.0.0.1` no próprio computador. |
| `port` | Porta do MySQL, normalmente `3306`; não é a porta `8080` da API. |
| `database` | Nome do banco, normalmente `smartcontrol`. |
| `username` | Usuário MySQL autorizado a esse banco. |
| `password` | Senha desse usuário MySQL. |
| `allowed_origins` | Mantenha `[]` para Android/iOS; só configure a origem exata se testar Flutter web. |
| `token_hours` | Duração do token; o exemplo usa `24` horas. |

Alternativamente, o servidor aceita `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASS` como variáveis de ambiente. São configurações do processo PHP, não do Flutter.

Execute:

```powershell
php '.\tcc Código web\database\migrate_web_extensions.php'
php '.\tcc Código web\api\mobile\migrate.php'
```

O primeiro comando compatibiliza o banco básico com as páginas web estendidas: login protegido, verificação de e-mail, SKU/código de barras, recuperação de senha, auditoria, notificações, anexos e inventário físico. O segundo aplica `database/mobile_api.sql` e cria, se ainda não existirem, `mobile_api_tokens` e `mobile_api_login_attempts`. Ambos são repetíveis e preservam os dados das tabelas de negócio.

Na instalação USBWebserver desta pasta, execute os mesmos scripts com o PHP incluído no pacote e os caminhos publicados:

```powershell
& '.\usbwebserver\php\php.exe' '.\usbwebserver\root\smartcontrol\database\migrate_web_extensions.php'
& '.\usbwebserver\php\php.exe' '.\usbwebserver\root\smartcontrol\api\mobile\migrate.php'
```

### 4.5. Definir a primeira senha do administrador

Para a instalação nova ou o administrador inicial que ainda possui senha vazia:

```powershell
php '.\tcc Código web\api\mobile\install_admin.php' --email=admin@smartcontrol.com
```

Digite a senha solicitada no terminal e confirme-a. Esse prompt mostra os caracteres digitados. Use de 8 a 72 bytes; caracteres acentuados podem ocupar mais de um byte. O script inicializa o hash no servidor e não sobrescreve uma senha já definida. Não é um recuperador de senha. Se seu banco já tem usuário e senha válidos, entre com eles e pule esta etapa.

Em instalações locais com verificação de e-mail, a API respeita essa condição do usuário; complete a verificação pelo fluxo web quando exigida.

Se criar um novo usuário pelo mobile nesse banco estendido, solicite o reenvio da verificação pelo web e conclua a confirmação antes de entrar com ele. A API não envia mensagens de verificação. No banco original do repositório, sem essa coluna adicional, essa etapa não existe.

## 5. Iniciar a API e conferir o endereço

Na raiz principal `TCC`:

```powershell
Set-Location 'C:\Users\danim\OneDrive\Documentos\TCC'
php -S 0.0.0.0:8080 -t '.\tcc Código web'
```

Mantenha esse terminal aberto. No navegador do computador, acesse:

```text
http://localhost:8080/api/mobile/index.php?resource=health
```

A resposta deve ser JSON da API. Isso confirma PHP e a rota; o `health` não comprova conexão com o banco. Conexão e permissões do banco são verificadas pela migração e pelo login.

O servidor embutido do PHP serve para desenvolvimento local. Para uso público, hospede a API em um servidor PHP com HTTPS e mantenha o banco restrito ao servidor.

### Qual URL usar no Flutter

| Onde o app roda | `API_BASE_URL` |
| --- | --- |
| Emulador Android do Android Studio | `http://10.0.2.2:8080/api/mobile/index.php` |
| Celular Android na mesma rede Wi-Fi | `http://IP_DO_COMPUTADOR:8080/api/mobile/index.php` |
| Celular com o USBWebserver desta pasta | `http://IP_DO_COMPUTADOR/smartcontrol/api/mobile/index.php` |
| Flutter Windows, no mesmo PC do servidor | `http://localhost:8080/api/mobile/index.php` |
| Servidor publicado | `https://SEU_DOMINIO/api/mobile/index.php` |

O Android usa `10.0.2.2` como acesso especial ao computador hospedeiro do emulador. No celular físico, `localhost` aponta para o próprio celular. Veja a [documentação de rede do Android Emulator](https://developer.android.com/studio/run/emulator-networking-address).

Se usar Apache/XAMPP com o projeto em uma subpasta, inclua-a. Exemplo: `http://IP_DO_COMPUTADOR/smartcontrol/api/mobile/index.php`. A URL não deve apontar para `/auth/login.php`, que é uma página HTML do web.

## 6. Rodar no emulador Android

Em outro terminal, dentro de `TCC/mobile`:

```powershell
Set-Location 'C:\Users\danim\OneDrive\Documentos\TCC\mobile'
flutter pub get
flutter analyze
flutter test
flutter devices
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/mobile/index.php
```

Se houver vários dispositivos, copie o identificador de `flutter devices` e acrescente `-d IDENTIFICADOR` ao comando `flutter run`.

Entre com um usuário ativo do banco. Na instalação nova, use `admin@smartcontrol.com` e a senha definida pelo instalador.

`--dart-define` é lido na execução/compilação. Ao trocar a URL ou alternar demonstração, encerre o app e rode novamente com os novos parâmetros; hot reload não é suficiente.

## 7. Rodar no celular Android físico

1. Conecte PC e celular à mesma rede Wi-Fi, sem isolamento entre clientes.
2. Execute `ipconfig` no PC e encontre o IPv4 do adaptador Wi-Fi usado, por exemplo `192.168.1.50`.
3. Mantenha o servidor PHP da seção 5 aberto. Se o Windows solicitar acesso à rede, permita o PHP na rede privada usada no teste.
4. No navegador do celular, confira `http://192.168.1.50:8080/api/mobile/index.php?resource=health`, substituindo pelo seu IPv4.
5. Ative a depuração USB, conecte o aparelho, autorize o PC e confira `flutter devices`.
6. Em `TCC/mobile`, execute:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.50:8080/api/mobile/index.php
```

Substitua `192.168.1.50` pelo endereço real do PC. Se a rede mudar seu IPv4, atualize o comando. `0.0.0.0` é o endereço de escuta do servidor, não um endereço para colocar no aplicativo.

## 8. Demonstração sem MySQL e sem API

Para visualizar as telas antes de configurar o servidor:

```powershell
Set-Location 'C:\Users\danim\OneDrive\Documentos\TCC\mobile'
flutter run --dart-define=DEMO_MODE=true
```

Use as contas de exemplo mostradas no login. Produtos e usuários são dados de teste em memória. Alterações são descartadas ao encerrar e iniciar o processo novamente. Hot reload pode manter memória; isso não representa persistência.

O modo real é o padrão. Uma falha de conexão não deve ser interpretada como entrada automática em demonstração. Para voltar aos dados reais, encerre a execução e use o comando da seção 6 sem `DEMO_MODE=true`.

## 9. Roteiro para conferir o funcionamento

Este roteiro serve para você conferir a instalação no seu computador, emulador e banco real. Nesta entrega, os testes automatizados do Flutter e da API foram executados; o banco de trabalho e um aparelho Android real não foram alterados.

1. **Login:** tente uma senha incorreta e confira a mensagem. Entre com a senha correta de um usuário ativo.
2. **Cadastros:** crie categoria, fornecedor e produto; confira nome, unidade, preços e estoque mínimo.
3. **Persistência real:** atualize o web e procure o produto. Feche e abra o mobile, entre novamente e confira que ele permanece no modo real.
4. **Edição:** edite produto e cadastros auxiliares; confira os valores atualizando o web.
5. **Estoque:** registre entrada e uma saída menor ou igual ao saldo. Confira saldo e histórico nos dois sistemas.
6. **Saldo insuficiente:** tente uma saída maior que o saldo. O servidor deve rejeitá-la sem alterar o estoque.
7. **Permissões:** como administrador, crie um funcionário; entre com ele e confira a restrição às áreas administrativas.
8. **Relatórios:** aplique os filtros disponíveis e compare com os dados cadastrados.
9. **Atualização:** altere um cadastro pelo web, atualize os dados no mobile e confira a mudança.
10. **Sair:** encerre a sessão e confira o retorno ao login.

Para as verificações automatizadas disponíveis:

```powershell
flutter analyze
flutter test
```

Esses comandos não substituem a integração com PHP/MySQL nem o teste de instalação no Android.

Validação executada nesta entrega:

- `flutter analyze`: nenhum problema encontrado;
- `flutter test`: 24 testes passaram, cobrindo login, perfis, formulários, valores brasileiros, estoque, CSV, sessão e respostas da API;
- PHP: 30 verificações de validação e 5 de contrato HTTP passaram;
- MariaDB temporário e isolado: 27 verificações de integração passaram, incluindo login, tokens, CRUD, permissões, transações e rollback. A instância temporária foi encerrada e nenhum banco existente foi modificado.

Não havia emulador nem celular Android conectado. Mesmo assim, a compilação Android foi concluída com JDK 17 e os três APKs de depuração foram validados quanto a pacote, atividade inicial, SDKs, arquiteturas e permissão de internet:

- `artifacts/SmartControl-demo-debug.apk`: funciona sem servidor e usa os dados fictícios exibidos no login;
- `artifacts/SmartControl-conectado-debug.apk`: usa a API padrão do emulador. Para outro endereço, gere novamente passando `API_BASE_URL`.
- `artifacts/SmartControl-usbwebserver-debug.apk`: usa `http://192.168.18.13/smartcontrol/api/mobile/index.php` para o teste atual em celular físico.

## 10. Gerar um APK

### APK de teste local

Em `TCC/mobile`, usando o IPv4 real do computador:

```powershell
flutter build apk --debug --dart-define=API_BASE_URL=http://192.168.1.50:8080/api/mobile/index.php
```

Saída:

```text
TCC/mobile/build/app/outputs/flutter-apk/app-debug.apk
```

Esse APK depende do servidor PHP e do PC acessível pela rede. Para gerar apenas a demonstração sem servidor:

```powershell
flutter build apk --debug --dart-define=DEMO_MODE=true
```

### Compilação de distribuição

Use a URL HTTPS do servidor publicado:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://SEU_DOMINIO/api/mobile/index.php
```

Substitua `SEU_DOMINIO` pelo endereço real. Saída padrão: `TCC/mobile/build/app/outputs/flutter-apk/app-release.apk`.

Para uma demonstração em release sem servidor, use `flutter build apk --release --dart-define=DEMO_MODE=true`. Esse modo não precisa de HTTPS porque não faz conexão com a API.

Antes de publicar em loja, configure sua chave de assinatura em `android/app/build.gradle`. O identificador atual é `com.smartcontrol.mobile`; a assinatura de depuração não representa uma assinatura de publicação. Siga as [instruções oficiais de assinatura e distribuição Flutter](https://docs.flutter.dev/deployment/android).

HTTP local fica reservado à configuração Android de desenvolvimento. Use HTTPS no modo real de distribuição.

## 11. iPhone e outras plataformas

O projeto contém `ios`, mas compilar e validar iOS exige Mac com Xcode, simulador ou dispositivo e configuração de assinatura Apple. Veja a [configuração oficial para iOS](https://docs.flutter.dev/platform-integration/ios/setup). Esta entrega em Windows não comprova uma compilação iOS.

Windows, Linux, macOS e web são estruturas de plataforma do Flutter. O foco é mobile; a presença das pastas não comprova validação de todas as plataformas. Flutter web também pode exigir a configuração de origem/CORS na API.

## 12. Problemas comuns

| Sintoma | O que conferir |
| --- | --- |
| `flutter` não é reconhecido | `flutter/bin` no `Path`; reabra terminal/editor ou use o caminho completo da seção 3. |
| Nenhum dispositivo encontrado | Inicie o emulador ou autorize depuração USB; confira `flutter devices`. |
| `php` não é reconhecido | Configure seu `Path` ou use o caminho completo do PHP. |
| `could not find driver` | Habilite `pdo_mysql` no PHP usado pelo terminal. |
| Conexão recusada ou timeout | Servidor PHP aberto, porta correta, firewall e endereço próprio do dispositivo. |
| A rota retorna HTML/404 | Use `api/mobile/index.php` sob `tcc Código web`, sem a cópia interna `TCC`. |
| `health` funciona, mas login falha por banco | MySQL ativo, credenciais e migração; `health` só verifica disponibilidade HTTP. |
| Tabela de tokens/tentativas não existe | Execute `api/mobile/migrate.php` no banco correto. |
| Login inválido no banco novo | Defina a primeira senha com `api/mobile/install_admin.php`. |
| E-mail precisa de verificação | Complete o fluxo pelo web, se essa extensão existir no banco. |
| Dados somem após reiniciar | Confira `DEMO_MODE=true`; demonstração não salva no MySQL. |
| Dados do web e mobile diferentes | Confira se usam o mesmo host, porta e banco. |
| A URL permanece antiga | Encerre e inicie com o novo `--dart-define`; não use só hot reload. |
| HTTP funciona em debug e falha em release | Use API HTTPS para o modo real de distribuição. |
| Falha nas ferramentas Android | Execute `flutter doctor -v`; instale SDK/licenças solicitados e use o JDK do Android Studio. |
| `SSLHandshakeException`, `PKIX path building failed` ao baixar o Gradle | O antivírus pode estar inspecionando HTTPS. Use um JDK 17 confiável e importe nele a autoridade já confiável pelo Windows; não desative a validação HTTPS. Depois repita a compilação. |
| A compilação mostra apenas `25.0.2` | O Java 25 do Android Studio é novo demais para esta combinação do Flutter/Gradle. Instale um JDK 17 LTS, execute `flutter config --jdk-dir="CAMINHO_DO_JDK_17"` e tente novamente. |

## 13. Limites da conversão

O [mapa web → Flutter](docs/MAPA_WEB_FLUTTER.md) detalha os módulos convertidos.

Conta pessoal, anexos, auditoria central, inventário físico, notificações e recuperação/verificação de senha encontrados nas extensões da cópia local continuam como funções do web. A nova API não converte automaticamente essas telas. Também não estão configurados sincronização offline, notificações push ou publicação em lojas.
