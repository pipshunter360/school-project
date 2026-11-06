
<?php
require_once 'db.php';

$username = 'lecturer';
$password = 'Lecturer@123';
$fullname = 'NSUK Lecturer';

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO lecturers (username, password, fullname)
     VALUES (?, ?, ?)"
);

$stmt->bind_param("sss", $username, $hashed_password, $fullname);

if ($stmt->execute()) {
    echo "Lecturer account created successfully!";
} else {
    echo "Error creating account: " . htmlspecialchars($stmt->error);
}

$stmt->close();
$conn->close();
?>
