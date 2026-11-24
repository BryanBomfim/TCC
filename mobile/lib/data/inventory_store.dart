import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../core/api_config.dart';
import '../models/models.dart';
import 'api_client.dart';
import 'demo_data.dart';

/// Coordena sessão e dados. No modo conectado, o PHP é a fonte de verdade.
class InventoryStore extends ChangeNotifier {
  InventoryStore({ApiClient? api, this.isDemo = ApiConfig.demoMode})
    : _api = api ?? ApiClient() {
    if (isDemo) {
      _demo = DemoData();
      users.addAll(_demo!.users);
      products.addAll(_demo!.products);
      categories.addAll(_demo!.categories);
      suppliers.addAll(_demo!.suppliers);
      movements.addAll(_demo!.movements);
      movements.sort((a, b) => b.date.compareTo(a.date));
    }
  }

  final ApiClient _api;
  final bool isDemo;
  DemoData? _demo;
  bool busy = false;
  bool _disposed = false;
  String? lastError;
  DateTime? lastUpdated;
  SystemSettings settings = SystemSettings();
  final products = <Product>[];
  final categories = <DirectoryItem>[];
  final suppliers = <DirectoryItem>[];
  final users = <AppUser>[];
  final movements = <StockMovement>[];
  final auditLogs = <AuditLog>[];
  final inventories = <InventorySummary>[];
  final inventoryItems = <InventoryItem>[];
  final notifications = <AppNotification>[];
  final remoteAuditLogs = <RemoteAuditLog>[];
  AuthSession? session;

  // Compatibilidade com os formulários antigos. Não é um CSRF de navegador.
  String get csrfToken => session?.token ?? '';
  int get lowStockCount =>
      products.where((p) => p.active && p.isLowStock).length;
  int get entriesToday => _todayTotal(MovementType.entrada);
  int get exitsToday => _todayTotal(MovementType.saida);
  double get inventoryValue =>
      products.where((p) => p.active).fold(0, (sum, p) => sum + p.stockValue);
  int get unreadNotifications =>
      notifications.where((item) => !item.isRead).length;

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  Future<AuthSession?> login(String email, String password) async {
    if (busy) return null;
    busy = true;
    lastError = null;
    _notify();
    try {
      if (isDemo) {
        final found = users.where(
          (u) =>
              u.email.toLowerCase() == email.trim().toLowerCase() && u.active,
        );
        if (found.isEmpty || _demo!.passwords[found.first.id] != password) {
          throw const ApiException(
            'E-mail, senha ou status do usuário inválidos.',
          );
        }
        session = AuthSession(
          user: found.first,
          token: 'demo-${DateTime.now().microsecondsSinceEpoch}',
          createdAt: DateTime.now(),
        );
      } else {
        final data = await _api.request(
          'login',
          body: {'email': email.trim(), 'password': password},
        );
        final token = data['token'];
        if (token is! String || token.isEmpty) throw const FormatException();
        _api.token = token;
        final snapshot = await _api.request('bootstrap');
        _applySnapshot(snapshot, token: token);
      }
      lastUpdated = DateTime.now();
      _audit(AuditAction.login, 'Login realizado');
      return session;
    } catch (error) {
      _api.token = null;
      session = null;
      lastError = error is ApiException && error.statusCode == 401
          ? 'E-mail, senha ou status do usuário inválidos.'
          : _message(error);
      return null;
    } finally {
      busy = false;
      _notify();
    }
  }

  Future<String?> logout() async {
    if (busy) return 'Aguarde a operação atual terminar.';
    busy = true;
    _notify();
    try {
      if (!isDemo && session != null) await _api.request('logout', body: {});
      _clearSession();
      lastError = null;
      return null;
    } catch (error) {
      if (error is ApiException && error.statusCode == 401) {
        _clearSession();
        return null;
      }
      return lastError = _message(error);
    } finally {
      busy = false;
      _notify();
    }
  }

  Future<void> refresh() async {
    if (busy || session == null) return;
    busy = true;
    lastError = null;
    _notify();
    try {
      if (!isDemo) _applySnapshot(await _api.request('bootstrap'));
      lastUpdated = DateTime.now();
    } catch (error) {
      _handleError(error);
    } finally {
      busy = false;
      _notify();
    }
  }

