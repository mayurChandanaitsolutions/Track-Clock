# PayFlow Pro v3 — Cashfree Payouts
### Warm Amber · Editorial Design · Excel Export · UPI · Department Filter

---

## 📁 Files
```
payflow_v3/
├── index.php          Main dashboard
├── login.php          Admin login (split-screen editorial design)
├── db.php             Database + auto-setup
├── libs/
│   └── XlsxWriter.php Pure PHP Excel writer (no Composer needed)
└── README.md
```

---

## 🚀 XAMPP Setup

1. Copy `payflow_v3/` → `C:\xampp\htdocs\payflow_v3\`
2. Start **Apache + MySQL**
3. Visit: `http://localhost/payflow_v3/`
4. DB `payflow_v3_db` auto-created · 10 sample employees added
5. Login: **admin / admin123**

---

## 🎨 Design Theme (v3)

| Element | Details |
|---------|---------|
| Background | Warm cream `#faf7f2` / sand tones |
| Accent | Amber-orange `#c9651a` |
| Typography | Playfair Display (serif) + Plus Jakarta Sans |
| Login | Split-screen editorial layout |
| Dashboard | Light card-based, department bar charts |
| Tables | Striped cream/warm rows, dark headers |
| Forms | Unique floating label style with orange focus rings |
| Buttons | Dark, accent-orange, ghost variants |

---

## 📊 Excel Export (New in v3!)

Every payout in History has a **📊 Excel** button.

The exported `.xlsx` file has **4 sheets**:
- **Summary** — Payout stats (total, success, failed, amount, rate)
- **All Transactions** — Full list with status color coding
- **Successful** — Only successful transfers with UTR numbers
- **Failed** — Failed transactions with reasons

Uses `libs/XlsxWriter.php` — **pure PHP, zero dependencies**, works on any XAMPP.

---

## 🔑 Go LIVE with Cashfree

Edit top of `index.php`:
```php
$SIM = false;
define('CF_ID',     'YOUR_CLIENT_ID');
define('CF_SECRET', 'YOUR_CLIENT_SECRET');
define('CF_ENV',    'TEST'); // or 'PROD'
```

Get credentials: https://merchant.cashfree.com/merchants/pg-settings/apis

---

## ⚡ Cashfree API Flow
1. `POST /authorize` → Bearer Token
2. `POST /addBeneficiary` → Register bank or UPI
3. `POST /requestTransfer` → Send salary
4. `GET /getTransferStatus` → Get UTR + status

---

## ✨ All Features

| Feature | Status |
|---------|--------|
| Cashfree Bank Transfer | ✅ |
| Cashfree UPI Payout | ✅ |
| Auto mode (UPI if available) | ✅ |
| Department-wise filter | ✅ |
| Export to Excel (.xlsx) 4 sheets | ✅ NEW |
| Export to PDF (print-ready) | ✅ |
| Retry failed transactions | ✅ |
| Dashboard with dept charts | ✅ |
| Simulation mode (95% success) | ✅ |
| CSRF protection | ✅ |
| Change username/password | ✅ |

---

## Default Login
- **Username:** `admin`
- **Password:** `admin123`
