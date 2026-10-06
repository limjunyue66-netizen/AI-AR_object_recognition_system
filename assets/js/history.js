/**
 * Scan history page.
 */
(function () {
  'use strict';

  function apiBase() {
    const meta = document.querySelector('meta[name="app-base"]');
    return meta ? meta.content.replace(/\/$/, '') : '';
  }

  let page = 1;
  let lastQuery = '';

  const els = {
    tbody: document.getElementById('historyBody'),
    search: document.getElementById('historySearch'),
    meta: document.getElementById('historyMeta'),
    prev: document.getElementById('prevPage'),
    next: document.getElementById('nextPage'),
    clear: document.getElementById('clearHistory'),
    modal: document.getElementById('detailModal'),
    modalBody: document.getElementById('modalBody'),
    modalClose: document.getElementById('modalClose'),
    notice: document.getElementById('historyNotice'),
  };

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  async function loadHistory() {
    const q = els.search ? els.search.value.trim() : '';
    lastQuery = q;
    const url = apiBase() + '/api/history.php?page=' + encodeURIComponent(String(page)) + '&q=' + encodeURIComponent(q);
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const json = await res.json();

    if (!json.ok) {
      if (els.notice) {
        els.notice.textContent = json.error || 'History unavailable.';
        els.notice.style.display = 'block';
      }
      if (els.tbody) els.tbody.innerHTML = '';
      return;
    }

    if (els.notice) els.notice.style.display = 'none';
    const data = json.data;
    const items = data.items || [];

    if (els.tbody) {
      if (items.length === 0) {
        els.tbody.innerHTML = '<tr><td colspan="5">No scans yet.</td></tr>';
      } else {
        els.tbody.innerHTML = items.map((item) => {
          const conf = Math.round((item.confidence || 0) * 100) + '%';
          return `<tr>
            <td><button type="button" class="secondary-btn" data-id="${item.id}">${escapeHtml(item.productName)}</button></td>
            <td>${escapeHtml(item.provider || '—')}</td>
            <td>${escapeHtml(conf)}</td>
            <td>${escapeHtml(item.createdAt || '')}</td>
            <td><button type="button" class="secondary-btn" data-id="${item.id}">View</button></td>
          </tr>`;
        }).join('');
      }
    }

    const totalPages = Math.max(1, Math.ceil((data.total || 0) / (data.perPage || 20)));
    if (els.meta) {
      els.meta.textContent = `Page ${data.page} of ${totalPages} · ${data.total} records`;
    }
    if (els.prev) els.prev.disabled = data.page <= 1;
    if (els.next) els.next.disabled = data.page >= totalPages;

    if (els.tbody) {
      els.tbody.querySelectorAll('button[data-id]').forEach((btn) => {
        btn.addEventListener('click', () => openDetail(Number(btn.getAttribute('data-id'))));
      });
    }
  }

  async function openDetail(id) {
    const res = await fetch(apiBase() + '/api/history.php?id=' + encodeURIComponent(String(id)), {
      headers: { Accept: 'application/json' },
    });
    const json = await res.json();
    if (!json.ok) return;
    const d = json.data;
    if (els.modalBody) {
      els.modalBody.innerHTML = `
        <p><strong>Product:</strong> ${escapeHtml(d.productName)}</p>
        <p><strong>Object:</strong> ${escapeHtml(d.objectLabel)}</p>
        <p><strong>Manufacturer:</strong> ${escapeHtml(d.manufacturer)}</p>
        <p><strong>Specification:</strong> ${escapeHtml(d.specification || '—')}</p>
        <p><strong>Description:</strong> ${escapeHtml(d.description || '—')}</p>
        <p><strong>Confidence:</strong> ${Math.round((d.confidence || 0) * 100)}%</p>
        <p><strong>Provider:</strong> ${escapeHtml(d.provider || '—')}</p>
        <p><strong>Date:</strong> ${escapeHtml(d.createdAt || '')}</p>
      `;
    }
    if (els.modal) els.modal.classList.add('visible');
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (els.search) {
      let timer = null;
      els.search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
          page = 1;
          loadHistory().catch(() => {});
        }, 300);
      });
    }

    if (els.prev) {
      els.prev.addEventListener('click', () => {
        page = Math.max(1, page - 1);
        loadHistory();
      });
    }
    if (els.next) {
      els.next.addEventListener('click', () => {
        page += 1;
        loadHistory();
      });
    }
    if (els.clear) {
      els.clear.addEventListener('click', async () => {
        if (!confirm('Clear all scan history?')) return;
        await fetch(apiBase() + '/api/history.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ action: 'clear' }),
        });
        page = 1;
        loadHistory();
      });
    }
    if (els.modalClose) {
      els.modalClose.addEventListener('click', () => els.modal.classList.remove('visible'));
    }
    if (els.modal) {
      els.modal.addEventListener('click', (e) => {
        if (e.target === els.modal) els.modal.classList.remove('visible');
      });
    }

    loadHistory().catch(() => {});
  });
})();
