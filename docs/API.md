# API REST v1

## Objetivo

A API REST do SmartControl demonstra interoperabilidade e permite que clientes autorizados consultem dados do estoque em JSON sem acessar diretamente o banco.

Base:

```text
{APP_URL}/api/v1
```

Com `mod_rewrite`, as rotas podem ser acessadas sem `.php`. Quando a reescrita não estiver habilitada, use os arquivos `.php` diretamente.

## Autenticação

Há duas formas:

1. sessão já autenticada no navegador;
2. header Bearer com token criado por administrador em `settings/api.php`.

Exemplo com valor fictício:

```http
Authorization: Bearer sc_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
Accept: application/json
```

O token bruto não é salvo. O banco mantém somente `SHA-256(token)` e datas de expiração/revogação.

## Formato de resposta

Sucesso:

```json
{
  "success": true,
  "data": [],
  "message": "Produtos encontrados.",
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 0,
    "pages": 1
  }
}
```

Erro:

```json
{
  "success": false,
  "data": null,
  "message": "Autenticação necessária.",
  "errors": {}
}
```

## Endpoints

### GET `/products`

Arquivo alternativo: `/products.php`

Filtros:

- `search`: nome, SKU ou código de barras;
- `category_id`;
- `supplier_id`;
- `page`;
- `limit` (máximo 100).

### GET `/categories`

Lista categorias e status.

### GET `/suppliers`

Filtros:

- `search`: nome ou CPF/CNPJ;
- `page`;
- `limit`.

### GET `/inventory`

Lista até 100 inventários recentes e informa quantidade de itens contados/divergentes.

### GET `/movements`

Filtros:

- `type=entrada|saida`;
- `product_id`;
- `origin=manual|ajuste|inventario|cadastro`;
- `page`;
- `limit`.

A resposta inclui saldo anterior/posterior, origem, motivo, referência, usuário e fornecedor quando disponíveis.

### GET `/reports`

Retorna indicadores gerenciais: produtos ativos, unidades em estoque, estoque baixo, itens sem estoque, inventários abertos e movimentações do dia.

### GET `/external/cep.php?cep=...`

Consulta CEP. O serviço tenta ViaCEP e possui fallback para BrasilAPI. O preenchimento manual continua disponível se ambos falharem.

### GET `/external/cnpj.php?cnpj=...`

Consulta informações públicas de CNPJ pela BrasilAPI. O preenchimento manual continua disponível em caso de indisponibilidade.

## Códigos HTTP usados

- `200`: sucesso;
- `400`: requisição inválida;
- `401`: autenticação ausente/inválida;
- `403`: operação sem permissão;
- `405`: método não permitido;
- `422`: parâmetro semanticamente inválido;
- `503`: integração externa indisponível.

## Escopo atual

Os endpoints de domínio são somente leitura. Entradas, saídas, ajustes e finalização de inventário permanecem no fluxo web transacional para que exista um único ponto de aplicação das regras de saldo e auditoria.
