import '../models/models.dart';

/// Dados fictícios, usados somente quando DEMO_MODE=true. Não são persistidos.
class DemoData {
  final users = [
    AppUser(
      id: 1,
      name: 'Administrador',
      email: 'admin@smartcontrol.com',
      role: UserRole.administrador,
    ),
    AppUser(
      id: 2,
      name: 'Marina Souza',
      email: 'marina@smartcontrol.com',
      role: UserRole.funcionario,
    ),
  ];
  // Senhas públicas de demonstração. Autenticação real ocorre somente no PHP.
  final passwords = <int, String>{1: 'admin123', 2: 'func123'};
  final categories = [
    DirectoryItem(
      id: 1,
      name: 'Informática',
      subtitle: 'Equipamentos e acessórios',
    ),
    DirectoryItem(
      id: 2,
      name: 'Escritório',
      subtitle: 'Materiais de escritório',
    ),
    DirectoryItem(id: 3, name: 'Limpeza', subtitle: 'Produtos de limpeza'),
  ];
  final suppliers = [
    DirectoryItem(
      id: 1,
      name: 'Distribuidora Tech LTDA',
      phone: '(13) 3421-1234',
      email: 'contato@distech.com',
    ),
    DirectoryItem(
      id: 2,
      name: 'Papelaria Central',
      phone: '(13) 3421-5678',
      email: 'vendas@papelariacentral.com',
    ),
  ];
  final products = [
    Product(
      id: 1,
      name: 'Notebook Pro 15',
      categoryId: 1,
      supplierId: 1,
      category: 'Informática',
      supplier: 'Distribuidora Tech LTDA',
      costPrice: 3200,
      salePrice: 4299.90,
      stock: 3,
      minimumStock: 5,
    ),
    Product(
      id: 2,
      name: 'Mouse sem fio',
      categoryId: 1,
      supplierId: 1,
      category: 'Informática',
      supplier: 'Distribuidora Tech LTDA',
      costPrice: 45,
      salePrice: 79.90,
      stock: 24,
      minimumStock: 8,
    ),
    Product(
      id: 3,
      name: 'Papel A4 — 500 folhas',
      categoryId: 2,
      supplierId: 2,
      category: 'Escritório',
      supplier: 'Papelaria Central',
      costPrice: 22,
      salePrice: 34.50,
      stock: 4,
      minimumStock: 10,
      unit: 'PCT',
    ),
  ];
  late final movements = [
    StockMovement(
      id: 1,
      productId: 2,
      product: 'Mouse sem fio',
      type: MovementType.entrada,
      quantity: 10,
      userId: 1,
      user: 'Administrador',
      date: DateTime.now(),
      note: 'Reposição de demonstração',
    ),
    StockMovement(
      id: 2,
      productId: 3,
      product: 'Papel A4 — 500 folhas',
      type: MovementType.saida,
      quantity: 3,
      userId: 2,
      user: 'Marina Souza',
      date: DateTime.now().subtract(const Duration(days: 1)),
    ),
  ];
}
