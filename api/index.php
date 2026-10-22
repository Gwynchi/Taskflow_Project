<?php
// Buffer all output to prevent unexpected notices/whitespace from breaking JSON responses
ob_start();

// Disable HTML error output directly to browser (prevents HTML inside JSON responses)
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Ensure fatal errors are captured and returned cleanly as JSON
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (function_exists('ob_get_length') && ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'PHP Server Error: ' . $error['message'] . ' in ' . basename($error['file']) . ' on line ' . $error['line']
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

function respond(array $data, int $status = 200): never {
    if (function_exists('ob_get_length') && ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function input_data(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : $_POST;
}

function require_login(): string {
    if (empty($_SESSION['username'])) {
        respond(['success' => false, 'message' => 'Please log in first.'], 401);
    }
    return (string) $_SESSION['username'];
}

function clean_string(mixed $value, int $max = 5000): string {
    $value = trim((string)($value ?? ''));
    return mb_substr($value, 0, $max);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data = input_data();

try {
    switch ($action) {
        case 'session':
            respond(['success' => true, 'loggedIn' => !empty($_SESSION['username']), 'username' => $_SESSION['username'] ?? null]);

        case 'register':
            $username = clean_string($data['username'] ?? '', 50);
            $password = (string)($data['password'] ?? '');
            if (mb_strlen($username) < 3 || mb_strlen($password) < 8) {
                respond(['success' => false, 'message' => 'Username must be at least 3 characters and password must be at least 8 characters.'], 422);
            }
            $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                respond(['success' => false, 'message' => 'Username already exists!'], 409);
            }

            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            respond(['success' => true, 'message' => 'Account created! Please login.']);

        case 'login':
            $username = clean_string($data['username'] ?? '', 50);
            $password = (string)($data['password'] ?? '');
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password_hash'])) {
                respond(['success' => false, 'message' => 'Invalid username or password!'], 401);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            respond(['success' => true, 'username' => $user['username']]);

        case 'logout':
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            respond(['success' => true]);

        case 'delete_account':
            $username = require_login();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            if ($user) {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM tasks WHERE user_id = ?');
                $stmt->execute([(int)$user['id']]);
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([(int)$user['id']]);
                $pdo->commit();
            }
            $_SESSION = [];
            session_destroy();
            respond(['success' => true, 'message' => 'Your account and all associated tasks have been deleted.']);

        case 'list_tasks':
            $username = require_login();
            $stmt = $pdo->prepare('SELECT u.id FROM users u WHERE u.username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            $stmt = $pdo->prepare('SELECT t.id, t.title, t.subject, t.teacher, DATE_FORMAT(t.deadline, "%Y-%m-%dT%H:%i") AS deadline, t.status, t.notes, t.file_name AS fileName, t.file_path AS filePath FROM tasks t WHERE t.user_id = ? ORDER BY t.deadline ASC');
            $stmt->execute([(int)$user['id']]);
            respond(['success' => true, 'tasks' => $stmt->fetchAll()]);

        case 'save_task':
            $username = require_login();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            $userId = (int)$user['id'];

            $id = (int)($data['id'] ?? 0);
            $title = clean_string($data['title'] ?? '', 200);
            $subject = clean_string($data['subject'] ?? '', 200);
            $teacher = clean_string($data['teacher'] ?? '', 200);
            $deadline = clean_string($data['deadline'] ?? '', 30);
            $status = clean_string($data['status'] ?? 'todo', 20);
            $notes = clean_string($data['notes'] ?? '', 5000);
            if ($title === '' || $subject === '' || $deadline === '') {
                respond(['success' => false, 'message' => 'Please fill in the required task fields.'], 422);
            }
            if (!in_array($status, ['todo', 'progress', 'done'], true)) {
                $status = 'todo';
            }
            $deadlineSql = str_replace('T', ' ', $deadline);
            if (strlen($deadlineSql) === 16) {
                $deadlineSql .= ':00';
            }

            $fileName = null;
            $filePath = null;
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT file_name, file_path FROM tasks WHERE id = ? AND user_id = ?');
                $stmt->execute([$id, $userId]);
                $old = $stmt->fetch();
                if (!$old) {
                    respond(['success' => false, 'message' => 'Task not found.'], 404);
                }
                $fileName = $old['file_name'];
                $filePath = $old['file_path'];
            }

            // 1. Support Base64 JSON file uploads (Bypasses ByetHost's multipart/form-data 403 block)
            if (!empty($data['file']) && is_array($data['file']) && !empty($data['file']['data'])) {
                $origName = basename((string)($data['file']['name'] ?? 'attachment'));
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','ppt','pptx'];
                if (!in_array($ext, $allowed, true)) {
                    respond(['success' => false, 'message' => 'This file type is not allowed.'], 422);
                }

                $rawBase64 = (string)$data['file']['data'];
                if (strpos($rawBase64, ',') !== false) {
                    $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
                }
                $decoded = base64_decode($rawBase64, true);
                if ($decoded === false) {
                    respond(['success' => false, 'message' => 'Invalid file data received.'], 422);
                }
                if (strlen($decoded) > 10 * 1024 * 1024) {
                    respond(['success' => false, 'message' => 'Files must be 10 MB or smaller.'], 422);
                }

                $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
                $uploadsDir = __DIR__ . '/../uploads';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0755, true);
                }
                $target = $uploadsDir . '/' . $storedName;
                if (@file_put_contents($target, $decoded) === false) {
                    respond(['success' => false, 'message' => 'Could not save the uploaded file. Check uploads folder permissions.'], 500);
                }

                if ($filePath && is_file(__DIR__ . '/../' . $filePath)) {
                    @unlink(__DIR__ . '/../' . $filePath);
                }
                $fileName = $origName;
                $filePath = 'uploads/' . $storedName;
            }
            // 2. Fallback for standard multipart/form-data uploads
            elseif (!empty($_FILES['taskFile']) && $_FILES['taskFile']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['taskFile'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    respond(['success' => false, 'message' => 'File upload failed.'], 422);
                }
                if ($file['size'] > 10 * 1024 * 1024) {
                    respond(['success' => false, 'message' => 'Files must be 10 MB or smaller.'], 422);
                }
                $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','ppt','pptx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    respond(['success' => false, 'message' => 'This file type is not allowed.'], 422);
                }
                $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
                $uploadsDir = __DIR__ . '/../uploads';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0755, true);
                }
                $target = $uploadsDir . '/' . $storedName;
                if (!move_uploaded_file($file['tmp_name'], $target)) {
                    respond(['success' => false, 'message' => 'Could not store the uploaded file. Ensure uploads/ folder has write permissions.'], 500);
                }
                if ($filePath && is_file(__DIR__ . '/../' . $filePath)) {
                    @unlink(__DIR__ . '/../' . $filePath);
                }
                $fileName = basename($file['name']);
                $filePath = 'uploads/' . $storedName;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE tasks SET title=?, subject=?, teacher=?, deadline=?, status=?, notes=?, file_name=?, file_path=? WHERE id=? AND user_id=?');
                $stmt->execute([$title, $subject, $teacher ?: null, $deadlineSql, $status, $notes ?: null, $fileName, $filePath, $id, $userId]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO tasks (user_id, title, subject, teacher, deadline, status, notes, file_name, file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$userId, $title, $subject, $teacher ?: null, $deadlineSql, $status, $notes ?: null, $fileName, $filePath]);
            }
            respond(['success' => true, 'message' => 'Task saved.']);

        case 'delete_task':
            $username = require_login();
            $id = (int)($data['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT t.file_path FROM tasks t JOIN users u ON u.id=t.user_id WHERE t.id=? AND u.username=?');
            $stmt->execute([$id, $username]);
            $task = $stmt->fetch();
            if (!$task) {
                respond(['success' => false, 'message' => 'Task not found.'], 404);
            }
            $stmt = $pdo->prepare('DELETE t FROM tasks t JOIN users u ON u.id=t.user_id WHERE t.id=? AND u.username=?');
            $stmt->execute([$id, $username]);
            if ($task['file_path'] && is_file(__DIR__ . '/../' . $task['file_path'])) {
                @unlink(__DIR__ . '/../' . $task['file_path']);
            }
            respond(['success' => true]);

        case 'view_file':
            $username = require_login();
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT t.file_name, t.file_path FROM tasks t JOIN users u ON u.id=t.user_id WHERE t.id=? AND u.username=?');
            $stmt->execute([$id, $username]);
            $file = $stmt->fetch();
            if (!$file || !$file['file_path'] || !is_file(__DIR__ . '/../' . $file['file_path'])) {
                respond(['success' => false, 'message' => 'File not found.'], 404);
            }
            respond(['success' => true, 'fileName' => $file['file_name'], 'fileUrl' => $file['file_path']]);

        default:
            respond(['success' => false, 'message' => 'Unknown API action.'], 400);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    respond(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}