const form = document.getElementById('login-form');
const errorBox = document.getElementById('login-error');

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    errorBox.textContent = '';

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    const { ok, data } = await apiPost('/auth/login', { email, password });
    if (!ok) {
        errorBox.textContent = (data && data.error) || 'Login failed.';
        return;
    }
    setCsrfToken(data.csrfToken);
    window.location.href = 'index.html';
});
