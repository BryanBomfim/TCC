# Banco de dados

## SGBD

O projeto foi estruturado para MySQL 5.7+ e MariaDB 10.4+, usando InnoDB e `utf8mb4`.

`database/smartcontrol.sql` é a instalação limpa. Alterações incrementais ficam em `database/migrations/`.

## Entidades principais

| Tabela | Responsabilidade |
| --- | --- |
| `usuarios` | autenticação, perfil e status |
| `categorias` | classificação de produtos |
| `fornecedores` | fornecedor e endereço estruturado |
| `produtos` | catálogo, saldo atual, mínimo, SKU e código de barras |
| `movimentacoes` | trilha das entradas/saídas/ajustes |
| `inventarios` | cabeçalho do inventário físico |
| `inventario_itens` | fotografia, contagem e divergência por produto |
| `logs_auditoria` | ações administrativas e de segurança |
| `notificacoes` | alertas internos por usuário |
| `api_tokens` | hashes de tokens da API |
| `tentativas_login` | proteção contra força bruta |
| `recuperacoes_senha` | tokens de recuperação em hash |
| `verificacoes_email` | tokens de confirmação de e-mail em hash |
| `anexos` | metadados de arquivos vinculados a produtos |
| `configuracoes` | parâmetros simples da aplicação |

## Rastreabilidade de movimentações

Novas linhas em `movimentacoes` podem registrar:

- produto;
- usuário responsável;
- fornecedor;
- tipo (`entrada` ou `saida`);
- quantidade;
- saldo anterior;
- saldo posterior;
- origem (`manual`, `ajuste`, `inventario` etc.);
- motivo;
- valor unitário de compra quando aplicável;
- referência;
- observação;
- data/hora.

Em bancos migrados, movimentações antigas podem ter `saldo_anterior` e `saldo_posterior` nulos, porque reconstruí-los a partir do saldo atual poderia gerar histórico falso.

## Integridade

A instalação limpa usa chaves estrangeiras nos relacionamentos estruturais. Exclusões físicas são evitadas na interface para cadastros já relacionados, priorizando status ativo/inativo.

Índices existem nos campos mais usados em filtros e relacionamentos, incluindo produto, usuário, fornecedor, tipo/origem/data das movimentações, status de inventário e validade dos tokens.

## Transações

São usadas transações nas operações críticas de estoque e inventário. O produto é bloqueado com `FOR UPDATE` antes de calcular e persistir o novo saldo. Isso reduz condições de corrida quando duas requisições tentam alterar o mesmo item simultaneamente.

## Migrações

Para bancos antigos, execute em ordem e após backup:

```text
2026_09_08_atualizar_schema.sql
2026_09_22_profissionalizacao.sql
```

A migração de profissionalização adiciona endereço estruturado de fornecedor, rastreabilidade de movimentação, índices relacionados, chave estrangeira do fornecedor da movimentação e tokens da API.

Para uma checagem não destrutiva após migrar, execute `database/check_integrity.sql`. Ele apenas conta registros órfãos, estoque negativo e duplicidades relevantes; não corrige nem apaga dados automaticamente.
