// Loads all subjects from the back end and groups them into O Level and A Level sections
async function loadSubjects() {
  // Find the box in subjects.html that will hold the subject cards
  const box = document.getElementById('subjects');

  try {
    // Ask the back end for every subject. api() comes from common.js.
    const subjects = await api('/subjects');

    // The two levels we want to show, in order, with a short note for each
    const levels = [
      { name: 'O Level', note: 'Classes S1 to S4' },
      { name: 'A Level', note: 'Classes S5 and S6' }
    ];

    // Build one section per level. .map() creates a block of HTML for each level.
    box.innerHTML = levels.map(level => {

      // Keep only the subjects that belong to this level.
      // filter() returns a new list containing just the matching items.
      const items = subjects.filter(s => s.level === level.name);

      return `
        <section class="level-block">
          <h2 class="section-title">${level.name}</h2>
          <p class="muted">${level.note}</p>

          <div class="grid" style="margin-top: 12px;">
            ${items.map(s => `
              <div class="card">
                <h3>${escapeHtml(s.name)}</h3>
                <p>${escapeHtml(s.description || '')}</p>
              </div>
            `).join('') || '<p>No subjects added yet.</p>'}
          </div>
        </section>`;
    }).join('');   // join('') combines the two sections into one string

  } catch (err) {
    // Runs if the server is off or the request fails
    box.innerHTML = '<p>Could not load subjects.</p>';
  }
}

// Run loadSubjects() once the page has finished loading
document.addEventListener('DOMContentLoaded', loadSubjects);