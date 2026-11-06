// Loads the latest news posts and shows them as cards on the home page
async function loadNews() {
  // Find the empty box in index.html that will hold the news cards
  const box = document.getElementById('news-list');

  try {
    // Ask the back end for up to 3 published posts.
    // api() comes from common.js.
    const posts = await api('/posts?limit=3');

    // If there are no posts yet, show a short message and stop here
    if (posts.length === 0) {
      box.innerHTML = '<p>No news yet. Check back soon.</p>';
      return;
    }

    // Build one card for each post and place them all in the box.
    // .map() goes through every post in the list, one at a time.
    box.innerHTML = posts.map(p => `
      <article class="card">
        ${p.image_url ? `<img src="${p.image_url}" alt="${escapeHtml(p.title)}">` : ''}
        <span class="tag">${escapeHtml(p.category)}</span>
        <h3>${escapeHtml(p.title)}</h3>
        <p class="post-body">${escapeHtml(p.body.slice(0, 160))}${p.body.length > 160 ? '...' : ''}</p>
        <small class="muted">${formatDate(p.created_at)}</small>
      </article>
    `).join('');   // join('') combines the cards into one string with no separators

  } catch (err) {
    // This runs if the server is off or the request fails.
    // The page stays usable and shows a clear message instead of breaking.
    box.innerHTML = '<p>Could not load news right now.</p>';
  }
}

// Run loadNews() once the page has finished loading
document.addEventListener('DOMContentLoaded', loadNews);