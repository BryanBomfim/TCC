import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';
import '../widgets/form_helpers.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({required this.store, required this.user, super.key});
  final InventoryStore store;
  final AppUser user;
  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  String selected = 'Produtos';
  MovementType? movementFilter;
  int? productId;
  DateTimeRange? range;

  List<List<Object?>> _rows() {
    final store = widget.store;
    switch (selected) {
      case 'Produtos':
      case 'Estoque baixo':
        return [
          [
            'Produto',
            'Categoria',
            'Fornecedor',
            'Custo',
            'Venda',
            'Estoque',
            'Mínimo',
            'Unidade',
            'Situação',
            'Valor a custo',
          ],
          ...store.products
              .where(
                (p) =>
                    selected != 'Estoque baixo' || (p.active && p.isLowStock),
              )
              .map(
                (p) => [
                  p.name,
                  p.category,
                  p.supplier,
                  _decimal(p.costPrice),
                  _decimal(p.salePrice),
                  p.stock,
                  p.minimumStock,
                  p.unit,
                  p.active ? 'Ativo' : 'Inativo',
                  _decimal(p.stockValue),
                ],
              ),
        ];
      case 'Movimentações':
        return [
          ['Data', 'Produto', 'Tipo', 'Quantidade', 'Usuário', 'Observação'],
          ...store
              .movementHistory(
                type: movementFilter,
                productId: productId,
                start: range?.start,
                end: range?.end,
              )
              .map(
                (m) => [
                  formatDateTime(m.date),
                  m.product,
                  m.type == MovementType.entrada ? 'Entrada' : 'Saída',
                  m.quantity,
                  m.user,
                  m.note,
                ],
              ),
        ];
      case 'Categorias':
        return [
          ['Categoria', 'Descrição', 'Situação'],
          ...store.categories.map(
            (c) => [c.name, c.subtitle, c.active ? 'Ativo' : 'Inativo'],
          ),
        ];
      case 'Fornecedores':
        return [
          [
            'Fornecedor',
            'CPF/CNPJ',
            'Telefone',
            'E-mail',
            'Endereço',
            'Situação',
          ],
          ...store.suppliers.map(
            (s) => [
              s.name,
              s.document,
              s.phone,
              s.email,
              s.address,
              s.active ? 'Ativo' : 'Inativo',
            ],
          ),
        ];
      case 'Usuários':
        if (!widget.user.isAdmin) {
          return [
            ['Acesso restrito'],
          ];
        }
        return [
          ['Nome', 'E-mail', 'Perfil', 'Situação'],
          ...store.users.map(
            (u) => [
              u.name,
              u.email,
              u.roleLabel,
              u.active ? 'Ativo' : 'Inativo',
            ],
          ),
        ];
      default:
        return [
          ['Registro'],
        ];
    }
  }

  String _decimal(double value) =>
      value.toStringAsFixed(2).replaceAll('.', ',');

  Future<void> _copy() async {
    try {
      await Clipboard.setData(ClipboardData(text: toCsv(_rows())));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('CSV copiado. Cole em uma planilha ou arquivo .csv.'),
        ),
      );
    } on PlatformException {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Não foi possível copiar neste dispositivo.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: widget.store,
    builder: (context, _) {
      final reportTypes = widget.store.reportsFor(widget.user);
      if (!reportTypes.any((r) => r.title == selected)) selected = 'Produtos';
      final rows = _rows();
      final headers = rows.first;
      final body = rows.skip(1).toList();
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Relatórios',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 6),
          const Text(
            'Consulte os registros e copie o relatório completo, com os filtros aplicados.',
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            value: selected,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Relatório'),
            items: reportTypes
                .map(
                  (r) => DropdownMenuItem(value: r.title, child: Text(r.title)),
                )
                .toList(),
            onChanged: (value) => setState(() => selected = value ?? selected),
          ),
          const SizedBox(height: 16),
          if (selected == 'Movimentações') ...[
            MovementFilters(
              products: widget.store.products,
              type: movementFilter,
              productId: productId,
              range: range,
              onTypeChanged: (v) => setState(() => movementFilter = v),
              onProductChanged: (v) => setState(() => productId = v),
              onRangeChanged: (v) => setState(() => range = v),
            ),
            const SizedBox(height: 16),
          ],
          Wrap(
            spacing: 12,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                '${body.length} registro(s)',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              OutlinedButton.icon(
                onPressed: _copy,
                icon: const Icon(Icons.copy),
                label: const Text('Copiar CSV'),
              ),
            ],
          ),
          if (selected == 'Produtos')
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'Valor em estoque dos produtos ativos: ${money(widget.store.inventoryValue)}',
              ),
            ),
          if (body.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text('Nenhum registro encontrado para este relatório.'),
            ),
          ...body.map(
            (row) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: List.generate(
                      headers.length,
                      (i) => Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          '${headers[i]}: ${row[i] ?? ''}',
                          style: i == 0
                              ? const TextStyle(fontWeight: FontWeight.bold)
                              : null,
                        ),
                      ),
                    ),
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
