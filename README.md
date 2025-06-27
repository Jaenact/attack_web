# PLC Rotator Control & Fault Management System

A modern web-based system for controlling PLC rotators, managing faults, logging all activities, and administering users (Admin/Guest). Built with PHP and MySQL, this project is designed for industrial environments and portfolio demonstration.

---

## 🚀 Features
- **Rotator Control**: Admins can start/stop the rotator and set RPM. Real-time status display.
- **Fault Board**: Submit, edit, and delete fault reports with file attachments. All actions are logged.
- **Activity Log**: Every action (login, control, fault, etc.) is recorded with timestamp, user, and masked IP. Color-coded, paginated, and searchable.
- **User Management**: View all accounts (admin/guest), see activity stats, recent actions, registration date, password hash, reset password, and delete accounts—all in one place.
- **Role Separation**: Admins have full control; guests can only view status and submit faults.
- **Responsive UI**: Clean, sidebar-driven navigation for all major features.

---

## 🛠️ Installation
1. **Requirements**: PHP 7.4+, MySQL 5.7+, Apache/Nginx
2. **Database Setup**:
   ```bash
   php create_database.php
   php create_admin.php
   ```
3. **Web Server**: Serve the `/var/www/html/rotator-system` directory
4. **File Permissions**:
   ```bash
   sudo chown -R www-data:www-data uploads/
   sudo chmod 755 uploads/
   ```
5. **Access**: Open `http://your-server/rotator-system/` in your browser

---

## 📁 Main Files & Structure
- `index.php` : Dashboard (requires login)
- `control.php` : Rotator control (admin only)
- `faults.php` : Fault board (with file upload, edit/delete, logging)
- `logs.php` : Activity log (admin only, paginated, color-coded)
- `user_management.php` : User management (accounts, stats, password reset, delete)
- `auth.php` : Login handler
- `make_account.php` : Guest registration
- `logout.php` : Logout
- `includes/db.php` : Database connection
- `log_function.php` : Logging utility
- `uploads/` : File attachments

---

## 👤 Default Admin Account
- **Username**: `admin`
- **Password**: `ateam4567!`

> Please change the default password after first login!

---

## 🗄️ Database Schema (Summary)
- **admins**: id, username, password, created_at
- **guests**: id, username, password, created_at
- **faults**: id, part, filename, original_filename, created_at
- **logs**: id, username, log_message, ip_address, created_at

---

## 🔒 Security Notes
- Passwords are securely hashed (never stored in plain text)
- File uploads: For production, restrict allowed file types for security
- Only admins can access logs and user management
- Session-based authentication; full session destroy on logout
- IP addresses are masked in logs for privacy

---

## 💡 Portfolio Highlights
- **Full-stack PHP/MySQL**: End-to-end CRUD, authentication, and admin features
- **Modern UI/UX**: Sidebar navigation, modals, color-coded logs, responsive tables
- **Security Best Practices**: Password hashing, session management, file upload safety
- **Real-world Use Case**: Industrial PLC control, fault tracking, and audit logging
- **Extensible**: Easily add new features, roles, or integrate with hardware APIs

---

## 🤝 Contribution
Feel free to fork, improve, or use this project as a reference for your own industrial or admin dashboard solutions.

---


**Made with passion for automation, reliability, and clean code.** 