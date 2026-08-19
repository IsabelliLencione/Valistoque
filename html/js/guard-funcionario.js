(async function () {
    try {
        const resposta = await apiRequest('auth/me.php');
        const usuario = resposta?.dados?.usuario;
        if (!usuario || usuario.perfil !== 'funcionario') {
            window.location.replace('interiorAdm.html#relatorio');
            return;
        }
        const alvo = document.getElementById('usuario-logado');
        if (alvo) alvo.textContent = `👤 ${usuario.nome}`;
        salvarUsuarioLocal(usuario);
    } catch (e) {
        window.location.replace('login.html');
    }
})();
