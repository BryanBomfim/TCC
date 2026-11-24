import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';
import '../widgets/form_helpers.dart';

class StockScreen extends StatefulWidget {
  const StockScreen({required this.store, required this.user, super.key});
  final InventoryStore store;
  final AppUser user;

  @override
  State<StockScreen> createState() => _StockScreenState();
}

class _StockScreenState extends State<StockScreen> {
  MovementType? filter;
  int? productId;
  DateTimeRange? range;
  bool formOpen = false;

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: widget.store,
    builder: (context, _) {
      final movements = widget.store.movementHistory(
        type: filter,
        productId: productId,
        start: range?.start,
        end: range?.end,
      );
      return RefreshIndicator(
        onRefresh: widget.store.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Movimentações de estoque',
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.success,
                    ),
                    onPressed: widget.store.busy || formOpen
                        ? null
                        : () => _movementForm(MovementType.entrada),
                    icon: const Icon(Icons.add),
                    label: const Text('Entrada'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.danger,
                    ),
                    onPressed: widget.store.busy || formOpen
                        ? null
                        : () => _movementForm(MovementType.saida),
                    icon: const Icon(Icons.remove),
                    label: const Text('Saída'),
                  ),
                ),
              ],
            ),
            if (widget.user.isAdmin) ...[
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: widget.store.busy || formOpen ? null : _adjustForm,
                icon: const Icon(Icons.tune),
                label: const Text('Ajustar saldo administrativo'),
              ),
            ],
            const SizedBox(height: 12),
            MovementFilters(
              products: widget.store.products,
              type: filter,
              productId: productId,
              range: range,
              onTypeChanged: (value) => setState(() => filter = value),
              onProductChanged: (value) => setState(() => productId = value),
              onRangeChanged: (value) => setState(() => range = value),
            ),
            const SizedBox(height: 14),
            Text(
              '${movements.length} movimentações encontradas',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 8),
            Card(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                child: movements.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.all(24),
                        child: Center(
                          child: Text('Nenhuma movimentação neste filtro.'),
                        ),
                      )
                    : Column(
                        children: movements
                            .map((movement) => MovementTile(movement: movement))
                            .toList(),
                      ),
              ),
            ),
          ],
        ),
      );
    },
  );

  Future<void> _movementForm(MovementType type) async {
    if (formOpen || widget.store.busy) return;
    if (!widget.store.products.any((product) => product.active)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Cadastre ou ative um produto antes de movimentar o estoque.',
          ),
        ),
      );
      return;
    }
    setState(() => formOpen = true);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      isDismissible: false,
      enableDrag: false,
      builder: (_) =>
          _MovementForm(store: widget.store, user: widget.user, type: type),
    );
    if (!mounted) return;
    setState(() => formOpen = false);
    if (saved == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Movimentação registrada com sucesso.')),
      );
    }
  }

  Future<void> _adjustForm() async {
    if (formOpen || widget.store.busy) return;
    setState(() => formOpen = true);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      isDismissible: false,
      enableDrag: false,
      builder: (_) => _StockAdjustForm(store: widget.store, user: widget.user),
    );
    if (!mounted) return;
    setState(() => formOpen = false);
    if (saved == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Saldo ajustado com sucesso.')),
      );
    }
  }
}

class _MovementForm extends StatefulWidget {
  const _MovementForm({
    required this.store,
    required this.user,
    required this.type,
  });
  final InventoryStore store;
  final AppUser user;
  final MovementType type;

  @override
  State<_MovementForm> createState() => _MovementFormState();
}

