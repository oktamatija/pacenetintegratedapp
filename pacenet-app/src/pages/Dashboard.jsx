import React, { useState } from 'react';
import { 
  Wifi, 
  Activity, 
  Server, 
  Cpu, 
  ExternalLink, 
  ArrowDown, 
  ArrowUp, 
  PlusCircle, 
  Printer, 
  BarChart3,
  Clock,
  HardDrive,
  ArrowUpCircle,
  Sparkles,
  Copy,
  Check,
  Edit3,
  Trash2,
  Key,
  Eye,
  EyeOff,
  AlertTriangle,
  X,
  RefreshCw,
  ShieldCheck
} from 'lucide-react';

export default function Dashboard({ data, isLoading, onNavigate, onRefresh, isReadOnly }) {
  const summary = data?.summary || {};
  const routers = data?.routers || [];
  const [copied, setCopied] = useState(false);

  // Edit Modal State
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [editForm, setEditForm] = useState({
    session: '',
    new_session: '',
    ip: '',
    user: 'admin',
    pass: '',
    hotspot_name: '',
    dns_name: 'hotspot.yunus',
    currency: 'Rp'
  });
  const [showPass, setShowPass] = useState(false);
  const [testResult, setTestResult] = useState(null);
  const [editLoading, setEditLoading] = useState(false);
  const [editFeedback, setEditFeedback] = useState(null);

  // Delete Modal State
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [deletingRouter, setDeletingRouter] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const bootstrapCmd = '/tool fetch url="http://202.10.46.222/join.php?action=bootstrap" mode=http dst-path=join.rsc; :delay 2s; /import join.rsc; /file remove join.rsc';

  const handleCopyCmd = () => {
    navigator.clipboard.writeText(bootstrapCmd);
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  };

  // Open Edit Router Modal
  const handleOpenEdit = (r) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Pengubahan kredensial router dinonaktifkan.');
      return;
    }
    setEditForm({
      session: r.session,
      new_session: r.session,
      ip: r.vpn_ip || '',
      user: r.user || 'admin',
      pass: '',
      hotspot_name: r.hotspot_name || r.session,
      dns_name: r.dns_name || 'hotspot.yunus',
      currency: r.currency || 'Rp'
    });
    setTestResult(null);
    setEditFeedback(null);
    setShowPass(false);
    setEditModalOpen(true);
  };

  // Live Test Credentials
  const handleTestCredentials = async () => {
    setTestResult({ loading: true });
    try {
      const res = await fetch('/api/routers.php?action=test_credentials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ip: editForm.ip,
          user: editForm.user,
          pass: editForm.pass
        })
      });
      const json = await res.json();
      if (json.success) {
        setTestResult({
          success: true,
          message: json.message || `Terhubung ke ${json.data?.board_name} (ROS v${json.data?.ros_version})`
        });
      } else {
        setTestResult({
          success: false,
          message: json.message || 'Gagal terhubung ke router. Periksa IP, user, dan password.'
        });
      }
    } catch (e) {
      setTestResult({
        success: false,
        message: 'Koneksi ke API test router gagal.'
      });
    }
  };

  // Save Updated Credentials
  const handleSaveEdit = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setEditFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Pengubahan kredensial router dinonaktifkan.' });
      return;
    }
    setEditLoading(true);
    setEditFeedback(null);

    try {
      const res = await fetch('/api/routers.php?action=update_credentials', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(editForm)
      });
      const json = await res.json();
      if (json.success) {
        setEditModalOpen(false);
        if (onRefresh) onRefresh();
        alert(json.message || 'Kredensial router berhasil diperbarui.');
      } else {
        setEditFeedback({ type: 'error', text: json.message || 'Gagal menyimpan perubahan kredensial.' });
      }
    } catch (e) {
      setEditFeedback({ type: 'error', text: 'Terjadi kesalahan jaringan saat menyimpan kredensial.' });
    } finally {
      setEditLoading(false);
    }
  };

  // Open Delete Router Modal
  const handleOpenDelete = (r) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan router dinonaktifkan.');
      return;
    }
    setDeletingRouter(r);
    setDeleteModalOpen(true);
  };

  // Confirm Delete Router
  const handleConfirmDelete = async () => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan router dinonaktifkan.');
      return;
    }
    if (!deletingRouter) return;
    setDeleteLoading(true);

    try {
      const res = await fetch('/api/routers.php?action=delete_router', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session: deletingRouter.session })
      });
      const json = await res.json();
      if (json.success) {
        setDeleteModalOpen(false);
        setDeletingRouter(null);
        if (onRefresh) onRefresh();
        alert(json.message || 'Router berhasil dihapus dari sistem billing.');
      } else {
        alert(json.message || 'Gagal menghapus router.');
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan saat menghapus router.');
    } finally {
      setDeleteLoading(false);
    }
  };

  return (
    <div>
      {/* Quick Action Banner */}
      <div className="dashboard-hero">
        <div className="dashboard-hero-text">
          <h2>Multi-Router Cloud NOC Controller</h2>
          <p>Pemantauan terpusat real-time jaringan Pacenet di seluruh node router MikroTik & FTTH.</p>
        </div>

        <div className="dashboard-actions-grid">
          <button 
            className="btn btn-primary btn-sm"
            onClick={() => onNavigate('onboarding')}
          >
            <Sparkles size={14} />
            <span>Onboarding</span>
          </button>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('user_profiles')}
            style={{ borderColor: 'rgba(0, 210, 211, 0.4)', color: 'var(--accent-cyan)' }}
          >
            <Key size={14} />
            <span>User Profiles</span>
          </button>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('olt_ont')}
            style={{ borderColor: 'rgba(16, 185, 129, 0.4)', color: 'var(--accent-emerald)' }}
          >
            <Server size={14} />
            <span>OLT & ONT GIS</span>
          </button>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('generate')}
          >
            <PlusCircle size={14} />
            <span>Buat Voucher</span>
          </button>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('print')}
          >
            <Printer size={14} />
            <span>Cetak Cepat</span>
          </button>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('ros_manager')}
          >
            <ArrowUpCircle size={14} />
            <span>ROS Upgrade</span>
          </button>
        </div>
      </div>

      {/* Mini Zero-Touch Onboarding Quick Banner */}
      <div className="glass-card zero-touch-mini-card">
        <div className="zero-touch-inner">
          <div className="zero-touch-info">
            <div className="zero-touch-title">
              <Sparkles size={15} />
              <span>Hubungkan Router Baru Otomatis (Zero-Touch)</span>
            </div>
            <p className="zero-touch-desc">
              Jalankan perintah ini di <strong>New Terminal MikroTik</strong> untuk auto-connect:
            </p>
            <div className="zero-touch-cmd">
              <code>{bootstrapCmd}</code>
            </div>
          </div>

          <div className="zero-touch-actions">
            <button
              className="btn btn-primary btn-sm"
              onClick={handleCopyCmd}
            >
              {copied ? <Check size={14} /> : <Copy size={14} />}
              <span>{copied ? 'Tersalin!' : 'Salin CLI'}</span>
            </button>
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => onNavigate('onboarding')}
            >
              <span>Buka Onboarding</span>
            </button>
          </div>
        </div>
      </div>

      {/* KPI Summary Grid */}
      <div className="kpi-grid">
        {/* Active Hotspot Users */}
        <div className="glass-card kpi-card">
          <div className="kpi-info">
            <h3>Total Hotspot Aktif</h3>
            <div className="kpi-value">
              {summary.total_active_sessions ? summary.total_active_sessions.toLocaleString() : 0}
            </div>
            <div className="kpi-sub">Pelanggan terhubung saat ini</div>
          </div>
          <div className="kpi-icon">
            <Wifi size={24} />
          </div>
        </div>

        {/* Live WAN Download */}
        <div className="glass-card kpi-card emerald">
          <div className="kpi-info">
            <h3>Throughput Download (RX)</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-emerald)' }}>
              {summary.total_rx_human || '0 bps'}
            </div>
            <div className="kpi-sub" style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
              <ArrowDown size={12} color="var(--accent-emerald)" /> Total WAN agregat
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-emerald)' }}>
            <ArrowDown size={24} />
          </div>
        </div>

        {/* Live WAN Upload */}
        <div className="glass-card kpi-card blue">
          <div className="kpi-info">
            <h3>Throughput Upload (TX)</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-blue)' }}>
              {summary.total_tx_human || '0 bps'}
            </div>
            <div className="kpi-sub" style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
              <ArrowUp size={12} color="var(--accent-blue)" /> Total WAN agregat
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-blue)' }}>
            <ArrowUp size={24} />
          </div>
        </div>

        {/* Router Fleet Health */}
        <div className="glass-card kpi-card amber">
          <div className="kpi-info">
            <h3>Node MikroTik Online</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-amber)' }}>
              {summary.routers_online || 0} / {summary.routers_total || 0}
            </div>
            <div className="kpi-sub">
              VPS Load: {summary.vps_load || 0} • RAM: {summary.vps_ram_percent || 0}%
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-amber)' }}>
            <Server size={24} />
          </div>
        </div>
      </div>

      {/* Routers Grid Section */}
      <div style={{ marginTop: '28px', marginBottom: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '10px' }}>
        <h3 style={{ fontSize: '16px', fontWeight: 800, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
          <Activity size={18} color="var(--accent-cyan)" />
          <span>Status Node Router MikroTik ({routers.length} Node)</span>
        </h3>
        <span style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
          Kredensial router dapat diedit atau dihapus langsung melalui tombol pada kartu router.
        </span>
      </div>

      <div className="routers-grid">
        {routers.map((r) => {
          const isOnline = r.online;
          return (
            <div key={r.session} className={`glass-card router-card ${isOnline ? 'online' : 'offline'}`}>
              {/* Header */}
              <div className="router-header">
                <div className="router-title">
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <span className={`pulse-dot ${isOnline ? '' : 'offline'}`}></span>
                    <h4>{r.hotspot_name}</h4>
                  </div>
                  <p>{r.vpn_ip} • Sesi: <strong>{r.session}</strong></p>
                </div>

                <span className={`tag ${isOnline ? 'tag-emerald' : 'tag-rose'}`}>
                  {isOnline ? 'ONLINE' : 'OFFLINE'}
                </span>
              </div>

              {/* Hardware & Version */}
              <div style={{ 
                fontSize: '12px', 
                color: 'var(--text-muted)', 
                marginBottom: '10px',
                display: 'flex',
                justifyContent: 'space-between',
                flexWrap: 'wrap',
                gap: '6px'
              }}>
                <span><strong>Board:</strong> {r.board_name}</span>
                <span><strong>ROS:</strong> {r.ros_version}</span>
              </div>

              {/* Key Metrics Row */}
              <div className="router-stats-row">
                <div className="stat-item">
                  <span>Hotspot Aktif</span>
                  <strong>{r.active_sessions} Sesi</strong>
                </div>
                <div className="stat-item">
                  <span>Uptime</span>
                  <strong style={{ fontSize: '12px' }}>{r.uptime}</strong>
                </div>
                <div className="stat-item">
                  <span>CPU Load</span>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <strong>{r.cpu_load}%</strong>
                    <div style={{ 
                      flex: 1, 
                      height: '4px', 
                      background: 'rgba(255, 255, 255, 0.1)', 
                      borderRadius: '2px',
                      overflow: 'hidden'
                    }}>
                      <div style={{
                        width: `${Math.min(r.cpu_load, 100)}%`,
                        height: '100%',
                        background: r.cpu_load > 80 ? 'var(--accent-rose)' : 'var(--accent-cyan)'
                      }} />
                    </div>
                  </div>
                </div>
                <div className="stat-item">
                  <span>Throughput Total</span>
                  <strong style={{ fontSize: '11.5px', color: 'var(--accent-emerald)' }}>
                    ↓{r.total_rx_human || '0 bps'}
                  </strong>
                </div>
              </div>

              {/* WAN Interfaces Traffic */}
              {r.wan_interfaces && r.wan_interfaces.length > 0 && (
                <div style={{ marginBottom: '14px' }}>
                  <div style={{ fontSize: '11px', color: 'var(--text-muted)', marginBottom: '4px', fontWeight: 600 }}>
                    INTERFACE WAN DETECTED
                  </div>
                  {r.wan_interfaces.map((w, wIdx) => (
                    <div key={wIdx} className="traffic-badge">
                      <span style={{ color: 'var(--accent-cyan)' }}>{w.name}</span>
                      <span style={{ color: 'var(--text-secondary)' }}>
                        <span style={{ color: 'var(--accent-emerald)' }}>↓{w.rx_human}</span> &nbsp;
                        <span style={{ color: 'var(--accent-blue)' }}>↑{w.tx_human}</span>
                      </span>
                    </div>
                  ))}
                </div>
              )}

              {/* Action Bar: Remote Winbox + Edit Kredensial + Hapus Router */}
              <div style={{ 
                marginTop: 'auto', 
                paddingTop: '12px',
                borderTop: '1px solid var(--border-subtle)',
                display: 'flex',
                gap: '8px'
              }}>
                <a 
                  href={`winbox://${r.winbox_addr}`}
                  className="btn btn-secondary btn-sm"
                  style={{ flex: 1, justifyContent: 'center' }}
                  title="Buka Winbox langsung"
                >
                  <ExternalLink size={13} />
                  <span>Winbox ({r.winbox_addr})</span>
                </a>

                <button
                  className="btn btn-secondary btn-sm"
                  onClick={() => handleOpenEdit(r)}
                  style={{ padding: '6px 10px', color: 'var(--accent-cyan)', borderColor: 'rgba(0, 210, 211, 0.3)' }}
                  title="Edit Kredensial Router Ini"
                >
                  <Edit3 size={13} />
                  <span>Edit</span>
                </button>

                <button
                  className="btn btn-secondary btn-sm"
                  onClick={() => handleOpenDelete(r)}
                  style={{ padding: '6px 10px', color: 'var(--accent-rose)', borderColor: 'rgba(244, 63, 94, 0.3)' }}
                  title="Hapus Router Ini dari Sistem"
                >
                  <Trash2 size={13} />
                </button>
              </div>
            </div>
          );
        })}
      </div>

      {/* Modal: Edit Kredensial Router */}
      {editModalOpen && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(4px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 50,
          padding: '20px'
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '520px',
            padding: '24px',
            borderRadius: 'var(--radius-lg)',
            border: '1px solid var(--border-active)',
            maxHeight: '90vh',
            overflowY: 'auto'
          }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <Key size={18} color="var(--accent-cyan)" />
                <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff' }}>
                  Edit Kredensial Router: {editForm.session}
                </h3>
              </div>
              <button 
                onClick={() => setEditModalOpen(false)}
                style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            {editFeedback && (
              <div style={{
                marginBottom: '14px',
                padding: '10px 14px',
                borderRadius: 'var(--radius-sm)',
                background: 'rgba(244, 63, 94, 0.12)',
                border: '1px solid var(--accent-rose)',
                color: '#fb7185',
                fontSize: '12px'
              }}>
                {editFeedback.text}
              </div>
            )}

            <form onSubmit={handleSaveEdit}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                    Nama Sesi Router * (ID Unik Sistem)
                  </label>
                  <input
                    type="text"
                    className="input-field"
                    value={editForm.new_session}
                    onChange={e => setEditForm({ ...editForm, new_session: e.target.value })}
                    required
                    style={{ width: '100%' }}
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      IP Address MikroTik / VPN *
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={editForm.ip}
                      onChange={e => setEditForm({ ...editForm, ip: e.target.value })}
                      required
                      placeholder="10.10.10.3"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      User API MikroTik *
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={editForm.user}
                      onChange={e => setEditForm({ ...editForm, user: e.target.value })}
                      required
                      placeholder="admin"
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                    Password Baru MikroTik (Kosongkan jika tidak ingin mengubah)
                  </label>
                  <div style={{ position: 'relative' }}>
                    <input
                      type={showPass ? 'text' : 'password'}
                      className="input-field"
                      value={editForm.pass}
                      onChange={e => setEditForm({ ...editForm, pass: e.target.value })}
                      placeholder="Masukkan password baru jika diubah"
                      style={{ width: '100%', paddingRight: '36px' }}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPass(!showPass)}
                      style={{
                        position: 'absolute',
                        right: '8px',
                        top: '50%',
                        transform: 'translateY(-50%)',
                        background: 'none',
                        border: 'none',
                        color: 'var(--text-muted)',
                        cursor: 'pointer'
                      }}
                    >
                      {showPass ? <EyeOff size={15} /> : <Eye size={15} />}
                    </button>
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Nama Display Hotspot
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={editForm.hotspot_name}
                      onChange={e => setEditForm({ ...editForm, hotspot_name: e.target.value })}
                      placeholder="misal: Rumah Dolphin"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      DNS Name Hotspot
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={editForm.dns_name}
                      onChange={e => setEditForm({ ...editForm, dns_name: e.target.value })}
                      placeholder="hotspot.yunus"
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                {/* Test Connection Button & Indicator */}
                <div style={{
                  padding: '12px',
                  background: 'rgba(0,0,0,0.25)',
                  borderRadius: 'var(--radius-sm)',
                  border: '1px solid var(--border-subtle)',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '8px'
                }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Uji koneksi sebelum menyimpan:</span>
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      onClick={handleTestCredentials}
                      disabled={testResult?.loading}
                      style={{ padding: '4px 12px', fontSize: '12px' }}
                    >
                      <RefreshCw size={12} className={testResult?.loading ? 'spin' : ''} />
                      <span>{testResult?.loading ? 'Menguji...' : 'Test Koneksi API'}</span>
                    </button>
                  </div>

                  {testResult && !testResult.loading && (
                    <div style={{
                      padding: '8px 10px',
                      borderRadius: '4px',
                      background: testResult.success ? 'rgba(16, 185, 129, 0.15)' : 'rgba(244, 63, 94, 0.15)',
                      color: testResult.success ? '#34d399' : '#fb7185',
                      fontSize: '11.5px',
                      display: 'flex',
                      alignItems: 'center',
                      gap: '6px'
                    }}>
                      {testResult.success ? <ShieldCheck size={14} /> : <AlertTriangle size={14} />}
                      <span>{testResult.message}</span>
                    </div>
                  )}
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '6px' }}>
                  <button 
                    type="button" 
                    className="btn btn-secondary"
                    onClick={() => setEditModalOpen(false)}
                    disabled={editLoading}
                  >
                    Batal
                  </button>
                  <button 
                    type="submit" 
                    className="btn btn-primary"
                    disabled={editLoading}
                    style={{ background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)' }}
                  >
                    {editLoading ? 'Menyimpan...' : 'Simpan Kredensial'}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Konfirmasi Hapus Router */}
      {deleteModalOpen && deletingRouter && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(4px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 50,
          padding: '20px'
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '460px',
            padding: '24px',
            borderRadius: 'var(--radius-lg)',
            border: '1px solid var(--accent-rose)'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '16px' }}>
              <div style={{
                width: '42px',
                height: '42px',
                borderRadius: '50%',
                background: 'rgba(244, 63, 94, 0.15)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'var(--accent-rose)'
              }}>
                <AlertTriangle size={22} />
              </div>
              <div>
                <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff' }}>
                  Hapus Router dari Billing
                </h3>
                <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
                  Tindakan ini akan menghapus sesi router dari konfigurasi Pacenet.
                </p>
              </div>
            </div>

            <div style={{
              background: 'rgba(0,0,0,0.3)',
              padding: '12px',
              borderRadius: 'var(--radius-sm)',
              marginBottom: '18px',
              fontSize: '12.5px',
              lineHeight: '1.5',
              color: 'var(--text-secondary)'
            }}>
              Apakah Anda yakin ingin menghapus router <strong>{deletingRouter.hotspot_name}</strong> (Sesi: <code>{deletingRouter.session}</code> • IP: <code>{deletingRouter.vpn_ip}</code>)?
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
              <button 
                type="button" 
                className="btn btn-secondary"
                onClick={() => {
                  setDeleteModalOpen(false);
                  setDeletingRouter(null);
                }}
                disabled={deleteLoading}
              >
                Batal
              </button>
              <button 
                type="button" 
                className="btn"
                onClick={handleConfirmDelete}
                disabled={deleteLoading}
                style={{
                  background: 'var(--accent-rose)',
                  color: '#fff',
                  border: 'none',
                  padding: '8px 16px',
                  borderRadius: 'var(--radius-sm)',
                  fontWeight: 700
                }}
              >
                {deleteLoading ? 'Menghapus...' : 'Ya, Hapus Router'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
