let currentUser = null;
let editingTaskId = null;
let pendingFileTaskId = null;
let pendingFileName = null;
let alarmTimer = null;
const notifiedTasks = new Set();

const apiUrl = (action) => `api/index.php?action=${encodeURIComponent(action)}`;
async function api(action, options = {}) {
    const response = await fetch(apiUrl(action), { credentials: 'same-origin', ...options });
    const rawText = await response.text();
    let result;
    try {
        result = JSON.parse(rawText);
    } catch {
        console.error('API Error: Server returned non-JSON response:', rawText);
        const cleanMsg = rawText.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        throw new Error(cleanMsg || 'The server returned an invalid response. Check your PHP configuration.');
    }
    if (!response.ok || result.success === false) throw new Error(result.message || 'Request failed.');
    return result;
}
function jsonOptions(data) {
    return { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) };
}
function switchAuthMode(mode) {
    document.getElementById('loginTab').classList.toggle('active', mode === 'login');
    document.getElementById('registerTab').classList.toggle('active', mode === 'register');
    document.getElementById('loginForm').classList.toggle('hidden', mode !== 'login');
    document.getElementById('registerForm').classList.toggle('hidden', mode !== 'register');
    hideError();
}
function showError(message) {
    const box = document.getElementById('errorBox');
    box.classList.remove('hidden');
    document.getElementById('errorText').textContent = message;
}
function hideError() { document.getElementById('errorBox').classList.add('hidden'); }

async function handleRegister(event) {
    event.preventDefault();
    hideError();
    const username = document.getElementById('regUsername').value.trim();
    const password = document.getElementById('regPassword').value;
    try {
        await api('register', jsonOptions({ username, password }));
        alert('Account created! Please login.');
        document.getElementById('registerForm').reset();
        document.getElementById('loginUsername').value = username;
        switchAuthMode('login');
    } catch (error) { showError(error.message); }
}
async function handleLogin(event) {
    event.preventDefault();
    hideError();
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;
    try {
        const result = await api('login', jsonOptions({ username, password }));
        currentUser = result.username;
        document.getElementById('authPage').classList.add('hidden');
        document.getElementById('questionnaireModal').classList.add('active');
    } catch (error) { showError(error.message); }
}
function handleQuestionnaireSubmit(event) {
    event.preventDefault();
    const motivations = [
        'Even when things feel heavy and tiring, you can do this! Your hard work today is building a beautiful future. Meow! 🐾🤍',
        'You are doing amazingly well. Your determination will take you to the top. Keep fighting! ✨🐱',
        'Don’t rush too much; what matters is moving forward step by step. Keep going! 🤍💪'
    ];
    document.getElementById('motivationText').innerText = motivations[Math.floor(Math.random() * motivations.length)];
    document.getElementById('questionnaireModal').classList.remove('active');
    document.getElementById('motivationModal').classList.add('active');
}
async function finishCheckIn() {
    document.getElementById('motivationModal').classList.remove('active');
    if ('Notification' in window && Notification.permission === 'default') {
        try { await Notification.requestPermission(); } catch {}
    }
    showDashboard();
}
function showDashboard() {
    document.getElementById('authPage').classList.add('hidden');
    document.getElementById('questionnaireModal').classList.remove('active');
    document.getElementById('motivationModal').classList.remove('active');
    document.getElementById('dashboardPage').classList.remove('hidden');
    document.getElementById('currentUserDisplay').textContent = `Welcome, ${currentUser}!`;
    loadTasks();
    if (alarmTimer) clearInterval(alarmTimer);
    alarmTimer = setInterval(checkAlarms, 10000);
}
async function checkSession() {
    try {
        const result = await api('session');
        if (result.loggedIn) {
            currentUser = result.username;
            if ('Notification' in window && Notification.permission === 'default') {
                try { await Notification.requestPermission(); } catch {}
            }
            showDashboard();
        }
    } catch (error) { console.error(error); }
}
async function logout() {
    try { await api('logout', { method: 'POST' }); } catch {}
    currentUser = null;
    if (alarmTimer) clearInterval(alarmTimer);
    document.getElementById('dashboardPage').classList.add('hidden');
    document.getElementById('authPage').classList.remove('hidden');
    document.getElementById('loginForm').reset();
    document.getElementById('registerForm').reset();
}
async function deleteAccount() {
    if (!currentUser) return;
    if (!confirm(`Are you sure you want to delete your account "${currentUser}"? All your tasks and uploaded files will be permanently deleted!`)) return;
    try {
        await api('delete_account', { method: 'POST' });
        alert('Your account and all associated data have been successfully deleted.');
        await logout();
    } catch (error) { alert(error.message); }
}

