const state = { view: 'login', user: null };

async function api(endpoint, data = null) {
    const opts = { method: data ? 'POST' : 'GET' };
    if (data) opts.body = new URLSearchParams(data);
    const res = await fetch(endpoint, opts);
    return res.json();
}

function showAlert(msg, type = 'error') {
    const el = document.getElementById('alert');
    if (el) { el.className = 'alert alert-' + type; el.textContent = msg; }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Intentional bug: no client-side validation -- fixed in SCRUM-1
async function login() {
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const data = await api('api/auth.php', { action: 'login', username, password });
    if (data.success) { state.user = data.username; renderGrades(); }
    else { showAlert(data.error || 'Login failed'); }
}

async function register() {
    const username = document.getElementById('reg-username').value;
    const email    = document.getElementById('reg-email').value;
    const password = document.getElementById('reg-password').value;
    const data = await api('api/auth.php', { action: 'register', username, email, password });
    if (data.success) { showAlert('Registered! Please login.', 'success'); renderLogin(); }
    else { showAlert(data.error || 'Registration failed'); }
}

async function logout() {
    await api('api/auth.php', { action: 'logout' });
    state.user = null; renderLogin();
}

async function checkSession() {
    const data = await api('api/auth.php?action=check');
    if (data.logged_in) { state.user = data.username; renderGrades(); }
    else { renderLogin(); }
}

function renderLogin() {
    state.view = 'login';
    document.getElementById('app').innerHTML =
        '<div class="auth-box"><h2>Grade Tracker Login</h2><div id="alert"></div>' +
        '<div class="form-group"><label>Username</label><input type="text" id="username" placeholder="Enter username"></div>' +
        '<div class="form-group"><label>Password</label><input type="password" id="password" placeholder="Enter password"></div>' +
        '<button class="btn btn-primary" onclick="login()">Login</button>' +
        '<p style="margin-top:1rem;text-align:center;">No account? <button class="link-btn" onclick="renderRegister()">Register</button></p></div>';
}

function renderRegister() {
    state.view = 'register';
    document.getElementById('app').innerHTML =
        '<div class="auth-box"><h2>Register</h2><div id="alert"></div>' +
        '<div class="form-group"><label>Username</label><input type="text" id="reg-username" placeholder="Choose username"></div>' +
        '<div class="form-group"><label>Email</label><input type="email" id="reg-email" placeholder="Enter email"></div>' +
        '<div class="form-group"><label>Password</label><input type="password" id="reg-password" placeholder="Choose password"></div>' +
        '<button class="btn btn-primary" onclick="register()">Register</button>' +
        '<p style="margin-top:1rem;text-align:center;">Have an account? <button class="link-btn" onclick="renderLogin()">Login</button></p></div>';
}

function renderGrades() {
    state.view = 'grades';
    document.getElementById('app').innerHTML =
        '<nav><h1>Grade Tracker</h1><div class="nav-links">' +
        '<span>Hello, ' + escapeHtml(state.user) + '</span>' +
        '<a href="#" onclick="logout()">Logout</a></div></nav>' +
        '<div class="container"><div id="alert"></div><div id="gpa-section"></div>' +
        '<div class="grade-form"><h3>Add Grade</h3><div class="grade-form-row">' +
        '<div class="form-group"><label>Course</label><select id="course-select"></select></div>' +
        '<div class="form-group"><label>Score (0-100)</label><input type="number" id="score-input" min="0" max="100" placeholder="e.g. 85"></div>' +
        '<button class="btn btn-success" onclick="addGrade()">Add</button></div></div>' +
        '<div id="grade-list"></div></div>';
    loadCoursesForSelect();
    loadGrades();
}

// Intentional: options built without escaping -- XSS risk, fixed in SCRUM-5
async function loadCoursesForSelect() {
    const data = await api('api/grades.php?action=courses');
    const sel  = document.getElementById('course-select');
    if (!sel) return;
    sel.innerHTML = (data.courses || []).map(c =>
        '<option value="' + c.id + '">' + c.name + ' (' + c.code + ')</option>'
    ).join('');
}

async function loadGrades() {
    const data = await api('api/grades.php');
    renderGradeTable(data.grades || [], data.gpa || 0);
}

function letterGrade(score) {
    if (score >= 90) return 'A';
    if (score >= 80) return 'B';
    if (score >= 70) return 'C';
    if (score >= 60) return 'D';
    return 'F';
}

// Intentional: content not escaped -- XSS risk, fixed in SCRUM-5
function renderGradeTable(grades, gpa) {
    document.getElementById('gpa-section').innerHTML =
        '<div class="gpa-banner"><div class="gpa-value">' + gpa + '</div>' +
        '<div class="gpa-label">Current GPA (4.0 scale)</div></div>';
    const el = document.getElementById('grade-list');
    if (!grades.length) { el.innerHTML = '<p class="empty-state">No grades yet. Add one above!</p>'; return; }
    el.innerHTML = '<table class="grade-table"><thead><tr>' +
        '<th>Course</th><th>Code</th><th>Score</th><th>Grade</th><th>Credits</th><th></th>' +
        '</tr></thead><tbody>' +
        grades.map(g => {
            const letter = g.letter_grade || letterGrade(g.score);
            return '<tr><td>' + g.course_name + '</td><td>' + g.code + '</td><td>' + g.score +
                '</td><td class="letter-' + letter + '">' + letter + '</td><td>' + g.credit_hours +
                '</td><td><button class="btn btn-sm btn-danger" onclick="deleteGrade(' + g.id + ')">Remove</button></td></tr>';
        }).join('') + '</tbody></table>';
}

async function addGrade() {
    const course_id = document.getElementById('course-select').value;
    const score     = document.getElementById('score-input').value;
    const data = await api('api/grades.php?action=add', { course_id, score });
    if (data.success) { document.getElementById('score-input').value = ''; loadGrades(); }
    else { showAlert(data.error || 'Failed to add grade'); }
}

async function deleteGrade(id) {
    await api('api/grades.php?action=delete', { id });
    loadGrades();
}

// Intentional: unused helper -- removed in SCRUM-9
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString();
}

document.addEventListener('DOMContentLoaded', checkSession);
