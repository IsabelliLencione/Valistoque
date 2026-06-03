# 🏪 Valistoque — Back-end

Sistema de **Controle de Estoque** desenvolvido em **PHP + MySQL** com APIs REST.

---

## 📂 Estrutura de Pastas

```
valistoque_backend/
│
├── includes/                 # Configuração e funções utilitárias
│   ├── config.php            # Constantes, sessão, ambiente
│   ├── conexao.php           # Conexões PDO + MySQLi
│   ├── funcoes.php           # Validação, sessão, JSON, logs, CSRF
│   └── .htaccess             # Bloqueia acesso direto
│
├── auth/                     # Autenticação
│   ├── processa_login.php
│   ├── processa_cadastro.php
│   ├── processa_recuperacao.php
│   ├── redefinir_senha.php
│   └── logout.php
│
├── api/                      # APIs REST do sistema
│   ├── produtos.php
│   ├── estoque.php
│   ├── prateleira.php
│   ├── movimentacao.php
│   ├── transferir.php
│   ├── alertas.php
│   ├── alertas_check.php     # Helper interno
│   ├── dashboard.php
│   └── relatorios.php
│
├── admin/                    # Painel administrativo
│   └── usuarios.php          # CRUD de admins/funcionários
│
├── sql/
│   └── database.sql          # Script de criação do BD
│
├── .htaccess                 # Proteções e CORS
└── README.md
```

---

## ⚙️ Instalação

### 1️⃣ Banco de dados
```bash
mysql -u root -p < sql/database.sql
```

Cria o banco `projeto_valistoque` com:
- Tabelas: `adm`, `func`, `produto`, `estoque`, `prateleira`, `movimentacao`, `alertas`, `config_alertas`, `recuperacao_senha`, `log_atividade`
- Usuário admin padrão: **admin@valistoque.com / admin123**
- Funcionário padrão: **funcionario@valistoque.com / func123**

### 2️⃣ Configure `includes/config.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'projeto_valistoque');
define('DB_USER', 'root');
define('DB_PASS', '');     // sua senha do MySQL
```

### 3️⃣ Servidor
Coloque a pasta no diretório do Apache (XAMPP / WAMP):
```
C:/xampp/htdocs/valistoque/backend/
```

---

## 🔐 Autenticação

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/auth/processa_login.php` | POST | Login (`email`, `senha`, `perfil`) |
| `/auth/processa_cadastro.php` | POST | Cadastro de novo usuário |
| `/auth/processa_recuperacao.php` | POST | Solicita código de recuperação |
| `/auth/redefinir_senha.php` | POST | Define nova senha com código |
| `/auth/logout.php` | GET | Encerra a sessão |

- Bloqueio automático após **5 tentativas** por **15 minutos**
- Senhas em `password_hash` (bcrypt)
- Sessão com `httponly` + `samesite=Lax`
- Timeout de inatividade: **1 hora**

---

## 📦 APIs REST (todas exigem login)

Todas retornam JSON no formato:
```json
{
  "sucesso": true,
  "mensagem": "...",
  "dados": { ... },
  "timestamp": "2026-06-03 12:00:00"
}
```

### 🔹 Produtos — `/api/produtos.php`
| Método | URL | Descrição |
|--------|-----|-----------|
| GET    | `?busca=arroz&categoria=Grãos` | Lista/filtra |
| GET    | `?id=1` | Detalhe |
| POST   | — | Cadastra produto |
| PUT    | `?id=1` | Atualiza |
| DELETE | `?id=1` | Exclui (apenas admin) |

### 🔹 Estoque Central — `/api/estoque.php`
- GET listar/consultar
- POST registrar **entrada** (acumula lote + movimentação)
- PUT editar lote
- DELETE remover (admin)

### 🔹 Prateleira — `/api/prateleira.php`
Mesmo padrão do estoque.

### 🔹 Movimentação — `/api/movimentacao.php`
- GET filtros: `id_produto`, `tipo`, `inicio`, `fim`
- POST registra **saída** (consome **FIFO** por validade)

### 🔹 Transferência Estoque → Prateleira — `/api/transferir.php`
POST: `{ id_produto, lote, quantidade, codigo_prateleira? }`

### 🔹 Alertas — `/api/alertas.php`
- GET `?todos=1` (inclui lidos) / `?varrer=1` (regera) / `?rota=config`
- POST `?id=N&acao=ler` marca como lido
- PUT `?rota=config` atualiza parâmetros (admin)
- DELETE `?id=N` exclui (admin)

**Tipos de alerta:**
- 🟡 Validade Próxima
- 🔴 Produto Vencido
- 🟠 Estoque Baixo Central
- 🟠 Estoque Baixo Prateleira

### 🔹 Dashboard — `/api/dashboard.php`
Retorna KPIs, gráfico 30 dias (entradas × saídas), validades próximas, estoque baixo, últimas movimentações e produtos mais movimentados.

### 🔹 Relatórios — `/api/relatorios.php`
- `?tipo=estoque|prateleira|movimentacao|alertas|validade`
- `?formato=json` (padrão) ou `&formato=csv` (download Excel UTF-8)

### 🔹 Usuários (admin) — `/admin/usuarios.php`
- `?perfil=adm|func`
- GET, PUT, DELETE

---

## 🛡️ Recursos de Segurança

- ✅ Senhas com **bcrypt** (`password_hash`)
- ✅ **Prepared statements** (PDO) — anti SQL Injection
- ✅ **CSRF token** disponível (`gerarCsrf()` / `validarCsrf()`)
- ✅ Sanitização (`limpar()`) — anti XSS
- ✅ Bloqueio progressivo de login
- ✅ Logs de auditoria em `log_atividade`
- ✅ Sessão regenerada após login
- ✅ Validação de CPF (algoritmo oficial)

---

## 📊 Lógica de Negócio

- **FIFO** automático nas saídas (consome lote com validade mais próxima primeiro)
- **Transferência** Estoque → Prateleira agrupada por validade
- **Alertas automáticos** recalculados a cada operação no produto
- **Movimentação** registrada em todas as operações (rastreabilidade)

---

## 👥 Usuários padrão para teste

| Perfil | E-mail | Senha |
|--------|--------|-------|
| Admin | `admin@valistoque.com` | `admin123` |
| Funcionário | `funcionario@valistoque.com` | `func123` |

---

## 📝 Logs

- Auditoria em banco: tabela `log_atividade`
- Erros PHP (em produção): `logs/erros.log`

---

**Projeto:** Valistoque · **Stack:** PHP 7.4+, MySQL 5.7+, Apache
