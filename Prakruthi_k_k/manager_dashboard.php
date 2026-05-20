<?php
include 'db.php';
$result = $conn->query("SELECT * FROM attendance ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
<title>Manager Dashboard - TrackClock</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; background: #f0f4f8; padding: 20px; }
  h2 { color: #2c3e50; text-align: center; padding: 20px 0; font-size: 24px; }

  .card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    overflow: hidden;
  }
  .card-header {
    background: #2c3e50;
    color: white;
    padding: 10px 15px;
    font-size: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .card-header span.date { font-size: 13px; color: #aed6f1; }

  .photos-row {
    display: flex;
    gap: 0;
    flex-wrap: wrap;
  }
  .photo-block {
    flex: 1;
    min-width: 260px;
    padding: 15px;
    border-right: 1px solid #eee;
  }
  .photo-block:last-child { border-right: none; }

  .photo-block h4 {
    font-size: 13px;
    color: #7f8c8d;
    text-transform: uppercase;
    margin-bottom: 10px;
    letter-spacing: 1px;
  }
  .photo-block img {
    width: 100%;
    max-width: 280px;
    border-radius: 6px;
    border: 2px solid #ddd;
    display: block;
  }
  .photo-block .no-photo {
    width: 100%;
    max-width: 280px;
    height: 180px;
    background: #ecf0f1;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #bdc3c7;
    font-size: 13px;
  }

  .info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
  }
  .info-pill {
    background: #eaf4fb;
    border-radius: 20px;
    padding: 4px 10px;
    font-size: 12px;
    color: #2980b9;
  }
  .info-pill.address {
    background: #e9f7ef;
    color: #27ae60;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12px;
    width: 100%;
  }
  .info-pill.accuracy {
    background: #fef9e7;
    color: #d68910;
  }

  .working-hours {
    padding: 10px 15px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    font-size: 13px;
    color: #2c3e50;
  }
  .working-hours span {
    font-weight: bold;
    color: #27ae60;
  }

  .map-link {
    font-size: 11px;
    color: #3498db;
    text-decoration: none;
    display: inline-block;
    margin-top: 4px;
  }
  .map-link:hover { text-decoration: underline; }

  .badge-login  { background: #27ae60; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
  .badge-logout { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
</style>
</head>
<body>

<h2>📋 Attendance Dashboard</h2>

<?php
$total = 0;
$rows = [];
while($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total++;
}
echo "<p style='text-align:center;color:#7f8c8d;margin-bottom:20px;font-size:13px;'>Total Records: <b>$total</b></p>";

foreach($rows as $row):
?>

<div class="card">

  <div class="card-header">
    <div>👤 Employee ID: <?php echo htmlspecialchars($row['user_id']); ?></div>
    <span class="date">📅 <?php echo htmlspecialchars($row['date']); ?></span>
  </div>

  <div class="photos-row">

    <!-- LOGIN -->
    <div class="photo-block">
      <h4><span class="badge-login">LOGIN</span></h4>

      <?php if(!empty($row['login_photo'])): ?>
        <img src="<?php echo htmlspecialchars($row['login_photo']); ?>" alt="Login Photo">
      <?php else: ?>
        <div class="no-photo">No Photo</div>
      <?php endif; ?>

      <div class="info-row">
        <?php if(!empty($row['login_time'])): ?>
          <span class="info-pill">🕐 <?php echo htmlspecialchars($row['login_time']); ?></span>
        <?php endif; ?>

        <?php if(!empty($row['login_latitude']) && !empty($row['login_longitude'])): ?>
          <span class="info-pill">📍 <?php echo htmlspecialchars($row['login_latitude']); ?>, <?php echo htmlspecialchars($row['login_longitude']); ?></span>
          <a class="map-link" href="https://www.google.com/maps?q=<?php echo $row['login_latitude']; ?>,<?php echo $row['login_longitude']; ?>" target="_blank">🗺️ View on Map</a>
        <?php endif; ?>

        <?php if(!empty($row['login_accuracy'])): ?>
          <span class="info-pill accuracy">±<?php echo htmlspecialchars($row['login_accuracy']); ?>m accuracy</span>
        <?php endif; ?>

        <?php if(!empty($row['login_address'])): ?>
          <span class="info-pill address">📌 <?php echo htmlspecialchars($row['login_address']); ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- LOGOUT -->
    <div class="photo-block">
      <h4><span class="badge-logout">LOGOUT</span></h4>

      <?php if(!empty($row['logout_photo'])): ?>
        <img src="<?php echo htmlspecialchars($row['logout_photo']); ?>" alt="Logout Photo">
      <?php else: ?>
        <div class="no-photo">Not Logged Out Yet</div>
      <?php endif; ?>

      <div class="info-row">
        <?php if(!empty($row['logout_time'])): ?>
          <span class="info-pill">🕐 <?php echo htmlspecialchars($row['logout_time']); ?></span>
        <?php endif; ?>

        <?php if(!empty($row['logout_latitude']) && !empty($row['logout_longitude'])): ?>
          <span class="info-pill">📍 <?php echo htmlspecialchars($row['logout_latitude']); ?>, <?php echo htmlspecialchars($row['logout_longitude']); ?></span>
          <a class="map-link" href="https://www.google.com/maps?q=<?php echo $row['logout_latitude']; ?>,<?php echo $row['logout_longitude']; ?>" target="_blank">🗺️ View on Map</a>
        <?php endif; ?>

        <?php if(!empty($row['logout_accuracy'])): ?>
          <span class="info-pill accuracy">±<?php echo htmlspecialchars($row['logout_accuracy']); ?>m accuracy</span>
        <?php endif; ?>

        <?php if(!empty($row['logout_address'])): ?>
          <span class="info-pill address">📌 <?php echo htmlspecialchars($row['logout_address']); ?></span>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <div class="working-hours">
    ⏱️ Working Hours: <span><?php echo !empty($row['working_hours']) ? htmlspecialchars($row['working_hours']) : '—'; ?></span>
  </div>

</div>

<?php endforeach; ?>

</body>
</html>
