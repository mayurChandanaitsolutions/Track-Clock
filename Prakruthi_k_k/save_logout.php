<?php
include 'db.php';

if(!isset($_POST['image']) || !isset($_POST['latitude']) || !isset($_POST['longitude'])) {
    echo "POST data missing";
    exit;
}

$image     = $_POST['image'];
$latitude  = $conn->real_escape_string($_POST['latitude']);
$longitude = $conn->real_escape_string($_POST['longitude']);
$accuracy  = isset($_POST['accuracy']) ? $conn->real_escape_string($_POST['accuracy']) : '';
$address   = isset($_POST['address'])  ? $conn->real_escape_string($_POST['address'])  : '';

$image = str_replace('data:image/png;base64,', '', $image);
$image = str_replace(' ', '+', $image);

$imageName = "uploads/logout_" . time() . ".png";
file_put_contents($imageName, base64_decode($image));

$logout_time = date("H:i:s");

// Check if logout_address column exists, add if not
$checkCol = $conn->query("SHOW COLUMNS FROM attendance LIKE 'logout_address'");
if($checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE attendance ADD COLUMN logout_address TEXT AFTER logout_longitude");
}
$checkAcc = $conn->query("SHOW COLUMNS FROM attendance LIKE 'logout_accuracy'");
if($checkAcc->num_rows == 0) {
    $conn->query("ALTER TABLE attendance ADD COLUMN logout_accuracy VARCHAR(20) AFTER logout_address");
}

$result = $conn->query("SELECT * FROM attendance WHERE logout_time IS NULL ORDER BY id DESC LIMIT 1");
if($result->num_rows == 0) {
    $result = $conn->query("SELECT * FROM attendance ORDER BY id DESC LIMIT 1");
}
$row = $result->fetch_assoc();

$login_time = $row['login_time'];

$start    = new DateTime($login_time);
$end      = new DateTime($logout_time);
$interval = $start->diff($end);

$hours        = $interval->h;
$minutes      = $interval->i;
$working_hours = $hours . " Hours " . $minutes . " Minutes";

$conn->query("UPDATE attendance SET
    logout_photo='$imageName',
    logout_latitude='$latitude',
    logout_longitude='$longitude',
    logout_address='$address',
    logout_accuracy='$accuracy',
    logout_time='$logout_time',
    working_hours='$working_hours'
    WHERE id=" . $row['id']);

echo "Logout Saved";
?>
