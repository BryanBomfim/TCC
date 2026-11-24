import 'package:flutter_test/flutter_test.dart';
import 'package:smartcontrol_mobile/widgets/common.dart';
import 'package:smartcontrol_mobile/widgets/form_helpers.dart';

void main() {
  test('valores brasileiros e decimais sem perda silenciosa', () {
    expect(parseMoneyInput('1.234,56'), 1234.56);
    expect(parseMoneyInput('1234,56'), 1234.56);
    expect(parseMoneyInput('1234.56'), 1234.56);
    expect(parseMoneyInput('1.234'), 1234);
    expect(parseMoneyInput('0'), 0);
    for (final input in [
      '',
      'abc',
      '-1',
      'NaN',
      'Infinity',
      '1,234',
      '1.2.3',
      '100000000',
    ]) {
      expect(parseMoneyInput(input), isNull, reason: input);
    }
    expect(money(1234.56), 'R\$ 1.234,56');
  });
  test('quantidades inteiras respeitam limite do MySQL', () {
    expect(parseQuantityInput('24'), 24);
    expect(parseQuantityInput('2147483647'), 2147483647);
    for (final input in ['', '-1', '1.5', '1,5', '2147483648', 'a']) {
      expect(parseQuantityInput(input), isNull);
    }
    expect(validateQuantity('0', positive: true), isNotNull);
  });
  test('CSV escapa aspas, quebras, delimitadores e neutraliza fórmulas', () {
    expect(
      toCsv([
        ['=1+1', 'a"b', 'a;b', 'a\nb', '@sum(1)', '-10'],
      ]),
      '"\'=1+1";"a""b";"a;b";"a\nb";"\'@sum(1)";"\'-10"',
    );
  });
}
