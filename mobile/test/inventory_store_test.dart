import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:smartcontrol_mobile/data/api_client.dart';
import 'package:smartcontrol_mobile/data/inventory_store.dart';
import 'package:smartcontrol_mobile/models/models.dart';

Map<String, dynamic> snapshot({String role = 'administrador'}) => {
  'user': {
    'id': 1,
    'nome': 'Ana',
    'email': 'ana@example.com',
    'perfil': role,
    'ativo': 1,
  },
  'products': [
    {
      'id': 8,
      'nome': 'Teclado',
      'categoria_id': 5,
      'fornecedor_id': null,
      'categoria': 'Tecnologia',
      'preco_custo': '10.50',
      'preco_venda': '20.00',
      'quantidade_estoque': '3',
      'estoque_minimo': '5',
      'unidade': 'UN',
      'ativo': 1,
    },
  ],
  'categories': [
    {'id': 5, 'nome': 'Tecnologia', 'descricao': 'Descrição', 'ativo': 1},
  ],
  'suppliers': [],
  'users': [],
  'movements': [],
  'settings': {
    'nome_sistema': 'SmartControl',
    'nome_empresa': 'Empresa',
    'estoque_minimo_padrao': '5',
  },
};
http.Response envelope(Map<String, dynamic> data) => http.Response(
  jsonEncode({'data': data}),
  200,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

void main() {
  group('modo demonstração e regras de estoque', () {
    late InventoryStore store;
    setUp(() async {
      store = InventoryStore(isDemo: true);
      await store.login('admin@smartcontrol.com', 'admin123');
    });
    tearDown(() => store.dispose());
    test('saída insuficiente não altera saldo nem histórico', () async {
      final product = store.products.first;
      final stock = product.stock;
      final count = store.movements.length;
      expect(
        await store.registerMovement(
          product,
          MovementType.saida,
          stock + 1,
          '',
          store.session!.user,
          store.csrfToken,
        ),
        contains('insuficiente'),
      );
      expect(product.stock, stock);
      expect(store.movements.length, count);
    });
    test('entrada atualiza saldo, histórico e indicadores juntos', () async {
      final p = store.products.first;
      final stock = p.stock;
      final total = store.entriesToday;
      expect(
        await store.registerMovement(
          p,
          MovementType.entrada,
          4,
          'Reposição',
          store.session!.user,
          store.csrfToken,
        ),
        isNull,
      );
      expect(p.stock, stock + 4);
      expect(store.movements.first.note, 'Reposição');
      expect(store.entriesToday, total + 4);
      expect(store.dailyTotals(MovementType.entrada).last, total + 4);
    });
    test('produto inativo e quantidade zero são bloqueados', () async {
      final p = store.products.first;
      await store.toggleProduct(p, store.session!.user, store.csrfToken);
      expect(
        await store.registerMovement(
          p,
          MovementType.entrada,
          1,
          '',
          store.session!.user,
          store.csrfToken,
        ),
        contains('inativo'),
      );
      expect(
        await store.registerMovement(
          p,
          MovementType.entrada,
          0,
          '',
          store.session!.user,
          store.csrfToken,
        ),
        isNotNull,
      );
    });
    test(
      'criação usa id único e saldo zero; edição não reescreve saldo',
      () async {
        final user = store.session!.user;
        final p = Product(
          id: 0,
          name: 'Novo item',
          category: '',
          supplier: '',
          costPrice: 1,
          salePrice: 2,
          stock: 999,
          minimumStock: 1,
        );
        expect(await store.addProduct(p, user, store.csrfToken), isNull);
        final saved = store.products.last;
        expect(saved.stock, 0);
        expect(
          store.products.map((p) => p.id).toSet().length,
          store.products.length,
        );
        final update = Product(
          id: saved.id,
          name: 'Editado',
          category: '',
          supplier: '',
          costPrice: 1,
          salePrice: 2,
          stock: 77,
          minimumStock: 2,
        );
        expect(
          await store.updateProduct(update, user, store.csrfToken),
          isNull,
        );
        expect(store.products.last.stock, 0);
        expect(store.products.last.name, 'Editado');
      },
    );
    test('renomear categoria preserva vínculo pelo id', () async {
      final first = store.categories.first;
      expect(
        await store.updateDirectoryItem(
          store.categories,
          DirectoryItem(id: first.id, name: 'Tecnologia'),
          store.session!.user,
          store.csrfToken,
        ),
        isNull,
      );
      expect(store.products.first.category, 'Tecnologia');
      expect(store.products.first.categoryId, first.id);
    });
    test(
      'funcionário não gerencia usuários ou configurações, mesmo forjando perfil',
      () async {
        await store.logout();
        await store.login('marina@smartcontrol.com', 'func123');
        final forged = AppUser(
          id: store.session!.user.id,
          name: 'Falso admin',
          email: '',
          role: UserRole.administrador,
        );
        expect(
          await store.saveSettings('X', 'Y', 5, forged, store.csrfToken),
          contains('administrador'),
        );
        expect(
          await store.createUser(
            'Teste',
            'test@example.com',
            'senha12345',
            UserRole.funcionario,
            forged,
            store.csrfToken,
          ),
          contains('administrador'),
        );
        expect(
          store.reportsFor(forged).any((r) => r.title == 'Usuários'),
          isFalse,
        );
      },
    );
    test('usuário criado pode entrar; não permite autoinativação', () async {
      final admin = store.session!.user;
      expect(await store.toggleUser(admin, admin, store.csrfToken), isNotNull);
      expect(
        await store.createUser(
          'José',
          'jose@example.com',
          'senha12345',
          UserRole.funcionario,
          admin,
          store.csrfToken,
        ),
        isNull,
      );
      await store.logout();
      expect(await store.login('jose@example.com', 'senha12345'), isNotNull);
    });
    test('data final inclui 23:59:59 e exclui dia seguinte', () {
      final day = DateTime(2026, 8, 1);
      store.movements
        ..clear()
        ..addAll([
          for (final date in [
            day,
            DateTime(2026, 8, 1, 23, 59, 59),
            DateTime(2026, 8, 2),
          ])
            StockMovement(
              id: date.hour,
              productId: 1,
              product: 'P',
              type: MovementType.entrada,
              quantity: 1,
              userId: 1,
              user: 'U',
              date: date,
            ),
        ]);
      expect(store.movementHistory(start: day, end: day).length, 2);
    });
  });

  group('API e sessão', () {
    test(
      'modo real inicia vazio, autentica e lê números MySQL sem senhas',
      () async {
        final client = MockClient((request) async {
          if (request.url.queryParameters['resource'] == 'login') {
            expect(jsonDecode(request.body)['password'], 'senha');
            return envelope({'token': 'abc'});
          }
          expect(request.headers['Authorization'], 'Bearer abc');
          return envelope(snapshot());
        });
        final store = InventoryStore(
          api: ApiClient(client: client),
          isDemo: false,
        );
        addTearDown(store.dispose);
        expect(store.products, isEmpty);
        expect(await store.login('ana@example.com', 'senha'), isNotNull);
        expect(store.products.single.costPrice, 10.5);
        expect(store.products.single.categoryId, 5);
        expect(store.session!.user.passwordHash, isEmpty);
      },
    );
    test(
      '401 ao atualizar limpa sessão e dados; não cai no modo demo',
      () async {
        var expired = false;
        final store = InventoryStore(
          isDemo: false,
          api: ApiClient(
            client: MockClient((request) async {
              if (request.url.queryParameters['resource'] == 'login') {
                return envelope({'token': 'abc'});
              }
              if (expired) return http.Response('{"error":"Expirou"}', 401);
              return envelope(snapshot());
            }),
          ),
        );
        addTearDown(store.dispose);
        await store.login('ana@example.com', 'senha');
        expired = true;
        await store.refresh();
        expect(store.session, isNull);
        expect(store.products, isEmpty);
        expect(store.lastError, contains('expirada'));
        expect(store.isDemo, isFalse);
      },
    );
    test('resposta inválida preserva dados carregados', () async {
      var malformed = false;
      final store = InventoryStore(
        isDemo: false,
        api: ApiClient(
          client: MockClient((r) async {
            if (r.url.queryParameters['resource'] == 'login') {
              return envelope({'token': 'abc'});
            }
            return envelope(malformed ? {'products': []} : snapshot());
          }),
        ),
      );
      addTearDown(store.dispose);
      await store.login('ana@example.com', 'senha');
      malformed = true;
      await store.refresh();
      expect(store.products.single.name, 'Teclado');
      expect(store.lastError, isNotNull);
    });
    test(
      'erro de mutação não altera estoque local nem repete request',
      () async {
        var writes = 0;
        final store = InventoryStore(
          isDemo: false,
          api: ApiClient(
            client: MockClient((r) async {
              if (r.url.queryParameters['resource'] == 'login') {
                return envelope({'token': 'abc'});
              }
              if (r.url.queryParameters['resource'] == 'movements') {
                writes++;
                expect(jsonDecode(r.body)['produto_id'], 8);
                return http.Response('{"error":"Estoque insuficiente"}', 409);
              }
              return envelope(snapshot());
            }),
          ),
        );
        addTearDown(store.dispose);
        await store.login('ana@example.com', 'senha');
        expect(
          await store.registerMovement(
            store.products.first,
            MovementType.saida,
            10,
            '',
            store.session!.user,
            store.csrfToken,
          ),
          contains('insuficiente'),
        );
        expect(writes, 1);
        expect(store.products.single.stock, 3);
      },
    );
  });
}
