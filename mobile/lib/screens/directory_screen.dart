import 'package:flutter/material.dart';
import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';
import '../widgets/form_helpers.dart';

class DirectoryScreen extends StatefulWidget {
  const DirectoryScreen({
    required this.title,
    required this.items,
    required this.newLabel,
    required this.store,
    required this.user,
    super.key,
  });
  final String title, newLabel;
  final List<DirectoryItem> items;
  final InventoryStore store;
  final AppUser user;
  @override
  State<DirectoryScreen> createState() => _DirectoryScreenState();
}

class _DirectoryScreenState extends State<DirectoryScreen> {
  String query = '';
  bool changingStatus = false;
  bool get isSupplier => widget.title == 'Fornecedores';

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: widget.store,
    builder: (context, _) {
      final search = query.trim().toLowerCase();
      final items = widget.items
          .where(
            (item) =>
                '${item.name} ${item.subtitle} ${item.document} ${item.email}'
                    .toLowerCase()
                    .contains(search),
          )
          .toList();
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  widget.title,
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
            decoration: InputDecoration(
              hintText: 'Buscar ${widget.title.toLowerCase()}...',
              prefixIcon: const Icon(Icons.search),
            ),
          ),
          const SizedBox(height: 14),
          if (items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: Text('Nenhum cadastro encontrado.')),
            ),
          ...items.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                child: ListTile(
                  contentPadding: const EdgeInsets.all(14),
                  onTap: () => _edit(item),
                  leading: CircleAvatar(
                    backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                    child: Icon(
                      isSupplier
                          ? Icons.local_shipping_outlined
                          : Icons.category_outlined,
                      color: AppColors.primary,
                    ),
                  ),
                  title: Text(
                    item.name,
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (item.subtitle.isNotEmpty) Text(item.subtitle),
                      if (isSupplier && item.document.isNotEmpty)
                        Text(item.document),
                      if (isSupplier && item.phone.isNotEmpty) Text(item.phone),
                      if (isSupplier && item.email.isNotEmpty) Text(item.email),
                      const SizedBox(height: 6),
                      StatusBadge(
                        label: item.active ? 'Ativo' : 'Inativo',
                        color: item.active ? AppColors.success : Colors.grey,
                      ),
                    ],
                  ),
                  trailing: PopupMenuButton<String>(
                    enabled: !changingStatus && !widget.store.busy,
                    onSelected: (action) {
                      if (action == 'edit') {
                        _edit(item);
                      } else {
                        _toggle(item);
                      }
                    },
                    itemBuilder: (_) => [
                      const PopupMenuItem(value: 'edit', child: Text('Editar')),
                      PopupMenuItem(
                        value: 'status',
                        child: Text(item.active ? 'Inativar' : 'Ativar'),
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

  Future<void> _toggle(DirectoryItem item) async {
    if (changingStatus || widget.store.busy) return;
    setState(() => changingStatus = true);
    final message = await widget.store.toggleDirectoryItem(
      item,
      widget.user,
      widget.store.csrfToken,
    );
    if (!mounted) return;
    setState(() => changingStatus = false);
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message ?? 'Situação atualizada.')));
  }

  Future<void> _edit([DirectoryItem? item]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      isDismissible: false,
      enableDrag: false,
      builder: (_) => _DirectoryForm(
        store: widget.store,
        user: widget.user,
        items: widget.items,
        item: item,
        supplier: isSupplier,
        title: item == null ? widget.newLabel : 'Editar cadastro',
      ),
    );
    if (!mounted || saved != true) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Cadastro salvo com sucesso.')),
    );
  }
}

class _DirectoryForm extends StatefulWidget {
  const _DirectoryForm({
    required this.store,
    required this.user,
    required this.items,
    required this.supplier,
    required this.title,
    this.item,
  });
  final InventoryStore store;
  final AppUser user;
  final List<DirectoryItem> items;
  final DirectoryItem? item;
  final bool supplier;
  final String title;
  @override
  State<_DirectoryForm> createState() => _DirectoryFormState();
}

class _DirectoryFormState extends State<_DirectoryForm> {
  final formKey = GlobalKey<FormState>();
  late final TextEditingController name,
      description,
      document,
      phone,
      email,
      address,
      legalName,
      zipCode,
      street,
      number,
      complement,
      district,
      city,
      state;
  bool saving = false;
  String? error;

  @override
  void initState() {
    super.initState();
    name = TextEditingController(text: widget.item?.name ?? '');
    description = TextEditingController(text: widget.item?.subtitle ?? '');
    document = TextEditingController(text: widget.item?.document ?? '');
    phone = TextEditingController(text: widget.item?.phone ?? '');
    email = TextEditingController(text: widget.item?.email ?? '');
    address = TextEditingController(text: widget.item?.address ?? '');
    legalName = TextEditingController(text: widget.item?.legalName ?? '');
    zipCode = TextEditingController(text: widget.item?.zipCode ?? '');
    street = TextEditingController(text: widget.item?.street ?? '');
    number = TextEditingController(text: widget.item?.number ?? '');
    complement = TextEditingController(text: widget.item?.complement ?? '');
    district = TextEditingController(text: widget.item?.district ?? '');
    city = TextEditingController(text: widget.item?.city ?? '');
    state = TextEditingController(text: widget.item?.state ?? '');
  }

