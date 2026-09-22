# Auditoria técnica final

Data de revisão desta versão: 22/09/2026.

## Diagnóstico inicial

A base recebida já possuía CRUDs, autenticação, inventário, auditoria, notificações, recuperação/verificação de e-mail, anexos, acessibilidade e código de barras. O trabalho interrompido ainda apresentava configuração local hardcoded, rastreabilidade parcial de estoque, ausência ou incompletude de API REST/integradores, relatórios incompletos e pequenos erros de marcação HTML.

A evolução foi feita de forma incremental, preservando os módulos existentes e evitando migração para framework ou reescrita total.

## Alterações principais desta evolução

- configuração por `.env` com `.env.example` seguro;
- tratamento de erro diferente para desenvolvimento e produção;
- cookies, timeout de sessão e headers de segurança revisados;
- login, logout, falhas e bloqueios de autenticação registrados na auditoria;
- alerta interno de tentativas de acesso suspeitas;
- movimentações enriquecidas com saldo anterior/posterior, origem, motivo, fornecedor, valor e referência;
- entrada e saída protegidas por transação e `FOR UPDATE`;
- ajuste administrativo transacional com justificativa obrigatória;
- estoque inicial do produto registrado como movimentação rastreável;
- inventário finalizado com origem, referência e saldos das movimentações de ajuste;
- API REST v1 autenticada, paginada e com tokens persistidos somente por hash;
- gerenciamento administrativo de tokens com expiração e revogação;
- integração opcional de CEP com ViaCEP e fallback BrasilAPI;
- integração opcional de CNPJ via BrasilAPI;
- dashboard com indicadores adicionais;
- relatórios de estoque zerado e inventário;
- exportação CSV filtrada com mitigação de injeção de fórmulas;
- paginação na listagem principal de produtos;
- auditoria ampliada para cadastros e configurações;
- correções de HTML e remoção de emojis usados como elementos da interface;
- documentação técnica e material acadêmico atualizados;
- consulta SQL não destrutiva para verificação de integridade após migrações.

## Verificações executadas neste ambiente

- `php -l` em **79 arquivos PHP**: aprovado, sem erros de sintaxe;
- `node --check assets/js/script.js`: aprovado;
- `node --check assets/js/accessibility.js`: aprovado;
- busca estática por credenciais/chaves privadas comuns: nenhuma credencial real identificada;
- busca por `var_dump`, `print_r` e debug residual em PHP: nenhum ponto identificado;
- busca por saída direta de `$_GET`, `$_POST` ou `$_REQUEST`: nenhum ponto identificado;
- revisão dos pontos que inserem `movimentacoes`: cadastro inicial, entrada, saída, ajuste e inventário usam o modelo novo de rastreabilidade;
- revisão de links internos estáticos e marcações duplicadas encontradas durante a auditoria: problemas identificados foram corrigidos.

## Verificações não executadas aqui

Este ambiente não possui servidor MySQL/MariaDB nem Composer disponíveis. Por isso, não foi possível executar uma instalação HTTP completa com banco real, `composer install`, SMTP, chamadas externas reais ou testes de navegador. Essas verificações devem ser concluídas no XAMPP antes da apresentação.

## Classificação de achados após as correções

### CRÍTICO

Nenhum achado crítico conhecido permaneceu na revisão estática.

### ALTO

Nenhum achado alto conhecido permaneceu na revisão estática.

### MÉDIO

- É obrigatório validar as migrações em uma cópia do banco real antes de aplicá-las à base principal.
- A criação de usuário com verificação de e-mail depende de SMTP operacional para concluir o fluxo normal de ativação.
- Não existe suíte automatizada de integração com banco/HTTP; o ciclo transacional final depende do XAMPP/MySQL real.
- O projeto não possui MFA, adequado como evolução futura se houver requisito de segurança maior.

### BAIXO

- Alguns módulos legados ainda concentram HTML e lógica no mesmo arquivo. A estrutura foi preservada para reduzir risco de regressão e manter o projeto compreensível para o TCC.
- A API de domínio é somente leitura. Escrita deve ser adicionada somente depois de centralizar as regras de estoque em um serviço compartilhado, evitando caminhos paralelos para alteração de saldo.
- QR Code não foi adicionado porque SKU e código de barras já atendem a identificação operacional sem introduzir dependência desnecessária.

## Teste obrigatório no XAMPP

1. Faça backup do banco atual.
2. Aplique as migrações em ordem.
3. Execute `database/check_integrity.sql` e revise qualquer contagem diferente de zero.
4. Teste login, logout, recuperação de senha e permissões dos dois perfis.
5. Teste o ciclo `produto → entrada → saída → ajuste → inventário → histórico → relatório`.
6. Confira saldo anterior/posterior e origem nas movimentações.
7. Teste criação, uso, expiração e revogação de token da API.
8. Teste consulta de CEP/CNPJ com internet e também o fallback manual quando a integração estiver indisponível.
9. Teste CSV em Excel/LibreOffice e impressão/PDF pelo navegador.
10. Revise responsividade e acessibilidade em desktop e celular.

Consulte também `docs/TESTES.md` para o checklist detalhado.
