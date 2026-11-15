# Scripts locais para Windows

Abra PowerShell na raiz do repositório.

1. Execute `Set-ExecutionPolicy -Scope Process Bypass` se a política local bloquear scripts.
2. Execute `./scripts/setup-windows.ps1` para validar ferramentas, instalar Composer/npm e criar `backend/.env`.
3. Crie a base MySQL e aplique os três ficheiros SQL indicados no terminal.
4. Edite `backend/.env` com as credenciais correctas.
5. Execute `./scripts/start-windows.ps1`.

O script de arranque abre dois terminais PowerShell: um para a API PHP e outro para o Angular. Para encerrar, feche esses terminais.
