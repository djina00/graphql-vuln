const form = document.getElementById('register-form');
const errorBox = document.getElementById('register-error');

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    errorBox.textContent = '';

    const displayName = document.getElementById('displayName').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    const { ok, data } = await apiPost('/auth/register', { email, password, displayName });
    if (!ok) {
        errorBox.textContent = (data && data.error) || 'Registration failed.';
        return;
    }
    window.location.href = 'index.html';
});
