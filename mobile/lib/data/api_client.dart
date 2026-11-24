import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../core/api_config.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;
  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client, this.baseUrl = ApiConfig.baseUrl})
    : _client = client ?? http.Client();

  final http.Client _client;
  final String baseUrl;
  String? token;

  Future<Map<String, dynamic>> request(
    String resource, {
    Map<String, dynamic>? body,
  }) async {
    try {
      final request = http.Request(
        body == null ? 'GET' : 'POST',
        ApiConfig.endpoint(baseUrl, resource),
      );
      // Não seguir redirecionamentos com credenciais nem repetir gravações.
      request.followRedirects = false;
      request.headers.addAll({
        'Accept': 'application/json',
        if (body != null) 'Content-Type': 'application/json; charset=utf-8',
        if (token != null) 'Authorization': 'Bearer $token',
      });
      if (body != null) request.body = jsonEncode(body);
      final response = await _client
          .send(request)
          .then(http.Response.fromStream)
          .timeout(ApiConfig.timeout);
      if (response.statusCode == 401) {
        throw const ApiException(
          'Sessão inválida ou expirada. Entre novamente.',
          statusCode: 401,
        );
      }
      final decoded = jsonDecode(utf8.decode(response.bodyBytes));
      if (decoded is! Map<String, dynamic>) throw const FormatException();
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final message = decoded['error'];
        throw ApiException(
          message is String ? message : 'Não foi possível concluir a operação.',
          statusCode: response.statusCode,
        );
      }
      if (decoded['data'] is! Map<String, dynamic>) {
        throw const FormatException();
      }
      return decoded['data'] as Map<String, dynamic>;
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw ApiException(
        body == null || resource == 'login'
            ? 'O servidor demorou para responder. Confira a conexão e o endereço da API.'
            : 'A resposta não chegou. Atualize os dados antes de repetir: a operação pode ter sido salva.',
      );
    } on http.ClientException {
      throw ApiException(
        body == null || resource == 'login'
            ? 'Não foi possível conectar ao servidor. Confira a rede e a API_BASE_URL.'
            : 'Conexão interrompida. Atualize os dados antes de repetir a operação.',
      );
    } on FormatException catch (error) {
      throw ApiException(
        error.message.toString().startsWith('Configure') ||
                error.message.toString().startsWith('O aplicativo')
            ? error.message.toString()
            : 'Resposta inválida da API. Confira o endereço, o PHP e a configuração do banco.',
      );
    }
  }

  void dispose() => _client.close();
}
