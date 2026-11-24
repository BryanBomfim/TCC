import 'package:flutter/material.dart';
import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';
import '../widgets/form_helpers.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({required this.store, required this.user, super.key});
  final InventoryStore store;
  final AppUser user;
  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  String query = '';
  bool lowOnly = false;
  bool changingStatus = false;

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: widget.store,
    builder: (context, _) {
      final search = query.trim().toLowerCase();
      final products = widget.store.products
          .where(
            (product) =>
                '${product.name} ${product.category} ${product.supplier}'
                    .toLowerCase()
                    .contains(search) &&
                (!lowOnly || (product.active && product.isLowStock)),
          )
          .toList();
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Produtos',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              FilledButton.icon(
                onPressed: widget.store.busy ? null : () => _edit(),
                icon: const Icon(Icons.add),
                label: const Text('Novo'),
              ),
            ],
          ),
          const SizedBox(height: 14),
          TextField(
            onChanged: (value) => setState(() => query = value),
            decoration: const InputDecoration(
              hintText: 'Buscar nome, categoria ou fornecedor...',
              prefixIcon: Icon(Icons.search),
            ),
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Exibir apenas estoque baixo'),
            value: lowOnly,
            onChanged: (value) => setState(() => lowOnly = value ?? false),
          ),
          if (products.isEmpty)
            const Padding(
              padding: EdgeInsets.all(30),
              child: Center(child: Text('Nenhum produto encontrado.')),
            ),
          ...products.map(
            (product) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                color: product.active && product.isLowStock
                    ? AppColors.warningBackground
                    : Colors.white,
                child: ListTile(
                  contentPadding: const EdgeInsets.all(14),
                  onTap: () => _details(product),
                  leading: CircleAvatar(
                    backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                    child: const Icon(
                      Icons.inventory_2_outlined,
                      color: AppColors.primary,
                    ),
                  ),
                  title: Text(
                    product.name,
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${product.category} • ${product.supplier}'),
                      Text(
                        '${product.stock} ${product.unit} • ${money(product.salePrice)}',
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          StatusBadge(
                            label: product.active ? 'Ativo' : 'Inativo',
                            color: product.active
                                ? AppColors.success
                                : Colors.grey,
                          ),
                          if (product.active && product.isLowStock)
                            const StatusBadge(
                              label: 'Estoque baixo',
                              color: AppColors.danger,
                            ),
                        ],
                      ),
                    ],
                  ),
                  trailing: PopupMenuButton<String>(
                    enabled: !changingStatus && !widget.store.busy,
                    onSelected: (action) {
                      if (action == 'edit') {
                        _edit(product);
                      } else {
                        _toggle(product);
                      }
                    },
                    itemBuilder: (_) => [
                      const PopupMenuItem(value: 'edit', child: Text('Editar')),
                      PopupMenuItem(
                        value: 'status',
                        child: Text(product.active ? 'Inativar' : 'Ativar'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      );
    },
  );

  Future<void> _edit([Product? product]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      isDismissible: false,
      enableDrag: false,
      builder: (_) => _ProductForm(
        store: widget.store,
        user: widget.user,
        product: product,
      ),
    );
    if (!mounted || saved != true) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('Produto salvo com sucesso.')));
  }

  Future<void> _toggle(Product product) async {
    if (changingStatus || widget.store.busy) return;
    setState(() => changingStatus = true);
    final error = await widget.store.toggleProduct(
      product,
      widget.user,
      widget.store.csrfToken,
    );
    if (!mounted) return;
    setState(() => changingStatus = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error ?? 'Situação do produto atualizada.')),
    );
  }

  Future<void> _details(Product product) => showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (sheetContext) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(product.name, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 12),
              Text(
                product.description.isEmpty
                    ? 'Sem descrição.'
                    : product.description,
              ),
              const Divider(height: 28),
              Text(
                'SKU: ${product.sku.isEmpty ? 'Não informado' : product.sku}\n'
                'Código de barras: ${product.barcode.isEmpty ? 'Não informado' : product.barcode}\n'
                'Categoria: ${product.category}\nFornecedor: ${product.supplier}\n'
                'Custo: ${money(product.costPrice)}\nVenda: ${money(product.salePrice)}\n'
                'Estoque: ${product.stock} ${product.unit}\nEstoque mínimo: ${product.minimumStock} ${product.unit}\n'
                'Situação: ${product.active ? 'Ativo' : 'Inativo'}',
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () {
                  Navigator.pop(sheetContext);
                  _edit(product);
                },
                icon: const Icon(Icons.edit_outlined),
                label: const Text('Editar produto'),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _ProductForm extends StatefulWidget {
  const _ProductForm({required this.store, required this.user, this.product});
  final InventoryStore store;
  final AppUser user;
  final Product? product;
  @override
  State<_ProductForm> createState() => _ProductFormState();
}

class _ProductFormState extends State<_ProductForm> {
  final formKey = GlobalKey<FormState>();
  late final TextEditingController name,
      description,
      barcode,
      cost,
      sale,
      minimum,
      unit;
  int? categoryId, supplierId;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    final product = widget.product;
    name = TextEditingController(text: product?.name ?? '');
    description = TextEditingController(text: product?.description ?? '');
    barcode = TextEditingController(text: product?.barcode ?? '');
    cost = TextEditingController(
      text: (product?.costPrice ?? 0).toStringAsFixed(2).replaceAll('.', ','),
    );
    sale = TextEditingController(
      text: product?.salePrice.toStringAsFixed(2).replaceAll('.', ',') ?? '',
    );
    minimum = TextEditingController(
      text:
          '${product?.minimumStock ?? widget.store.settings.defaultMinimumStock}',
    );
    unit = TextEditingController(text: product?.unit ?? 'UN');
    categoryId = product?.categoryId;
    supplierId = product?.supplierId;
  }

  @override
  void dispose() {
    for (final controller in [
      name,
      description,
      barcode,
      cost,
      sale,
      minimum,
      unit,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Widget _directoryField(
    String label,
    List<DirectoryItem> all,
    int? id,
    ValueChanged<int?> onChanged,
  ) {
    final items = all.where((item) => item.active || item.id == id).toList();
    final missing = id != null && !items.any((item) => item.id == id);
    return DropdownButtonFormField<int>(
      value: id ?? 0,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: [
        const DropdownMenuItem(value: 0, child: Text('Não informado')),
        ...items.map(
          (item) => DropdownMenuItem(
            value: item.id,
            child: Text(
              '${item.name}${item.active ? '' : ' (inativo)'}',
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
        if (missing)
          DropdownMenuItem(
            value: id,
            child: const Text('Cadastro indisponível'),
          ),
      ],
      onChanged: saving
          ? null
          : (value) => setState(() => onChanged(value == 0 ? null : value)),
    );
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !saving,
    child: SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          20,
          0,
          20,
          MediaQuery.viewInsetsOf(context).bottom + 24,
        ),
        child: SingleChildScrollView(
          child: Form(
            key: formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  widget.product == null ? 'Novo produto' : 'Editar produto',
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: name,
                  enabled: !saving,
                  validator: validateRequiredName,
                  decoration: const InputDecoration(labelText: 'Nome *'),
                  maxLength: 150,
                ),
                TextFormField(
                  controller: description,
                  enabled: !saving,
                  maxLines: 2,
                  decoration: const InputDecoration(labelText: 'Descrição'),
                ),
                TextFormField(
                  controller: barcode,
                  enabled: !saving,
                  maxLength: 50,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Código de barras',
                    hintText: 'Digite ou use um leitor conectado',
                  ),
                ),
                if (widget.product?.sku.isNotEmpty ?? false)
                  Text('SKU gerado pelo sistema: ${widget.product!.sku}'),
                const SizedBox(height: 12),
                _directoryField(
                  'Categoria',
                  widget.store.categories,
                  categoryId,
                  (id) => categoryId = id,
                ),
                const SizedBox(height: 12),
                _directoryField(
                  'Fornecedor',
                  widget.store.suppliers,
                  supplierId,
                  (id) => supplierId = id,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: cost,
                  enabled: !saving,
                  validator: validateMoney,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Preço de custo *',
                    prefixText: 'R\$ ',
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: sale,
                  enabled: !saving,
                  validator: validateMoney,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: const InputDecoration(
                    labelText: 'Preço de venda *',
                    prefixText: 'R\$ ',
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: minimum,
                  enabled: !saving,
                  validator: (value) => validateQuantity(value),
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Estoque mínimo *',
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: unit,
                  enabled: !saving,
                  maxLength: 10,
                  validator: (value) => (value?.trim().isEmpty ?? true)
                      ? 'Informe a unidade.'
                      : null,
                  textCapitalization: TextCapitalization.characters,
                  decoration: const InputDecoration(
                    labelText: 'Unidade *',
                    hintText: 'UN, KG, PCT...',
                  ),
                ),
                Text(
                  widget.product == null
                      ? 'O estoque inicia em zero. Registre uma entrada após cadastrar.'
                      : 'Saldo atual: ${widget.product!.stock} ${widget.product!.unit}. Para alterar, registre uma movimentação.',
                  style: const TextStyle(color: Colors.black54),
                ),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(
                      error!,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: saving ? null : _save,
                  icon: const Icon(Icons.save_outlined),
                  label: Text(saving ? 'Salvando...' : 'Salvar produto'),
                ),
                TextButton(
                  onPressed: saving ? null : () => Navigator.pop(context),
                  child: const Text('Cancelar'),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );

  Future<void> _save() async {
    if (saving || !(formKey.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() {
      saving = true;
      error = null;
    });
    String directoryName(List<DirectoryItem> items, int? id, String fallback) {
      final matches = items.where((item) => item.id == id);
      return matches.isEmpty ? fallback : matches.first.name;
    }

    final previous = widget.product;
    final product = Product(
      id: previous?.id ?? 0,
      name: name.text.trim(),
      description: description.text.trim(),
      sku: previous?.sku ?? '',
      barcode: barcode.text.trim(),
      categoryId: categoryId,
      supplierId: supplierId,
      category: directoryName(
        widget.store.categories,
        categoryId,
        'Sem categoria',
      ),
      supplier: directoryName(
        widget.store.suppliers,
        supplierId,
        'Sem fornecedor',
      ),
      costPrice: parseMoneyInput(cost.text)!,
      salePrice: parseMoneyInput(sale.text)!,
      minimumStock: parseQuantityInput(minimum.text)!,
      stock: previous?.stock ?? 0,
      unit: unit.text.trim().toUpperCase(),
      active: previous?.active ?? true,
      createdAt: previous?.createdAt,
    );
    final message = previous == null
        ? await widget.store.addProduct(
            product,
            widget.user,
            widget.store.csrfToken,
          )
        : await widget.store.updateProduct(
            product,
            widget.user,
            widget.store.csrfToken,
          );
    if (!mounted) return;
    if (message == null) {
      Navigator.pop(context, true);
      return;
    }
    setState(() {
      saving = false;
      error = message;
    });
  }
}
