import React from 'react';
import { 
  Activity, 
  BarChart3, 
  Users, 
  PlusCircle, 
  Printer, 
  FileText, 
  Server, 
  ShieldCheck,
  Zap,
  ArrowUpCircle,
  Sparkles,
  Network,
  Layers,
  Eye,
  X
} from 'lucide-react';

export default function Sidebar({ currentPage, onNavigate, isOpen, onClose, currentUser, isReadOnly }) {
  const navItems = [
    {
      section: 'MONITORING & CORE',
      items: [
        { id: 'dashboard', label: 'NOC Dashboard', icon: Activity, badge: 'Live' },
        { id: 'onboarding', label: 'Router Onboarding', icon: Sparkles, badge: 'Zero-Touch' },
        { id: 'olt_ont', label: 'Pemetaan OLT & ONT', icon: Network, badge: 'FTTH' },
        { id: 'traffic', label: 'Traffic Monitor', icon: BarChart3 },
        { id: 'ros_manager', label: 'ROS Upgrade Center', icon: ArrowUpCircle, badge: 'Smart' },
        { id: 'system', label: 'VPS & Mesh Health', icon: Server }
      ]
    },
    {
      section: 'BILLING & VOUCHERS',
      items: [
        { id: 'user_profiles', label: 'User Profiles', icon: Layers, badge: 'Paket' },
        { id: 'vouchers', label: 'Vouchers Hub', icon: Users, badge: '2.6k+' },
        { id: 'generate', label: 'Batch Generator', icon: PlusCircle },
        { id: 'print', label: 'Quick Print', icon: Printer }
      ]
    },
    {
      section: 'LAPORAN KEUANGAN',
      items: [
        { id: 'reports', label: 'Rekap Penjualan', icon: FileText }
      ]
    }
  ];

  return (
    <>
      {/* Mobile Backdrop */}
      {isOpen && (
        <div 
          onClick={onClose}
          style={{
            position: 'fixed',
            inset: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.7)',
            backdropFilter: 'blur(4px)',
            zIndex: 45
          }}
        />
      )}

      <aside className={`sidebar ${isOpen ? 'open' : ''}`}>
        {/* Brand Header */}
        <div className="sidebar-header" style={{ justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
            <div className="brand-badge">
              <Zap size={22} />
            </div>
            <div className="brand-text">
              <h1>PACENET PRO</h1>
              <span>CLOUD NOC CONTROLLER</span>
            </div>
          </div>
          <button 
            onClick={onClose} 
            className="sidebar-close-btn"
            aria-label="Tutup Menu"
          >
            <X size={18} />
          </button>
        </div>

        {/* Navigation */}
        <nav className="sidebar-nav">
          {navItems.map((sec, sIdx) => (
            <div key={sIdx}>
              <div className="nav-section-title">{sec.section}</div>
              {sec.items.map((item) => {
                const Icon = item.icon;
                const isActive = currentPage === item.id;
                return (
                  <button
                    key={item.id}
                    onClick={() => {
                      onNavigate(item.id);
                      if (onClose) onClose();
                    }}
                    className={`nav-item ${isActive ? 'active' : ''}`}
                    style={{ width: '100%', border: 'none', textAlign: 'left' }}
                  >
                    <Icon size={18} />
                    <span style={{ flex: 1 }}>{item.label}</span>
                    {item.badge && (
                      <span 
                        className={`tag ${item.badge === 'Live' ? 'tag-emerald' : 'tag-cyan'}`}
                        style={{ fontSize: '10px', padding: '1px 6px' }}
                      >
                        {item.badge}
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          ))}
        </nav>

        {/* Footer User Info */}
        <div className="sidebar-footer">
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <div style={{
              width: '34px',
              height: '34px',
              borderRadius: '50%',
              background: isReadOnly ? 'rgba(245, 158, 11, 0.15)' : 'rgba(0, 210, 211, 0.15)',
              color: isReadOnly ? '#f59e0b' : 'var(--accent-cyan)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: isReadOnly ? '1px solid rgba(245, 158, 11, 0.3)' : '1px solid rgba(0, 210, 211, 0.2)'
            }}>
              {isReadOnly ? <Eye size={16} /> : <ShieldCheck size={16} />}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: '13px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {currentUser ? (isReadOnly ? 'Pengguna Demo' : currentUser) : 'Administrator'}
                </span>
                {isReadOnly && (
                  <span style={{ 
                    fontSize: '9.5px', 
                    padding: '1px 5px', 
                    borderRadius: '4px', 
                    background: 'rgba(245, 158, 11, 0.2)', 
                    color: '#f59e0b',
                    fontWeight: 700 
                  }}>
                    READ ONLY
                  </span>
                )}
              </div>
              <div style={{ fontSize: '11px', color: isReadOnly ? '#f59e0b' : 'var(--accent-emerald)' }}>
                {isReadOnly ? '● Mode Peninjauan' : '● Session Active'}
              </div>
            </div>
          </div>
        </div>
      </aside>
    </>
  );
}
