<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'db.php';
$db = getDB();

// -----------------------------------------------
// LOGOUT
// -----------------------------------------------
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// -----------------------------------------------
// Already logged in → go to dashboard
// -----------------------------------------------
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$message = '';
$msgType = '';
$showChangePassword = false;

// -----------------------------------------------
// HANDLE LOGIN
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '❌ Invalid request! Please try again.';
        $msgType = 'error';
    }

    // LOGIN
    elseif ($_POST['action'] === 'login') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($password)) {
            $message = '❌ Please enter username and password!';
            $msgType = 'error';
        } else {
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin  = $result->fetch_assoc();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['admin_id']        = $admin['id'];
                header('Location: index.php');
                exit;
            } else {
                $message = '❌ Invalid username or password!';
                $msgType = 'error';
            }
        }
    }

    // CHANGE PASSWORD (no username field — works on the single admin account)
    elseif ($_POST['action'] === 'change_password') {
        $showChangePassword = true;
        $oldPassword = trim($_POST['old_password']);
        $newPassword = trim($_POST['new_password']);
        $confirmPass = trim($_POST['confirm_password']);

        // Strong password: min 8 chars, uppercase, lowercase, digit, special char
        $strongPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPass)) {
            $message = '❌ All fields are required!';
            $msgType = 'error';
        } elseif ($newPassword !== $confirmPass) {
            $message = '❌ New passwords do not match!';
            $msgType = 'error';
        } elseif (!preg_match($strongPattern, $newPassword)) {
            $message = '❌ Password must be at least 8 characters and include uppercase, lowercase, number, and special character!';
            $msgType = 'error';
        } else {
            $result = $db->query("SELECT * FROM admin_users LIMIT 1");
            $admin  = $result->fetch_assoc();

            if ($admin && password_verify($oldPassword, $admin['password'])) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt2   = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                $stmt2->bind_param("si", $newHash, $admin['id']);
                $stmt2->execute();
                $message = '✅ Password changed successfully! Please login with new password.';
                $msgType = 'success';
                $showChangePassword = false;
            } else {
                $message = '❌ Invalid current password!';
                $msgType = 'error';
            }
        }
    }

    // CHANGE USERNAME (no password verification required)
    elseif ($_POST['action'] === 'change_username') {
        $showChangePassword = true;
        $oldUsername = trim($_POST['old_username']);
        $newUsername = trim($_POST['new_username']);

        if (empty($oldUsername) || empty($newUsername)) {
            $message = '❌ All fields are required!';
            $msgType = 'error';
        } elseif (strlen($newUsername) < 3) {
            $message = '❌ Username must be at least 3 characters!';
            $msgType = 'error';
        } else {
            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
            $stmt->bind_param("s", $oldUsername);
            $stmt->execute();
            $result = $stmt->get_result();
            $admin  = $result->fetch_assoc();

            if ($admin) {
                $checkStmt = $db->prepare("SELECT id FROM admin_users WHERE username = ?");
                $checkStmt->bind_param("s", $newUsername);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    $message = '❌ Username already taken! Choose a different username.';
                    $msgType = 'error';
                } else {
                    $stmt2 = $db->prepare("UPDATE admin_users SET username = ? WHERE username = ?");
                    $stmt2->bind_param("ss", $newUsername, $oldUsername);
                    $stmt2->execute();
                    $message = '✅ Username changed to "' . htmlspecialchars($newUsername) . '"! Please login.';
                    $msgType = 'success';
                    $showChangePassword = false;
                }
            } else {
                $message = '❌ Current username not found!';
                $msgType = 'error';
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PayFlow — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
  --bg:     #0a0e1a;
  --card:   #131929;
  --border: #1e2d47;
  --blue:   #3b82f6;
  --cyan:   #06b6d4;
  --green:  #10b981;
  --green2: #34d399;
  --red:    #ef4444;
  --text:   #e2e8f0;
  --text2:  #94a3b8;
  --sans:   'Outfit', sans-serif;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(59,130,246,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59,130,246,0.03) 1px, transparent 1px);
  background-size: 40px 40px;
  pointer-events: none;
}

.wrap {
  width: 100%;
  max-width: 440px;
  padding: 24px;
  position: relative;
  z-index: 1;
}

/* LOGO */
.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: center;
  margin-bottom: 32px;
}
.logo-mark {
  width: 44px; height: 44px;
  background: linear-gradient(135deg, var(--blue), var(--cyan));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
  box-shadow: 0 0 30px rgba(59,130,246,0.4);
}
.logo-text { font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
.logo-text span { color: var(--cyan); }

/* CARD */
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 32px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}
.card-title {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 6px;
  text-align: center;
}
.card-sub {
  font-size: 13px;
  color: var(--text2);
  text-align: center;
  margin-bottom: 28px;
}

/* TABS */
.tab-row {
  display: flex;
  gap: 4px;
  background: rgba(255,255,255,0.04);
  border-radius: 10px;
  padding: 4px;
  margin-bottom: 24px;
}
.tab-btn {
  flex: 1;
  padding: 9px;
  border-radius: 7px;
  border: none;
  background: transparent;
  color: var(--text2);
  font-family: var(--sans);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.tab-btn.active {
  background: linear-gradient(135deg, var(--blue), var(--cyan));
  color: white;
  box-shadow: 0 4px 14px rgba(59,130,246,0.3);
}

/* FORM */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}
.form-group label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text2);
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.form-group input {
  background: rgba(255,255,255,0.05);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 12px 16px;
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}
.form-group input:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.btn-submit {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, var(--blue), var(--cyan));
  color: white;
  border: none;
  border-radius: 12px;
  font-family: var(--sans);
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 8px;
  box-shadow: 0 4px 20px rgba(59,130,246,0.3);
}
.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(59,130,246,0.45);
}