  @override
  void dispose() {
    for (final controller in [
      name,
      description,
      document,
      phone,
      email,
      address,
      legalName,
      zipCode,
      street,
      number,
      complement,
      district,
      city,
      state,
    ]) {
      controller.dispose();
    }
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
                  widget.title,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: name,
                  enabled: !saving,
                  maxLength: widget.supplier ? 150 : 100,
                  validator: (value) => validateRequiredName(
                    value,
                    max: widget.supplier ? 150 : 100,
                  ),
                  decoration: const InputDecoration(labelText: 'Nome *'),
                ),
                if (!widget.supplier)
                  TextFormField(
                    controller: description,
                    maxLength: 255,
                    enabled: !saving,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Descrição'),
                  ),
                if (widget.supplier) ...[
                  TextFormField(
                    controller: legalName,
                    enabled: !saving,
                    maxLength: 180,
                    decoration: const InputDecoration(
                      labelText: 'Razão social',
                    ),
                  ),
                  TextFormField(
                    controller: document,
                    enabled: !saving,
                    maxLength: 20,
                    decoration: const InputDecoration(labelText: 'CPF / CNPJ'),
                    validator: (value) {
                      final text = value?.trim() ?? '';
                      if (text.isEmpty) return null;
                      final digits = text.replaceAll(RegExp(r'[.\-/\s]'), '');
                      return RegExp(r'^(\d{11}|\d{14})$').hasMatch(digits)
                          ? null
                          : 'Informe 11 dígitos (CPF) ou 14 (CNPJ).';
                    },
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: phone,
                    enabled: !saving,
                    maxLength: 20,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Telefone'),
                    validator: (value) {
                      final text = value?.trim() ?? '';
                      if (text.isEmpty) return null;
                      final digits = text.replaceAll(RegExp(r'[+()\-\s]'), '');
                      return RegExp(r'^\d{8,15}$').hasMatch(digits)
                          ? null
                          : 'Informe um telefone válido com DDD.';
                    },
                  ),
                  TextFormField(
                    controller: email,
                    enabled: !saving,
                    keyboardType: TextInputType.emailAddress,
                    validator: (value) => validateEmail(value, required: false),
                    autocorrect: false,
                    decoration: const InputDecoration(labelText: 'E-mail'),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: zipCode,
                    maxLength: 9,
                    enabled: !saving,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'CEP'),
                    validator: (value) {
                      final text = value?.replaceAll(RegExp(r'\D'), '') ?? '';
                      return text.isEmpty || text.length == 8
                          ? null
                          : 'Informe um CEP com 8 números.';
                    },
                  ),
                  TextFormField(
                    controller: street,
                    maxLength: 180,
                    enabled: !saving,
                    decoration: const InputDecoration(labelText: 'Logradouro'),
                  ),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: number,
                          maxLength: 20,
                          enabled: !saving,
                          decoration: const InputDecoration(
                            labelText: 'Número',
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: complement,
                          maxLength: 100,
                          enabled: !saving,
                          decoration: const InputDecoration(
                            labelText: 'Complemento',
                          ),
                        ),
                      ),
                    ],
                  ),
                  TextFormField(
                    controller: district,
                    maxLength: 100,
                    enabled: !saving,
                    decoration: const InputDecoration(labelText: 'Bairro'),
                  ),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: city,
                          maxLength: 100,
                          enabled: !saving,
                          decoration: const InputDecoration(
                            labelText: 'Cidade',
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      SizedBox(
                        width: 90,
                        child: TextFormField(
                          controller: state,
                          maxLength: 2,
                          enabled: !saving,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(labelText: 'UF'),
                        ),
                      ),
                    ],
                  ),
                  TextFormField(
                    controller: address,
                    maxLength: 255,
                    enabled: !saving,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Endereço completo/observação',
                    ),
                  ),
                ],
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
    final previous = widget.item;
    final message = previous == null
        ? await widget.store.addDirectoryItem(
            widget.items,
            name.text.trim(),
            description.text.trim(),
            widget.user,
            widget.store.csrfToken,
            document: document.text.trim(),
            phone: phone.text.trim(),
            email: email.text.trim(),
            address: address.text.trim(),
            legalName: legalName.text.trim(),
            zipCode: zipCode.text.replaceAll(RegExp(r'\D'), ''),
            street: street.text.trim(),
            number: number.text.trim(),
            complement: complement.text.trim(),
            district: district.text.trim(),
            city: city.text.trim(),
            state: state.text.trim().toUpperCase(),
          )
        : await widget.store.updateDirectoryItem(
            widget.items,
            DirectoryItem(
              id: previous.id,
              name: name.text.trim(),
              subtitle: description.text.trim(),
              active: previous.active,
              createdAt: previous.createdAt,
              document: document.text.trim(),
              phone: phone.text.trim(),
              email: email.text.trim(),
              address: address.text.trim(),
              legalName: legalName.text.trim(),
              zipCode: zipCode.text.replaceAll(RegExp(r'\D'), ''),
              street: street.text.trim(),
              number: number.text.trim(),
              complement: complement.text.trim(),
              district: district.text.trim(),
              city: city.text.trim(),
              state: state.text.trim().toUpperCase(),
            ),
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
