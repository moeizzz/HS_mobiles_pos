# HS Mobiles - Business POS

> **FOR LIVE DEMOS CONTACT EMAIL:** [abdulmoeiz977@gmail.com](mailto:abdulmoeiz977@gmail.com)

A PHP-based point-of-sale / business management system for direct sales (no installments). Used for tracking customers, khata (credit) sales, payments, inventory, and financial books.

**HS Mobiles** | Smartphones & Accessories | Cell: 03268711118

## Features

- **Customer Management** - Registration, CNIC tracking, khata/credit balances, full history, ledger print
- **Sales & Billing** - Direct sale / khata invoicing with product/item tracking, cash received vs balance due
- **Credit (Khata) Sales** - Record full-credit, partial, or fully paid sales with running balances
- **Payment Collection** - Record cash/card/bank payments against invoices with receipts
- **Inventory Management** - Products (Mobile, General), brands, categories, suppliers, purchases, per-unit IMEI 1 & 2 tracking with PTA status, condition, battery health and warranty
- **Cash Book** - Daily cash position with opening/closing balances, inflow/outflow tracking
- **Bank Book** - Multi-account bank transaction management with cheque tracking
- **General Ledger** - Party-based ledger entries
- **Expense Management** - Categories and expense recording
- **Reports** - Sales report, closing report with customer-wise balances

## Product Types

- **Mobile** - IMEI 1 & 2, PTA status, condition (Box Pack / Open Box / Used / Kit), storage, RAM, color, battery health, warranty tracking
- **General** - mobile accessories & other items (quantity-based stock)

## Tech Stack

- **Backend:** PHP 7.4+ (native, no framework)
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** Bootstrap 4.6, jQuery, Font Awesome 5
- **Date Picker:** Flatpickr
- **Print:** Dedicated print-optimized layouts

## Installation

1. Clone this repository into your web server directory (e.g., `C:\xampp\htdocs`):
   ```bash
   git clone https://github.com/moeizzz/HS_mobiles_pos.git
   ```

2. Create a MySQL database named `hs_mobile`.

3. Import the database SQL (choose one option):
   - **Option A — Full database with demo data & default admin login (`hs_mobile.sql`):**
     ```bash
     mysql -u root hs_mobile < hs_mobile.sql
     ```
   - **Option B — Fresh empty database (schema + default settings & dropdown options only, no users/transactions):**
     ```bash
     mysql -u root hs_mobile < database/hs_mobile_schema.sql
     mysql -u root hs_mobile < database/hs_mobile_seed.sql
     ```
     *(When using Option B, create your admin user in the `users` table.)*

4. Configure database connection in `config/db.php`:
   ```php
   $host = 'localhost';
   $username = 'root';
   $password = '';
   $db_mode = 'client'; // 'client' for production, 'test' for development
   ```

5. Access the application at `http://localhost/HS_mobiles_pos`

## Usage

### Login
When imported via `hs_mobile.sql`, login at `login.php` using the seeded admin account (`admin` / `admin123`). For fresh installs using `database/hs_mobile_schema.sql` + `database/hs_mobile_seed.sql`, insert an initial user into the `users` table first.

### Workflow
1. **Register customers** under Customer Management (optional for walk-in sales)
2. **Add products** under Inventory (choose Mobile / General)
3. **Make a sale** - enter items, received amount (0 = full khata), payment method
4. **Collect payments** on khata invoices from the customer's Credit Received tab
5. **Track finances** via Cash Book, Bank Book, and reports

## Directory Structure

```
├── assets/              # CSS, JS
├── config/              # Database configuration
├── includes/            # Header, footer, auth, helper functions
├── modules/
│   ├── bankbook/        # Bank account & transaction management
│   ├── cashbook/        # Daily cash book
│   ├── customers/       # Customer CRUD, view (khata/ledger), history
│   ├── expenses/        # Expense categories & entries
│   ├── general/         # General ledger
│   ├── inventory/       # Products, purchases, suppliers
│   ├── payments/        # Payment receipt
│   ├── reports/         # Sales report, closing report
│   └── sales/           # New sale, invoices
├── database/
│   ├── hs_mobile_schema.sql  # Full database schema (tables only)
│   └── hs_mobile_seed.sql    # Default settings, dropdowns, brands & categories
└── hs_mobile.sql             # Full database dump (schema + demo data + admin user)
```

## Database

Key tables:
- `customers`, `sales`, `sale_items`, `sale_returns`
- `payments`, `products`, `suppliers`, `purchases`, `product_serials`
- `cash_book_daily`, `cash_book`, `bank_accounts`, `bank_transactions`
- `chart_of_accounts`, `journal_entries`, `general_parties`, `general_transactions`

Sales track `paid_amount` / `due_amount` with payment status `paid` / `partial` / `pending` (no installment schedules or interest).
