const VALISTOQUE_API_BASE = `${window.location.origin}/valistoque_backend_corrigido`;

async function apiRequest(path, options = {}) {
    const config = { credentials: 'include', ...options, headers: { ...(options.headers || {}) } };
    if (config.body && typeof config.body !== 'string' && !(config.body instanceof FormData)) {
        config.headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(config.body);
    }

    const response = await fetch(`${VALISTOQUE_API_BASE}/${path.replace(/^\//, '')}`, config);
    const text = await response.text();
    let data = null;
    try { data = text ? JSON.parse(text) : null; } catch (_) {}
    if (!response.ok || !data?.sucesso) {
        const message = data?.mensagem || `Erro HTTP ${response.status}`;
        const error = new Error(message);
        error.response = response;
        error.data = data;
        throw error;
    }
    return data;
}

function salvarUsuarioLocal(usuario) {
    if (usuario) localStorage.setItem('valistoqueUsuario', JSON.stringify(usuario));
}

function obterUsuarioLocal() {
    try { return JSON.parse(localStorage.getItem('valistoqueUsuario') || '{}'); } catch (_) { return {}; }
}
