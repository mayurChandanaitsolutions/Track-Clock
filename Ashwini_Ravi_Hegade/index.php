<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
$db = getDB();

// ============================================================
//  CONFIGURATION
//  Simulation = true  → demo mode (no real API calls)
//  Simulation = false → real mode (paste RazorpayX keys below)
// ============================================================
$SIMULATION_MODE = true;

// RazorpayX credentials (fill when ready for live)
define('RAZORPAYX_KEY_ID',     'rzp_test_XXXXXXXXXXXXXXX');
define('RAZORPAYX_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');
define('RAZORPAYX_ACCOUNT_NO', 'XXXXXXXXXXXXXXXXXX');

// ============================================================
//  HANDLE ALL ACTIONS
// ============================================================
$action  = $_POST['action']  ?? $_GET['action']  ?? '';
$tab     = $_GET['tab'] ?? 'employees';
$message = '';
$msgType = '';

// -----------------------------------------------
// ADD EMPLOYEE
// -----------------------------------------------
if ($action === 'add_employee') {
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $mobile  = trim($_POST['mobile']);
    $dept    = trim($_POST['department']);
    $bank    = trim($_POST['bank_name']);
    $acc     = trim($_POST['account_number']);
    $ifsc    = strtoupper(trim($_POST['ifsc']));
    $salary  = floatval($_POST['salary']);

    $stmt = $db->prepare("INSERT INTO employees (name, email, mobile, department, bank_name, account_number, ifsc, salary) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssssd", $name, $email, $mobile, $dept, $bank, $acc, $ifsc, $salary);
    if ($stmt->execute()) {
        $message = "✅ Employee '$name' added successfully!";
        $msgType = 'success';
    } else {
        $message = "❌ Failed to add employee!";
        $msgType = 'error';
    }
    $tab = 'employees';
}

// -----------------------------------------------
// DELETE EMPLOYEE
// -----------------------------------------------
if ($action === 'delete_employee') {
    $id = intval($_POST['emp_id']);
    $db->query("UPDATE employees SET status='inactive' WHERE id=$id");
    $message = "✅ Employee removed successfully!";
    $msgType = 'success';
    $tab = 'employees';
}

// -----------------------------------------------
// EDIT EMPLOYEE
// -----------------------------------------------
if ($action === 'edit_employee') {
    $id     = intval($_POST['emp_id']);
    $name   = trim($_POST['name']);
    $email  = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $dept   = trim($_POST['department']);
    $bank   = trim($_POST['bank_name']);
    $acc    = trim($_POST['account_number']);
    $ifsc   = strtoupper(trim($_POST['ifsc']));
    $salary = floatval($_POST['salary']);

    $stmt = $db->prepare("UPDATE employees SET name=?,email=?,mobile=?,department=?,bank_name=?,account_number=?,ifsc=?,salary=? WHERE id=?");
    $stmt->bind_param("sssssssdi", $name, $email, $mobile, $dept, $bank, $acc, $ifsc, $salary, $id);
    if ($stmt->execute()) {
        $message = "✅ Employee '$name' updated successfully!";
        $msgType = 'success';
    }
    $tab = 'employees';
}

