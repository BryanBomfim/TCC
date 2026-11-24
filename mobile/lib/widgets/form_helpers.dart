import 'package:flutter/material.dart';
import '../models/models.dart';

/// Accepts 1.234,56, 1234,56 and 1234.56. Invalid input never becomes zero.
double? parseMoneyInput(String value) {
  final input = value.trim();
  String normalized;
  if (RegExp(r'^\d+\.\d{1,2}$').hasMatch(input)) {
    normalized = input;
  } else if (RegExp(
    r'^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$',
  ).hasMatch(input)) {
    normalized = input.replaceAll('.', '').replaceAll(',', '.');
  } else {
    return null;
  }
  final number = double.tryParse(normalized);
  return number != null && number.isFinite && number <= 99999999.99
      ? number
      : null;
}

int? parseQuantityInput(String value) {
  final input = value.trim();
  if (!RegExp(r'^\d+$').hasMatch(input)) return null;
  final number = int.tryParse(input);
  return number != null && number <= 2147483647 ? number : null;
}

String? validateRequiredName(String? value, {int max = 150}) {
  final clean = value?.trim() ?? '';
  if (clean.length < 2) return 'Informe pelo menos 2 caracteres.';
  if (clean.length > max) return 'Use no máximo $max caracteres.';
  return null;
}

String? validateEmail(String? value, {bool required = true}) {
  final clean = value?.trim() ?? '';
  if (!required && clean.isEmpty) return null;
  if (clean.length > 150 ||
      !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(clean)) {
    return 'Informe um e-mail válido.';
  }
  return null;
}

String? validateMoney(String? value) => parseMoneyInput(value ?? '') == null
    ? 'Use um valor válido, como 1.234,56 (até 2 casas).'
    : null;

String? validateQuantity(String? value, {bool positive = false}) {
  final number = parseQuantityInput(value ?? '');
  if (number == null) return 'Informe um inteiro de zero a 2147483647.';
  if (positive && number == 0) return 'Informe uma quantidade maior que zero.';
  return null;
}

String shortDate(DateTime date) =>
    '${date.day.toString().padLeft(2, '0')}/'
    '${date.month.toString().padLeft(2, '0')}/${date.year}';

DateTime? inclusiveRangeEnd(DateTimeRange? range) => range == null
    ? null
    : DateTime(
        range.end.year,
        range.end.month,
        range.end.day + 1,
      ).subtract(const Duration(microseconds: 1));

/// Semicolon-separated CSV with quoting and protection against spreadsheet formulas.
String toCsv(List<List<Object?>> rows) => rows
    .map((row) {
      return row
          .map((value) {
            var text = value?.toString() ?? '';
            if (RegExp(r'^\s*[=+@\-]').hasMatch(text) ||
                text.startsWith('\t') ||
                text.startsWith('\r')) {
              text = "'$text";
            }
            return '"${text.replaceAll('"', '""')}"';
          })
          .join(';');
    })
    .join('\r\n');

class MovementFilters extends StatelessWidget {
  const MovementFilters({
    required this.products,
    required this.type,
    required this.productId,
    required this.range,
    required this.onTypeChanged,
    required this.onProductChanged,
    required this.onRangeChanged,
    super.key,
  });
  final List<Product> products;
  final MovementType? type;
  final int? productId;
  final DateTimeRange? range;
  final ValueChanged<MovementType?> onTypeChanged;
  final ValueChanged<int?> onProductChanged;
  final ValueChanged<DateTimeRange?> onRangeChanged;

  @override
  Widget build(BuildContext context) {
    final validId = products.any((item) => item.id == productId)
        ? productId
        : null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SegmentedButton<MovementType?>(
          segments: const [
            ButtonSegment(value: null, label: Text('Todas')),
            ButtonSegment(value: MovementType.entrada, label: Text('Entradas')),
            ButtonSegment(value: MovementType.saida, label: Text('Saídas')),
          ],
          selected: {type},
          onSelectionChanged: (value) => onTypeChanged(value.first),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int>(
          value: validId ?? 0,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Produto'),
          items: [
            const DropdownMenuItem(value: 0, child: Text('Todos os produtos')),
            ...products.map(
              (product) => DropdownMenuItem(
                value: product.id,
                child: Text(product.name, overflow: TextOverflow.ellipsis),
              ),
            ),
          ],
          onChanged: (id) => onProductChanged(id == 0 ? null : id),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            OutlinedButton.icon(
              onPressed: () async {
                final now = DateTime.now();
                final selected = await showDateRangePicker(
                  context: context,
                  firstDate: DateTime(2000),
                  lastDate: DateTime(now.year + 1, 12, 31),
                  initialDateRange: range,
                  helpText: 'Selecionar período',
                  saveText: 'Aplicar',
                );
                if (!context.mounted || selected == null) return;
                onRangeChanged(selected);
              },
              icon: const Icon(Icons.date_range),
              label: Text(
                range == null
                    ? 'Todo o período'
                    : '${shortDate(range!.start)} – ${shortDate(range!.end)}',
              ),
            ),
            if (range != null)
              TextButton(
                onPressed: () => onRangeChanged(null),
                child: const Text('Limpar período'),
              ),
          ],
        ),
      ],
    );
  }
}
