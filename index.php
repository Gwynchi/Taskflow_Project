<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow | Student Task & Project Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- AUTH PAGE -->
    <div id="authPage" class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-logo">
                <h1><i class="fas fa-graduation-cap"></i> TaskFlow</h1>
                <p>Student Task & Project Management System</p>
            </div>

            <!-- Error Box -->
            <div id="errorBox" class="error-box hidden">
                <span id="errorText"></span>
                <button onclick="hideError()">&times;</button>
            </div>

            <!-- Tabs -->
            <div class="auth-tabs">
                <button id="loginTab" class="tab-btn active" onclick="switchAuthMode('login')">Login</button>
                <button id="registerTab" class="tab-btn" onclick="switchAuthMode('register')">Register</button>
            </div>

            <!-- Login Form -->
            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label for="loginUsername">Username</label>
                    <input type="text" id="loginUsername" placeholder="Enter username" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login to Dashboard</button>
            </form>

            <!-- Register Form -->
            <form id="registerForm" class="hidden" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label for="regUsername">Username</label>
                    <input type="text" id="regUsername" placeholder="Choose a username" required>
                </div>
                <div class="form-group">
                    <label for="regPassword">Password</label>
                    <input type="password" id="regPassword" placeholder="Create a password" required>
                </div>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
        </div>
    </div>

    <!-- 3 QUESTIONS CHECK-IN MODAL -->
    <div id="questionnaireModal" class="modal-overlay">
        <div class="modal" style="text-align: center;">
            <h3>🌱 A Quick Check-In With Yourself</h3>
            <p style="color: var(--text-light); margin-bottom: 20px; font-size: 14px;">Let's answer 3 quick questions before starting:</p>
            <form onsubmit="handleQuestionnaireSubmit(event)">
                <div class="form-group" style="text-align: left;">
                    <label>1. What is your primary motivation for studying?</label>
                    <input type="text" id="q1" class="form-control" required>
                </div>
                <div class="form-group" style="text-align: left;">
                    <label>2. On a scale of 1-10, how is your energy level today?</label>
                    <input type="number" id="q2" class="form-control" min="1" max="10" required>
                </div>
                <div class="form-group" style="text-align: left;">
                    <label>3. What small victory do you want to achieve?</label>
                    <input type="text" id="q3" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Proceed ✨</button>
            </form>
        </div>
    </div>

    <!-- MOTIVATION MODAL (WHITE CAT) -->
    <div id="motivationModal" class="modal-overlay">
        <div class="modal" style="text-align: center; border-top: 6px solid var(--primary);">
            <div class="cat-avatar">🐱🤍</div>
            <h3 style="color: var(--primary);">"Meow! Hi, Friend!"</h3>
            <p style="font-size: 0.85rem; color: var(--primary); font-weight: 700; margin-bottom: 0.8rem;">
                - Said the cute white cat who will always support you!
            </p>
            <blockquote id="motivationText" style="font-style: italic; background: #EBF2EF; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; line-height: 1.5; font-size: 0.95rem;"></blockquote>
            <button class="btn btn-primary" onclick="finishCheckIn()">Thank You! Open My Dashboard 🚀</button>
        </div>
    </div>

    <!-- DASHBOARD PAGE -->
    <div id="dashboardPage" class="container hidden">
        <div class="header">
            <h2><i class="fas fa-graduation-cap"></i> TaskFlow Dashboard</h2>
            <div class="user-info">
                <span id="currentUserDisplay"></span>
                <button class="btn btn-sm btn-outline" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
                <button class="btn btn-sm" onclick="deleteAccount()" style="background: var(--danger); color: white; border: none;">
                    <i class="fas fa-user-slash"></i> Delete Account
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Tasks</h3>
                <div class="number" id="totalTasks">0</div>
            </div>
            <div class="stat-card">
                <h3>In Progress</h3>
                <div class="number" id="progressTasks">0</div>
            </div>
            <div class="stat-card">
                <h3>Submitted</h3>
                <div class="number" id="doneTasks">0</div>
            </div>
        </div>

        <!-- Tasks Section -->
        <div class="section-header">
            <h3>My Tasks & Projects</h3>
            <button class="btn btn-sm btn-success" onclick="openTaskModal()">
                <i class="fas fa-plus"></i> Add New Task
            </button>
        </div>

        <!-- Tasks List -->
        <div id="tasksContainer" class="tasks-grid"></div>

        <!-- Empty State -->
        <div id="emptyState" class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>No tasks yet</h3>
            <p>Click "Add New Task" to get started!</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>TaskFlow — Student Task & Project Management System | Capstone Project</p>
        </div>
    </div>

    <!-- TASK MODAL -->
    <div id="taskModal" class="modal-overlay" onclick="closeModalOnOverlay(event)">
        <div class="modal">
            <h2 id="modalTitle">Add New Task</h2>
            <form id="taskForm" onsubmit="saveTask(event)">
                <input type="hidden" id="taskId">
                
                <div class="form-group">
                    <label for="taskTitle">Task / Project Title *</label>
                    <input type="text" id="taskTitle" placeholder="e.g. Capstone Documentation" required>
                </div>

                <div class="form-group">
                    <label for="taskSubject">Subject / Course *</label>
                    <input type="text" id="taskSubject" placeholder="e.g. Web Development 2" required>
                </div>

                <div class="form-group">
                    <label for="taskTeacher">Teacher / Professor</label>
                    <input type="text" id="taskTeacher" placeholder="e.g. Prof. Gomez">
                </div>

                <div class="form-group">
                    <label for="taskDeadline">⏰ Work Schedule & Deadline *</label>
                    <input type="datetime-local" id="taskDeadline" required>
                </div>

                <div class="form-group">
                    <label for="taskStatus">Status</label>
                    <select id="taskStatus">
                        <option value="todo">To Do</option>
                        <option value="progress">In Progress</option>
                        <option value="done">Submitted / Done</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="taskNotes">Notes / Description</label>
                    <textarea id="taskNotes" rows="3" placeholder="Details about the task..."></textarea>
                </div>

                <div class="form-group">
                    <label for="taskFile">Attach File / Image</label>
                    <input type="file" id="taskFile" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx">
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeTaskModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW CONFIRMATION MODAL (YES / NO) -->
    <div id="confirmViewModal" class="modal-overlay">
        <div class="modal" style="max-width: 400px; text-align: center;">
            <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--primary); margin-bottom: 16px;"></i>
            <h3 style="margin-bottom: 12px; color: var(--text-dark);">Do you want to view this file?</h3>
            <p id="confirmFileName" style="font-weight: 600; color: var(--primary); margin-bottom: 24px; word-break: break-all;"></p>
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-primary" onclick="confirmViewYes()" style="flex: 1;">Yes</button>
                <button class="btn btn-cancel" onclick="confirmViewNo()" style="flex: 1;">No</button>
            </div>
        </div>
    </div>

    <!-- FILE PREVIEW MODAL -->
    <div id="filePreviewModal" class="modal-overlay">
        <div class="modal" style="max-width: 650px; text-align: center;">
            <h3 id="previewModalTitle" style="margin-bottom: 16px; color: var(--primary);">File Preview</h3>
            <div id="previewContainer" style="margin-bottom: 20px; max-height: 55vh; overflow: auto; background: var(--bg-color); padding: 12px; border-radius: 8px; display: flex; justify-content: center; align-items: center;"></div>
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-primary" id="downloadPreviewBtn" style="flex: 1;"><i class="fas fa-download"></i> Download File</button>
                <button class="btn btn-cancel" onclick="closeFilePreview()" style="flex: 1;">Close</button>
            </div>
        </div>
    </div>

    <!-- ALARM NOTIFICATION MODAL -->
    <div id="alarmModal" class="modal-overlay">
        <div class="modal" style="max-width: 420px; text-align: center; border-top: 6px solid var(--danger);">
            <div style="font-size: 3.5rem; color: var(--danger); margin-bottom: 12px;"><i class="fas fa-bell fa-shake"></i></div>
            <h3 style="color: var(--danger); margin-bottom: 8px;">⏰ ALARM REMINDER!</h3>
            <p style="color: var(--text-light); font-size: 14px; margin-bottom: 16px;">Dumating na ang takdang oras para sa gawain o proyekto mo:</p>
            <div id="alarmTaskInfo" style="background: var(--bg-color); padding: 16px; border-radius: 8px; margin-bottom: 20px; text-align: left;">
                <h4 id="alarmTitle" style="color: var(--text-dark); margin-bottom: 6px;"></h4>
                <p id="alarmSubject" style="font-size: 14px; color: var(--primary); font-weight: 600;"></p>
            </div>
            <button class="btn btn-primary" onclick="dismissAlarm()">OK, Nakuha ko na! 👍</button>
        </div>
    </div>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