// -----------------------------------------------
// TRIGGER BULK PAYOUT
// -----------------------------------------------
if ($action === 'trigger_payout') {
    $selectedIds = array_map('intval', $_POST['employee_ids'] ?? []);
    $tab = 'payout';

    if (empty($selectedIds)) {
        $message = "❌ Please select at least one employee!";
        $msgType = 'error';
    } else {
        // Fetch selected employees
        $ids     = implode(',', $selectedIds);
        $empRes  = $db->query("SELECT * FROM employees WHERE id IN ($ids) AND status='active'");
        $selEmps = $empRes->fetch_all(MYSQLI_ASSOC);

        $totalAmt = array_sum(array_column($selEmps, 'salary'));
        $month    = date('F Y');

        // Create payout session
        $stmt = $db->prepare("INSERT INTO payouts (payout_month, total_amount, total_employees) VALUES (?,?,?)");
        $empCount = count($selEmps);
        $stmt->bind_param("sdi", $month, $totalAmt, $empCount);
        $stmt->execute();
        $payoutSessionId = $db->insert_id;

        $successCount = 0;
        $failCount    = 0;
        $payoutResults = [];

        foreach ($selEmps as $emp) {
            if ($SIMULATION_MODE) {
                $result = simulatePayout($emp);
            } else {
                $result = realPayout($emp);
            }

            // Save to payout_items
            $status    = $result['status'];
            $contactId = $result['contact_id']      ?? null;
            $fundAccId = $result['fund_account_id'] ?? null;
            $payoutRef = $result['payout_id']       ?? null;
            $utr       = $result['utr']             ?? null;
            $failMsg   = $result['message'] ?? null;
            $empId     = $emp['id'];
            $empName   = $emp['name'];
            $empSalary = $emp['salary'];

            $stmt2 = $db->prepare("INSERT INTO payout_items (payout_id, employee_id, employee_name, salary, status, contact_id, fund_account_id, payout_ref_id, utr_number, failure_reason) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt2->bind_param("iisdssssss", $payoutSessionId, $empId, $empName, $empSalary, $status, $contactId, $fundAccId, $payoutRef, $utr, $failMsg);
            $stmt2->execute();

            if ($status === 'success') $successCount++;
            else $failCount++;

            $result['employee'] = $emp['name'];
            $result['salary']   = $emp['salary'];
            $result['dept']     = $emp['department'];
            $result['bank']     = $emp['bank_name'];
            $payoutResults[]    = $result;
        }

        // Update payout session
        $totalPaid = array_sum(array_map(fn($r) => $r['status']==='success' ? $r['salary'] : 0, $payoutResults));
        $db->query("UPDATE payouts SET success_count=$successCount, fail_count=$failCount, total_amount=$totalPaid, status='completed' WHERE id=$payoutSessionId");

        $message = "✅ Bulk payout completed! $successCount successful, $failCount failed.";
        $msgType = 'success';
    }
}


// -----------------------------------------------
// RETRY FAILED PAYOUTS
// -----------------------------------------------
if ($action === 'retry_failed') {
    $payoutId = intval($_POST['payout_id'] ?? 0);
    $tab = 'history';

    if ($payoutId > 0) {
        // Get failed employees from this payout session
        $failedRes = $db->query("SELECT pi.*, e.account_number, e.ifsc, e.email, e.mobile, e.department, e.bank_name 
                                  FROM payout_items pi 
                                  JOIN employees e ON pi.employee_id = e.id 
                                  WHERE pi.payout_id = $payoutId AND pi.status = 'failed'");
        $failedEmps = $failedRes->fetch_all(MYSQLI_ASSOC);

        if (empty($failedEmps)) {
            $message = "✅ No failed transactions found for this payout!";
            $msgType = 'success';
        } else {
            $retryCount   = 0;
            $retrySuccess = 0;
            $retryFail    = 0;
            $payoutResults = [];

            foreach ($failedEmps as $item) {
                // Build employee array for payout function
                $emp = [
                    'id'             => $item['employee_id'],
                    'name'           => $item['employee_name'],
                    'salary'         => $item['salary'],
                    'email'          => $item['email'],
                    'mobile'         => $item['mobile'],
                    'department'     => $item['department'],
                    'bank_name'      => $item['bank_name'],
                    'account_number' => $item['account_number'],
                    'ifsc'           => $item['ifsc'],
                ];

                if ($SIMULATION_MODE) {
                    $result = simulatePayout($emp);
                } else {
                    $result = realPayout($emp);
                }

                // Update payout_item record
                $status    = $result['status'];
                $contactId = $result['contact_id']      ?? null;
                $fundAccId = $result['fund_account_id'] ?? null;
                $payoutRef = $result['payout_id']       ?? null;
                $utr       = $result['utr']             ?? null;
                $failMsg   = $result['message']         ?? null;
                $itemId    = $item['id'];

                $stmt = $db->prepare("UPDATE payout_items SET status=?, contact_id=?, fund_account_id=?, payout_ref_id=?, utr_number=?, failure_reason=? WHERE id=?");
                $stmt->bind_param("ssssssi", $status, $contactId, $fundAccId, $payoutRef, $utr, $failMsg, $itemId);
                $stmt->execute();

                if ($status === 'success') $retrySuccess++;
                else $retryFail++;

                $result['employee'] = $item['employee_name'];
                $result['salary']   = $item['salary'];
                $result['dept']     = $item['department'];
                $result['bank']     = $item['bank_name'];
                $payoutResults[]    = $result;
            }

            // Update payout session counts
            $allItems = $db->query("SELECT status FROM payout_items WHERE payout_id = $payoutId");
            $allSuccess = 0; $allFail = 0; $allAmt = 0;
            while ($row = $allItems->fetch_assoc()) {
                if ($row['status'] === 'success') $allSuccess++;
                else $allFail++;
            }
            $amtRes = $db->query("SELECT SUM(salary) as total FROM payout_items WHERE payout_id = $payoutId AND status = 'success'");
            $allAmt = $amtRes->fetch_assoc()['total'] ?? 0;
            $db->query("UPDATE payouts SET success_count=$allSuccess, fail_count=$allFail, total_amount=$allAmt WHERE id=$payoutId");

            $message = "🔄 Retry complete! $retrySuccess successful, $retryFail still failed.";
            $msgType = $retryFail === 0 ? 'success' : 'warning';
        }
    }
}

// -----------------------------------------------
// SIMULATE PAYOUT (mimics real RazorpayX response)
// -----------------------------------------------
function simulatePayout($employee) {
    $contactId     = 'cont_SIM' . strtoupper(substr(md5($employee['id'] . microtime()), 0, 14));
    $fundAccountId = 'fa_SIM'   . strtoupper(substr(md5($employee['account_number'] . microtime()), 0, 14));
    $payoutId      = 'pout_SIM' . strtoupper(substr(md5($employee['email'] . microtime()), 0, 14));
    $utr           = 'UTR'      . rand(100000000000, 999999999999);

    $isSuccess = (rand(1, 100) <= 95); // 95% success rate

    if ($isSuccess) {
        return [
            'status'          => 'success',
            'contact_id'      => $contactId,
            'fund_account_id' => $fundAccountId,
            'payout_id'       => $payoutId,
            'reference_id'    => 'SAL_' . date('Ymd') . '_' . rand(1000, 9999),
            'utr'             => $utr,
            'payout_status'   => 'PROCESSED',
            'mode'            => 'NEFT',
            'message'         => 'Salary transferred successfully!',
            'timestamp'       => date('d M Y, h:i A'),
        ];
    } else {
        $errors = ['Insufficient balance', 'Bank validation failed', 'IFSC mismatch', 'Daily limit exceeded'];
        return [
            'status'  => 'failed',
            'message' => $errors[array_rand($errors)],
            'step'    => 'Payout Processing',
        ];
    }
}

// -----------------------------------------------
// REAL PAYOUT via RazorpayX API (used when live)
// -----------------------------------------------
function realPayout($employee) {
    // Step 1 - Create Contact
    $contactData = ['name'=>$employee['name'],'email'=>$employee['email'],'contact'=>$employee['mobile'],'type'=>'employee'];
    $contactRes  = callRazorpayX('POST', 'https://api.razorpay.com/v1/contacts', $contactData);
    if ($contactRes['status'] !== 201) {
        return ['status'=>'failed','message'=>$contactRes['response']['error']['description'] ?? 'Contact failed','step'=>'Create Contact'];
    }
    $contactId = $contactRes['response']['id'];

    // Step 2 - Create Fund Account
    $fundData = ['contact_id'=>$contactId,'account_type'=>'bank_account','bank_account'=>['name'=>$employee['name'],'ifsc'=>$employee['ifsc'],'account_number'=>$employee['account_number']]];
    $fundRes  = callRazorpayX('POST', 'https://api.razorpay.com/v1/fund_accounts', $fundData);
    if ($fundRes['status'] !== 201) {
        return ['status'=>'failed','message'=>$fundRes['response']['error']['description'] ?? 'Fund account failed','step'=>'Fund Account'];
    }
    $fundAccountId = $fundRes['response']['id'];

    // Step 3 - Create Payout
    $payoutData = ['account_number'=>RAZORPAYX_ACCOUNT_NO,'fund_account_id'=>$fundAccountId,'amount'=>$employee['salary']*100,'currency'=>'INR','mode'=>'NEFT','purpose'=>'salary','queue_if_low_balance'=>true];
    $payoutRes  = callRazorpayX('POST', 'https://api.razorpay.com/v1/payouts', $payoutData);
    if (in_array($payoutRes['status'], [200,201])) {
        return ['status'=>'success','contact_id'=>$contactId,'fund_account_id'=>$fundAccountId,'payout_id'=>$payoutRes['response']['id'],'utr'=>$payoutRes['response']['utr'] ?? 'Pending','payout_status'=>$payoutRes['response']['status'] ?? 'queued','mode'=>'NEFT','message'=>'Salary transferred!','timestamp'=>date('d M Y, h:i A')];
    }
    return ['status'=>'failed','message'=>$payoutRes['response']['error']['description'] ?? 'Payout failed','step'=>'Payout'];
}

function callRazorpayX($method, $url, $data=[]) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAYX_KEY_ID.':'.RAZORPAYX_KEY_SECRET);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    if ($method==='POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status'=>$httpCode,'response'=>json_decode($response,true)];
}

