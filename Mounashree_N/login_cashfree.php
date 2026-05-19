<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
require_once 'db.php';
$db = getDB();

if (isset($_GET['logout'])) { session_destroy(); header('Location: login.php'); exit; }
if (isset($_SESSION['admin_logged_in'])) { header('Location: index.php'); exit; }

$msg = ''; $msgT = ''; $showCred = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $msg='Security check failed.'; $msgT='error';
    } elseif ($_POST['action']==='login') {
        $u=trim($_POST['u']); $p=trim($_POST['p']);
        if (!$u||!$p) { $msg='Both fields required.'; $msgT='error'; }
        else {
            $st=$db->prepare("SELECT * FROM admin_users WHERE username=?");
            $st->bind_param("s",$u); $st->execute();
            $admin=$st->get_result()->fetch_assoc();
            if ($admin && password_verify($p,$admin['password'])) {
                $_SESSION['admin_logged_in']=true; $_SESSION['admin_username']=$admin['username']; $_SESSION['admin_id']=$admin['id'];
                header('Location: index.php'); exit;
            } else { $msg='Wrong username or password.'; $msgT='error'; }
        }
    } elseif ($_POST['action']==='chpwd') {
        $showCred=true;
        $u=trim($_POST['u']); $op=trim($_POST['op']); $np=trim($_POST['np']); $cp=trim($_POST['cp']);
        if (!$u||!$op||!$np||!$cp) { $msg='All fields required.'; $msgT='error'; }
        elseif ($np!==$cp) { $msg='Passwords do not match.'; $msgT='error'; }
        elseif (strlen($np)<6) { $msg='Min 6 characters.'; $msgT='error'; }
        else {
            $st=$db->prepare("SELECT * FROM admin_users WHERE username=?"); $st->bind_param("s",$u); $st->execute();
            $admin=$st->get_result()->fetch_assoc();
            if ($admin && password_verify($op,$admin['password'])) {
                $h=password_hash($np,PASSWORD_DEFAULT);
                $s2=$db->prepare("UPDATE admin_users SET password=? WHERE username=?"); $s2->bind_param("ss",$h,$u); $s2->execute();
                $msg='Password updated! Please login.'; $msgT='success'; $showCred=false;
            } else { $msg='Wrong credentials.'; $msgT='error'; }
        }
    } elseif ($_POST['action']==='chuser') {
        $showCred=true;
        $ou=trim($_POST['ou']); $nu=trim($_POST['nu']); $p=trim($_POST['p']);
        if (!$ou||!$nu||!$p) { $msg='All fields required.'; $msgT='error'; }
        elseif (strlen($nu)<3) { $msg='Username min 3 chars.'; $msgT='error'; }
        else {
            $st=$db->prepare("SELECT * FROM admin_users WHERE username=?"); $st->bind_param("s",$ou); $st->execute();
            $admin=$st->get_result()->fetch_assoc();
            if ($admin && password_verify($p,$admin['password'])) {
                $chk=$db->prepare("SELECT id FROM admin_users WHERE username=?"); $chk->bind_param("s",$nu); $chk->execute();
                if ($chk->get_result()->num_rows>0) { $msg='Username taken.'; $msgT='error'; }
                else {
                    $s2=$db->prepare("UPDATE admin_users SET username=? WHERE username=?"); $s2->bind_param("ss",$nu,$ou); $s2->execute();
                    $msg='Username updated! Please login.'; $msgT='success'; $showCred=false;
                }
            } else { $msg='Wrong credentials.'; $msgT='error'; }
        }
    }
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PayFlow Pro · Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --cream:  #faf7f2;
  --sand:   #f0e9dc;
  --warm:   #e8ddd0;
  --border: #d4c9b8;
  --muted:  #9e8f7e;
  --body:   #3d3328;
  --head:   #1a1510;
  --accent: #c9651a;
  --accent2:#e07b2a;
  --green:  #2d6a4f;
  --red:    #a83232;
  --gold:   #b8860b;
  --playfair:'Playfair Display',serif;
  --jakarta: 'Plus Jakarta Sans',sans-serif;
  --code:    'Fira Code',monospace;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{
  background:var(--cream);
  color:var(--body);
  font-family:var(--jakarta);
  min-height:100vh;
  display:flex;
  position:relative;
  overflow:hidden;
}