  void _clearSession() {
    session = null;
    _api.token = null;
    auditLogs.clear();
    if (!isDemo) {
      products.clear();
      categories.clear();
      suppliers.clear();
      users.clear();
      movements.clear();
      inventories.clear();
      inventoryItems.clear();
      notifications.clear();
      remoteAuditLogs.clear();
      settings = SystemSettings();
    }
    lastUpdated = null;
  }

  void _applySnapshot(Map<String, dynamic> data, {String? token}) {
    // Decodifica tudo antes de substituir: resposta incompleta não apaga dados.
    List<T> parse<T>(String key, T Function(Map<String, dynamic>) factory) =>
        (data[key] as List)
            .map((item) => factory(Map<String, dynamic>.from(item as Map)))
            .toList();
    List<T> parseOptional<T>(
      String key,
      T Function(Map<String, dynamic>) factory,
    ) => (data[key] as List? ?? const [])
        .map((item) => factory(Map<String, dynamic>.from(item as Map)))
        .toList();
    final nextProducts = parse('products', Product.fromJson);
    final nextCategories = parse('categories', DirectoryItem.fromJson);
    final nextSuppliers = parse('suppliers', DirectoryItem.fromJson);
    final nextUsers = parse('users', AppUser.fromJson);
    final nextMovements = parse('movements', StockMovement.fromJson)
      ..sort((a, b) => b.date.compareTo(a.date));
    final nextInventories = parseOptional(
      'inventories',
      InventorySummary.fromJson,
    );
    final nextInventoryItems = parseOptional(
      'inventory_items',
      InventoryItem.fromJson,
    );
    final nextNotifications = parseOptional(
      'notifications',
      AppNotification.fromJson,
    );
    final nextRemoteAudit = parseOptional('audit', RemoteAuditLog.fromJson);
    final nextSettings = SystemSettings.fromJson(
      Map<String, dynamic>.from(data['settings'] as Map),
    );
    final nextUser = AppUser.fromJson(
      Map<String, dynamic>.from(data['user'] as Map),
    );
    products
      ..clear()
      ..addAll(nextProducts);
    categories
      ..clear()
      ..addAll(nextCategories);
    suppliers
      ..clear()
      ..addAll(nextSuppliers);
    users
      ..clear()
      ..addAll(nextUsers);
    movements
      ..clear()
      ..addAll(nextMovements);
    inventories
      ..clear()
      ..addAll(nextInventories);
    inventoryItems
      ..clear()
      ..addAll(nextInventoryItems);
    notifications
      ..clear()
      ..addAll(nextNotifications);
    remoteAuditLogs
      ..clear()
      ..addAll(nextRemoteAudit);
    settings = nextSettings;
    session = AuthSession(
      user: nextUser,
      token: token ?? session!.token,
      createdAt: session?.createdAt ?? DateTime.now(),
    );
    lastUpdated = DateTime.now();
  }

  String _message(Object error) => error is ApiException
      ? error.message
      : 'Não foi possível ler os dados. Confira a versão e a configuração da API.';
  void _handleError(Object error) {
    lastError = _message(error);
    if (error is ApiException && error.statusCode == 401) _clearSession();
  }

  String? _authorize(AppUser actor, String token, {bool admin = false}) {
    final current = session;
    if (current == null ||
        !current.user.active ||
        actor.id != current.user.id ||
        token.isEmpty ||
        token != current.token) {
      return 'Sessão inválida. Entre novamente.';
    }
    if (admin && !current.user.isAdmin) {
      return 'Acesso restrito ao administrador.';
    }
    return null;
  }

