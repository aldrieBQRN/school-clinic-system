# KCCF School Clinic System - Complete Documentation

**Document Version:** 1.1

**Date:** May 4, 2026

**Last Updated:** May 8, 2026

**Client:** Kurios Christian Colleges Foundation

**Client Location:** Magallanes, Cavite

**Software Developer:** John Aldrie Baquiran

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [System Architecture](#system-architecture)
3. [Technology Stack](#technology-stack)
4. [Installation & Setup](#installation--setup)
5. [Database Structure](#database-structure)
6. [User Roles & Access](#user-roles--access)
7. [Features Overview](#features-overview)
8. [File Structure & Descriptions](#file-structure--descriptions)
9. [Admin Features](#admin-features)
10. [Nurse Features](#nurse-features)
11. [API Endpoints](#api-endpoints)
12. [Security Implementation](#security-implementation)
13. [Troubleshooting](#troubleshooting)
14. [Development Notes](#development-notes)

---

## Project Overview

**KCCF School Clinic System** is a comprehensive web-based health management system designed for Kurios Christian Colleges Foundation. It streamlines patient triage, health record management, medicine inventory tracking, and clinic operations.

### Key Objectives
- Centralized student health record management
- Real-time patient queue management
- Medicine inventory tracking with low-stock alerts
- Role-based access control (Admin & Nurse)
- Comprehensive clinic reporting and analytics
- Mobile-responsive design for accessibility

### Project Scope
- **Students:** 25+ enrolled students across 4 programs
- **Programs:** BSED, BSIT, BSCRIM, BSBA (Years 1-4 each)
- **Users:** Admin (1), Nurses (2+)
- **Records:** 60+ historical visits, comprehensive health data

---

## System Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Web Browser (Client)                 │
│              (Desktop, Tablet, Mobile)                  │
└─────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│              Apache/PHP Server (Laragon)                │
├─────────────────────────────────────────────────────────┤
│  ┌──────────────────────────────────────────────────┐   │
│  │  Frontend Layer (HTML/CSS/JavaScript)            │   │
│  │  - Admin Panel (7 pages)                         │   │
│  │  - Nurse Panel (7 pages)                         │   │
│  │  - Authentication                                │   │
│  └──────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Backend Layer (PHP Processors)                  │   │
│  │  - User Management                               │   │
│  │  - Student Operations                            │   │
│  │  - Visit Processing                              │   │
│  │  - Health Records                                │   │
│  │  - Inventory Management                          │   │
│  └──────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Configuration Layer                             │   │
│  │  - Database Connection (PDO)                     │   │
│  │  - Authentication Check                          │   │
│  │  - Helper Functions                              │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────┐
│            MySQL/MariaDB Database                       │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Tables:                                        │    │
│  │  - users (Admin/Nurse accounts)                 │    │
│  │  - students (25+ enrolled students)             │    │
│  │  - visits (Patient visit records)               │    │
│  │  - health_records (Diagnosis & treatment)       │    │
│  │  - medicines (Inventory items)                  │    │
│  └─────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

---

## Technology Stack

| Component | Technology | Version |
|-----------|-----------|---------|
| **Server** | Apache HTTP Server | 2.4+ |
| **Runtime** | PHP | 7.4+ |
| **Database** | MySQL/MariaDB | 5.7+ |
| **Frontend** | HTML5, CSS3, JavaScript (ES6+) |  |
| **Frameworks** | PDO (Database Abstraction) | PHP Native |
| **Authentication** | bcrypt (Password Hashing) | PHP Native |
| **UI Library** | Phosphor Icons | v2.0+ |
| **PDF Export** | html2pdf.js | v0.10.1 |
| **Development** | XAMPP (Local Environment) |  |

---

## Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP) installed
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser (Chrome, Firefox, Safari, Edge)

### Step 1: XAMPP Installation & Configuration

1. **Download XAMPP:**
   - Visit: https://www.apachefriends.org/
   - Download version with PHP 7.4 or higher
   - Install to default location (C:\xampp)

2. **Start Services:**
   - Open XAMPP Control Panel
   - Click "Start" for Apache
   - Click "Start" for MySQL
   - Verify both show green "Running"

3. **Access phpMyAdmin:**
   - Open browser and go to: `http://localhost/phpmyadmin`
   - Default credentials: Username: `root`, Password: (empty)

### Step 2: Database Setup

1. In phpMyAdmin:
   - Click "New" to create new database
   - Database name: `school_clinic_system`
   - Collation: `utf8_general_ci`
   - Click "Create"

2. Import Database Schema:
   - Click on `school_clinic_system` database
   - Go to "Import" tab
   - Click "Choose File" and select SQL script or copy schema from `database_structure.txt`
   - Click "Go" to execute

3. Import Seed Data:
   - Copy-paste SQL content from `seed.txt`
   - Execute in SQL query window
   - Verify 25 students, 12 medicines, 60+ visits created

### Step 3: Project File Placement

1. **Quick clone into XAMPP htdocs (recommended):**

  - Open PowerShell (or Command Prompt) and run:

    `git clone https://github.com/aldrieBQRN/school-clinic-system.git "C:\xampp\htdocs\school-clinic-system"`

  - This clones the repository directly into the webroot so files are immediately accessible at `http://localhost/school-clinic-system/`.

2. **Manual placement (alternative):**

  - Locate XAMPP htdocs: `C:\xampp\htdocs\`
  - Extract or move project files to: `C:\xampp\htdocs\school-clinic-system\`

3. **Verify Structure:**

  - `C:\xampp\htdocs\school-clinic-system\index.php` ← Login page
  - `C:\xampp\htdocs\school-clinic-system\admin\` ← Admin pages
  - `C:\xampp\htdocs\school-clinic-system\nurse\` ← Nurse pages
  - `C:\xampp\htdocs\school-clinic-system\config\` ← Configuration

### Step 4: Configuration

1. **Edit Database Connection:**
   - Open: `config/db_connect.php`
   - Verify settings:
   ```php
   $host = 'localhost';      // XAMPP MySQL host
   $db = 'school_clinic_system';  // Database name
   $user = 'root';            // Default XAMPP MySQL user
   $pass = '';                // Default XAMPP MySQL password (empty)
   $charset = 'utf8mb4';
   ```

2. **Test Connection:**
   - Access: `http://localhost/school-clinic-system/`
   - If database error appears, check phpMyAdmin connection first

### Step 5: Verify Installation

1. **Access Login Page:**
   - Open browser: `http://localhost/school-clinic-system/`
   - Should see login form (not blank page or error)

2. **Test Login:**
   - Use admin credentials (see next section)
   - Should redirect to admin dashboard

3. **Check Database:**
   - In phpMyAdmin, verify tables:
     - `users` (3 records)
     - `students` (25 records)
     - `visits` (60+ records)
     - `health_records`, `medicines`

### Step 6: Default Login Credentials

**Admin Account:**
- Username: `admin`
- Password: `password123` (bcrypt hashed)

**Nurse Account 1:**
- Username: `nurse_reyes`
- Password: `password123`

**Nurse Account 2:**
- Username: `nurse_mendoza`
- Password: `password123`

⚠️ **Security Note:** Change all passwords after first login!

### Step 7: Accessing the Application

1. **Ensure Services Running:**
   - XAMPP Control Panel shows Apache & MySQL in green

2. **Open Application:**
   - Browser: `http://localhost/school-clinic-system/`

3. **Login:**
   - Use credentials from Step 6
   - Admin sees admin dashboard
   - Nurse sees nurse dashboard

### Troubleshooting XAMPP Setup

| Issue | Solution |
|-------|----------|
| "This site can't be reached" | Verify Apache is started in XAMPP Control Panel |
| "Unable to connect to database" | Verify MySQL is started; check phpMyAdmin access |
| "Table doesn't exist" | Import seed.txt in phpMyAdmin SQL tab |
| "Blank page / 500 error" | Check `C:\xampp\apache\logs\error.log` |
| Port already in use | Stop other applications using ports 80/443 or 3306 |

---

## Database Structure

### Users Table
```sql
CREATE TABLE users (
  user_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  role ENUM('Admin', 'Nurse') NOT NULL,
  status ENUM('Active', 'Inactive') DEFAULT 'Active',
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Students Table
```sql
CREATE TABLE students (
  student_id INT PRIMARY KEY AUTO_INCREMENT,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  gender ENUM('Male', 'Female') NOT NULL,
  birthdate DATE NOT NULL,
  course VARCHAR(50) NOT NULL,           -- BSED, BSIT, BSCRIM, BSBA
  year_level INT NOT NULL,               -- 1, 2, 3, 4
  guardian_name VARCHAR(100),
  relationship VARCHAR(50),
  guardian_contact VARCHAR(15),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Visits Table
```sql
CREATE TABLE visits (
  visit_id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  complaint VARCHAR(255) NOT NULL,
  temperature DECIMAL(5,1),              -- Celsius
  height DECIMAL(6,2),                   -- cm
  weight DECIMAL(6,2),                   -- kg
  nurse_notes TEXT,
  time_in TIME,
  date_logged DATE,
  status ENUM('Active', 'Completed') DEFAULT 'Active',
  FOREIGN KEY (student_id) REFERENCES students(student_id)
);
```

### Health Records Table
```sql
CREATE TABLE health_records (
  record_id INT PRIMARY KEY AUTO_INCREMENT,
  visit_id INT NOT NULL,
  student_id INT NOT NULL,
  diagnosis VARCHAR(255),
  treatment TEXT,
  date DATETIME,
  FOREIGN KEY (visit_id) REFERENCES visits(visit_id),
  FOREIGN KEY (student_id) REFERENCES students(student_id)
);
```

### Medicines Table
```sql
CREATE TABLE medicines (
  med_id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  category VARCHAR(50),
  quantity INT DEFAULT 0,
  status ENUM('In Stock', 'Low Stock', 'Out of Stock'),
  expiration DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## User Roles & Access

### Role Hierarchy

```
┌─────────────────────────────────────────┐
│           System Administrator          │
│  (Admin Account - Full System Access)   │
├─────────────────────────────────────────┤
│ Can:                                    │
│ ✓ View all health records               │
│ ✓ Generate reports & analytics          │
│ ✓ Manage user accounts (Add/Edit/View)  │
│ ✓ View complete visit logs              │
│ ✓ Manage medicine inventory             │
│ ✓ View student database                 │
│ ✓ Export data (PDF)                     │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│         Clinical Nurse Staff            │
│  (Nurse Accounts - Clinical Operations) │
├─────────────────────────────────────────┤
│ Can:                                    │
│ ✓ Log new patient visits                │
│ ✓ Record vital signs                    │
│ ✓ Finalize visit diagnosis/treatment    │
│ ✓ View health records                   │
│ ✓ Check medicine availability           │
│ ✓ Restock medicines                     │
│ ✓ Register new students                 │
│ ✓ Edit student information              │
│ Cannot:                                 │
│ ✗ Access admin reports                  │
│ ✗ Manage user accounts                  │
│ ✗ View admin dashboard                  │
└─────────────────────────────────────────┘
```

---

## Features Overview

### 1. Authentication & Authorization
- **Secure Login:** bcrypt password hashing
- **Session Management:** PHP sessions with timeout
- **Role-Based Access Control:** Separate admin & nurse dashboards
- **Logout:** Secure session termination

### 2. Dashboard
- **Admin Dashboard:** Overview of clinic statistics, user count, recent visits, and low-stock medicine totals
- **Nurse Dashboard:** Active patient queue, finalized consultation count, medicine alerts, and quick actions

### 3. Student Management
- **Student Registration:** Add new students with course & year level
- **Student Records:** Browse, search, edit student profiles
- **Guardian Info:** Emergency contact details management
- **Course/Year Filtering:** Organized by BSED, BSIT, BSCRIM, BSBA

### 4. Patient Visit Management
- **Log Visit:** Initiate new patient triage (complaint, vitals)
- **View Queue:** Real-time active patient queue
- **Finalize Visit:** Complete visit with diagnosis & treatment
- **Visit History:** Archive of all completed visits

### 5. Health Records
- **Record Creation:** Automatic with visit finalization
- **Record Search:** Filter by student, course, date range
- **Record Viewing:** Detailed patient medical history
- **Record Editing:** Update diagnosis and treatment info

### 6. Medicine Inventory
- **Add Medicines:** Stock new medicine items
- **Track Inventory:** Real-time quantity tracking
- **Stock Alerts:** Low stock alerts and expiration warnings
- **Restock Operations:** Update quantities
- **Delete Obsolete:** Remove expired items

### 7. Reporting & Analytics
- **Visit Reports:** Filtered by date range, course, complaint
- **Clinic Statistics:** Patient volume, common complaints
- **Export to PDF:** Generate printable reports
- **Search & Filter:** Advanced filtering options

### 8. User Management
- **Add Users:** Create new admin/nurse accounts
- **Edit Users:** Modify user information and status
- **View Users:** User directory with active status
- **Password Hashing:** Secure bcrypt encryption

### 9. Mobile Responsiveness
- **Responsive Design:** Works on desktop, tablet, mobile
- **Mobile Menu:** Collapsible sidebar on small screens
- **Touch-Friendly:** Optimized buttons and controls
- **Adaptive Tables:** Card layout on mobile

---

## File Structure & Descriptions

### Root Level Files
```
index.php                    # Login page and authentication entry point
logout.php                   # Session termination and logout handler
database_structure.txt       # Database schema and table definitions
seed.txt                     # Initial seed data for development/testing
```

### Admin Panel (`/admin/`)
```
dashboard.php        # Admin overview with statistics and system info
health_records.php   # Search and view all student health records
inventory.php        # Medicine stock management and tracking
manage_users.php     # User account management (Add/Edit/View/Delete)
reports.php          # Generate clinic reports with filters
student_records.php  # Student database with add/edit/delete functions
visit_log.php        # Archive of completed visits (read-only)
```

### Nurse Panel (`/nurse/`)
```
dashboard.php        # Nurse dashboard with active queue and alerts
health_records.php   # View patient health history
inventory.php        # Check medicine availability
student_records.php  # Student profiles and directory
visit_log.php        # Completed visits archive
visits.php           # Active patient queue for triage
forms/
  register_student.php       # New student registration form
  finalize_visit.php         # Complete patient visit with diagnosis
```

### Backend Processors (`/backend/`)
```
auth_login.php                          # Authenticate user login

admin/
  process_add_user.php                  # Create new admin/nurse account
  process_edit_user.php                 # Modify user details

nurse/
  process_register_student.php          # Insert new student record
  process_edit_student.php              # Update student information
  process_delete_student.php            # Remove student from system
  process_log_visit.php                 # Create new visit entry
  process_finalize_visit.php            # Complete visit with diagnosis
  process_edit_visit.php                # Modify visit details
  process_delete_visit.php              # Remove visit record
  process_delete_health_record.php      # Delete health record
  process_add_medicine.php              # Add medicine to inventory
  process_update_medicine.php           # Update medicine details
  process_delete_medicine.php           # Remove medicine from stock
  process_restock_medicine.php          # Adjust medicine quantity
```

### Configuration (`/config/`)
```
db_connect.php           # Database connection setup (PDO)
auth_check.php           # Session verification and access control
medicine_helpers.php     # Helper functions for medicine operations
```

### Includes (`/includes/`)
```
admin_header.php         # Admin navigation header with user menu
admin_sidebar.php        # Admin sidebar with menu options
nurse_header.php         # Nurse navigation header with notifications
nurse_sidebar.php        # Nurse sidebar with menu options
```

### Assets (`/assets/`)
```
css/style.css            # Main stylesheet (responsive, dark theme)
js/script.js             # JavaScript utilities and mobile functionality
images/logo.jpg          # KCCF school logo
```

---

## Admin Features

### Dashboard
- System statistics (total students, users, recent visits, low-stock medicines)
- Quick navigation to main functions
- User greeting with current date/time

### Student Records
- **Search:** By name, ID, course, gender
- **Filter:** By course and gender
- **Display:** Age calculation, registration date
- **Actions:** View profile, Edit info, Delete record
- **Export:** PDF of student directory
- **Pagination:** 10 records per page

### Health Records
- **Search:** By student ID, name, or diagnosis
- **Filter:** By course and date range
- **View:** Complete medical history with diagnosis
- **Edit:** Update existing health records
- **Export:** PDF report with filters applied

### Inventory Management
- **Track Stock:** Real-time quantity updates
- **Alerts:** Admin dashboard summary counts low stock at <=10, while nurse/admin alerts use <=5 and expiration warnings
- **Status:** In Stock, Low Stock, Out of Stock
- **Operations:** Add, Edit, Delete, Restock
- **Export:** Inventory report to PDF

### Visit Log (Archive)
- **Completed Visits Only:** Read-only archive
- **Search:** By student name, ID, or complaint
- **Filter:** By course and date range
- **View Details:** Complete triage data and vitals
- **Statistics:** Temperature analysis with color coding

### User Management
- **Add User:** Create new admin or nurse account
- **Edit User:** Modify name, role, or status
- **View All:** User directory with active status
- **Delete:** Remove inactive accounts
- **Password:** Bcrypt hashed on creation

### Reports
- **Visit Analysis:** Trends and statistics
- **Date Filtering:** Custom date range selection
- **Export:** PDF generation with professional formatting
- **Charts:** Visual representation of data

---

## Nurse Features

### Dashboard
- **Active Queue:** Real-time list of patients waiting
- **Finalized Consultations:** Shows today's completed health records count
- **Alerts:** Low medicine stock warnings using the current <=5 threshold
- **Quick Actions:** Buttons to register student or log visit
- **Notifications:** Medicine expiration alerts

### Visits (Patient Queue)
- **Active Patients:** Real-time queue of incoming patients
- **Triage Data:** Initial complaint, vitals, notes
- **Status:** Active (In Queue) or Completed
- **Actions:** View details, Edit, Finalize visit, Delete
- **Edit:** Update complaint, temperature, height, weight, notes

### Finalize Visit
- **Complete Triage:** Record final diagnosis and treatment
- **Auto-Populated:** Student and visit information pre-filled
- **Diagnosis Field:** Detailed medical assessment
- **Treatment Field:** Prescribed treatment and advice
- **Save:** Creates permanent health record

### Register Student
- **New Enrollment:** Add student to system
- **Guardian Info:** Emergency contact collection
- **Course & Year:** Select from BSED, BSIT, BSCRIM, BSBA (Years 1-4)
- **Validation:** Required fields enforcement
- **Confirmation:** Success notification on submission

### Health Records
- **Search:** By student ID, name, course
- **Filter:** By course and date range
- **Pagination:** 10 records per page
- **View Details:** Modal with complete record
- **Vitals Display:** Temperature with color-coded status, plus height and weight in the details modal

### Student Records
- **Directory:** Browse all registered students
- **Search:** By ID, name, or course
- **Filter:** By course and gender
- **View Profile:** Modal with student details
- **Edit:** Update student information
- **Delete:** Remove student (if no visit history)

### Inventory
- **Medicine List:** All available medicines
- **Stock Check:** Real-time quantity display
- **Categories:** Organized by medicine type
- **Alerts:** Highlighted low stock items
- **Expiration:** Date tracking with warnings
- **Restock:** Update quantities quickly
- **Add Medicine:** New stock items

---

## API Endpoints

### Authentication
```
POST /backend/auth_login.php
  Input: username, password
  Output: Session creation / Error

GET /logout.php
  Output: Session destruction
```

### Student Operations (Nurse)
```
POST /backend/nurse/process_register_student.php
  Input: first_name, last_name, gender, birthdate, course, year_level,
         guardian_name, relationship, guardian_contact
  Output: Success/Error message

POST /backend/nurse/process_edit_student.php
  Input: student_id, first_name, last_name, course, year_level,
         guardian_name, relationship, guardian_contact
  Output: Success/Error message

POST /backend/nurse/process_delete_student.php
  Input: student_id
  Output: Success/Error message
```

### Visit Operations
```
POST /backend/nurse/process_log_visit.php
  Input: student_id, complaint, temperature, height, weight, nurse_notes
  Output: visit_id, Success/Error

POST /backend/nurse/process_finalize_visit.php
  Input: visit_id, diagnosis, treatment
  Output: Success/Error, Creates health_record

POST /backend/nurse/process_edit_visit.php
  Input: visit_id, complaint, temperature, height, weight, nurse_notes
  Output: Success/Error

POST /backend/nurse/process_delete_visit.php
  Input: visit_id
  Output: Success/Error
```

### Health Records
```
POST /backend/nurse/process_edit_health_record.php
  Input: record_id, diagnosis, treatment
  Output: Success/Error

POST /backend/nurse/process_delete_health_record.php
  Input: record_id
  Output: Success/Error
```

### Medicine Operations
```
POST /backend/nurse/process_add_medicine.php
  Input: name, category, quantity, expiration
  Output: Success/Error

POST /backend/nurse/process_update_medicine.php
  Input: med_id, name, category, quantity, status, expiration
  Output: Success/Error

POST /backend/nurse/process_delete_medicine.php
  Input: med_id
  Output: Success/Error

POST /backend/nurse/process_restock_medicine.php
  Input: med_id, quantity
  Output: Success/Error
```

### User Management (Admin)
```
POST /backend/admin/process_add_user.php
  Input: name, username, password, role
  Output: Success/Error

POST /backend/admin/process_edit_user.php
  Input: user_id, name, role, status
  Output: Success/Error
```

---

## Security Implementation

### Authentication & Authorization
- ✓ **Secure Password Storage:** bcrypt hashing (cost: 10).
- ✓ **Session Management:** PHP $_SESSION with timeout.
- ✓ **Access Control:** Role-based routing (admin_check.php, auth_check.php).
- ✓ **CSRF Protection:** Form submissions with PHP native protection.

### Data Protection
- ✓ **SQL Injection Prevention:** Prepared statements with PDO.
- ✓ **XSS Prevention:** htmlspecialchars() on all output.
- ✓ **Input Validation:** Type checking and sanitization.
- ✓ **Error Handling:** Generic error messages to users.

### Database Security
- ✓ **PDO Abstraction:** Parameterized queries only.
- ✓ **Connection Isolation:** Separate user account for DB.
- ✓ **Foreign Keys:** Referential integrity enforcement.
- ✓ **Constraints:** NOT NULL, UNIQUE, DEFAULT values.

### Best Practices
 - ⚠️ **Change Default Passwords:** After first deployment.
 - ⚠️ **Update PHP Version:** Keep PHP 7.4+ current.
 - ⚠️ **HTTPS Deployment:** Use SSL/TLS in production.
 - ⚠️ **Regular Backups:** Database backup schedule.
 - ⚠️ **Log Monitoring:** Track failed login attempts.

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Error
**Error:** "Error connecting to database"

**Solution:**
- Verify MySQL is running
- Check credentials in `config/db_connect.php`
- Ensure database `school_clinic_system` exists
- Verify user permissions

#### 2. Login Not Working
**Error:** "Invalid username or password" or blank response

**Solution:**
- Verify user exists in `users` table
- Check password is correctly bcrypt hashed
- Ensure cookies are enabled
- Clear browser cache

#### 3. Missing Student/Course Data
**Error:** "No courses available" or empty dropdowns

**Solution:**
- Import seed.txt into database
- Verify students table has records
- Check for NULL course values
- Refresh page cache

#### 4. Medicine Alerts Not Showing
**Error:** Low stock warnings not displaying

**Solution:**
- Update medicine quantities manually
- Set expiration dates
- Refresh browser
- Check JavaScript console for errors

#### 5. PDF Export Fails
**Error:** "PDF generation failed" or blank file

**Solution:**
- Verify html2pdf.js is loaded (check browser console)
- Ensure table data is present
- Try exporting smaller dataset
- Check browser security settings

#### 6. Mobile Layout Issues
**Error:** Sidebar not collapsing or misaligned content

**Solution:**
- Clear browser cache and cookies
- Verify script.js is loaded
- Test in incognito/private window
- Check viewport meta tag in HTML

#### 7. Year Level Display Incorrect
**Error:** Shows "1th Year" instead of "1st Year"

**Solution:**
- Verify ordinal conversion function in script.js
- Check database year_level values (1-4)
- Clear page cache
- Refresh database with seed.txt

### Debug Mode

Enable error logging in `config/db_connect.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

Check PHP error log:
- Laragon: `C:\laragon\logs\php-error.log`
- Browser console: F12 → Console tab
- Network tab: Check failed requests

---

## Development Notes

### Recent Updates (May 2026)

#### Schema Migration: grade/section → course/year_level
- **Reason:** Better accommodate 4-year programs with year levels
- **Impact:** 14+ files updated, all queries refactored
- **Status:** Complete and tested

#### Mobile Responsiveness Improvements
- **Consolidated JS:** Moved mobile-responsive.js code to script.js
- **Responsive Tables:** Card layout on mobile (<768px)
- **Touch Optimization:** Larger buttons and spacing
- **Sidebar Collapse:** Auto-collapse on mobile

#### Display Formatting: Ordinal Year Levels
- **Format:** Years now display as "1st, 2nd, 3rd, 4th" instead of "1, 2, 3, 4"
- **Implementation:** getOrdinal() function in JavaScript and PHP
- **Applied:** All modals, tables, and displays

### File Organization Cleanup
- Removed 8+ temporary documentation files
- Consolidated JavaScript utilities
- Organized project structure
- Updated documentation files

### Performance Optimizations
- AJAX pagination for faster page loading
- Debounced search to reduce queries
- Modal caching to improve responsiveness
- Optimized CSS with CSS variables

### Future Recommendations

1. **Authentication:**
   - Implement 2FA (Two-Factor Authentication)
   - Add password reset functionality
   - Session security improvements

2. **Database:**
   - Add audit logging for changes
   - Implement soft deletes (archive records)
   - Database backup automation

3. **Features:**
   - SMS notifications for alerts
   - Photo ID storage for students
   - Prescription printing
   - Appointment scheduling

4. **Performance:**
   - Database indexing optimization
   - API rate limiting
   - Caching layer implementation
   - Image optimization

5. **Security:**
   - SSL/TLS certificate
   - Web Application Firewall (WAF)
   - Regular security audits
   - Penetration testing

---

## Support & Contact

**Software Developer:** John Aldrie Baquiran

**Location:** Nasugbu, Batangas

**For Technical Support:**
- Check this documentation first
- Review troubleshooting section
- Check error logs in Laragon
- Contact the software developer

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | May 4, 2026 | John Aldrie Baquiran (Software Developer) | Initial documentation |

---

**End of Documentation**

*This document is confidential and intended for authorized personnel only.*
*Last Updated: May 8, 2026*
