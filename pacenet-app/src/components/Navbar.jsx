import React from 'react';
import { Menu, Activity, Server, Wifi, RefreshCw, LogOut, Shield, Eye } from 'lucide-react';

export default function Navbar({ 
  currentPage, 
  onToggleSidebar, 
  summaryData, 
  onRefresh, 
  isRefreshing, 
  onLogout,
  currentUser,
  isReadOnly
}) {
  const routersOnline = summaryData?.routers_online ?? 0;
  const routersTotal = summaryData?.routers_total ?? 0;
  const totalActive = summaryData?.total_active_sessions ?? 0;
  const rxHuman = summaryData?.total_rx_human ?? '0 bps';
  const txHuman = summaryData?.total_tx_human ?? '0 bps';
  const vpsRam = summaryData?.vps_ram_percent ?? 0;

  const pageTitles = {
    dashboard: 'NOC Executive Dashboard',
    user_profiles: 'User Profiles & Paket',
    olt_ont: 'Pemetaan OLT & ONT GIS',
    onboarding: 'Zero-Touch Onboarding',
    traffic: 'Traffic Monitor Real-Time',
    vouchers: 'Centralized Vouchers Hub',
    generate: 'Batch Voucher Generator',
    print: 'Quick Print & Thermal Slips',
    reports: 'Rekap Penjualan & Laporan',
    ros_manager: 'ROS Upgrade Center',
    system: 'VPS Cloud & WireGuard Mesh'
  };

  const mobileTitles = {
    dashboard: 'NOC Dashboard',
    user_profiles: 'User Profiles',
    olt_ont: 'OLT / ONT GIS',
    onboarding: 'Onboarding',
    traffic: 'Traffic Monitor',
    vouchers: 'Vouchers Hub',
    generate: 'Batch Voucher',
    print: 'Cetak Cepat',
    reports: 'Rekap Laporan',
    ros_manager: 'ROS Upgrade',
    system: 'VPS Cloud'
  };

  return (
    <header className="topbar">
      <div className="topbar-left">
        <button 
          className="mobile-toggle" 
          onClick={onToggleSidebar} 
          aria-label="Toggle Navigation"
        >
          <Menu size={20} />
        </button>

        <div className="page-title-badge">
          <Shield size={16} color="var(--accent-cyan)" className="title-icon" />
          <h2 className="title-desktop">{pageTitles[currentPage] || 'Dashboard'}</h2>
          <h2 className="title-mobile">{mobileTitles[currentPage] || 'Dashboard'}</h2>
        </div>
      </div>

      <div className="topbar-right">
        {/* Demo Read-Only Pill - Desktop */}
        {isReadOnly && (
          <div className="status-pill demo-pill-desktop" style={{
            background: 'rgba(245, 158, 11, 0.15)',
            border: '1px solid rgba(245, 158, 11, 0.35)',
            color: '#f59e0b'
          }}>
            <Eye size={13} />
            <span style={{ fontWeight: 700 }}>DEMO (READ-ONLY)</span>
          </div>
        )}

        {/* Demo Read-Only Pill - Mobile */}
        {isReadOnly && (
          <span className="demo-pill-mobile">
            <span className="pulse-dot warning" style={{ width: '6px', height: '6px' }}></span>
            <span>DEMO</span>
          </span>
        )}

        {/* Router Status Pill - Desktop only */}
        <div className="status-pill desktop-only-pill">
          <span className={`pulse-dot ${routersOnline === routersTotal && routersTotal > 0 ? '' : 'warning'}`}></span>
          <span>{routersOnline}/{routersTotal} Routers Online</span>
        </div>

        {/* Live Active Sessions - Desktop only */}
        <div className="status-pill desktop-only-pill">
          <Wifi size={13} color="var(--accent-cyan)" />
          <span>{totalActive.toLocaleString()} Aktif</span>
        </div>

        {/* Total WAN Throughput - Desktop only */}
        <div className="status-pill desktop-only-pill" title="Total WAN Bandwidth">
          <Activity size={13} color="var(--accent-emerald)" />
          <span style={{ fontFamily: 'var(--font-mono)' }}>↓{rxHuman} ↑{txHuman}</span>
        </div>

        {/* Refresh Button */}
        <button 
          className="btn btn-secondary btn-sm nav-btn" 
          onClick={onRefresh} 
          disabled={isRefreshing}
          title="Refresh Live Data"
        >
          <RefreshCw size={14} className={isRefreshing ? 'spin-anim' : ''} />
          <span className="btn-text-desktop">Sync</span>
        </button>

        {/* Logout Button */}
        <button 
          className="btn btn-danger btn-sm nav-btn" 
          onClick={onLogout} 
          title="Keluar dari Sistem"
        >
          <LogOut size={14} />
        </button>
      </div>
    </header>
  );
}
