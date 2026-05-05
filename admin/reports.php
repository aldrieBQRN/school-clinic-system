<?php
require_once '../config/db_connect.php';
require_once '../config/auth_check.php';

// Date filters for the reporting window
$from_date = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to_date = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

try {
    // Summary statistics
    $stmt = $conn->prepare("SELECT COUNT(*) FROM visits WHERE date_logged BETWEEN ? AND ?");
    $stmt->execute([$from_date, $to_date]);
    $total_consults = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM health_records WHERE date BETWEEN ? AND ?");
    $stmt->execute([$from_date, $to_date]);
    $treatments_finalized = $stmt->fetchColumn();

    // Top complaints
    $stmt = $conn->prepare("SELECT complaint, COUNT(*) as count FROM visits WHERE date_logged BETWEEN ? AND ? GROUP BY complaint ORDER BY count DESC LIMIT 5");
    $stmt->execute([$from_date, $to_date]);
    $top_complaints = $stmt->fetchAll();

    // Visits by course
    $stmt = $conn->prepare("SELECT s.course, COUNT(*) as count FROM visits v JOIN students s ON v.student_id = s.student_id WHERE v.date_logged BETWEEN ? AND ? GROUP BY s.course ORDER BY count DESC");
    $stmt->execute([$from_date, $to_date]);
    $course_stats = $stmt->fetchAll();

    // Low-stock inventory alerts
    $stmt = $conn->query("SELECT name, quantity, category FROM medicines WHERE quantity <= 10 ORDER BY quantity ASC LIMIT 5");
    $inventory_alerts = $stmt->fetchAll();

    // Gender breakdown
    $stmt = $conn->prepare("SELECT s.gender, COUNT(*) as count FROM visits v JOIN students s ON v.student_id = s.student_id WHERE v.date_logged BETWEEN ? AND ? GROUP BY s.gender");
    $stmt->execute([$from_date, $to_date]);
    $gender_stats = $stmt->fetchAll();

    // Peak clinic hours
    $stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN time_in < '12:00:00' THEN 1 ELSE 0 END) as morning_cases,
            SUM(CASE WHEN time_in >= '12:00:00' THEN 1 ELSE 0 END) as afternoon_cases
        FROM visits
        WHERE date_logged BETWEEN ? AND ?
    ");
    $stmt->execute([$from_date, $to_date]);
    $peak_hours = $stmt->fetch(PDO::FETCH_ASSOC);

    // High fever cases
    $stmt = $conn->prepare("SELECT COUNT(*) FROM visits WHERE temperature >= 37.8 AND date_logged BETWEEN ? AND ?");
    $stmt->execute([$from_date, $to_date]);
    $fever_cases = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Executive Report Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Health Summary | KCCF Clinic</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Reports styles */

        .report-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .report-summary-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--r-md);
            padding: 20px;
            border-left: 4px solid var(--brand-primary);
        }

        .rsc-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: block;
        }

        .rsc-value {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 32px;
            color: var(--text-heading);
            line-height: 1;
            margin: 0 0 12px 0 !important;
            display: block;
        }

        .rsc-trend {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            display: block;
            margin: 0;
        }

        /* Inline styles */

        .summary-box {
            padding: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-heading);
            margin-top: 5px;
        }

        /* Filter Controls UI */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-card);
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .date-input {
            padding: 6px 10px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-family: inherit;
            font-size: 13px;
            background: var(--bg-base);
            color: var(--text-heading);
            outline: none;
        }

        .date-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        /* Loading State Overlay */
        .table-wrapper {
            position: relative;
            min-height: 300px;
        }

        .loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 5;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            border-radius: 12px;
        }

        .is-loading .loader-overlay {
            opacity: 1;
            pointer-events: all;
        }

        .spinner-icon {
            font-size: 32px;
            color: var(--brand-primary);
            animation: spin 1s linear infinite;
            margin-bottom: 8px;
        }

        .spinner-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        @keyframes spin {
            100% {
                transform: rotate(360deg);
            }
        }

        /* PDF Page Break Protections */
        .avoid-break {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        @media (max-width: 768px) {
            .report-grid-flex {
                flex-direction: column !important;
            }

            .report-summary-flex {
                flex-direction: column !important;
            }
        }
    </style>
</head>

<body>

    <div class="wrapper">
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="main-container">
            <?php include '../includes/admin_header.php'; ?>

            <main class="content-body">
                <div class="page-header print-hide">
                    <div class="page-header-text">
                        <p class="page-eyebrow">Analytics & Records</p>
                        <h1 class="page-title">Executive Summary</h1>
                        <p class="page-subtitle">High-level overview of clinic operations, illness patterns, and inventory status.</p>
                    </div>
                </div>

                <div class="filter-bar print-hide">
                    <div class="filter-group">
                        <span class="date-label">From:</span>
                        <input type="date" id="fromDate" class="date-input" value="<?php echo $from_date; ?>">

                        <span class="date-label">To:</span>
                        <input type="date" id="toDate" class="date-input" value="<?php echo $to_date; ?>">
                    </div>

                    <div class="page-actions">
                        <button class="btn btn-ghost" onclick="exportToPDF()" style="border-radius: 8px;">
                            <i class="ph ph-file-pdf" style="color: #EF4444; font-size: 18px;"></i> Export PDF
                        </button>
                    </div>
                </div>

                <div class="table-wrapper" id="contentWrapper">
                    <div class="loader-overlay">
                        <i class="ph-bold ph-spinner-gap spinner-icon"></i>
                        <span class="spinner-text">Updating report...</span>
                    </div>

                    <div id="pdfExportArea">

                        <div id="pdfHeader" class="avoid-break" style="display: none; text-align: center; margin-bottom: 15px; border-bottom: 2px solid #1e293b; padding-bottom: 10px; background: #fff;">
                            <img src="../assets/images/logo.jpg" alt="KCCF Logo" style="width: 55px; height: 55px; margin-bottom: 5px; border-radius: 50%;">
                            <h2 style="margin: 0; color: #1e293b; font-family: 'DM Serif Display', serif; font-size: 20px;">Kurios Christian Colleges Foundation</h2>
                            <p style="margin: 0; color: #64748b; font-size: 13px;">Magallanes, Cavite</p>
                            <h3 style="margin-top: 10px; margin-bottom: 5px; text-transform: uppercase; font-size: 15px; letter-spacing: 0.5px;">Executive Health Summary Report</h3>
                            <p style="font-size: 12px; color: #475569; margin: 0;">Period: <span id="displayFrom"><?php echo date('M d, Y', strtotime($from_date)); ?></span> to <span id="displayTo"><?php echo date('M d, Y', strtotime($to_date)); ?></span></p>
                        </div>

                        <div id="reportContainer">

                            <div class="report-summary-flex avoid-break" style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <div class="report-summary-card" style="flex: 1; background: var(--bg-card); padding: 32px; border-radius: 12px; border: 1px solid var(--border); box-sizing: border-box;">
                                    <div class="rsc-title" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px; display: block;">Total Consultations</div>
                                    <div class="rsc-value" style="font-size: 32px; font-weight: 700; color: var(--text-heading); margin: 0 0 12px 0; display: block;"><?php echo number_format($total_consults); ?></div>
                                    <div class="rsc-trend" style="font-size: 12px; color: var(--text-muted); margin: 0; display: block;">Students treated in clinic</div>
                                </div>
                                <div class="report-summary-card" style="flex: 1; background: var(--bg-card); padding: 32px; border-radius: 12px; border: 1px solid var(--border); box-sizing: border-box;">
                                    <div class="rsc-title" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px; display: block;">Medication Finalized</div>
                                    <div class="rsc-value" style="font-size: 32px; font-weight: 700; color: var(--text-heading); margin: 0 0 12px 0; display: block;"><?php echo number_format($treatments_finalized); ?></div>
                                    <div class="rsc-trend" style="font-size: 12px; color: var(--text-muted); margin: 0; display: block;">Prescriptions/Treatments given</div>
                                </div>
                                <div class="report-summary-card" style="flex: 1; background: var(--bg-card); padding: 32px; border-radius: 12px; border: 1px solid var(--border); box-sizing: border-box;">
                                    <div class="rsc-title" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 12px; display: block;">Clinic Productivity</div>
                                    <div class="rsc-value" style="font-size: 32px; font-weight: 700; color: var(--brand-primary); margin: 0 0 12px 0; display: block;"><?php echo $total_consults > 0 ? round(($treatments_finalized / $total_consults) * 100) : 0; ?>%</div>
                                    <div class="rsc-trend" style="font-size: 12px; color: var(--text-muted); margin: 0; display: block;">Triage to Treatment conversion</div>
                                </div>
                            </div>

                            <div class="report-grid-flex avoid-break" style="display: flex; gap: 15px; margin-bottom: 15px;">
                                <div class="panel" style="flex: 1; border-radius: 12px; border: 1px solid var(--border); min-width: 0;">
                                    <div class="panel-header" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border);">
                                        <h2 class="panel-title" style="font-size: 14px;">Top 5 Illnesses / Complaints</h2>
                                    </div>
                                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                        <tbody>
                                            <?php if ($top_complaints): foreach ($top_complaints as $tc): ?>
                                                    <tr>
                                                        <td style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($tc['complaint']); ?></td>
                                                        <td class="text-right" style="padding: 12px 16px; border-bottom: 1px solid var(--border); color: var(--brand-primary); font-weight: 700; font-size: 13px;"><?php echo $tc['count']; ?> Cases</td>
                                                    </tr>
                                                <?php endforeach;
                                            else: ?>
                                                <tr>
                                                    <td colspan="2" style="text-align: center; color: var(--text-muted); padding: 20px;">No data found for this period.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="panel" style="flex: 1; border-radius: 12px; border: 1px solid var(--border); min-width: 0;">
                                    <div class="panel-header" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border);">
                                        <h2 class="panel-title" style="font-size: 14px;">Volume by Course</h2>
                                    </div>
                                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                        <tbody>
                                            <?php if ($course_stats): foreach ($course_stats as $gs): ?>
                                                    <tr>
                                                        <td style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($gs['course']); ?></td>
                                                        <td class="text-right" style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 13px;"><?php echo $gs['count']; ?> visits</td>
                                                    </tr>
                                                <?php endforeach;
                                            else: ?>
                                                <tr>
                                                    <td colspan="2" style="text-align: center; color: var(--text-muted); padding: 20px;">No data found for this period.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="html2pdf__page-break" style="page-break-before: always; height: 0; width: 100%;"></div>

                            <div class="report-grid-flex avoid-break" style="display: flex; gap: 15px; margin-bottom: 15px;">

                                <div class="panel" style="flex: 1; border-left: 4px solid #ef4444; border-radius: 12px; border-top: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); min-width: 0;">
                                    <div class="panel-header" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border);">
                                        <h2 class="panel-title" style="font-size: 14px; color: #b91c1c;"><i class="ph-fill ph-warning-octagon"></i> Critical Stock Alerts</h2>
                                    </div>
                                    <table class="data-table" style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); background: var(--bg-base);">
                                                <th style="padding: 12px 16px; text-align: left;">Medicine Name</th>
                                                <th style="padding: 12px 16px; text-align: left;">Category</th>
                                                <th class="text-right" style="padding: 12px 16px;">Stock Left</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($inventory_alerts): foreach ($inventory_alerts as $ia): ?>
                                                    <tr>
                                                        <td style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($ia['name']); ?></td>
                                                        <td style="padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($ia['category']); ?></td>
                                                        <td class="text-right" style="padding: 12px 16px; border-bottom: 1px solid var(--border);"><span class="badge badge-red"><?php echo $ia['quantity']; ?> units</span></td>
                                                    </tr>
                                                <?php endforeach;
                                            else: ?>
                                                <tr>
                                                    <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">All stock levels are healthy.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="panel" style="flex: 1; border-radius: 12px; border: 1px solid var(--border); min-width: 0;">
                                    <div class="panel-header" style="padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border);">
                                        <h2 class="panel-title" style="font-size: 14px;">Student Demographics & Vitals</h2>
                                    </div>

                                    <div style="padding: 20px; display: flex; gap: 15px; text-align: center;">
                                        <?php if ($gender_stats): foreach ($gender_stats as $gen): ?>
                                                <div style="flex: 1; background: var(--bg-base); padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                                                    <div class="stat-label"><?php echo htmlspecialchars($gen['gender']); ?></div>
                                                    <div class="stat-value" style="font-size: 20px;"><?php echo $gen['count']; ?></div>
                                                </div>
                                            <?php endforeach;
                                        else: ?>
                                            <div style="flex: 1; background: var(--bg-base); padding: 12px; border-radius: 8px; border: 1px solid var(--border); color: var(--text-muted);">No demographics data available.</div>
                                        <?php endif; ?>
                                    </div>

                                    <div style="padding: 0 20px 20px;">
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <div style="display: flex; justify-content: space-between; padding: 10px 12px; background: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0;">
                                                <span style="font-size: 13px; font-weight: 600; color: #334155;">Morning Visits (Before 12 PM)</span>
                                                <span style="font-weight: 700; color: #334155;"><?php echo $peak_hours['morning_cases'] ?? 0; ?></span>
                                            </div>

                                            <div style="display: flex; justify-content: space-between; padding: 10px 12px; background: #FFF7ED; border-radius: 6px; border: 1px solid #FFEDD5;">
                                                <span style="font-size: 13px; font-weight: 600; color: #9A3412;">Afternoon Visits (After 12 PM)</span>
                                                <span style="font-weight: 700; color: #9A3412;"><?php echo $peak_hours['afternoon_cases'] ?? 0; ?></span>
                                            </div>

                                            <div style="display: flex; justify-content: space-between; padding: 10px 12px; background: #FEF2F2; border-radius: 6px; border: 1px solid #FEE2E2;">
                                                <span style="font-size: 13px; font-weight: 600; color: #991B1B;">High Fever Cases (37.8°C and above)</span>
                                                <span style="font-weight: 700; color: #991B1B;"><?php echo $fever_cases; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="pdfSignatures" class="avoid-break" style="margin-top: 30px; display: none; justify-content: space-between; padding: 0 80px;">
                            <div style="text-align: center; width: 250px;">
                                <p style="margin-bottom: 40px; font-size: 14px;">Prepared by:</p>
                                <div style="border-top: 1px solid #000; padding-top: 5px;">
                                    <p style="margin: 0; font-weight: 700; font-size: 13px;">SYSTEM ADMINISTRATOR</p>
                                </div>
                            </div>
                            <div style="text-align: center; width: 250px;">
                                <p style="margin-bottom: 40px; font-size: 14px;">Approved by:</p>
                                <div style="border-top: 1px solid #000; padding-top: 5px;">
                                    <p style="margin: 0; font-weight: 700; font-size: 13px;">CLINIC DIRECTOR</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Set Active Nav
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelector('a[href="reports.php"]').classList.add('active');

        // AJAX Filtering Logic
        const fromDate = document.getElementById('fromDate');
        const toDate = document.getElementById('toDate');
        const contentWrapper = document.getElementById('contentWrapper');

        function triggerAjax() {
            contentWrapper.classList.add('is-loading');
            const url = new URL(window.location.href);
            url.searchParams.set('from', fromDate.value);
            url.searchParams.set('to', toDate.value);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    document.getElementById('reportContainer').innerHTML = doc.getElementById('reportContainer').innerHTML;
                    document.getElementById('displayFrom').innerHTML = doc.getElementById('displayFrom').innerHTML;
                    document.getElementById('displayTo').innerHTML = doc.getElementById('displayTo').innerHTML;

                    contentWrapper.classList.remove('is-loading');
                    window.history.pushState({}, '', url);
                })
                .catch(() => contentWrapper.classList.remove('is-loading'));
        }

        fromDate.addEventListener('change', triggerAjax);
        toDate.addEventListener('change', triggerAjax);

        // PERFECTED PDF EXPORT LOGIC
        function exportToPDF() {
            const element = document.getElementById('pdfExportArea');
            const clone = element.cloneNode(true);

            // Reduced padding to allow more content to fit comfortably
            clone.style.background = '#ffffff';
            clone.style.padding = '10px 20px';

            clone.querySelector('#pdfHeader').style.display = 'block';
            clone.querySelector('#pdfSignatures').style.display = 'flex';

            const hiddenWrapper = document.createElement('div');
            hiddenWrapper.style.position = 'absolute';
            hiddenWrapper.style.left = '-9999px';

            // Force a wide landscape container for the clone
            hiddenWrapper.style.width = '1200px';
            hiddenWrapper.appendChild(clone);
            document.body.appendChild(hiddenWrapper);

            const opt = {
                margin: 0.3, // Reduced margin to 0.3 inches
                filename: 'KCCF_Executive_Health_Report.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                },
                jsPDF: {
                    unit: 'in',
                    format: 'letter',
                    orientation: 'landscape'
                },
                pagebreak: {
                    mode: ['css', 'legacy'],
                    avoid: ['.avoid-break', 'tr']
                }
            };

            html2pdf().set(opt).from(clone).save().then(() => {
                document.body.removeChild(hiddenWrapper);
            });
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>