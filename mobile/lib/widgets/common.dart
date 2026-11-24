import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../models/models.dart';

class SectionCard extends StatelessWidget {
  const SectionCard({
    required this.title,
    required this.child,
    this.action,
    super.key,
  });

  final String title;
  final Widget child;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                if (action != null) action!,
              ],
            ),
            const SizedBox(height: 14),
            const Divider(height: 1, color: AppColors.border),
            const SizedBox(height: 16),
            child,
          ],
        ),
      ),
    );
  }
}

class StatusBadge extends StatelessWidget {
  const StatusBadge({required this.label, required this.color, super.key});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class MovementTile extends StatelessWidget {
  const MovementTile({required this.movement, super.key});

  final StockMovement movement;

  @override
  Widget build(BuildContext context) {
    final entry = movement.type == MovementType.entrada;
    final date = movement.date;
    final color = entry ? AppColors.success : AppColors.danger;

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleAvatar(
        backgroundColor: color.withValues(alpha: 0.12),
        child: Icon(entry ? Icons.south_west : Icons.north_east, color: color),
      ),
      title: Text(
        movement.product,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        '${movement.user} • '
        '${date.day.toString().padLeft(2, '0')}/'
        '${date.month.toString().padLeft(2, '0')} às '
        '${date.hour.toString().padLeft(2, '0')}:'
        '${date.minute.toString().padLeft(2, '0')}'
        '${movement.origin.isEmpty ? '' : '\nOrigem: ${movement.origin}'}'
        '${movement.previousBalance == null || movement.currentBalance == null ? '' : '\nSaldo: ${movement.previousBalance} → ${movement.currentBalance}'}'
        '${movement.reason.isEmpty ? '' : '\n${movement.reason}'}',
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          StatusBadge(label: entry ? 'Entrada' : 'Saída', color: color),
          const SizedBox(height: 3),
          Text(
            '${entry ? '+' : '-'}${movement.quantity}',
            style: TextStyle(fontWeight: FontWeight.bold, color: color),
          ),
        ],
      ),
    );
  }
}

String money(double value) {
  final parts = value.toStringAsFixed(2).split('.');
  final whole = parts[0].replaceAllMapped(
    RegExp(r'\B(?=(\d{3})+(?!\d))'),
    (_) => '.',
  );
  return 'R\$ $whole,${parts[1]}';
}

String formatDateTime(DateTime date) {
  return '${date.day.toString().padLeft(2, '0')}/'
      '${date.month.toString().padLeft(2, '0')}/'
      '${date.year} às '
      '${date.hour.toString().padLeft(2, '0')}:'
      '${date.minute.toString().padLeft(2, '0')}';
}