  Future<String?> _mutate(
    String resource,
    Map<String, dynamic> body,
    AppUser actor,
    String token,
    String? Function() demoAction, {
    bool admin = false,
  }) async {
    final auth = _authorize(actor, token, admin: admin);
    if (auth != null) return auth;
    if (busy) return 'Aguarde a operação atual terminar.';
    busy = true;
    lastError = null;
    _notify();
    try {
      if (isDemo) {
        final error = demoAction();
        if (error != null) return lastError = error;
      } else {
        _applySnapshot(await _api.request(resource, body: body));
      }
      lastUpdated = DateTime.now();
      _audit(AuditAction.update, 'Operação concluída: $resource');
      return null;
    } catch (error) {
      _handleError(error);
      return lastError;
    } finally {
      busy = false;
      _notify();
    }
  }

  Future<String?> addProduct(Product product, AppUser user, String token) =>
      _saveProduct(product, user, token, create: true);
  Future<String?> updateProduct(Product product, AppUser user, String token) =>
      _saveProduct(product, user, token, create: false);

  Future<String?> _saveProduct(
    Product p,
    AppUser user,
    String token, {
    required bool create,
  }) async {
    if (p.name.trim().isEmpty || p.name.trim().length > 150) {
      return 'Informe um nome de até 150 caracteres.';
    }
    if (!p.costPrice.isFinite ||
        !p.salePrice.isFinite ||
        p.costPrice < 0 ||
        p.salePrice < 0 ||
        p.costPrice > 99999999.99 ||
        p.salePrice > 99999999.99) {
      return 'Informe preços válidos e não negativos.';
    }
    if (p.minimumStock < 0 ||
        p.minimumStock > 2147483647 ||
        p.unit.trim().isEmpty ||
        p.unit.length > 20) {
      return 'Confira o estoque mínimo e a unidade.';
    }
    final body = p.toJson();
    if (create) body.remove('id');
    return _mutate('products', body, user, token, () {
      final index = products.indexWhere((item) => item.id == p.id);
      if (!create && index < 0) return 'Produto não encontrado.';
      final category = categories.where((c) => c.id == p.categoryId);
      final supplier = suppliers.where((s) => s.id == p.supplierId);
      if (p.categoryId != null &&
          (category.isEmpty ||
              (!category.first.active &&
                  (create || products[index].categoryId != p.categoryId)))) {
        return 'Selecione uma categoria ativa.';
      }
      if (p.supplierId != null &&
          (supplier.isEmpty ||
              (!supplier.first.active &&
                  (create || products[index].supplierId != p.supplierId)))) {
        return 'Selecione um fornecedor ativo.';
      }
      final saved = Product(
        id: create ? _nextId(products.map((p) => p.id)) : p.id,
        name: p.name.trim(),
        categoryId: p.categoryId,
        supplierId: p.supplierId,
        category: category.isEmpty ? '' : category.first.name,
        supplier: supplier.isEmpty ? '' : supplier.first.name,
        costPrice: p.costPrice,
        salePrice: p.salePrice,
        stock: create ? 0 : products[index].stock,
        minimumStock: p.minimumStock,
        unit: p.unit.trim(),
        active: p.active,
        description: p.description.trim(),
        createdAt: create ? DateTime.now() : products[index].createdAt,
      );
      if (create) {
        products.add(saved);
      } else {
        products[index] = saved;
      }
      return null;
    });
  }

  Future<String?> toggleProduct(Product product, AppUser user, String token) =>
      _mutate('products/toggle', {'id': product.id}, user, token, () {
        final list = products.where((p) => p.id == product.id);
        if (list.isEmpty) return 'Produto não encontrado.';
        list.first.active = !list.first.active;
        return null;
      });

  Future<String?> addDirectoryItem(
    List<DirectoryItem> list,
    String name,
    String subtitle,
    AppUser user,
    String token, {
    String document = '',
    String phone = '',
    String email = '',
    String address = '',
    String legalName = '',
    String zipCode = '',
    String street = '',
    String number = '',
    String complement = '',
    String district = '',
    String city = '',
    String state = '',
  }) => _saveDirectory(
    list,
    DirectoryItem(
      id: 0,
      name: name,
      subtitle: subtitle,
      document: document,
      phone: phone,
      email: email,
      address: address,
      legalName: legalName,
      zipCode: zipCode,
      street: street,
      number: number,
      complement: complement,
      district: district,
      city: city,
      state: state,
    ),
    user,
    token,
  );

