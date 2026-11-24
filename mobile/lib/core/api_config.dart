import 'package:flutter/foundation.dart';

/// Passe o endereço completo do index.php com --dart-define=API_BASE_URL=...
abstract final class ApiConfig {
  static const demoMode = bool.fromEnvironment('DEMO_MODE');
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8080/api/mobile/index.php',
  );
  static const timeout = Duration(seconds: 20);

  static Uri endpoint(String base, String resource) {
    final uri = Uri.tryParse(base.trim());
    if (uri == null ||
        !uri.hasAuthority ||
        !['http', 'https'].contains(uri.scheme) ||
        uri.userInfo.isNotEmpty ||
        uri.hasFragment) {
      throw const FormatException(
        'Configure um endereço HTTP/HTTPS válido para a API.',
      );
    }
    if (kReleaseMode && uri.scheme != 'https') {
      throw const FormatException(
        'O aplicativo instalado exige um endereço HTTPS.',
      );
    }
    return uri.replace(
      queryParameters: {...uri.queryParameters, 'resource': resource},
    );
  }
}
