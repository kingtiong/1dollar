// Shared top bar + tab bar — injected on every page
window.Layout = (function () {
    // Cached URLs from localStorage — lets us render <img> synchronously on every page
    // load after the very first one, so there's no inline-SVG-then-swap flash.
    let siteLogoUrl = null;
    let siteFavUrl  = null;
    try {
        siteLogoUrl = localStorage.getItem('lm.site_logo_url') || null;
        siteFavUrl  = localStorage.getItem('lm.site_fav_url')  || null;
    } catch (e) {}

    // Background refresh: pick up any new upload + cache it for next time.
    const siteLogoReady = fetch('/h5/img/site-logo.json?v=' + Date.now(), { cache: 'no-store' })
        .then(r => r.ok ? r.json() : null)
        .then(j => {
            if (j && j.url) {
                const newFav = j.fav_url || j.url;
                let changed = false;
                if (j.url !== siteLogoUrl) {
                    siteLogoUrl = j.url;
                    try { localStorage.setItem('lm.site_logo_url', j.url); } catch (e) {}
                    changed = true;
                }
                if (newFav !== siteFavUrl) {
                    siteFavUrl = newFav;
                    try { localStorage.setItem('lm.site_fav_url', newFav); } catch (e) {}
                    changed = true;
                }
                if (changed) applySiteLogo();
            } else if (siteLogoUrl) {
                // Server has no logo anymore — drop cache so next reload falls back to SVG.
                siteLogoUrl = null;
                siteFavUrl  = null;
                try {
                    localStorage.removeItem('lm.site_logo_url');
                    localStorage.removeItem('lm.site_fav_url');
                } catch (e) {}
            }
        })
        .catch(() => { /* offline / first run with no uploaded logo — keep inline SVG */ });

    function siteLogoImg() {
        return '<img src="' + siteLogoUrl + '" alt="JackOne" loading="eager" fetchpriority="high"' +
               ' style="width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block;">';
    }
    function applySiteLogo() {
        if (!siteLogoUrl) return;
        document.querySelectorAll('.topbar .logo, .tab-center').forEach(el => {
            el.innerHTML = siteLogoImg();
        });
        applyFavicon();
    }

    // Point all <link rel="icon"> / shortcut icon at the uploaded logo so the
    // browser tab matches what's in the topbar. Prefers the favicon-sized PNG
    // (site-logo-fav.png) provided by the upload endpoint; falls back to the
    // primary logo URL when the sidecar doesn't carry fav_url (e.g. SVG uploads).
    function applyFavicon() {
        const href = siteFavUrl || siteLogoUrl;
        if (!href) return;
        const type = /\.svg(\?|$)/i.test(href) ? 'image/svg+xml' : 'image/png';
        const replaceOrAdd = (rel) => {
            let el = document.querySelector('link[rel="' + rel + '"]');
            if (!el) {
                el = document.createElement('link');
                el.rel = rel;
                document.head.append(el);
            }
            el.type = type;
            el.href = href;
        };
        replaceOrAdd('icon');
        replaceOrAdd('shortcut icon');
        replaceOrAdd('apple-touch-icon');
    }

    function ensureFont() {
        if (document.getElementById('lm-font')) return;
        const l1 = document.createElement('link');
        l1.rel = 'preconnect'; l1.href = 'https://fonts.googleapis.com';
        const l2 = document.createElement('link');
        l2.rel = 'preconnect'; l2.href = 'https://fonts.gstatic.com'; l2.crossOrigin = 'anonymous';
        const l3 = document.createElement('link');
        l3.id = 'lm-font'; l3.rel = 'stylesheet';
        l3.href = 'https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800;900&family=Playfair+Display:wght@700;800;900&family=Noto+Serif+SC:wght@700;900&display=swap';
        document.head.append(l1, l2, l3);

        if (!document.querySelector('link[rel="icon"]')) {
            const fav = document.createElement('link');
            fav.rel = 'icon'; fav.type = 'image/svg+xml'; fav.href = '/favicon.svg';
            document.head.append(fav);
        }
    }

    function logoSvg() {
        // If we have a cached uploaded logo URL, render <img> directly — no flash.
        if (siteLogoUrl) return siteLogoImg();
        // Fallback: JackOne mark — chunky numeral "1" wearing a 3-peak crown.
        return `<svg viewBox="0 0 40 40" width="22" height="22" fill="currentColor" style="display:block;">
            <path d="M8 17 L11 11 L14.5 15.5 L20 8 L25.5 15.5 L29 11 L32 17 Z"/>
            <rect x="8" y="17" width="24" height="2.5"/>
            <path d="M14 24 L21.5 20 L21.5 32 L27 32 L27 34.5 L13 34.5 L13 32 L17.5 32 L17.5 25 L14 26.5 Z"/>
        </svg>`;
    }

    function topbar() {
        return `
        <div class="topbar" id="lmTopbar">
            <a class="brand-row" href="/h5/index.html" title="JackOne">
                <div class="logo">${logoSvg()}</div>
                <div class="brand">
                    <div class="wordmark">JackOne</div>
                </div>
            </a>
            <div class="search">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="searchInput" data-i18n-ph="search" />
            </div>
            <div class="lang-wrap">
                <button class="lang" id="langBtn" aria-haspopup="true" aria-expanded="false">${I18N.label(I18N.get(), 'short')}</button>
                <div class="lang-menu" id="langMenu" role="menu" hidden>
                    ${I18N.supported().map(code => `
                        <button type="button" role="menuitem" data-lang="${code}"
                                class="lang-opt${code === I18N.get() ? ' is-active' : ''}">
                            <span class="lang-opt-short">${I18N.label(code, 'short')}</span>
                            <span class="lang-opt-long">${I18N.label(code, 'long')}</span>
                        </button>
                    `).join('')}
                </div>
            </div>
        </div>`;
    }

    function ticker() {
        // Fake-but-believable live feed; gets replaced by real data if API supports it later
        const lang = I18N.get();
        const templates = {
            zh: (n, p, s) => `<b>${n}</b> 参与 <b>第${p}期</b> · ${s} 注`,
            en: (n, p, s) => `<b>${n}</b> joined <b>#${p}</b> · ${s} slots`,
            si: (n, p, s) => `<b>${n}</b> සහභාගි විය <b>අංක ${p}</b> · ස්ලට් ${s}`,
            bn: (n, p, s) => `<b>${n}</b> যোগ দিয়েছেন <b>#${p}</b> · ${s} স্লট`,
        };
        const tpl = templates[lang] || templates.en;
        const names = ['Sarah', 'Aaron', 'Lucy', 'Miguel', 'Priya', 'Jin', 'Hassan', 'Emma', 'Noah', 'Kai'];
        const items = Array.from({ length: 12 }, () => {
            const n = names[Math.floor(Math.random() * names.length)];
            const period = 130 + Math.floor(Math.random() * 20);
            const slots = 1 + Math.floor(Math.random() * 25);
            return `<span class="ticker-item">${tpl(n, period, slots)}</span>`;
        });
        // Duplicate for seamless loop
        const track = items.concat(items).join('');
        return `<div class="ticker"><div class="ticker-track">${track}</div></div>`;
    }

    function tabbar(active) {
        const T = (k) => I18N.t(k);
        const item = (key, href, icon, name) => `
            <a class="tab-item ${active === key ? 'active' : ''}" href="${href}">
                ${icon}<span>${T(name)}</span>
            </a>`;
        const ic = {
            home: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>`,
            bag: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12l-1 13H7L6 7z"/><path d="M9 7a3 3 0 1 1 6 0"/></svg>`,
            trophy: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4z"/><path d="M5 6H3a2 2 0 0 0 2 4M19 6h2a2 2 0 0 1-2 4"/></svg>`,
            user: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg>`,
        };
        return `
        <nav class="tabbar">
            ${item('home',    '/h5/index.html',    ic.home,   'home')}
            ${item('all',     '/h5/products.html', ic.bag,    'all')}
            <a class="tab-item" href="/h5/index.html" style="flex:0 0 auto;">
                <div class="tab-center">${logoSvg()}</div>
            </a>
            ${item('reveals', '/h5/reveals.html',  ic.trophy, 'reveals')}
            ${item('me',      '/h5/me.html',       ic.user,   'me')}
        </nav>`;
    }

    function wireScrollTopbar() {
        const bar = document.getElementById('lmTopbar');
        if (!bar) return;
        const onScroll = () => {
            if (window.scrollY > 8) bar.classList.add('is-scrolled');
            else bar.classList.remove('is-scrolled');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    function supportBubble() {
        return `
        <button id="lmSupportBtn" class="support-bubble" type="button"
                title="" aria-label="Support" onclick="openSupport()">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
        </button>`;
    }

    // Cache the support URL so the bubble can open instantly. Refresh in background.
    let supportUrl = null;
    try { supportUrl = localStorage.getItem('lm.support_url') || null; } catch (e) {}
    function refreshSupportUrl() {
        fetch('/api/settings', { cache: 'no-store' })
            .then(r => r.ok ? r.json() : null)
            .then(j => {
                const url = (j && j.ok && j.data && j.data.support_url) ? String(j.data.support_url).trim() : '';
                supportUrl = url || null;
                try {
                    if (supportUrl) localStorage.setItem('lm.support_url', supportUrl);
                    else localStorage.removeItem('lm.support_url');
                } catch (e) {}
            })
            .catch(() => {});
    }
    window.openSupport = function () {
        if (supportUrl) {
            window.open(supportUrl, '_blank', 'noopener');
        } else {
            window.toast && window.toast(I18N.t('support_unavailable'));
        }
    };

    function mount(active, opts) {
        ensureFont();
        opts = opts || {};
        document.body.insertAdjacentHTML('afterbegin', topbar());
        if (opts.ticker !== false && active === 'home') {
            document.getElementById('lmTopbar')
                .insertAdjacentHTML('afterend', ticker());
        }
        document.body.insertAdjacentHTML('beforeend', tabbar(active));
        if (opts.supportBubble !== false) {
            document.body.insertAdjacentHTML('beforeend', supportBubble());
            refreshSupportUrl();
        }
        const langBtn = document.getElementById('langBtn');
        const langMenu = document.getElementById('langMenu');
        const closeLangMenu = () => {
            langMenu.hidden = true;
            langBtn.setAttribute('aria-expanded', 'false');
        };
        langBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = !langMenu.hidden;
            if (open) { closeLangMenu(); return; }
            langMenu.hidden = false;
            langBtn.setAttribute('aria-expanded', 'true');
        });
        langMenu.querySelectorAll('[data-lang]').forEach(opt => {
            opt.addEventListener('click', () => {
                const next = opt.getAttribute('data-lang');
                if (next === I18N.get()) { closeLangMenu(); return; }
                I18N.set(next);
                location.reload();
            });
        });
        document.addEventListener('click', (e) => {
            if (langMenu.hidden) return;
            if (!e.target.closest('.lang-wrap')) closeLangMenu();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !langMenu.hidden) closeLangMenu();
        });
        const si = document.getElementById('searchInput');
        si.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                location.href = '/h5/products.html?q=' + encodeURIComponent(si.value);
            }
        });
        I18N.apply();
        wireScrollTopbar();
        // Swap inline crown SVG for uploaded logo if available.
        if (siteLogoUrl) applySiteLogo();
        else siteLogoReady.then(applySiteLogo);
    }
    return { mount };
})();

window.fmtMoney = function (v) {
    return '$' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
};
window.pct = function (a, b) {
    if (!b) return 0;
    return Math.min(100, Math.round(a * 100 / b));
};

/* ============================================================
   Effects: countdown, count-up, confetti
   ============================================================ */
window.LM = window.LM || {};

LM.countdown = function (deadlineMs) {
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
        const diff = Math.max(0, deadlineMs - Date.now());
        const s = Math.floor(diff / 1000);
        return {
            done: s === 0,
            h: pad(Math.floor(s / 3600)),
            m: pad(Math.floor((s % 3600) / 60)),
            s: pad(s % 60),
        };
    };
    return tick;
};

LM.bindCountdowns = function () {
    const els = document.querySelectorAll('[data-countdown-deadline]');
    if (!els.length) return;
    const run = () => {
        els.forEach((el) => {
            const dl = parseInt(el.dataset.countdownDeadline, 10);
            if (!dl) return;
            const c = LM.countdown(dl)();
            el.textContent = c.done ? '—' : `${c.h}:${c.m}:${c.s}`;
        });
    };
    run();
    setInterval(run, 1000);
};

LM.countUp = function (el, target, duration) {
    duration = duration || 800;
    const start = performance.now();
    const from = parseInt(el.textContent.replace(/[^\d-]/g, ''), 10) || 0;
    const step = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = Math.round(from + (target - from) * eased).toLocaleString();
        if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
};

LM.confetti = function (host, count) {
    count = count || 60;
    const colors = ['#d4af37', '#f4cf6b', '#ff3b3b', '#ff9800', '#fff'];
    for (let i = 0; i < count; i++) {
        const c = document.createElement('div');
        c.className = 'confetti';
        c.style.left = (Math.random() * 100) + '%';
        c.style.background = colors[i % colors.length];
        c.style.animationDelay = (Math.random() * 1.2) + 's';
        c.style.animationDuration = (2.5 + Math.random() * 1.5) + 's';
        c.style.transform = `rotate(${Math.random() * 360}deg)`;
        host.appendChild(c);
    }
};

window.toast = function (msg) {
    let t = document.querySelector('.toast');
    if (!t) {
        t = document.createElement('div');
        t.className = 'toast';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._tm);
    t._tm = setTimeout(() => t.classList.remove('show'), 2200);
};
