<?php
date_default_timezone_set('Asia/Kolkata');
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once 'db.php';
require_once 'libs/XlsxWriter.php';
$db = getDB();

// ═══════════════════════════════════════════════
//  CASHFREE TEST CREDENTIALS
//  Set $SIM = false + fill credentials to go live
// ═══════════════════════════════════════════════
$SIM = true;
define('CF_ID',       'TEST_CF_CLIENT_ID_HERE');
define('CF_SECRET',   'TEST_CF_CLIENT_SECRET_HERE');
define('CF_ENV',      'TEST');
define('CF_TEST_URL', 'https://payout-gamma.cashfree.com/payout/v1');
define('CF_PROD_URL', 'https://payout-api.cashfree.com/payout/v1');

// ═══════════════════════════════════════════════
//  ACTIONS
// ═══════════════════════════════════════════════
$action        = $_POST['action'] ?? $_GET['action'] ?? '';
$tab           = $_GET['tab'] ?? 'dashboard';
$msg           = ''; $msgT = '';
$payoutResults = []; $okCount = 0; $failCount = 0;

// ── ADD EMPLOYEE ──────────────────────────────
if ($action === 'add_employee') {
    $tab = 'employees';
    $ui = trim($_POST['upi_id'] ?? '') ?: null;
    $stmt = $db->prepare("INSERT INTO employees (name,email,mobile,department,bank_name,account_number,ifsc,upi_id,salary) VALUES(?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssssd", $_POST['name'],$_POST['email'],$_POST['mobile'],$_POST['department'],$_POST['bank_name'],$_POST['account_number'],strtoupper(trim($_POST['ifsc'])),$ui,floatval($_POST['salary']));
    $stmt->execute() ? ($msg="✅ Employee added!",$msgT='ok') : ($msg="❌ Failed.",$msgT='err');
}

// ── EDIT EMPLOYEE ─────────────────────────────
if ($action === 'edit_employee') {
    $tab = 'employees'; $id = intval($_POST['emp_id']);
    $ui = trim($_POST['upi_id'] ?? '') ?: null;
    $stmt = $db->prepare("UPDATE employees SET name=?,email=?,mobile=?,department=?,bank_name=?,account_number=?,ifsc=?,upi_id=?,salary=? WHERE id=?");
    $stmt->bind_param("ssssssssdi",$_POST['name'],$_POST['email'],$_POST['mobile'],$_POST['department'],$_POST['bank_name'],$_POST['account_number'],strtoupper(trim($_POST['ifsc'])),$ui,floatval($_POST['salary']),$id);
    $stmt->execute() && ($msg="✅ Updated!",$msgT='ok');
}

// ── DELETE EMPLOYEE ───────────────────────────
if ($action === 'delete_employee') {
    $tab = 'employees'; $id = intval($_POST['emp_id']);
    $db->query("UPDATE employees SET status='inactive' WHERE id=$id");
    $msg="✅ Employee removed."; $msgT='ok';
}

