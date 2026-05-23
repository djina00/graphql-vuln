async function api(path, options) {
    options = options || {};
    const res = await fetch(BACKEND_URL + path, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        ...options
    });
    let data = null;
    try { data = await res.json(); } catch (e) {}
    return { ok: res.ok, status: res.status, data: data };
}

function apiGet(path) {
    return api(path, { method: 'GET' });
}

function apiPost(path, body) {
    return api(path, { method: 'POST', body: JSON.stringify(body || {}) });
}
