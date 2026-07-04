/* ============================================================
   common.js — API wrapper, auth guard, shared sidebar/topbar
   Loaded on every page except index.html (login)
   ============================================================ */

const API_BASE = './backend/api';

/** Generic fetch wrapper: sends session cookie, parses JSON, throws on error */
async function apiFetch(path, options = {}) {
  const res = await fetch(`${API_BASE}${path}`, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(data.error || `Request failed (${res.status})`);
  }
  return data;
}

/** Redirects to login if not authenticated. Returns the current user. */
async function requireAuth() {
  try {
    const user = await apiFetch('/auth.php?action=me');
    renderSidebar(user);
    return user;
  } catch (e) {
    window.location.href = 'index.html';
    throw e;
  }
}

async function logout() {
  try { await apiFetch('/auth.php?action=logout', { method: 'POST' }); } catch (e) {}
  window.location.href = 'index.html';
}

/** Nav items per role — keeps kitchen/waiter UIs uncluttered */
function navItemsForRole(role) {
  const all = [
    { href: 'dashboard.html', label: 'Dashboard', icon: 'M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V9.75z', roles: ['admin','manager','waiter','kitchen'] },
    { href: 'tables.html', label: 'Tables', icon: 'M4 6h16M4 12h16M4 18h16', roles: ['admin','manager','waiter'] },
    { href: 'orders.html', label: 'Orders', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', roles: ['admin','manager','waiter','kitchen'] },
    { href: 'billing.html', label: 'Billing', icon: 'M12 8c-1.657 0-3 .672-3 1.5S10.343 11 12 11s3 .672 3 1.5-1.343 1.5-3 1.5m0-6c1.11 0 2.08.402 2.599 1M12 8V6m0 8v2m9-4a9 9 0 11-18 0 9 9 0 0118 0z', roles: ['admin','manager','waiter'] },
    { href: 'menu.html', label: 'Menu', icon: 'M4 6h16M4 10h16M4 14h10M4 18h10', roles: ['admin','manager','kitchen'] },
  ];
  return all.filter(item => item.roles.includes(role));
}

function toggleSidebar() {
  document.getElementById('mobile-sidebar')?.classList.toggle('-translate-x-full');
  document.getElementById('sidebar-overlay')?.classList.toggle('hidden');
}

function renderSidebar(user) {
  const mount = document.getElementById('sidebar-mount');
  if (!mount) return;

  const items = navItemsForRole(user.role);
  const current = window.location.pathname.split('/').pop();

  const navHtml = items.map(item => `
    <a href="${item.href}" class="nav-link flex items-center gap-3 px-6 py-3 text-sm font-medium ${current === item.href ? 'active' : 'hover:bg-white/5'}">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="${item.icon}"/>
      </svg>
      ${item.label}
    </a>
  `).join('');

  mount.innerHTML = `
    <!-- Mobile top bar: hamburger left, brand center, sign-out right, all one line -->
    <div class="mobile-topbar lg:hidden fixed top-0 left-0 right-0 z-30 bg-[#1F2937] text-white flex items-center justify-between px-4 py-3">
      <button onclick="toggleSidebar()" class="p-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <p class="font-display font-bold text-amber-400 text-sm">DineAtDesk</p>
      <button onclick="logout()" class="text-xs font-semibold text-amber-400">
        Sign out
      </button>
    </div>

    <!-- Overlay, tap to close drawer -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="hidden lg:hidden fixed inset-0 bg-black/50 z-30"></div>

    <!-- Full sidebar: slides in as a drawer on mobile, static column on desktop -->
    <aside id="mobile-sidebar"
      class="w-64 shrink-0 bg-[#1F2937] text-gray-200 flex flex-col h-screen
             fixed lg:static inset-y-0 left-0 z-40
             -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out">
      <div class="px-6 py-6 border-b border-white/10">
        <p class="font-display font-bold text-xl text-amber-400">DineAt<span class="text-white">Desk</span></p>
        <p class="text-xs text-gray-400 mt-1">Restaurant Floor Manager</p>
      </div>
      <nav class="flex-1 py-4 space-y-1 overflow-y-auto">
        ${navHtml}
      </nav>
      <div class="px-6 py-4 border-t border-white/10">
        <p class="text-sm font-semibold text-white">${user.name}</p>
        <p class="text-xs text-gray-400 capitalize mb-3">${user.role}</p>
        <button onclick="logout()" class="w-full text-xs font-semibold text-amber-400 hover:text-amber-300 flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Sign out
        </button>
      </div>
    </aside>
  `;
}

/** Small toast for success/error feedback */
function toast(message, type = 'success') {
  const el = document.createElement('div');
  const bg = type === 'success' ? 'bg-green-700' : 'bg-red-700';
  el.className = `fixed bottom-6 right-6 ${bg} text-white text-sm font-medium px-4 py-3 rounded-lg shadow-lg fade-in z-50`;
  el.textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

function formatMoney(n) {
  return '₹' + Number(n).toFixed(2);
}

function timeAgo(dateStr) {
  const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T'));
  const mins = Math.floor(diffMs / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  return `${hrs}h ${mins % 60}m ago`;
}
