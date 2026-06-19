import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:rsrs_app/main.dart';

void main() {
  testWidgets('shows passenger trip tracker home', (tester) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(const RSRSApp());
    await tester.pump();

    expect(find.text('Speed info'), findsOneWidget);
    expect(find.text('Start Tracking'), findsOneWidget);
    expect(
      find.text('We are checking your location and the nearest speed rule.'),
      findsOneWidget,
    );
  });
}
