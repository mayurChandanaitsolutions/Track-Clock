<?php
include 'db.php';

if(isset($_POST['image']) && isset($_POST['latitude']) && isset($_POST['longitude'])) {

    $image     = $_POST['image'];
    $latitude  = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $accuracy  = isset($_POST['accuracy']) ? $_POST['accuracy'] : '';
    $address   = isset($_POST['address'])  ? $_POST['address']  : '';

    // Sanitize inputs
    $latitude  = $conn->real_escape_string($latitude);
    $longitude = $conn->real_escape_string($longitude);
    $accuracy  = $conn->real_escape_string($accuracy);
    $address   = $conn->real_escape_string($address);

    // Remove base64 header and decode
    $image = str_replace('data:image/png;base64,', '', $image);
    $image = str_replace(' ', '+', $image);

    $imageName = "uploads/login_" . time() . ".png";

    if(file_put_contents($imageName, base64_decode($image))) {

        $date       = date("Y-m-d");
        $login_time = date("H:i:s");

        // Check if login_address column exists, add it if not
        $checkCol = $conn->query("SHOW COLUMNS FROM attendance LIKE 'login_address'");
        if($checkCol->num_rows == 0) {
            $conn->query("ALTER TABLE attendance ADD COLUMN login_address TEXT AFTER login_longitude");
        }
        $checkAcc = $conn->query("SHOW COLUMNS FROM attendance LIKE 'login_accuracy'");
        if($checkAcc->num_rows == 0) {
            $conn->query("ALTER TABLE attendance ADD COLUMN login_accuracy VARCHAR(20) AFTER login_address");
        }

        $sql = "INSERT INTO attendance 
        (user_id, login_photo, login_latitude, login_longitude, login_address, login_accuracy, login_time, date)
        VALUES 
        (1, '$imageName', '$latitude', '$longitude', '$address', '$accuracy', '$login_time', '$date')";

        if($conn->query($sql)) {
            echo "Login Saved Successfully";
        } else {
            echo "Database Error: " . $conn->error;
        }

    } else {
        echo "Image Save Failed";
    }

} else {
    echo "POST data missing";
}
?>
