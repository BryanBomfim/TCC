# API REST v1

## Escopo

A API do SmartControl é interna, versionada e somente leitura. Ela demonstra interoperabilidade sem expor operações administrativas de escrita. A URL base local é:

```text
http://localhost/smartcontrol/api/v1
```

## Autenticação

Um administrador cria o token em **Configurações > Tokens da API**. O valor aparece uma única vez; o banco guarda apenas o hash. Envie-o no cabeçalho:

```http
Authorization: Bearer sc_seu_token_de_64_caracteres_hexadecimais
Accept: application/json
```

Tokens podem expirar ou ser revogados. Usuários inativos ou sem e-mail verificado perdem acesso. Alterações sensíveis de conta revogam tokens. A senha de demonstração impede o uso da API. Cookies de sessão não autenticam nem redirecionam a API. O limite atual é 120 requisições por minuto por token e IP.

## Envelope de resposta

```json
{
  "success": true,
  "data": [],
  "message": "Produtos encontrados.",
  "meta": {
    "total": 42,
    "page": 1,
    "per_page": 25,
    "pages": 2
  }
}
```

Em falhas, `success` é `false`, `data` é `null` e `message` contém texto seguro para o cliente.

## Endpoints

| Método e rota | Descrição | Parâmetros suportados |
|---|---|---|
| `GET /products` | lista produtos | `q`, `category_id`, `supplier_id`, `stock_status=low|out`, `page`, `per_page` |
| `GET /products/{id}` | detalha produto | ID numérico na rota |
| `GET /categories` | lista categorias e total de produtos | `page`, `per_page` |
| `GET /suppliers` | lista fornecedores e total de produtos | `page`, `per_page` |
| `GET /inventory` | lista inventários e seus totais | `page`, `per_page` |
| `GET /inventory/{id}` | detalha inventário e itens | ID numérico, `page`, `per_page` para os itens |
| `GET /movements` | lista movimentações | `type=entrada|saida`, `product_id`, `page`, `per_page` |
| `GET /reports` | retorna indicadores gerenciais | nenhum |

`per_page` é limitado a 100 itens; o padrão é 25. Todas as coleções retornam `meta`. No detalhe de inventário, a paginação se refere a `data.itens`. `page` é limitado a 1.000.000; parâmetros numéricos inválidos retornam 400.

## Exemplos

```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://localhost/smartcontrol/api/v1/products?stock_status=low&per_page=20"
```

```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  "http://localhost/smartcontrol/api/v1/movements?type=saida&product_id=10"
```

## Códigos HTTP

- `200`: consulta concluída.
- `400`: parâmetros inválidos.
- `401`: token ausente, inválido, expirado ou revogado.
- `404`: recurso ou registro não encontrado.
- `405`: método diferente de GET.
- `429`: limite de requisições excedido; consulte `Retry-After`.
- `500`: falha interna tratada e registrada no servidor.

## Regras de segurança

- Não envie tokens em query string, logs, capturas ou repositório.
- Use HTTPS em produção.
- Revogue tokens que deixarem de ser usados.
- As respostas não incluem senhas, hashes, tokens, configuração SMTP ou dados de auditoria.
- A API não aceita POST, PUT, PATCH ou DELETE na versão 1.
