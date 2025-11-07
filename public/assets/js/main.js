// main.js - search + small helpers
document.addEventListener('DOMContentLoaded', function() {
  const searchForm = document.getElementById('searchForm');
  if (searchForm) {
    searchForm.addEventListener('submit', async function(e){
      e.preventDefault();
      const q = (document.getElementById('q')||{value:''}).value;
      const city = (document.getElementById('city')||{value:''}).value;
      // basic client-side navigation to listings page with query params
      const params = new URLSearchParams();
      if (q) params.append('q', q);
      if (city) params.append('city', city);
      // Use clean URL - remove /public/ and .php
      const basePath = window.location.pathname.replace(/\/public\/.*$/, '');
      window.location.href = basePath + '/listings?' + params.toString();
    });
  }
});

// small escape util
function escapeHtml(s){ return String(s||'').replace(/[&<>"'`=\/]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c])); }


// Theme toggle — add at end of public/assets/js/main.js or in footer script
(function() {
  const STORAGE_KEY = 'pgfinder_theme'; // 'light' | 'dark'
  const html = document.documentElement;
  const toggleBtn = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');

  function prefersDark() {
    return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function setIconForTheme(theme) {
    if (!themeIcon) return;
    if (theme === 'dark') {
      themeIcon.classList.remove('bi-moon-stars');
      themeIcon.classList.add('bi-sun');
    } else {
      themeIcon.classList.remove('bi-sun');
      themeIcon.classList.add('bi-moon-stars');
    }
  }

  function applyTheme(theme) {
    if (theme === 'dark') {
      html.setAttribute('data-theme', 'dark');
      if (toggleBtn) toggleBtn.setAttribute('aria-pressed', 'true');
      setIconForTheme('dark');
    } else {
      html.removeAttribute('data-theme');
      if (toggleBtn) toggleBtn.setAttribute('aria-pressed', 'false');
      setIconForTheme('light');
    }
  }

  function getStoredTheme() {
    try { return localStorage.getItem(STORAGE_KEY); } catch(e) { return null; }
  }
  function storeTheme(val) {
    try { if (val) localStorage.setItem(STORAGE_KEY, val); } catch(e) {}
  }

  function getEffectiveTheme() {
    const stored = getStoredTheme();
    if (stored === 'dark' || stored === 'light') return stored;
    return prefersDark() ? 'dark' : 'light';
  }

  // Initial theme selection
  (function initTheme() {
    applyTheme(getEffectiveTheme());
  })();

  // Toggle handler: flip effective theme directly (no system cycle)
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      const currentEffective = getEffectiveTheme();
      const next = currentEffective === 'dark' ? 'light' : 'dark';
      storeTheme(next);
      applyTheme(next);
    });
  }

  // React to system preference change only if user hasn't set an explicit preference
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      const stored = getStoredTheme();
      if (stored !== 'dark' && stored !== 'light') {
        applyTheme(getEffectiveTheme());
      }
    });
  }

  // Sync across tabs
  window.addEventListener('storage', function(e) {
    if (e.key === STORAGE_KEY) {
      const val = e.newValue;
      if (val === 'dark' || val === 'light') applyTheme(val);
    }
  });
})();
