# PhilHealth AIM (Admin Inventory Management)

PhilHealth AIM is a premium utility backend designed to streamline inventory management through a strictly enforced **"Zero-Encoding" policy**. Data flows seamlessly from Budgeting to Procurement, Warehouse, and finally to Accountability, eliminating manual re-typing and reducing human error.

---

## 🚀 Tech Stack
- **Framework:** Laravel 12
- **Frontend:** TALL Stack (Tailwind CSS, Alpine.js, Livewire)
- **Database:** PostgreSQL
- **Development Environment:** Laravel Sail (Docker)
- **Build Tool:** Vite

---

## ✨ Core Features

### 🛡️ Zero-Encoding Policy
- **Budget → Procurement:** PRs link directly to COB items.
- **Procurement → Warehouse:** Deliveries (including partials) are tracked against active POs.
- **Warehouse → Accountability:** One-click issuance generates ICS/PAR documents automatically.

### 🤖 Google Workspace Automation
- **Gmail API:** Automatically email RFQ forms to accredited suppliers.
- **Sheets API:** Sync supplier bids directly to the dashboard and export monthly RSMI reports.
- **Docs API:** Inject database values into PhilHealth standard templates (IAR, ICS, PAR) using placeholder tags (e.g., `{{SERIAL_NO}}`).

### 📦 Inventory Intelligence
- **Automatic Status Tracking:** Lifecycle support for Stock, Issued, Repair, Disposed, and Returned items.
- **Dynamic Accountability:** Automatically determines `PAR` (items ≥ 50,000) or `ICS` type.
- **Real-time Ledger:** Instant Stock Card generation with running balance calculations.
- **Legal Liability Enforcement:** JO/Casual staff issuance requires a primary Permanent Staff end-user.

---

## 🛠️ Installation & Setup

### 1. Clone & Initialize
```bash
# Start the containers
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate
```

### 2. Google API Configuration
1. Follow the instructions in [google_api_setup.md](.gemini/antigravity/brain/1b7cfb11-904e-4be7-a023-8e1599dcf3c6/google_api_setup.md) to create your Service Account.
2. Place your `google-credentials.json` in the project root.
3. Add the following to your `.env`:
   ```env
   GOOGLE_APPLICATION_CREDENTIALS=/var/www/html/google-credentials.json
   ```

### 3. Build Assets
```bash
npm install
npm run dev
```

---

## 🗄️ Database Schema Overview
- `employees`: Staff registry (Permanent, Casual, JO).
- `cob_items`: Budget allocations and remaining balances.
- `procurement_folders`: PR and RFQ tracking.
- `purchase_orders`: PO tracking with supplier integration.
- `inventory_items`: Catalog of goods.
- `inventory_stocks`: Batched items from POs or Legacy CSV sources.
- `inventory_units`: Serialized individual items.
- `inventory_ledger`: Transaction logs (IN, OUT, RETURN, ADJUST).
- `property_accountabilities`: Chain of custody (ICS/PAR).

---

## 📜 Legacy Support
The system supports historical data from 2014–Present using a specialized `batch_source` column to distinguish legacy CSV seeders from active PO-driven stocks.

---

## ⚖️ License
This project is proprietary and intended for PhilHealth internal use.
