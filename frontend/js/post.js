async function graphql(query, variables) {
    const res = await fetch(BACKEND_URL + '/graphql', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: query, variables: variables || null })
    });
    return res.json();
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

const postId = parseInt(new URLSearchParams(window.location.search).get('id'), 10);

async function loadCurrentUser() {
    const { data } = await apiGet('/auth/me');
    if (!data || !data.user) {
        window.location.href = 'login.html';
        return;
    }
    document.getElementById('welcome-name').textContent = data.user.displayName;
}

function renderPost(p) {
    const date = (p.createdAt || '').split(' ')[0];
    document.getElementById('post').innerHTML =
        '<div class="card shadow-sm">' +
            '<div class="card-body">' +
                '<h4 class="mb-1">' + escapeHtml(p.title) + '</h4>' +
                '<p class="text-muted small mb-3">by ' + escapeHtml(p.author.displayName) +
                    ' &middot; ' + escapeHtml(date) + '</p>' +
                '<p class="card-text" style="white-space: pre-line;">' + escapeHtml(p.body) + '</p>' +
            '</div>' +
        '</div>';
}

function renderComments(comments) {
    const box = document.getElementById('comments');
    if (!comments || comments.length === 0) {
        box.innerHTML = '<p class="text-muted small">No comments yet.</p>';
        return;
    }
    box.innerHTML = comments.map(function (c) {
        const date = (c.createdAt || '').split(' ')[0];
        return '' +
            '<div class="border-start ps-3 mb-3">' +
                '<p class="small text-muted mb-1">' +
                    escapeHtml(c.author.displayName) + ' &middot; ' + escapeHtml(date) +
                '</p>' +
                '<p class="mb-0">' + escapeHtml(c.body) + '</p>' +
            '</div>';
    }).join('');
}

async function loadPost() {
    const q = '{ post(id: ' + postId + ') { id title body createdAt ' +
              'author { displayName } ' +
              'comments { id body createdAt author { displayName } } } }';
    const result = await graphql(q);
    const p = result.data && result.data.post;
    if (!p) {
        document.getElementById('post').innerHTML =
            '<p class="text-danger">Post not found.</p>';
        return;
    }
    renderPost(p);
    renderComments(p.comments);
}

document.getElementById('comment-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorBox = document.getElementById('comment-error');
    errorBox.textContent = '';
    const body = document.getElementById('comment-body').value.trim();
    if (body === '') return;

    const result = await graphql(
        'mutation($pid: Int!, $b: String!) { createComment(postId: $pid, body: $b) { id } }',
        { pid: postId, b: body }
    );
    if (result.errors) {
        errorBox.textContent = result.errors[0].message;
        return;
    }
    document.getElementById('comment-body').value = '';
    loadPost();
});

document.getElementById('logout-btn').addEventListener('click', async function () {
    await apiPost('/auth/logout', {});
    window.location.href = 'login.html';
});

if (!postId) {
    document.getElementById('post').innerHTML =
        '<p class="text-danger">Missing post id.</p>';
} else {
    loadCurrentUser();
    loadPost();
}
