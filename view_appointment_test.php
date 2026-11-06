
<?php
// Connect to the database
$conn = new mysqli("localhost", "root", "", "student_system");

// Check the connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get all appointments
$sql = "SELECT * FROM appointments ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointments</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f4f0;
            margin: 0;
            padding: 30px;
        }

        h1 {
            text-align: center;
            color: #14532d;
        }

        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background: #14532d;
            color: white;
        }

        tr:nth-child(even) {
            background: #f2f7f2;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            background: #14532d;
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 5px;
        }

        .message {
            padding: 12px;
            background: #fff3cd;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Student Appointment Records</h1>

        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <?php
                        while ($field = $result->fetch_field()) {
                            echo "<th>" .
                                htmlspecialchars($field->name) .
                                "</th>";
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result->data_seek(0);

                    while ($row = $result->fetch_assoc()):
                    ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td>
                                    <?= htmlspecialchars((string)($value ?? "")) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

        <?php elseif ($result): ?>
            <p class="message">No appointments have been recorded yet.</p>

        <?php else: ?>
            <p class="message">
                Could not retrieve appointments. Please check your
                database table name and column structure.
            </p>
        <?php endif; ?>

        <a class="back" href="book_appointment.php">
            Back to Booking
        </a>
    </div>
</body>
</html>

<?php
$conn->close();
?>
