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
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

let myId = null;

async function loadCurrentUser() {
    const { data } = await apiGet('/auth/me');
    if (!data || !data.user) {
        window.location.href = 'login.html';
        return null;
    }
    document.getElementById('welcome-name').textContent = data.user.displayName;
    myId = data.user.id;
    return myId;
}

function renderMessages(messages) {
    const box = document.getElementById('messages');
    if (!messages || messages.length === 0) {
        box.innerHTML = '<p class="text-muted small">No messages.</p>';
        return;
    }
    box.innerHTML = messages.map(function (m) {
        const incoming = m.recipient.id === myId;
        const other = incoming ? m.sender : m.recipient;
        const direction = incoming ? 'from' : 'to';
        const badge = incoming ? 'bg-secondary' : 'bg-primary';
        const date = (m.createdAt || '').split(' ')[0];
        return '' +
            '<div class="card shadow-sm mb-2">' +
                '<div class="card-body py-2">' +
                    '<div class="d-flex justify-content-between align-items-center mb-1">' +
                        '<span class="small">' +
                            '<span class="badge ' + badge + ' me-2">' + direction + '</span>' +
                            escapeHtml(other.displayName) +
                        '</span>' +
                        '<span class="text-muted small">' + escapeHtml(date) + '</span>' +
                    '</div>' +
                    '<p class="mb-0">' + escapeHtml(m.body) + '</p>' +
                '</div>' +
            '</div>';
    }).join('');
}

async function loadMessages() {
    const result = await graphql(
        'query($id: Int!) { user(id: $id) { messages { id body createdAt ' +
        'sender { id displayName } recipient { id displayName } } } }',
        { id: myId }
    );
    const u = result.data && result.data.user;
    renderMessages(u && u.messages);
}

document.getElementById('send-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorBox = document.getElementById('send-error');
    errorBox.textContent = '';

    const recipientId = parseInt(document.getElementById('recipient-id').value, 10);
    const body = document.getElementById('message-body').value.trim();
    if (!recipientId || body === '') return;

    const result = await graphql(
        'mutation($r: Int!, $b: String!) { sendMessage(recipientId: $r, body: $b) { id } }',
        { r: recipientId, b: body }
    );
    if (result.errors) {
        errorBox.textContent = result.errors[0].message;
        return;
    }
    document.getElementById('message-body').value = '';
    loadMessages();
});

document.getElementById('logout-btn').addEventListener('click', async function () {
    await apiPost('/auth/logout', {});
    window.location.href = 'login.html';
});

async function init() {
    const id = await loadCurrentUser();
    if (id === null) return;
    loadMessages();
}

init();
