import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:smartcontrol_mobile/data/api_client.dart';

void main() {
  test('preserva query da URL, token e não segue redirecionamento', () async {
    final api = ApiClient(
      baseUrl: 'https://example.com/api/index.php?v=1',
      client: MockClient((request) async {
        expect(request.url.queryParameters, {
          'v': '1',
          'resource': 'bootstrap',
        });
        expect(request.followRedirects, isFalse);
        expect(request.headers['Authorization'], 'Bearer secreto');
        return http.Response('{"data":{}}', 200);
      }),
    )..token = 'secreto';
    addTearDown(api.dispose);
    await api.request('bootstrap');
  });
  test('HTML e resposta sem envelope viram mensagem compreensível', () async {
    for (final body in ['<html>Erro PHP</html>', '[]', '{"ok":true}']) {
      final api = ApiClient(
        client: MockClient((_) async => http.Response(body, 200)),
      );
      await expectLater(api.request('bootstrap'), throwsA(isA<ApiException>()));
      api.dispose();
    }
  });
  test('URL com credencial embutida é rejeitada antes da rede', () async {
    final api = ApiClient(
      baseUrl: 'https://user:pass@example.com/index.php',
      client: MockClient((_) async => throw StateError('não deve chamar')),
    );
    addTearDown(api.dispose);
    await expectLater(
      api.request('login', body: {}),
      throwsA(isA<ApiException>()),
    );
  });
}
