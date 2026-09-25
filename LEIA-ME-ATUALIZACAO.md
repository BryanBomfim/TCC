# Atualização do SmartControl — 24/09/2026

Esta entrega continua o projeto do arquivo `smartcontrol(1).zip`. Mantém PHP, MySQL/MariaDB, Composer, PHPMailer e a interface existente.

## Se você já usa o SmartControl no XAMPP

1. Faça uma cópia da pasta atual e exporte o banco pelo phpMyAdmin.
2. Pare os acessos durante a atualização. Copie o conteúdo da pasta `smartcontrol` deste ZIP para sua pasta do projeto. **Preserve seu `.env` e `storage/uploads`**. Configurações privadas e arquivos enviados por usuários não estão no pacote.
3. No phpMyAdmin, selecione o banco usado pelo seu `.env`. Importe **somente** `database/migrations/2026_09_24_conclusao.sql` se você está atualizando a versão enviada nesta conversa. Execute uma única vez. Ela adiciona `usuarios.auth_version`, `inventarios.revisao` e `inventario_itens.ultimo_movimento_id`; não recria tabelas.
4. Não reaplique as migrações de setembro anteriores: o ZIP de origem já contém esse esquema. Se as três colunas já existirem, não execute novamente a migração.
5. Abra `http://localhost/smartcontrol/` e entre novamente. Sessões antigas são encerradas por segurança.
6. Em inventários que estavam abertos antes da atualização, use **Revisar saldos e recontar**. Os valores antigos ficam preservados até essa confirmação; a revisão registra os valores na auditoria antes de limpar as contagens necessárias.
7. Execute `VERIFICAR.cmd` e o checklist em `docs/TESTES.md`. Use uma cópia de teste do banco para homologar antes de liberar o uso.

## Instalação nova

Siga `README.md`: importe `database/smartcontrol.sql` em uma base vazia e copie `.env.example` para `.env`. Não aplique migrations em seguida: o esquema novo já contém as alterações. O SQL não contém mais comandos DROP.

Conta inicial de demonstração: `admin@smartcontrol.com`, senha `SmartControl@2026!`. O sistema exige definir outra senha em Minha Conta antes de usar as operações. Configure SMTP para cadastrar usuários com verificação, trocar e-mail ou recuperar senhas.

## O que foi corrigido

- Sessões são revalidadas contra o banco; bloqueio, alteração de perfil, e-mail e senha invalidam acessos anteriores. Bloquear e reativar não restaura sessões antigas.
- Alterações sensíveis revogam tokens de recuperação, verificação e API; a gestão de usuários preserva ao menos um administrador ativo e verificado.
- Erros técnicos de banco não são apresentados como mensagens de negócio. Logs não incluem os valores das exceções.
- Entrada, saída e ajuste registram auditoria na mesma transação do saldo e do histórico.
- Inventários detectam movimentações posteriores mesmo quando o saldo volta ao valor inicial. Há revisão para recontagem, controle de edição simultânea e paginação de 25 itens.
- SKU e código de barras funcionam simultaneamente na seleção de produtos.
- Quantidades e preços respeitam limites do banco; CSV também neutraliza fórmulas precedidas de espaços e caracteres de controle.
- API não depende de cookies de sessão, verifica e-mail/escopo e pagina todas as coleções, incluindo itens de inventário.
- Pacote sem `.env`, sem anexos pessoais e sem logs reais; documentação e testes atualizados.

## Validação e limites

A entrega passou por análise sintática PHP com Tree-sitter e verificações JavaScript, incluindo três cenários do leitor de códigos. Isso **não substitui** `php -l`, testes com MariaDB, Apache, SMTP ou navegador. Esses serviços não estavam disponíveis no ambiente desta revisão. Os resultados antigos no arquivo de auditoria não foram reapresentados como resultados desta versão.

O layout foi preservado; não houve validação visual em navegador nesta revisão. PDF continua disponível pelo comando Imprimir/Salvar como PDF do navegador. Não foi adicionada geração de QR Code, e a API continua somente leitura.
