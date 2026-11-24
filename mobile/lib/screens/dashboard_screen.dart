import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';
import '../models/models.dart';
import '../widgets/common.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({required this.store, required this.user, super.key});

  final InventoryStore store;
  final AppUser user;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: store,
      builder: (context, _) {
        return RefreshIndicator(
          onRefresh: store.refresh,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 22, 20, 28),
            children: [
              Text(
                'Dashboard',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                'Bem-vindo(a), ${user.name}! Aqui está um resumo do sistema.',
                style: const TextStyle(color: Colors.black54),
              ),
              const SizedBox(height: 18),
              GridView(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: SliverGridDelegateWithMaxCrossAxisExtent(
                  maxCrossAxisExtent: 240,
                  mainAxisExtent:
                      120 *
                      MediaQuery.textScalerOf(context).scale(1).clamp(1, 1.8),
                  mainAxisSpacing: 14,
                  crossAxisSpacing: 14,
                ),
                children: [
                  _Metric(
                    'Produtos',
                    '${store.products.where((p) => p.active).length}',
                    Icons.inventory_2_outlined,
                  ),
                  _Metric(
                    'Categorias',
                    '${store.categories.where((c) => c.active).length}',
                    Icons.category_outlined,
                  ),
                  _Metric(
                    'Fornecedores',
                    '${store.suppliers.where((s) => s.active).length}',
                    Icons.local_shipping_outlined,
                  ),
                  _Metric(
                    'Estoque baixo',
                    '${store.lowStockCount}',
                    Icons.warning_amber_rounded,
                    danger: true,
                  ),
                  _Metric(
                    'Entradas hoje',
                    '${store.entriesToday}',
                    Icons.south_west,
                    color: AppColors.success,
                  ),
                  _Metric(
                    'Saídas hoje',
                    '${store.exitsToday}',
                    Icons.north_east,
                    color: AppColors.danger,
                  ),
                ],
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Movimentações dos últimos 7 dias',
                child: SizedBox(
                  height: 190,
                  width: double.infinity,
                  child: CustomPaint(
                    painter: _BarChartPainter(
                      entries: store.dailyTotals(MovementType.entrada),
                      exits: store.dailyTotals(MovementType.saida),
                      today: DateTime.now(),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Produtos com estoque baixo',
                child: Column(
                  children: store.products
                      .where((product) {
                        return product.active && product.isLowStock;
                      })
                      .map((product) {
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const Icon(
                            Icons.warning_amber_rounded,
                            color: AppColors.danger,
                          ),
                          title: Text(product.name),
                          subtitle: Text(
                            'Mínimo: ${product.minimumStock} ${product.unit}',
                          ),
                          trailing: Text(
                            '${product.stock} ${product.unit}',
                            style: const TextStyle(
                              color: AppColors.danger,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        );
                      })
                      .toList(),
                ),
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Últimas movimentações',
                child: Column(
                  children: store.movements.take(5).map((movement) {
                    return MovementTile(movement: movement);
                  }).toList(),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric(
    this.label,
    this.value,
    this.icon, {
    this.danger = false,
    this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final bool danger;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Container(
        decoration: danger
            ? const BoxDecoration(
                border: Border(
                  left: BorderSide(color: AppColors.danger, width: 4),
                ),
              )
            : null,
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Text(
                    label,
                    maxLines: 1,
                    style: const TextStyle(color: Colors.black54, fontSize: 12),
                  ),
                ),
                Icon(
                  icon,
                  size: 20,
                  color:
                      color ?? (danger ? AppColors.danger : AppColors.primary),
                ),
              ],
            ),
            Text(
              value,
              style: Theme.of(
                context,
              ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
            ),
          ],
        ),
      ),
    );
  }
}

class _BarChartPainter extends CustomPainter {
  _BarChartPainter({
    required this.entries,
    required this.exits,
    required this.today,
  });
  final List<int> entries;
  final List<int> exits;
  final DateTime today;
  @override
  void paint(Canvas canvas, Size size) {
    final maximum = [...entries, ...exits].fold<int>(1, math.max);
    final labels = List.generate(7, (i) {
      final day = DateTime(today.year, today.month, today.day - 6 + i);
      return '${day.day}/${day.month}';
    });

    final grid = Paint()
      ..color = const Color(0xFFE5E7EB)
      ..strokeWidth = 1;

    final textPainter = TextPainter(textDirection: TextDirection.ltr);

    const chartTop = 24.0;
    final chartBottom = size.height - 24;

    for (var i = 0; i <= 3; i++) {
      final y = chartTop + (chartBottom - chartTop) * i / 3;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }

    final groupWidth = size.width / 7;

    for (var i = 0; i < 7; i++) {
      final center = groupWidth * (i + .5);
      final entryHeight = (chartBottom - chartTop) * entries[i] / maximum;
      final exitHeight = (chartBottom - chartTop) * exits[i] / maximum;

      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(center - 11, chartBottom - entryHeight, 9, entryHeight),
          const Radius.circular(2),
        ),
        Paint()..color = AppColors.success,
      );

      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(center + 2, chartBottom - exitHeight, 9, exitHeight),
          const Radius.circular(2),
        ),
        Paint()..color = AppColors.danger,
      );

      textPainter.text = TextSpan(
        text: labels[i],
        style: const TextStyle(fontSize: 10, color: Colors.black54),
      );

      textPainter.layout();

      textPainter.paint(
        canvas,
        Offset(center - textPainter.width / 2, size.height - 17),
      );
    }

    _legend(canvas, 'Entradas', AppColors.success, 0);

    _legend(canvas, 'Saídas', AppColors.danger, math.min(90, size.width / 2));
  }

  void _legend(Canvas canvas, String label, Color color, double x) {
    canvas.drawRect(Rect.fromLTWH(x, 2, 10, 10), Paint()..color = color);

    final painter = TextPainter(
      text: TextSpan(
        text: label,
        style: const TextStyle(color: Colors.black54, fontSize: 10),
      ),
      textDirection: TextDirection.ltr,
    )..layout();

    painter.paint(canvas, Offset(x + 14, 1));
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) {
    return true;
  }
}
