# Acesso por perfil — correção

O problema estava no `login.html`: o JavaScript antigo aceitava qualquer e-mail diferente do padrão com uma senha curta e redirecionava para o painel, sem consultar o PHP.

## Regra agora

- **Administrador** → autentica exclusivamente na tabela `adm` → `interiorAdm.html#relatorio`.
- **Funcionário** → autentica exclusivamente na tabela `func` → `tela_alerta.html`.
- Um funcionário autenticado não consegue abrir o painel administrativo: `guard-admin.js` manda para `tela_alerta.html`.
- Um administrador autenticado não consegue abrir o painel do funcionário: `guard-funcionario.js` manda para `interiorAdm.html#relatorio`.
- O PHP continua sendo a autoridade real: APIs administrativas usam `exigirAdmin()`.

## Importante

Remova ou deixe de carregar o JavaScript antigo de login com a lógica `usuarioDemo` e não mantenha outro `submit` inline no `login.html`.

Os caminhos `../js/...` assumem esta estrutura:

```text
html/login.html
html/tela_alerta.html
js/login.js
js/api.js
auth/processa_login.php
```

A `VALISTOQUE_API_BASE` em `js/api.js` deve apontar para a pasta real do backend.
