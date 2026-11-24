import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:smartcontrol_mobile/app.dart';
import 'package:smartcontrol_mobile/data/inventory_store.dart';
import 'package:smartcontrol_mobile/screens/dashboard_screen.dart';
import 'package:smartcontrol_mobile/screens/products_screen.dart';
import 'package:smartcontrol_mobile/screens/stock_screen.dart';

void main() {
  Future<InventoryStore> start(
    WidgetTester tester, {
    double width = 390,
  }) async {
    tester.view.physicalSize = Size(width, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final store = InventoryStore(isDemo: true);
    addTearDown(store.dispose);
    await tester.pumpWidget(SmartControlApp(store: store));
    await tester.pumpAndSettle();
    return store;
  }

  testWidgets('login e navegação mobile para estoque e menu Mais', (
    tester,
  ) async {
    await start(tester);
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();
    expect(find.byType(DashboardScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Estoque'));
    await tester.pumpAndSettle();
    expect(find.byType(StockScreen), findsOneWidget);
    await tester.tap(find.text('Mais'));
    await tester.pumpAndSettle();
    expect(find.text('Categorias'), findsWidgets);
    await tester.tap(find.text('Categorias').last);
    await tester.pumpAndSettle();
    expect(find.text('Nova categoria'), findsNothing);
    expect(find.text('Informática'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('login errado mantém tela e mostra erro', (tester) async {
    await start(tester);
    await tester.enterText(find.byType(TextField).last, 'errada');
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();
    expect(find.byType(DashboardScreen), findsNothing);
    expect(find.textContaining('inválidos'), findsWidgets);
  });
  testWidgets('funcionário não vê gerenciamento administrativo', (
    tester,
  ) async {
    await start(tester);
    await tester.enterText(
      find.byType(TextField).first,
      'marina@smartcontrol.com',
    );
    await tester.enterText(find.byType(TextField).last, 'func123');
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Mais'));
    await tester.pumpAndSettle();
    expect(find.text('Usuários'), findsNothing);
    expect(find.text('Configurações'), findsNothing);
  });
  testWidgets('dashboard e produtos cabem em tela estreita', (tester) async {
    await start(tester, width: 320);
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Produtos').last);
    await tester.pumpAndSettle();
    expect(find.byType(ProductsScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Novo'));
    await tester.pumpAndSettle();
    expect(find.text('Novo produto'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('cadastro de produto valida moeda e salva no fluxo da tela', (
    tester,
  ) async {
    final store = await start(tester);
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Produtos').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Novo'));
    await tester.pumpAndSettle();
    Finder field(String label) => find.widgetWithText(TextFormField, label);
    await tester.enterText(field('Nome *'), 'Mesa de escritório');
    await tester.ensureVisible(field('Preço de venda *'));
    await tester.enterText(field('Preço de venda *'), 'inválido');
    await tester.ensureVisible(find.text('Salvar produto'));
    await tester.tap(find.text('Salvar produto'));
    await tester.pumpAndSettle();
    expect(store.products.any((p) => p.name == 'Mesa de escritório'), isFalse);
    await tester.ensureVisible(field('Preço de venda *'));
    await tester.enterText(field('Preço de venda *'), '1.234,56');
    await tester.ensureVisible(find.text('Salvar produto'));
    await tester.tap(find.text('Salvar produto'));
    await tester.pumpAndSettle();
    expect(store.products.last.name, 'Mesa de escritório');
    expect(store.products.last.salePrice, 1234.56);
    expect(store.products.last.stock, 0);
    expect(find.text('Novo produto'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('saída insuficiente mantém formulário e permite corrigir', (
    tester,
  ) async {
    final store = await start(tester);
    await tester.tap(find.text('Entrar'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Estoque'));
    await tester.pumpAndSettle();
    await tester.tap(find.byIcon(Icons.remove));
    await tester.pumpAndSettle();
    final quantity = find.widgetWithText(TextFormField, 'Quantidade *');
    await tester.enterText(quantity, '999');
    await tester.tap(find.text('Confirmar movimentação'));
    await tester.pumpAndSettle();
    expect(find.text('Registrar saída'), findsOneWidget);
    expect(store.products.first.stock, 3);
    await tester.enterText(quantity, '1');
    await tester.ensureVisible(find.text('Confirmar movimentação'));
    await tester.tap(find.text('Confirmar movimentação'));
    await tester.pumpAndSettle();
    expect(store.products.first.stock, 2);
    expect(find.text('Registrar saída'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
