function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

let currentUserId = null;

async function loadCurrentUser() {
    const { data } = await apiGet('/auth/me');
    if (!data || !data.user) {
        window.location.href = 'login.html';
        return null;
    }
    document.getElementById('welcome-name').textContent = data.user.displayName;
    setCsrfToken(data.csrfToken);
    currentUserId = data.user.id;
    return data.user.id;
}

function renderProfile(u) {
    if (!u) {
        document.getElementById('profile').innerHTML =
            '<p class="text-danger">User not found.</p>';
        document.getElementById('drafts').innerHTML = '';
        return;
    }
    document.getElementById('profile').innerHTML =
        '<div class="card shadow-sm">' +
            '<div class="card-body">' +
                '<h4 class="mb-1">' + escapeHtml(u.displayName) + '</h4>' +
                '<p class="text-muted small mb-2">' + escapeHtml(u.email) + '</p>' +
                '<p class="mb-0">' + escapeHtml(u.bio) + '</p>' +
            '</div>' +
        '</div>';

    renderDrafts(u.drafts);
}

function renderDrafts(drafts) {
    const box = document.getElementById('drafts');
    if (!drafts || drafts.length === 0) {
        box.innerHTML = '<p class="text-muted small">No drafts.</p>';
        return;
    }
    box.innerHTML = drafts.map(function (d) {
        const snippet = d.body.length > 160 ? d.body.slice(0, 160) + '...' : d.body;
        const date = (d.createdAt || '').split(' ')[0];
        return '' +
            '<div class="card shadow-sm mb-2">' +
                '<div class="card-body">' +
                    '<h6 class="mb-1">' + escapeHtml(d.title) + '</h6>' +
                    '<p class="text-muted small mb-2">' + escapeHtml(date) + '</p>' +
                    '<p class="mb-0">' + escapeHtml(snippet) + '</p>' +
                '</div>' +
            '</div>';
    }).join('');
}

async function loadProfile(id) {
    const result = await graphqlPost(
        'query($id: Int!) { user(id: $id) { id displayName email bio drafts { id title body createdAt } } }',
        { id: id }
    );
    renderProfile(result.data && result.data.user);
}

document.getElementById('lookup-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const id = parseInt(document.getElementById('lookup-id').value, 10);
    if (!id) return;
    loadProfile(id);
});

document.getElementById('logout-btn').addEventListener('click', async function () {
    await apiPost('/auth/logout', {});
    window.location.href = 'login.html';
});

async function init() {
    const id = await loadCurrentUser();
    if (id === null) return;
    const params = new URLSearchParams(window.location.search);
    const target = parseInt(params.get('id'), 10) || id;
    loadProfile(target);
}

init();
