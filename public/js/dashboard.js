// ===== STARTUP =====

requireLogin();

document.getElementById('logout-btn').addEventListener('click', logout);

// Shared memory for student search results
let studentCache = {};
let resultStudent = null;
let allSubjects = [];


// ===== PUBLIC API HELPER (for classes & subjects) =====
async function api(path, options = {}) {
  const headers = {};
  let body = options.body;

  if (body && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(body);
  }

  const res = await fetch('/st-mugaga-ss/public/api' + path, {
    method: options.method || 'GET',
    headers,
    body
  });

  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.message || 'Request failed');
  return data;
}


// ===== TABS =====
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn, .tab-panel')
      .forEach(el => el.classList.remove('active'));

    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');

    if (btn.dataset.tab === 'tab-applications') loadApplications();
    if (btn.dataset.tab === 'tab-posts') loadPosts();
  });
});


// ===== DROPDOWNS =====
async function populateLookups() {
  try {
    const [classes, subjects] = await Promise.all([
      api('/classes'),
      api('/subjects')
    ]);

    allSubjects = subjects || [];

    const classOptions = '<option value="">Choose class</option>' +
      (classes || []).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');

    document.querySelectorAll('.class-select')
      .forEach(sel => sel.innerHTML = classOptions);

    // Subject dropdown (will be filtered later by level)
    const subSel = document.getElementById('res-subject');
    if (subSel) {
      subSel.innerHTML = allSubjects
        .map(s => `<option value="${s.id}">${escapeHtml(s.name)} (${s.level})</option>`)
        .join('');
    }
  } catch (err) {
    console.error(err);
    alert('Could not load classes or subjects: ' + err.message);
  }
}


// ===== POSTS =====
async function loadPosts() {
  try {
    const posts = await adminApi('/posts');
    const list = document.getElementById('posts-list');

    list.innerHTML = (posts || []).map(p => `
      <div class="row-item">
        <div>
          <strong>${escapeHtml(p.title)}</strong>
          <span class="tag">${escapeHtml(p.category)}</span>
          <span class="tag ${p.is_published ? 'ok' : ''}">
            ${p.is_published ? 'Published' : 'Hidden'}
          </span><br>
          <small class="muted">${formatDate(p.created_at)}</small>
        </div>
        <div class="row-actions">
          <button onclick="togglePost(${p.id}, ${p.is_published ? 0 : 1})">
            ${p.is_published ? 'Hide' : 'Publish'}
          </button>
          <button class="danger" onclick="deletePost(${p.id})">Delete</button>
        </div>
      </div>`).join('') || '<p>No posts yet.</p>';
  } catch (err) {
    document.getElementById('posts-list').innerHTML =
      `<div class="alert error">${escapeHtml(err.message)}</div>`;
  }
}

async function togglePost(id, value) {
  try {
    await adminApi(`/posts/${id}`, { method: 'PUT', body: { is_published: value } });
    loadPosts();
  } catch (err) {
    alert(err.message);
  }
}

async function deletePost(id) {
  if (!confirm('Delete this post permanently?')) return;
  try {
    await adminApi(`/posts/${id}`, { method: 'DELETE' });
    loadPosts();
  } catch (err) {
    alert(err.message);
  }
}

document.getElementById('post-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const msg = document.getElementById('post-msg');

  try {
    await adminApi('/posts', { method: 'POST', body: new FormData(form) });
    form.reset();
    msg.textContent = 'Saved';
    loadPosts();
  } catch (err) {
    msg.textContent = err.message;
  }
});


// ===== APPLICATIONS =====
const DOC_LABELS = {
  passport_photo: 'Photo',
  birth_certificate: 'Birth certificate',
  ple_slip: 'PLE slip',
  transfer_letter: 'Transfer letter',
  previous_report: 'Previous report'
};