// ── TRIGGER BULK PAYOUT ───────────────────────
if ($action === 'trigger_payout') {
    $tab     = 'payout';
    $selIds  = array_map('intval', $_POST['employee_ids'] ?? []);
    $pMode   = $_POST['payout_mode'] ?? 'bank';
    $dFilter = $_POST['dept_filter']  ?? 'All';

    if (empty($selIds)) { $msg="❌ Select at least one employee."; $msgT='err'; }
    else {
        $ids = implode(',', $selIds);
        $emps = $db->query("SELECT * FROM employees WHERE id IN ($ids) AND status='active'")->fetch_all(MYSQLI_ASSOC);
        $totalAmt = array_sum(array_column($emps,'salary'));
        $month = date('F Y');

        $stmt = $db->prepare("INSERT INTO payouts (payout_month,total_amount,total_employees,department_filter,payout_mode) VALUES(?,?,?,?,?)");
        $cnt = count($emps);
        $stmt->bind_param("sdiss",$month,$totalAmt,$cnt,$dFilter,$pMode);
        $stmt->execute(); $pid = $db->insert_id;

        $cfToken = (!$SIM) ? getCFToken() : null;

        foreach ($emps as $emp) {
            $eMode = 'bank';
            if (($pMode==='upi'||$pMode==='auto') && !empty($emp['upi_id'])) $eMode='upi';

            $r = $SIM ? simPayout($emp,$eMode) : realCFPayout($emp,$cfToken,$eMode);
            $s=$r['status']; $bi=$r['beneficiary_id']??null; $ti=$r['transfer_id']??null;
            $u=$r['utr']??null; $fm=$r['message']??null;
            $eid=$emp['id']; $en=$emp['name']; $es=$emp['salary']; $ed=$emp['department'];

            $s2=$db->prepare("INSERT INTO payout_items (payout_id,employee_id,employee_name,department,salary,payout_mode,status,beneficiary_id,transfer_id,utr_number,failure_reason) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
            $s2->bind_param("iissdssssss",$pid,$eid,$en,$ed,$es,$eMode,$s,$bi,$ti,$u,$fm);
            $s2->execute();
            ($s==='success') ? $okCount++ : $failCount++;
            $r['employee']=$emp['name']; $r['salary']=$emp['salary'];
            $r['dept']=$emp['department']; $r['bank']=$emp['bank_name']; $r['emode']=$eMode;
            $payoutResults[]=$r;
        }
        $paid=array_sum(array_map(fn($r)=>$r['status']==='success'?$r['salary']:0,$payoutResults));
        $db->query("UPDATE payouts SET success_count=$okCount,fail_count=$failCount,total_amount=$paid,status='completed' WHERE id=$pid");
        $msg="✅ Payout complete — $okCount success, $failCount failed."; $msgT='ok';
    }
}

// ── RETRY FAILED ──────────────────────────────
if ($action === 'retry_failed') {
    $tab = 'history'; $pid = intval($_POST['payout_id']??0);
    if ($pid>0) {
        $fr=$db->query("SELECT pi.*,e.account_number,e.ifsc,e.email,e.mobile,e.department,e.bank_name,e.upi_id FROM payout_items pi JOIN employees e ON pi.employee_id=e.id WHERE pi.payout_id=$pid AND pi.status='failed'");
        $failed=$fr->fetch_all(MYSQLI_ASSOC);
        if (empty($failed)) { $msg="No failed items."; $msgT='ok'; }
        else {
            $cfToken=(!$SIM)?getCFToken():null;
            foreach ($failed as $item) {
                $emp=['id'=>$item['employee_id'],'name'=>$item['employee_name'],'salary'=>$item['salary'],'email'=>$item['email'],'mobile'=>$item['mobile'],'department'=>$item['department'],'bank_name'=>$item['bank_name'],'account_number'=>$item['account_number'],'ifsc'=>$item['ifsc'],'upi_id'=>$item['upi_id']];
                $md=$item['payout_mode']??'bank';
                $r=$SIM?simPayout($emp,$md):realCFPayout($emp,$cfToken,$md);
                $s=$r['status'];$bi=$r['beneficiary_id']??null;$ti=$r['transfer_id']??null;$u=$r['utr']??null;$fm=$r['message']??null;$iid=$item['id'];
                $st=$db->prepare("UPDATE payout_items SET status=?,beneficiary_id=?,transfer_id=?,utr_number=?,failure_reason=? WHERE id=?");
                $st->bind_param("sssssi",$s,$bi,$ti,$u,$fm,$iid);$st->execute();
                ($s==='success')?$okCount++:$failCount++;
                $r['employee']=$item['employee_name'];$r['salary']=$item['salary'];$r['dept']=$item['department'];$r['bank']=$item['bank_name'];$r['emode']=$md;
                $payoutResults[]=$r;
            }
            $ai=$db->query("SELECT status,salary FROM payout_items WHERE payout_id=$pid");
            $as=0;$af=0;$aa=0;
            while($row=$ai->fetch_assoc()){if($row['status']==='success'){$as++;$aa+=$row['salary'];}else $af++;}
            $db->query("UPDATE payouts SET success_count=$as,fail_count=$af,total_amount=$aa WHERE id=$pid");
            $msg="🔄 Retry: $okCount recovered, $failCount still failed."; $msgT=$failCount===0?'ok':'warn';
        }
    }
}

// ── EXPORT XLSX ───────────────────────────────
if ($action === 'export_xlsx') {
    $pid = intval($_GET['payout_id']??0);
    if ($pid>0) {
        $rows  = $db->query("SELECT * FROM payout_items WHERE payout_id=$pid ORDER BY id")->fetch_all(MYSQLI_ASSOC);
        $pInfo = $db->query("SELECT * FROM payouts WHERE id=$pid")->fetch_assoc();
        $succ  = array_filter($rows,fn($r)=>$r['status']==='success');
        $fail  = array_filter($rows,fn($r)=>$r['status']==='failed');
        $totalPaid = array_sum(array_column(array_values($succ),'salary'));

        $xl = new XlsxWriter();

        // ── SHEET 1: Summary ────────────────────
        $xl->addSheet('Summary');
        $xl->setColWidth(1,28)->setColWidth(2,22)->setColWidth(3,22)->setColWidth(4,22)->setColWidth(5,22);
        $xl->setRowHeight(1,40)->setRowHeight(2,32)->setRowHeight(5,28)->setRowHeight(6,28);

        // Title row
        $xl->writeRow(['PayFlow Pro — Payout Report','','','',''], [5,5,5,5,5]);
        $xl->mergeCells('A1','E1');

        // Payout info
        $xl->writeRow(['Month: '.$pInfo['payout_month'],'Mode: '.strtoupper($pInfo['payout_mode']),'Dept: '.$pInfo['department_filter'],'Generated: '.date('d M Y, h:i A'),''], [9,9,9,9,9]);
        $xl->mergeCells('A2','E2');

        $xl->writeRow(['','','','',''], [0,0,0,0,0]);

        // Summary stats headers
        $xl->writeRow(['TOTAL EMPLOYEES','SUCCESSFUL','FAILED','AMOUNT DISBURSED','SUCCESS RATE'], [1,1,1,1,1]);

        // Summary stats values
        $rate = count($rows)>0 ? round(count($succ)/count($rows)*100,1).'%' : '0%';
        $xl->writeRow([count($rows), count($succ), count($fail), $totalPaid, $rate], [10,3,4,11,10]);

        $xl->writeRow(['','','','',''], [0,0,0,0,0]);

        // ── SHEET 2: All Transactions ────────────
        $xl->addSheet('All Transactions');
        $xl->setColWidth(1,5)->setColWidth(2,22)->setColWidth(3,16)->setColWidth(4,14)->setColWidth(5,10)->setColWidth(6,12)->setColWidth(7,26)->setColWidth(8,26)->setColWidth(9,22)->setColWidth(10,28);
        $xl->setRowHeight(1,36);

        $xl->writeRow(['#','Employee Name','Department','Salary (₹)','Mode','Status','Beneficiary ID','Transfer ID','UTR Number','Note / Reason'], [1,1,1,1,1,1,1,1,1,1]);

        foreach ($rows as $i=>$r) {
            $isOk = $r['status']==='success';
            $altStyle = ($i%2===1) ? 6 : 0;
            $statusStyle = $isOk ? 3 : 4;
            $xl->writeRow([
                $i+1,
                $r['employee_name'],
                $r['department'],
                floatval($r['salary']),
                strtoupper($r['payout_mode']),
                strtoupper($r['status']),
                $r['beneficiary_id']??'—',
                $r['transfer_id']??'—',
                $r['utr_number']??'—',
                $r['failure_reason']??($isOk?'Salary credited successfully':''),
            ], [8,$altStyle,$altStyle,7,8,$statusStyle,$altStyle,$altStyle,$altStyle,$altStyle]);
        }

        // ── SHEET 3: Successful Only ─────────────
        $xl->addSheet('Successful');
        $xl->setColWidth(1,5)->setColWidth(2,22)->setColWidth(3,16)->setColWidth(4,14)->setColWidth(5,10)->setColWidth(6,26)->setColWidth(7,26)->setColWidth(8,22);
        $xl->setRowHeight(1,36);
        $xl->writeRow(['#','Employee Name','Department','Salary (₹)','Mode','Beneficiary ID','Transfer ID','UTR Number'], [1,1,1,1,1,1,1,1]);
        $si=1;
        foreach ($succ as $r) {
            $alt = ($si%2===1)?6:0;
            $xl->writeRow([$si,$r['employee_name'],$r['department'],floatval($r['salary']),strtoupper($r['payout_mode']),$r['beneficiary_id']??'—',$r['transfer_id']??'—',$r['utr_number']??'—'],[$al=$alt,$alt,$alt,7,8,$alt,$alt,$alt]);
            $si++;
        }

        // ── SHEET 4: Failed Only ──────────────────
        $xl->addSheet('Failed');
        if (!empty($fail)) {
            $xl->setColWidth(1,5)->setColWidth(2,22)->setColWidth(3,16)->setColWidth(4,14)->setColWidth(5,10)->setColWidth(6,34);
            $xl->setRowHeight(1,36);
            $xl->writeRow(['#','Employee Name','Department','Salary (₹)','Mode','Failure Reason'],[1,1,1,1,1,1]);
            $fi=1;
            foreach ($fail as $r) {
                $xl->writeRow([$fi,$r['employee_name'],$r['department'],floatval($r['salary']),strtoupper($r['payout_mode']),$r['failure_reason']??'Unknown error'],[8,0,0,7,8,4]);
                $fi++;
            }
        } else {
            $xl->writeRow(['🎉 No failed transactions in this payout!'],[0]);
        }

        $fname = 'PayFlow_Payout_'.str_replace(' ','_',$pInfo['payout_month']).'_'.date('Ymd').'.xlsx';
        $xl->download($fname);
    }
}

// ── EXPORT PDF ────────────────────────────────
if ($action === 'export_pdf') {
    $pid = intval($_GET['payout_id']??0);
    if ($pid>0) {
        $rows  = $db->query("SELECT * FROM payout_items WHERE payout_id=$pid ORDER BY id")->fetch_all(MYSQLI_ASSOC);
        $pInfo = $db->query("SELECT * FROM payouts WHERE id=$pid")->fetch_assoc();
        $succ  = array_filter($rows,fn($r)=>$r['status']==='success');
        $fail  = array_filter($rows,fn($r)=>$r['status']==='failed');
        $paid  = array_sum(array_column(array_values($succ),'salary'));
        $rate  = count($rows)>0?round(count($succ)/count($rows)*100,1):0;
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Payout Report</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<style>
  body{font-family:'Plus Jakarta Sans',sans-serif;padding:36px;color:#1a1510;background:#faf7f2;font-size:13px;}
  .no-print{margin-bottom:20px;}
  .btn{padding:10px 22px;background:#1a1510;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-family:'Playfair Display',serif;font-weight:700;margin-right:10px;}
  .btn-orange{background:#c9651a;}
  h1{font-family:'Playfair Display',serif;font-size:32px;font-weight:900;color:#1a1510;margin-bottom:4px;}
  .meta{color:#9e8f7e;margin-bottom:24px;font-size:12px;font-family:'Fira Code',monospace;}
  .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:28px;}
  .stat{background:#fff;border:1px solid #d4c9b8;border-radius:8px;padding:14px;text-align:center;}
  .stat-val{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;}
  .stat-lbl{font-size:10px;color:#9e8f7e;text-transform:uppercase;letter-spacing:1px;margin-top:2px;}
  .stat.ok .stat-val{color:#2d6a4f;} .stat.fail .stat-val{color:#a83232;} .stat.amt .stat-val{color:#c9651a;}
  table{width:100%;border-collapse:collapse;margin-top:16px;}
  th{background:#1a1510;color:#fff;padding:10px 12px;text-align:left;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;font-family:'Fira Code',monospace;}
  td{padding:9px 12px;border-bottom:1px solid #e8ddd0;font-size:12px;}
  tr:nth-child(even) td{background:#f0e9dc;}
  .badge-ok{background:#dcfce7;color:#166534;padding:2px 10px;border-radius:3px;font-weight:700;font-size:10px;font-family:'Fira Code',monospace;}
  .badge-fail{background:#fee2e2;color:#991b1b;padding:2px 10px;border-radius:3px;font-weight:700;font-size:10px;font-family:'Fira Code',monospace;}
  .utr{font-family:'Fira Code',monospace;font-size:10px;color:#9e8f7e;}
  .footer{margin-top:28px;text-align:center;font-size:11px;color:#9e8f7e;border-top:1px solid #d4c9b8;padding-top:16px;}
  @media print{.no-print{display:none!important;}body{padding:20px;}}
</style></head>
<body>
<div class="no-print">
  <button class="btn btn-orange" onclick="window.print()">🖨️ Print / Save PDF</button>
  <button class="btn" onclick="window.close()">✕ Close</button>
</div>
<h1>PayFlow Pro — Payout Report</h1>
<div class="meta">📅 <?= htmlspecialchars($pInfo['payout_month']) ?> &nbsp;|&nbsp; Mode: <?= strtoupper($pInfo['payout_mode']) ?> &nbsp;|&nbsp; Dept: <?= htmlspecialchars($pInfo['department_filter']) ?> &nbsp;|&nbsp; Generated: <?= date('d M Y, h:i A') ?> &nbsp;|&nbsp; Via Cashfree Payouts</div>
<div class="stats">
  <div class="stat"><div class="stat-val"><?= count($rows) ?></div><div class="stat-lbl">Total</div></div>
  <div class="stat ok"><div class="stat-val"><?= count($succ) ?></div><div class="stat-lbl">Success</div></div>
  <div class="stat fail"><div class="stat-val"><?= count($fail) ?></div><div class="stat-lbl">Failed</div></div>
  <div class="stat amt"><div class="stat-val">₹<?= number_format($paid) ?></div><div class="stat-lbl">Disbursed</div></div>
  <div class="stat"><div class="stat-val"><?= $rate ?>%</div><div class="stat-lbl">Success Rate</div></div>
</div>
<table>
<thead><tr><th>#</th><th>Employee</th><th>Dept</th><th>Salary</th><th>Mode</th><th>Status</th><th>Transfer ID</th><th>UTR</th><th>Note</th></tr></thead>
<tbody>
<?php $i=1; foreach ($rows as $r): $ok=$r['status']==='success'; ?>
<tr>
  <td><?= $i++ ?></td>
  <td><?= htmlspecialchars($r['employee_name']) ?></td>
  <td><?= htmlspecialchars($r['department']) ?></td>
  <td>₹<?= number_format($r['salary']) ?></td>
  <td style="font-family:'Fira Code',monospace;font-size:11px"><?= strtoupper($r['payout_mode']) ?></td>
  <td><span class="<?= $ok?'badge-ok':'badge-fail' ?>"><?= strtoupper($r['status']) ?></span></td>
  <td class="utr"><?= htmlspecialchars($r['transfer_id']??'—') ?></td>
  <td class="utr"><?= htmlspecialchars($r['utr_number']??'—') ?></td>
  <td><?= htmlspecialchars($r['failure_reason']??($ok?'Credited':'')) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="footer">PayFlow Pro v3 · Cashfree Payouts · <?= date('d M Y') ?></div>
</body></html>
<?php     exit; }
}

// ═══════════════════════════════════════════════
//  CASHFREE API
// ═══════════════════════════════════════════════
function getCFToken(){
    $url=(CF_ENV==='PROD'?CF_PROD_URL:CF_TEST_URL).'/authorize';
    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>'{}',CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Client-Id: '.CF_ID,'X-Client-Secret: '.CF_SECRET],CURLOPT_TIMEOUT=>20]);
    $res=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($code===200){$d=json_decode($res,true);return $d['data']['token']??null;}return null;
}
function realCFPayout($emp,$token,$mode){
    $base=CF_ENV==='PROD'?CF_PROD_URL:CF_TEST_URL;
    $h=['Content-Type: application/json','Authorization: Bearer '.$token];
    $bId='EMP_'.strtoupper(substr(md5($emp['id'].date('Ymd')),0,12));
    $bd=$mode==='upi'
        ?['beneId'=>$bId,'name'=>$emp['name'],'email'=>$emp['email'],'phone'=>$emp['mobile'],'vpa'=>$emp['upi_id'],'address1'=>'Employee','city'=>'India','state'=>'India','pincode'=>'400001']
        :['beneId'=>$bId,'name'=>$emp['name'],'email'=>$emp['email'],'phone'=>$emp['mobile'],'bankAccount'=>$emp['account_number'],'ifsc'=>$emp['ifsc'],'address1'=>'Employee','city'=>'India','state'=>'India','pincode'=>'400001'];
    $br=cfHttp('POST',$base.'/addBeneficiary',$h,$bd);
    if(!in_array($br['code'],[200,409]))return['status'=>'failed','message'=>$br['body']['message']??'Beneficiary failed','step'=>'Add Beneficiary'];
    $tId=strtoupper($mode==='upi'?'UPI':'BANK').'_'.strtoupper(substr(md5($emp['email'].microtime()),0,12));
    $td=['beneId'=>$bId,'amount'=>(string)$emp['salary'],'transferId'=>$tId,'transferMode'=>$mode==='upi'?'upi':'banktransfer','remarks'=>'Salary '.date('F Y')];
    $tr=cfHttp('POST',$base.'/requestTransfer',$h,$td);
    if($tr['code']!==200)return['status'=>'failed','message'=>$tr['body']['message']??'Transfer failed','step'=>'Request Transfer'];
    $sr=cfHttp('GET',$base.'/getTransferStatus?transferId='.$tId,$h,[]);
    $sd=$sr['body']['data']['transfer']??[];$utr=$sd['utr']??'Pending';$fs=strtolower($sd['status']??'failed');
    if(in_array($fs,['success','processed']))return['status'=>'success','beneficiary_id'=>$bId,'transfer_id'=>$tId,'utr'=>$utr,'transfer_status'=>strtoupper($fs),'mode'=>strtoupper($mode),'message'=>'Salary credited!','timestamp'=>date('d M Y, h:i A')];
    return['status'=>'failed','message'=>$sd['reason']??'Transfer failed','step'=>'Status Check'];
}
function cfHttp($method,$url,$headers,$data){
    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30]);
    if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    $res=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return['code'=>$code,'body'=>json_decode($res,true)];
}
function simPayout($emp,$mode){
    $bId='EMP_'.strtoupper(substr(md5($emp['id'].microtime()),0,10));
    $tId=strtoupper($mode).'_'.strtoupper(substr(md5($emp['email'].microtime()),0,12));
    $utr='CF'.strtoupper(substr(md5(microtime()),0,14));
    if(rand(1,100)<=95)return['status'=>'success','beneficiary_id'=>$bId,'transfer_id'=>$tId,'utr'=>$utr,'transfer_status'=>'SUCCESS','mode'=>strtoupper($mode),'message'=>'Salary credited via Cashfree '.strtoupper($mode).'!','timestamp'=>date('d M Y, h:i A')];
    $e=['Insufficient balance','Account validation failed','IFSC mismatch','Daily limit exceeded','Beneficiary inactive'];
    return['status'=>'failed','message'=>$e[array_rand($e)],'step'=>'Cashfree '.strtoupper($mode)];
}

// ═══════════════════════════════════════════════
//  FETCH DATA
// ═══════════════════════════════════════════════
$deptF   = $_GET['dept'] ?? 'All';
$depts   = ['Engineering','Design','Marketing','Finance','HR','Support','Operations','Sales'];
$banks   = ['HDFC Bank','ICICI Bank','SBI','Axis Bank','Kotak Bank','PNB','Bank of Baroda','Canara Bank','Yes Bank','IndusInd Bank'];
$empWhere= "status='active'".($deptF!=='All'?" AND department='".mysqli_real_escape_string($db,$deptF)."'":'');
$employees=$db->query("SELECT * FROM employees WHERE $empWhere ORDER BY department,name")->fetch_all(MYSQLI_ASSOC);
$allEmps =$db->query("SELECT * FROM employees WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$history =$db->query("SELECT * FROM payouts ORDER BY created_at DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$totalSal=array_sum(array_column($allEmps,'salary'));
$totalPay=$db->query("SELECT COUNT(*) as c FROM payouts")->fetch_assoc()['c'];
$totalDis=$db->query("SELECT COALESCE(SUM(total_amount),0) as s FROM payouts")->fetch_assoc()['s'];
$upiCount=count(array_filter($allEmps,fn($e)=>!empty($e['upi_id'])));
$editEmp =null;
if(isset($_GET['edit'])){$eid=intval($_GET['edit']);$editEmp=$db->query("SELECT * FROM employees WHERE id=$eid")->fetch_assoc();$tab='employees';}
$deptStats=[];
foreach($depts as $d){$r=$db->query("SELECT COUNT(*) as c,COALESCE(SUM(salary),0) as s FROM employees WHERE department='$d' AND status='active'")->fetch_assoc();if($r['c']>0)$deptStats[$d]=$r;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PayFlow Pro v3 — <?= ucfirst($tab) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,900;1,700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ════════════════════════════════════════
   DESIGN TOKENS — WARM AMBER / EDITORIAL
════════════════════════════════════════ */
:root{
  --cream:   #faf7f2;
  --sand:    #f0e9dc;
  --warm:    #e8ddd0;
  --warm2:   #ddd0c0;
  --border:  #d4c9b8;
  --muted:   #9e8f7e;
  --body:    #3d3328;
  --head:    #1a1510;
  --ink:     #0d0a07;
  --accent:  #c9651a;
  --accent2: #e07b2a;
  --amber:   #b8860b;
  --green:   #2d6a4f;
  --green2:  #166534;
  --red:     #a83232;
  --red2:    #991b1b;
  --blue:    #1e3a5f;
  --sky:     #0284c7;
  --playfair:'Playfair Display',serif;
  --jakarta: 'Plus Jakarta Sans',sans-serif;
  --code:    'Fira Code',monospace;
  --side:    260px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;}
body{background:var(--cream);color:var(--body);font-family:var(--jakarta);display:flex;min-height:100vh;}

/* ════════════════════════════════════════
   SIDEBAR
════════════════════════════════════════ */
.sidebar{
  width:var(--side);flex-shrink:0;
  background:var(--head);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;
  z-index:50;overflow:hidden;
}
.sidebar::before{
  content:'';position:absolute;
  bottom:-80px;right:-80px;
  width:220px;height:220px;border-radius:50%;
  background:radial-gradient(circle,rgba(201,101,26,0.2),transparent);
  pointer-events:none;
}
.sb-top{padding:24px 22px;border-bottom:1px solid rgba(255,255,255,0.07);}
.sb-brand{display:flex;align-items:center;gap:12px;}
.sb-icon{
  width:40px;height:40px;border:1.5px solid var(--accent);border-radius:4px;
  display:flex;align-items:center;justify-content:center;font-size:20px;
  flex-shrink:0;position:relative;
}
.sb-icon::after{content:'';position:absolute;inset:3px;border:1px solid rgba(201,101,26,0.25);border-radius:2px;}
.sb-name{font-family:var(--playfair);font-size:17px;color:#fff;line-height:1;}
.sb-ver{font-size:10px;color:rgba(255,255,255,0.3);letter-spacing:2px;text-transform:uppercase;font-family:var(--code);margin-top:2px;}

.sb-nav{padding:16px 12px;flex:1;}
.sb-label{font-size:9px;font-weight:700;color:rgba(255,255,255,0.2);letter-spacing:3px;text-transform:uppercase;padding:10px 10px 6px;font-family:var(--code);}
.sb-item{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;border-radius:6px;
  text-decoration:none;color:rgba(255,255,255,0.5);
  font-size:13px;font-weight:500;
  transition:all 0.2s;margin-bottom:2px;
  border-left:2px solid transparent;
}
.sb-item:hover{color:rgba(255,255,255,0.8);background:rgba(255,255,255,0.05);}
.sb-item.on{color:#fff;background:rgba(201,101,26,0.15);border-left-color:var(--accent);}
.sb-ico{width:30px;height:30px;border-radius:6px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
.sb-item.on .sb-ico{background:rgba(201,101,26,0.2);}
.sb-ct{margin-left:auto;background:rgba(201,101,26,0.2);color:var(--accent2);font-size:10px;font-weight:700;padding:2px 7px;border-radius:4px;font-family:var(--code);}

.sb-mode{margin:0 12px;padding:12px 14px;border-radius:6px;border:1px solid rgba(201,101,26,0.2);background:rgba(201,101,26,0.06);}
.sb-mode-dot{display:inline-block;width:6px;height:6px;background:var(--accent);border-radius:50%;margin-right:6px;animation:blink 1.5s infinite;}
.sb-live-dot{display:inline-block;width:6px;height:6px;background:#4ade80;border-radius:50%;margin-right:6px;animation:blink 1.5s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0.3;}}
.sb-mode-txt{font-size:11px;font-weight:700;color:var(--accent2);font-family:var(--code);}
.sb-mode-sub{font-size:10px;color:rgba(255,255,255,0.3);margin-top:3px;}

.sb-user{margin:10px 12px 20px;padding:10px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.07);display:flex;align-items:center;gap:10px;}
.sb-av{width:28px;height:28px;background:linear-gradient(135deg,var(--accent),var(--amber));border-radius:6px;display:flex;align-items:center;justify-content:center;font-family:var(--playfair);font-size:12px;font-weight:700;color:#fff;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:600;color:rgba(255,255,255,0.7);flex:1;}
.sb-logout{font-size:11px;color:rgba(255,100,100,0.7);text-decoration:none;font-weight:600;transition:color 0.2s;}
.sb-logout:hover{color:#f87171;}

/* ════════════════════════════════════════
   MAIN
════════════════════════════════════════ */
.main{margin-left:var(--side);flex:1;display:flex;flex-direction:column;min-height:100vh;}

/* ════════════════════════════════════════
   TOPBAR
════════════════════════════════════════ */
.topbar{
  height:60px;background:rgba(250,247,242,0.95);backdrop-filter:blur(10px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 32px;position:sticky;top:0;z-index:40;
}
.page-heading{font-family:var(--playfair);font-size:20px;font-weight:700;color:var(--head);}
.page-heading span{color:var(--accent);}
.topbar-pills{display:flex;align-items:center;gap:8px;}
.top-pill{
  font-size:11px;font-weight:600;padding:5px 12px;border-radius:4px;
  font-family:var(--code);letter-spacing:0.5px;
}
.pill-cf{background:var(--head);color:var(--accent2);}
.pill-sim{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;}
.pill-live{background:#dcfce7;color:var(--green2);border:1px solid #86efac;}
.pill-date{background:var(--sand);color:var(--muted);border:1px solid var(--border);}

/* ════════════════════════════════════════
   CONTENT
════════════════════════════════════════ */
.content{padding:28px 32px;flex:1;}

/* ════════════════════════════════════════
   ALERTS
════════════════════════════════════════ */
.alert{padding:12px 18px;border-radius:6px;margin-bottom:20px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:10px;animation:slideIn 0.3s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}
.alert.ok{background:#f0fdf4;border:1px solid #86efac;color:var(--green2);}
.alert.err{background:#fef2f2;border:1px solid #fca5a5;color:var(--red2);}
.alert.warn{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;}

/* ════════════════════════════════════════
   STAT CARDS
════════════════════════════════════════ */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:26px;}
.scard{
  background:#fff;border:1px solid var(--border);border-radius:10px;
  padding:20px;position:relative;overflow:hidden;
  transition:transform 0.2s,box-shadow 0.2s;cursor:default;
}
.scard:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(26,21,16,0.08);}
.scard::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
.scard.c1::before{background:linear-gradient(90deg,var(--accent),var(--accent2));}
.scard.c2::before{background:linear-gradient(90deg,var(--amber),#d97706);}
.scard.c3::before{background:linear-gradient(90deg,var(--green),#059669);}
.scard.c4::before{background:linear-gradient(90deg,var(--blue),var(--sky));}
.scard-ico{font-size:22px;margin-bottom:12px;}
.scard-val{font-family:var(--playfair);font-size:28px;font-weight:700;color:var(--head);margin-bottom:4px;}
.scard-label{font-size:12px;color:var(--muted);font-weight:500;}
.scard-sub{font-size:11px;color:var(--warm2);margin-top:2px;}

/* ════════════════════════════════════════
   SECTION
════════════════════════════════════════ */
.sec-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.sec-title{
  font-family:var(--playfair);font-size:16px;font-weight:700;color:var(--head);
  display:flex;align-items:center;gap:10px;
}
.sec-title::before{content:'';width:4px;height:18px;background:var(--accent);border-radius:2px;}

/* ════════════════════════════════════════
   CARDS
════════════════════════════════════════ */
.card{background:#fff;border:1px solid var(--border);border-radius:10px;padding:24px;margin-bottom:20px;}
.card-title{font-family:var(--playfair);font-size:15px;font-weight:700;color:var(--head);margin-bottom:18px;}

/* ════════════════════════════════════════
   FORM — UNIQUE FLOATING-LABEL STYLE
════════════════════════════════════════ */
.form-section{background:#fff;border:1px solid var(--border);border-radius:10px;padding:28px;margin-bottom:20px;}
.form-section-title{
  font-family:var(--playfair);font-size:18px;font-weight:700;color:var(--head);
  margin-bottom:22px;padding-bottom:14px;border-bottom:1px solid var(--warm);
  display:flex;align-items:center;gap:10px;
}
.form-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;}
.form-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}

/* Floating label input */
.f-field{position:relative;margin-bottom:4px;}
.f-field label{
  display:block;font-size:10px;font-weight:700;
  color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px;
}
.f-field input,.f-field select{
  width:100%;
  background:var(--cream);border:1.5px solid var(--border);border-radius:6px;
  padding:11px 14px;font-family:var(--jakarta);font-size:13px;color:var(--head);
  outline:none;transition:all 0.2s;appearance:none;
}
.f-field input:focus,.f-field select:focus{
  border-color:var(--accent);background:#fff;
  box-shadow:0 0 0 4px rgba(201,101,26,0.08);
}
.f-field input::placeholder{color:var(--border);}
.f-field select option{background:#fff;}
.f-hint{font-size:10px;color:var(--muted);margin-top:4px;}

.form-actions{display:flex;gap:10px;margin-top:22px;padding-top:18px;border-top:1px solid var(--warm);}

/* ════════════════════════════════════════
   BUTTONS
════════════════════════════════════════ */
.btn{padding:10px 20px;border-radius:6px;font-family:var(--jakarta);font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-dark{background:var(--head);color:#fff;}
.btn-dark:hover{background:var(--ink);}
.btn-accent{background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(201,101,26,0.25);}
.btn-accent:hover{background:var(--accent2);transform:translateY(-1px);box-shadow:0 4px 14px rgba(201,101,26,0.3);}
.btn-ghost{background:var(--sand);color:var(--body);border:1px solid var(--border);}
.btn-ghost:hover{background:var(--warm);color:var(--head);}
.btn-danger{background:#fef2f2;color:var(--red2);border:1px solid #fca5a5;}
.btn-danger:hover{background:#fee2e2;}
.btn-green{background:#f0fdf4;color:var(--green2);border:1px solid #86efac;}
.btn-green:hover{background:#dcfce7;}
.btn-amber{background:#fffbeb;color:#92400e;border:1px solid #fcd34d;}
.btn-sm{padding:6px 14px;font-size:12px;}
.btn-xs{padding:4px 10px;font-size:11px;}

/* ════════════════════════════════════════
   DEPT FILTERS
════════════════════════════════════════ */
.dept-bar{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;}
.dept-btn{
  padding:6px 14px;border-radius:4px;border:1px solid var(--border);
  background:var(--cream);color:var(--muted);font-size:12px;font-weight:600;
  cursor:pointer;text-decoration:none;transition:all 0.15s;font-family:var(--jakarta);
}
.dept-btn:hover{border-color:var(--accent);color:var(--accent);}
.dept-btn.on{background:var(--head);border-color:var(--head);color:#fff;}

/* ════════════════════════════════════════
   TABLE
════════════════════════════════════════ */
.tbl-box{background:#fff;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:20px;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--head);padding:11px 16px;text-align:left;font-size:9px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,0.5);font-weight:700;border-right:1px solid rgba(255,255,255,0.05);font-family:var(--code);}
thead th:last-child{border-right:none;}
tbody tr{border-bottom:1px solid var(--warm);transition:background 0.15s;}
tbody tr:nth-child(even){background:var(--cream);}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--sand);}
td{padding:12px 16px;font-size:13px;}
.emp-av{width:32px;height:32px;border-radius:6px;background:var(--head);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--accent);flex-shrink:0;font-family:var(--playfair);}
.emp-row{display:flex;align-items:center;gap:10px;}
.emp-nm{font-size:13px;font-weight:600;color:var(--head);}
.emp-em{font-size:11px;color:var(--muted);}
.dept-tag{font-size:10px;font-weight:700;padding:2px 8px;border-radius:3px;border:1px solid;font-family:var(--code);}
.dt-Engineering{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe;}
.dt-Design{background:#faf5ff;color:#7e22ce;border-color:#e9d5ff;}
.dt-Marketing{background:#fffbeb;color:#92400e;border-color:#fcd34d;}
.dt-Finance{background:#f0fdf4;color:var(--green2);border-color:#86efac;}
.dt-HR{background:#fdf2f8;color:#9d174d;border-color:#f9a8d4;}
.dt-Support{background:#ecfeff;color:#164e63;border-color:#a5f3fc;}
.dt-Operations{background:#fff7ed;color:#9a3412;border-color:#fdba74;}
.dt-Sales{background:#f0fdfa;color:#134e4a;border-color:#99f6e4;}
.mono{font-family:var(--code);font-size:11px;color:var(--muted);}
.salary-tag{font-family:var(--code);font-size:13px;font-weight:600;color:var(--green2);}
.upi-tag{font-size:10px;background:#faf5ff;color:#7e22ce;border:1px solid #e9d5ff;padding:2px 8px;border-radius:3px;font-family:var(--code);}
input[type="checkbox"]{width:14px;height:14px;accent-color:var(--accent);cursor:pointer;}

/* ════════════════════════════════════════
   PAYOUT CONTROLS
════════════════════════════════════════ */
.pay-ctrl{
  background:#fff;border:1px solid var(--border);border-radius:10px;
  padding:20px 24px;margin-bottom:18px;
}
.pay-ctrl-top{display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;margin-bottom:16px;}
.ctrl-group{flex:1;min-width:200px;}
.ctrl-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;font-family:var(--code);}
.mode-btns{display:flex;gap:6px;}
.mode-btn{
  padding:8px 16px;border-radius:5px;border:1.5px solid var(--border);
  background:var(--cream);color:var(--muted);font-size:12px;font-weight:600;cursor:pointer;
  transition:all 0.2s;font-family:var(--jakarta);
}
.mode-btn.on-bank{background:var(--head);border-color:var(--head);color:#fff;}
.mode-btn.on-upi{background:#7e22ce;border-color:#7e22ce;color:#fff;}
.mode-btn.on-auto{background:var(--accent);border-color:var(--accent);color:#fff;}
.pay-summary{
  background:var(--sand);border:1px solid var(--border);border-radius:8px;
  padding:16px 20px;display:flex;align-items:center;justify-content:space-between;
}
.pay-total-val{font-family:var(--playfair);font-size:26px;font-weight:700;color:var(--head);}
.pay-total-sub{font-size:12px;color:var(--muted);margin-top:2px;}
.btn-fire{
  background:var(--head);color:#fff;border:none;
  padding:14px 32px;border-radius:6px;
  font-family:var(--playfair);font-size:16px;font-weight:700;cursor:pointer;
  transition:all 0.25s;display:flex;align-items:center;gap:10px;
  position:relative;overflow:hidden;
  box-shadow:0 4px 14px rgba(26,21,16,0.2);
}
.btn-fire::before{content:'';position:absolute;left:-100%;top:0;bottom:0;width:100%;background:linear-gradient(90deg,transparent,rgba(201,101,26,0.4),transparent);transition:left 0.4s;}
.btn-fire:hover::before{left:100%;}
.btn-fire:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(26,21,16,0.25);}

/* CF FLOW STEPS */
.cf-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;}
.cf-step{
  background:var(--sand);border:1px solid var(--border);border-radius:8px;
  padding:14px;position:relative;text-align:center;
}
.cf-step::after{content:'›';position:absolute;right:-8px;top:50%;transform:translateY(-50%);color:var(--border);font-size:20px;font-weight:700;}
.cf-step:last-child::after{display:none;}
.cf-num{width:26px;height:26px;background:var(--head);color:var(--accent);border-radius:4px;font-family:var(--playfair);font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;margin:0 auto 8px;}
.cf-title{font-size:11px;font-weight:700;color:var(--head);margin-bottom:2px;}
.cf-sub{font-size:10px;color:var(--muted);font-family:var(--code);}

/* NOTICE */
.notice{border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:13px;display:flex;align-items:flex-start;gap:10px;}
.notice.sim{background:#fffbeb;border:1px solid #fcd34d;color:#92400e;}
.notice.live{background:#f0fdf4;border:1px solid #86efac;color:var(--green2);}

/* ════════════════════════════════════════
   RESULT CARDS
════════════════════════════════════════ */
.result-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--border);border-radius:10px;overflow:hidden;border:1px solid var(--border);margin-bottom:22px;}
.rs{background:#fff;padding:18px;text-align:center;}
.rs-val{font-family:var(--playfair);font-size:26px;font-weight:700;}
.rs-lbl{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-top:4px;font-family:var(--code);}

.results-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;}
.rc{background:#fff;border:1px solid var(--border);border-radius:10px;padding:18px;animation:rcIn 0.4s ease both;}
@keyframes rcIn{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}
.rc.success{border-top:3px solid var(--green);}
.rc.failed{border-top:3px solid var(--red);}
.rc-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;}
.rc-name{font-family:var(--playfair);font-size:14px;font-weight:700;color:var(--head);}
.rc-sub{font-size:11px;color:var(--muted);margin-top:2px;}
.rc-badge{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:3px;font-family:var(--code);}
.rc-badge.success{background:#dcfce7;color:var(--green2);}
.rc-badge.failed{background:#fee2e2;color:var(--red2);}
.rc-badge.upi{background:#f3e8ff;color:#7e22ce;}
.rc-badge.bank{background:#f0f9ff;color:#0369a1;}
.rc-rows{display:flex;flex-direction:column;gap:7px;}
.rc-row{display:flex;justify-content:space-between;font-size:12px;}
.rc-lbl{color:var(--muted);}
.rc-val{font-family:var(--code);font-size:11px;}
.rc-val.big{font-family:var(--playfair);font-size:15px;font-weight:700;color:var(--green2);}
.rc-val.ok{color:var(--green2);}
.rc-val.fail{color:var(--red2);}
.rc-val.amber{color:var(--amber);}
.rc-hr{height:1px;background:var(--warm);margin:4px 0;}

/* ════════════════════════════════════════
   HISTORY
════════════════════════════════════════ */
.hist-item{
  background:#fff;border:1px solid var(--border);border-radius:10px;
  padding:16px 22px;margin-bottom:12px;
  transition:border-color 0.2s,box-shadow 0.2s;
}
.hist-item:hover{border-color:var(--accent);box-shadow:0 4px 14px rgba(201,101,26,0.08);}
.hist-row{display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.hist-month{font-family:var(--playfair);font-size:16px;font-weight:700;color:var(--head);min-width:110px;}
.hist-date{font-size:11px;color:var(--muted);font-family:var(--code);}
.hist-stats{display:flex;gap:18px;}
.hist-stat{text-align:center;}
.hist-stat-val{font-family:var(--code);font-size:15px;font-weight:700;}
.hist-stat-lbl{font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;}
.hist-amt{font-family:var(--playfair);font-size:18px;font-weight:700;color:var(--accent);}
.hist-actions{display:flex;gap:6px;align-items:center;margin-left:auto;flex-wrap:wrap;}
.status-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.status-dot.ok{background:var(--green);}
.status-dot.warn{background:var(--amber);}

/* ════════════════════════════════════════
   DASHBOARD WIDGETS
════════════════════════════════════════ */
.widgets{display:grid;grid-template-columns:1.2fr 0.8fr;gap:16px;margin-bottom:20px;}
.dept-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--warm);}
.dept-row:last-child{border-bottom:none;}
.dept-name{font-size:12px;font-weight:600;color:var(--head);width:90px;flex-shrink:0;}
.dept-track{flex:1;height:6px;background:var(--warm);border-radius:3px;overflow:hidden;}
.dept-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--accent),var(--amber));transition:width 0.8s cubic-bezier(0.22,1,0.36,1);}
.dept-meta{font-size:11px;color:var(--muted);width:90px;text-align:right;font-family:var(--code);}
.recent-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--warm);}
.recent-row:last-child{border-bottom:none;}
.recent-m{font-size:13px;font-weight:600;color:var(--head);}
.recent-d{font-size:11px;color:var(--muted);font-family:var(--code);}
.recent-a{font-family:var(--playfair);font-size:14px;font-weight:700;color:var(--accent);}

/* EMPTY */
.empty{text-align:center;padding:52px;background:#fff;border:1px solid var(--border);border-radius:10px;}
.empty-ico{font-size:40px;margin-bottom:12px;opacity:0.5;}
.empty p{color:var(--muted);font-size:14px;line-height:1.7;}
.empty a{color:var(--accent);text-decoration:none;font-weight:600;}

@media(max-width:900px){
  .sidebar{width:54px;}
  .sb-name,.sb-ver,.sb-item span:not(.sb-ico),.sb-ct,.sb-label,.sb-mode-sub,.sb-mode-txt,.sb-uname,.sb-logout{display:none;}
  .sb-item{justify-content:center;padding:10px;}
  .sb-top{padding:14px 8px;}
  .main{margin-left:54px;}
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .form-grid{grid-template-columns:1fr 1fr;}
  .cf-steps{grid-template-columns:repeat(2,1fr);}
  .widgets{grid-template-columns:1fr;}
  .content{padding:16px;}
  .topbar{padding:0 16px;}
}
</style>
</head>
<body>

<!-- ═══════ SIDEBAR ═══════ -->
<aside class="sidebar">
  <div class="sb-top">
    <div class="sb-brand">
      <div class="sb-icon">💸</div>
      <div>
        <div class="sb-name">PayFlow Pro</div>
        <div class="sb-ver">v3 · Cashfree</div>
      </div>
    </div>
  </div>
  <div class="sb-nav">
    <div class="sb-label">Menu</div>
    <a href="?tab=dashboard" class="sb-item <?= $tab==='dashboard'?'on':'' ?>"><div class="sb-ico">📊</div><span>Dashboard</span></a>
    <a href="?tab=employees" class="sb-item <?= $tab==='employees'?'on':'' ?>"><div class="sb-ico">👥</div><span>Employees</span><span class="sb-ct"><?= count($allEmps) ?></span></a>
    <a href="?tab=payout"    class="sb-item <?= $tab==='payout'   ?'on':'' ?>"><div class="sb-ico">💸</div><span>Bulk Payout</span></a>
    <a href="?tab=history"   class="sb-item <?= $tab==='history'  ?'on':'' ?>"><div class="sb-ico">📋</div><span>History</span><span class="sb-ct"><?= $totalPay ?></span></a>
  </div>
  <div class="sb-mode">
    <?php if ($SIM): ?>
    <div><span class="sb-mode-dot"></span><span class="sb-mode-txt">SIMULATION</span></div>
    <div class="sb-mode-sub">Demo — no real transfers</div>
    <?php else: ?>
    <div><span class="sb-live-dot"></span><span class="sb-mode-txt" style="color:#4ade80">LIVE · <?= CF_ENV ?></span></div>
    <div class="sb-mode-sub">Real Cashfree transfers</div>
    <?php endif; ?>
  </div>
  <div class="sb-user">
    <div class="sb-av"><?= strtoupper(substr($_SESSION['admin_username']??'A',0,1)) ?></div>
    <div class="sb-uname"><?= htmlspecialchars($_SESSION['admin_username']??'Admin') ?></div>
    <a href="login.php?logout=1" class="sb-logout">Exit</a>
  </div>
</aside>

<!-- ═══════ MAIN ═══════ -->
<div class="main">
  <div class="topbar">
    <div class="page-heading">
      <?php $titles=['dashboard'=>'<span>Dashboard</span>','employees'=>'<span>Employees</span>','payout'=>'<span>Bulk</span> Payout','history'=>'Payout <span>History</span>'];
      echo $titles[$tab]??'PayFlow'; ?>
    </div>
    <div class="topbar-pills">
      <span class="top-pill pill-cf">⚡ Cashfree <?= CF_ENV ?></span>
      <?php if ($SIM): ?><span class="top-pill pill-sim">🧪 SIMULATION</span><?php else: ?><span class="top-pill pill-live">● LIVE</span><?php endif; ?>
      <span class="top-pill pill-date"><?= date('d M Y') ?></span>
    </div>
  </div>

  <div class="content">
    <?php if ($msg): ?><div class="alert <?= $msgT ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($tab==='dashboard'): ?>
    <!-- ════════ DASHBOARD ════════ -->
    <div class="stats-row">
      <div class="scard c1"><div class="scard-ico">👥</div><div class="scard-val"><?= count($allEmps) ?></div><div class="scard-label">Active Employees</div><div class="scard-sub"><?= $upiCount ?> UPI enabled</div></div>
      <div class="scard c2"><div class="scard-ico">₹</div><div class="scard-val">₹<?= number_format($totalSal/1000) ?>K</div><div class="scard-label">Monthly Salary Pool</div><div class="scard-sub">All departments</div></div>
      <div class="scard c3"><div class="scard-ico">✅</div><div class="scard-val"><?= $totalPay ?></div><div class="scard-label">Payouts Executed</div><div class="scard-sub">Via Cashfree</div></div>
      <div class="scard c4"><div class="scard-ico">💰</div><div class="scard-val">₹<?= number_format($totalDis/1000) ?>K</div><div class="scard-label">Total Disbursed</div><div class="scard-sub">All time</div></div>
    </div>
    <div class="widgets">
      <div class="card">
        <div class="card-title">📂 Department Breakdown</div>
        <?php $mx=max(array_column($deptStats,'s')+[1]);
        foreach($deptStats as $d=>$ds): ?>
        <div class="dept-row">
          <div class="dept-name"><?= $d ?></div>
          <div class="dept-track"><div class="dept-fill" style="width:<?= round($ds['s']/$mx*100) ?>%"></div></div>
          <div class="dept-meta"><?= $ds['c'] ?> · ₹<?= number_format($ds['s']/1000,0) ?>K</div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card">
        <div class="card-title">🕐 Recent Payouts</div>
        <?php if (empty($history)): ?>
        <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px;">No payouts yet. <a href="?tab=payout" style="color:var(--accent)">Start →</a></div>
        <?php else: foreach(array_slice($history,0,5) as $h): ?>
        <div class="recent-row">
          <div><div class="recent-m"><?= htmlspecialchars($h['payout_month']) ?></div><div class="recent-d"><?= date('d M · H:i',strtotime($h['created_at'])) ?></div></div>
          <div style="display:flex;align-items:center;gap:8px;"><div class="status-dot <?= $h['fail_count']>0?'warn':'ok' ?>"></div><div class="recent-a">₹<?= number_format($h['total_amount']) ?></div></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-title">⚡ Quick Actions</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="?tab=payout"   class="btn btn-dark">💸 Run Bulk Payout</a>
        <a href="?tab=employees" class="btn btn-accent">➕ Add Employee</a>
        <a href="?tab=history"  class="btn btn-ghost">📋 View History</a>
        <?php if (!empty($history)): ?>
        <a href="?action=export_xlsx&payout_id=<?= $history[0]['id'] ?>" class="btn btn-green btn-sm">📊 Download Last Excel</a>
        <a href="?action=export_pdf&payout_id=<?= $history[0]['id'] ?>" target="_blank" class="btn btn-amber btn-sm">🖨️ Print Last PDF</a>
        <?php endif; ?>
      </div>
    </div>

    <?php elseif ($tab==='employees'): ?>
    <!-- ════════ EMPLOYEES ════════ -->
    <div class="dept-bar">
      <a href="?tab=employees&dept=All" class="dept-btn <?= $deptF==='All'?'on':'' ?>">All (<?= count($allEmps) ?>)</a>
      <?php foreach($depts as $d): $dc=count(array_filter($allEmps,fn($e)=>$e['department']===$d));if($dc>0): ?>
      <a href="?tab=employees&dept=<?= urlencode($d) ?>" class="dept-btn <?= $deptF===$d?'on':'' ?>"><?= $d ?> (<?= $dc ?>)</a>
      <?php endif;endforeach; ?>
    </div>

    <!-- FORM -->
    <div class="form-section">
      <div class="form-section-title"><?= $editEmp?'✏️ Edit Employee':'➕ Add New Employee' ?></div>
      <form method="POST">
        <input type="hidden" name="action" value="<?= $editEmp?'edit_employee':'add_employee' ?>">
        <?php if ($editEmp): ?><input type="hidden" name="emp_id" value="<?= $editEmp['id'] ?>"><?php endif; ?>
        <div class="form-grid">
          <div class="f-field"><label>Full Name</label><input type="text" name="name" required placeholder="e.g. Ravi Kumar" value="<?= htmlspecialchars($editEmp['name']??'') ?>"></div>
          <div class="f-field"><label>Email Address</label><input type="email" name="email" required placeholder="ravi@company.com" value="<?= htmlspecialchars($editEmp['email']??'') ?>"></div>
          <div class="f-field"><label>Mobile Number</label><input type="text" name="mobile" required placeholder="9000000001" value="<?= htmlspecialchars($editEmp['mobile']??'') ?>"></div>
          <div class="f-field"><label>Department</label><select name="department" required><option value="">Select…</option><?php foreach($depts as $d): ?><option value="<?= $d ?>" <?= ($editEmp['department']??'')===$d?'selected':'' ?>><?= $d ?></option><?php endforeach; ?></select></div>
          <div class="f-field"><label>Bank Name</label><select name="bank_name" required><option value="">Select…</option><?php foreach($banks as $b): ?><option value="<?= $b ?>" <?= ($editEmp['bank_name']??'')===$b?'selected':'' ?>><?= $b ?></option><?php endforeach; ?></select></div>
          <div class="f-field"><label>Account Number</label><input type="text" name="account_number" required placeholder="1234567890" value="<?= htmlspecialchars($editEmp['account_number']??'') ?>"></div>
          <div class="f-field"><label>IFSC Code</label><input type="text" name="ifsc" required placeholder="HDFC0001234" value="<?= htmlspecialchars($editEmp['ifsc']??'') ?>" style="text-transform:uppercase"></div>
          <div class="f-field"><label>UPI ID <span style="font-size:9px;color:var(--muted)">(Optional)</span></label><input type="text" name="upi_id" placeholder="name@okaxis" value="<?= htmlspecialchars($editEmp['upi_id']??'') ?>"><div class="f-hint">Leave blank if no UPI</div></div>
        </div>
        <div class="f-field" style="max-width:200px;margin-top:16px;"><label>Monthly Salary (₹)</label><input type="number" name="salary" required placeholder="45000" value="<?= htmlspecialchars($editEmp['salary']??'') ?>"></div>
        <div class="form-actions">
          <button type="submit" class="btn btn-dark"><?= $editEmp?'💾 Update Employee':'➕ Add Employee' ?></button>
          <?php if ($editEmp): ?><a href="?tab=employees&dept=<?= urlencode($deptF) ?>" class="btn btn-ghost">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>

    <!-- TABLE -->
    <div class="sec-bar"><div class="sec-title">All Employees (<?= count($employees) ?>)</div></div>
    <?php if (empty($employees)): ?>
    <div class="empty"><div class="empty-ico">👥</div><p>No employees in this filter.</p></div>
    <?php else: ?>
    <div class="tbl-box">
      <table>
        <thead><tr><th>Employee</th><th>Dept</th><th>Bank</th><th>IFSC</th><th>Account</th><th>UPI</th><th>Salary</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($employees as $e): ?>
          <tr>
            <td><div class="emp-row"><div class="emp-av"><?= strtoupper(substr($e['name'],0,1)) ?></div><div><div class="emp-nm"><?= htmlspecialchars($e['name']) ?></div><div class="emp-em"><?= htmlspecialchars($e['email']) ?></div></div></div></td>
            <td><span class="dept-tag dt-<?= $e['department'] ?>"><?= $e['department'] ?></span></td>
            <td><span class="mono"><?= htmlspecialchars($e['bank_name']) ?></span></td>
            <td><span class="mono"><?= htmlspecialchars($e['ifsc']) ?></span></td>
            <td><span class="mono"><?= htmlspecialchars($e['account_number']) ?></span></td>
            <td><?= $e['upi_id']?'<span class="upi-tag">'.htmlspecialchars($e['upi_id']).'</span>':'<span style="color:var(--border)">—</span>' ?></td>
            <td><span class="salary-tag">₹<?= number_format($e['salary']) ?></span></td>
            <td><div style="display:flex;gap:5px;">
              <a href="?edit=<?= $e['id'] ?>&tab=employees&dept=<?= urlencode($deptF) ?>" class="btn btn-ghost btn-xs">✏️</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= htmlspecialchars($e['name']) ?>?')"><input type="hidden" name="action" value="delete_employee"><input type="hidden" name="emp_id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-danger btn-xs">🗑️</button></form>
            </div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($tab==='payout'): ?>
    <!-- ════════ BULK PAYOUT ════════ -->
    <div class="notice <?= $SIM?'sim':'live' ?>">
      <span><?= $SIM?'🧪':'✅' ?></span>
      <div><?= $SIM?'<strong>Simulation Mode</strong> — Realistic Cashfree responses. Set <code>$SIM = false</code> + add credentials to go live.':'<strong>Live Mode · Cashfree '.CF_ENV.'</strong> — Real transfers will be executed.' ?>
      </div>
    </div>
    <div class="cf-steps">
      <div class="cf-step"><div class="cf-num">1</div><div class="cf-title">Authenticate</div><div class="cf-sub">/authorize</div></div>
      <div class="cf-step"><div class="cf-num">2</div><div class="cf-title">Add Beneficiary</div><div class="cf-sub">/addBeneficiary</div></div>
      <div class="cf-step"><div class="cf-num">3</div><div class="cf-title">Request Transfer</div><div class="cf-sub">/requestTransfer</div></div>
      <div class="cf-step"><div class="cf-num">4</div><div class="cf-title">Get Status</div><div class="cf-sub">/getTransferStatus</div></div>
    </div>
    <?php if (empty($allEmps)): ?>
    <div class="empty"><div class="empty-ico">👥</div><p>No employees yet. <a href="?tab=employees">Add some →</a></p></div>
    <?php else: ?>
    <form method="POST" id="pForm">
      <input type="hidden" name="action" value="trigger_payout">
      <input type="hidden" name="dept_filter" id="dFilter" value="All">
      <div class="pay-ctrl">
        <div class="pay-ctrl-top">
          <div class="ctrl-group">
            <div class="ctrl-label">Payout Mode</div>
            <div class="mode-btns" id="mBtns">
              <button type="button" class="mode-btn on-bank" data-m="bank" onclick="setMode('bank')">🏦 Bank</button>
              <button type="button" class="mode-btn" data-m="upi" onclick="setMode('upi')">📱 UPI</button>
              <button type="button" class="mode-btn" data-m="auto" onclick="setMode('auto')">⚡ Auto</button>
            </div>
            <input type="hidden" name="payout_mode" id="pMode" value="bank">
          </div>
          <div class="ctrl-group">
            <div class="ctrl-label">Filter by Department</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <button type="button" class="dept-btn on" onclick="filterD('All',this)">All</button>
              <?php foreach($depts as $d):$dc=count(array_filter($allEmps,fn($e)=>$e['department']===$d));if($dc>0): ?>
              <button type="button" class="dept-btn" onclick="filterD('<?= $d ?>',this)"><?= $d ?></button>
              <?php endif;endforeach; ?>
            </div>
          </div>
        </div>
        <div class="pay-summary">
          <div><div class="pay-total-val" id="payTotal">₹<?= number_format($totalSal) ?></div><div class="pay-total-sub">Total for <span id="payCnt"><?= count($allEmps) ?></span> selected employees</div></div>
          <button type="submit" class="btn-fire">💸 Trigger Bulk Payout via Cashfree</button>
        </div>
      </div>
      <div class="tbl-box">
        <table>
          <thead><tr><th style="width:44px;text-align:center"><input type="checkbox" id="selAll" checked onchange="togAll(this)"></th><th>Employee</th><th>Dept</th><th>Bank · UPI</th><th>Salary</th></tr></thead>
          <tbody id="empTbody">
            <?php foreach ($allEmps as $e): ?>
            <tr data-dept="<?= $e['department'] ?>">
              <td style="text-align:center"><input type="checkbox" class="ecb" name="employee_ids[]" value="<?= $e['id'] ?>" data-sal="<?= $e['salary'] ?>" checked onchange="calcTot()"></td>
              <td><div class="emp-row"><div class="emp-av"><?= strtoupper(substr($e['name'],0,1)) ?></div><div><div class="emp-nm"><?= htmlspecialchars($e['name']) ?></div><div class="emp-em"><?= htmlspecialchars($e['email']) ?></div></div></div></td>
              <td><span class="dept-tag dt-<?= $e['department'] ?>"><?= $e['department'] ?></span></td>
              <td><div class="mono"><?= htmlspecialchars($e['bank_name']) ?></div><?php if($e['upi_id']): ?><div class="upi-tag" style="display:inline-block;margin-top:3px"><?= htmlspecialchars($e['upi_id']) ?></div><?php endif; ?></td>
              <td><span class="salary-tag">₹<?= number_format($e['salary']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
    <?php if (!empty($payoutResults)):
      $paid=array_sum(array_map(fn($r)=>$r['status']==='success'?$r['salary']:0,$payoutResults)); ?>
    <div class="sec-bar" style="margin-top:6px"><div class="sec-title">Payout Results</div></div>
    <div class="result-strip">
      <div class="rs"><div class="rs-val" style="color:var(--accent)"><?= count($payoutResults) ?></div><div class="rs-lbl">Processed</div></div>
      <div class="rs"><div class="rs-val" style="color:var(--green2)"><?= $okCount ?></div><div class="rs-lbl">Success</div></div>
      <div class="rs"><div class="rs-val" style="color:var(--red2)"><?= $failCount ?></div><div class="rs-lbl">Failed</div></div>
      <div class="rs"><div class="rs-val" style="color:var(--accent);font-size:18px;padding-top:6px">₹<?= number_format($paid) ?></div><div class="rs-lbl">Disbursed</div></div>
    </div>
    <div class="results-grid">
      <?php foreach ($payoutResults as $i=>$r): ?>
      <div class="rc <?= $r['status'] ?>" style="animation-delay:<?= $i*0.06 ?>s">
        <div class="rc-head">
          <div><div class="rc-name"><?= htmlspecialchars($r['employee']) ?></div><div class="rc-sub"><?= $r['dept'] ?> · <?= $r['bank'] ?></div></div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
            <span class="rc-badge <?= $r['status'] ?>"><?= $r['status']==='success'?'✓ SUCCESS':'✕ FAILED' ?></span>
            <span class="rc-badge <?= ($r['emode']??'bank')==='upi'?'upi':'bank' ?>"><?= strtoupper($r['emode']??'BANK') ?></span>
          </div>
        </div>
        <div class="rc-rows">
          <div class="rc-row"><span class="rc-lbl">Salary</span><span class="rc-val big">₹<?= number_format($r['salary']) ?></span></div>
          <div class="rc-hr"></div>
          <?php if ($r['status']==='success'): ?>
          <div class="rc-row"><span class="rc-lbl">Beneficiary ID</span><span class="rc-val ok"><?= htmlspecialchars($r['beneficiary_id']??'') ?></span></div>
          <div class="rc-row"><span class="rc-lbl">Transfer ID</span><span class="rc-val"><?= htmlspecialchars($r['transfer_id']??'') ?></span></div>
          <div class="rc-row"><span class="rc-lbl">UTR</span><span class="rc-val amber"><?= htmlspecialchars($r['utr']??'') ?></span></div>
          <div class="rc-row"><span class="rc-lbl">Time</span><span class="rc-val"><?= htmlspecialchars($r['timestamp']??'') ?></span></div>
          <div class="rc-hr"></div>
          <div class="rc-row"><span class="rc-lbl">Status</span><span class="rc-val ok">● <?= htmlspecialchars($r['transfer_status']??'') ?></span></div>
          <?php else: ?>
          <div class="rc-row"><span class="rc-lbl">Reason</span><span class="rc-val fail"><?= htmlspecialchars($r['message']??'') ?></span></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; endif; ?>

    <?php elseif ($tab==='history'): ?>
    <!-- ════════ HISTORY ════════ -->
    <div class="sec-bar"><div class="sec-title">Payout History (<?= count($history) ?>)</div></div>
    <?php if (empty($history)): ?>
    <div class="empty"><div class="empty-ico">📋</div><p>No payouts yet. <a href="?tab=payout">Run your first payout →</a></p></div>
    <?php else: foreach ($history as $h): ?>
    <div class="hist-item">
      <div class="hist-row">
        <div><div class="hist-month"><?= htmlspecialchars($h['payout_month']) ?></div><div class="hist-date"><?= date('d M Y · H:i',strtotime($h['created_at'])) ?> · <?= strtoupper($h['payout_mode']??'bank') ?></div></div>
        <div class="hist-stats">
          <div class="hist-stat"><div class="hist-stat-val" style="color:var(--accent)"><?= $h['total_employees'] ?></div><div class="hist-stat-lbl">Emp</div></div>
          <div class="hist-stat"><div class="hist-stat-val" style="color:var(--green2)"><?= $h['success_count'] ?></div><div class="hist-stat-lbl">OK</div></div>
          <div class="hist-stat"><div class="hist-stat-val" style="color:var(--red2)"><?= $h['fail_count'] ?></div><div class="hist-stat-lbl">Fail</div></div>
        </div>
        <div class="hist-amt">₹<?= number_format($h['total_amount']) ?></div>
        <div class="hist-actions">
          <span class="rc-badge success">✓ <?= strtoupper($h['status']) ?></span>
          <a href="?action=export_xlsx&payout_id=<?= $h['id'] ?>" class="btn btn-green btn-xs">📊 Excel</a>
          <a href="?action=export_pdf&payout_id=<?= $h['id'] ?>" target="_blank" class="btn btn-amber btn-xs">🖨️ PDF</a>
          <?php if ($h['fail_count']>0): ?>
          <form method="POST" style="margin:0;display:inline">
            <input type="hidden" name="action" value="retry_failed">
            <input type="hidden" name="payout_id" value="<?= $h['id'] ?>">
            <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Retry <?= $h['fail_count'] ?> failed?')">🔄 Retry <?= $h['fail_count'] ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach;
    // Retry results
    if (!empty($payoutResults)):
      $paid=array_sum(array_map(fn($r)=>$r['status']==='success'?$r['salary']:0,$payoutResults)); ?>
    <div class="sec-bar" style="margin-top:20px"><div class="sec-title">Retry Results</div></div>
    <div class="result-strip">
      <div class="rs"><div class="rs-val" style="color:var(--accent)"><?= count($payoutResults) ?></div><div class="rs-lbl">Retried</div></div>
      <div class="rs"><div class="rs-val" style="color:var(--green2)"><?= $okCount ?></div><div class="rs-lbl">Recovered</div></div>
      <div class="rs"><div class="rs-val" style="color:var(--red2)"><?= $failCount ?></div><div class="rs-lbl">Still Failed</div></div>
      <div class="rs"><div class="rs-val" style="color:var(--accent);font-size:18px;padding-top:6px">₹<?= number_format($paid) ?></div><div class="rs-lbl">Recovered</div></div>
    </div>
    <div class="results-grid">
      <?php foreach ($payoutResults as $i=>$r): ?>
      <div class="rc <?= $r['status'] ?>" style="animation-delay:<?= $i*0.06 ?>s">
        <div class="rc-head"><div><div class="rc-name"><?= htmlspecialchars($r['employee']) ?></div><div class="rc-sub"><?= $r['dept'] ?></div></div><span class="rc-badge <?= $r['status'] ?>"><?= $r['status']==='success'?'✓ RECOVERED':'✕ FAILED' ?></span></div>
        <div class="rc-rows">
          <div class="rc-row"><span class="rc-lbl">Salary</span><span class="rc-val big">₹<?= number_format($r['salary']) ?></span></div>
          <?php if ($r['status']==='success'): ?>
          <div class="rc-hr"></div>
          <div class="rc-row"><span class="rc-lbl">Transfer ID</span><span class="rc-val"><?= htmlspecialchars($r['transfer_id']??'') ?></span></div>
          <div class="rc-row"><span class="rc-lbl">UTR</span><span class="rc-val amber"><?= htmlspecialchars($r['utr']??'') ?></span></div>
          <?php else: ?><div class="rc-row"><span class="rc-lbl">Reason</span><span class="rc-val fail"><?= htmlspecialchars($r['message']??'') ?></span></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; endif; ?>

  <?php endif; ?>
  </div>
</div>

<script>
function setMode(m){
  document.querySelectorAll('#mBtns .mode-btn').forEach(b=>{b.className='mode-btn'+(b.dataset.m===m?' on-'+m:'');});
  document.getElementById('pMode').value=m;
}
function filterD(d,btn){
  document.getElementById('dFilter').value=d;
  document.querySelectorAll('.pay-ctrl .dept-btn').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on');
  document.querySelectorAll('#empTbody tr').forEach(r=>{
    const show=d==='All'||r.dataset.dept===d;
    r.style.display=show?'':'none';
    r.querySelector('.ecb').checked=show;
  });
  calcTot();
}
function togAll(cb){
  document.querySelectorAll('.ecb').forEach(c=>{if(c.closest('tr').style.display!=='none')c.checked=cb.checked;});
  calcTot();
}
function calcTot(){
  let t=0,n=0;
  document.querySelectorAll('.ecb:checked').forEach(c=>{if(c.closest('tr').style.display!=='none'){t+=parseFloat(c.dataset.sal)||0;n++;}});
  const tv=document.getElementById('payTotal');const tn=document.getElementById('payCnt');
  if(tv)tv.textContent='₹'+t.toLocaleString('en-IN');
  if(tn)tn.textContent=n;
}
document.querySelectorAll('.ecb').forEach(c=>c.addEventListener('change',calcTot));
window.addEventListener('load',()=>{document.querySelectorAll('.dept-fill').forEach(b=>{const w=b.style.width;b.style.width='0';setTimeout(()=>b.style.width=w,300);});});
</script>
</body>
</html>
