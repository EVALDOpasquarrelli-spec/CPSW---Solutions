# Segurança de produção

- Usar HTTPS e configurar cookies `Secure`, `HttpOnly` e `SameSite`.
- Restringir `FRONTEND_URL` ao domínio exacto; não usar CORS wildcard com credenciais.
- Nunca versionar `.env`, passwords, tokens ou dados pessoais.
- Criar o administrador inicial com `password_hash()` e proteger o processo de provisionamento.
- Aplicar rate limiting no endpoint de login, registar eventos administrativos e rever a retenção de dados.
- Validar uploads (MIME, tamanho, extensão e nome aleatório) e armazená-los fora da raiz pública.
- Fazer backups cifrados e testar a restauração.
- Verificar autorização no servidor para cada operação sensível; a interface não substitui controlo de acesso.