function openTaskModal(task = null) {
    document.getElementById('taskModal').classList.add('active');
    document.getElementById('taskForm').reset();
    document.getElementById('taskFile').value = '';
    editingTaskId = null;
    if (task) {
        editingTaskId = Number(task.id);
        document.getElementById('modalTitle').textContent = 'Edit Task';
        document.getElementById('taskTitle').value = task.title || '';
        document.getElementById('taskSubject').value = task.subject || '';
        document.getElementById('taskTeacher').value = task.teacher || '';
        document.getElementById('taskDeadline').value = task.deadline || '';
        document.getElementById('taskStatus').value = task.status || 'todo';
        document.getElementById('taskNotes').value = task.notes || '';
    } else {
        document.getElementById('modalTitle').textContent = 'Add New Task';
    }
}
function closeTaskModal() {
    document.getElementById('taskModal').classList.remove('active');
    editingTaskId = null;
}
function closeModalOnOverlay(event) { if (event.target.id === 'taskModal') closeTaskModal(); }

function readFileAsBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve({ name: file.name, data: reader.result });
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

async function saveTask(event) {
    event.preventDefault();
    const fileInput = document.getElementById('taskFile');
    const selectedFile = fileInput.files && fileInput.files[0];

    let filePayload = null;
    if (selectedFile) {
        if (selectedFile.size > 10 * 1024 * 1024) {
            alert('File must be 10 MB or smaller.');
            return;
        }
        try {
            filePayload = await readFileAsBase64(selectedFile);
        } catch {
            alert('Failed to read the selected file.');
            return;
        }
    }

    const payload = {
        id: editingTaskId || 0,
        title: document.getElementById('taskTitle').value.trim(),
        subject: document.getElementById('taskSubject').value.trim(),
        teacher: document.getElementById('taskTeacher').value.trim(),
        deadline: document.getElementById('taskDeadline').value,
        status: document.getElementById('taskStatus').value,
        notes: document.getElementById('taskNotes').value.trim(),
        file: filePayload
    };

    try {
        await api('save_task', jsonOptions(payload));
        closeTaskModal();
        await loadTasks();
    } catch (error) {
        alert(error.message);
    }
}

async function loadTasks() {
    if (!currentUser) return;
    const container = document.getElementById('tasksContainer');
    const emptyState = document.getElementById('emptyState');
    try {
        const result = await api('list_tasks');
        const tasks = result.tasks || [];
        container.innerHTML = '';
        updateStats(tasks);
        if (!tasks.length) {
            emptyState.style.display = 'block';
            return;
        }
        emptyState.style.display = 'none';
        tasks.forEach(task => container.appendChild(createTaskCard(task)));
    } catch (error) {
        showError(error.message);
    }
}
function updateStats(tasks) {
    document.getElementById('totalTasks').textContent = tasks.length;
    document.getElementById('progressTasks').textContent = tasks.filter(t => t.status === 'progress').length;
    document.getElementById('doneTasks').textContent = tasks.filter(t => t.status === 'done').length;
}
function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
}
function createTaskCard(task) {
    const div = document.createElement('div');
    div.className = 'task-card';
    const deadlineDate = task.deadline ? new Date(task.deadline).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'No deadline';
    const statusClass = task.status === 'progress' ? 'status-progress' : task.status === 'done' ? 'status-done' : 'status-todo';
    const statusText = task.status === 'todo' ? 'To Do' : task.status === 'progress' ? 'In Progress' : 'Submitted ✅';
    const fileHTML = task.fileName ? `<button type="button" class="file-link" data-file-id="${Number(task.id)}"><i class="fas fa-paperclip"></i> ${escapeHTML(task.fileName)}</button>` : '';
    div.innerHTML = `
        <span class="status-badge ${statusClass}">${statusText}</span>
        <h3>${escapeHTML(task.title)}</h3>
        <p class="subject">${escapeHTML(task.subject)}</p>
        ${task.teacher ? `<p class="teacher"><i class="fas fa-user"></i> ${escapeHTML(task.teacher)}</p>` : ''}
        <p class="deadline"><i class="fas fa-clock"></i> ${escapeHTML(deadlineDate)}</p>
        ${task.notes ? `<p style="font-size:14px; color:#666; margin-bottom:8px;">${escapeHTML(task.notes)}</p>` : ''}
        ${fileHTML}
        <div class="task-actions">
            <button class="btn btn-icon btn-edit" type="button" data-edit-id="${Number(task.id)}" aria-label="Edit task"><i class="fas fa-edit"></i></button>
            <button class="btn btn-icon btn-delete" type="button" data-delete-id="${Number(task.id)}" aria-label="Delete task"><i class="fas fa-trash"></i></button>
        </div>`;
    div.querySelector('[data-edit-id]').addEventListener('click', () => openTaskModal(task));
    div.querySelector('[data-delete-id]').addEventListener('click', () => deleteTask(Number(task.id)));
    const fileButton = div.querySelector('[data-file-id]');
    if (fileButton) fileButton.addEventListener('click', () => promptViewFile(Number(task.id), task.fileName));
    return div;
}
async function deleteTask(id) {
    if (!confirm('Delete this task?')) return;
    try {
        await api('delete_task', jsonOptions({ id }));
        await loadTasks();
    } catch (error) { alert(error.message); }
}

