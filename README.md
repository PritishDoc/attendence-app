# Attendify — Smart Attendance Management Platform

A multi-tenant, SaaS attendance management system with GPS verification, real-time tracking capabilities, and role-based dashboards.

## 🚀 Features

- **Multi-Company Support** — Register companies, manage subscriptions
- **Role-Based Access** — Super Admin, Company Admin, Employee
- **GPS-Verified Attendance** — Office radius validation
- **Field Attendance** — Track field employees with GPS
- **Smart Reports** — Monthly reports with CSV export
- **Department Management** — Organize employees by department
- **Real-Time Dashboard** — Live attendance stats and charts
- **Premium Dark UI** — Modern glassmorphism design

## 📦 Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP 8+ |
| Database | MySQL |
| Real-Time | Node.js + Socket.io (Phase 2) |
| Maps | Leaflet.js + OpenStreetMap |

## 🛠️ Setup

### Prerequisites
- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx with mod_rewrite
- Node.js 18+ (for Phase 2)

### Installation

1. **Clone the repository**
```bash
git clone <repo-url>
cd Attendance-Tracker
```

2. **Create the database**
```bash
mysql -u root -p < database/schema.sql
```

3. **Configure database connection**
   Edit `api/config/database.php` with your MySQL credentials.

4. **Configure Apache/Nginx**
   Point document root to the `public/` directory.
   Ensure `.htaccess` or equivalent routes `/api/*` to `api/index.php`.

5. **Access the application**
   - Landing page: `http://localhost/`
   - Login: `http://localhost/login.html`

### Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@attendify.com | Admin@123 |
| Company Admin | admin@demo.com | Admin@123 |
| Employee | priya@demo.com | Admin@123 |

## 📁 Project Structure

```
Attendance-Tracker/
├── public/               # Frontend
│   ├── index.html        # Landing page
│   ├── login.html        # Login
│   ├── register.html     # Registration
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript
│   ├── admin/            # Super Admin pages
│   ├── company/          # Company Admin pages
│   └── employee/         # Employee pages
├── api/                  # PHP Backend
│   ├── index.php         # Router
│   ├── config/           # Database & constants
│   ├── controllers/      # Route handlers
│   ├── models/           # Data models
│   ├── middleware/        # Auth & CORS
│   └── helpers/          # JWT, validation, response
├── database/
│   └── schema.sql        # MySQL schema + seeds
└── socket-server/        # Node.js (Phase 2)
```

## 🗺️ Roadmap

- [x] Phase 1: Authentication, Employee CRUD, Attendance, Dashboards
- [ ] Phase 2: Real-time tracking with Socket.io
- [ ] Phase 3: Subscription & payment integration
- [ ] Phase 4: Face verification, PWA, notifications
