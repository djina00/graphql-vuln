async function init() {
    const { data } = await apiGet('/auth/me');
    if (!data || !data.user) {
        window.location.href = 'login.html';
        return;
    }
    document.getElementById('welcome-name').textContent = data.user.displayName;
}

document.getElementById('logout-btn').addEventListener('click', async function () {
    await apiPost('/auth/logout', {});
    window.location.href = 'login.html';
});

init();