async function promptViewFile(taskId, fileName) {
    pendingFileTaskId = taskId;
    pendingFileName = fileName;
    document.getElementById('confirmFileName').textContent = fileName;
    document.getElementById('confirmViewModal').classList.add('active');
}
async function confirmViewYes() {
    document.getElementById('confirmViewModal').classList.remove('active');
    if (!pendingFileTaskId) return;
    try {
        const result = await api(`view_file&id=${encodeURIComponent(pendingFileTaskId)}`);
        openFilePreview(result.fileUrl, result.fileName);
    } catch (error) { alert(error.message); }
}
function confirmViewNo() {
    document.getElementById('confirmViewModal').classList.remove('active');
    pendingFileTaskId = null;
    pendingFileName = null;
}
function openFilePreview(fileUrl, fileName) {
    document.getElementById('previewModalTitle').textContent = fileName;
    const container = document.getElementById('previewContainer');
    const safeUrl = encodeURI(fileUrl);
    if (/\.(jpeg|jpg|gif|png|webp)$/i.test(fileName)) {
        container.innerHTML = `<img src="${safeUrl}" alt="${escapeHTML(fileName)}" style="max-width:100%; max-height:50vh; border-radius:8px;">`;
    } else {
        container.innerHTML = `<p>File: <strong>${escapeHTML(fileName)}</strong></p>`;
    }
    document.getElementById('downloadPreviewBtn').onclick = () => {
        const a = document.createElement('a');
        a.href = safeUrl;
        a.download = fileName;
        a.rel = 'noopener';
        a.click();
    };
    document.getElementById('filePreviewModal').classList.add('active');
}
function closeFilePreview() {
    document.getElementById('filePreviewModal').classList.remove('active');
}
async function checkAlarms() {
    if (!currentUser) return;
    try {
        const result = await api('list_tasks');
        const now = Date.now();
        (result.tasks || []).forEach(task => {
            if (task.deadline && task.status !== 'done' && new Date(task.deadline).getTime() <= now && !notifiedTasks.has(Number(task.id))) {
                notifiedTasks.add(Number(task.id));
                triggerAlarm(task);
            }
        });
    } catch (error) { console.error(error); }
}
function triggerAlarm(task) {
    document.getElementById('alarmTitle').textContent = task.title;
    document.getElementById('alarmSubject').textContent = `Subject: ${task.subject} | Deadline reached!`;
    document.getElementById('alarmModal').classList.add('active');
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('⏰ TaskFlow Alert: ' + task.title, { body: `Subject: ${task.subject}` });
    }
}
function dismissAlarm() { document.getElementById('alarmModal').classList.remove('active'); }

document.addEventListener('DOMContentLoaded', checkSession);