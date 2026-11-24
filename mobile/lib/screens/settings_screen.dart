import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/form_helpers.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({required this.store, required this.user, super.key});
  final InventoryStore store;
  final AppUser user;

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final formKey = GlobalKey<FormState>();
  late final TextEditingController system;
  late final TextEditingController company;
  late final TextEditingController minimum;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    final settings = widget.store.settings;
    system = TextEditingController(text: settings.systemName);
    company = TextEditingController(text: settings.companyName);
    minimum = TextEditingController(text: '${settings.defaultMinimumStock}');
  }

  @override
  void dispose() {
    system.dispose();
    company.dispose();
    minimum.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: widget.store,
    builder: (context, _) {
      if (!(widget.store.session?.user ?? widget.user).isAdmin) {
        return const Center(child: Text('Acesso restrito ao administrador.'));
      }
      final locked = saving || widget.store.busy;
      return PopScope(
        canPop: !saving,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Configurações',
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            const Text(
              'Defina as informações gerais e os valores padrão do sistema.',
              style: TextStyle(color: Colors.black54),
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Form(
                  key: formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        controller: system,
                        enabled: !locked,
                        maxLength: 100,
                        validator: validateRequiredName,
                        decoration: const InputDecoration(
                          labelText: 'Nome do sistema *',
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: company,
                        enabled: !locked,
                        maxLength: 150,
                        validator: (value) {
                          final clean = value?.trim() ?? '';
                          return clean.isEmpty || clean.length > 150
                              ? 'Informe o nome da empresa (até 150 caracteres).'
                              : null;
                        },
                        decoration: const InputDecoration(
                          labelText: 'Nome da empresa *',
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: minimum,
                        enabled: !locked,
                        keyboardType: TextInputType.number,
                        validator: (value) => validateQuantity(value),
                        decoration: const InputDecoration(
                          labelText: 'Estoque mínimo padrão *',
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'O padrão é sugerido para novos produtos. Produtos já cadastrados mantêm o próprio mínimo.',
                        style: TextStyle(color: Colors.black54),
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
                      ElevatedButton.icon(
                        onPressed: locked ? null : _save,
                        icon: const Icon(Icons.save_outlined),
                        label: Text(
                          saving ? 'Salvando...' : 'Salvar configurações',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
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
    FocusScope.of(context).unfocus();
    setState(() {
      saving = true;
      error = null;
    });
    final message = await widget.store.saveSettings(
      system.text.trim(),
      company.text.trim(),
      parseQuantityInput(minimum.text)!,
      widget.user,
      widget.store.csrfToken,
    );
    if (!mounted) return;
    setState(() {
      saving = false;
      error = message;
    });
    if (message == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Configurações salvas com sucesso.')),
      );
    }
  }
}