  Future<String?> updateDirectoryItem(
    List<DirectoryItem> list,
    DirectoryItem item,
    AppUser user,
    String token,
  ) => _saveDirectory(list, item, user, token);

  Future<String?> _saveDirectory(
    List<DirectoryItem> list,
    DirectoryItem item,
    AppUser user,
    String token,
  ) async {
    if (!identical(list, categories) && !identical(list, suppliers)) {
      return 'Cadastro inválido.';
    }
    if (item.name.trim().isEmpty) return 'Informe o nome.';
    if (item.email.isNotEmpty && !_validEmail(item.email)) {
      return 'Informe um e-mail válido.';
    }
    final resource = identical(list, categories) ? 'categories' : 'suppliers';
    return _mutate(resource, item.toJson(), user, token, () {
      final index = list.indexWhere((d) => d.id == item.id);
      if (item.id > 0 && index < 0) return 'Cadastro não encontrado.';
      final saved = DirectoryItem(
        id: item.id == 0 ? _nextId(list.map((i) => i.id)) : item.id,
        name: item.name.trim(),
        subtitle: item.subtitle.trim(),
        document: item.document.trim(),
        phone: item.phone.trim(),
        email: item.email.trim(),
        address: item.address.trim(),
        legalName: item.legalName.trim(),
        zipCode: item.zipCode.trim(),
        street: item.street.trim(),
        number: item.number.trim(),
        complement: item.complement.trim(),
        district: item.district.trim(),
        city: item.city.trim(),
        state: item.state.trim().toUpperCase(),
        active: item.active,
        createdAt: index < 0 ? DateTime.now() : list[index].createdAt,
      );
      if (index < 0) {
        list.add(saved);
      } else {
        list[index] = saved;
      }
      for (final p in products) {
        if (resource == 'categories' && p.categoryId == saved.id) {
          p.category = saved.name;
        }
        if (resource == 'suppliers' && p.supplierId == saved.id) {
          p.supplier = saved.name;
        }
      }
      return null;
    });
  }

  Future<String?> toggleDirectoryItem(
    DirectoryItem item,
    AppUser user,
    String token, {
    bool preventSelf = false,
  }) {
    final isCategory = categories.any((c) => identical(c, item));
    final list = isCategory ? categories : suppliers;
    if (!list.any((d) => identical(d, item))) {
      return Future.value('Cadastro não encontrado. Atualize os dados.');
    }
    return _mutate(
      '${isCategory ? 'categories' : 'suppliers'}/toggle',
      {'id': item.id},
      user,
      token,
      () {
        item.active = !item.active;
        return null;
      },
    );
  }

  Future<String?> registerMovement(
    Product product,
    MovementType type,
    int quantity,
    String note,
    AppUser user,
    String token,
  ) async {
    if (quantity <= 0 || quantity > 2147483647) {
      return 'Informe uma quantidade inteira válida.';
    }
    if (note.length > 255) return 'A observação deve ter até 255 caracteres.';
    return _mutate(
      'movements',
      {
        'produto_id': product.id,
        'tipo': type.name,
        'quantidade': quantity,
        'observacao': note.trim(),
        if (type == MovementType.entrada && product.supplierId != null)
          'fornecedor_id': product.supplierId,
        if (type == MovementType.entrada) 'valor_unitario': product.costPrice,
        if (type == MovementType.saida)
          'motivo': note.trim().isEmpty ? 'Saída pelo aplicativo' : note.trim(),
      },
      user,
      token,
      () {
        final found = products.where((p) => p.id == product.id);
        if (found.isEmpty || !found.first.active) {
          return 'Produto inexistente ou inativo.';
        }
        final p = found.first;
        if (type == MovementType.saida && quantity > p.stock) {
          return 'Estoque insuficiente para esta saída.';
        }
        if (type == MovementType.entrada && p.stock + quantity > 2147483647) {
          return 'O saldo ultrapassa o limite permitido.';
        }
        p.stock += type == MovementType.entrada ? quantity : -quantity;
        movements.insert(
          0,
          StockMovement(
            id: _nextId(movements.map((m) => m.id)),
            productId: p.id,
            product: p.name,
            type: type,
            quantity: quantity,
            userId: session!.user.id,
            user: session!.user.name,
            date: DateTime.now(),
            note: note.trim(),
          ),
        );
        return null;
      },
    );
  }

