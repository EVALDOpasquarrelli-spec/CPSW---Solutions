# LIRANDZU — LC CONSULTORIA

Plataforma digital de descoberta e intermediação de fornecedores para casamentos em Moçambique.

## Stack
- Angular 20 + TypeScript em `frontend/`.
- PHP 8.2+ REST API com PDO em `backend/`.
- MySQL/MariaDB em `database/schema.sql`.

## Arranque
1. Executar `database/schema.sql` no MySQL/MariaDB.
2. Copiar `backend/.env.example` para `backend/.env` e configurar as variáveis no ambiente PHP.
3. `cd backend && composer install && php -S 127.0.0.1:8080 -t public`.
4. `cd frontend && npm install && npm start`.

## Modelo funcional
Catálogo público, candidaturas de fornecedores sujeitas a aprovação, pedidos encaminhados através da LIRANDZU e área administrativa. Os contactos pessoais não são expostos publicamente. A primeira versão não inclui contratos, pagamentos internos nem negociação comercial dentro da plataforma.

## Segurança
Passwords são verificadas com `password_verify`; sessões usam cookies HttpOnly; a API usa PDO prepared statements e validação de entrada. Configurar HTTPS, origem CORS, cookie Secure, backups e credenciais de produção antes da publicação. Criar o primeiro administrador manualmente com `password_hash` — não há credenciais fixas no código.

O scaffold inicial ainda necessita de completar o fluxo de pedidos/conversas, perfis do fornecedor, moderação administrativa, testes automatizados e validação de produção.
