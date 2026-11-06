
<?php
session_start();

// Clear the staff login session.
$_SESSION = [];

// Destroy the current session.
session_destroy();

// Return to the Staff Login page.
header("Location: staff_login.php");
exit;
