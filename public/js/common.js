// The school's name and location, used in the header and footer
const SCHOOL = {
  name: 'St. Mugaga Secondary School',
  location: 'Jalamba'
};

// The menu links, in the order they appear on screen.
// Each item has the file to open (href) and the text to show (label).
const NAV = [
  { href: 'index.html', label: 'Home' },
  { href: 'subjects.html', label: 'Subjects' },
  { href: 'admissions.html', label: 'Admissions' },
  { href: 'school-life.html', label: 'School Life' },
  { href: 'apply.html', label: 'Online Application' },
  { href: 'admin/login.html', label: 'Admin Portal' }
];

// Builds the navigation bar and footer on the current page
function renderLayout() {
  // Find the name of the file currently open, e.g. "subjects.html".
  // If the URL ends in "/", the page is the home page.
  const current = location.pathname.split('/').pop() || 'index.html';

  // Build the header, but only on pages that have a <div id="site-header">
  const header = document.getElementById('site-header');
  if (header) {
    header.innerHTML = `
      <nav class="navbar">
        <a class="brand" href="index.html">${SCHOOL.name}</a>
        <button class="menu-toggle" aria-label="Open menu"
          onclick="document.querySelector('.nav-links').classList.toggle('open')">☰</button>
        <ul class="nav-links">
          ${NAV.map(n => `
            <li>
              <a class="${n.href === current ? 'active' : ''}" href="${n.href}">${n.label}</a>
            </li>`).join('')}
        </ul>
      </nav>`;
  }

  // Build the footer, but only on pages that have a <div id="site-footer">
  const footer = document.getElementById('site-footer');
  if (footer) {
    footer.innerHTML = `
      <p>&copy; ${new Date().getFullYear()} ${SCHOOL.name}, ${SCHOOL.location}</p>
      <p><a href="admin/login.html">Admin Portal</a></p>`;
  }
}

// Calls the back end API and returns the data.
// If the server returns an error, it throws an error with a readable message.
async function api(path, options = {}) {
  const res = await fetch('/st-mugaga-ss/public/api' + path, options);
  const data = await res.json().catch(() => ({}));

  if (!res.ok) throw new Error(data.message || 'Request failed');
  return data;
}

// Makes user text safe to show on the page.
// It converts characters like < and > into harmless text, which stops
// someone from typing HTML or scripts into a post and breaking the page.
function escapeHtml(value = '') {
  return String(value).replace(/[&<>"']/g, c => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[c]));
}

// Turns a date from the database into a readable form, e.g. "24 Oct 2026"
function formatDate(value) {
  return new Date(value).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  });
}

// Run renderLayout() once the page has finished loading
document.addEventListener('DOMContentLoaded', renderLayout);