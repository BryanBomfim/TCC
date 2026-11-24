import 'package:flutter/material.dart';

import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';

class SecurityScreen extends StatelessWidget {
  const SecurityScreen({required this.store, required this.user, super.key});
  final InventoryStore store;
  final AppUser user;

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      Text('Minha sessão', style: Theme.of(context).textTheme.headlineSmall),
      const SizedBox(height: 16),
      SectionCard(
        title: user.name,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(user.email),
            const SizedBox(height: 8),
            Text('Perfil: ${user.roleLabel}'),
            const SizedBox(height: 8),
            Text(
              store.isDemo
                  ? 'Você está usando dados fictícios de demonstração.'
                  : 'Você está conectado ao banco do SmartControl. Os dados salvos também ficam disponíveis no site.',
            ),
            const SizedBox(height: 8),
            const Text(
              'Ao fechar o aplicativo, será necessário entrar novamente.',
            ),
            if (store.lastUpdated != null) ...[
              const SizedBox(height: 8),
              Text('Última atualização: ${formatDateTime(store.lastUpdated!)}'),
            ],
          ],
        ),
      ),
      const SizedBox(height: 16),
      SectionCard(
        title: 'Seus acessos',
        child: Text(
          user.isAdmin
              ? 'Catálogo, estoque, relatórios, usuários e configurações.'
              : 'Catálogo, estoque e relatórios gerais.',
        ),
      ),
      const SizedBox(height: 16),
      SectionCard(
        title: 'Atividade nesta sessão',
        child: Column(
          children: [
            const Text(
              'Este resumo fica apenas no aplicativo durante esta sessão.',
            ),
            ...store.auditLogs
                .take(12)
                .map(
                  (log) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.history),
                    title: Text(log.message),
                    subtitle: Text(formatDateTime(log.date)),
                  ),
                ),
          ],
        ),
      ),
      if (user.isAdmin && store.remoteAuditLogs.isNotEmpty) ...[
        const SizedBox(height: 16),
        SectionCard(
          title: 'Auditoria do sistema',
          child: Column(
            children: store.remoteAuditLogs
                .take(30)
                .map(
                  (log) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.policy_outlined),
                    title: Text('${log.action} • ${log.module}'),
                    subtitle: Text(
                      '${log.description}\n${log.user} • ${formatDateTime(log.createdAt)}',
                    ),
                  ),
                )
                .toList(),
          ),
        ),
      ],
    ],
  );
}
