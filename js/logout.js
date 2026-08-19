async function sairValistoque(event) {
    if (event) event.preventDefault();
    try { await apiRequest('auth/logout.php', { method: 'POST' }); } catch (_) {}
    localStorage.removeItem('valistoqueUsuario');
    sessionStorage.removeItem('valistoqueRecoveryEmail');
    window.location.href = 'Principal.html';
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-logout]').forEach(el => el.addEventListener('click', sairValistoque));
});
