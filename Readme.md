# ElevionSupply — PHP E-Commerce Platform

A full-featured wholesale electronics e-commerce platform built with plain PHP, HTML, CSS, and MySQL. No frameworks, no dependencies — just clean, production-ready PHP.

## Live Pages

| Page | Path |
|---|---|
| Home | `/` |
| Catalog | `/catalog.php` |
| Product Detail | `/product.php?id=1` |
| Cart | `/cart.php` |
| Checkout | `/checkout.php` |
| Order Tracking | `/track.php` |
| Login / Register | `/login.php` · `/register.php` |
| Account Dashboard | `/account/index.php` |
| Order History | `/account/orders.php` |
| Addresses | `/account/addresses.php` |
| Settings | `/account/settings.php` |
| Order Detail | `/orders/detail.php?id=1` |
| FAQ | `/help/faq.php` |
| Privacy / Shipping / Returns / Terms | `/help/` |
| Sitemap | `/sitemap.php` |

## Tech Stack

- **Backend:** PHP 8+ (no framework)
- **Database:** MySQL 8 via PDO
- **Frontend:** Plain HTML, CSS Modules, Vanilla JS
- **Auth:** PHP sessions + bcrypt password hashing
- **Fonts:** Montserrat + Poppins (Google Fonts)
- **Icons:** Font Awesome 6

## Project Structure

```
/
├── index.php               # Home page
├── catalog.php             # Product catalog with filters
├── product.php             # Product detail
├── cart.php                # Shopping cart
├── checkout.php            # Multi-step checkout
├── login.php               # Sign in
├── register.php            # Create account
├── track.php               # Order tracking
├── order-success.php       # Post-checkout confirmation
├── sitemap.php             # Site map
│
├── account/
│   ├── index.php           # Dashboard
│   ├── orders.php          # Order history
│   ├── addresses.php       # Address management (CRUD)
│   └── settings.php        # Profile & preferences
│
├── orders/
│   └── detail.php          # Order detail page
│
├── help/
│   ├── faq.php             # FAQ accordion
│   ├── privacy.php
│   ├── shipping.php
│   ├── returns.php
│   └── terms.php
│
├── api/
│   ├── auth/               # login, register, logout
│   ├── cart/               # add, update, remove
│   ├── checkout/           # process payment
│   └── orders/             # cancel
│
├── includes/
│   ├── config.php          # DB connection & constants
│   ├── auth.php            # Login, register, session helpers
│   ├── functions.php       # Cart, product, order, formatting helpers
│   ├── header.php          # Shared HTML header + nav
│   └── footer.php          # Shared HTML footer
│
├── assets/
│   ├── css/                # Per-page CSS modules
│   └── js/main.js          # Cart AJAX, mobile menu, notifications
│
└── database/
    └── schema.sql          # Full schema + seed data
```

## Features

- **Auth** — Register, login, logout, session management, bcrypt passwords
- **Catalog** — Search, category filters, price range, sort, pagination
- **Cart** — AJAX add/update/remove, session-based (works without login)
- **Checkout** — 3-step form (shipping → payment → review), mock card processing
- **Orders** — Full history, status filtering, cancellation, order detail
- **Tracking** — Track by order number with status progress bar
- **Account** — Dashboard, address book (CRUD), profile editing, notification prefs
- **Help Center** — FAQ accordion, 5 policy pages
- **Design** — Responsive (mobile/tablet/desktop), dark footer, sticky header, CSS variables

## Setup

### Requirements
- PHP 8.0+
- MySQL 8.0+
- Apache with `mod_rewrite` enabled (or XAMPP/WAMP/Laragon)

### Installation

1. **Clone the repo**
   ```bash
   git clone https://github.com/AnikethGit/Elevionsupply.git
   cd Elevionsupply
   ```

2. **Create the database**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

3. **Configure DB connection**

   Edit `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'elevionsupply');
   ```

4. **Point your web server** to the project root and visit `http://localhost`

### XAMPP / Laragon (Windows)

Place the project folder in `htdocs/` (XAMPP) or `www/` (Laragon), then visit:
```
http://localhost/Elevionsupply
```

## Test Credentials

**Admin account (pre-seeded):**
- Email: `admin@elevionsupply.com`
- Password: `password`

**Test payment cards:**
- ✅ Success: `4111 1111 1111 1111`
- ❌ Decline: `4000 0000 0000 0002`

## Design System

| Token | Value |
|---|---|
| Primary | `#16163F` (navy) |
| Accent | `#56CFE1` (teal) |
| Gold | `#E8B84B` |
| Heading Font | Montserrat |
| Body Font | Poppins |

## License

MIT
