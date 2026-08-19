(function () {
    const form = document.getElementById('recovery-form');
    if (!form) return;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const email = form.querySelector('input[type="email"]').value.trim();
        const cpf = document.getElementById('cpf').value.trim();
        try {
            const resposta = await apiRequest('auth/processa_recuperacao.php', {
                method: 'POST',
                body: { email, cpf }
            });
            alert(`Código de recuperação: ${resposta.dados.codigo}\nExpira em: ${resposta.dados.expira_em}`);
            sessionStorage.setItem('valistoqueRecoveryEmail', email);
            window.location.href = 'redefinirsenha.html';
        } catch (erro) {
            alert(erro.message);
        }
    }, { once: true, capture: true });
})();
