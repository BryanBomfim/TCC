import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/app_colors.dart';
import 'data/inventory_store.dart';
import 'screens/home_shell.dart';
import 'screens/login_screen.dart';

class SmartControlApp extends StatefulWidget {
  const SmartControlApp({this.store, super.key});

  final InventoryStore? store;

  @override
  State<SmartControlApp> createState() => _SmartControlAppState();
}

class _SmartControlAppState extends State<SmartControlApp> {
  late final store = widget.store ?? InventoryStore();

  @override
  void dispose() {
    if (widget.store == null) store.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SmartControl',
      debugShowCheckedModeBanner: false,
      locale: const Locale('pt', 'BR'),
      supportedLocales: const [Locale('pt', 'BR')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      theme: ThemeData(
        useMaterial3: true,
        scaffoldBackgroundColor: AppColors.background,
        colorScheme: ColorScheme.fromSeed(
          seedColor: AppColors.primary,
          primary: AppColors.primary,
          onPrimary: Colors.white,
          error: AppColors.danger,
          surface: AppColors.surface,
          onSurface: AppColors.text,
        ),
        textTheme: ThemeData.light().textTheme.apply(
          bodyColor: AppColors.text,
          displayColor: AppColors.navy,
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          centerTitle: false,
          elevation: 0,
          scrolledUnderElevation: 1,
          surfaceTintColor: Colors.transparent,
        ),
        inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: AppColors.surface,
          labelStyle: const TextStyle(
            color: AppColors.mutedText,
            fontWeight: FontWeight.w600,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(color: AppColors.border),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(color: AppColors.border),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
          ),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 12,
          ),
        ),
        cardTheme: CardThemeData(
          color: AppColors.surface,
          elevation: 0,
          margin: EdgeInsets.zero,
          shadowColor: AppColors.navy.withValues(alpha: 0.04),
          surfaceTintColor: Colors.transparent,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: const BorderSide(color: Color(0xFFE3E9F0)),
          ),
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: AppColors.primary,
            foregroundColor: Colors.white,
            minimumSize: const Size(0, 44),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
            textStyle: const TextStyle(fontWeight: FontWeight.w600),
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
        ),
        outlinedButtonTheme: OutlinedButtonThemeData(
          style: OutlinedButton.styleFrom(
            foregroundColor: AppColors.text,
            minimumSize: const Size(0, 44),
            side: const BorderSide(color: AppColors.border),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
        ),
        navigationBarTheme: NavigationBarThemeData(
          backgroundColor: AppColors.surface,
          indicatorColor: AppColors.primary.withValues(alpha: 0.12),
          labelTextStyle: const WidgetStatePropertyAll(
            TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          ),
          iconTheme: WidgetStateProperty.resolveWith((states) {
            return IconThemeData(
              color: states.contains(WidgetState.selected)
                  ? AppColors.primary
                  : AppColors.mutedText,
            );
          }),
        ),
      ),
      home: AnimatedBuilder(
        animation: store,
        builder: (context, _) {
          if (store.session == null) {
            return LoginScreen(store: store);
          }

          return HomeShell(
            key: ValueKey(store.session!.token),
            store: store,
            user: store.session!.user,
            onLogout: store.logout,
          );
        },
      ),
    );
  }
}
