# LIRANDZU — LC CONSULTORIA

Plataforma digital de descoberta e intermediação de fornecedores para casamentos em Moçambique.

A LIRANDZU aproxima clientes e profissionais de casamentos, mantendo a LC CONSULTORIA como intermediária das comunicações e do encaminhamento dos pedidos.

## Funcionalidades

### Portal público e clientes
- Catálogo de fornecedores activos e aprovados.
- Pesquisa por texto, categoria e cidade.
- Perfis públicos com serviços, preços indicativos e portfólio.
- Favoritos guardados no navegador.
- Comparação de até três fornecedores.
- Pedido de contacto encaminhado à equipa LIRANDZU.
- Consulta do estado do pedido por referência e envio de mensagens à equipa.

### Portal de fornecedores
- Candidatura pública sujeita a aprovação administrativa.
- Registo directo pela administração.
- Gestão do perfil comercial e das categorias.
- Gestão de serviços, preços e galeria através de URLs de imagens/vídeos.
- Caixa de notificações.
- Consulta de pedidos encaminhados e resposta privada à equipa LIRANDZU.
- Alteração da palavra-passe.

### Painel administrativo
- Indicadores de operação.
- Aprovação/rejeição de candidaturas.
- Registo, activação, suspensão, verificação e destaque de fornecedores.
- Gestão de categorias.
- Pesquisa e filtragem de pedidos.
- Encaminhamento de mensagens para cliente ou fornecedor.
- Histórico de estados, notificações e auditoria.

## Tecnologias
- Angular 20 + TypeScript
- PHP 8.2+ + PDO
- MySQL 8 / MariaDB
- API REST
- GitHub Actions para build e testes

## Executar localmente com Docker (recomendado)

Requisitos: Windows 10/11 com Docker Desktop e WSL 2, ou Docker Engine + Docker Compose.

1. Descarregue o ZIP da branch `feat/lirandzu-platform` ou clone o repositório.
2. Abra um terminal na pasta do projecto.
3. Execute:

```bash
docker compose up --build
```

4. Abra:
   - Frontend: http://localhost:4200
   - API: http://localhost:8080/api/health
   - MySQL: localhost:3307 (base `lirandzu`, utilizador `lirandzu`, palavra-passe `lirandzu_local`)

A primeira inicialização cria a base de dados e aplica automaticamente o schema e as migrações. Os dados ficam no volume Docker `lirandzu_db`.

Para parar: `docker compose down`.

Para apagar também a base de dados local e começar de novo (ATENÇÃO: elimina os dados do volume): `docker compose down -v`.

## Instalação no Windows sem Docker

Com PHP 8.2+ (incluindo a extensão pdo_mysql), Composer 2, Node.js 22+ e MySQL instalados:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
./scripts/setup-windows.ps1
```

Crie a base de dados e aplique os três scripts SQL da secção seguinte. Depois ajuste `backend/.env` e execute:

```powershell
./scripts/start-windows.ps1
```

O script abre o backend e o frontend em janelas PowerShell separadas.

## Executar sem Docker

### Requisitos
- PHP 8.2+ com extensões PDO e pdo_mysql
- Composer 2
- Node.js 22+
- MySQL 8 ou MariaDB recente

### 1. Base de dados
Execute, pela ordem, no MySQL/MariaDB:

```sql
SOURCE database/schema.sql;
SOURCE database/migrations/001_expand_intermediation_model.sql;
SOURCE database/migrations/002_auth_rate_limits.sql;
```

### 2. Backend
```bash
cd backend
cp .env.example .env
composer install
php -S 127.0.0.1:8080 -t public
```

Edite `backend/.env` com as credenciais da sua base de dados.

### 3. Frontend
Noutro terminal:
```bash
cd frontend
npm install
npm start
```

Abra http://localhost:4200. O proxy de desenvolvimento encaminha os pedidos `/api` para o backend em `127.0.0.1:8080`.

## Criar o primeiro administrador

Depois de instalar as dependências PHP e configurar a base de dados:

```bash
cd backend
php bin/create-admin.php "Administrador LIRANDZU" admin@seudominio.mz "UmaSenha-Forte-com-12"
```

Use uma palavra-passe única com pelo menos 12 caracteres. Não reutilize credenciais de exemplo.

## Estrutura do projecto

```
backend/                 API PHP, autenticação e testes
  bin/                   Comandos administrativos
  public/                Ponto de entrada REST
  src/                   Classes PHP
  tests/                 Testes smoke e integração SQL
database/                Schema e migrações SQL
docs/                    Segurança e fluxos de negócio
frontend/                Aplicação Angular
.github/workflows/       Integração contínua
docker-compose.yml       Ambiente local completo
```

## Regras de negócio essenciais
- Apenas fornecedores aprovados e activos aparecem no catálogo.
- Um fornecedor pode candidatar-se ou ser registado pela administração.
- A administração decide a aprovação e controla estado, verificação e destaque.
- O fornecedor gere o próprio perfil, serviços, categorias e portfólio.
- A LC CONSULTORIA intermedeia as mensagens entre cliente e fornecedor.
- Os contactos privados dos clientes não são expostos no catálogo nem no portal do fornecedor.
- Não existe processamento de pagamentos integrado nesta versão.

## Segurança e produção
Leia [docs/SECURITY.md](docs/SECURITY.md) antes de publicar. O ambiente Docker é apenas para desenvolvimento local e usa credenciais de conveniência. Em produção configure HTTPS, segredos fortes, backups, domínio CORS exacto, monitorização, política de retenção e revisão de segurança. Não publique ficheiros `.env`.

## Verificações
```bash
cd frontend && npm install && npm run build
cd ../backend && composer install && composer test
``

A CI também aplica o schema e as migrações numa instância MySQL de teste.
