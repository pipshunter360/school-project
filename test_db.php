
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>Database Connection Test</h2>";

require_once __DIR__ . '/db.php';

if (isset($conn) && $conn instanceof mysqli) {
    echo "<p style='color:green;'>Database connection file loaded.</p>";

    if ($conn->connect_error) {
        echo "<p style='color:red;'>Connection failed: "
            . htmlspecialchars($conn->connect_error) . "</p>";
    } else {
        echo "<p style='color:green;'>MySQL connection is working!</p>";

        $result = $conn->query("SHOW TABLES");

        if ($result) {
            echo "<h3>Database tables</h3><ul>";

            while ($row = $result->fetch_row()) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>";
            }

            echo "</ul>";
        } else {
            echo "<p>Could not list tables: "
                . htmlspecialchars($conn->error) . "</p>";
        }
    }
} else {
    echo "<p style='color:red;'>The database connection variable "
        . "is missing or is not a MySQL connection.</p>";
}
?>