// -----------------------------------------------
// Fetch data for display
// -----------------------------------------------
$employees    = $db->query("SELECT * FROM employees WHERE status='active' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$totalSalary  = array_sum(array_column($employees, 'salary'));
$payoutHistory= $db->query("SELECT * FROM payouts ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
$editEmp      = null;
if (isset($_GET['edit'])) {
    $editId  = intval($_GET['edit']);
    $editRes = $db->query("SELECT * FROM employees WHERE id=$editId");
    $editEmp = $editRes->fetch_assoc();
    $tab     = 'employees';
}

$departments = ['Engineering','Design','Marketing','Finance','HR','Support','Operations','Sales'];
$banks       = ['HDFC Bank','ICICI Bank','SBI','Axis Bank','Kotak Bank','PNB','Bank of Baroda','Canara Bank','Yes Bank','IndusInd Bank'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PayFlow — Bulk Salary System</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:      #0a0e1a;
  --surface: #0f1524;
  --card:    #131929;
  --card2:   #161e30;
  --border:  #1e2d47;
  --blue:    #3b82f6;
  --blue2:   #60a5fa;
  --cyan:    #06b6d4;
  --green:   #10b981;
  --green2:  #34d399;
  --red:     #ef4444;
  --yellow:  #f59e0b;
  --text:    #e2e8f0;
  --muted:   #64748b;
  --text2:   #94a3b8;
  --mono:    'JetBrains Mono', monospace;
  --sans:    'Outfit', sans-serif;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { background:var(--bg); color:var(--text); font-family:var(--sans); min-height:100vh; padding-bottom:60px; }
body::before { content:''; position:fixed; inset:0; background-image:linear-gradient(rgba(59,130,246,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(59,130,246,0.03) 1px,transparent 1px); background-size:40px 40px; pointer-events:none; z-index:0; }

/* HEADER */
header { position:sticky; top:0; z-index:100; background:rgba(10,14,26,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border); padding:0 40px; height:64px; display:flex; align-items:center; justify-content:space-between; }
.logo { display:flex; align-items:center; gap:12px; }
.logo-mark { width:36px; height:36px; background:linear-gradient(135deg,var(--blue),var(--cyan)); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px; box-shadow:0 0 20px rgba(59,130,246,0.3); }
.logo-text { font-size:18px; font-weight:800; letter-spacing:-0.5px; }
.logo-text span { color:var(--cyan); }
.sim-badge { display:flex; align-items:center; gap:8px; background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); color:var(--yellow); font-family:var(--mono); font-size:11px; padding:6px 14px; border-radius:20px; }
.sim-dot { width:6px; height:6px; background:var(--yellow); border-radius:50%; animation:blink 1.5s infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

/* STATS */
.stats { max-width:1280px; margin:28px auto 0; padding:0 32px; display:grid; grid-template-columns:repeat(4,1fr); gap:16px; position:relative; z-index:1; }
.sc { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:22px; position:relative; overflow:hidden; }
.sc::after { content:''; position:absolute; top:0; left:0; right:0; height:2px; }
.sc.s1::after{background:linear-gradient(90deg,var(--blue),var(--cyan));}
.sc.s2::after{background:linear-gradient(90deg,var(--cyan),var(--green));}
.sc.s3::after{background:linear-gradient(90deg,var(--green),var(--green2));}
.sc.s4::after{background:linear-gradient(90deg,var(--yellow),#fb923c);}
.sc-val { font-size:28px; font-weight:800; letter-spacing:-1px; margin-bottom:4px; }
.sc-val.blue{color:var(--blue2);} .sc-val.cyan{color:var(--cyan);} .sc-val.green{color:var(--green2);} .sc-val.yellow{color:var(--yellow);}
.sc-lbl { font-size:12px; color:var(--text2); font-weight:500; }

/* MAIN */
.main { max-width:1280px; margin:28px auto; padding:0 32px; position:relative; z-index:1; }

/* ALERT */
.alert { padding:14px 20px; border-radius:12px; margin-bottom:20px; font-size:14px; font-weight:500; }
.alert.success { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.25); color:var(--green2); }
.alert.error   { background:rgba(239,68,68,0.1);  border:1px solid rgba(239,68,68,0.25);  color:#f87171; }

/* TABS */
.tabs { display:flex; gap:4px; background:var(--card); border:1px solid var(--border); border-radius:12px; padding:6px; margin-bottom:24px; width:fit-content; }
.tab { padding:10px 24px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; color:var(--text2); transition:all 0.2s; }
.tab.active { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:white; box-shadow:0 4px 14px rgba(59,130,246,0.3); }
.tab:hover:not(.active) { color:var(--text); background:rgba(255,255,255,0.05); }

/* SECTION TITLE */
.sec-title { font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:var(--text2); display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.sec-title::before { content:''; width:3px; height:14px; background:linear-gradient(var(--blue),var(--cyan)); border-radius:2px; }

/* FORM CARD */
.form-card { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:28px; margin-bottom:24px; }
.form-card h3 { font-size:16px; font-weight:700; margin-bottom:20px; color:var(--text); }
.form-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group label { font-size:12px; color:var(--text2); font-weight:600; letter-spacing:0.5px; text-transform:uppercase; }
.form-group input, .form-group select {
  background:var(--surface); border:1px solid var(--border); border-radius:8px;
  padding:10px 14px; color:var(--text); font-family:var(--sans); font-size:14px;
  transition:border-color 0.2s; outline:none;
}
.form-group input:focus, .form-group select:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
.form-group select option { background:var(--surface); }
.form-actions { display:flex; gap:12px; margin-top:20px; }
.btn { padding:11px 24px; border-radius:8px; font-family:var(--sans); font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all 0.2s; }
.btn-primary { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:white; box-shadow:0 4px 14px rgba(59,130,246,0.25); }
.btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(59,130,246,0.35); }
.btn-secondary { background:var(--surface); color:var(--text2); border:1px solid var(--border); }
.btn-secondary:hover { color:var(--text); border-color:var(--border2,#243352); }
.btn-danger { background:rgba(239,68,68,0.1); color:#f87171; border:1px solid rgba(239,68,68,0.2); }
.btn-danger:hover { background:rgba(239,68,68,0.2); }
.btn-sm { padding:6px 14px; font-size:12px; }

/* TABLE */
.tbl-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; }
thead th { background:var(--surface); padding:13px 18px; text-align:left; font-size:10px; letter-spacing:2px; text-transform:uppercase; color:var(--text2); font-weight:700; border-bottom:1px solid var(--border); }
tbody tr { border-bottom:1px solid rgba(30,45,71,0.5); transition:background 0.15s; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:rgba(59,130,246,0.04); }
td { padding:14px 18px; font-size:14px; }
.emp-av { width:32px; height:32px; border-radius:8px; background:linear-gradient(135deg,var(--blue),var(--cyan)); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:white; flex-shrink:0; }
.emp-info { display:flex; align-items:center; gap:10px; }
.emp-name { font-size:14px; font-weight:600; }
.emp-email { font-size:11px; color:var(--text2); margin-top:1px; }
.dept-tag { font-size:10px; font-weight:600; padding:3px 8px; border-radius:4px; }
.dept-tag.Engineering{background:rgba(59,130,246,0.12);color:var(--blue2);}
.dept-tag.Design{background:rgba(168,85,247,0.12);color:#c084fc;}
.dept-tag.Marketing{background:rgba(245,158,11,0.12);color:var(--yellow);}
.dept-tag.Finance{background:rgba(16,185,129,0.12);color:var(--green2);}
.dept-tag.HR{background:rgba(236,72,153,0.12);color:#f472b6;}
.dept-tag.Support{background:rgba(6,182,212,0.12);color:var(--cyan);}
.dept-tag.Operations{background:rgba(251,146,60,0.12);color:#fb923c;}
.dept-tag.Sales{background:rgba(52,211,153,0.12);color:var(--green2);}
.mono-sm { font-family:var(--mono); font-size:12px; color:var(--text2); }
.salary-val { font-family:var(--mono); font-size:14px; font-weight:600; color:var(--green2); }
.actions-cell { display:flex; gap:8px; }
input[type="checkbox"] { width:16px; height:16px; accent-color:var(--blue); cursor:pointer; }

/* PAYOUT TAB */
.action-bar { background:var(--card2); border:1px solid var(--border); border-radius:14px; padding:18px 24px; display:flex; align-items:center; justify-content:space-between; gap:20px; margin-bottom:24px; flex-wrap:wrap; }
.sel-label { display:flex; align-items:center; gap:10px; font-size:13px; color:var(--text2); cursor:pointer; font-weight:500; }
.payout-preview { text-align:center; }
.payout-amt { font-family:var(--mono); font-size:22px; font-weight:700; color:var(--green2); }
.payout-lbl { font-size:11px; color:var(--text2); margin-top:2px; }
.btn-trigger { background:linear-gradient(135deg,var(--blue),var(--cyan)); color:white; border:none; padding:14px 36px; border-radius:12px; font-family:var(--sans); font-size:15px; font-weight:700; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; gap:10px; box-shadow:0 4px 20px rgba(59,130,246,0.3); }
.btn-trigger:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(59,130,246,0.45); }

/* RESULTS */
.result-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:var(--border); border-radius:14px; overflow:hidden; border:1px solid var(--border); margin-bottom:24px; }
.rs { background:var(--card); padding:20px; text-align:center; }
.rs-val { font-size:30px; font-weight:900; letter-spacing:-1px; }
.rs-lbl { font-size:11px; color:var(--text2); letter-spacing:1.5px; text-transform:uppercase; margin-top:5px; }
.results-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
.rc { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px; animation:fadeUp 0.4s ease both; }
.rc.success{border-color:rgba(16,185,129,0.25);border-top:2px solid var(--green);}
.rc.failed{border-color:rgba(239,68,68,0.25);border-top:2px solid var(--red);}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.rc-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
.rc-name { font-size:15px; font-weight:700; }
.rc-dept { font-size:11px; color:var(--text2); margin-top:2px; }
.badge { font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; padding:5px 12px; border-radius:20px; }
.badge.success{background:rgba(16,185,129,0.12);color:var(--green2);border:1px solid rgba(16,185,129,0.25);}
.badge.failed{background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.25);}
.rc-rows { display:flex; flex-direction:column; gap:9px; }
.rc-row { display:flex; justify-content:space-between; font-size:13px; }
.rc-lbl { color:var(--text2); }
.rc-val { font-family:var(--mono); font-size:11px; color:var(--text); }
.rc-val.green{color:var(--green2);font-size:14px;font-weight:700;}
.rc-val.red{color:#f87171;} .rc-val.blue{color:var(--blue2);} .rc-val.yellow{color:var(--yellow);}
.divider { height:1px; background:var(--border); margin:4px 0; }

/* HISTORY */
.history-card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px 24px; margin-bottom:14px; display:flex; align-items:center; justify-content:space-between; cursor:pointer; transition:border-color 0.2s; }
.history-card:hover { border-color:rgba(59,130,246,0.3); }
.h-month { font-size:16px; font-weight:700; }
.h-date  { font-size:12px; color:var(--text2); margin-top:2px; }
.h-stats { display:flex; gap:24px; }
.h-stat  { text-align:center; }
.h-stat-val { font-family:var(--mono); font-size:18px; font-weight:700; }
.h-stat-lbl { font-size:11px; color:var(--text2); margin-top:2px; }
.h-amount { font-family:var(--mono); font-size:20px; font-weight:800; color:var(--green2); }

/* EMPTY */
.empty { text-align:center; padding:60px 20px; background:var(--card); border:1px solid var(--border); border-radius:16px; }
.empty-icon { font-size:48px; margin-bottom:14px; opacity:0.6; }
.empty p { color:var(--text2); font-size:14px; line-height:1.7; }

/* SIM NOTICE */
.sim-notice { background:linear-gradient(135deg,rgba(245,158,11,0.08),rgba(251,146,60,0.05)); border:1px solid rgba(245,158,11,0.2); border-radius:12px; padding:14px 20px; margin-bottom:20px; font-size:13px; color:#fcd34d; display:flex; align-items:center; gap:12px; }

@media(max-width:768px){
  .stats{grid-template-columns:repeat(2,1fr);}
  header{padding:0 16px;}
  .main{padding:0 16px;}
  .form-grid{grid-template-columns:1fr 1fr;}
  .result-strip{grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body>

<!-- HEADER -->
<header>
  <div class="logo">
    <div class="logo-mark">💸</div>
    <div class="logo-text">Pay<span>Flow</span></div>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:13px;color:var(--text2);">👤 <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></span>
    <a href="login.php?logout=1" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.25);color:#f87171;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Logout</a>
  </div>
  <div class="sim-badge">
    <div class="sim-dot"></div>
    <?= $SIMULATION_MODE ? 'SIMULATION MODE' : 'LIVE MODE' ?>
  </div>
</header>

<!-- STATS -->
<div class="stats">
  <div class="sc s1">
    <div class="sc-val blue"><?= count($employees) ?></div>
    <div class="sc-lbl">Active Employees</div>
  </div>
  <div class="sc s2">
    <div class="sc-val cyan">₹<?= number_format($totalSalary) ?></div>
    <div class="sc-lbl">Monthly Salary Pool</div>
  </div>
  <div class="sc s3">
    <?php $totalPayouts = $db->query("SELECT COUNT(*) as c FROM payouts")->fetch_assoc()['c']; ?>
    <div class="sc-val green"><?= $totalPayouts ?></div>
    <div class="sc-lbl">Total Payouts Done</div>
  </div>
  <div class="sc s4">
    <?php $lastPayout = $db->query("SELECT SUM(total_amount) as s FROM payouts")->fetch_assoc()['s'] ?? 0; ?>
    <div class="sc-val yellow">₹<?= number_format($lastPayout) ?></div>
    <div class="sc-lbl">Total Disbursed</div>
  </div>
</div>

<!-- MAIN -->
<div class="main">

  <?php if ($message): ?>
  <div class="alert <?= $msgType ?>"><?= $message ?></div>
  <?php endif; ?>

  <!-- TABS -->
  <div class="tabs">
    <a href="?tab=employees" class="tab <?= $tab==='employees'?'active':'' ?>">👥 Employees</a>
    <a href="?tab=payout"    class="tab <?= $tab==='payout'   ?'active':'' ?>">💸 Bulk Payout</a>
    <a href="?tab=history"   class="tab <?= $tab==='history'  ?'active':'' ?>">📋 History</a>
  </div>

  <!-- ================================================ -->
  <!-- TAB 1: EMPLOYEES -->
  <!-- ================================================ -->
  <?php if ($tab === 'employees'): ?>

  <!-- ADD / EDIT FORM -->
  <div class="form-card">
    <h3><?= $editEmp ? '✏️ Edit Employee' : '➕ Add New Employee' ?></h3>
    <form method="POST">
      <input type="hidden" name="action" value="<?= $editEmp ? 'edit_employee' : 'add_employee' ?>">
      <?php if ($editEmp): ?>
      <input type="hidden" name="emp_id" value="<?= $editEmp['id'] ?>">
      <?php endif; ?>
      <div class="form-grid">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" required placeholder="e.g. Ravi Kumar" value="<?= htmlspecialchars($editEmp['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required placeholder="name@company.com" value="<?= htmlspecialchars($editEmp['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Mobile</label>
          <input type="text" name="mobile" required placeholder="9000000000" value="<?= htmlspecialchars($editEmp['mobile'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Department</label>
          <select name="department" required>
            <option value="">Select Department</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d ?>" <?= ($editEmp['department'] ?? '')===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Bank Name</label>
          <select name="bank_name" required>
            <option value="">Select Bank</option>
            <?php foreach ($banks as $b): ?>
            <option value="<?= $b ?>" <?= ($editEmp['bank_name'] ?? '')===$b?'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Account Number</label>
          <input type="text" name="account_number" required placeholder="1234567890" value="<?= htmlspecialchars($editEmp['account_number'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>IFSC Code</label>
          <input type="text" name="ifsc" required placeholder="HDFC0001234" value="<?= htmlspecialchars($editEmp['ifsc'] ?? '') ?>" style="text-transform:uppercase">
        </div>
        <div class="form-group">
          <label>Monthly Salary (₹)</label>
          <input type="number" name="salary" required placeholder="45000" value="<?= htmlspecialchars($editEmp['salary'] ?? '') ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editEmp ? '💾 Update Employee' : '➕ Add Employee' ?></button>
        <?php if ($editEmp): ?>
        <a href="?tab=employees" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- EMPLOYEE LIST -->
  <div class="sec-title">All Employees (<?= count($employees) ?>)</div>
  <?php if (empty($employees)): ?>
  <div class="empty"><div class="empty-icon">👥</div><p>No employees added yet.<br>Add your first employee using the form above!</p></div>
  <?php else: ?>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Employee</th>
          <th>Department</th>
          <th>Bank</th>
          <th>IFSC</th>
          <th>Account No.</th>
          <th>Salary</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
        <tr>
          <td>
            <div class="emp-info">
              <div class="emp-av"><?= strtoupper(substr($emp['name'],0,1)) ?></div>
              <div>
                <div class="emp-name"><?= htmlspecialchars($emp['name']) ?></div>
                <div class="emp-email"><?= htmlspecialchars($emp['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="dept-tag <?= $emp['department'] ?>"><?= $emp['department'] ?></span></td>
          <td><span class="mono-sm"><?= htmlspecialchars($emp['bank_name']) ?></span></td>
          <td><span class="mono-sm"><?= htmlspecialchars($emp['ifsc']) ?></span></td>
          <td><span class="mono-sm"><?= htmlspecialchars($emp['account_number']) ?></span></td>
          <td><span class="salary-val">₹<?= number_format($emp['salary']) ?></span></td>
          <td>
            <div class="actions-cell">
              <a href="?edit=<?= $emp['id'] ?>&tab=employees" class="btn btn-secondary btn-sm">✏️ Edit</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars($emp['name']) ?>?')">
                <input type="hidden" name="action" value="delete_employee">
                <input type="hidden" name="emp_id" value="<?= $emp['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">🗑️ Remove</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- ================================================ -->
  <!-- TAB 2: BULK PAYOUT -->
  <!-- ================================================ -->
  <?php elseif ($tab === 'payout'): ?>

  <div class="sim-notice">
    🧪 <strong>Simulation Mode</strong> — Realistic dummy responses mirror real RazorpayX API. Change <code style="background:rgba(0,0,0,0.3);padding:1px 6px;border-radius:4px;">$SIMULATION_MODE = false</code> + add keys when ready for live!
  </div>

  <?php if (empty($employees)): ?>
  <div class="empty"><div class="empty-icon">👥</div><p>No employees found!<br>Add employees first from the <a href="?tab=employees" style="color:var(--blue2)">Employees tab</a>.</p></div>
  <?php else: ?>

  <form method="POST">
    <input type="hidden" name="action" value="trigger_payout">

    <div class="sec-title">Select Employees for <?= date('F Y') ?> Salary</div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:52px;text-align:center;"><input type="checkbox" id="sa" checked></th>
            <th>Employee</th>
            <th>Department</th>
            <th>Bank</th>
            <th>Account No.</th>
            <th>Salary</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp): ?>
          <tr>
            <td style="text-align:center;">
              <input type="checkbox" name="employee_ids[]" value="<?= $emp['id'] ?>" class="ecb" checked>
            </td>
            <td>
              <div class="emp-info">
                <div class="emp-av"><?= strtoupper(substr($emp['name'],0,1)) ?></div>
                <div>
                  <div class="emp-name"><?= htmlspecialchars($emp['name']) ?></div>
                  <div class="emp-email"><?= htmlspecialchars($emp['email']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="dept-tag <?= $emp['department'] ?>"><?= $emp['department'] ?></span></td>
            <td><span class="mono-sm"><?= htmlspecialchars($emp['bank_name']) ?></span></td>
            <td><span class="mono-sm"><?= htmlspecialchars($emp['account_number']) ?></span></td>
            <td><span class="salary-val">₹<?= number_format($emp['salary']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="action-bar">
      <label class="sel-label">
        <input type="checkbox" id="sab" checked> Select / Deselect All
      </label>
      <div class="payout-preview">
        <div class="payout-amt" id="totalAmt">₹<?= number_format($totalSalary) ?></div>
        <div class="payout-lbl">Total for <span id="selCnt"><?= count($employees) ?></span> employees</div>
      </div>
      <button type="submit" class="btn-trigger">💸 Trigger Bulk Payout</button>
    </div>
  </form>

  <!-- RESULTS -->
  <?php if (!empty($payoutResults)): ?>
  <div class="sec-title" style="margin-top:8px;">Payout Results</div>
  <div class="result-strip">
    <div class="rs"><div class="rs-val" style="color:var(--blue2)"><?= count($payoutResults) ?></div><div class="rs-lbl">Processed</div></div>
    <div class="rs"><div class="rs-val" style="color:var(--green2)"><?= $successCount ?></div><div class="rs-lbl">Successful</div></div>
    <div class="rs"><div class="rs-val" style="color:#f87171"><?= $failCount ?></div><div class="rs-lbl">Failed</div></div>
    <div class="rs"><div class="rs-val" style="color:var(--green2);font-size:20px;padding-top:6px;">₹<?= number_format($totalPaid) ?></div><div class="rs-lbl">Disbursed</div></div>
  </div>
  <div class="results-grid">
    <?php foreach ($payoutResults as $i => $r): ?>
    <div class="rc <?= $r['status'] ?>" style="animation-delay:<?= $i*0.07 ?>s">
      <div class="rc-top">
        <div><div class="rc-name"><?= htmlspecialchars($r['employee']) ?></div><div class="rc-dept"><?= $r['dept'] ?> · <?= $r['bank'] ?></div></div>
        <span class="badge <?= $r['status'] ?>"><?= $r['status']==='success'?'✓ SUCCESS':'✕ FAILED' ?></span>
      </div>
      <div class="rc-rows">
        <div class="rc-row"><span class="rc-lbl">Salary</span><span class="rc-val green">₹<?= number_format($r['salary']) ?></span></div>
        <div class="divider"></div>
        <?php if ($r['status']==='success'): ?>
        <div class="rc-row"><span class="rc-lbl">Contact ID</span><span class="rc-val blue"><?= $r['contact_id'] ?></span></div>
        <div class="rc-row"><span class="rc-lbl">Fund Account ID</span><span class="rc-val blue"><?= $r['fund_account_id'] ?></span></div>
        <div class="rc-row"><span class="rc-lbl">Payout ID</span><span class="rc-val blue"><?= $r['payout_id'] ?></span></div>
        <div class="rc-row"><span class="rc-lbl">UTR Number</span><span class="rc-val yellow"><?= $r['utr'] ?></span></div>
        <div class="rc-row"><span class="rc-lbl">Mode</span><span class="rc-val"><?= $r['mode'] ?></span></div>
        <div class="rc-row"><span class="rc-lbl">Timestamp</span><span class="rc-val"><?= $r['timestamp'] ?></span></div>
        <div class="divider"></div>
        <div class="rc-row"><span class="rc-lbl">Status</span><span class="rc-val green">● <?= $r['payout_status'] ?></span></div>
        <?php else: ?>
        <div class="rc-row"><span class="rc-lbl">Reason</span><span class="rc-val red"><?= htmlspecialchars($r['message']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- ================================================ -->
  <!-- TAB 3: HISTORY -->
  <!-- ================================================ -->
  <?php elseif ($tab === 'history'): ?>

  <div class="sec-title">Payout History</div>

  <?php if (empty($payoutHistory)): ?>
  <div class="empty"><div class="empty-icon">📋</div><p>No payout history yet.<br>Trigger your first bulk payout from the <a href="?tab=payout" style="color:var(--blue2)">Payout tab</a>!</p></div>
  <?php else: ?>
  <?php foreach ($payoutHistory as $p): ?>
  <div class="history-card">
    <div>
      <div class="h-month"><?= htmlspecialchars($p['payout_month']) ?></div>
      <div class="h-date"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></div>
    </div>
    <div class="h-stats">
      <div class="h-stat"><div class="h-stat-val" style="color:var(--blue2)"><?= $p['total_employees'] ?></div><div class="h-stat-lbl">Employees</div></div>
      <div class="h-stat"><div class="h-stat-val" style="color:var(--green2)"><?= $p['success_count'] ?></div><div class="h-stat-lbl">Success</div></div>
      <div class="h-stat"><div class="h-stat-val" style="color:#f87171"><?= $p['fail_count'] ?></div><div class="h-stat-lbl">Failed</div></div>
    </div>
    <div class="h-amount">₹<?= number_format($p['total_amount']) ?></div>
    <div style="display:flex;align-items:center;gap:10px;">
      <span class="badge success">✓ <?= strtoupper($p['status']) ?></span>
      <?php if ($p['fail_count'] > 0): ?>
      <form method="POST" style="margin:0">
        <input type="hidden" name="action" value="retry_failed">
        <input type="hidden" name="payout_id" value="<?= $p['id'] ?>">
        <button type="submit" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#f87171;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;" onclick="return confirm('Retry <?= $p['fail_count'] ?> failed payment(s)?')">🔄 Retry <?= $p['fail_count'] ?> Failed</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php endif; ?>

</div>

<script>
const salaries = <?= json_encode(array_column($employees, 'salary', 'id')) ?>;
function update() {
  const checked = document.querySelectorAll('.ecb:checked');
  let total = 0;
  checked.forEach(cb => total += salaries[cb.value] || 0);
  const ta = document.getElementById('totalAmt');
  const sc = document.getElementById('selCnt');
  if(ta) ta.textContent = '₹' + total.toLocaleString('en-IN');
  if(sc) sc.textContent = checked.length;
}
['sa','sab'].forEach(id => {
  document.getElementById(id)?.addEventListener('change', function() {
    document.querySelectorAll('.ecb').forEach(cb => cb.checked = this.checked);
    ['sa','sab'].forEach(oid => { const el=document.getElementById(oid); if(el) el.checked=this.checked; });
    update();
  });
});
document.querySelectorAll('.ecb').forEach(cb => cb.addEventListener('change', update));
</script>
</body>
</html>