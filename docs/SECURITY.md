# Segurança e preparação para produção

## Controlos já implementados
- Autorização no servidor por função e verificação de propriedade dos registos do fornecedor.
- Sessões PHP com cookies `HttpOnly` e `SameSite=Lax`; em HTTPS, o cookie recebe também `Secure`.
- Token CSRF de sessão nas operações autenticadas de escrita; o frontend envia `X-CSRF-Token`.
- CORS limitado a `FRONTEND_URL` configurado, com métodos e cabeçalhos explícitos.
- Limitação de tentativas de login por combinação de e-mail e endereço IP: oito falhas na janela de 15 minutos accionam bloqueio temporário de 15 minutos. Requer a migração `002_auth_rate_limits.sql`.
- Registo de operações administrativas e histórico das transições de estado dos pedidos.
- Contactos privados dos clientes não são devolvidos pela API de fornecedor; a comunicação passa pela LIRANDZU.

## Obrigações antes de produção
- Usar HTTPS e confirmar que o proxy/servidor define correctamente `HTTPS` para activar cookies Secure.
- Restringir `FRONTEND_URL` ao domínio exacto; nunca usar CORS wildcard com credenciais.
- Nunca versionar `.env`, passwords, tokens ou dados pessoais.
- Criar o administrador inicial com `password_hash()` e proteger o processo de provisionamento.
- Implementar limites de frequência também para candidaturas, pedidos, mensagens e consultas de referências (o rate limit actual cobre login).
- Validar uploads (MIME, tamanho, extensão e nome aleatório) e armazená-los fora da raiz pública. A galeria actual aceita URLs; upload binário ainda não está implementado.
- Fazer backups cifrados e testar a restauração.
- Definir política de retenção, consentimento e tratamento de dados pessoais.
- Executar testes funcionais de ponta a ponta e uma revisão de segurança independente.
