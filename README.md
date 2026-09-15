# PACENET PRO - Integrated Cloud NOC Controller & Hotspot Billing System

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![React](https://img.shields.io/badge/Frontend-React%2019%20%2B%20Vite-61dafb.svg)](https://react.dev/)
[![PHP](https://img.shields.io/badge/Backend-PHP%208%20REST%20API-777bb4.svg)](https://www.php.net/)
[![MikroTik RouterOS](https://img.shields.io/badge/MikroTik-RouterOS%20v6%20%2F%20v7-ee2a24.svg)](https://mikrotik.com/)
[![FreeRADIUS](https://img.shields.io/badge/AAA-FreeRADIUS%20%2B%20PostgreSQL-2b579a.svg)](https://freeradius.org/)

**PACENET PRO** is an enterprise-grade, centralized Network Operations Center (NOC) Controller and Hotspot Billing Management System designed for multi-router Internet Service Providers (ISPs), RT/RW Net operators, and commercial hotspot networks.

Built on modern **React 19 + Vite** (SPA frontend) and high-performance **PHP REST APIs** interfacing directly with **MikroTik RouterOS API** and **FreeRADIUS (PostgreSQL)** over secure WireGuard VPN mesh tunnels.

---

## Key Features

### 1. Multi-Router Live Synchronization
- **Centralized NOC Dashboard**: Real-time aggregation of active users, WAN bandwidth throughput (RX/TX), router CPU/RAM health, and WireGuard mesh status across all managed routers.
- **Synchronized Status Bar**: Header indicators automatically calculate network-wide active sessions, router online status, and traffic rates with live sub-second polling.

### 2. Comprehensive Voucher & Revenue Reporting
- **3-Way Voucher Classification**:
  - 🟢 **Sementara Terpakai (Active / In-Use)**: Live countdown of session time left, active uptime, device IP/MAC address, and data quota consumed.
  - 🟡 **Belum Terpakai (Unused Stock)**: Inventory of generated vouchers waiting in stock with potential revenue projection.
  - ⚪ **Habis Terpakai (Expired / Completed)**: Historical logs of completed sessions, sales timestamps, and expiration details.
- **Multi-Router Filtering**: View reports aggregated across all routers or filter down to an individual router with zero-latency cached queries.
- **One-Click CSV Export**: Export formatted accounting and voucher datasets.

### 3. Bilingual Regex Duration Engine & Expiration Watchdog
- **Universal Duration Parsing**: Supports both Indonesian (`jam`, `hari`, `minggu`, `bulan`) and standard MikroTik notation (`h`, `d`, `w`, `m`).
  - Example: `12 jam`, `12jam`, `12h`, `12 hours` $\rightarrow$ normalized to `12h` (43,200s).
- **High-Reliability Expiration Watchdog**: Cron-based daemon (`watchdog_expire.php`) running every 1 minute to enforce strict expiration for both FreeRADIUS PostgreSQL sessions and local MikroTik hotspot cookies/active sessions, terminating expired sessions automatically.

### 4. Advanced Hotspot & User Profile Management
- **Centralized Vouchers Hub**: Search, filter, and manage thousands of vouchers in under 50ms with server-side pagination.
- **Batch Generator**: Create hundreds of randomized or prefix-based vouchers with customizable character sets and print templates.
- **Quick Thermal Slips**: Direct printing optimized for 58mm and 80mm ESC/POS Bluetooth thermal slip printers.

### 5. OLT & ONT GIS Topology Mapping
- Interactive Leaflet-powered GIS map visualizing OLTs, optical distribution points (ODP), and subscriber ONTs with optical power levels (dBm) and physical locations.

### 6. RouterOS Remote Upgrade Center
- Automated firmware check and remote upgrade orchestration across RouterOS v6 and v7 devices with health verification and pre-upgrade configuration backups.

---

## Architecture & Tech Stack

```
                     ┌─────────────────────────────────────────┐
                     │          PACENET PRO FRONTEND           │
                     │         React 19 + Vite (Dark NOC)      │
                     └────────────────────┬────────────────────┘
                                          │ HTTPS / REST API
                     ┌────────────────────▼────────────────────┐
                     │          PACENET REST API ENGINE        │
                     │          PHP 8 + RouterOS API           │
                     └───────┬─────────────────────────┬───────┘
                             │                         │
            WireGuard Tunnel │                         │ SQL / RADIUS
                             ▼                         ▼
            ┌─────────────────────────────┐   ┌───────────────────────────┐
            │   MIKROTIK ROUTERS MESH     │   │   FREERADIUS POSTGRESQL   │
            │  • Rumah Dolphin (Master)   │   │  • radcheck / radacct     │
            │  • Dolphin Hamadi           │   │  • Strict Session-Timeout │
            │  • Additional POP Routers   │   └───────────────────────────┘
            └─────────────────────────────┘
```

- **Frontend**: React 19, Vite, Lucide Icons, Leaflet GIS, Vanilla CSS Design System.
- **Backend**: PHP 8.x, RouterosAPI Class, FreeRADIUS PostgreSQL Adapter.
- **Infrastructure**: Nginx, WireGuard Mesh VPN, systemd/cron automation.

---

## Project Structure

```
.
├── pacenet-app/               # React 19 Single Page Application (Frontend)
│   ├── src/
│   │   ├── components/        # UI Components (Navbar, Sidebar, etc.)
│   │   ├── pages/             # Dashboard, Reports, Vouchers, Profiles, etc.
│   │   ├── App.jsx            # Master Router & State Controller
│   │   └── index.css          # Glassmorphism dark NOC design system
│   └── package.json
├── pacenetintegratedapp/      # Independent Pacenet PHP Backend & REST APIs
│   ├── api/                   # Modern JSON REST endpoints
│   │   ├── common.php         # Shared utilities, duration regex, auth guards
│   │   ├── auth.php           # Session & Token authentication controller
│   │   ├── reports.php        # Multi-router sales & voucher classification API
│   │   ├── routers.php        # Multi-router health & WAN traffic monitor
│   │   ├── vouchers.php       # High-speed voucher inventory & batch querying
│   │   ├── watchdog_expire.php# 1-minute expiration watchdog daemon
│   │   ├── profiles.php       # Hotspot user profile generator & quick print
│   │   ├── generate.php       # High-speed batch voucher generator
│   │   ├── traffic.php        # Multi-router SQLite traffic statistics API
│   │   ├── system.php         # Host VPS resources & WireGuard mesh monitor
│   │   ├── ros_manager.php    # RouterOS firmware upgrade & version manager
│   │   ├── onboarding.php     # Zero-touch router onboarding controller
│   │   └── olt_ont.php        # Fiber OLT/ONT topology & GIS mapping API
│   ├── lib/                   # RouterOS API client & binary formatting helpers
│   ├── data/                  # SQLite traffic database & device storage
│   ├── hotspot-login/         # Responsive customer captive portal login
│   ├── join.php               # Zero-Touch MikroTik WireGuard Bootstrap
│   ├── index.php              # Root entry point (redirects to /app/)
│   └── include/
│       └── config.php.example # Configuration template for routers
├── deploy_multi_router.py     # Automated build & VPS sync deploy script
├── deploy_config.py.example   # VPS deployment connection template
└── LICENSE                    # GNU General Public License v3.0
```

---

## Quick Start & Installation

### 1. Clone the Repository
```bash
git clone https://github.com/oktamatija/pacenetintegratedapp.git
cd pacenetintegratedapp
```

### 2. Frontend Setup
```bash
cd pacenet-app
npm install
npm run build
```

### 3. Backend & Router Configuration
1. Copy `pacenetintegratedapp/include/config.php.example` to `pacenetintegratedapp/include/config.php`:
   ```bash
   cp pacenetintegratedapp/include/config.php.example pacenetintegratedapp/include/config.php
   ```
2. Configure your MikroTik routers in `config.php` with appropriate API credentials and WireGuard IP addresses.
3. Configure the watchdog cron job on your server:
   ```cron
   * * * * * /usr/bin/php /var/www/pacenetintegratedapp/api/watchdog_expire.php >/dev/null 2>&1
   ```

### 4. Deploying to VPS
Copy `deploy_config.py.example` to `deploy_config.py` and set your server details:
```python
VPS_HOST = "your-vps-ip"
VPS_PORT = 22
VPS_USER = "root"
VPS_PASS = "your-password"
```
Run the automated deployment script:
```bash
python deploy_multi_router.py
```

---

## License

This project is licensed under the **GNU General Public License v3.0 (GPLv3)** - see the [LICENSE](LICENSE) file for details.
