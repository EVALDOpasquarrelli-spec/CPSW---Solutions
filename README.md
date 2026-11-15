# LIRANDZU — LC CONSULTORIA

Plataforma de descoberta e intermediação de fornecedores para casamentos em Moçambique.

## Tecnologias
- Angular 20 + TypeScript em `frontend/`
- PHP 8.2+ + PDO em `backend/`
- MySQL 8 / MariaDB em `database/`

## Instalação local
1. Executar `database/schema.sql` no MySQL/MariaDB.
2. Executar, pela ordem, as migrações existentes em `database/migrations/` (actualmente `001_expand_intermediation_model.sql`).
3. Copiar `backend/.env.example` para `backend/.env` e preencher as credenciais.
4. Na pasta `backend`: `composer install`.
5. Iniciar a API: `php -S 127.0.0.1:8080 -t public`.
6. Na pasta `frontend`: `npm install` e `npm start`.
7. Abrir `http://localhost:4200`. O proxy Angular encaminha `/api` para `127.0.0.1:8080`.

## Criar o primeiro administrador
No terminal do servidor, dentro de `backend/`, executar:

```bash
php bin/create-admin.php "Administrador LIRANDZU" admin@seudominio.mz "UmaSenha-Forte-com-12"
```

Não usar a palavra-passe de exemplo em produção.

## Portais e regras de negócio
- **Cliente:** descobre fornecedores activos e submete pedidos à equipa LIRANDZU.
- **Fornecedor:** candidata-se ou é registado pela administração; só é publicado após aprovação. Depois de aprovado, gere o próprio perfil, múltiplas categorias, serviços, preços e galeria.
- **Administração:** gere categorias, fornecedores, candidaturas, clientes, pedidos e comunicações; pode cadastrar fornecedores directamente, encaminhar mensagens para cada parte e actualizar o estado dos pedidos.
- **Intermediação obrigatória:** as comunicações entre cliente e fornecedor são mediadas pela LIRANDZU. Não disponibilizar contactos directos nem canais privados entre as partes na aplicação.
- Apenas fornecedores activos e aprovados podem aparecer no catálogo.
- O cliente acompanha o pedido pela referência LIRANDZU e pode enviar mensagens à equipa; a equipa encaminha as respostas, sem expor contactos directos.
- A negociação e o pagamento podem ser tratados fora da plataforma nesta fase; não existe processamento de pagamentos integrado no MVP.

## Segurança antes de produção
Configurar HTTPS, domínio exacto em `FRONTEND_URL`, cookies Secure, backups, rate limiting no login e monitorização. Nunca publicar ficheiros `.env`. Registar acções administrativas e limitar o acesso aos dados pessoais dos clientes à equipa autorizada.

## Estado
Implementação em evolução. A migração 001 amplia a estrutura relacional para clientes, múltiplas categorias, serviços, galeria, histórico de pedidos, notificações e auditoria. A API e os três portais já incluem operações iniciais para gerir serviços/galeria, responder e encaminhar mensagens, acompanhar pedidos e registar fornecedores pela administração. A integração ainda requer validação funcional e revisão de segurança. Antes de produção, executar build Angular, testes PHP, testes integrados MySQL/MariaDB e revisão de privacidade/segurança.

## Testes de integração
O workflow GitHub Actions executa build Angular, lint PHP, smoke tests e uma tarefa MySQL 8 que aplica `database/schema.sql` e `database/migrations/001_expand_intermediation_model.sql`, validando as tabelas e colunas essenciais.


### Segurança e gestão operacional (actualização)
- A administração pode gerir categorias (criar, editar, ordenar e activar/desactivar) sem apagar referências históricas.
- A lista de pedidos administrativos aceita filtros por estado, cidade, referência, cliente ou fornecedor.
- O acesso de fornecedores inclui edição de serviços existentes.
- O catálogo abre páginas públicas individuais com categorias, serviços, portfólio e pedido de contacto mediado.
- A autenticação limita tentativas falhadas por combinação de e-mail e endereço IP: após oito falhas dentro da janela de 15 minutos, bloqueia novas tentativas durante 15 minutos. A tabela é criada pela migração `002_auth_rate_limits.sql`.


### Novas funcionalidades e segurança
- Perfis públicos individuais de fornecedores com portfólio, serviços e pedido de contacto mediado.
- Gestão administrativa de categorias e filtros avançados de pedidos.
- Histórico de transições de estado dos pedidos visível no painel.
- Caixa de notificações para administradores e fornecedores.
- Protecção CSRF por token de sessão nas operações autenticadas.
- Limitação de tentativas falhadas de login (migração `002_auth_rate_limits.sql`).

Para instalar/actualizar a base de dados, executar pela ordem:
1. `database/schema.sql`
2. `database/migrations/001_expand_intermediation_model.sql`
3. `database/migrations/002_auth_rate_limits.sql`

As notificações internas são apresentadas nos painéis; envio de e-mail/SMS e armazenamento binário de imagens ainda exigem configuração e desenvolvimento próprios.
