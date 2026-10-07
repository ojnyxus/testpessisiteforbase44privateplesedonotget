/* BlockForge Hub — progressive enhancement: filtering, bookmarks, copy, toasts. */
(() => {
    const csrf = document.body.dataset.csrf || '';
    const userId = parseInt(document.body.dataset.user || '0', 10);
    const toastHost = document.getElementById('toasts');

    const ICON = {
        bookmark: '<svg class="icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-6-5-6 5z"/></svg>',
        download: '<svg class="icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16"/></svg>',
        lock: '<svg class="icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 11h12v9H6zM9 11V8a3 3 0 016 0v3"/></svg>'
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    function toast(message, tone = 'ok') {
        if (!toastHost) return;
        const el = document.createElement('div');
        el.className = 'toast ' + tone;
        el.setAttribute('data-toast', '');
        el.textContent = message;
        toastHost.appendChild(el);
        setTimeout(() => el.remove(), 4800);
    }
    window.blockforgeToast = toast;

    document.querySelectorAll('[data-toast]').forEach((el) => setTimeout(() => el.remove(), 4800));

    async function post(path, payload) {
        const response = await fetch(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(payload)
        });
        try {
            return await response.json();
        } catch (error) {
            return { ok: false, error: 'Unexpected server response.' };
        }
    }

    /* ------------------------------------------------------------ cards */

    function card(item) {
        const premium = Number(item.is_premium) === 1;
        const needsUnlock = premium && !item.unlocked;
        const action = needsUnlock
            ? `<a class="btn btn-gold btn-sm grow" href="/asset/${esc(item.slug)}">${ICON.lock} Unlock $${esc(item.price)}</a>`
            : `<a class="btn btn-primary btn-sm grow" href="/download/${Number(item.id)}">${ICON.download} Download</a>`;

        return `<article class="asset-card${premium ? ' is-premium' : ''}">
            <a class="asset-thumb" href="/asset/${esc(item.slug)}">
                <img src="${esc(item.thumb)}" alt="" loading="lazy" width="640" height="360">
                ${premium ? '<span class="badge badge-gold">Premium</span>' : ''}
                <span class="badge badge-impact impact-${esc(item.impact)}">${esc(item.impact_label)}</span>
            </a>
            <div class="asset-body">
                <div class="chip-row">
                    <span class="chip">${esc(item.category_label)}</span>
                    <span class="chip chip-ghost">${esc(item.mc_version)}</span>
                </div>
                <h3 class="asset-title"><a href="/asset/${esc(item.slug)}">${esc(item.name)}</a></h3>
                <p class="asset-summary">${esc(item.summary)}</p>
                <div class="asset-meta">
                    <span>by ${esc(item.author)}</span><span class="dot"></span>
                    <span>${esc(item.rating)} ★</span><span class="dot"></span>
                    <span>${esc(item.downloads_label)} dl</span>
                </div>
                <div class="asset-actions">
                    ${action}
                    <button class="btn btn-ghost btn-sm icon-btn${item.bookmarked ? ' is-on' : ''}"
                            data-bookmark="${Number(item.id)}" aria-pressed="${item.bookmarked ? 'true' : 'false'}"
                            title="${item.bookmarked ? 'Remove bookmark' : 'Bookmark this'}">${ICON.bookmark}</button>
                </div>
            </div>
        </article>`;
    }

    /* ---------------------------------------------------------- filters */

    const form = document.getElementById('filter-form');
    const results = document.getElementById('results');
    const countLabel = document.getElementById('result-count');

    if (form && results) {
        let premium = form.dataset.premium ?? '';
        let timer = null;

        const load = async () => {
            const params = new URLSearchParams(new FormData(form));
            params.set('section', form.dataset.section);
            if (premium !== '') params.set('premium', premium);
            else params.delete('premium');

            results.style.opacity = '.45';
            try {
                const response = await fetch('/api/assets?' + params.toString());
                const data = await response.json();
                if (!data.items.length) {
                    results.innerHTML = '<p class="empty-state">Nothing matches those filters yet. Try widening them.</p>';
                } else {
                    results.innerHTML = '<div class="asset-grid">' + data.items.map(card).join('') + '</div>';
                }
                if (countLabel) countLabel.textContent = data.total + (data.total === 1 ? ' result' : ' results');
                const url = new URL(window.location.href);
                ['search', 'version', 'category', 'impact', 'sort'].forEach((key) => {
                    const value = params.get(key);
                    if (value) url.searchParams.set(key, value); else url.searchParams.delete(key);
                });
                window.history.replaceState({}, '', url);
            } finally {
                results.style.opacity = '1';
            }
        };

        form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
        form.querySelectorAll('select').forEach((select) => select.addEventListener('change', load));

        const search = form.querySelector('input[name="search"]');
        if (search) {
            search.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(load, 320);
            });
        }

        const tabs = document.querySelectorAll('[data-tab]');
        tabs.forEach((tab) => tab.addEventListener('click', () => {
            tabs.forEach((other) => other.classList.remove('is-active', 'gold'));
            tab.classList.add('is-active');
            if (tab.dataset.tab === 'premium') tab.classList.add('gold');
            premium = tab.dataset.tab === 'premium' ? '1' : '0';
            const heading = document.getElementById('tab-blurb');
            if (heading) {
                heading.textContent = premium === '1'
                    ? 'Hand-tuned, production-ready configs and assets from vetted creators — unlocked with crypto.'
                    : 'Free, community-shared configurations and data packs. Install and go.';
            }
            load();
        }));
    }

    /* -------------------------------------------------------- bookmarks */

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-bookmark]');
        if (!button) return;
        event.preventDefault();

        if (!userId) {
            toast('Sign in to keep a personal hub of bookmarks.', 'gold');
            return;
        }

        const result = await post('/api/bookmark', { asset_id: parseInt(button.dataset.bookmark, 10) });
        if (result.ok) {
            button.classList.toggle('is-on', result.bookmarked);
            button.setAttribute('aria-pressed', result.bookmarked ? 'true' : 'false');
            toast(result.bookmarked ? 'Saved to your hub.' : 'Removed from your hub.', result.bookmarked ? 'ok' : 'gold');
        } else {
            toast(result.error || 'Could not save that.', 'bad');
        }
    });

    /* ------------------------------------------------------------ copy */

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy]');
        if (!button) return;
        event.preventDefault();
        const value = button.dataset.copy;
        try {
            await navigator.clipboard.writeText(value);
        } catch (error) {
            const helper = document.createElement('textarea');
            helper.value = value;
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            helper.remove();
        }
        toast('Copied to clipboard.', 'ok');
    });

    /* ------------------------------------------------- coin / tip input */

    document.querySelectorAll('.coin-option').forEach((option) => {
        const input = option.querySelector('input');
        if (!input) return;
        if (input.checked) option.classList.add('is-selected');
        option.addEventListener('click', () => {
            option.closest('.coin-picker')?.querySelectorAll('.coin-option').forEach((other) => other.classList.remove('is-selected'));
            option.classList.add('is-selected');
            input.checked = true;
        });
    });

    document.querySelectorAll('[data-amount]').forEach((chip) => chip.addEventListener('click', () => {
        const target = document.getElementById('amount-input');
        if (!target) return;
        document.querySelectorAll('[data-amount]').forEach((other) => other.classList.remove('is-active'));
        chip.classList.add('is-active');
        target.value = chip.dataset.amount;
    }));

    /* --------------------------------------------------- mobile nav etc */

    const toggle = document.getElementById('nav-toggle');
    const nav = document.getElementById('main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.querySelectorAll('[data-count]').forEach((el) => {
        const target = parseFloat(el.dataset.count);
        const decimals = el.dataset.decimals ? parseInt(el.dataset.decimals, 10) : 0;
        const duration = 900;
        const start = performance.now();
        const step = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = (target * eased).toLocaleString(undefined, {
                minimumFractionDigits: decimals, maximumFractionDigits: decimals
            });
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });

    /* -------------------------------------------------- payment polling */

    const statusBox = document.getElementById('pay-status');
    if (statusBox) {
        const id = statusBox.dataset.id;
        const current = statusBox.dataset.status;
        setInterval(async () => {
            try {
                const response = await fetch('/api/pay/status?id=' + encodeURIComponent(id));
                const data = await response.json();
                if (data.ok && data.status !== current) window.location.reload();
            } catch (error) { /* offline: keep showing the current state */ }
        }, 10000);
    }
})();
