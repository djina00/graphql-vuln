let csrfToken = null;

function setCsrfToken(t) {
    csrfToken = t || null;
}

async function api(path, options) {
    options = options || {};
    const headers = Object.assign(
        { 'Content-Type': 'application/json' },
        options.headers || {}
    );
    if (csrfToken && options.method && options.method !== 'GET') {
        headers['X-CSRF-Token'] = csrfToken;
    }
    const res = await fetch(BACKEND_URL + path, {
        credentials: 'include',
        ...options,
        headers: headers
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

async function graphqlPost(query, variables) {
    const headers = { 'Content-Type': 'application/json' };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
    const res = await fetch(BACKEND_URL + '/graphql', {
        method: 'POST',
        credentials: 'include',
        headers: headers,
        body: JSON.stringify({ query: query, variables: variables || null })
    });
    return res.json();
}
