# Estratégia e checklist de testes

## Verificações automatizáveis sem banco

Na raiz do projeto:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
node --check assets/js/script.js
node --check assets/js/accessibility.js
composer validate --no-check-publish
```

Também verifique `git diff --check` antes de commit.

## Instalação e ambiente

- [ ] Copiar `.env.example` para `.env` e preencher apenas localmente.
- [ ] Confirmar que `.env` não aparece no `git status`.
- [ ] Importar instalação limpa em banco de teste.
- [ ] Em uma cópia de banco antigo, executar as migrações em ordem.
- [ ] Confirmar `APP_DEBUG=false` em simulação de produção.

## Autenticação e autorização

- [ ] Login válido.
- [ ] Senha inválida não revela se a conta existe.
- [ ] Cinco falhas recentes acionam bloqueio temporário.
- [ ] Sequência suspeita para conta existente gera alerta interno.
- [ ] Logout encerra a sessão.
- [ ] Sessão expira após o tempo configurado.
- [ ] Funcionário não acessa páginas exclusivas de administrador.
- [ ] O último administrador ativo não pode ser rebaixado.
- [ ] Recuperação de senha aceita apenas token válido/não utilizado/não expirado.

## Produtos, categorias e fornecedores

- [ ] Criar/editar/inativar categoria.
- [ ] Criar/editar/inativar fornecedor.
- [ ] Consulta de CEP preenche endereço quando serviço está disponível.
- [ ] Falha de CEP permite continuar manualmente.
- [ ] Consulta de CNPJ preenche dados quando serviço está disponível.
- [ ] Falha de CNPJ permite continuar manualmente.
- [ ] Criar/editar produto.
- [ ] SKU é gerado e permanece único.
- [ ] Código de barras duplicado é rejeitado.
- [ ] Busca encontra nome, SKU e código de barras.
- [ ] Paginação de produtos preserva filtros.

## Estoque

- [ ] Entrada aumenta saldo e grava saldo anterior/posterior.
- [ ] Entrada aceita fornecedor/valor/referência opcionais.
- [ ] Saída exige motivo.
- [ ] Saída reduz saldo e não permite quantidade acima do disponível.
- [ ] Ajuste administrativo exige justificativa.
- [ ] Ajuste positivo gera movimentação de entrada com origem `ajuste`.
- [ ] Ajuste negativo gera movimentação de saída com origem `ajuste`.
- [ ] Histórico filtra por tipo, origem, produto e período.

### Concorrência

Abra duas sessões/navegadores com o mesmo produto e tente registrar operações quase simultâneas. O resultado final deve permanecer consistente e nenhuma saída pode deixar saldo negativo.

## Inventário

- [ ] Criar inventário gera itens com o saldo de referência.
- [ ] Registrar contagem calcula diferença.
- [ ] Não finalizar enquanto houver item sem contagem.
- [ ] Finalização aplica divergências em transação.
- [ ] Movimentações geradas têm origem `inventario` e referência ao inventário.
- [ ] Histórico do inventário permanece acessível após finalizar.
- [ ] Cancelar inventário aberto não altera estoque.

## Dashboard e relatórios

- [ ] Totais de produtos/categorias/fornecedores correspondem ao banco.
- [ ] Unidades em estoque correspondem à soma dos produtos ativos.
- [ ] Estoque baixo não mistura produtos zerados.
- [ ] Sem estoque mostra apenas produtos ativos com saldo zero.
- [ ] Gráfico de sete dias corresponde à tabela acessível equivalente.
- [ ] Relatório de movimentações respeita todos os filtros.
- [ ] Exportação CSV respeita os mesmos filtros da tela.
- [ ] CSV abre corretamente com UTF-8.
- [ ] Impressão/PDF pelo navegador mantém conteúdo legível.

## API REST

- [ ] Requisição sem sessão/token retorna 401.
- [ ] Token válido retorna 200.
- [ ] Token expirado retorna 401.
- [ ] Token revogado retorna 401.
- [ ] `products` pagina e filtra corretamente.
- [ ] `movements` não expõe senha/token/chave.
- [ ] Métodos diferentes de GET nos endpoints de leitura retornam 405.
- [ ] CEP/CNPJ inválido retorna 422.
- [ ] Indisponibilidade externa retorna mensagem amigável.

## Uploads

- [ ] PDF/JPG/PNG/WEBP válido de até 5 MB é aceito.
- [ ] Arquivo com extensão falsa e MIME não permitido é rejeitado.
- [ ] Arquivo acima de 5 MB é rejeitado.
- [ ] Diretório de uploads não executa PHP.

## Auditoria

- [ ] Login/logout/falha de login aparecem na auditoria.
- [ ] Criação/edição/inativação de cadastros relevantes aparece na auditoria.
- [ ] Entrada/saída/ajuste/inventário aparece na auditoria.
- [ ] Auditoria não contém senha, token bruto ou credencial.

## Responsividade e acessibilidade

- [ ] 320 px, 375 px, tablet e desktop não escondem ações críticas.
- [ ] Tabelas extensas possuem rolagem horizontal no contêiner.
- [ ] Menu mobile abre/fecha e `Esc` fecha o menu.
- [ ] Navegação por `Tab` possui foco visível.
- [ ] Link "Pular para o conteúdo" funciona.
- [ ] `Alt + A` abre acessibilidade.
- [ ] Alto contraste/tamanho de fonte persistem.
- [ ] Mensagens de erro importantes são anunciáveis.
