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
  X,
  Store,
  UserCog,
  Crown,
  Shield,
  Briefcase
} from 'lucide-react';

export default function Sidebar({ 
  currentPage, 
  onNavigate, 
  isOpen, 
  onClose, 
  currentUser, 
  userRole = 'admin', 
  userProfile = {}, 
  isReadOnly = false 
}) {
  const role = userRole || 'admin';

  // Section & Nav Items with Role Filters
  const allSections = [
    {
      section: 'MONITORING & CORE',
      roles: ['owner', 'admin', 'manager', 'staff_noc', 'demo'],
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
      section: 'PORTAL RESELLER',
      roles: ['owner', 'admin', 'manager', 'reseller'],
      items: [
        { id: 'reseller', label: 'Reseller Kios Voucher', icon: Store, badge: 'Scanner' }
      ]
    },
    {
      section: 'BILLING & VOUCHERS',
      roles: ['owner', 'admin', 'manager', 'demo'],
      items: [
        { id: 'user_profiles', label: 'User Profiles', icon: Layers, badge: 'Paket' },
        { id: 'vouchers', label: 'Vouchers Hub', icon: Users, badge: '2.6k+' },
        { id: 'generate', label: 'Batch Generator', icon: PlusCircle },
        { id: 'print', label: 'Quick Print', icon: Printer }
      ]
    },
    {
      section: 'LAPORAN KEUANGAN',
      roles: ['owner', 'admin', 'manager', 'finance', 'demo'],
      items: [
        { id: 'reports', label: 'Rekap Penjualan', icon: FileText }
      ]
    },
    {
      section: 'ADMINISTRASI & RBAC',
      roles: ['owner', 'admin'],
      items: [
        { id: 'users_management', label: 'Kelola Pengguna', icon: UserCog, badge: 'RBAC' }
      ]
    }
  ];

  // Filter sections and items based on role
  const filteredSections = allSections
    .filter(sec => sec.roles.includes(role))
    .map(sec => ({
      ...sec,
      items: sec.items
    }));

  const roleMeta = {
    owner: { label: 'Owner', color: '#ec4899', icon: Crown },
    admin: { label: 'Administrator', color: '#00d2d3', icon: Shield },
    manager: { label: 'Manager', color: '#a855f7', icon: Briefcase },
    reseller: { label: 'Reseller Kios', color: '#f59e0b', icon: Store },
    staff_noc: { label: 'Staff NOC', color: '#3b82f6', icon: Layers },
    finance: { label: 'Finance', color: '#10b981', icon: BarChart3 },
    demo: { label: 'Demo Read-Only', color: '#f59e0b', icon: Eye }
  }[role] || { label: role, color: '#00d2d3', icon: ShieldCheck };

  const RoleIcon = roleMeta.icon;

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
          {filteredSections.map((sec, sIdx) => (
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
                        className={`tag ${
                          item.badge === 'Live' ? 'tag-emerald' : 
                          item.badge === 'Scanner' ? 'tag-amber' : 
                          item.badge === 'RBAC' ? 'tag-purple' : 'tag-cyan'
                        }`}
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
              background: `${roleMeta.color}22`,
              color: roleMeta.color,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: `1px solid ${roleMeta.color}44`
            }}>
              <RoleIcon size={16} />
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: '13px', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '6px' }}>
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {userProfile?.name || (currentUser ? (isReadOnly ? 'Pengguna Demo' : currentUser) : 'Administrator')}
                </span>
              </div>
              <div style={{ fontSize: '11px', display: 'flex', alignItems: 'center', gap: '4px', marginTop: '1px' }}>
                <span style={{ color: roleMeta.color, fontWeight: 700 }}>
                  ● {roleMeta.label}
                </span>
                {userProfile?.kiosk_name && (
                  <span style={{ color: 'var(--text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    • {userProfile.kiosk_name}
                  </span>
                )}
              </div>
            </div>
          </div>
        </div>
      </aside>
    </>
  );
}
