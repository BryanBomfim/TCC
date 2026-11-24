#!/bin/bash
set -e

cd "$(dirname "$0")"
flutter pub get
flutter build ipa --release \
  --dart-define=API_BASE_URL=https://smartcontrol-tcc.tail495b46.ts.net/index.php

echo
echo "IPA gerado em build/ios/ipa/"
echo "A assinatura usa a equipe Apple configurada no Xcode."
