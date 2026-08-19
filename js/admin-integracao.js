(function () {
    const qs = (s) => document.querySelector(s);
    const qsa = (s) => [...document.querySelectorAll(s)];
    const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    let estoques = [], prateleiras = [], alertas = [], configuracao = null;

    async function carregarMe() {
        const me = await apiRequest('auth/me.php');
        const usuario = me.dados.usuario;
        salvarUsuarioLocal(usuario);
        if (usuario.perfil !== 'administrador') {
            window.location.href = 'tela_alerta.html';
            return null;
        }
        return usuario;
    }

    async function carregarEstoque() {
        estoques = (await apiRequest('api/estoque.php')).dados || [];
        renderEstoque();
    }

    function renderEstoque() {
        const c = qs('.cards-container'); if (!c) return;
        c.innerHTML = estoques.map(e => `
            <div class="card-item" data-id="${e.id}">
                <h3>${escapeHtml(e.produto)}</h3>
                <div class="card-info"><strong>Validade:</strong> ${escapeHtml(e.validade)}</div>
                <div class="card-info"><strong>Lote:</strong> ${escapeHtml(e.lote)}</div>
                <div class="card-info"><strong>Qtd. Caixas:</strong> ${e.quantidade_caixas}</div>
                <div class="card-info"><strong>Produtos/Caixa:</strong> ${e.produtos_por_caixa}</div>
                <div class="card-acoes"><button class="btn-editar" data-edit="${e.id}">Editar</button><button class="btn-excluir" data-delete="${e.id}">Excluir</button></div>
            </div>`).join('') || '<div class="vazio-alertas">Nenhum estoque cadastrado.</div>';
        c.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => editarEstoque(Number(b.dataset.edit))));
        c.querySelectorAll('[data-delete]').forEach(b => b.addEventListener('click', () => excluirEstoque(Number(b.dataset.delete))));
    }

    async function salvarProdutoEstoque(event) {
        event.preventDefault();
        const idEstoque = Number(qs('#editando-id')?.value || 0);
        const bodyEstoque = {
            lote: qs('#lote').value.trim(),
            validade: qs('#validade').value,
            quantidade_caixas: Number(qs('#caixa').value || 0),
            produtos_por_caixa: Number(qs('#produto-caixa').value || 1),
            peso_kg: Number(qs('#peso').value || 0)
        };
        if (!bodyEstoque.lote || !bodyEstoque.validade || bodyEstoque.quantidade_caixas <= 0) return alert('Preencha lote, validade e quantidade.');

        try {
            if (idEstoque) {
                const atual = estoques.find(e => Number(e.id) === idEstoque);
                if (!atual) throw new Error('Registro de estoque não encontrado.');
                const produto = (await apiRequest(`api/produtos.php?id=${atual.id_produto}`)).dados;
                await apiRequest(`api/produtos.php?id=${atual.id_produto}`, { method: 'PUT', body: {
                    id: atual.id_produto, nome: qs('#produto').value.trim(), categoria: produto.categoria || '', marca: produto.marca || '', codigo_barras: produto.codigo_barras || '', unidade_medida: produto.unidade_medida || 'un', peso_kg: bodyEstoque.peso_kg
                }});
                await apiRequest(`api/estoque.php?id=${idEstoque}`, { method: 'PUT', body: { id: idEstoque, ...bodyEstoque } });
            } else {
                const produto = await apiRequest('api/produtos.php', { method: 'POST', body: { nome: qs('#produto').value.trim(), categoria: '', marca: '', codigo_barras: '', unidade_medida: 'un', peso_kg: bodyEstoque.peso_kg } });
                await apiRequest('api/estoque.php', { method: 'POST', body: { id_produto: produto.dados.id, ...bodyEstoque } });
            }
            event.target.reset();
            qs('#editando-id').value = '';
            await carregarEstoque();
            if (typeof Estoque === 'function') Estoque();
            alert('Dados salvos com sucesso.');
        } catch (erro) { alert(erro.message); }
    }

    function editarEstoque(id) {
        const e = estoques.find(x => Number(x.id) === id); if (!e) return;
        qs('#editando-id').value = e.id; qs('#produto').value = e.produto || ''; qs('#lote').value = e.lote || ''; qs('#validade').value = e.validade || ''; qs('#caixa').value = e.quantidade_caixas || 0; qs('#produto-caixa').value = e.produtos_por_caixa || 1; qs('#peso').value = e.peso_kg || 0;
        if (typeof Produto === 'function') Produto();
    }

    async function excluirEstoque(id) {
        if (!confirm('Tem certeza que deseja excluir este registro?')) return;
        try { await apiRequest(`api/estoque.php?id=${id}`, { method: 'DELETE' }); await carregarEstoque(); } catch (erro) { alert(erro.message); }
    }

    async function carregarPrateleiras() {
        prateleiras = (await apiRequest('api/prateleira.php')).dados || [];
        renderPrateleiras();
    }
    function renderPrateleiras() {
        const c = qs('.prateleiras-container'); if (!c) return;
        c.innerHTML = prateleiras.map(p => `<div class="prateleira-card-horizontal"><div class="prat-numero">${escapeHtml(p.codigo_prateleira)}</div><div class="prat-detalhes"><h4>${escapeHtml(p.produto)}</h4><p><strong>Alocado:</strong> ${p.unidades} unidades</p><p><strong>Validade:</strong> ${escapeHtml(p.validade)}</p><p><strong>Lote:</strong> ${escapeHtml(p.lote)}</p></div></div>`).join('') || '<div class="vazio-alertas">Nenhuma alocação encontrada.</div>';
    }

    async function prepararModalPrateleira() {
        const estoqueAtual = (await apiRequest('api/estoque.php')).dados || [];
        const selectLote = qs('#lote-prat'), selectPrat = qs('#num-prateleira');
        if (!selectLote || !selectPrat) return;
        selectLote.innerHTML = '<option value="">Selecione um lote...</option>' + estoqueAtual.filter(e => Number(e.quantidade_caixas) > 0).map(e => `<option value="${e.id_produto}|${escapeHtml(e.lote)}">${escapeHtml(e.lote)} (${escapeHtml(e.produto)})</option>`).join('');
        selectPrat.innerHTML = '<option value="">Escolha o número...</option>' + Array.from({length: 1000}, (_, i) => `<option value="PRAT-${String(i+1).padStart(2,'0')}">Prateleira ${i+1}</option>`).join('');
    }

    async function transferirParaPrateleira() {
        const valor = qs('#lote-prat').value, quantidade = Number(qs('#caixas-prat').value || 0), codigo = qs('#num-prateleira').value;
        if (!valor || !codigo || quantidade <= 0) return alert('Preencha os campos da transferência.');
        const [id_produto, lote] = valor.split('|');
        try { await apiRequest('api/transferir.php', { method: 'POST', body: { id_produto: Number(id_produto), lote, quantidade, codigo_prateleira: codigo } }); qs('#cadastrarPrateleira').close(); qs('#form-prateleira').reset(); await Promise.all([carregarEstoque(), carregarPrateleiras()]); } catch (erro) { alert(erro.message); }
    }

    async function carregarUsuarios() {
        const dados = (await apiRequest('api/usuarios.php')).dados || {};
        const adm = qs('#tabela-administradores tbody'), func = qs('#tabela-funcionarios tbody');
        if (adm) adm.innerHTML = (dados.administrador || []).map(u => `<tr><td>${escapeHtml(u.nome)}</td><td>${escapeHtml(u.email)}</td></tr>`).join('');
        if (func) func.innerHTML = (dados.funcionario || []).map(u => `<tr><td>${escapeHtml(u.nome)}</td><td>${escapeHtml(u.email)}</td></tr>`).join('');
    }

    window.salvarUsuario = async function (event) {
        event.preventDefault();
        const body = { nome: qs('#nome-usuario').value.trim(), email: qs('#email-usuario').value.trim(), cpf: qs('#cpf-usuario').value.trim(), senha: qs('#senha').value, confirmar_senha: qs('#confirmsenha').value, perfil: qs('input[name="tipo-usuario"]:checked').value };
        try { await apiRequest('auth/processa_cadastro.php', { method: 'POST', body }); event.target.reset(); await carregarUsuarios(); alert('Usuário cadastrado com sucesso.'); } catch (erro) { alert(erro.message); }
    };

    async function carregarAlertas() {
        await apiRequest('api/alerta.php?varrer=1');
        alertas = (await apiRequest('api/alerta.php?todos=1')).dados || [];
        renderAlertas();
    }
    function renderAlertas() {
        const lista = qs('#lista-alertas-completa');
        if (!lista) return;
        const total = alertas.length, crit = alertas.filter(a => a.status === 'critico').length, aviso = alertas.filter(a => a.status === 'aviso').length;
        qs('#resumo-total-alertas').textContent = total; qs('#resumo-alertas-criticos').textContent = crit; qs('#resumo-alertas-aviso').textContent = aviso;
        lista.innerHTML = alertas.map(a => `<div class="alerta-item"><div class="alerta-meta"><span class="tag-alerta ${a.status === 'critico' ? 'tag-critico' : 'tag-aviso'}">${a.status}</span><span><strong>Tipo:</strong> ${escapeHtml(a.tipo)}</span><span><strong>Produto:</strong> ${escapeHtml(a.produto)}</span><span><strong>Lote:</strong> ${escapeHtml(a.lote)}</span></div><h3>${escapeHtml(a.referencia)}</h3><div class="alerta-mensagem">${escapeHtml(a.mensagem)}</div></div>`).join('') || '<div class="vazio-alertas">Nenhum alerta.</div>';
    }

    async function carregarConfiguracoes() {
        configuracao = (await apiRequest('api/alerta.php?rota=config')).dados;
        if (qs('#dias-alerta-validade')) qs('#dias-alerta-validade').value = configuracao.dias_antes_validade;
        if (qs('#caixas-alerta-estoque')) qs('#caixas-alerta-estoque').value = configuracao.caixas_minimas_central;
        if (qs('#intervalo-alerta')) qs('#intervalo-alerta').value = String(configuracao.intervalo_minutos);
    }
    window.salvarConfiguracoesAlertas = async function (event) {
        event.preventDefault();
        try { await apiRequest('api/alerta.php?rota=config', { method: 'PUT', body: { dias_antes_validade: Number(qs('#dias-alerta-validade').value), caixas_minimas_central: Number(qs('#caixas-alerta-estoque').value), caixas_minimas_prateleira: Number(configuracao?.caixas_minimas_prateleira || 5), intervalo_minutos: Number(qs('#intervalo-alerta').value) } }); await carregarAlertas(); alert('Configuração salva.'); } catch (erro) { alert(erro.message); }
    };

    async function carregarRelatorio() {
        const dados = (await apiRequest('api/relatorios.php?tipo=movimentacao')).dados || [];
        const grupos = { 'saida-estoque': [], 'entrada-estoque': [], 'saida-prateleira': [], 'entrada-prateleira': [] };
        dados.forEach(m => {
            const tipo = String(m.tipo || '');
            let alvo = tipo.startsWith('entrada') ? 'entrada-estoque' : tipo.startsWith('transferencia') ? 'entrada-prateleira' : 'saida-estoque';
            grupos[alvo].push(m);
        });
        Object.entries(grupos).forEach(([id, arr]) => { const el = qs('#'+id); if (el) el.innerHTML = arr.slice(0, 20).map(m => `<div class="list-item"><span>${m.quantidade_caixas} caixas</span><span>${escapeHtml(m.lote || '-')}</span><span>${escapeHtml(m.created_at || '')}</span></div>`).join(''); });
        const al = (await apiRequest('api/relatorios.php?tipo=alertas')).dados || [], la = qs('#lista-alertas'); if (la) la.innerHTML = al.slice(0, 10).map(a => `<div class="list-item"><span>[${escapeHtml(a.status)}] ${escapeHtml(a.produto)}</span><span>Lote: ${escapeHtml(a.lote || '-')}</span><span>${escapeHtml(a.referencia || '')}</span></div>`).join('');
    }

    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const usuario = await carregarMe(); if (!usuario) return;
            const formProduto = qs('form.produto'); if (formProduto) formProduto.addEventListener('submit', salvarProdutoEstoque, true);
            const btnMais = qs('.btn-mais'); if (btnMais) btnMais.addEventListener('click', async (e) => { e.preventDefault(); e.stopImmediatePropagation(); { await prepararModalPrateleira(); qs('#cadastrarPrateleira')?.showModal(); }, true);
            const btnTransfer = qs('#closeModalBtn'); if (btnTransfer) btnTransfer.addEventListener('click', e => { e.preventDefault(); e.stopImmediatePropagation(); transferirParaPrateleira(); }, true);
            await Promise.all([carregarEstoque(), carregarPrateleiras(), carregarUsuarios(), carregarConfiguracoes(), carregarAlertas(), carregarRelatorio()]);
        } catch (erro) { alert(erro.message); window.location.href = 'login.html'; }
    });
})();
