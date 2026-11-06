<?php
session_start();
require_once __DIR__ . '/db.php';

// Ensure the student is logged in
if (isset($_SESSION['matric'])) {
    $matric = $_SESSION['matric'];

    // Mark all unread notifications for this student as read
    $stmt = $conn->prepare(
        "UPDATE notifications 
         SET is_read = 1 
         WHERE student_matric = ? AND is_read = 0"
    );

    if ($stmt) {
        $stmt->bind_param("s", $matric);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect to the actual notifications page
header("Location: student_notifications.php");
exit;