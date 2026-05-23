
document.addEventListener('DOMContentLoaded', function () {
  const tables = document.querySelectorAll('table');
  tables.forEach((table) => {
    if (table.parentElement && !table.parentElement.classList.contains('table-responsive')) {
      const wrap = document.createElement('div');
      wrap.className = 'table-responsive';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    }
  });

  const modernNavs = document.querySelectorAll('[data-nav-shell]');
  modernNavs.forEach((nav) => {
    const toggle = nav.querySelector('[data-nav-toggle]');
    const mobileMenu = nav.querySelector('[data-nav-mobile]');
    if (!toggle || !mobileMenu) return;

    toggle.addEventListener('click', function () {
      const isHidden = mobileMenu.classList.contains('hidden');
      mobileMenu.classList.toggle('hidden', !isHidden);
      toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    });
  });

  const navs = document.querySelectorAll('nav.bg-blue-700');
  navs.forEach((nav) => {
    if (nav.dataset.responsiveEnhanced === '1') return;
    nav.dataset.responsiveEnhanced = '1';

    const topRow = nav.querySelector('.flex.h-16.items-center.justify-between');
    if (!topRow) return;
    if (nav.querySelector('#mobile-menu')) return; // page already has mobile menu

    const desktopLinkContainer = nav.querySelector('.hidden.md\\:block .ml-10.flex, .hidden.md\\:block .ml-10');
    const logoutLink = Array.from(nav.querySelectorAll('a')).find(a => /log out|logout|sign out|تسجيل الخروج/i.test((a.textContent || '').trim()));
    if (!desktopLinkContainer) return;

    const mobileToggleWrap = document.createElement('div');
    mobileToggleWrap.className = '-mr-2 flex md:hidden';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'relative inline-flex items-center justify-center rounded-md bg-gray-800 p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none';
    button.setAttribute('aria-controls', 'mobile-menu-generated');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = '<span class="sr-only">Open main menu</span>' +
      '<svg class="hamburger-open h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>' +
      '<svg class="hamburger-close hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>';
    mobileToggleWrap.appendChild(button);
    topRow.appendChild(mobileToggleWrap);

    const mobileMenu = document.createElement('div');
    mobileMenu.className = 'md:hidden hidden border-t border-blue-600';
    mobileMenu.id = 'mobile-menu-generated';

    const linksWrap = document.createElement('div');
    linksWrap.className = 'space-y-1 px-2 pb-3 pt-2';
    Array.from(desktopLinkContainer.querySelectorAll('a')).forEach((link) => {
      const clone = link.cloneNode(true);
      clone.className = ((clone.className || '') + ' block').trim();
      linksWrap.appendChild(clone);
    });
    if (logoutLink) {
      const clone = logoutLink.cloneNode(true);
      clone.className = 'block rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white';
      linksWrap.appendChild(clone);
    }
    mobileMenu.appendChild(linksWrap);
    nav.appendChild(mobileMenu);

    button.addEventListener('click', function () {
      mobileMenu.classList.toggle('hidden');
      const expanded = !mobileMenu.classList.contains('hidden');
      button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      button.querySelector('.hamburger-open').classList.toggle('hidden', expanded);
      button.querySelector('.hamburger-close').classList.toggle('hidden', !expanded);
    });
  });
});
