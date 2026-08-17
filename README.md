<div align="center">

# YangHomestay

### Ở đúng gu, sống đúng chất Hà Nội.

**A full-stack homestay booking platform for Hanoi with curated stays, personalized YangMatch recommendations, booking management and server-side RBAC.**

[![Live Demo](https://img.shields.io/badge/Live%20Demo-yanghomestay.helioho.st-1f2923?style=for-the-badge)](https://yanghomestay.helioho.st/)
[![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?style=flat-square&logo=php&logoColor=white)](#tech-stack)
[![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?style=flat-square&logo=javascript&logoColor=111)](#tech-stack)
[![Supabase](https://img.shields.io/badge/Supabase-PostgreSQL-3ECF8E?style=flat-square&logo=supabase&logoColor=white)](#tech-stack)
[![RBAC](https://img.shields.io/badge/Security-Server--side%20RBAC-355E3B?style=flat-square)](#security)

</div>

![YangHomestay Hanoi boutique stay](assets/images/homestays/hero-hanoi-boutique.jpg)

## Overview

**YangHomestay** is a Hanoi-focused homestay booking project built as a production-oriented final-year/portfolio system rather than a UI-only demo.

The platform combines a boutique local-travel experience with real booking business logic: users can discover stays by district and travel style, search by dates and guest count, inspect detailed unit data, receive personalized recommendations through **YangMatch**, create booking requests, manage reservations, and interact with role-specific internal portals.

**Live production:** https://yanghomestay.helioho.st/

## Problem

Typical student booking projects often stop at static room cards and CRUD screens. That makes it difficult to demonstrate real product thinking, data consistency, booking rules, authorization and production readiness.

YangHomestay was designed around a stricter question:

> **What would a small Hanoi-focused Airbnb/Booking-style product need in order to feel believable to a real guest and defensible in a software engineering presentation?**

## Solution

YangHomestay focuses on four areas:

- **Trustworthy accommodation data** — capacity, beds, gallery, amenities, district and pricing are kept consistent.
- **End-to-end booking logic** — date validation, availability, overlap protection, pricing snapshots and booking state transitions.
- **Personalized discovery** — YangMatch ranks suitable stays from user preferences instead of returning random recommendations.
- **Server-side authorization** — Customer, Staff, Manager and Super Admin responsibilities are separated with PHP Session + backend RBAC.

## Key Features

### Customer experience

- Search homestays by **district, check-in, check-out and guest count**.
- Filter and sort available stays.
- Detailed unit pages with gallery, capacity, beds, bedrooms, bathrooms, amenities, area, reviews and transparent pricing.
- Favorite stays and customer account flow.
- Booking request flow with validation and availability checks.
- Booking history / reservation management where supported.
- Hanoi experiences and local lifestyle content.
- Responsive boutique-style interface designed around the brand line **“Ở đúng gu, sống đúng chất Hà Nội.”**

### YangMatch recommendation engine

YangMatch is the signature recommendation feature of the project. User inputs affect the actual result set and ranking.

```text
User preferences
      ↓
Hard constraints
(capacity / budget)
      ↓
Eligible homestays
      ↓
Weighted scoring
(companion / style / amenities / location / purpose)
      ↓
Ranking
      ↓
Top matches
```

The scoring flow is deterministic and kept readable so the recommendation logic can be explained clearly during a project defense.

### Internal portals

| Portal | Role | Main responsibility |
|---|---|---|
| Customer | `customer` | Search, booking, favorites, reviews |
| Staff / Receptionist | `staff` | Reservation operations, check-in/check-out |
| Manager | `manager` | Property, unit, booking, pricing and reporting operations |
| Super Admin | `super_admin` | System-wide administration, accounts, roles and audit access |

Sensitive authorization is enforced by the backend. Hiding a menu item in JavaScript is **not** treated as a security boundary.

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Backend | PHP 8+, PHP Session, cURL/HTTPS |
| Database | Supabase PostgreSQL |
| Data access | Supabase REST API + PostgreSQL RPC |
| Authentication | `password_hash()` / `password_verify()` + PHP Session |
| Authorization | Server-side role-based access control (RBAC) |
| Hosting target | Apache/PHP shared hosting, HelioHost/Plesk |

The project intentionally avoids unnecessary runtime complexity: **no Node server, React, Next.js, Docker, Redis or Composer is required to run the application.**

## Architecture

```text
┌──────────────────────────────────────────┐
│ Browser                                  │
│ Customer / Staff / Manager / Super Admin │
└───────────────────┬──────────────────────┘
                    │ HTTPS
                    ▼
┌──────────────────────────────────────────┐
│ HTML + CSS + Vanilla JavaScript           │
└───────────────────┬──────────────────────┘
                    │ Fetch / Forms
                    ▼
┌──────────────────────────────────────────┐
│ PHP Backend                               │
│ Session · CSRF · RBAC · Business Services │
└───────────────────┬──────────────────────┘
                    │ HTTPS :443
                    ▼
┌──────────────────────────────────────────┐
│ Supabase PostgreSQL                       │
│ Tables · Views · Indexes · RPC            │
└──────────────────────────────────────────┘
```

### Portal entry points

| Portal | URL |
|---|---|
| Customer | `/customer/` |
| Super Admin | `/admin/login.html` |
| Manager | `/manager/login.html` |
| Staff / Receptionist | `/staff/login.html` |

Internal dashboard HTML is protected through Apache rewrite + `backend/portal.php`, so a user cannot gain access simply by typing a dashboard URL directly.

## Booking Flow

```text
Homepage
   ↓
Search
   ↓
Listing + Filters
   ↓
Unit Detail
   ↓
Dates + Guests
   ↓
Availability Check
   ↓
Pricing Snapshot
   ↓
Authenticated Booking Request
   ↓
Booking Status / Management
```

Core booking rules include:

- check-in inclusive / check-out exclusive date convention;
- guest capacity validation;
- server-side availability checks;
- overlapping-date protection;
- atomic booking creation through `create_booking_atomic`;
- advisory-lock based conflict protection;
- price/night, nights, discounts and total snapshots;
- controlled booking status transitions.

The backend does not trust a total price supplied by the browser.

## Data Model & Consistency

The project uses a normalized PostgreSQL schema covering the main booking domain:

- customers and internal accounts;
- roles and authorization;
- properties and units;
- unit images and amenities;
- bookings and booking status;
- promotions / pricing rules;
- reviews;
- audit and system data where applicable.

The current curated dataset contains **16 accommodation units** with normalized capacity/bed logic and local gallery mappings. Database migrations are stored under [`database/supabase/`](database/supabase/).

## Security

Security controls implemented in the project include:

- server-side RBAC through `backend/lib/InternalAccess.php`;
- server-side portal guard through `backend/portal.php` + `.htaccess`;
- PHP Session validation and session regeneration after login;
- CSRF protection for sensitive state-changing requests;
- bcrypt-compatible password hashing via PHP password APIs;
- backend input validation;
- Supabase service credentials kept **server-side only**;
- private config excluded through `.gitignore`;
- direct access to private config/log/diagnostic resources blocked by Apache rules;
- restricted PostgreSQL RPC permissions for privileged operations;
- user-facing API errors that avoid exposing raw database internals.

> `backend/config.supabase.local.php` is intentionally **not committed**. Copy `backend/config.supabase.example.php` and provide the private server credential locally or through environment configuration.

## Project Structure

```text
YangHomestay/
├── admin/                         # Super Admin portal
├── assets/                        # Shared styles, scripts, images, favicon
├── backend/
│   ├── api.php                    # Internal API router
│   ├── customer-api.php           # Customer API router
│   ├── portal.php                 # Server-side portal guard
│   ├── lib/                       # Access + infrastructure helpers
│   ├── services/                  # Business services
│   └── tests/                     # Pricing / availability / RBAC tests
├── customer/                      # Public/customer booking experience
├── database/
│   └── supabase/                  # Schema, seed and migrations
├── manager/                       # Manager portal
├── staff/                         # Staff / receptionist portal
├── tools/                         # Development/data maintenance utilities
├── .gitignore
├── .htaccess
├── README.md
├── CHANGELOG-FINAL.md
├── QA-REPORT.md
├── DEPLOYMENT.md
└── IMAGE_SOURCES.md
```

## Local Setup

### Requirements

- Apache + PHP 8+
- PHP cURL extension
- Apache `mod_rewrite`
- Supabase project / PostgreSQL database

### XAMPP example

1. Clone the repository into Apache document root, for example:

```text
C:\xampp\htdocs\YangHomestay
```

2. Copy the private config template:

```text
backend/config.supabase.example.php
→ backend/config.supabase.local.php
```

3. Fill in the server-side Supabase URL and private credential.
4. Make sure Apache allows `.htaccess` (`AllowOverride All`).
5. Open:

```text
http://localhost/YangHomestay/customer/
http://localhost/YangHomestay/admin/login.html
http://localhost/YangHomestay/manager/login.html
http://localhost/YangHomestay/staff/login.html
```

## Database Migrations

For a fresh Supabase database, migrations live in [`database/supabase/`](database/supabase/) and should be applied in numerical order.

Important production rule:

> **Do not re-run the destructive development seed on an existing production database.** Apply only the missing migrations in order.

See [`DEPLOYMENT.md`](DEPLOYMENT.md) for the current production sequence and HelioHost instructions.

## Testing

Backend regression scripts:

```bash
php backend/tests/test-pricing.php
php backend/tests/test-availability.php
php backend/tests/test-rbac.php
```

Useful syntax checks:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check admin/js/admin-api.js
node --check admin/js/admin-login.js
node --check admin/js/admin.js
node --check staff/js/staff.js
```

For the latest audited test scope, limitations and regression results, see **[`QA-REPORT.md`](QA-REPORT.md)**.

## Documentation

| Document | Purpose |
|---|---|
| [`CHANGELOG-FINAL.md`](CHANGELOG-FINAL.md) | Important fixes and production hardening |
| [`QA-REPORT.md`](QA-REPORT.md) | Test coverage, results and known limitations |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | HelioHost / server deployment guide |
| [`IMAGE_SOURCES.md`](IMAGE_SOURCES.md) | Curated image source traceability |
| [`database/supabase/ERD.md`](database/supabase/ERD.md) | Database relationship documentation |

## Image & Content Policy

Accommodation and experience images used for the portfolio/demo dataset are stored locally in the repository rather than hotlinked at runtime. Source traceability is documented in [`IMAGE_SOURCES.md`](IMAGE_SOURCES.md).

The project does **not** claim that the curated demo properties are real commercial inventory.

## Known Limitations

- No commercial payment gateway is integrated yet.
- The accommodation dataset is curated for portfolio/demo use rather than connected to a live property-management system.
- Production behavior depends on correct server-side Supabase configuration.
- Some infrastructure-level visual regression and end-to-end checks still require a real browser/server environment.

These limitations are documented intentionally instead of being hidden behind fake production claims.

## Roadmap

- [ ] Integrate a real payment sandbox/provider.
- [ ] Add automated browser E2E tests for the complete booking journey.
- [ ] Add CI for PHP/JavaScript syntax and regression checks.
- [ ] Add repository screenshots for homepage, listing, unit detail, YangMatch and internal dashboards.
- [ ] Expand observability and production monitoring.

## Portfolio Notes

YangHomestay demonstrates more than CRUD/UI implementation. The project is designed to show:

- product-oriented UX thinking;
- frontend implementation without a heavy framework;
- PHP backend architecture;
- PostgreSQL/Supabase data modeling;
- booking-domain business rules;
- personalized recommendation logic;
- authentication and authorization;
- security hardening;
- deployment and QA documentation.

---

<div align="center">

**YangHomestay · Hà Nội Boutique Stays**  
*Ở đúng gu, sống đúng chất Hà Nội.*

[Live Demo](https://yanghomestay.helioho.st/) · [QA Report](QA-REPORT.md) · [Deployment](DEPLOYMENT.md) · [Changelog](CHANGELOG-FINAL.md)

</div>
