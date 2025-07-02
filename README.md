# PLC Rotator Control & Integrated Management System

---

## Project Overview

This system is a robust, production-grade web solution for real-time PLC (Programmable Logic Controller) rotator control, fault management, activity logging, and user administration. Designed for industrial and public sector environments, it emphasizes reliability, security, maintainability, and extensibility.

---

## Key Features & Practical Strengths

- Real-time rotator control (admin only): ON/OFF, RPM setting, status monitoring
- Fault board: submit, edit, delete faults with file attachments; all actions are logged
- Activity log: records all major events (login, control, faults, etc.), color-coded UI, IP masking, detail popups
- User management: separate admin/guest roles, account statistics, password reset, account deletion, recent activity tracking
- Maintenance mode: real-time ON/OFF, maintenance notice, admin exception access, extensible for scheduling
- Unified UI/UX: government-style design system (main.css), responsive, accessible, intuitive navigation
- Security: password hashing, session authentication, file upload restrictions, complete session destruction on logout
- Maintainable & Extensible: modular folder structure (public/src/sql), shared layouts, utility functions, normalized DB schema

---

## Folder & File Structure

- `public/` : Main pages (index, control, faults, logs, login, etc.)
- `src/` : Core logic (DB, authentication, logging, user management)
- `assets/css/` : Design system (main.css)
- `sql/` : DB schema and initialization scripts
- `uploads/` : File attachments

---

## Installation & Setup

1. **Requirements**: PHP 7.4+, MySQL 5.7+, Apache/Nginx
2. **Database & Admin Account Setup**
   ```bash
   php public/create_database.php
   php public/create_admin.php
   ```
3. **File Permissions**
   ```bash
   sudo chown -R www-data:www-data uploads/
   sudo chmod 755 uploads/
   ```
4. **Access**: Open `http://your-server/rotator-system/` in your browser

---

## Default Admin Account
- Username: `admin`
- Password: `ateam4567!`
> Please change the default password after your first login.

---

## Database Schema (Summary)
- **admins**: admin accounts
- **guests**: guest accounts
- **faults**: fault history and attachments
- **logs**: activity logs (all major events)
- **maintenance**: maintenance status and schedule

---

## Security & Best Practices
- Passwords are securely hashed (never stored in plain text)
- File uploads: restrict file types and size in production
- Strict role-based access control (admin/guest)
- Session-based authentication; full session destruction on logout
- IP addresses are masked in logs for privacy
- All DB operations use prepared statements and error handling

---

## Portfolio & Real-World Value
- Production-ready structure: code, DB, folder, security, and UX all meet industry standards
- Extensible: easily add new features (notifications, APIs, hardware integration)
- Maintainable: shared layouts, utility functions, clear comments, modular files for easy collaboration and handover
- UI/UX: government/public sector style, trustworthy design, responsive and accessible
- Real-world applicability: PLC control, fault tracking, audit logging for industrial/public sector use cases

---

## Open Source & Contribution
- This project is open for reference, extension, and use in professional, educational, or research contexts.
- Contributions, improvements, and inquiries are welcome.

---

Automation | Reliability | Security | Clean Code 