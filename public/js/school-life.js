// Loads event posts from the back end and shows them as cards
async function loadEvents() {
  // Find the box in school-life.html that will hold the event cards
  const box = document.getElementById('events');

  try {
    // Ask the back end for up to 6 published posts in the "event" category.
    // api() comes from common.js.
    const events = await api('/posts?category=event&limit=6');

    // If there are no events, show a short message.
    // The ? and : below form an if/else in one line:
    // if there are events, build the cards; otherwise, show the message.
    box.innerHTML = events.length
      ? events.map(e => `
          <article class="card">
            ${e.image_url ? `<img src="${e.image_url}" alt="${escapeHtml(e.title)}">` : ''}
            <h3>${escapeHtml(e.title)}</h3>
            <p class="post-body">${escapeHtml(e.body)}</p>
            <small class="muted">${formatDate(e.created_at)}</small>
          </article>
        `).join('')
      : '<p>No events posted yet.</p>';

  } catch (err) {
    // Runs if the server is off or the request fails
    box.innerHTML = '<p>Could not load events.</p>';
  }
}

// Run loadEvents() once the page has finished loading
document.addEventListener('DOMContentLoaded', loadEvents);