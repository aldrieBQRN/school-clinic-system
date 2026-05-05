<?php
// End the current session and redirect to the login page.
session_start();

// Clear all session data.
$_SESSION = array();

// Remove the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the server-side session.
session_destroy();

// Redirect to login.
header("Location: index.php");
exit();
