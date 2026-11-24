import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({required this.store, super.key});

  final InventoryStore store;

  Color _color(String type) => switch (type) {
    'sucesso' => AppColors.success,
    'alerta' => AppColors.warning,
    'critico' => AppColors.danger,
    _ => AppColors.primary,
  };

  IconData _icon(String type) => switch (type) {
    'sucesso' => Icons.check_circle_outline,
    'alerta' => Icons.warning_amber_outlined,
    'critico' => Icons.error_outline,
    _ => Icons.info_outline,
  };

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: store,
    builder: (context, _) => ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                'Notificações',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            if (store.unreadNotifications > 0)
              TextButton(
                onPressed: store.busy ? null : store.markAllNotificationsRead,
                child: const Text('Marcar todas'),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (store.notifications.isEmpty)
          const SectionCard(
            title: 'Tudo certo',
            child: Text('Você ainda não possui notificações.'),
          ),
        ...store.notifications.map((item) => _tile(context, item)),
      ],
    ),
  );

  Widget _tile(BuildContext context, AppNotification item) {
    final color = _color(item.type);
    return Card(
      color: item.isRead ? null : color.withValues(alpha: 0.06),
      child: ListTile(
        contentPadding: const EdgeInsets.all(14),
        onTap: item.isRead || store.busy
            ? null
            : () async {
                final message = await store.markNotificationRead(item);
                if (message != null && context.mounted) {
                  ScaffoldMessenger.of(
                    context,
                  ).showSnackBar(SnackBar(content: Text(message)));
                }
              },
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.12),
          child: Icon(_icon(item.type), color: color),
        ),
        title: Text(
          item.title,
          style: TextStyle(
            fontWeight: item.isRead ? FontWeight.normal : FontWeight.bold,
          ),
        ),
        subtitle: Text('${item.message}\n${formatDateTime(item.createdAt)}'),
        trailing: item.isRead
            ? const Icon(Icons.done, color: Colors.grey)
            : const Icon(Icons.circle, size: 10, color: AppColors.primary),
      ),
    );
  }
}
