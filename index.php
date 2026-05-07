<?php
// Login page for the KCCF Clinic Management System.

// SVG icon map used by the page UI.

$icon = [

    // Input icons
    'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',

    'lock' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',

    // Password visibility icons
    'eye' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',

    'eye-off' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',

    // Alert icon
    'alert-triangle' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',

    // Left panel feature icons
    'activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',

    'package' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',

    'bar-chart' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',

    'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',

    // Badge icons
    'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',

    'box' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',

    'graduation-cap' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"/></svg>',

];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | KCCF Clinic Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="assets/css/style.css?v=20260504002">
    <style>
        /* Login page split layout */

        /* Root wrapper */
        .login-wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Left branded panel */
        .login-left {
            flex: 0 0 52%;
            background: var(--bg-sidebar);
            /* Brand green */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 48px 56px;
            position: relative;
            overflow: hidden;
            animation: fadeIn var(--t-slow) var(--ease-out) both;
        }

        /* Decorative circles */
        .login-left::before {
            content: '';
            position: absolute;
            top: -120px;
            right: -120px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            pointer-events: none;
        }

        .login-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(216, 163, 49, 0.07);
            pointer-events: none;
        }

        /* Gold ring accents */
        .login-deco-ring {
            position: absolute;
            bottom: 140px;
            right: -60px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 2px solid rgba(216, 163, 49, 0.2);
            pointer-events: none;
        }

        .login-deco-ring-2 {
            position: absolute;
            bottom: 110px;
            right: -30px;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 1px solid rgba(216, 163, 49, 0.1);
            pointer-events: none;
        }

        /* --- Floating live-stat pills --- */
        @keyframes float-a {

            0%,
            100% {
                transform: translateY(0) rotate(-6deg);
            }

            50% {
                transform: translateY(-10px) rotate(-6deg);
            }
        }

        @keyframes float-b {

            0%,
            100% {
                transform: translateY(0) rotate(12deg);
            }

            50% {
                transform: translateY(-14px) rotate(12deg);
            }
        }

        @keyframes float-c {

            0%,
            100% {
                transform: translateY(0) rotate(-3deg);
            }

            50% {
                transform: translateY(-8px) rotate(-3deg);
            }
        }

        .login-float-badge {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: var(--r-pill);
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            white-space: nowrap;
            pointer-events: none;
        }

        .login-float-badge .lfb-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #4ade80;
            box-shadow: 0 0 6px #4ade80;
            flex-shrink: 0;
        }

        .lfb-1 {
            right: 48px;
            top: 170px;
            animation: float-a 5s ease-in-out infinite;
        }

        .lfb-2 {
            right: 22px;
            top: 280px;
            animation: float-b 6s ease-in-out 1s infinite;
        }

        .lfb-3 {
            right: 60px;
            top: 390px;
            animation: float-c 4.5s ease-in-out 0.5s infinite;
        }

        /* --- Left brand row (top) --- */
        .left-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            animation: fadeUp var(--t-std) var(--ease-out) 0.1s both;
        }

        .left-logo-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: var(--brand-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            color: var(--brand-deeper);
            box-shadow: 0 0 0 3px rgba(216, 163, 49, 0.35);
            letter-spacing: -1px;
            flex-shrink: 0;
        }

        /* If using an actual logo image on the left panel */
        .left-logo-img {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 0 3px rgba(216, 163, 49, 0.35);
            flex-shrink: 0;
        }

        .left-brand-name {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: white;
            letter-spacing: 0.2px;
            margin-bottom: 4px;
        }

        .left-brand-sub {
            font-size: 11px;
            font-weight: 700;
            color: var(--brand-accent);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* --- Center content block --- */
        .left-center {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 0;
        }

        .left-eyebrow {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--brand-accent);
            margin-bottom: 18px;
            animation: fadeUp var(--t-std) var(--ease-out) 0.2s both;
        }

        .left-headline {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 40px;
            line-height: 1.17;
            color: white;
            font-weight: 400;
            margin-bottom: 20px;
            animation: fadeUp var(--t-std) var(--ease-out) 0.28s both;
        }

        .left-headline em {
            color: var(--brand-accent-lt);
            font-style: italic;
        }

        .left-sub {
            font-size: 14.5px;
            color: rgba(255, 255, 255, 0.62);
            line-height: 1.75;
            max-width: 340px;
            margin-bottom: 38px;
            animation: fadeUp var(--t-std) var(--ease-out) 0.36s both;
        }

        /* Feature list */
        .left-features {
            display: flex;
            flex-direction: column;
            gap: 13px;
            animation: fadeUp var(--t-std) var(--ease-out) 0.44s both;
        }

        .left-feature-row {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .left-feature-icon {
            width: 34px;
            height: 34px;
            border-radius: var(--r-sm);
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .left-feature-label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.78);
            font-weight: 500;
        }

        /* --- Left footer (version) --- */
        .left-footer {
            animation: fadeUp var(--t-std) var(--ease-out) 0.5s both;
        }

        .left-version-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--r-pill);
            padding: 7px 14px;
            font-size: 11.5px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 500;
        }

        .left-version-badge .lvb-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #4ade80;
            flex-shrink: 0;
        }

        /* Right form panel */
        .login-right {
            flex: 1;
            background: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 40px;
            overflow-y: auto;
        }

        .login-form-wrap {
            width: 100%;
            max-width: 380px;
            animation: fadeUp var(--t-slow) var(--ease-out) 0.15s both;
        }

        /* Form heading */
        .login-form-top {
            margin-bottom: 36px;
        }

        .login-form-eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--brand-primary);
            margin-bottom: 10px;
        }

        .login-form-title {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 30px;
            font-weight: 400;
            color: var(--text-heading);
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .login-form-subtitle {
            font-size: 14px;
            color: var(--text-muted);
        }

        /* Input with leading icon */
        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.45;
            pointer-events: none;
            line-height: 1;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }

        .input-icon svg {
            width: 14px;
            height: 14px;
            display: block;
        }

        @media (min-width: 768px) {
            .input-icon {
                left: 10px;
                width: 26px;
                height: 26px;
            }

            .input-icon svg {
                width: 15px;
                height: 15px;
            }
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            /* Mobile padding */
            text-indent: 0;
            background: var(--bg-base);
            border: 1.5px solid var(--border);
            border-radius: var(--r-md);
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            color: var(--text-heading);
            transition: all var(--t-std) var(--ease-out);
            outline: none;
            min-height: 40px;
        }

        @media (min-width: 768px) {
            .input-wrapper input {
                padding: 13px 14px 13px 42px;
                /* Desktop padding */
                font-size: 14px;
                min-height: auto;
            }
        }

        .input-wrapper input:focus {
            background: var(--bg-card);
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px var(--brand-glow);
        }

        /* Hide native browser password controls so only the custom eye icon shows. */
        .input-wrapper input[type="password"]::-ms-reveal,
        .input-wrapper input[type="password"]::-ms-clear,
        .input-wrapper input[type="password"]::-webkit-credentials-auto-fill-button,
        .input-wrapper input[type="password"]::-webkit-textfield-decoration-container,
        .input-wrapper input[type="password"]::-webkit-strong-password-auto-fill-button {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
            opacity: 0 !important;
        }

        .input-wrapper input::placeholder {
            color: var(--text-xmuted);
            opacity: 1;
        }

        /* Password eye toggle */
        .input-eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            opacity: 0.45;
            padding: 6px;
            transition: opacity var(--t-fast);
            line-height: 1;
            min-width: 32px;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (min-width: 768px) {
            .input-eye-btn {
                right: 13px;
                font-size: 15px;
                min-width: auto;
                min-height: auto;
                padding: 4px;
            }
        }

        .input-eye-btn:hover {
            opacity: 0.85;
        }

        .input-eye-btn:active {
            opacity: 1;
        }

        /* Error state */
        .input-wrapper.has-error input {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .input-error-msg {
            font-size: 12px;
            color: #ef4444;
            margin-top: 6px;
            font-weight: 500;
            display: none;
        }

        .input-wrapper.has-error~.input-error-msg {
            display: block;
        }

        /* Flash error alert (from PHP) */
        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 16px;
            background: #fef2f2;
            border: 1.5px solid #fecaca;
            border-radius: var(--r-md);
            margin-bottom: 20px;
            font-size: 13px;
            color: #dc2626;
            font-weight: 500;
            animation: fadeUp var(--t-std) var(--ease-out) both;
        }

        .login-alert-icon {
            font-size: 16px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Submit button */
        .form-actions {
            margin-top: 8px;
        }

        .login-submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--brand-primary);
            color: white;
            border: none;
            border-radius: var(--r-md);
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all var(--t-std) var(--ease-out);
            box-shadow: 0 4px 14px rgba(22, 123, 70, 0.3);
        }

        .login-submit-btn:hover {
            background: var(--brand-deep);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(22, 123, 70, 0.35);
        }

        .login-submit-btn:active {
            transform: translateY(0);
        }

        /* Divider */
        .login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
        }

        .login-divider-line {
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .login-divider-txt {
            font-size: 12px;
            color: var(--text-xmuted);
            font-weight: 600;
            white-space: nowrap;
        }

        /* Support link */
        .login-support {
            text-align: center;
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .login-support a {
            color: var(--brand-primary);
            font-weight: 600;
            transition: color var(--t-fast);
        }

        .login-support a:hover {
            color: var(--brand-deep);
        }

        /* Footer copyright */
        .login-footer {
            text-align: center;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .login-footer p {
            font-size: 11.5px;
            color: var(--text-xmuted);
        }

        /* ---- RESPONSIVE ---- */
        @media (max-width: 900px) {
            .login-left {
                flex: 0 0 44%;
                padding: 40px 36px;
            }

            .left-headline {
                font-size: 32px;
            }

            .login-float-badge {
                display: none;
            }
        }

        @media (max-width: 680px) {
            .login-wrapper {
                flex-direction: column;
            }

            .login-left {
                flex: none;
                padding: 36px 28px 32px;
                min-height: auto;
            }

            .left-center {
                padding: 28px 0 24px;
            }

            .left-headline {
                font-size: 28px;
            }

            .left-sub,
            .left-features,
            .left-footer {
                display: none;
            }

            .login-right {
                flex: 1;
                padding: 32px 24px;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <!-- Left branded panel -->
        <div class="login-left">

            <!-- Decorative rings -->
            <div class="login-deco-ring"></div>
            <div class="login-deco-ring-2"></div>



            <!-- Top brand -->
            <div class="left-brand">
                <img src="assets/images/logo.jpg" alt="KCCF Logo" class="left-logo-img"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="left-logo-circle" style="display:none;">K</div>
                <div>
                    <span class="left-brand-name"> Kurios Christian Colleges Foundation</span>
                    <span class="left-brand-sub">Health Record System</span>
                </div>
            </div>

            <!-- Headline and features -->
            <div class="left-center">
                <span class="left-eyebrow">Clinic Management System</span>
                <h1 class="left-headline">
                    Caring for students,<br><em>simplified.</em>
                </h1>
                <p class="left-sub">
                    A centralized health record system built for Kurios Christian Colleges Foundation — fast, reliable, and purpose-built for school clinics.
                </p>

                <div class="left-features">
                    <div class="left-feature-row">
                        <div class="left-feature-icon"><?= $icon['activity'] ?></div>
                        <span class="left-feature-label">Patient visits &amp; consultations</span>
                    </div>
                    <div class="left-feature-row">
                        <div class="left-feature-icon"><?= $icon['package'] ?></div>
                        <span class="left-feature-label">Medicine inventory &amp; stock alerts</span>
                    </div>
                    <div class="left-feature-row">
                        <div class="left-feature-icon"><?= $icon['bar-chart'] ?></div>
                        <span class="left-feature-label">Health reports &amp; analytics</span>
                    </div>
                    <div class="left-feature-row">
                        <div class="left-feature-icon"><?= $icon['users'] ?></div>
                        <span class="left-feature-label">Student records management</span>
                    </div>
                </div>
            </div>

            <!-- Version badge -->
            <div class="left-footer">
                <div class="left-version-badge">
                    <span class="lvb-dot"></span>
                    v1.0 &nbsp;·&nbsp; School Year 2025–2026
                </div>
            </div>

        </div><!-- /.login-left -->


        <!-- Right form panel -->
        <div class="login-right">
            <div class="login-form-wrap">

                <!-- Heading -->
                <div class="login-form-top">
                    <p class="login-form-eyebrow">Authorized Personnel Only</p>
                    <h2 class="login-form-title">Welcome back</h2>
                    <p class="login-form-subtitle">Sign in to access the clinic dashboard.</p>
                </div>

                <!-- Error alert -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="login-alert">
                        <span class="login-alert-icon"><?= $icon['alert-triangle'] ?></span>
                        <span>
                            <?php
                            $errors = [
                                'invalid'  => 'Incorrect username or password. Please try again.',
                                'empty'    => 'Please fill in both username and password.',
                                'inactive' => 'Your account is inactive. Contact the administrator.',
                            ];
                            echo htmlspecialchars($errors[$_GET['error']] ?? 'An error occurred. Please try again.');
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Login form -->
                <form action="backend/auth_login.php" method="POST" class="login-form" novalidate>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><?= $icon['user'] ?></span>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="e.g. admin_01"
                                required
                                autocomplete="username"
                                value="<?= htmlspecialchars($_GET['last_user'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon"><?= $icon['lock'] ?></span>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password">
                            <button type="button" class="input-eye-btn" id="eyeBtn" title="Show password">
                                <?= $icon['eye'] ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="login-submit-btn">
                            Sign In

                        </button>
                    </div>

                </form>

                <!-- Divider -->
                <div class="login-divider">
                    <span class="login-divider-line"></span>
                    <span class="login-divider-txt">Need help?</span>
                    <span class="login-divider-line"></span>
                </div>

                <!-- Support -->
                <p class="login-support">
                    Contact your <a href="mailto:admin@kccf.edu.ph">system administrator</a> for account access issues.
                </p>

                <!-- Footer -->
                <div class="login-footer">
                    <p>Clinic Health Record Management System &copy; <?= date('Y') ?></p>
                </div>

            </div>
        </div><!-- /.login-right -->

    </div><!-- /.login-wrapper -->

    <script>
        // --- SVG strings for eye toggle (JS-side swap) ---
        const SVG_EYE = `<?= addslashes($icon['eye']) ?>`;
        const SVG_EYE_OFF = `<?= addslashes($icon['eye-off']) ?>`;

        const eyeBtn = document.getElementById('eyeBtn');
        const pwInput = document.getElementById('password');

        eyeBtn.addEventListener('click', () => {
            const isPassword = pwInput.type === 'password';
            pwInput.type = isPassword ? 'text' : 'password';
            eyeBtn.innerHTML = isPassword ? SVG_EYE_OFF : SVG_EYE;
            eyeBtn.title = isPassword ? 'Hide password' : 'Show password';
            pwInput.focus();
        });

        // --- Basic client-side validation ---
        const loginForm = document.querySelector('.login-form');
        loginForm.addEventListener('submit', function(e) {
            const user = document.getElementById('username').value.trim();
            const pass = document.getElementById('password').value;
            if (!user || !pass) {
                e.preventDefault();
                window.location.href = '?error=empty';
                return;
            }

            // Add spinner to submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                if (submitBtn.classList.contains('btn-loading')) {
                    e.preventDefault();
                    return false;
                }
                submitBtn.classList.add('btn-loading');
                submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite; margin-right: 8px;">⟳</span> Signing in...';
                submitBtn.disabled = true;
            }
        });

        // Spinner animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
    <script src="assets/js/script.js"></script>
</body>

</html>