  Future<String?> adjustStock(
    Product product,
    int newBalance,
    String reason,
    AppUser user,
    String token,
  ) {
    if (!user.isAdmin) return Future.value('Acesso restrito ao administrador.');
    if (newBalance < 0 || newBalance > 2147483647) {
      return Future.value('Informe um saldo válido.');
    }
    if (reason.trim().length < 10 || reason.trim().length > 255) {
      return Future.value(
        'A justificativa deve possuir entre 10 e 255 caracteres.',
      );
    }
    return _mutate(
      'movements',
      {
        'produto_id': product.id,
        'tipo': 'ajuste',
        'novo_saldo': newBalance,
        'justificativa': reason.trim(),
      },
      user,
      token,
      () {
        final difference = newBalance - product.stock;
        if (difference == 0) return 'O novo saldo é igual ao saldo atual.';
        product.stock = newBalance;
        movements.insert(
          0,
          StockMovement(
            id: _nextId(movements.map((item) => item.id)),
            productId: product.id,
            product: product.name,
            type: difference > 0 ? MovementType.entrada : MovementType.saida,
            quantity: difference.abs(),
            userId: user.id,
            user: user.name,
            date: DateTime.now(),
            note: reason.trim(),
            previousBalance: newBalance - difference,
            currentBalance: newBalance,
            reason: 'Ajuste administrativo',
            origin: 'ajuste',
          ),
        );
        return null;
      },
      admin: true,
    );
  }

  Future<String?> markNotificationRead(AppNotification item) async {
    final current = session;
    if (current == null) return 'Sessão inválida. Entre novamente.';
    if (item.isRead) return null;
    return _mutate(
      'notifications/read',
      {'id': item.id},
      current.user,
      current.token,
      () => null,
    );
  }

  Future<String?> markAllNotificationsRead() async {
    final current = session;
    if (current == null) return 'Sessão inválida. Entre novamente.';
    return _mutate(
      'notifications/read-all',
      const {},
      current.user,
      current.token,
      () => null,
    );
  }

  Future<String?> saveSettings(
    String systemName,
    String companyName,
    int minimumStock,
    AppUser user,
    String token,
  ) async {
    if (systemName.trim().isEmpty ||
        companyName.trim().isEmpty ||
        minimumStock < 0) {
      return 'Preencha os nomes e um estoque mínimo não negativo.';
    }
    return _mutate(
      'settings',
      {
        'nome_sistema': systemName.trim(),
        'nome_empresa': companyName.trim(),
        'estoque_minimo_padrao': minimumStock,
      },
      user,
      token,
      () {
        settings = SystemSettings(
          systemName: systemName.trim(),
          companyName: companyName.trim(),
          defaultMinimumStock: minimumStock,
        );
        return null;
      },
      admin: true,
    );
  }

  Future<String?> createUser(
    String name,
    String email,
    String password,
    UserRole role,
    AppUser admin,
    String token,
  ) => _saveUser(null, name, email, password, role, admin, token);
  Future<String?> updateUser(
    AppUser target,
    String name,
    String email,
    String password,
    UserRole role,
    AppUser admin,
    String token,
  ) => _saveUser(target, name, email, password, role, admin, token);

