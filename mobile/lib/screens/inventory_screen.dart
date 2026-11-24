import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';

class InventoryScreen extends StatelessWidget {
  const InventoryScreen({required this.store, super.key});

  final InventoryStore store;

  Color _statusColor(String status) => switch (status) {
    'aberto' => AppColors.warning,
    'finalizado' => AppColors.success,
    _ => Colors.grey,
  };

  String _statusLabel(String status) => switch (status) {
    'aberto' => 'Aberto',
    'finalizado' => 'Finalizado',
    'cancelado' => 'Cancelado',
    _ => status,
  };

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: store,
    builder: (context, _) {
      final open = store.inventories.where((item) => item.status == 'aberto');
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Inventário físico',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          const Text(
            'Acompanhe contagens, divergências e o andamento registrado no sistema web.',
          ),
          const SizedBox(height: 16),
          if (store.inventories.isEmpty)
            const SectionCard(
              title: 'Nenhum inventário',
              child: Text('Ainda não há inventários cadastrados no sistema.'),
            ),
          if (open.isNotEmpty) _openInventory(context, open.first),
          if (store.inventories.isNotEmpty) ...[
            const SizedBox(height: 12),
            ...store.inventories.map((item) => _inventoryCard(context, item)),
          ],
        ],
      );
    },
  );

  Widget _openInventory(BuildContext context, InventorySummary inventory) {
    final items = store.inventoryItems
        .where((item) => item.inventoryId == inventory.id)
        .toList();
    return SectionCard(
      title: 'Contagem em andamento',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            inventory.title,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          Text('${inventory.countedItems} de ${inventory.totalItems} contados'),
          LinearProgressIndicator(
            value: inventory.totalItems == 0
                ? 0
                : inventory.countedItems / inventory.totalItems,
          ),
          const SizedBox(height: 12),
          ...items.take(50).map(_inventoryItem),
          if (items.length > 50)
            Text('Mais ${items.length - 50} itens disponíveis no sistema web.'),
        ],
      ),
    );
  }

  Widget _inventoryItem(InventoryItem item) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: CircleAvatar(
      child: Text(
        item.countedQuantity == null ? '?' : '${item.countedQuantity}',
      ),
    ),
    title: Text(item.product),
    subtitle: Text(
      'Sistema: ${item.systemStock}'
      '${item.difference == null ? '' : ' • Diferença: ${item.difference}'}'
      '${item.sku.isEmpty ? '' : ' • ${item.sku}'}',
    ),
    trailing: item.adjusted
        ? const Icon(Icons.check_circle, color: AppColors.success)
        : null,
  );

  Widget _inventoryCard(BuildContext context, InventorySummary item) => Card(
    child: ListTile(
      contentPadding: const EdgeInsets.all(14),
      title: Text(
        item.title,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        'Criado por ${item.createdBy} em ${formatDateTime(item.createdAt)}\n'
        '${item.countedItems}/${item.totalItems} contados • ${item.divergences} divergências',
      ),
      trailing: StatusBadge(
        label: _statusLabel(item.status),
        color: _statusColor(item.status),
      ),
    ),
  );
}
