$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$Backend = Join-Path $Root 'backend'
$Frontend = Join-Path $Root 'frontend'

if (-not (Test-Path (Join-Path $Backend '.env'))) { throw 'Falta backend/.env. Execute primeiro scripts/setup-windows.ps1.' }
if (-not (Test-Path (Join-Path $Backend 'vendor/autoload.php'))) { throw 'Faltam dependências PHP. Execute scripts/setup-windows.ps1.' }
if (-not (Test-Path (Join-Path $Frontend 'node_modules'))) { throw 'Faltam dependências Angular. Execute scripts/setup-windows.ps1.' }

Start-Process -FilePath 'powershell.exe' -WorkingDirectory $Backend -ArgumentList @('-NoExit','-Command','php -S 127.0.0.1:8080 -t public router.php')
Start-Process -FilePath 'powershell.exe' -WorkingDirectory $Frontend -ArgumentList @('-NoExit','-Command','npm start')

Write-Host 'Servidores iniciados em terminais separados.' -ForegroundColor Green
Write-Host 'Frontend: http://localhost:4200'
Write-Host 'API:      http://localhost:8080/api/health'
