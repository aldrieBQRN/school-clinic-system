# KCCF School Clinic System

A web-based clinic management system for Kurios Christian Colleges Foundation. The app supports role-based access for admins and nurses, with features for student records, visit logging, health records, medicine inventory, and reporting.

## Features

- Secure login with role-based access control
- Admin dashboard for user, student, inventory, and report management
- Nurse dashboard for visit logging, triage, and health record handling
- Medicine inventory tracking with stock alerts and restocking
- Student profile and visit history management
- Mobile-responsive interface for desktop and mobile use

## Technology Stack

- PHP 7.4+
- MySQL / MariaDB
- HTML5, CSS3, JavaScript
- PDO for database access
- BCrypt password hashing

## Project Structure

- `index.php` - login page
- `logout.php` - session logout handler
- `admin/` - admin-facing pages
- `nurse/` - nurse-facing pages and forms
- `backend/` - form processors and action handlers
- `config/` - database connection, auth checks, helper functions
- `includes/` - shared page components
- `assets/` - styles, scripts, and images
- `database_structure.txt` - database schema reference
- `seed.txt` - sample seed data
- `DOCUMENTATION.md` - full project documentation

## Setup

### Requirements

- Apache web server
- PHP 7.4 or higher
- MySQL or MariaDB
- A local environment such as Laragon, XAMPP, or WAMP

### Installation

1. Copy the project into your web server directory.
2. Create a database named `school_clinic_system`.
3. Import the schema from `database_structure.txt`.
4. Import the sample data from `seed.txt` if needed.
5. Update `config/db_connect.php` with your database credentials.
6. Open the app in your browser and log in with a valid account.

### Default Credentials

If the seed data is being used, the project documentation includes sample admin and nurse accounts. Update all passwords after first login.

## Usage

- Open `index.php` to sign in.
- Admin users manage clinic data from the `admin/` pages.
- Nurse users manage clinic workflows from the `nurse/` pages.

## Notes

- The system is designed for mobile-friendly use across modern browsers.
- Refer to `DOCUMENTATION.md` for the full setup guide, file references, and implementation details.
