# Estratégia e checklist de testes

## Testes automatizados

Com Apache e MariaDB ativos:

```bash
composer validate --no-check-publish
composer test
```

A suíte padrão não deixa alterações persistentes nos testes transacionais. Ela verifica ambiente, esquema, integridade de saldos, CSRF, escape HTML, entrada, saída, ajuste e tokens da API.

Para validar a integração externa real em PowerShell:

```powershell
$env:RUN_EXTERNAL_LOOKUP='1'
composer test
Remove-Item Env:RUN_EXTERNAL_LOOKUP
```

Para o smoke test HTTP, a URL precisa apontar para esta cópia do projeto em Apache:

```powershell
$env:HTTP_SMOKE_BASE_URL='http://localhost/smartcontrol'
composer run-script test:http
Remove-Item Env:HTTP_SMOKE_BASE_URL
```

O modo HTTP testa login 200, headers de segurança, redirecionamento de rota protegida, bloqueio de diretório interno, 404, API 401 e dois endpoints autenticados. O token temporário e seus registros de limite são removidos ao final.

## Checklist manual de autenticação

- [ ] Login válido cria sessão com novo ID e abre o dashboard.
- [ ] Senha inválida não revela se o e-mail existe.
- [ ] A quinta falha no período bloqueia novas tentativas.
- [ ] Funcionário recebe 403 ao abrir rota administrativa por URL.
- [ ] Logout por POST encerra a sessão; GET não efetua logout.
- [ ] Sessão expira após o período configurado de inatividade.
- [ ] Recuperação de senha aceita token uma vez e recusa token vencido.
- [ ] Alteração de e-mail exige senha atual e nova verificação.

## Cadastros e integrações

- [ ] Criar e editar categoria; validar limites de nome/descrição. Nomes de categoria não possuem restrição única.
- [ ] Criar fornecedor manualmente sem consultar serviço externo.
- [ ] Consultar CEP válido e revisar os campos preenchidos.
- [ ] Simular API externa indisponível e continuar manualmente.
- [ ] Consultar CNPJ válido; CPF/CNPJ duplicado é recusado.
- [ ] Criar produto e confirmar SKU automático.
- [ ] Código de barras duplicado é bloqueado.
- [ ] Produto é localizado por nome, SKU e código de barras.
- [ ] Anexo permitido é aceito; extensão/MIME indevido é recusado.

## Estoque

- [ ] Entrada registra fornecedor, quantidade, valor, observação e responsável.
- [ ] Leitor USB ou digitação no campo de código seleciona o produto.
- [ ] Saída atualiza saldo e exige motivo.
- [ ] Saída acima do saldo é recusada e não gera movimento parcial.
- [ ] Ajuste aparece apenas para administrador e exige justificativa mínima.
- [ ] Histórico mostra saldo anterior/posterior, origem, data e usuário.
- [ ] Chegada a zero e estoque baixo geram alerta interno.
- [ ] Quantidade acima do limite configurado gera alerta sem spam duplicado.

## Inventário

- [ ] Só um inventário pode ficar aberto.
- [ ] Abertura fotografa todos os produtos ativos e respectivos saldos.
- [ ] Contagem aceita zero e calcula diferença automaticamente.
- [ ] Finalização é recusada se houver item não contado.
- [ ] Movimento concorrente bloqueia a finalização até revisão.
- [ ] Divergências geram ajustes, histórico, auditoria e alerta.
- [ ] Inventário finalizado preserva contagens e responsáveis.
- [ ] Cancelamento não altera saldos.

## Relatórios e API

- [ ] Filtros de movimento combinam período, produto, categoria, fornecedor, usuário, tipo e origem.
- [ ] Estoque atual, baixo, sem estoque, inventários e divergências exibem dados coerentes.
- [ ] Impressão remove navegação e mantém título/tabela legíveis.
- [ ] CSV abre corretamente e texto iniciado por `=`, `+`, `-` ou `@` não executa fórmula.
- [ ] Token da API é mostrado somente ao criar.
- [ ] Token válido consulta endpoints; revogado ou expirado recebe 401.
- [ ] Métodos diferentes de GET recebem 405.
- [ ] Paginação da API respeita o máximo de 100 itens.

## Interface, responsividade e acessibilidade