  Future<String?> _saveUser(
    AppUser? target,
    String name,
    String email,
    String password,
    UserRole role,
    AppUser actor,
    String token,
  ) async {
    if (name.trim().isEmpty ||
        !_validEmail(email) ||
        ((target == null || password.isNotEmpty) && password.length < 10)) {
      return 'Informe nome, e-mail válido e senha com no mínimo 10 caracteres.';
    }
    if (utf8.encode(password).length > 72 || password.contains('\u0000')) {
      return 'A senha ultrapassa o limite de 72 bytes ou contém caracteres inválidos.';
    }
    return _mutate(
      'users',
      {
        if (target != null) 'id': target.id,
        'nome': name.trim(),
        'email': email.trim().toLowerCase(),
        'senha': password,
        'perfil': role.name,
      },
      actor,
      token,
      () {
        if (users.any(
          (u) =>
              u.id != target?.id &&
              u.email.toLowerCase() == email.trim().toLowerCase(),
        )) {
          return 'E-mail já cadastrado.';
        }
        if (target?.id == session!.user.id && role != UserRole.administrador) {
          return 'Você não pode remover o próprio perfil de administrador.';
        }
        if (target == null) {
          final saved = AppUser(
            id: _nextId(users.map((u) => u.id)),
            name: name.trim(),
            email: email.trim().toLowerCase(),
            role: role,
          );
          users.add(saved);
          _demo!.passwords[saved.id] = password;
        } else {
          final found = users.where((u) => u.id == target.id);
          if (found.isEmpty) return 'Usuário não encontrado.';
          found.first
            ..name = name.trim()
            ..email = email.trim().toLowerCase()
            ..role = role;
          if (password.isNotEmpty) _demo!.passwords[target.id] = password;
        }
        return null;
      },
      admin: true,
    );
  }

  Future<String?> toggleUser(AppUser target, AppUser admin, String token) =>
      _mutate('users/toggle', {'id': target.id}, admin, token, () {
        if (target.id == session!.user.id) {
          return 'Administrador não pode inativar a própria conta.';
        }
        final found = users.where((u) => u.id == target.id);
        if (found.isEmpty) return 'Usuário não encontrado.';
        found.first.active = !found.first.active;
        return null;
      }, admin: true);

  List<StockMovement> movementHistory({
    MovementType? type,
    int? productId,
    DateTime? start,
    DateTime? end,
  }) => movements.where((m) {
    final endExclusive = end == null
        ? null
        : DateTime(end.year, end.month, end.day + 1);
    return (type == null || m.type == type) &&
        (productId == null || m.productId == productId) &&
        (start == null ||
            !m.date.isBefore(DateTime(start.year, start.month, start.day))) &&
        (endExclusive == null || m.date.isBefore(endExclusive));
  }).toList();

  List<ReportSummary> reportsFor(AppUser user) => [
    ReportSummary(
      title: 'Produtos',
      description:
          '${products.where((p) => p.active).length} produtos ativos • valor a preço de custo',
      total: inventoryValue,
    ),
    ReportSummary(
      title: 'Estoque baixo',
      description: 'Produtos ativos no mínimo ou abaixo',
      total: lowStockCount,
    ),
    ReportSummary(
      title: 'Movimentações',
      description: 'Histórico de entradas e saídas',
      total: movements.length,
    ),
    ReportSummary(
      title: 'Categorias',
      description: 'Categorias ativas e inativas',
      total: categories.length,
    ),
    ReportSummary(
      title: 'Fornecedores',
      description: 'Fornecedores cadastrados',
      total: suppliers.length,
    ),
    if (session?.user.isAdmin ?? false)
      ReportSummary(
        title: 'Usuários',
        description: 'Acesso do administrador',
        total: users.length,
      ),
  ];

  List<int> dailyTotals(MovementType type, {DateTime? today}) {
    final now = today ?? DateTime.now();
    return List.generate(7, (index) {
      final day = DateTime(now.year, now.month, now.day - 6 + index);
      return movementHistory(
        type: type,
        start: day,
        end: day,
      ).fold(0, (sum, m) => sum + m.quantity);
    });
  }

  int _todayTotal(MovementType type) => dailyTotals(type).last;
  int _nextId(Iterable<int> ids) =>
      ids.fold(0, (max, id) => id > max ? id : max) + 1;
  bool _validEmail(String email) =>
      RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email.trim());
  void _audit(AuditAction action, String message) {
    auditLogs.insert(
      0,
      AuditLog(
        action: action,
        message: message,
        user: session?.user.name ?? 'Sistema',
        date: DateTime.now(),
      ),
    );
    if (auditLogs.length > 100) auditLogs.removeLast();
  }

  @override
  void dispose() {
    _disposed = true;
    _api.dispose();
    super.dispose();
  }
}
