# Auditoria de conclusão — 24/09/2026

## Diagnóstico da versão recebida

O ZIP já possuía autenticação/verificação/recuperação, usuários, produtos, fornecedores, categorias, anexos, estoque, inventário, alertas, auditoria, dashboard, relatórios CSV/impressão, API v1, ViaCEP/BrasilAPI e documentação acadêmica. A arquitetura combina páginas PHP, helpers e serviços para regras críticas. O esquema possui 16 tabelas InnoDB e migrações anteriores.

Não era necessário reescrever o sistema. As lacunas confirmadas estavam em revogação de sessões, mensagens de exceção, concorrência de inventário, auditoria de usuários, paginação da API/contagem, busca por SKU e orientação de atualização. A interface existente foi preservada; a revisão acrescentou paginação e revisão de saldos ao inventário.

## Correções implementadas

| Severidade | Problema confirmado | Implementação |
|---|---|---|
| Alto | Conta bloqueada ou com perfil alterado mantinha sessão antiga | revalidação no banco e assinatura/versionamento de autenticação |
| Alto | Bloquear/reativar podia restaurar acesso antigo | `auth_version` incrementada em alterações administrativas |
| Alto | Último administrador podia perder perfil | `UserService` com bloqueios ordenados e proteção do último administrador ativo/verificado |
| Alto | `PDOException` entrava no tratamento de `RuntimeException` exibido ao usuário | erros de negócio usam `DomainException`; erros técnicos recebem texto genérico |
| Alto | Trocas sensíveis mantinham tokens auxiliares | revogação de recuperação, verificação e API dentro da transação |
| Alto | Entrada+saída com saldo final igual não eram detectadas pelo inventário | fotografia inclui ID da última movimentação, conferido sob bloqueio |
| Alto | Contagem antiga sobrescrevia edição concorrente | revisão otimista e bloqueio do inventário |
| Alto | Auditoria de estoque podia falhar após saldo já confirmado | auditoria e movimento/saldo na mesma transação das rotas web |
| Médio | Revisão de conflito não tinha ação disponível | Revisar saldos e recontar, com preservação anterior na auditoria |
| Médio | POST incompleto podia limpar itens omitidos | apenas itens enviados são atualizados; paginação de 25 itens |
| Médio | API dependia do bootstrap de sessão | bootstrap sem cookies/redirecionamentos e tratamento JSON de erros |
| Médio | Coleções de API sem limite | paginação das coleções e dos itens de inventário, parâmetros validados |
| Médio | SKU não selecionava produto com código de barras | busca por ambos os identificadores |
| Médio | Quantidades eram truncadas e excedentes chegavam ao SQL | validação de inteiros e limites de INT/DECIMAL |
| Médio | CSV podia aceitar fórmulas precedidas de controle/espaços | neutralização do prefixo completo |
| Médio | SQL de instalação podia apagar tabelas existentes | removidos DROP; atualização específica e aditiva documentada |
| Médio | Configuração privada estava no ZIP | entrega sem `.env`; preservar configuração local na atualização |
| Médio | Senha conhecida de demonstração permitia operação normal | troca obrigatória antes de operar ou usar a API |
| Médio | Cabeçalho de proxy aceito de qualquer remetente | lista explícita de proxies confiáveis; HTTP recusado quando HTTPS é obrigatório |

## Banco de dados

Migração nova: `database/migrations/2026_09_24_conclusao.sql`, uma execução na versão de origem. Adiciona três colunas: `usuarios.auth_version`, `inventarios.revisao`, `inventario_itens.ultimo_movimento_id`. Não apaga registros nem reconstrói tabelas. Itens de inventários legados recebem referência -1 e precisam de revisão explícita antes de finalizar; as contagens não são apagadas pela migração.

A migração não é idempotente. DDL do MySQL/MariaDB não é atomicamente revertido como DML: se houver interrupção, conferir colunas antes de tentar novamente. Não reaplicar a migração numa instalação criada pelo SQL novo.

## Evidências executadas neste ambiente

- Análise sintática pela gramática PHP do Tree-sitter: **83 arquivos da aplicação, zero erros**.
- Referências literais de include/require verificadas: **nenhum destino ausente**.
- `node --check`: **2 arquivos JavaScript da aplicação aprovados**.
- Teste isolado do código JavaScript real com DOM simulado: **3 cenários aprovados** — código de barras, SKU e código inexistente.
- JSON de Composer/lock parseável; conferência de correspondência dos campos novos em esquema e migração.
- SQL novo sem DROP e configuração `.env` excluída da entrega.

**Não executados:** PHP nativo (`php -l` ou suítes PHP), consultas/migrações MySQL/MariaDB, Composer validate/audit, smoke HTTP em Apache, envio SMTP, ViaCEP/BrasilAPI reais, fluxos de navegador e avaliação visual responsiva. Esses serviços/ferramentas não estavam disponíveis aqui.

Os números 29/30/37 e os resultados de Apache/USBWebserver apresentados no documento recebido eram relatos anteriores. Foram substituídos por evidências desta revisão; não são comprovação desta versão. Análise sintática não comprova execução correta nem integridade de banco.

## Limitações e pendências

- **Homologação necessária:** executar `VERIFICAR.cmd`, smoke HTTP e checklist em cópia do banco no XAMPP antes de liberar uso. Não é possível afirmar ausência de regressões de execução sem esses testes.
- **Médio:** rate limiting por identificador/IP usa contagem e inserção, podendo admitir excedentes sob paralelismo. Não substitui proteção no servidor/proxy em exposição pública.
- **Médio:** cadastros, seletores e algumas telas administrativas ainda consultam listas completas; evolução posterior pode ampliar busca paginada. Contagem de inventário e coleções de API foram paginadas.
- **Médio:** integridade contra escrita SQL direta depende de privilégios e operação do banco; não há trilha criptográfica inviolável nem teste de invasão nesta revisão.
- **Baixo:** PDF utiliza impressão do navegador; QR Code não foi adicionado. A API continua somente leitura, conforme a versão existente.
- **Baixo:** styles inline continuam permitidos por compatibilidade; scripts inline permanecem bloqueados no Apache.
- Se mudar o subdiretório `/smartcontrol`, ajustar `APP_URL`, `APP_BASE_URL` e os três `ErrorDocument` no `.htaccess`.
- Usuários novos precisam do SMTP configurado para receber a verificação. Não foi introduzido atalho que dispense confirmação do e-mail.

## Resultado

As correções acima estão implementadas e documentadas. A conclusão da homologação funcional depende dos testes PHP/banco/HTTP e visuais explicitamente listados. Esta entrega não constitui certificação de segurança nem alegação de aprovação integral em produção.