- [ ] Sidebar, breadcrumbs e item ativo correspondem à tela.
- [ ] Menu mobile abre, fecha por botão e fecha com `Esc`.
- [ ] Tabelas têm rolagem horizontal contida em 320 px de largura.
- [ ] Formulários e ações continuam acessíveis em smartphone e tablet.
- [ ] Estados de sucesso, erro, vazio e carregamento são compreensíveis.
- [ ] Confirmações são exibidas antes de ações destrutivas.
- [ ] `Tab` percorre controles com foco visível.
- [ ] Link "Pular para o conteúdo" funciona.
- [ ] `Alt + A` abre o painel de acessibilidade.
- [ ] Fonte, contraste e destaque de links persistem entre páginas.
- [ ] Gráfico possui alternativa textual e respeita redução de movimento.
- [ ] Leitura em voz alta inicia, pausa e encerra em navegador compatível.

## Evidência de execução

Registre data, ambiente, versão do PHP/MariaDB, comandos, total aprovado e capturas dos fluxos principais. Não inclua senhas, tokens ou `.env` nas evidências do TCC.

## Execução desta revisão e comandos novos

No ambiente desta entrega foram executados apenas análise sintática PHP com Tree-sitter, verificação de sintaxe JavaScript com Node e três cenários isolados do leitor de códigos. PHP nativo, MariaDB, Apache, SMTP e navegador não estavam disponíveis. Os números e limites estão em `FINAL_AUDIT.md`.

No Windows, `VERIFICAR.cmd` usa `C:\xampp\php\php.exe` ou PHP do PATH. Executa lint PHP, `tests/unit.php` e `tests/run.php`, interrompendo ao primeiro erro. Antes, configure `.env`, aplique a migração correta e use uma cópia do banco para homologação. O script não importa SQL nem altera suas configurações.

Comandos individuais:

```bash
php tests/lint.php
php tests/unit.php
php tests/run.php
node tests/barcode.cjs
```

A suíte de banco cria produto e usuário de API temporários dentro de transações revertidas. Não precisa haver produtos cadastrados; é necessário ao menos um usuário ativo para a FK de movimentações. No smoke HTTP, use um administrador verificado que já trocou a senha de demonstração. O token temporário é removido ao final. Contadores AUTO_INCREMENT podem avançar mesmo após rollback.

### Regressões obrigatórias desta entrega

- [ ] Com usuário conectado, bloquear e reativar por outra conta; a sessão anterior continua inválida.
- [ ] Alterar perfil/senha/e-mail de usuário conectado; a próxima requisição exige novo login.
- [ ] Tentar remover o último administrador ativo/verificado; alteração recusada.
- [ ] Recuperar senha; confirmar que a senha anterior, sessões antigas e tokens de API deixaram de funcionar.
- [ ] Entrar com senha de demonstração; somente Minha Conta e logout ficam acessíveis até a troca.
- [ ] Fazer entrada + saída de mesmo tamanho durante inventário; finalização deve recusar mesmo com saldo igual.
- [ ] Revisar esse inventário; somente itens afetados perdem contagem e valores anteriores ficam na auditoria.
- [ ] Abrir contagem em duas sessões; segundo salvamento antigo deve ser recusado.
- [ ] Contar mais de 25 itens em páginas diferentes; salvar uma página não apaga outra.
- [ ] Finalizar inventário; nenhuma contagem/revisão posterior é permitida.
- [ ] Simular falha de INSERT em auditoria em banco de teste: entrada/saída/ajuste não podem confirmar saldo parcialmente.
- [ ] POST de quantidade `1.5`, `12abc`, negativa ou acima de INT: recusar sem movimento.
- [ ] Abrir CSV contendo ` =1+1`, tabulação e `@SUM(...)`: valores tratados como texto.
- [ ] API com cookie de sessão vencido e Bearer válido: responder JSON, sem redirecionamento ao login.
- [ ] API com token revogado, vencido ou conta sem verificação: 401; `page[]=2`: 400; `/products/abc`: 404.
- [ ] Configurar HTTPS obrigatório; HTTP deve ser recusado. Cabeçalho de proxy não confiável não deve liberar a conexão.
- [ ] Conferir menu, foco, tabelas e formulários em 360 px e desktop; não há evidência visual automatizada nesta revisão.
