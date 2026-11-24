import 'dart:convert';
import 'package:flutter/material.dart';
import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';
import '../widgets/form_helpers.dart';

class UsersScreen extends StatefulWidget {
  const UsersScreen({required this.store, required this.user, super.key});
  final InventoryStore store;
  final AppUser user;
  @override
  State<UsersScreen> createState() => _UsersScreenState();
}

class _UsersScreenState extends State<UsersScreen> {
  String query = '';
  bool changingStatus = false;

  @override
  Widget build(BuildContext context) {
    if (!widget.user.isAdmin) {
      return const Center(child: Text('Acesso restrito ao administrador.'));
    }
    return AnimatedBuilder(
      animation: widget.store,
      builder: (context, _) {
        final search = query.trim().toLowerCase();
        final users = widget.store.users
            .where(
              (user) =>
                  '${user.name} ${user.email}'.toLowerCase().contains(search),
            )
            .toList();
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Usuários',
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
                hintText: 'Buscar usuário...',
                prefixIcon: Icon(Icons.search),
              ),
            ),
            const SizedBox(height: 14),
            if (users.isEmpty)
              const Padding(
                padding: EdgeInsets.all(24),
                child: Center(child: Text('Nenhum usuário encontrado.')),
              ),
            ...users.map(
              (user) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.all(14),
                    onTap: () => _edit(user),
                    leading: CircleAvatar(
                      backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                      child: const Icon(
                        Icons.person_outline,
                        color: AppColors.primary,
                      ),
                    ),
                    title: Text(
                      user.name,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${user.email} • ${user.roleLabel}'),
                        const SizedBox(height: 6),
                        StatusBadge(
                          label: user.active ? 'Ativo' : 'Inativo',
                          color: user.active ? AppColors.success : Colors.grey,
                        ),
                      ],
                    ),
                    trailing: PopupMenuButton<String>(
                      enabled: !changingStatus && !widget.store.busy,
                      onSelected: (action) {
                        if (action == 'edit') {
                          _edit(user);
                        } else {
                          _toggle(user);
                        }
                      },
                      itemBuilder: (_) => [
                        const PopupMenuItem(
                          value: 'edit',
                          child: Text('Editar'),
                        ),
                        PopupMenuItem(
                          value: 'status',
                          enabled: user.id != widget.user.id,
                          child: Text(user.active ? 'Inativar' : 'Ativar'),
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
  }

  Future<void> _toggle(AppUser user) async {
    if (changingStatus || widget.store.busy || user.id == widget.user.id) {
      return;
    }
    setState(() => changingStatus = true);
    final message = await widget.store.toggleUser(
      user,
      widget.user,
      widget.store.csrfToken,
    );
    if (!mounted) return;
    setState(() => changingStatus = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message ?? 'Situação do usuário atualizada.')),
    );
  }

  Future<void> _edit([AppUser? user]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      isDismissible: false,
      enableDrag: false,
      builder: (_) =>
          _UserForm(store: widget.store, admin: widget.user, target: user),
    );
    if (!mounted || saved != true) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('Usuário salvo com sucesso.')));
  }
}

class _UserForm extends StatefulWidget {
  const _UserForm({required this.store, required this.admin, this.target});
  final InventoryStore store;
  final AppUser admin;
  final AppUser? target;
  @override
  State<_UserForm> createState() => _UserFormState();
}

class _UserFormState extends State<_UserForm> {
  final formKey = GlobalKey<FormState>();
  late final TextEditingController name, email;
  final password = TextEditingController();
  late UserRole role;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    name = TextEditingController(text: widget.target?.name ?? '');
    email = TextEditingController(text: widget.target?.email ?? '');
    role = widget.target?.role ?? UserRole.funcionario;
  }

  @override
  void dispose() {
    name.dispose();
    email.dispose();
    password.dispose();
    super.dispose();
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
                  widget.target == null ? 'Novo usuário' : 'Editar usuário',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: name,
                  enabled: !saving,
                  validator: (value) => validateRequiredName(value, max: 100),
                  maxLength: 100,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Nome *'),
                ),
                TextFormField(
                  controller: email,
                  enabled: !saving,
                  validator: (value) => validateEmail(value),
                  keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(labelText: 'E-mail *'),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: password,
                  enabled: !saving,
                  obscureText: true,
                  autocorrect: false,
                  enableSuggestions: false,
                  decoration: InputDecoration(
                    labelText: widget.target == null ? 'Senha *' : 'Nova senha',
                    helperText: widget.target == null
                        ? 'No mínimo 10 caracteres.'
                        : 'Deixe em branco para manter a senha atual.',
                  ),
                  validator: (value) {
                    if (widget.target != null && (value?.isEmpty ?? true)) {
                      return null;
                    }
                    if (utf8.encode(value ?? '').length > 72) {
                      return 'Senha muito longa (máximo 72 bytes).';
                    }
                    return (value?.length ?? 0) < 10
                        ? 'Use no mínimo 10 caracteres.'
                        : null;
                  },
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<UserRole>(
                  value: role,
                  decoration: const InputDecoration(labelText: 'Perfil'),
                  items: const [
                    DropdownMenuItem(
                      value: UserRole.funcionario,
                      child: Text('Funcionário'),
                    ),
                    DropdownMenuItem(
                      value: UserRole.administrador,
                      child: Text('Administrador'),
                    ),
                  ],
                  onChanged: saving || widget.target?.id == widget.admin.id
                      ? null
                      : (value) => setState(() => role = value ?? role),
                ),
                if (widget.target?.id == widget.admin.id)
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text(
                      'Você não pode alterar o perfil da própria conta.',
                      style: TextStyle(color: Colors.black54),
                    ),
                  ),
                if (!widget.store.isDemo)
                  const Padding(
                    padding: EdgeInsets.only(top: 8),
                    child: Text(
                      'Se o site exige confirmação de e-mail, o novo usuário deve solicitar o link e confirmar pelo site antes de entrar.',
                    ),
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
                FilledButton(
                  onPressed: saving ? null : _save,
                  child: Text(saving ? 'Salvando...' : 'Salvar'),
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
    setState(() {
      saving = true;
      error = null;
    });
    final target = widget.target;
    final message = target == null
        ? await widget.store.createUser(
            name.text.trim(),
            email.text.trim(),
            password.text,
            role,
            widget.admin,
            widget.store.csrfToken,
          )
        : await widget.store.updateUser(
            target,
            name.text.trim(),
            email.text.trim(),
            password.text,
            role,
            widget.admin,
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