async function loadApplications() {
  const box = document.getElementById('applications-list');
  box.innerHTML = '<p>Loading applications...</p>';

  try {
    const apps = await adminApi('/applications');

    if (!apps || apps.length === 0) {
      box.innerHTML = '<p>No applications yet.</p>';
      return;
    }

    box.innerHTML = apps.map(a => {
      const isTransfer = a.applicant_type === 'transfer';

      // Safely build documents list
      const docs = [
        ...(a.passport_photo ? [{ doc_type: 'passport_photo', file_path: a.passport_photo }] : []),
        ...(Array.isArray(a.documents) ? a.documents : [])
      ];

      const docLinks = docs
        .map(d => `<a href="${d.file_path}" target="_blank">${DOC_LABELS[d.doc_type] || d.doc_type}</a>`)
        .join(' · ') || 'none';

      const transferLine = isTransfer
        ? `<br>Transfer from ${escapeHtml(a.previous_school || '')} (${escapeHtml(a.previous_class || '')})
           · Reason: ${escapeHtml(a.reason_for_transfer || '')}`
        : '';

      const primaryLine = isTransfer
        ? ''
        : `<br>Previous school: ${escapeHtml(a.previous_school || '')} · PLE: ${a.ple_aggregates ?? 'N/A'}`;

      return `
        <div class="row-item">
          <div>
            <strong>${escapeHtml(a.student_full_name)}</strong> to ${escapeHtml(a.desired_class || '')}
            <span class="tag">${isTransfer ? 'Transfer' : 'New student'}</span>
            <span class="tag ${a.status === 'approved' ? 'ok' : ''}">${escapeHtml(a.status)}</span><br>
            <small class="muted">
              ${escapeHtml(a.gender || '')} · DOB ${formatDate(a.date_of_birth)} · Parent: ${escapeHtml(a.parent_contact || '')}
              ${primaryLine}
              ${transferLine}<br>
              Documents: ${docLinks}
            </small>
          </div>
          <div class="row-actions">
            <button onclick="setStatus(${a.id}, 'approved')">Approve</button>
            <button class="danger" onclick="setStatus(${a.id}, 'rejected')">Reject</button>
          </div>
        </div>`;
    }).join('');

  } catch (err) {
    box.innerHTML = `<div class="alert error">Could not load applications: ${escapeHtml(err.message)}</div>`;
  }
}

async function setStatus(id, status) {
  try {
    await adminApi(`/applications/${id}`, { method: 'PATCH', body: { status } });
    loadApplications();
  } catch (err) {
    alert(err.message);
  }
}


// ===== STUDENT SEARCH =====
async function searchStudents(query, classId) {
  const students = await adminApi(
    `/students?search=${encodeURIComponent(query)}&class_id=${classId}`
  );

  (students || []).forEach(s => studentCache[s.id] = s);
  return students || [];
}


// ===== ENTER RESULTS =====
const TERM_FIELDS = [
  'days_present', 'days_absent', 'term_ended_on', 'next_term_begins',
  'fees_balance', 'fees_next_term', 'class_teacher_comment', 'headteacher_comment',
  'project_title', 'project_score', 'project_grade', 'project_remark', 'project_teacher'
];

const currentTerm = () => document.getElementById('res-term').value;
const currentYear = () => document.getElementById('res-year').value;

document.getElementById('res-search-btn')?.addEventListener('click', async () => {
  const q = document.getElementById('res-search').value.trim();
  const cls = document.getElementById('res-class').value;
  if (!cls) return alert('Choose a class first');

  try {
    const students = await searchStudents(q, cls);
    document.getElementById('res-students').innerHTML = students.map(s => `
      <div class="row-item">
        <div>
          <strong>${escapeHtml(s.full_name)}</strong>
          <small class="muted">${s.admission_no} · ${escapeHtml(s.class_name || '')}</small>
        </div>
        <button onclick="pickForResults(${s.id})">Select</button>
      </div>`).join('') || '<p>No student found in this class.</p>';
  } catch (err) {
    alert(err.message);
  }
});

function pickForResults(id) {
  resultStudent = studentCache[id];
  if (!resultStudent) return;

  document.getElementById('res-selected').textContent =
    `${resultStudent.full_name} (${resultStudent.class_name || ''})`;
  document.getElementById('res-entry').hidden = false;

  fillStudentForm();
  setupSubjectForm().catch(err => alert(err.message));
  loadEntries();
}

function fillStudentForm() {
  const form = document.getElementById('student-form');
  if (!form || !resultStudent) return;

  form.elements.lin.value = resultStudent.lin || '';
  form.elements.stream.value = resultStudent.stream || '';
  form.elements.house.value = resultStudent.house || '';
  document.getElementById('student-msg').textContent = '';
}

