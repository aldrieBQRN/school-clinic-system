<?php
// Mark this page as a subfolder route for include path handling.
$is_subfolder = true;
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Patient | KCCF Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

    <div class="wrapper">
        <?php include '../../includes/nurse_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../../includes/nurse_header.php'; ?>

            <main class="content-body">

                <div class="page-header">
                    <div class="page-header-text">
                        <a href="../student_records.php" style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px; margin-bottom: 10px; text-decoration: none;">
                            <i class="ph ph-arrow-left"></i> Back to Records
                        </a>
                        <h1 class="page-title">Register New Patient</h1>
                        <p class="page-subtitle">Encode the student's personal details and emergency contacts for the clinic database.</p>
                    </div>
                </div>

                <div class="panel" style="width: 100%; padding: 32px; box-sizing: border-box;">
                    <form action="../../backend/nurse/process_register_student.php" method="POST">

                        <h3 style="font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--text-heading); margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                            <i class="ph ph-user" style="color: var(--brand-primary); margin-right: 8px;"></i>Personal Information
                        </h3>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 40px;">
                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">First Name</label>
                                <input type="text" name="first_name" placeholder="First Name" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Last Name</label>
                                <input type="text" name="last_name" placeholder="Last Name" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Course</label>
                                <select name="course" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-heading); box-sizing: border-box;">
                                    <option value="" disabled selected>Select Course...</option>
                                    <option value="BSED">BSED (Bachelor of Science in Education)</option>
                                    <option value="BSIT">BSIT (Bachelor of Science in Information Technology)</option>
                                    <option value="BSCRIM">BSCRIM (Bachelor of Science in Criminology)</option>
                                    <option value="BSBA">BSBA (Bachelor of Science in Business Administration)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Year Level</label>
                                <select name="year_level" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-heading); box-sizing: border-box;">
                                    <option value="" disabled selected>Select Year Level...</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Gender</label>
                                <select name="gender" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-heading); box-sizing: border-box;">
                                    <option value="" disabled selected>Select Gender...</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Date of Birth</label>
                                <input type="date" name="birthdate" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-heading); box-sizing: border-box;">
                            </div>
                        </div>

                        <h3 style="font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--text-heading); margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                            <i class="ph ph-phone-call" style="color: var(--brand-primary); margin-right: 8px;"></i>Emergency Contact
                        </h3>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 32px;">
                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Guardian Full Name</label>
                                <input type="text" name="guardian_name" placeholder="e.g. Maria dela Cruz" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Relationship</label>
                                <input type="text" name="relationship" placeholder="e.g. Mother" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; box-sizing: border-box;">
                            </div>

                            <div class="form-group">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Contact Number</label>
                                <input type="text" name="guardian_contact" placeholder="09XX XXX XXXX" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; box-sizing: border-box;">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border); padding-top: 24px;">
                            <a href="../student_records.php" class="btn btn-ghost" style="padding: 12px 24px;">Cancel</a>
                            <button type="submit" class="btn btn-primary" style="padding: 12px 24px;"><i class="ph ph-floppy-disk"></i> Save Student Record</button>
                        </div>

                    </form>
                </div>

            </main>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes('student_records.php')) {
                    link.classList.add('active');
                }
            });

            // Add a loading state to the submit button.
            document.querySelector('form').addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    if (btn.classList.contains('btn-loading')) return false;
                    btn.classList.add('btn-loading');
                    btn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="display: inline-block; animation: spin 1s linear infinite; margin-right: 8px;"></i> Saving...';
                    btn.disabled = true;
                }
            });
        });

        // Inject spinner keyframes.
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
    <script src="../../assets/js/script.js"></script>
</body>

</html>