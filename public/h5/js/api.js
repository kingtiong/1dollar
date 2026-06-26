// Tiny HTTP wrapper
window.API = (function () {
    const TOKEN_KEY = 'lm.token';
    function token(t) {
        if (t === null) localStorage.removeItem(TOKEN_KEY);
        else if (t !== undefined) localStorage.setItem(TOKEN_KEY, t);
        return localStorage.getItem(TOKEN_KEY);
    }
    async function req(path, opts = {}) {
        const headers = Object.assign({}, opts.headers || {});
        let body = opts.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }
        const tk = token();
        if (tk) headers['Authorization'] = 'Bearer ' + tk;
        const r = await fetch(path, { method: opts.method || 'GET', headers, body, credentials: 'include' });
        const j = await r.json().catch(() => ({}));
        if (!j.ok) throw new Error(j.error || ('HTTP ' + r.status));
        return j.data;
    }
    return {
        get:  (p)         => req(p),
        post: (p, b)      => req(p, { method: 'POST', body: b }),
        del:  (p)         => req(p, { method: 'DELETE' }),
        token,
    };
})();

window.toast = function (msg) {
    let el = document.getElementById('lm-toast');
    if (!el) {
        el = document.createElement('div');
        el.id = 'lm-toast'; el.className = 'toast';
        document.body.appendChild(el);
    }
    el.textContent = msg; el.classList.add('show');
    clearTimeout(window.__toastT);
    window.__toastT = setTimeout(() => el.classList.remove('show'), 2200);
};
