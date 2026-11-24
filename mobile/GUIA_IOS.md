# SmartControl no iPhone

O iPhone não instala arquivos APK. Para iOS, o Flutter gera um arquivo IPA, que precisa ser compilado e assinado em um Mac com Xcode.

O projeto já está configurado com:

- nome: `SmartControl`;
- identificador: `com.smartcontrol.mobile`;
- versão: `1.0.1 (2)`;
- API: `https://smartcontrol-tcc.tail495b46.ts.net/index.php`.

## Gerar no Mac

1. Copie a pasta `mobile` para o Mac.
2. Instale Flutter e Xcode.
3. Abra `ios/Runner.xcworkspace` no Xcode.
4. Em **Signing & Capabilities**, selecione sua equipe Apple.
5. Feche o Xcode e dê permissão ao script:

   ```bash
   chmod +x GERAR_IOS_NO_MAC.command
   ```

6. Execute `GERAR_IOS_NO_MAC.command`.
7. O IPA será criado em `build/ios/ipa/`.

Para instalar em um iPhone fora da App Store, use a instalação permitida pela conta Apple configurada no Xcode ou distribua pelo TestFlight.
