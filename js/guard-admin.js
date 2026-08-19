(async function () {
    try {
        const resposta = await apiRequest('auth/me.php');
        const usuario = resposta?.dados?.usuario;
        if (!usuario || usuario.perfil !== 'administrador') {
            window.location.replace('tela_alerta.html');
            return;
        }
        const badge = document.getElementById('badge-perfil');
        const titulo = document.getElementById('titulo-perfil-admin');
        const nome = document.getElementById('user-name');
        const email = document.getElementById('user-email');
        if (badge) badge.textContent = 'Administrador';
        if (titulo) titulo.textContent = `Perfil do ${usuario.nome}`;
        if (nome) nome.value = usuario.nome || '';
        if (email) email.value = usuario.email || '';
        salvarUsuarioLocal(usuario);
    } catch (e) {
        window.location.replace('login.html');
    }
})();
