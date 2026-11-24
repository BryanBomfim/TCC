enum UserRole { administrador, funcionario }

enum MovementType { entrada, saida }

enum AuditAction {
  login,
  logout,
  create,
  update,
  toggle,
  movement,
  denied,
  security,
}

class AppUser {
  AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.passwordHash = '',
    this.active = true,
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  final int id;
  String name;
  String email;
  UserRole role;
  String passwordHash;
  bool active;
  final DateTime createdAt;

  bool get isAdmin => role == UserRole.administrador;

  String get roleLabel => isAdmin ? 'Administrador' : 'Funcionário';

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
    id: _int(json['id']),
    name: _text(json['nome']),
    email: _text(json['email']),
    role: json['perfil'] == 'administrador'
        ? UserRole.administrador
        : UserRole.funcionario,
    active: _active(json['ativo']),
    createdAt: _date(json['data_cadastro']),
  );
}

class AuthSession {
  const AuthSession({
    required this.user,
    required this.token,
    required this.createdAt,
  });

  final AppUser user;
  final String token;
  final DateTime createdAt;
}

class Product {
  Product({
    required this.id,
    required this.name,
    required this.category,
    required this.supplier,
    required this.costPrice,
    required this.salePrice,
    required this.stock,
    required this.minimumStock,
    this.unit = 'UN',
    this.active = true,
    this.description = '',
    this.sku = '',
    this.barcode = '',
    this.categoryId,
    this.supplierId,
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  final int id;
  String name;
  String category;
  String supplier;
  double costPrice;
  double salePrice;
  int stock;
  int minimumStock;
  String unit;
  bool active;
  String description;
  String sku;
  String barcode;
  int? categoryId;
  int? supplierId;
  final DateTime createdAt;

  bool get isLowStock => stock <= minimumStock;

  double get stockValue => stock * costPrice;

  factory Product.fromJson(Map<String, dynamic> json) => Product(
    id: _int(json['id']),
    name: _text(json['nome']),
    category: _text(json['categoria']),
    supplier: _text(json['fornecedor']),
    categoryId: json['categoria_id'] == null
        ? null
        : _int(json['categoria_id']),
    supplierId: json['fornecedor_id'] == null
        ? null
        : _int(json['fornecedor_id']),
    costPrice: _double(json['preco_custo']),
    salePrice: _double(json['preco_venda']),
    stock: _int(json['quantidade_estoque']),
    minimumStock: _int(json['estoque_minimo']),
    unit: _text(json['unidade']),
    active: _active(json['ativo']),
    description: _text(json['descricao']),
    sku: _text(json['sku']),
    barcode: _text(json['codigo_barras']),
    createdAt: _date(json['data_cadastro']),
  );

  Map<String, dynamic> toJson() => {
    if (id > 0) 'id': id,
    'nome': name,
    'descricao': description,
    'codigo_barras': barcode,
    'categoria_id': categoryId,
    'fornecedor_id': supplierId,
    'preco_custo': costPrice,
    'preco_venda': salePrice,
    'estoque_minimo': minimumStock,
    'unidade': unit,
    'ativo': active ? 1 : 0,
  };
}

class StockMovement {
  StockMovement({
    required this.id,
    required this.productId,
    required this.product,
    required this.type,
    required this.quantity,
    required this.userId,
    required this.user,
    required this.date,
    this.note = '',
    this.previousBalance,
    this.currentBalance,
    this.reason = '',
    this.origin = '',
  });

  final int id;
  final int productId;
  final String product;
  final MovementType type;
  final int quantity;
  final int userId;
  final String user;
  final DateTime date;
  final String note;
  final int? previousBalance;
  final int? currentBalance;
  final String reason;
  final String origin;

  factory StockMovement.fromJson(Map<String, dynamic> json) => StockMovement(
    id: _int(json['id']),
    productId: _int(json['produto_id']),
    product: _text(json['produto']),
    userId: _int(json['usuario_id']),
    user: _text(json['usuario']),
    type: json['tipo'] == 'entrada' ? MovementType.entrada : MovementType.saida,
    quantity: _int(json['quantidade']),
    date: _date(json['data_movimentacao']),
    note: _text(json['observacao']),
    previousBalance: json['saldo_anterior'] == null
        ? null
        : _int(json['saldo_anterior']),
    currentBalance: json['saldo_posterior'] == null
        ? null
        : _int(json['saldo_posterior']),
    reason: _text(json['motivo']),
    origin: _text(json['origem']),
  );
}

class DirectoryItem {
  DirectoryItem({
    required this.id,
    required this.name,
    this.subtitle = '',
    this.active = true,
    this.document = '',
    this.phone = '',
    this.email = '',
    this.address = '',
    this.legalName = '',
    this.zipCode = '',
    this.street = '',
    this.number = '',
    this.complement = '',
    this.district = '',
    this.city = '',
    this.state = '',
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  final int id;
  String name;
  String subtitle;
  String document;
  String phone;
  String email;
  String address;
  String legalName;
  String zipCode;
  String street;
  String number;
  String complement;
  String district;
  String city;
  String state;
  bool active;
  final DateTime createdAt;

  factory DirectoryItem.fromJson(Map<String, dynamic> json) => DirectoryItem(
    id: _int(json['id']),
    name: _text(json['nome']),
    subtitle: _text(json['descricao']),
    document: _text(json['cnpj_cpf']),
    phone: _text(json['telefone']),
    email: _text(json['email']),
    address: _text(json['endereco']),
    legalName: _text(json['razao_social']),
    zipCode: _text(json['cep']),
    street: _text(json['logradouro']),
    number: _text(json['numero']),
    complement: _text(json['complemento']),
    district: _text(json['bairro']),
    city: _text(json['cidade']),
    state: _text(json['uf']),
    active: _active(json['ativo']),
    createdAt: _date(json['data_cadastro']),
  );

  Map<String, dynamic> toJson() => {
    if (id > 0) 'id': id,
    'nome': name,
    'descricao': subtitle,
    'cnpj_cpf': document,
    'telefone': phone,
    'email': email,
    'endereco': address,
    'razao_social': legalName,
    'cep': zipCode,
    'logradouro': street,
    'numero': number,
    'complemento': complement,
    'bairro': district,
    'cidade': city,
    'uf': state,
    'ativo': active ? 1 : 0,
  };
}

class SystemSettings {
  SystemSettings({
    this.systemName = 'SmartControl',
    this.companyName = 'Minha Empresa',
    this.defaultMinimumStock = 5,
  });

  String systemName;
  String companyName;
  int defaultMinimumStock;

  factory SystemSettings.fromJson(Map<String, dynamic> json) => SystemSettings(
    systemName: _text(json['nome_sistema']),
    companyName: _text(json['nome_empresa']),
    defaultMinimumStock: _int(json['estoque_minimo_padrao']),
  );
}

class AuditLog {
  const AuditLog({
    required this.action,
    required this.message,
    required this.user,
    required this.date,
  });

  final AuditAction action;
  final String message;
  final String user;
  final DateTime date;
}

class InventorySummary {
  const InventorySummary({
    required this.id,
    required this.title,
    required this.status,
    required this.createdBy,
    required this.createdAt,
    required this.totalItems,
    required this.countedItems,
    required this.divergences,
    this.notes = '',
    this.revision = 0,
    this.finishedBy = '',
    this.finishedAt,
  });

  final int id;
  final String title;
  final String status;
  final String notes;
  final int revision;
  final String createdBy;
  final DateTime createdAt;
  final String finishedBy;
  final DateTime? finishedAt;
  final int totalItems;
  final int countedItems;
  final int divergences;

  factory InventorySummary.fromJson(Map<String, dynamic> json) =>
      InventorySummary(
        id: _int(json['id']),
        title: _text(json['titulo']),
        status: _text(json['status']),
        notes: _text(json['observacao']),
        revision: _int(json['revisao'] ?? 0),
        createdBy: _text(json['criado_por_nome']),
        createdAt: _date(json['criado_em']),
        finishedBy: _text(json['finalizado_por_nome']),
        finishedAt: _nullableDate(json['finalizado_em']),
        totalItems: _int(json['total_itens'] ?? 0),
        countedItems: _int(json['itens_contados'] ?? 0),
        divergences: _int(json['divergencias'] ?? 0),
      );
}

class InventoryItem {
  const InventoryItem({
    required this.id,
    required this.inventoryId,
    required this.productId,
    required this.product,
    required this.systemStock,
    this.sku = '',
    this.barcode = '',
    this.countedQuantity,
    this.difference,
    this.countedBy = '',
    this.countedAt,
    this.adjusted = false,
  });

  final int id;
  final int inventoryId;
  final int productId;
  final String product;
  final String sku;
  final String barcode;
  final int systemStock;
  final int? countedQuantity;
  final int? difference;
  final String countedBy;
  final DateTime? countedAt;
  final bool adjusted;

  factory InventoryItem.fromJson(Map<String, dynamic> json) => InventoryItem(
    id: _int(json['id']),
    inventoryId: _int(json['inventario_id']),
    productId: _int(json['produto_id']),
    product: _text(json['produto']),
    sku: _text(json['sku']),
    barcode: _text(json['codigo_barras']),
    systemStock: _int(json['estoque_sistema']),
    countedQuantity: json['quantidade_contada'] == null
        ? null
        : _int(json['quantidade_contada']),
    difference: json['diferenca'] == null ? null : _int(json['diferenca']),
    countedBy: _text(json['contado_por_nome']),
    countedAt: _nullableDate(json['contado_em']),
    adjusted: _active(json['ajustado']),
  );
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.createdAt,
    this.entity = '',
    this.entityId,
    this.link = '',
    this.readAt,
  });

  final int id;
  final String type;
  final String title;
  final String message;
  final String entity;
  final int? entityId;
  final String link;
  final DateTime? readAt;
  final DateTime createdAt;

  bool get isRead => readAt != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) =>
      AppNotification(
        id: _int(json['id']),
        type: _text(json['tipo']),
        title: _text(json['titulo']),
        message: _text(json['mensagem']),
        entity: _text(json['entidade']),
        entityId: json['entidade_id'] == null
            ? null
            : _int(json['entidade_id']),
        link: _text(json['link']),
        readAt: _nullableDate(json['lida_em']),
        createdAt: _date(json['criado_em']),
      );
}

class RemoteAuditLog {
  const RemoteAuditLog({
    required this.id,
    required this.action,
    required this.module,
    required this.description,
    required this.user,
    required this.createdAt,
  });

  final int id;
  final String action;
  final String module;
  final String description;
  final String user;
  final DateTime createdAt;

  factory RemoteAuditLog.fromJson(Map<String, dynamic> json) => RemoteAuditLog(
    id: _int(json['id']),
    action: _text(json['acao']),
    module: _text(json['modulo']),
    description: _text(json['descricao']),
    user: _text(json['usuario_nome']),
    createdAt: _date(json['criado_em']),
  );
}

class ReportSummary {
  const ReportSummary({
    required this.title,
    required this.description,
    required this.total,
  });

  final String title;
  final String description;
  final num total;
}

int _int(Object? value) => value is int ? value : int.parse(value.toString());
double _double(Object? value) =>
    value is num ? value.toDouble() : double.parse(value.toString());
String _text(Object? value) => value?.toString() ?? '';
bool _active(Object? value) => value == true || value == 1 || value == '1';
DateTime _date(Object? value) =>
    value == null ? DateTime.now() : DateTime.parse(value.toString());
DateTime? _nullableDate(Object? value) =>
    value == null || value.toString().isEmpty
    ? null
    : DateTime.parse(value.toString());
