# SmartControl Mobile 1.1.2

Aplicativo Flutter ligado ao banco e às regras da versão 4.0 do SmartControl web.

## Gerar o APK

O APK é um artefato local e não é enviado ao Git. Gere-o com a URL HTTPS
informada pelo script de inicialização:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://SEU-HOST/index.php
```

## Como usar na apresentação

1. No notebook, dê dois cliques em `INICIAR_MOBILE_INTERNET.cmd`, na raiz do repositório.
2. Aceite a confirmação de administrador do Windows.
3. Aguarde a mensagem verde `SMARTCONTROL DISPONIVEL PARA O APLICATIVO`.
4. Envie o APK gerado para o Android e instale-o.
5. Entre com o mesmo usuário ativo e verificado usado no sistema web.

O telefone e o notebook podem estar em redes diferentes. O notebook deve ficar ligado, com XAMPP, Tailscale, Apache e MySQL funcionando.

## Recursos sincronizados

- produtos, categorias, fornecedores e usuários;
- SKU e código de barras;
- entradas, saídas e ajuste administrativo de estoque;
- saldo anterior, saldo posterior, origem e motivo das movimentações;
- inventários e itens de inventário;
- notificações e marcação como lida;
- auditoria remota para administradores;
- regras atuais de conta, senha e sessão do web.

## Verificações desta entrega

- `flutter analyze`: sem problemas;
- testes Flutter: 24 aprovados;
- testes PHP/API: 30 verificações locais e 5 verificações HTTP aprovadas;
- APK 1.1.2, código 5, minSdk 21 e targetSdk 35;
- assinatura APK v1 e v2 válidas;
- API local e pública respondendo com `version: 2`.

Consulte [GUIA_RAPIDO_XAMPP.md](GUIA_RAPIDO_XAMPP.md) para caminhos, código e solução de problemas.
