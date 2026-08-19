(async function () {
    const usuarioEl = document.getElementById('usuario-logado');
    try {
        const me = await apiRequest('auth/me.php');
        const usuario = me.dados.usuario;
        salvarUsuarioLocal(usuario);
        if (usuarioEl) usuarioEl.textContent = `👤 ${usuario.nome}`;
        if (usuario.perfil !== 'funcionario') return;

        const resposta = await apiRequest('api/alerta.php?todos=0');
        const alertas = resposta.dados || [];
        const sessoes = document.querySelectorAll('.alert-section');
        const estoqueBox = sessoes[0]?.querySelector('.alert-list');
        const validadeBox = sessoes[1]?.querySelector('.alert-list');
        if (estoqueBox) estoqueBox.innerHTML = '';
        if (validadeBox) validadeBox.innerHTML = '';

        for (const alerta of alertas) {
            const li = document.createElement('li');
            li.className = 'alert-item';
            li.innerHTML = `<span class="item-name"></span><span class="item-details"></span><span class="item-details"></span><span class="item-details"></span><span class="item-details"></span><button class="close-btn" title="Marcar como lido">&times;</button>`;
            li.children[0].textContent = alerta.produto || '';
            li.children[1].textContent = alerta.tipo === 'estoque_baixo_prateleira' ? (alerta.codigo_prateleira || '') : 'Estoque central';
            li.children[2].textContent = `Lote: ${alerta.lote || '-'}`;
            li.children[3].textContent = alerta.referencia || '';
            li.children[4].textContent = alerta.status || '';
            li.querySelector('button').addEventListener('click', async () => {
                try {
                    await apiRequest(`api/alerta.php?id=${encodeURIComponent(alerta.id)}&acao=ler`, { method: 'POST' });
                    li.remove();
                } catch (erro) { alert(erro.message); }
            });
            const destino = alerta.tipo === 'validade_proxima' || alerta.tipo === 'produto_vencido' ? validadeBox : estoqueBox;
            destino?.appendChild(li);
        }
    } catch (erro) {
        alert(erro.message);
        window.location.href = 'login.html';
    }
})();
