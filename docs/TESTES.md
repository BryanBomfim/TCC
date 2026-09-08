# Checklist de testes

## Autenticação

- [ ] Login válido.
- [ ] Senha inválida exibe mensagem sem revelar detalhes sensíveis.
- [ ] Bloqueio por tentativas funciona.
- [ ] Logout encerra a sessão.
- [ ] Recuperação de senha não gera erro fatal sem SMTP configurado.

## Cadastros e estoque

- [ ] Criar/editar categorias.
- [ ] Criar/editar fornecedores.
- [ ] Criar/editar produtos.
- [ ] SKU é gerado após cadastro do produto.
- [ ] Código de barras duplicado é bloqueado.
- [ ] Entrada atualiza o estoque.
- [ ] Saída respeita a quantidade disponível.

## Inventário

- [ ] Criar inventário.
- [ ] Salvar contagem.
- [ ] Finalizar inventário.
- [ ] Ajustes geram movimentação e auditoria.
- [ ] Cancelar inventário aberto.

## Interface

- [ ] Menu responsivo abre e fecha.
- [ ] `Esc` fecha o menu.
- [ ] Botões de mostrar senha funcionam.
- [ ] Gráfico do dashboard é desenhado.
- [ ] Tabelas permanecem utilizáveis em tela pequena.

## Acessibilidade

- [ ] `Tab` percorre controles com foco visível.
- [ ] Link "Pular para o conteúdo" funciona.
- [ ] `Alt + A` abre o painel.
- [ ] Aumento/redução de fonte persiste ao trocar de página.
- [ ] Alto contraste persiste ao trocar de página.
- [ ] Destaque de links funciona.
- [ ] Leitura em voz alta inicia em navegador compatível.
- [ ] Pausar/retomar/parar leitura funciona.
