(function () {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopImmediatePropagation();

        const email = document.getElementById('email')?.value.trim() || '';
        const senha = document.getElementById('senha')?.value || '';
        const perfil = document.getElementById('perfil')?.value || '';

        if (!email || !senha || !perfil) {
            alert('Preencha email, senha e perfil.');
            return;
        }

        try {
            const resposta = await apiRequest('auth/processa_login.php', {
                method: 'POST',
                body: { email, senha, perfil }
            });

            // Salva somente a identidade retornada pelo servidor.
            salvarUsuarioLocal(resposta.dados.usuario);

            // O destino também vem do servidor e depende do perfil autenticado.
            window.location.replace(resposta.dados.destino);
        } catch (erro) {
            alert(erro.message || 'Não foi possível entrar.');
        }
    }, true);
})();
