<?php
$is_subfolder = true;
$breadcrumb_parent = 'Triage & Queue';
$breadcrumb_child = 'Finalize Consultation';
require_once '../../config/db_connect.php';
require_once '../../config/auth_check.php';

// Validate the required visit identifier.
if (!isset($_GET['visit_id']) || empty($_GET['visit_id'])) {
    header("Location: ../visits.php");
    exit();
}

$visit_id = $_GET['visit_id'];

try {
    // Load the active visit with student details.
    $query = "
        SELECT v.*, s.first_name, s.last_name, s.course, s.year_level
        FROM visits v
        JOIN students s ON v.student_id = s.student_id
        WHERE v.visit_id = ? AND v.status = 'Active'
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute([$visit_id]);
    $visit = $stmt->fetch();

    if (!$visit) {
        die("<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Visit Not Found</h2><p>This visit may have already been completed, or the ID is invalid.</p><a href='../visits.php'>Return to Queue</a></div>");
    }

    // Load medicines for the dispense dropdown.
    $med_query = "SELECT med_id, name, quantity, status FROM medicines ORDER BY name ASC";
    $stmt_med = $conn->prepare($med_query);
    $stmt_med->execute();
    $medicines = $stmt_med->fetchAll();

    // Load recent diagnoses and complaints for autocomplete suggestions.
    $diag_query = "SELECT diagnosis FROM health_records WHERE diagnosis != '' AND diagnosis IS NOT NULL GROUP BY diagnosis ORDER BY MAX(record_id) DESC LIMIT 50";
    $stmt_diag = $conn->prepare($diag_query);
    $stmt_diag->execute();
    $recent_diagnoses = $stmt_diag->fetchAll(PDO::FETCH_COLUMN);

    $comp_query = "SELECT complaint FROM visits WHERE complaint != '' AND complaint IS NOT NULL GROUP BY complaint ORDER BY MAX(visit_id) DESC LIMIT 50";
    $stmt_comp = $conn->prepare($comp_query);
    $stmt_comp->execute();
    $recent_complaints = $stmt_comp->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Prepare formatted display values for the page.
$formatted_visit_id = "VST-" . str_pad($visit['visit_id'], 4, "0", STR_PAD_LEFT);
$patient_name = htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']);

// Convert year level to ordinal text.
$ordinal_suffixes = ['st', 'nd', 'rd', 'th'];
$year_ordinal = ($visit['year_level'] == 1 ? '1st' : ($visit['year_level'] == 2 ? '2nd' : ($visit['year_level'] == 3 ? '3rd' : '4th')));
$course_year = htmlspecialchars($visit['course'] . ' - ' . $year_ordinal . ' Year');
$time_in = date("h:i A", strtotime($visit['time_in']));

// Temperature badge selection.
$temp = floatval($visit['temperature']);
$badge_style = 'badge-green';
if ($temp >= 37.8) $badge_style = 'badge-red';
elseif ($temp >= 37.3) $badge_style = 'badge-warn" style="background:#FEF3C7; color:#D97706;';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalize Consultation | KCCF Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* Autocomplete styles */
        .autocomplete-wrapper {
            position: relative;
            width: 100%;
        }

        .suggestions-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-base);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            margin-top: 5px;
        }

        .suggestion-item {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--text-heading);
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        /* Hover state */
        .suggestion-item:hover {
            background: var(--bg-base);
            color: var(--brand-primary);
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../../includes/nurse_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../../includes/nurse_header.php'; ?>

            <main class="content-body">

                <div class="page-header print-hide">
                    <div class="page-header-text">
                        <a href="../visits.php" style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px; margin-bottom: 10px; text-decoration: none;">
                            <i class="ph ph-arrow-left"></i> Back to Queue
                        </a>
                        <h1 class="page-title">Finalize Consultation</h1>
                        <p class="page-subtitle">Record the final diagnosis, treatment, and dispense medicine to clear the patient.</p>
                    </div>
                </div>

                <div class="panel" style="width: 100%; padding: 32px;">
                    <form action="../../backend/nurse/process_finalize_visit.php" method="POST">

                        <input type="hidden" name="visit_id" value="<?php echo $visit['visit_id']; ?>">
                        <input type="hidden" name="student_id" value="<?php echo $visit['student_id']; ?>">

                        <div style="background: var(--bg-card); padding: 24px; border-radius: var(--r-md); margin-bottom: 32px; border: 1.5px solid var(--border); box-shadow: var(--shadow-xs);">
                            <h3 style="font-size: 14px; color: var(--text-heading); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); padding-bottom: 12px;">
                                <i class="ph ph-info" style="color: var(--brand-primary); font-size: 18px;"></i>
                                Visit Summary <span style="color: var(--text-muted); font-weight: 500;">(<?php echo $formatted_visit_id; ?>)</span>
                            </h3>

                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px 15px; align-items: center;">
                                <div>
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Patient Details</span>
                                    <span style="display: block; font-size: 14px; font-weight: 600; color: var(--text-heading);"><?php echo $patient_name; ?></span>
                                    <span style="display: block; font-size: 12px; color: var(--text-muted);"><?php echo $course_year; ?></span>
                                </div>

                                <div>
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Time In</span>
                                    <span style="display: block; font-size: 14px; font-weight: 500; color: var(--text-heading);"><?php echo $time_in; ?></span>
                                </div>

                                <div>
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Chief Complaint</span>
                                    <span style="display: block; font-size: 14px; font-weight: 500; color: var(--text-heading);"><?php echo htmlspecialchars($visit['complaint']); ?></span>
                                </div>

                                <div>
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Temperature</span>
                                    <span class="badge <?php echo $badge_style; ?>" style="font-size: 13px; padding: 4px 10px;"><?php echo htmlspecialchars($visit['temperature']); ?> °C</span>
                                </div>

                                <div>
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Height</span>
                                    <span style="display: block; font-size: 14px; font-weight: 500; color: var(--text-heading);"><?php echo htmlspecialchars($visit['height'] ?? '--'); ?> cm</span>
                                </div>

                                <div>
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Weight</span>
                                    <span style="display: block; font-size: 14px; font-weight: 500; color: var(--text-heading);"><?php echo htmlspecialchars($visit['weight'] ?? '--'); ?> kg</span>
                                </div>

                                <div style="grid-column: 1 / -1; margin-top: 10px; padding-top: 15px; border-top: 1px dashed var(--border);">
                                    <span style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">Initial Nurse Notes</span>
                                    <span style="display: block; font-size: 14px; font-weight: 500; color: var(--text-heading); font-style: italic;">
                                        "<?php echo htmlspecialchars($visit['nurse_notes'] ?: 'No initial notes provided during triage.'); ?>"
                                    </span>
                                </div>
                            </div>
                        </div>

                        <h3 style="font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--text-heading); margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                            <i class="ph ph-stethoscope" style="color: var(--brand-primary); margin-right: 8px;"></i>Medical Assessment & Treatment
                        </h3>

                        <div style="margin-bottom: 40px;">

                            <!-- Diagnosis input with autocomplete -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Final Diagnosis / Assessment</label>
                                <div class="autocomplete-wrapper">
                                    <div style="position: relative;">
                                        <i class="ph ph-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 18px;"></i>
                                        <input type="text" id="diagnosisInput" name="diagnosis" placeholder="Search recent records or type diagnosis..." required autocomplete="off" style="width: 100%; padding: 12px 16px 12px 40px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px;">
                                    </div>
                                    <div id="diagnosisSuggestions" class="suggestions-list"></div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Treatment Administered / Notes</label>
                                <textarea name="treatment" placeholder="e.g. Advised to rest for 30 minutes, drank water, cold compress applied..." required rows="3" style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; resize: vertical;"></textarea>
                            </div>
                        </div>

                        <h3 style="font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--text-heading); margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">
                            <i class="ph ph-pill" style="color: var(--brand-primary); margin-right: 8px;"></i>Dispense Medicine (If Applicable)
                        </h3>

                        <div id="medicine-container" style="margin-bottom: 15px;"></div>

                        <button type="button" id="add-medicine-btn" class="btn btn-ghost" style="margin-bottom: 32px; padding: 8px 16px; font-size: 13px;">
                            <i class="ph ph-plus"></i> Add Medicine
                        </button>

                        <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border); padding-top: 24px;">
                            <a href="../visits.php" class="btn btn-ghost" style="padding: 12px 24px;">Cancel</a>
                            <button type="submit" class="btn btn-primary" style="padding: 12px 24px;"><i class="ph ph-check-circle"></i> Finalize & Clear Patient</button>
                        </div>

                    </form>
                </div>

            </main>
        </div>
    </div>

    <template id="medicine-row-template">
        <div class="medicine-row" style="display: grid; grid-template-columns: 3fr 1fr auto; gap: 20px; align-items: end; margin-bottom: 15px;">
            <div class="form-group" style="margin: 0;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Select Medicine</label>
                <select name="medicine_id[]" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-heading);">
                    <option value="" disabled selected>Select Medicine...</option>
                    <?php foreach ($medicines as $med):
                        $isDisabled = ($med['quantity'] <= 0 || $med['status'] == 'Out of Stock') ? 'disabled' : '';
                        $stockText = ($med['quantity'] <= 0) ? 'Out of Stock' : 'Stock: ' . $med['quantity'];
                    ?>
                        <option value="<?php echo $med['med_id']; ?>" <?php echo $isDisabled; ?>>
                            <?php echo htmlspecialchars($med['name']) . ' (' . $stockText . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--text-body); text-transform: uppercase; margin-bottom: 8px;">Quantity</label>
                <input type="number" name="quantity[]" min="1" placeholder="1" required style="width: 100%; padding: 12px 16px; background: var(--bg-card); border: 1.5px solid var(--border); border-radius: var(--r-md); font-family: 'Outfit', sans-serif; font-size: 14px;">
            </div>

            <div class="form-group" style="margin: 0; padding-bottom: 4px;">
                <button type="button" class="btn btn-ghost remove-btn" onclick="removeMedicineRow(this)" style="color: var(--text-muted); padding: 10px;" title="Remove this medicine">
                    <i class="ph ph-trash" style="font-size: 20px;"></i>
                </button>
            </div>
        </div>
    </template>

    <script>
        // Keep the Consultation nav item active in this subfolder.
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            const consultationLink = document.querySelector('a[href$="visits.php"]');
            if (consultationLink) consultationLink.classList.add('active');

            // Add a loading state to the submit button.
            document.querySelector('form').addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    if (btn.classList.contains('btn-loading')) return false;
                    btn.classList.add('btn-loading');
                    btn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="display: inline-block; animation: spin 1s linear infinite; margin-right: 8px;"></i> Finalizing...';
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

        // Add and remove medicine rows dynamically.
        document.getElementById('add-medicine-btn').addEventListener('click', function() {
            const container = document.getElementById('medicine-container');
            const template = document.getElementById('medicine-row-template');
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
        });

        function removeMedicineRow(button) {
            button.closest('.medicine-row').remove();
        }

        // Autocomplete logic for complaints and diagnosis.
        const diagnosesData = <?php echo json_encode($recent_diagnoses); ?>;
        const complaintsData = <?php echo json_encode($recent_complaints); ?>;

        function setupAutocomplete(inputId, suggestionsId, dataArray, iconClass) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);

            function showSuggestions(val) {
                suggestions.innerHTML = '';

                let filtered = dataArray;
                if (val) {
                    filtered = dataArray.filter(item => item.toLowerCase().includes(val.toLowerCase()));
                }

                if (filtered.length > 0) {
                    filtered.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';

                        let displayText = item;
                        if (val) {
                            const regex = new RegExp(`(${val})`, "gi");
                            displayText = item.replace(regex, "<strong style='color: inherit; text-decoration: underline;'>$1</strong>");
                        }

                        div.innerHTML = `<i class="${iconClass}" style="opacity: 0.6; font-size: 16px;"></i> <span style="font-weight: 500;">${displayText}</span>`;

                        div.onclick = function() {
                            input.value = item;
                            suggestions.style.display = 'none';
                        };
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            }

            // Show all options on focus, then filter while typing.
            input.addEventListener('focus', function() {
                this.select();
                showSuggestions('');
            });
            input.addEventListener('input', function() {
                showSuggestions(this.value.trim());
            });

            // Hide suggestions when clicking outside.
            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !suggestions.contains(e.target)) {
                    suggestions.style.display = 'none';
                }
            });
        }

        // Initialize autocomplete inputs.
        setupAutocomplete('diagnosisInput', 'diagnosisSuggestions', diagnosesData, 'ph ph-activity');
    </script>
    <script src="../../assets/js/script.js"></script>
</body>

</html>