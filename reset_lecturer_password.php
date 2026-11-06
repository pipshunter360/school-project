
<?php
require_once 'db.php';

$username = 'lecturer';
$new_password = 'Lecturer@123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE lecturers SET password = ? WHERE username = ?"
);

$stmt->bind_param("ss", $hashed_password, $username);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    echo "Lecturer password reset successfully!";
    echo "<br>Username: lecturer";
    echo "<br>Password: Lecturer@123";
} elseif ($stmt->affected_rows === 0) {
    echo "No row changed. Check that the lecturer username exists and the password was already updated.";
} else {
    echo "Error resetting password.";
}

$stmt->close();
$conn->close();
?>
