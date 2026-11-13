# LIRANDZU — LC CONSULTORIA

Plataforma de descoberta e intermediação de fornecedores para casamentos em Moçambique.

## Tecnologias
- Angular 20 + TypeScript em frontend/
- PHP 8.2+ + PDO em backend/
- MySQL 8 / MariaDB em database/schema.sql

## Instalação local
1. Executar database/schema.sql no MySQL/MariaDB.
2. Copiar backend/.env.example para backend/.env e preencher as credenciais.
3. Na pasta backend: composer install.
4. Iniciar a API: php -S 127.0.0.1:8080 -t public.
5. Na pasta frontend: npm install e npm start.
6. Abrir http://localhost:4200. O proxy Angular encaminha /api para 127.0.0.1:8080.

## Criar o primeiro administrador
No terminal do servidor, dentro de backend/, executar:

    php bin/create-admin.php "Administrador LIRANDZU" admin@seudominio.mz "UmaSenha-Forte-com-12"

Não usar a palavra-passe de exemplo em produção.

## Funcionalidades nesta versão
- Catálogo público com pesquisa por nome/descrição, cidade e categoria.
- Pedido de contacto por fornecedor, com referência de acompanhamento.
- Candidaturas públicas de fornecedores, sujeitas à aprovação.
- Painel administrativo: indicadores, aprovação/rejeição, verificação, destaque, suspensão e consulta de pedidos.
- Área do fornecedor: edição do próprio perfil e consulta dos pedidos recebidos.
- Sessões PHP, passwords com hash seguro, prepared statements e controlo de funções no servidor.

## Modelo de intermediação
A LIRANDZU recebe os pedidos dos clientes e faz o encaminhamento aos fornecedores. A negociação e o pagamento são combinados directamente entre cliente e fornecedor fora da plataforma nesta primeira versão. Não existem pagamentos internos, contratos ou planeamento integral do casamento.

## Segurança antes de produção
Configurar HTTPS, domínio exacto em FRONTEND_URL, cookies Secure, backups, rate limiting no login e monitorização. A palavra-passe temporária criada ao aprovar um fornecedor é apresentada ao administrador uma única vez; deve ser entregue por canal seguro e alterada pelo fornecedor. Nunca publicar ficheiros .env.

## Estado
Implementação funcional em desenvolvimento. Antes de produção, executar build Angular, smoke tests PHP e testes integrados com uma base de dados de teste. Rever a política de privacidade e consentimento para tratamento dos contactos dos clientes.