/* ALERT */
.alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 20px;
}
.alert.success {
  background: rgba(16,185,129,0.1);
  border: 1px solid rgba(16,185,129,0.25);
  color: var(--green2);
}
.alert.error {
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.25);
  color: #f87171;
}

/* DEFAULT CREDS */
.default-creds {
  background: rgba(59,130,246,0.06);
  border: 1px solid rgba(59,130,246,0.15);
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 20px;
  font-size: 12px;
  color: var(--text2);
  text-align: center;
  line-height: 1.8;
}
.default-creds strong { color: var(--blue); font-family: 'JetBrains Mono', monospace; }

.divider {
  height: 1px;
  background: var(--border);
  margin: 20px 0;
}

/* EYE ICON */
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.input-wrap input {
  width: 100%;
  padding-right: 42px !important;
}
.eye-btn {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text2);
  padding: 0;
  display: flex;
  align-items: center;
  transition: color 0.2s;
  line-height: 1;
}
.eye-btn:hover { color: var(--blue); }
.eye-btn svg { width: 18px; height: 18px; }

/* PASSWORD HINT */
.pw-hint {
  font-size: 11px;
  color: var(--text2);
  margin-top: 4px;
  line-height: 1.5;
}
</style>
</head>
<body>
<div class="wrap">

  <!-- LOGO -->
  <div class="logo">
    <div class="logo-mark">💸</div>
    <div class="logo-text">Pay<span>Flow</span></div>
  </div>

  <!-- CARD -->
  <div class="card">

    <?php if ($message): ?>
    <div class="alert <?= $msgType ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tab-row">
      <button class="tab-btn <?= !$showChangePassword ? 'active' : '' ?>" onclick="showTab('login')">🔐 Login</button>
      <button class="tab-btn <?= $showChangePassword ? 'active' : '' ?>" onclick="showTab('change')">⚙️ Change Credentials</button>
    </div>

    <!-- LOGIN FORM -->
    <div id="loginForm" style="display:<?= !$showChangePassword ? 'block' : 'none' ?>">
      <div class="card-title">Welcome Back!</div>
      <div class="card-sub">Login to access PayFlow dashboard</div>

      <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" placeholder="Enter username" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <input type="password" name="password" id="login_password" placeholder="Enter password" required>
            <button type="button" class="eye-btn" onclick="toggleEye('login_password',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
        </div>
        <button type="submit" class="btn-submit">🔐 Login to PayFlow</button>
      </form>
    </div>

    <!-- CHANGE CREDENTIALS FORM -->
    <div id="changeForm" style="display:<?= $showChangePassword ? 'block' : 'none' ?>">
      <div class="card-title">Change Credentials</div>
      <div class="card-sub">Update your username or password</div>

      <!-- CHANGE PASSWORD -->
      <div style="font-size:13px;font-weight:700;color:var(--text2);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;">🔑 Change Password</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
          <label>Current Password</label>
          <div class="input-wrap">
            <input type="password" name="old_password" id="old_password" placeholder="Current password" required>
            <button type="button" class="eye-btn" onclick="toggleEye('old_password',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
        </div>
        <div class="form-group">
          <label>New Password</label>
          <div class="input-wrap">
            <input type="password" name="new_password" id="new_password" placeholder="New password" required>
            <button type="button" class="eye-btn" onclick="toggleEye('new_password',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
          <div class="pw-hint">Must be 8+ chars with uppercase, lowercase, number &amp; special character</div>
        </div>
        <div class="form-group">
          <label>Confirm New Password</label>
          <div class="input-wrap">
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat new password" required>
            <button type="button" class="eye-btn" onclick="toggleEye('confirm_password',this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
          </div>
        </div>
        <button type="submit" class="btn-submit">🔑 Change Password</button>
      </form>

      <div class="divider"></div>

      <!-- CHANGE USERNAME -->
      <div style="font-size:13px;font-weight:700;color:var(--text2);letter-spacing:1px;text-transform:uppercase;margin-bottom:14px;">👤 Change Username</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_username">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <div class="form-group">
          <label>Current Username</label>
          <input type="text" name="old_username" placeholder="Current username" required>
        </div>
        <div class="form-group">
          <label>New Username</label>
          <input type="text" name="new_username" placeholder="New username (min 3 chars)" required>
        </div>
        <button type="submit" class="btn-submit">👤 Change Username</button>
      </form>
    </div>

  </div>
</div>

<script>
function showTab(tab) {
  document.getElementById('loginForm').style.display  = tab === 'login'  ? 'block' : 'none';
  document.getElementById('changeForm').style.display = tab === 'change' ? 'block' : 'none';
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', (tab === 'login' && i === 0) || (tab === 'change' && i === 1));
  });
}

const eyeOpen  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
const eyeClosed = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

function toggleEye(id, btn) {
  const input = document.getElementById(id);
  const isPass = input.type === 'password';
  input.type = isPass ? 'text' : 'password';
  btn.innerHTML = isPass ? eyeClosed : eyeOpen;
}
</script>
</body>
</html>