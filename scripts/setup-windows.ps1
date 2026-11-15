$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root 'backend'
$Frontend = Join-Path $Root 'frontend'

foreach ($Command in @('php','composer','node','npm')) {
    if (-not (Get-Command $Command -ErrorAction SilentlyContinue)) {
        throw "Comando '$Command' não encontrado. Instale PHP 8.2+, Composer 2 e Node.js 22+ e volte a executar este script."
    }
}

$PhpVersion = (& php -r 'echo PHP_VERSION_ID;')
if ([int]$PhpVersion -lt 80200) { throw 'A LIRANDZU requer PHP 8.2 ou superior.' }
& php -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) { throw 'A extensão pdo_mysql não está activa no PHP.' }
$NodeMajor = [int](& node -p 'process.versions.node.split(".")[0]')
if ($NodeMajor -lt 22) { throw 'A LIRANDZU requer Node.js 22 ou superior.' }

$EnvFile = Join-Path $Backend '.env'
if (-not (Test-Path $EnvFile)) {
    Copy-Item (Join-Path $Backend '.env.example') $EnvFile
    Write-Host 'Foi criado backend/.env. Edite as credenciais da base de dados antes de iniciar a API.' -ForegroundColor Yellow
}

Push-Location $Backend
try { composer install --no-interaction } finally { Pop-Location }
Push-Location $Frontend
try { npm install } finally { Pop-Location }

Write-Host ''
Write-Host 'Dependências instaladas.' -ForegroundColor Green
Write-Host 'Agora crie a base lirandzu no MySQL e execute, pela ordem:'
Write-Host '  database/schema.sql'
Write-Host '  database/migrations/001_expand_intermediation_model.sql'
Write-Host '  database/migrations/002_auth_rate_limits.sql'
Write-Host 'Confirme as credenciais em backend/.env e execute scripts/start-windows.ps1.'
