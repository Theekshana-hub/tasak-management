# Sipway Campus - Task Management System

Plain PHP + MySQL Task Management System (No Framework)

## Requirements
- WAMP / XAMPP
- PHP 8.0+
- MySQL 5.7+ / MariaDB
- Browser with modern JavaScript support

## Installation Steps

### 1. Copy Project
Copy the entire `task-management` folder into your web root:

**WAMP:** `C:\wamp64\www\task-management`  
**XAMPP:** `C:\xampp\htdocs\task-management`

### 2. Create Database
1. Open phpMyAdmin → http://localhost/phpmyadmin
2. Import the file: `database.sql`
3. Database name will be created as `sipway_tasks`

### 3. Database Config
Open `config/database.php` and change if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sipway_tasks');
define('DB_USER', 'root');
define('DB_PASS', ''); // empty for default WAMP/XAMPP
```

### 4. Password Setup (Recommended)
After importing SQL, open once in browser:

http://localhost/task-management/setup_password.php

This will set the admin password correctly.  
**Then DELETE the file `setup_password.php` for security.**

### 5. Login
Open: http://localhost/task-management/

**Default Admin Login:**
- Email: `admin@sipway.com`
- Password: `Admin@123`

## Project Structure
```
task-management/
├── config/database.php
├── includes/ (auth, header, footer, sidebar, functions)
├── admin/ (dashboard, users, sections, tasks, reports...)
├── user/ (dashboard, my-tasks, task-details...)
├── actions/ (all form handlers)
├── uploads/task-files/
├── assets/css/style.css + js/script.js
├── login.php
├── index.php
└── database.sql
```

## Features Implemented
- Admin & User roles with session authentication
- Create / Assign / Manage Tasks
- Task status: Pending → In Progress → Completed
- Admin review (Approve / Reopen)
- Comments (chat style)
- Activity history
- Notifications system
- Overdue detection
- Search & Filters
- User & Section management
- Reports with Chart.js
- File attachments
- CSRF protection
- PDO prepared statements
- password_hash / password_verify
- XSS protection with htmlspecialchars

## Security Notes
- Always verify ownership on server side
- Never trust GET parameters alone
- Delete setup_password.php after use
- In production, set proper DB password and disable error display

## Integration with existing Sipway Campus
You can later include the auth files or merge the users table with your main system.
Keep the folder structure clean for easy integration.

## Common Errors & Solutions

**Database connection failed**
→ Check DB name, username, password in config/database.php

**Blank page / 500 error**
→ Enable error display temporarily or check Apache/PHP error log

**Login not working**
→ Run setup_password.php once

**File upload fails**
→ Make sure `uploads/task-files/` folder exists and is writable (chmod 755)

**CSRF token error**
→ Make sure sessions are working and cookies are allowed

---
Built with plain PHP for Sipway Campus.
