
<?php
session_start();
require_once "db.php";

// Make sure the student is logged in
if (!isset($_SESSION['matric'])) {
    header("Location: login.php");
    exit;
}

// Allow cancellation only through a POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: student_dashboard.php");
    exit;
}

$matric = $_SESSION['matric'];
$id = filter_input(INPUT_POST, "appointment_id", FILTER_VALIDATE_INT);

if (!$id) {
    header("Location: student_dashboard.php");
    exit;
}

// Cancel only this student's Pending appointment
$sql = "DELETE FROM appointments
        WHERE id = ?
        AND student_matric = ?
        AND status = 'Pending'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $id, $matric);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION['cancel_message'] = "Your pending appointment was cancelled successfully.";
} else {
    $_SESSION['cancel_message'] = "Appointment not cancelled. It may not be Pending or may not belong to you.";
}

$stmt->close();

header("Location: student_dashboard.php");
exit;
?>
