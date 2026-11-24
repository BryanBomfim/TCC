import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import 'dashboard_screen.dart';
import 'directory_screen.dart';
import 'inventory_screen.dart';
import 'notifications_screen.dart';
import 'products_screen.dart';
import 'reports_screen.dart';
import 'security_screen.dart';
import 'settings_screen.dart';
import 'stock_screen.dart';
import 'users_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({
    required this.store,
    required this.user,
    required this.onLogout,
    super.key,
  });

  final InventoryStore store;
  final AppUser user;
  final Future<String?> Function() onLogout;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int selected = 0;
  final _scaffoldKey = GlobalKey<ScaffoldState>();

  Future<void> _logout() async {
    final message = await widget.onLogout();
    if (!mounted || message == null) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  List<_Destination> get destinations {
    return [
      _Destination(
        'Dashboard',
        Icons.dashboard_outlined,
        DashboardScreen(store: widget.store, user: widget.user),
      ),
      _Destination(
        'Produtos',
        Icons.inventory_2_outlined,
        ProductsScreen(store: widget.store, user: widget.user),
      ),
      _Destination(
        'Categorias',
        Icons.category_outlined,
        DirectoryScreen(
          title: 'Categorias',
          items: widget.store.categories,
          newLabel: 'Nova categoria',
          store: widget.store,
          user: widget.user,
        ),
      ),
      _Destination(
        'Fornecedores',
        Icons.local_shipping_outlined,
        DirectoryScreen(
          title: 'Fornecedores',
          items: widget.store.suppliers,
          newLabel: 'Novo fornecedor',
          store: widget.store,
          user: widget.user,
        ),
      ),
      _Destination(
        'Movimentações',
        Icons.swap_vert_circle_outlined,
        StockScreen(store: widget.store, user: widget.user),
      ),
      _Destination(
        'Inventário físico',
        Icons.fact_check_outlined,
        InventoryScreen(store: widget.store),
      ),
      _Destination(
        widget.store.unreadNotifications == 0
            ? 'Notificações'
            : 'Notificações (${widget.store.unreadNotifications})',
        Icons.notifications_outlined,
        NotificationsScreen(store: widget.store),
      ),
      if (widget.user.isAdmin)
        _Destination(
          'Usuários',
          Icons.people_outline,
          UsersScreen(store: widget.store, user: widget.user),
        ),
      _Destination(
        'Relatórios',
        Icons.assessment_outlined,
        ReportsScreen(store: widget.store, user: widget.user),
      ),
      _Destination(
        'Segurança',
        Icons.security_outlined,
        SecurityScreen(store: widget.store, user: widget.user),
      ),
      if (widget.user.isAdmin)
        _Destination(
          'Configurações',
          Icons.settings_outlined,
          SettingsScreen(store: widget.store, user: widget.user),
        ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final pages = destinations;

    if (selected >= pages.length) {
      selected = 0;
    }

    final userInitial = widget.user.name.trim().isEmpty
        ? '?'
        : widget.user.name.trim()[0].toUpperCase();

    return Scaffold(
      key: _scaffoldKey,
      appBar: AppBar(
        title: Row(
          children: [
            const Icon(Icons.inventory_2_outlined),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                widget.store.settings.systemName,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Atualizar dados',
            onPressed: widget.store.busy ? null : widget.store.refresh,
            icon: const Icon(Icons.refresh),
          ),
          IconButton(
            tooltip: 'Sair',
            onPressed: widget.store.busy ? null : _logout,
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      drawer: Drawer(
        width: 280,
        backgroundColor: AppColors.sidebar,
        child: SafeArea(
          child: Column(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.fromLTRB(28, 24, 24, 20),
                decoration: const BoxDecoration(
                  color: AppColors.navy,
                  border: Border(bottom: BorderSide(color: Color(0xFF263B5A))),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CircleAvatar(
                      radius: 38,
                      backgroundColor: Colors.white,
                      child: Text(
                        userInitial,
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.bold,
                          fontSize: 24,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      widget.user.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      widget.user.email,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xFFDCE6F0)),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      widget.user.isAdmin ? 'Administrador' : 'Funcionário',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Color(0xFFDCE6F0),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: ListView.builder(
                  cacheExtent: 1000,
                  itemCount: pages.length,
                  itemBuilder: (context, index) {
                    final item = pages[index];

                    return ListTile(
                      selected: selected == index,
                      selectedTileColor: AppColors.primary,
                      textColor: const Color(0xFFDCE6F0),
                      iconColor: const Color(0xFFDCE6F0),
                      selectedColor: Colors.white,
                      leading: Icon(item.icon),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 18,
                      ),
                      title: Text(
                        item.label,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      onTap: () {
                        setState(() {
                          selected = index;
                        });

                        Navigator.pop(context);
                      },
                    );
                  },
                ),
              ),
              const Divider(color: Colors.white24, height: 1),
              ListTile(
                textColor: Colors.white,
                iconColor: Colors.white,
                leading: const Icon(Icons.logout),
                title: const Text('Sair'),
                onTap: widget.store.busy
                    ? null
                    : () {
                        Navigator.pop(context);
                        _logout();
                      },
              ),
            ],
          ),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            if (widget.store.busy) const LinearProgressIndicator(),
            if (widget.store.isDemo)
              const MaterialBanner(
                padding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                content: Text('Demonstração • dados não são salvos ao fechar'),
                actions: [SizedBox.shrink()],
              ),
            if (widget.store.lastError != null)
              MaterialBanner(
                content: Text(widget.store.lastError!),
                actions: [
                  TextButton(
                    onPressed: widget.store.busy ? null : widget.store.refresh,
                    child: const Text('Atualizar'),
                  ),
                ],
              ),
            Expanded(
              child: IndexedStack(
                index: selected,
                children: pages.map((destination) => destination.page).toList(),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: selected == 0
            ? 0
            : selected == 1
            ? 1
            : selected == 4
            ? 2
            : 3,
        onDestinationSelected: (index) {
          if (index == 3) {
            _scaffoldKey.currentState?.openDrawer();
          } else {
            setState(() {
              selected = [0, 1, 4][index];
            });
          }
        },
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            label: 'Início',
          ),
          NavigationDestination(
            icon: Icon(Icons.inventory_2_outlined),
            label: 'Produtos',
          ),
          NavigationDestination(icon: Icon(Icons.swap_vert), label: 'Estoque'),
          NavigationDestination(icon: Icon(Icons.menu), label: 'Mais'),
        ],
      ),
    );
  }
}

class _Destination {
  const _Destination(this.label, this.icon, this.page);

  final String label;
  final IconData icon;
  final Widget page;
}
