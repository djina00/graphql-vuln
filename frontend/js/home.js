async function graphql(query, variables) {
    const res = await fetch(BACKEND_URL + '/graphql', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query: query, variables: variables || null })
    });
    return res.json();
}

async function loadCurrentUser() {
    const { data } = await apiGet('/auth/me');
    if (!data || !data.user) {
        window.location.href = 'login.html';
        return;
    }
    document.getElementById('welcome-name').textContent = data.user.displayName;
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function renderFeed(posts) {
    const feed = document.getElementById('feed');
    if (!posts || posts.length === 0) {
        feed.innerHTML = '<p class="text-muted">No posts found.</p>';
        return;
    }
    feed.innerHTML = posts.map(function (p) {
        const snippet = p.body.length > 180 ? p.body.slice(0, 180) + '...' : p.body;
        const date = (p.createdAt || '').split(' ')[0];
        return '' +
            '<div class="card shadow-sm mb-3">' +
                '<div class="card-body">' +
                    '<h5 class="card-title">' +
                        '<a href="post.html?id=' + p.id + '" class="text-decoration-none">' + escapeHtml(p.title) + '</a>' +
                    '</h5>' +
                    '<p class="text-muted small mb-2">' +
                        'by ' + escapeHtml(p.author.displayName) + ' &middot; ' + escapeHtml(date) +
                    '</p>' +
                    '<p class="card-text">' + escapeHtml(snippet) + '</p>' +
                '</div>' +
            '</div>';
    }).join('');
}

async function loadFeed() {
    const result = await graphql('{ posts { id title body createdAt author { displayName } } }');
    renderFeed(result.data && result.data.posts);
}

async function search(keyword) {
    const result = await graphql(
        'query($k: String!) { searchPosts(keyword: $k) { id title body createdAt author { displayName } } }',
        { k: keyword }
    );
    renderFeed(result.data && result.data.searchPosts);
}

document.getElementById('search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    const kw = document.getElementById('search-input').value.trim();
    if (kw === '') {
        loadFeed();
    } else {
        search(kw);
    }
});

document.getElementById('clear-search').addEventListener('click', function () {
    document.getElementById('search-input').value = '';
    loadFeed();
});

document.getElementById('logout-btn').addEventListener('click', async function () {
    await apiPost('/auth/logout', {});
    window.location.href = 'login.html';
});

loadCurrentUser();
loadFeed();
