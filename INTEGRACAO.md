# Alterações nos HTML existentes

## `login.html`
1. No final do body, antes do script antigo de login, inclua:
```html
<script src="js/api.js"></script>
<script src="js/login.js"></script>
```
2. Remova/comente o `formLogin.addEventListener('submit', ...)` antigo, para não haver dois logins.

## `recuperasenha.html`
1. No final do body inclua:
```html
<script src="js/api.js"></script>
<script src="js/recuperacao.js"></script>
```
2. Remova/comente o listener antigo que grava `localStorage` e redireciona diretamente para login.

## `tela_alerta.html`
1. Troque os links de saída por:
```html
<a href="Principal.html" class="menu-item logout-btn" data-logout>🚪 Sair</a>
```
2. Antes de `</body>` inclua:
```html
<script src="js/api.js"></script>
<script src="js/logout.js"></script>
<script src="js/funcionario-alertas.js"></script>
```
3. O HTML de exemplo das listas pode permanecer: o JS o substitui pelos alertas reais.

## `Principal.html`
Os links atuais para `login.html` e `recuperasenha.html` já estão corretos.

## `interiorAdm.html`
1. No final do body, depois dos scripts existentes, inclua:
```html
<script src="js/api.js"></script>
<script src="js/logout.js"></script>
<script src="js/admin-integracao.js"></script>
```
2. No menu, troque a saída para:
```html
<li><a href="Principal.html" data-logout>Sair</a></li>
```
3. O `admin-integracao.js` passa a usar MySQL para produtos, estoque, prateleiras, usuários, alertas e relatórios.
4. Os dados de demonstração do `localStorage` deixam de ser a fonte principal.

## SQL
Use somente `database.sql` com o banco `projeto_valistoque`. A consulta `SELECT * FROM sistema_login.usuarios;` não corresponde ao esquema enviado.
