<?php
// ============================================================
//  db.php — PayFlow v3 Database
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'payflow_v3_db');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) die("❌ DB Failed: " . $conn->connect_error);
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);

    $conn->query("CREATE TABLE IF NOT EXISTS employees (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(100) NOT NULL,
        email          VARCHAR(100) NOT NULL,
        mobile         VARCHAR(15)  NOT NULL,
        department     VARCHAR(50)  NOT NULL,
        bank_name      VARCHAR(100) NOT NULL,
        account_number VARCHAR(20)  NOT NULL,
        ifsc           VARCHAR(20)  NOT NULL,
        upi_id         VARCHAR(100) DEFAULT NULL,
        salary         DECIMAL(10,2) NOT NULL,
        status         ENUM('active','inactive') DEFAULT 'active',
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS payouts (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        payout_month    VARCHAR(20)  NOT NULL,
        total_amount    DECIMAL(10,2) NOT NULL,
        total_employees INT NOT NULL,
        success_count   INT DEFAULT 0,
        fail_count      INT DEFAULT 0,
        department_filter VARCHAR(50) DEFAULT 'All',
        payout_mode     ENUM('bank','upi','auto') DEFAULT 'bank',
        status          ENUM('processing','completed') DEFAULT 'processing',
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS payout_items (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        payout_id       INT NOT NULL,
        employee_id     INT NOT NULL,
        employee_name   VARCHAR(100) NOT NULL,
        department      VARCHAR(50)  DEFAULT '',
        salary          DECIMAL(10,2) NOT NULL,
        payout_mode     ENUM('bank','upi') DEFAULT 'bank',
        status          ENUM('success','failed') NOT NULL,
        beneficiary_id  VARCHAR(100) DEFAULT NULL,
        transfer_id     VARCHAR(100) DEFAULT NULL,
        utr_number      VARCHAR(100) DEFAULT NULL,
        failure_reason  VARCHAR(255) DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payout_id) REFERENCES payouts(id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(50) NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Sample data
    $r = $conn->query("SELECT COUNT(*) as c FROM employees")->fetch_assoc();
    if ($r['c'] == 0) {
        $conn->query("INSERT INTO employees (name,email,mobile,department,bank_name,account_number,ifsc,upi_id,salary) VALUES
            ('Ravi Kumar',   'ravi@company.com',   '9000000001','Engineering','HDFC Bank',  '1234567890','HDFC0001234','ravi@okaxis',  45000),
            ('Priya Sharma', 'priya@company.com',  '9000000002','Design',     'ICICI Bank', '9876543210','ICIC0002345','priya@oksbi',  52000),
            ('Amit Patel',   'amit@company.com',   '9000000003','Marketing',  'SBI',        '5555666677','SBIN0003456',NULL,           38000),
            ('Sneha Rao',    'sneha@company.com',  '9000000004','Finance',    'Axis Bank',  '1122334455','UTIB0004567','sneha@okicici',61000),
            ('Kiran Nair',   'kiran@company.com',  '9000000005','HR',         'Kotak Bank', '9988776655','KKBK0005678',NULL,           47000),
            ('Deepa Menon',  'deepa@company.com',  '9000000006','Engineering','HDFC Bank',  '4433221100','HDFC0009876','deepa@okaxis', 55000),
            ('Suresh Kumar', 'suresh@company.com', '9000000007','Support',    'PNB',        '7788990011','PUNB0001234',NULL,           42000),
            ('Nisha Verma',  'nisha@company.com',  '9000000008','Operations', 'Yes Bank',   '6677889900','YESB0001234','nisha@ybl',    49000),
            ('Arjun Singh',  'arjun@company.com',  '9000000009','Sales',      'Canara Bank','3344556677','CNRB0001234','arjun@okaxis', 44000),
            ('Meera Joshi',  'meera@company.com',  '9000000010','Finance',    'HDFC Bank',  '2233445566','HDFC0005678','meera@okhdfcbank',58000)
        ");
    }

    $ar = $conn->query("SELECT COUNT(*) as c FROM admin_users")->fetch_assoc();
    if ($ar['c'] == 0) {
        $p = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admin_users (username,password) VALUES ('admin','$p')");
    }
    return $conn;
}
?>
