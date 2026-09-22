# Material técnico para o TCC

## Problema

Pequenas e médias organizações podem perder controle sobre entradas, saídas e diferenças entre estoque físico e estoque registrado quando dependem de anotações dispersas ou planilhas sem rastreabilidade de usuário e origem.

## Solução proposta

O SmartControl centraliza catálogo, fornecedores, saldo, movimentações, inventário físico, relatórios e auditoria em uma aplicação web com autenticação e persistência relacional.

## Objetivo geral

Desenvolver uma plataforma web para controle e acompanhamento de estoque com rastreabilidade, segurança e apoio à tomada de decisão.

## Objetivos específicos

- organizar produtos por categoria e fornecedor;
- registrar entradas, saídas e ajustes com responsável;
- impedir saídas que produzam estoque negativo;
- comparar saldo registrado com contagem física;
- manter histórico de divergências e ajustes de inventário;
- disponibilizar indicadores e relatórios gerenciais;
- aplicar autenticação, autorização, CSRF e prepared statements;
- demonstrar interoperabilidade por API REST e integrações externas auxiliares;
- oferecer interface responsiva e recursos básicos de acessibilidade.

## Requisitos funcionais implementados

- RF01: autenticar usuário ativo e com e-mail verificado.
- RF02: administrar usuários e perfis.
- RF03: cadastrar e editar categorias.
- RF04: cadastrar e editar fornecedores.
- RF05: cadastrar e editar produtos com SKU/código de barras.
- RF06: registrar entrada de estoque.
- RF07: registrar saída respeitando saldo disponível.
- RF08: executar ajuste administrativo com justificativa.
- RF09: consultar histórico de movimentações.
- RF10: iniciar inventário físico.
- RF11: registrar contagem de inventário.
- RF12: calcular divergências.
- RF13: finalizar inventário e aplicar ajustes.
- RF14: consultar dashboard e indicadores.
- RF15: emitir relatórios e CSV.
- RF16: consultar dados pela API REST autenticada.
- RF17: consultar CEP/CNPJ como apoio ao cadastro de fornecedor.
- RF18: gerar notificações internas.
- RF19: consultar auditoria administrativa.
- RF20: anexar documentos/imagens aos produtos.

## Requisitos não funcionais

- RNF01: backend em PHP 8+.
- RNF02: dados em MySQL/MariaDB com InnoDB e `utf8mb4`.
- RNF03: operações críticas de estoque transacionais.
- RNF04: consultas com entrada do usuário preparadas no PDO.
- RNF05: credenciais fora do repositório.
- RNF06: interface utilizável em desktop e telas menores.
- RNF07: foco de teclado, labels e recursos de acessibilidade.
- RNF08: erros de produção sem detalhes internos.
- RNF09: API JSON versionada em `/api/v1`.
- RNF10: integrações externas não podem impedir o uso manual do sistema.

## Regras de negócio centrais

- RN01: não registrar saída maior que o saldo atual.
- RN02: toda nova alteração de saldo deve possuir movimentação correspondente.
- RN03: ajuste administrativo exige justificativa.
- RN04: apenas inventário aberto pode receber contagem/finalização.
- RN05: todas as posições do inventário devem estar contadas antes da finalização.
- RN06: finalização transforma divergências em movimentações rastreáveis.
- RN07: usuário inativo não autentica.
- RN08: funções administrativas exigem perfil de administrador.
- RN09: o último administrador ativo não pode ser rebaixado.
- RN10: token da API revogado ou expirado não autentica.

## Casos de uso centrais para apresentação

### UC - Registrar entrada

**Ator:** usuário autenticado.  
**Pré-condição:** produto ativo.  
**Fluxo:** selecionar produto, quantidade e dados opcionais de fornecedor/valor/referência; sistema bloqueia o produto, calcula o novo saldo, registra movimentação e atualiza o produto na mesma transação.  
**Pós-condição:** saldo aumentado e operação auditada.

### UC - Registrar saída

**Ator:** usuário autenticado.  
**Pré-condição:** produto ativo e saldo suficiente.  
**Fluxo:** selecionar produto, quantidade e motivo; sistema valida saldo sob bloqueio transacional, grava movimentação e reduz estoque.  
**Exceção:** quantidade maior que o saldo é recusada.

### UC - Ajustar estoque

**Ator:** administrador.  
**Fluxo:** informar saldo físico correto e justificativa; sistema calcula diferença, cria movimentação com origem `ajuste` e persiste o novo saldo.

### UC - Finalizar inventário

**Ator:** usuário autorizado.  
**Pré-condição:** inventário aberto e todos os itens contados.  
**Fluxo:** sistema verifica concorrência, compara contagem com o saldo de referência, gera movimentações das divergências, atualiza produtos e finaliza o inventário.

### UC - Consultar relatórios

**Ator:** usuário autenticado.  
**Fluxo:** escolher relatório, aplicar filtros e visualizar tabela; quando disponível, exportar CSV ou usar impressão/PDF do navegador.

## Tecnologias demonstráveis na banca

- PHP orientado a módulos;
- PDO e transações;
- MySQL/MariaDB e relacionamentos;
- JavaScript/Fetch;
- API REST/JSON;
- integração ViaCEP/BrasilAPI;
- segurança de sessão/CSRF/tokens;
- HTML semântico e acessibilidade;
- responsividade em CSS;
- Git e migrações versionadas.

## Limitações e trabalhos futuros

- testes automatizados de integração com banco podem ser adicionados a uma pipeline CI;
- QR Code pode ser adicionado futuramente se houver um caso operacional real, sem substituir o suporte atual a SKU/código de barras/leitor USB;
- escrita pela API pode ser criada futuramente extraindo a regra transacional de movimentação para um serviço único reutilizado pela interface e pela API;
- notificações por e-mail podem evoluir para preferências por usuário;
- autenticação multifator é uma evolução possível para ambientes com maior criticidade.