class _MovementFormState extends State<_MovementForm> {
  final formKey = GlobalKey<FormState>();
  final quantity = TextEditingController();
  final note = TextEditingController();
  int? selectedId;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    final active = widget.store.products.where((product) => product.active);
    selectedId = active.isEmpty ? null : active.first.id;
  }

  @override
  void dispose() {
    quantity.dispose();
    note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: widget.store,
    builder: (context, _) {
      final products = widget.store.products
          .where((product) => product.active)
          .toList();
      final matches = products.where((product) => product.id == selectedId);
      final selected = matches.isEmpty ? null : matches.first;
      final locked = saving || widget.store.busy;
      return PopScope(
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
                      widget.type == MovementType.entrada
                          ? 'Registrar entrada'
                          : 'Registrar saída',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: widget.type == MovementType.entrada
                            ? AppColors.success
                            : AppColors.danger,
                      ),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<int>(
                      value: selected?.id,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Produto *'),
                      items: products
                          .map(
                            (product) => DropdownMenuItem(
                              value: product.id,
                              child: Text(
                                product.name,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          )
                          .toList(),
                      validator: (value) => value == null || selected == null
                          ? 'Selecione um produto ativo.'
                          : null,
                      onChanged: locked
                          ? null
                          : (value) => setState(() => selectedId = value),
                    ),
                    if (selected != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          'Saldo atual: ${selected.stock} ${selected.unit}',
                        ),
                      ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: quantity,
                      enabled: !locked,
                      keyboardType: TextInputType.number,
                      validator: (value) =>
                          validateQuantity(value, positive: true),
                      decoration: const InputDecoration(
                        labelText: 'Quantidade *',
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: note,
                      enabled: !locked,
                      maxLines: 2,
                      maxLength: 255,
                      decoration: const InputDecoration(
                        labelText: 'Observação',
                      ),
                    ),
                    if (error != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          error!,
                          style: const TextStyle(color: AppColors.danger),
                        ),
                      ),
                    const SizedBox(height: 18),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: widget.type == MovementType.entrada
                            ? AppColors.success
                            : AppColors.danger,
                      ),
                      onPressed: locked ? null : _save,
                      child: Text(
                        saving ? 'Registrando...' : 'Confirmar movimentação',
                      ),
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
    },
  );

  Future<void> _save() async {
    if (saving ||
        widget.store.busy ||
        !(formKey.currentState?.validate() ?? false)) {
      return;
    }
    final products = widget.store.products.where(
      (product) => product.id == selectedId && product.active,
    );
    if (products.isEmpty) {
      setState(() => error = 'Selecione um produto ativo.');
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() {
      saving = true;
      error = null;
    });
    final message = await widget.store.registerMovement(
      products.first,
      widget.type,
      parseQuantityInput(quantity.text)!,
      note.text.trim(),
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

class _StockAdjustForm extends StatefulWidget {
  const _StockAdjustForm({required this.store, required this.user});

  final InventoryStore store;
  final AppUser user;

  @override
  State<_StockAdjustForm> createState() => _StockAdjustFormState();
}

class _StockAdjustFormState extends State<_StockAdjustForm> {
  final formKey = GlobalKey<FormState>();
  final balance = TextEditingController();
  final reason = TextEditingController();
  int? productId;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    final active = widget.store.products.where((item) => item.active);
    if (active.isNotEmpty) {
      productId = active.first.id;
      balance.text = '${active.first.stock}';
    }
  }

  @override
  void dispose() {
    balance.dispose();
    reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final products = widget.store.products
        .where((item) => item.active)
        .toList();
    return PopScope(
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
                    'Ajustar saldo',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    value: productId,
                    decoration: const InputDecoration(labelText: 'Produto *'),
                    items: products
                        .map(
                          (item) => DropdownMenuItem(
                            value: item.id,
                            child: Text(item.name),
                          ),
                        )
                        .toList(),
                    onChanged: saving
                        ? null
                        : (value) {
                            setState(() {
                              productId = value;
                              final match = products.where(
                                (item) => item.id == value,
                              );
                              if (match.isNotEmpty) {
                                balance.text = '${match.first.stock}';
                              }
                            });
                          },
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: balance,
                    enabled: !saving,
                    keyboardType: TextInputType.number,
                    validator: validateQuantity,
                    decoration: const InputDecoration(
                      labelText: 'Novo saldo *',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: reason,
                    enabled: !saving,
                    minLines: 2,
                    maxLines: 3,
                    maxLength: 255,
                    validator: (value) {
                      final text = value?.trim() ?? '';
                      return text.length < 10
                          ? 'Informe uma justificativa com pelo menos 10 caracteres.'
                          : null;
                    },
                    decoration: const InputDecoration(
                      labelText: 'Justificativa *',
                    ),
                  ),
                  if (error != null)
                    Text(
                      error!,
                      style: const TextStyle(color: AppColors.danger),
                    ),
                  const SizedBox(height: 18),
                  FilledButton(
                    onPressed: saving ? null : _save,
                    child: Text(saving ? 'Ajustando...' : 'Confirmar ajuste'),
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
  }

  Future<void> _save() async {
    if (saving || !(formKey.currentState?.validate() ?? false)) return;
    final products = widget.store.products.where(
      (item) => item.id == productId,
    );
    if (products.isEmpty) return;
    setState(() {
      saving = true;
      error = null;
    });
    final message = await widget.store.adjustStock(
      products.first,
      parseQuantityInput(balance.text)!,
      reason.text,
      widget.user,
      widget.store.csrfToken,
    );
    if (!mounted) return;
    if (message == null) {
      Navigator.pop(context, true);
    } else {
      setState(() {
        saving = false;
        error = message;
      });
    }
  }
}
