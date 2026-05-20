<?php
// ============================================================
//  db.php — Database Connection + Auto Setup
//  Just include this file anywhere you need DB connection
//  First time it runs → creates database + all tables automatically
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // XAMPP default
define('DB_PASS', '');           // XAMPP default (empty)
define('DB_NAME', 'payflow1_db');

function getDB() {
    // First connect without database to create it if not exists
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    if ($conn->connect_error) {
        die("❌ Database Connection Failed: " . $conn->connect_error);
    }

    // Create database if not exists (localhost)
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);

    // -----------------------------------------------
    // Table 1: employees
    // -----------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS employees (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(20) NOT NULL,
        email         VARCHAR(20) NOT NULL,
        mobile        VARCHAR(10)  NOT NULL,
        department    VARCHAR(20)  NOT NULL,
        bank_name     VARCHAR(20) NOT NULL,
        account_number VARCHAR(13) NOT NULL,
        ifsc          VARCHAR(13)  NOT NULL,
        salary        DECIMAL(10,2) NOT NULL,
        status        ENUM('active','inactive') DEFAULT 'active',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // -----------------------------------------------
    // Table 2: payouts (each bulk payout session)
    // -----------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS payouts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        payout_month  VARCHAR(12)  NOT NULL,
        total_amount  DECIMAL(10,2) NOT NULL,
        total_employees INT NOT NULL,
        success_count INT DEFAULT 0,
        fail_count    INT DEFAULT 0,
        status        ENUM('processing','completed') DEFAULT 'processing',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // -----------------------------------------------
    // Table 3: payout_items (individual results)
    // -----------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS payout_items (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        payout_id     INT NOT NULL,
        employee_id   INT NOT NULL,
        employee_name VARCHAR(20) NOT NULL,
        salary        DECIMAL(10,2) NOT NULL,
        status        ENUM('success','failed') NOT NULL,
        contact_id    VARCHAR(20) DEFAULT NULL,
        fund_account_id VARCHAR(20) DEFAULT NULL,
        payout_ref_id VARCHAR(20) DEFAULT NULL,
        utr_number    VARCHAR(20) DEFAULT NULL,
        failure_reason VARCHAR(255) DEFAULT NULL,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (payout_id) REFERENCES payouts(id)
    )");

    // -----------------------------------------------
    // Insert sample employees if table is empty
    // -----------------------------------------------
    $result = $conn->query("SELECT COUNT(*) as cnt FROM employees");
    $row = $result->fetch_assoc();
    if ($row['cnt'] == 0) {
        $conn->query("INSERT INTO employees (name, email, mobile, department, bank_name, account_number, ifsc, salary) VALUES
            ('Ravi Kumar',   'ravi@company.com',   '9000000001', 'Engineering', 'HDFC Bank',  '1234567890', 'HDFC0001234', 45000),
            ('Priya Sharma', 'priya@company.com',  '9000000002', 'Design',      'ICICI Bank', '9876543210', 'ICIC0002345', 52000),
            ('Amit Patel',   'amit@company.com',   '9000000003', 'Marketing',   'SBI',        '5555666677', 'SBIN0003456', 38000),
            ('Sneha Rao',    'sneha@company.com',  '9000000004', 'Finance',     'Axis Bank',  '1122334455', 'UTIB0004567', 61000),
            ('Kiran Nair',   'kiran@company.com',  '9000000005', 'HR',          'Kotak Bank', '9988776655', 'KKBK0005678', 47000),
            ('Deepa Menon',  'deepa@company.com',  '9000000006', 'Engineering', 'HDFC Bank',  '4433221100', 'HDFC0009876', 55000),
            ('Suresh Kumar', 'suresh@company.com', '9000000007', 'Support',     'PNB',        '7788990011', 'PUNB0001234', 42000)
        ");
    }


    // -----------------------------------------------
    // Table 4: admin_users
    // -----------------------------------------------
    $conn->query("CREATE TABLE IF NOT EXISTS admin_users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(20) NOT NULL UNIQUE,
        password   VARCHAR(75) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default admin if not exists
    $adminCheck = $conn->query("SELECT COUNT(*) as cnt FROM admin_users");
    $adminRow = $adminCheck->fetch_assoc();
    if ($adminRow['cnt'] == 0) {
        $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO admin_users (username, password) VALUES ('admin', '$defaultPass')");
    }

    return $conn;
}
?>
