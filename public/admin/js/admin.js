window.Admin = (function () {
    function token() { return localStorage.getItem('lm.admin.token'); }
    async function req(path, opts = {}) {
        const headers = Object.assign({}, opts.headers || {});
        let body = opts.body;
        if (body && typeof body === 'object' && !(body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(body);
        }
        headers['Authorization'] = 'Bearer ' + token();
        const r = await fetch(path, { method: opts.method || 'GET', headers, body });
        const j = await r.json().catch(() => ({}));
        if (r.status === 401) { localStorage.removeItem('lm.admin.token'); location.href='/admin/login.html'; return; }
        if (!j.ok) throw new Error(j.error || ('HTTP ' + r.status));
        return j.data;
    }
    return {
        get:  p => req(p),
        post: (p, b) => req(p, { method: 'POST', body: b }),
        del:  p => req(p, { method: 'DELETE' }),
    };
})();

window.adminIcons = {
    index:       `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>`,
    products:    `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="m21 16-9 5-9-5V8l9-5 9 5v8z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>`,
    periods:     `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>`,
    users:       `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>`,
    payments:    `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 11h20M7 15h2"/></svg>`,
    withdrawals: `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M12 2v14m-5-5 5 5 5-5"/><path d="M5 21h14"/></svg>`,
    winners:     `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4z"/><path d="M5 6H3a2 2 0 0 0 2 4M19 6h2a2 2 0 0 1-2 4"/></svg>`,
    ranks:       `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M6 9l6-6 6 6"/><path d="M6 14l6-6 6 6"/><path d="M6 19l6-6 6 6"/></svg>`,
    settings:    `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>`,
    brand:       `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><circle cx="9" cy="10" r="1.5"/><path d="m21 15-5-5-5 5"/></svg>`,
    proofs:      `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>`,
    shares:      `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>`,
    logout:      `<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>`,
};

window.sidebar = function (active) {
    // [label, key, href?] — href defaults to /admin/<key>.html
    const items = [
        ['Dashboard',   'index'],
        ['Products',    'products'],
        ['Periods',     'periods'],
        ['Users',       'users'],
        ['Payments',    'payments'],
        ['Withdrawals', 'withdrawals'],
        ['Winners',     'winners'],
        ['Proofs',      'proofs'],
        ['Shares',      'shares'],
        ['Ranks',       'ranks'],
        ['Settings',    'settings'],
        ['Site Logo',   'brand',       '/h5/upload-logo.html'],
    ];
    const logoMark = `<svg viewBox="0 0 40 40" width="20" height="20" fill="currentColor" style="display:block;"><path d="M8 17 L11 11 L14.5 15.5 L20 8 L25.5 15.5 L29 11 L32 17 Z"/><rect x="8" y="17" width="24" height="2.5"/><path d="M14 24 L21.5 20 L21.5 32 L27 32 L27 34.5 L13 34.5 L13 32 L17.5 32 L17.5 25 L14 26.5 Z"/></svg>`;
    return `<aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">${logoMark}</div>
            <div class="brand-text">
                JackOne
                <div class="sub">ADMIN PANEL</div>
            </div>
        </div>
        <nav>
            ${items.map(([n, k, href]) =>
                `<a class="${active === k ? 'active' : ''}" href="${href || ('/admin/' + k + '.html')}">${adminIcons[k]}<span>${n}</span></a>`
            ).join('')}
            <a class="logout" href="javascript:(()=>{localStorage.removeItem('lm.admin.token');location.href='/admin/login.html';})()">${adminIcons.logout}<span>Logout</span></a>
        </nav>
    </aside>`;
};

// Helper for status pills used in tables
window.pill = function (kind, label) {
    return `<span class="pill pill-${kind}">${label}</span>`;
};