document.getElementById('student-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  if (!resultStudent) return;

  const fd = new FormData(e.target);
  const details = {
    lin: fd.get('lin'),
    stream: fd.get('stream'),
    house: fd.get('house')
  };
  const msg = document.getElementById('student-msg');

  try {
    await adminApi(`/students/${resultStudent.id}`, { method: 'PATCH', body: details });
    Object.assign(resultStudent, details);
    msg.textContent = 'Student details saved';
  } catch (err) {
    msg.textContent = err.message;
  }
});

function fillTermForm(term) {
  const form = document.getElementById('term-form');
  if (!form) return;

  TERM_FIELDS.forEach(name => {
    if (form.elements[name]) {
      form.elements[name].value = term ? (term[name] ?? '') : '';
    }
  });
}

document.getElementById('term-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  if (!resultStudent) return;

  const fd = new FormData(e.target);
  const body = {
    student_id: resultStudent.id,
    term: currentTerm(),
    year: currentYear()
  };
  TERM_FIELDS.forEach(name => body[name] = fd.get(name));

  const msg = document.getElementById('term-msg');
  try {
    await adminApi('/term-details', { method: 'POST', body });
    msg.textContent = 'Term details saved';
  } catch (err) {
    msg.textContent = err.message;
  }
});

async function setupSubjectForm() {
  const form = document.getElementById('subject-form');
  if (!form || !resultStudent) return;

  form.reset();

  if (allSubjects.length === 0) {
    allSubjects = await api('/subjects');
  }

  // Try to detect level from class name or section
  const isA = (resultStudent.section === 'A Level') ||
              (resultStudent.class_name || '').toUpperCase().includes('A LEVEL') ||
              (resultStudent.class_name || '').toUpperCase().includes('S5') ||
              (resultStudent.class_name || '').toUpperCase().includes('S6');

  const level = isA ? 'A Level' : 'O Level';

  document.getElementById('res-subject').innerHTML = allSubjects
    .filter(s => s.level === level)
    .map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`)
    .join('') || '<option value="">No subjects for this level</option>';

  document.getElementById('a-level-fields').hidden = !isA;
  document.getElementById('o-level-fields').hidden = isA;

  document.getElementById('topic-rows').innerHTML = '';
  if (!isA) addTopicRow();

  document.getElementById('subject-msg').textContent = '';
}

function addTopicRow(topic = '', score = '') {
  const row = document.createElement('div');
  row.className = 'topic-row';
  row.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px; align-items: center;';
  row.innerHTML = `
    <input class="topic-name" placeholder="Topic" value="${escapeHtml(topic)}" style="flex: 3;">
    <input class="topic-score" type="number" min="0" max="3" step="0.1" placeholder="0 to 3"
      value="${score}" style="flex: 1;">
    <button type="button" class="danger">Remove</button>`;

  row.querySelector('button').addEventListener('click', () => row.remove());
  document.getElementById('topic-rows').appendChild(row);
}

document.getElementById('add-topic')?.addEventListener('click', () => addTopicRow());

document.getElementById('subject-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  if (!resultStudent) return;

  const fd = new FormData(e.target);
  const msg = document.getElementById('subject-msg');

  const isA = !document.getElementById('a-level-fields').hidden;

  const body = {
    student_id: resultStudent.id,
    subject_id: fd.get('subject_id'),
    term: currentTerm(),
    year: currentYear(),
    teacher_name: fd.get('teacher_name'),
    remark: fd.get('remark')
  };

  if (isA) {
    body.formative_1 = fd.get('formative_1');
    body.formative_2 = fd.get('formative_2');
    body.formative_3 = fd.get('formative_3');
    body.eot_score = fd.get('eot_score');
  } else {
    body.generic_skills = fd.get('generic_skills');
    body.topics = [...document.querySelectorAll('.topic-row')].map(row => ({
      topic: row.querySelector('.topic-name').value,
      score: row.querySelector('.topic-score').value
    }));
  }

  try {
    const data = await adminApi('/entries', { method: 'POST', body });
    msg.textContent = data.message || 'Saved';
    loadEntries();
  } catch (err) {
    msg.textContent = err.message;
  }
});

async function loadEntries() {
  if (!resultStudent) return;

  const box = document.getElementById('entries-list');
  try {
    const data = await adminApi(
      `/entries?student_id=${resultStudent.id}&term=${encodeURIComponent(currentTerm())}&year=${currentYear()}`
    );

    fillTermForm(data.term);

    const subjects = data.subjects || [];
    box.innerHTML = subjects.length
      ? `<table>
           <thead><tr><th>Subject</th><th>Marks</th><th>Grade / rating</th><th>Teacher</th></tr></thead>
           <tbody>
             ${subjects.map(s => `
               <tr>
                 <td>${escapeHtml(s.subject || '')}</td>
                 <td>${s.marks ?? '-'}</td>
                 <td>${escapeHtml(s.grade ?? '')}</td>
                 <td>${escapeHtml(s.teacher_name ?? '')}</td>
               </tr>`).join('')}
           </tbody>
         </table>`
      : '<p>No subjects saved for this term yet.</p>';
  } catch (err) {
    box.innerHTML = `<div class="alert error">${escapeHtml(err.message)}</div>`;
  }
}

document.getElementById('res-term')?.addEventListener('change', loadEntries);
document.getElementById('res-year')?.addEventListener('change', loadEntries);


// ===== REPORTS =====
document.getElementById('rep-search-btn')?.addEventListener('click', async () => {
  const q = document.getElementById('rep-search').value.trim();
  const cls = document.getElementById('rep-class').value;

  if (!cls) return alert('Choose a class first');

  try {
    const students = await searchStudents(q, cls);

    document.getElementById('rep-students').innerHTML = students.map(s => `
      <div class="row-item">
        <div>
          <strong>${escapeHtml(s.full_name)}</strong>
          <small class="muted">${s.admission_no} · ${escapeHtml(s.class_name || '')}</small>
        </div>
        <button onclick="viewReport(${s.id})">View report</button>
      </div>`).join('') || '<p>No student found in this class.</p>';

    document.getElementById('report-area').innerHTML = '';
  } catch (err) {
    alert(err.message);
  }
});

async function viewReport(id) {
  const student = studentCache[id];
  if (!student) return alert('Student not found in cache. Search again.');

  const term = document.getElementById('rep-term').value;
  const year = document.getElementById('rep-year').value;
  const area = document.getElementById('report-area');

  try {
    const r = await adminApi(
      `/reports?student_id=${id}&class_id=${student.class_id}&term=${encodeURIComponent(term)}&year=${year}`
    );
    area.innerHTML = reportHTML(r);
  } catch (err) {
    area.innerHTML = `<div class="alert error">${escapeHtml(err.message)}</div>`;
  }
}


// ===== REPORT CARD HTML =====
const REPORT_SCHOOL = {
  name: 'ST. MUGAGA SECONDARY SCHOOL',
  address: 'P.O. Box ______, Jalamba, Uganda',
  contact: 'Tel: +256 ___ ___ ___  |  Email: ____________________',
  logoHtml: '<div class="logo-slot">LOGO</div>'
};

function descriptorFor(score) {
  if (score >= 2.5) return 'Outstanding';
  if (score >= 1.5) return 'Moderate';
  if (score >= 0.9) return 'Basic';
  return 'Below basic';
}

const fmt = (value, decimals = 1) =>
  value === null || value === undefined || isNaN(value) ? '-' : Number(value).toFixed(decimals);

function dateText(value) {
  if (!value) return '-';
  const [y, m, d] = String(value).split('-');
  return `${d}/${m}/${y}`;
}

function money(value) {
  return value === null || value === undefined
    ? '-'
    : 'UGX ' + Number(value).toLocaleString('en-UG');
}

function reportHeader(r, title) {
  const photo = r.student?.passport_photo
    ? `<img src="${r.student.passport_photo}" alt="Student photo" class="report-photo">`
    : `<div class="report-photo report-photo-empty">No photo</div>`;

  return `
    <div class="rc-header">
      ${REPORT_SCHOOL.logoHtml}
      <div class="rc-school">
        <h2>${escapeHtml(REPORT_SCHOOL.name)}</h2>
        <p>${escapeHtml(REPORT_SCHOOL.address)}<br>${escapeHtml(REPORT_SCHOOL.contact)}</p>
        <h3>${title}</h3>
        <p class="rc-term">Term: ${escapeHtml(r.term || '')} &nbsp;|&nbsp; Year: ${r.year || ''}</p>
      </div>
      ${photo}
    </div>`;
}

function studentInfo(r) {
  const s = r.student || {};
  const t = r.termDetails || {};
  const present = t.days_present ?? '-';
  const absent = t.days_absent ?? '-';
  const total = (t.days_present != null && t.days_absent != null)
    ? Number(t.days_present) + Number(t.days_absent)
    : '-';

  return `
    <div class="rc-info-row">
      <table class="rc-info">
        <tr>
          <td><strong>NAME:</strong> ${escapeHtml(s.full_name || '')}</td>
          <td><strong>GENDER:</strong> ${escapeHtml(s.gender || '')}</td>
        </tr>
        <tr>
          <td><strong>LIN:</strong> ${escapeHtml(s.lin || '-')}</td>
          <td><strong>ADMISSION NO:</strong> ${escapeHtml(s.admission_no || '')}</td>
        </tr>
        <tr>
          <td><strong>CLASS &amp; STREAM:</strong> ${escapeHtml(s.class_name || '')} ${escapeHtml(s.stream || '')}</td>
          <td><strong>HOUSE:</strong> ${escapeHtml(s.house || '-')}</td>
        </tr>
      </table>
      <table class="rc-att">
        <tr><th colspan="2">ATTENDANCE</th></tr>
        <tr><td>Days present</td><td>${present}</td></tr>
        <tr><td>Days absent</td><td>${absent}</td></tr>
        <tr><td><strong>Total</strong></td><td><strong>${total}</strong></td></tr>
      </table>
    </div>`;
}

function oLevelBody(r) {
  const subjects = r.subjects || [];
  const rows = subjects.map(s => {
    const topics = (s.topics && s.topics.length) ? s.topics : [{ topic: '', score: null }];
    const n = topics.length;

    return topics.map((t, i) => `
      <tr>
        ${i === 0 ? `<td rowspan="${n}"><strong>${escapeHtml(s.subject || '')}</strong></td>` : ''}
        <td>${t.score === null ? '' : escapeHtml(t.topic || '')}</td>
        <td class="num">${t.score === null ? '-' : fmt(t.score)}</td>
        <td>${t.score === null ? '-' : descriptorFor(t.score)}</td>
        ${i === 0 ? `
          <td rowspan="${n}">${escapeHtml(s.generic_skills || '')}</td>
          <td rowspan="${n}">${escapeHtml(s.remark || '')}</td>
          <td rowspan="${n}">${escapeHtml(s.teacher || '')}</td>` : ''}
      </tr>`).join('');
  }).join('');

  return `
    <table class="rc-table">
      <thead>
        <tr>
          <th>SUBJECT</th>
          <th>TOPIC AND COMPETENCY</th>
          <th>SCORE</th>
          <th>DESCRIPTOR</th>
          <th>GENERIC SKILLS</th>
          <th>REMARKS</th>
          <th>TEACHER / SIGN</th>
        </tr>
      </thead>
      <tbody>${rows || '<tr><td colspan="7">No subjects</td></tr>'}</tbody>
    </table>`;
}

function oLevelFooter(r) {
  return `
    <p class="rc-key"><strong>Key:</strong> 2.5 to 3.0 Outstanding &nbsp;|&nbsp;
      1.5 to 2.49 Moderate &nbsp;|&nbsp; 0.9 to 1.49 Basic</p>
    <p><strong>Next term begins:</strong> ${dateText((r.termDetails || {}).next_term_begins)}</p>`;
}

function aLevelBody(r) {
  const subjects = r.subjects || [];
  const rows = subjects.map(s => `
    <tr>
      <td><strong>${escapeHtml(s.subject || '')}</strong></td>
      <td class="num">${fmt(s.f1)}</td>
      <td class="num">${fmt(s.f2)}</td>
      <td class="num">${fmt(s.f3)}</td>
      <td class="num">${fmt(s.formativeAvg, 2)}</td>
      <td class="num">${fmt(s.formativePct)}</td>
      <td class="num">${fmt(s.eot, 0)}</td>
      <td class="num">${fmt(s.eotPct)}</td>
      <td class="num"><strong>${fmt(s.total)}</strong></td>
      <td class="grade">${escapeHtml(s.grade || '')}</td>
      <td>${escapeHtml(s.remark || '')}</td>
      <td>${escapeHtml(s.teacher || '')}</td>
    </tr>`).join('');

  const summary = r.summary || {};

  return `
    <table class="rc-table">
      <thead>
        <tr>
          <th>SUBJECT</th><th>A1</th><th>A2</th><th>A3</th><th>AVG</th><th>20%</th>
          <th>EOT</th><th>80%</th><th>100%</th><th>GRADE</th><th>COMMENT</th><th>TEACHER</th>
        </tr>
      </thead>
      <tbody>
        ${rows || '<tr><td colspan="12">No subjects</td></tr>'}
        <tr>
          <td><strong>AVERAGE</strong></td>
          <td colspan="7"></td>
          <td class="num"><strong>${fmt(summary.average)}</strong></td>
          <td class="grade">${escapeHtml(summary.grade || '')}</td>
          <td colspan="2"></td>
        </tr>
      </tbody>
    </table>`;
}

function gradeScaleTable(scale) {
  if (!scale || !scale.length) return '<p>No grading scale available</p>';

  const bands = [...scale].sort((a, b) => b.min_mark - a.min_mark);

  const ranges = bands.map((b, i) => {
    const top = i === 0 ? 100 : Math.floor(bands[i - 1].min_mark) - 1;
    return `${Math.floor(b.min_mark)} to ${top}`;
  });

  return `
    <table class="rc-key-table">
      <tr><th>GRADE</th>${bands.map(b => `<th>${escapeHtml(b.grade)}</th>`).join('')}</tr>
      <tr><th>SCORES</th>${ranges.map(x => `<td>${x}</td>`).join('')}</tr>
    </table>`;
}

function aLevelFooter(r) {
  const p = r.termDetails || {};
  const projectPct = p.project_score != null
    ? fmt(Number(p.project_score) * 10, 0) + '%'
    : '-';

  return `
    <h4>Project work</h4>
    <table class="rc-table">
      <thead>
        <tr>
          <th>TITLE</th><th>AVERAGE SCORE (/10)</th><th>OUT OF 100</th>
          <th>GRADE</th><th>REMARKS</th><th>TEACHER</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>${escapeHtml(p.project_title || '-')}</td>
          <td class="num">${fmt(p.project_score)}</td>
          <td class="num">${projectPct}</td>
          <td class="grade">${escapeHtml(p.project_grade || '-')}</td>
          <td>${escapeHtml(p.project_remark || '')}</td>
          <td>${escapeHtml(p.project_teacher || '')}</td>
        </tr>
      </tbody>
    </table>

    <h4>Grading scale</h4>
    ${gradeScaleTable(r.gradeScale)}

    <table class="rc-comments">
      <tr><td><strong>Class teacher's comment:</strong><br>${escapeHtml(p.class_teacher_comment || '')}</td></tr>
      <tr><td><strong>Headteacher's comment:</strong><br>${escapeHtml(p.headteacher_comment || '')}</td></tr>
    </table>

    <table class="rc-fees">
      <tr><th>TERM ENDED ON</th><th>NEXT TERM BEGINS</th><th>FEES BALANCE</th><th>FEES NEXT TERM</th></tr>
      <tr>
        <td>${dateText(p.term_ended_on)}</td>
        <td>${dateText(p.next_term_begins)}</td>
        <td>${money(p.fees_balance)}</td>
        <td>${money(p.fees_next_term)}</td>
      </tr>
    </table>`;
}

function reportHTML(r) {
  if (!r) return '<div class="alert error">No report data received</div>';

  const isA = r.section === 'A Level';
  const title = isA ? 'A LEVEL END OF TERM REPORT CARD' : "LEARNER'S END OF TERM REPORT CARD";

  return `
    <div class="report rc" id="printable">
      ${reportHeader(r, title)}
      ${studentInfo(r)}
      ${isA ? aLevelBody(r) : oLevelBody(r)}
      ${isA ? aLevelFooter(r) : oLevelFooter(r)}
      <div class="signatures">
        <span>Class teacher: ______________</span>
        <span>Headteacher: ______________</span>
      </div>
    </div>
    <button class="btn btn-primary no-print" style="margin-top: 12px;"
      onclick="window.print()">Print report</button>`;
}


// ===== START =====
populateLookups().catch(err => alert('Could not load classes or subjects: ' + err.message));
loadPosts();