# TaskFlow (PHP + MySQL Edition)

**TaskFlow** is a full-stack to-do list and task management application built with PHP and MySQL. Refactored from a browser-only IndexedDB setup, this version separates the frontend and backend into modular PHP, CSS, JavaScript, and SQL files—enabling server-side authentication, file attachments, and multi-device syncing via a shared MySQL database.

---

##  Key Features

* **User Authentication:** Secure account registration and login powered by PHP sessions, `password_hash()`, and `password_verify()`.
* **Task Management:** Create, view, update, and delete to-do items stored persistently in MySQL.
* **File Attachments:** Upload document and image attachments to tasks (up to 10 MB per file).
* **Multi-Device Sync:** Access your tasks from any device when hosted on a live PHP/MySQL server.

---

##  Project Structure

```text
TaskFlow_PHP/
├── api/
│   └── index.php          # Handles auth, sessions, task CRUD, and file uploads
├── assets/
│   ├── css/
│   │   └── style.css      # Application styling
│   └── js/
│       └── app.js         # Frontend UI logic and API fetch requests
├── config/
│   └── database.php       # MySQL database connection settings
├── uploads/               # Storage directory for task attachments
├── index.php              # Main application interface
└── schema.sql             # Database and table creation script
