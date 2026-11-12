/* Sling CRM web panel helpers (vanilla JS, Bootstrap 5) */
(function () {
  'use strict';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const t = (key) => (window.CRM_I18N && window.CRM_I18N[key]) || key;

  // Confirm before destructive submits: <form data-confirm="Are you sure?">
  document.addEventListener('submit', (e) => {
    const msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Auto-submit filter selects: <select data-autosubmit>
  document.querySelectorAll('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

  // Select-all checkbox for bulk export: <input type="checkbox" data-check-all="ids">
  document.querySelectorAll('[data-check-all]').forEach((all) => {
    const name = all.getAttribute('data-check-all');
    const boxes = () => document.querySelectorAll(`input[type=checkbox][data-row-check="${name}"]`);
    const sync = () => {
      const n = [...boxes()].filter((b) => b.checked).length;
      document.querySelectorAll(`[data-selected-count="${name}"]`).forEach((el) => { el.textContent = n; });
      document.querySelectorAll(`[data-needs-selection="${name}"]`).forEach((el) => el.classList.toggle('disabled', n === 0));
    };
    all.addEventListener('change', () => { boxes().forEach((b) => { b.checked = all.checked; }); sync(); });
    boxes().forEach((b) => b.addEventListener('change', sync));
    sync();
  });

  // Export selected rows: <a data-export-selected="ids" href="...export/csv">
  document.querySelectorAll('[data-export-selected]').forEach((a) => a.addEventListener('click', (e) => {
    const name = a.getAttribute('data-export-selected');
    const ids = [...document.querySelectorAll(`input[data-row-check="${name}"]:checked`)].map((b) => b.value);
    if (!ids.length) { e.preventDefault(); return; }
    const url = new URL(a.href);
    ids.forEach((id) => url.searchParams.append('ids[]', id));
    a.href = url.toString();
  }));

  // District → town suggestions: <select data-district-for="city-list"> + <input list="city-list">
  const districts = window.CRM_DISTRICTS || {};
  document.querySelectorAll('[data-district-for]').forEach((sel) => {
    const list = document.getElementById(sel.getAttribute('data-district-for'));
    const fill = () => {
      if (!list) return;
      const towns = sel.value ? (districts[sel.value] || []) : Object.values(districts).flat();
      list.innerHTML = towns.map((c) => `<option value="${esc(c)}">`).join('');
    };
    sel.addEventListener('change', fill);
    fill();
  });

  // Modal forms: buttons with data-bs-target + data-action fill the form action and fields
  document.addEventListener('show.bs.modal', (e) => {
    const btn = e.relatedTarget;
    if (!btn) return;
    const form = e.target.querySelector('form');
    if (form && btn.dataset.action) form.action = btn.dataset.action;
    Object.entries(btn.dataset).forEach(([k, v]) => {
      if (!k.startsWith('fill')) return;
      const field = k.slice(4).replace(/^./, (c) => c.toLowerCase()).replace(/[A-Z]/g, (c) => '_' + c.toLowerCase());
      const el = e.target.querySelector(`[name="${field}"], [data-fill="${field}"]`);
      if (!el) return;
      if ('value' in el && el.tagName !== 'DIV' && el.tagName !== 'SPAN') el.value = v; else el.textContent = v;
    });
  });

  // Duplicate check while typing the phone (Add Business form)
  const dupBox = document.getElementById('duplicate-box');
  if (dupBox) {
    const form = dupBox.closest('form');
    const phone = form.querySelector('[name="phone"]');
    const name = form.querySelector('[name="business_name"]');
    const city = form.querySelector('[name="city"]');
    let timer;
    const check = () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const params = new URLSearchParams({ phone: phone.value, business_name: name.value, city: city.value });
        if (dupBox.dataset.except) params.set('except', dupBox.dataset.except);
        if (!phone.value.trim() && !name.value.trim()) { dupBox.innerHTML = ''; return; }
        try {
          const res = await fetch(`${dupBox.dataset.url}?${params}`, { headers: { Accept: 'application/json' } });
          const j = await res.json();
          let html = '';
          if (j.business) html += `<div class="alert alert-danger py-2 mb-2"><i class="bi bi-exclamation-octagon me-1"></i>${t('This phone number is already in your Business Finder list:')} <a href="${esc(j.business.url)}" target="_blank">${esc(j.business.name)}</a></div>`;
          else if (j.lead) html += `<div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i>${t('A CRM lead with this phone number already exists:')} <a href="${esc(j.lead.url)}" target="_blank">${esc(j.lead.name)}${j.lead.company ? ' · ' + esc(j.lead.company) : ''}</a>. ${t('Choose "Link to existing lead" below to avoid a duplicate.')}</div>`;
          if (j.similar && j.similar.length) html += `<div class="alert alert-info py-2 mb-2"><i class="bi bi-info-circle me-1"></i>${t('Similar business already researched:')} ${j.similar.map((s) => `<a href="${esc(s.url)}" target="_blank">${esc(s.name)}${s.city ? ' (' + esc(s.city) + ')' : ''}</a>`).join(', ')}</div>`;
          dupBox.innerHTML = html;
          const link = form.querySelector('[name="on_duplicate"][value="link"]');
          if (link && j.lead && !j.business) link.closest('.dup-link-option')?.classList.remove('d-none');
        } catch (_) { /* network errors are ignored: the server checks again on save */ }
      }, 450);
    };
    [phone, name, city].forEach((el) => el && el.addEventListener('input', check));
    if (phone.value || name.value) check();
  }

  // Website check (AJAX): <button data-website-check data-url="..." data-input="#website_url" data-target="#check-result">
  document.querySelectorAll('[data-website-check]').forEach((btn) => btn.addEventListener('click', async () => {
    const out = document.querySelector(btn.dataset.target);
    const input = btn.dataset.input ? document.querySelector(btn.dataset.input) : null;
    const url = input ? input.value.trim() : '';
    if (input && !url && btn.dataset.requireUrl !== undefined) { input.focus(); return; }
    const label = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${t('Checking…')}`;
    out.innerHTML = '';
    try {
      const res = await fetch(btn.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(url ? { url } : {}),
      });
      const j = await res.json();
      const c = j.check || j.data;
      if (!c) { out.innerHTML = `<div class="alert alert-danger py-2">${esc(j.message || t('Check failed.'))}</div>`; return; }
      const cls = c.reachable ? 'success' : (['not_allowed', 'invalid_url'].includes(c.result) ? 'danger' : 'warning');
      out.innerHTML = `<div class="alert alert-${cls} py-2 mb-0">
        <div class="fw-semibold">${esc(c.message)}</div>
        <div class="small mt-1">${c.http_status ? 'HTTP ' + esc(c.http_status) + ' · ' : ''}${c.final_url ? esc(c.final_url) + ' · ' : ''}${c.ssl_valid === false ? t('SSL problem') + ' · ' : ''}${c.response_time_ms ? esc(c.response_time_ms) + ' ms' : ''}${c.cached ? ' · ' + t('cached') : ''}</div>
        ${(c.hints || []).map((h) => `<div class="small"><i class="bi bi-lightbulb"></i> ${esc(h)}</div>`).join('')}
      </div>`;
      if (j.business) {
        document.querySelectorAll('[data-bind="website_status"]').forEach((el) => { el.textContent = j.business.website_status_label; });
        document.querySelectorAll('[data-bind="website_checked_at"]').forEach((el) => { el.textContent = j.business.website_checked_at || '—'; });
        document.querySelectorAll('[data-bind="website_http_status"]').forEach((el) => { el.textContent = j.business.website_http_status || '—'; });
        document.querySelectorAll('[data-bind="website_url"]').forEach((el) => { el.textContent = j.business.website_url || '—'; if (el.tagName === 'A') el.href = j.business.website_url; });
      }
    } catch (_) {
      out.innerHTML = `<div class="alert alert-danger py-2 mb-0">${t('Could not run the check. Try again.')}</div>`;
    } finally {
      btn.disabled = false;
      btn.innerHTML = label;
    }
  }));

  // Copy buttons: <button data-copy="text">
  document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    navigator.clipboard?.writeText(b.dataset.copy);
    const old = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i>'; setTimeout(() => { b.innerHTML = old; }, 1200);
  }));
})();
