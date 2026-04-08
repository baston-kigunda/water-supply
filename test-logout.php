<?php
// ========================================
// FILE: test-logout.php
// PURPOSE: Test if logout.php exists and works
// ========================================

echo "<h1>Logout Test</h1>";
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Looking for logout.php in: " . __DIR__ . "/logout.php</p>";

if (file_exists(__DIR__ . "/logout.php")) {
    echo "<p style='color:green'>✓ logout.php EXISTS in the correct location</p>";
    echo "<p><a href='logout.php'>Click here to test logout.php directly</a></p>";
} else {
    echo "<p style='color:red'>✗ logout.php NOT FOUND in: " . __DIR__ . "</p>";
    echo "<p>Please make sure logout.php is in: C:\\xampp\\htdocs\\water-supply-management-system\\logout.php</p>";
}
?>