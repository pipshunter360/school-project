```php
<?php
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the current session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit;
?>
```