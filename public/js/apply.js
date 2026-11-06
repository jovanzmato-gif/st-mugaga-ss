// Shows or hides a block of fields, and turns its fields on or off to match
function toggleBlock(id, show) {
  const block = document.getElementById(id);
  block.hidden = !show;

  block.querySelectorAll('input, select, textarea').forEach(el => {
    el.disabled = !show;                                   // disabled fields are not sent
    el.required = show && el.dataset.required === 'true';  // required only when showing
  });
}

// Chooses which block to show, based on the applicant type
function updateApplicantFields() {
  const isTransfer = document.getElementById('applicant_type').value === 'transfer';
  toggleBlock('transfer-fields', isTransfer);
  toggleBlock('primary-fields', !isTransfer);
}

// Fills the class dropdowns: desired class (by id) and previous class (by name)
async function loadClasses() {
  const classes = await api('/classes');

  document.getElementById('desired_class_id').innerHTML =
    '<option value="">Choose...</option>' +
    classes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

  document.getElementById('previous_class').innerHTML =
    '<option value="">Choose...</option>' +
    classes.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
}

// Re-show the right blocks whenever the applicant type changes
document.getElementById('applicant_type').addEventListener('change', updateApplicantFields);

// Send the form
document.getElementById('apply-form').addEventListener('submit', async e => {
  e.preventDefault();

  const form = e.target;
  const msg = document.getElementById('form-message');
  const btn = form.querySelector('button[type="submit"]');

  btn.disabled = true;
  msg.innerHTML = '';

  try {
    const data = await api('/applications', { method: 'POST', body: new FormData(form) });

    form.reset();
    updateApplicantFields();   // form.reset() puts the dropdown back to "New student", so re-check
    msg.innerHTML = `<div class="alert success">${escapeHtml(data.message)}</div>`;
  } catch (err) {
    msg.innerHTML = `<div class="alert error">${escapeHtml(err.message)}</div>`;
  } finally {
    btn.disabled = false;
  }
});

document.addEventListener('DOMContentLoaded', () => {
  updateApplicantFields();
  loadClasses().catch(() => {
    document.getElementById('form-message').innerHTML =
      '<div class="alert error">Could not load classes. Please refresh the page.</div>';
  });
});