/* ── SPLIT LAYOUT ── */
.left-panel{
  width:42%;flex-shrink:0;
  background:var(--head);
  display:flex;flex-direction:column;
  justify-content:center;
  padding:60px 52px;
  position:relative;
  overflow:hidden;
}
.left-panel::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(ellipse at 20% 80%, rgba(201,101,26,0.25) 0%, transparent 60%),
             radial-gradient(ellipse at 80% 20%, rgba(184,134,11,0.15) 0%, transparent 50%);
}
.lp-pattern{
  position:absolute;inset:0;opacity:0.04;
  background-image:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);
  background-size:20px 20px;
}
.lp-content{position:relative;z-index:1;}
.brand-mark{
  display:inline-flex;align-items:center;gap:14px;margin-bottom:48px;
}
.brand-icon{
  width:52px;height:52px;
  border:2px solid var(--accent);
  border-radius:4px;
  display:flex;align-items:center;justify-content:center;
  font-size:24px;
  position:relative;
}
.brand-icon::after{
  content:'';position:absolute;
  inset:3px;border:1px solid rgba(201,101,26,0.3);border-radius:2px;
}
.brand-name-wrap{}
.brand-name{font-family:var(--playfair);font-size:22px;color:#fff;line-height:1;}
.brand-sub{font-size:11px;color:rgba(255,255,255,0.4);letter-spacing:3px;text-transform:uppercase;margin-top:3px;font-family:var(--code);}

.lp-headline{font-family:var(--playfair);font-size:42px;font-weight:900;color:#fff;line-height:1.1;margin-bottom:20px;}
.lp-headline em{font-style:italic;color:var(--accent2);}
.lp-desc{font-size:14px;color:rgba(255,255,255,0.5);line-height:1.8;max-width:320px;margin-bottom:40px;}

.feature-list{display:flex;flex-direction:column;gap:12px;}
.feat{display:flex;align-items:center;gap:12px;font-size:13px;color:rgba(255,255,255,0.6);}
.feat-dot{width:6px;height:6px;background:var(--accent);border-radius:50%;flex-shrink:0;}

.lp-footer{position:absolute;bottom:28px;left:52px;font-size:11px;color:rgba(255,255,255,0.2);font-family:var(--code);}

/* ── RIGHT PANEL ── */
.right-panel{
  flex:1;
  display:flex;align-items:center;justify-content:center;
  padding:40px;
  background:var(--cream);
  position:relative;
}
.right-panel::before{
  content:'';position:absolute;
  top:0;right:0;width:300px;height:300px;
  background:radial-gradient(circle, rgba(201,101,26,0.06) 0%, transparent 70%);
}

.form-box{width:100%;max-width:400px;position:relative;z-index:1;}

/* ── TABS ── */
.tab-row{display:flex;border-bottom:2px solid var(--border);margin-bottom:32px;}
.tab-item{
  padding:10px 20px;font-size:13px;font-weight:600;
  color:var(--muted);cursor:pointer;
  border-bottom:2px solid transparent;margin-bottom:-2px;
  transition:all 0.2s;letter-spacing:0.5px;text-transform:uppercase;
  font-family:var(--jakarta);border:none;background:none;
}
.tab-item:hover{color:var(--body);}
.tab-item.on{color:var(--accent);border-bottom-color:var(--accent);}

/* ── TITLE ── */
.form-title{font-family:var(--playfair);font-size:28px;font-weight:700;color:var(--head);margin-bottom:6px;}
.form-sub{font-size:13px;color:var(--muted);margin-bottom:28px;}

/* ── CREDS HINT ── */
.creds-hint{
  background:var(--sand);border-left:3px solid var(--gold);
  padding:12px 16px;border-radius:0 8px 8px 0;
  margin-bottom:24px;font-size:12px;color:var(--muted);line-height:2;
}
.creds-hint strong{color:var(--gold);font-family:var(--code);}

/* ── FIELD ── */
.field{margin-bottom:20px;}
.field-label{
  display:block;font-size:11px;font-weight:700;
  color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;
  margin-bottom:8px;
}
.field-wrap{position:relative;}
.field-wrap input{
  width:100%;
  background:#fff;
  border:1.5px solid var(--border);
  border-radius:6px;
  padding:12px 16px;
  font-family:var(--jakarta);font-size:14px;
  color:var(--head);outline:none;
  transition:all 0.2s;
}
.field-wrap input:focus{
  border-color:var(--accent);
  box-shadow:0 0 0 4px rgba(201,101,26,0.1);
}
.field-wrap input::placeholder{color:var(--border);}

/* ── SUBMIT ── */
.btn-signin{
  width:100%;padding:14px;
  background:var(--head);
  color:#fff;border:none;border-radius:6px;
  font-family:var(--playfair);font-size:16px;font-weight:700;
  cursor:pointer;transition:all 0.2s;margin-top:4px;
  position:relative;overflow:hidden;
  letter-spacing:0.5px;
}
.btn-signin::before{
  content:'';position:absolute;
  left:-100%;top:0;bottom:0;width:100%;
  background:linear-gradient(90deg,transparent,rgba(201,101,26,0.3),transparent);
  transition:left 0.5s;
}
.btn-signin:hover::before{left:100%;}
.btn-signin:hover{background:#2d2820;}

/* ── ALERT ── */
.alert-box{
  padding:12px 16px;border-radius:6px;
  font-size:13px;margin-bottom:20px;
  display:flex;align-items:center;gap:10px;
  animation:alertIn 0.3s ease;
}
@keyframes alertIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
.alert-box.success{background:#f0fdf4;border:1px solid #86efac;color:var(--green);}
.alert-box.error{background:#fef2f2;border:1px solid #fca5a5;color:var(--red);}

.divider{height:1px;background:var(--border);margin:24px 0;position:relative;}
.divider-label{
  position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);
  background:var(--cream);padding:0 12px;
  font-size:10px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;
  white-space:nowrap;
}

.section-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:2px;margin-bottom:16px;}

/* ── POWERED ── */
.powered{
  text-align:center;margin-top:28px;
  font-size:11px;color:var(--muted);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.cf-tag{
  background:var(--head);color:var(--accent2);
  font-size:10px;font-weight:600;padding:3px 10px;border-radius:3px;
  font-family:var(--code);letter-spacing:1px;
}

@media(max-width:768px){.left-panel{display:none;}.right-panel{padding:24px;}}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="lp-pattern"></div>
  <div class="lp-content">
    <div class="brand-mark">
      <div class="brand-icon">💸</div>
      <div class="brand-name-wrap">
        <div class="brand-name">PayFlow Pro</div>
        <div class="brand-sub">v3 · Cashfree</div>
      </div>
    </div>
    <div class="lp-headline">Bulk Salary<br><em>Simplified.</em></div>
    <div class="lp-desc">Pay your entire team in one click. Bank transfers, UPI payouts, export reports — everything in one powerful dashboard.</div>
    <div class="feature-list">
      <div class="feat"><div class="feat-dot"></div>Cashfree Bank Transfer &amp; UPI</div>
      <div class="feat"><div class="feat-dot"></div>Department-wise filtering</div>
      <div class="feat"><div class="feat-dot"></div>Excel &amp; PDF export reports</div>
      <div class="feat"><div class="feat-dot"></div>Retry failed transactions</div>
      <div class="feat"><div class="feat-dot"></div>95%+ success simulation mode</div>
    </div>
  </div>
  <div class="lp-footer">PayFlow Pro v3 · Cashfree Payouts Edition</div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="form-box">

    <?php if ($msg): ?>
    <div class="alert-box <?= $msgT ?>"><?= $msgT==='success'?'✅':'❌' ?> <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tab-row">
      <button class="tab-item <?= !$showCred?'on':'' ?>" onclick="showT('login')">Sign In</button>
      <button class="tab-item <?= $showCred?'on':'' ?>" onclick="showT('cred')">Change Credentials</button>
    </div>

    <!-- LOGIN -->
    <div id="pLogin" style="display:<?= !$showCred?'block':'none' ?>">
      <div class="form-title">Welcome back</div>
      <div class="form-sub">Sign in to access your dashboard</div>
      <div class="creds-hint">Default → Username: <strong>admin</strong> &nbsp;·&nbsp; Password: <strong>admin123</strong></div>
      <form method="POST">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="field"><label class="field-label">Username</label><div class="field-wrap"><input type="text" name="u" placeholder="Enter username" required autofocus></div></div>
        <div class="field"><label class="field-label">Password</label><div class="field-wrap"><input type="password" name="p" placeholder="Enter password" required></div></div>
        <button type="submit" class="btn-signin">Sign In to Dashboard →</button>
      </form>
    </div>

    <!-- CREDENTIALS -->
    <div id="pCred" style="display:<?= $showCred?'block':'none' ?>">
      <div class="section-title">🔑 Change Password</div>
      <form method="POST">
        <input type="hidden" name="action" value="chpwd">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="field"><label class="field-label">Username</label><div class="field-wrap"><input type="text" name="u" placeholder="Your username" required></div></div>
        <div class="field"><label class="field-label">Current Password</label><div class="field-wrap"><input type="password" name="op" placeholder="Current password" required></div></div>
        <div class="field"><label class="field-label">New Password</label><div class="field-wrap"><input type="password" name="np" placeholder="New password (min 6)" required></div></div>
        <div class="field"><label class="field-label">Confirm Password</label><div class="field-wrap"><input type="password" name="cp" placeholder="Confirm new password" required></div></div>
        <button type="submit" class="btn-signin">Update Password</button>
      </form>
      <div class="divider"><span class="divider-label">or change username</span></div>
      <div class="section-title">👤 Change Username</div>
      <form method="POST">
        <input type="hidden" name="action" value="chuser">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <div class="field"><label class="field-label">Current Username</label><div class="field-wrap"><input type="text" name="ou" placeholder="Current username" required></div></div>
        <div class="field"><label class="field-label">New Username</label><div class="field-wrap"><input type="text" name="nu" placeholder="New username (min 3)" required></div></div>
        <div class="field"><label class="field-label">Password (to verify)</label><div class="field-wrap"><input type="password" name="p" placeholder="Your password" required></div></div>
        <button type="submit" class="btn-signin">Update Username</button>
      </form>
    </div>

    <div class="powered">Bulk payouts via <span class="cf-tag">CASHFREE</span></div>

  </div>
</div>

<script>
function showT(t){
  document.getElementById('pLogin').style.display=t==='login'?'block':'none';
  document.getElementById('pCred').style.display=t==='cred'?'block':'none';
  document.querySelectorAll('.tab-item').forEach((b,i)=>{
    b.classList.toggle('on',(t==='login'&&i===0)||(t==='cred'&&i===1));
  });
}
</script>
</body>
</html>
