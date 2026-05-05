# KCCF School Clinic System

Repository: https://github.com/aldrieBQRN/school-clinic-system.git

## Quick start (Windows, XAMPP)

1. Clone the repo directly into XAMPP htdocs:

   ```powershell
   git clone https://github.com/aldrieBQRN/school-clinic-system.git "C:\xampp\htdocs\school-clinic-system"
   ```

2. Start Apache and MySQL in XAMPP.
3. Import the database schema and seed data using `database_structure.txt` and `seed.txt` in phpMyAdmin.
4. Update DB credentials in `config/db_connect.php` if needed.
5. Open `http://localhost/school-clinic-system/` in your browser.

## Notes
- Change default passwords after first login.
- Add sensitive local overrides to `.env` and ensure `.gitignore` excludes them.

## Support
For issues, contact: John Aldrie Baquiran
