# Acessibilidade implementada

## Recursos para o usuário

- Link "Pular para o conteúdo principal".
- Navegação por teclado com foco visível.
- Painel global acionável pelo botão flutuante ou `Alt + A`.
- Fonte entre 87,5% e 150%.
- Alto contraste.
- Destaque de links.
- Leitura em voz alta com Web Speech.
- Pausar, retomar e parar a narração.
- Preferências salvas no navegador.
- Respeito à preferência de redução de movimento do sistema operacional.

## Semântica

- `lang="pt-BR"` nas páginas compartilhadas.
- Menu lateral com `aria-label`.
- Botão do menu com `aria-expanded` e `aria-controls`.
- Alertas com região viva.
- Cabeçalhos de tabela com `scope="col"`.
- Gráfico do dashboard com descrição e tabela textual equivalente para tecnologia assistiva.

## Observação sobre leitura em voz alta

O recurso utiliza a implementação de `speechSynthesis` do navegador. A disponibilidade e as vozes variam conforme navegador e sistema operacional. Ele complementa, mas não substitui, leitores de tela especializados.
