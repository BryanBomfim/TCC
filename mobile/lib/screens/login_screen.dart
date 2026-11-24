import 'package:flutter/material.dart';

import '../core/app_colors.dart';
import '../data/inventory_store.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({required this.store, super.key});

  final InventoryStore store;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  late final email = TextEditingController(
    text: widget.store.isDemo ? 'admin@smartcontrol.com' : '',
  );
  late final password = TextEditingController(
    text: widget.store.isDemo ? 'admin123' : '',
  );
  bool obscure = true;
  bool loading = false;

  @override
  void dispose() {
    email.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (loading || widget.store.busy) return;
    if (!email.text.contains('@') || password.text.length < 4) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Informe e-mail e senha válidos.')),
      );

      return;
    }

    setState(() {
      loading = true;
    });

    final session = await widget.store.login(email.text, password.text);

    if (!mounted) {
      return;
    }

    setState(() {
      loading = false;
    });

    if (session == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(widget.store.lastError ?? 'Não foi possível entrar.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.navy,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Column(
                    children: [
                      Container(
                        width: 60,
                        height: 60,
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          Icons.inventory_2_outlined,
                          color: Colors.white,
                          size: 32,
                        ),
                      ),
                      const SizedBox(height: 18),
                      Text(
                        widget.store.settings.systemName,
                        style: Theme.of(context).textTheme.headlineMedium
                            ?.copyWith(
                              fontWeight: FontWeight.w800,
                              color: AppColors.navy,
                            ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        widget.store.settings.companyName,
                        style: const TextStyle(color: Colors.black54),
                      ),
                      const SizedBox(height: 30),
                      if (widget.store.lastError != null) ...[
                        Text(
                          widget.store.lastError!,
                          style: const TextStyle(color: AppColors.danger),
                        ),
                        const SizedBox(height: 12),
                      ],
                      TextField(
                        controller: email,
                        keyboardType: TextInputType.emailAddress,
                        autocorrect: false,
                        autofillHints: const [AutofillHints.email],
                        decoration: const InputDecoration(
                          labelText: 'E-mail',
                          prefixIcon: Icon(Icons.mail_outline),
                        ),
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: password,
                        obscureText: obscure,
                        autocorrect: false,
                        enableSuggestions: false,
                        autofillHints: const [AutofillHints.password],
                        onSubmitted: (_) => submit(),
                        decoration: InputDecoration(
                          labelText: 'Senha',
                          prefixIcon: const Icon(Icons.lock_outline),
                          suffixIcon: IconButton(
                            onPressed: () {
                              setState(() {
                                obscure = !obscure;
                              });
                            },
                            icon: Icon(
                              obscure
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: loading ? null : submit,
                          child: loading
                              ? const SizedBox.square(
                                  dimension: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Entrar'),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        widget.store.isDemo
                            ? 'Demonstração — dados fictícios, apagados ao fechar o app.\n'
                                  'admin@smartcontrol.com / admin123\n'
                                  'marina@smartcontrol.com / func123'
                            : 'Entre com o e-mail e a senha do SmartControl web.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: Colors.black45,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
