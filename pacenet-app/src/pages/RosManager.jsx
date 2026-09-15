import React, { useState, useEffect } from 'react';
import { 
  Server, 
  Cpu, 
  ArrowUpCircle, 
  ArrowDownCircle, 
  RefreshCw, 
  CheckCircle2, 
  AlertTriangle, 
  RotateCw, 
  ShieldCheck, 
  Zap, 
  Layers, 
  Download, 
  CheckSquare, 
  Square,
  AlertCircle
} from 'lucide-react';

export default function RosManager({ isReadOnly }) {
  const [routers, setRouters] = useState([]);
  const [loading, setLoading] = useState(true);
  const [checkingAll, setCheckingAll] = useState(false);
  const [commonVersions, setCommonVersions] = useState([]);

  // Action status message
  const [notification, setNotification] = useState(null);

  // Modal: Target Version (Upgrade / Downgrade)
  const [versionModal, setVersionModal] = useState({
    open: false,
    router: null,
    targetVersion: '',
    isCustom: false,
    processing: false
  });

  // Modal: Mass Upgrade
  const [massModal, setMassModal] = useState({
    open: false,
    channel: 'stable',
    selectedSessions: {},
    processing: false,
    results: null
  });

  // Modal: Bootloader Upgrade Confirmation
  const [fwModal, setFwModal] = useState({
    open: false,
    router: null,
    processing: false
  });

  const fetchRouters = async (checkUpdates = false) => {
    setLoading(true);
    try {
      const res = await fetch(`/api/ros_manager.php?action=list${checkUpdates ? '&check=1' : ''}`);
      const json = await res.json();
      if (json.success) {
        setRouters(json.data.routers || []);
        setCommonVersions(json.data.common_versions || []);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
      setCheckingAll(false);
    }
  };

  useEffect(() => {
    fetchRouters(false);
  }, []);

  const handleCheckAllUpdates = async () => {
    setCheckingAll(true);
    await fetchRouters(true);
  };

  // 1. Trigger Smart Upgrade (Latest Version)
  const handleSmartUpgrade = async (router) => {
    if (isReadOnly) {
      setNotification({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Fitur Smart Upgrade dinonaktifkan.' });
      return;
    }
    if (!window.confirm(`Yakin ingin melakukan Smart Upgrade RouterOS pada "${router.hotspot_name}" (${router.session}) ke versi terbaru? Router akan restart otomatis.`)) {
      return;
    }

    setNotification({ type: 'info', text: `Mengirim perintah Smart Upgrade ke ${router.hotspot_name}...` });
    try {
      const res = await fetch('/api/ros_manager.php?action=smart_upgrade', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session: router.session })
      });
      const json = await res.json();
      if (json.success) {
        setNotification({ type: 'success', text: json.message });
      } else {
        setNotification({ type: 'error', text: json.message || 'Gagal mengirim perintah upgrade' });
      }
    } catch (e) {
      setNotification({ type: 'error', text: 'Koneksi error ke API' });
    }
  };

  // 2. Submit Target Version (Downgrade / Custom Upgrade)
  const handleSubmitTargetVersion = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setNotification({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Modifikasi versi RouterOS dinonaktifkan.' });
      return;
    }
    if (!versionModal.router || !versionModal.targetVersion) return;

    setVersionModal(prev => ({ ...prev, processing: true }));
    setNotification({ 
      type: 'info', 
      text: `Menyiapkan paket ${versionModal.router.architecture} versi ${versionModal.targetVersion} untuk ${versionModal.router.hotspot_name}...` 
    });

    try {
      const res = await fetch('/api/ros_manager.php?action=target_version', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session: versionModal.router.session,
          target_version: versionModal.targetVersion
        })
      });
      const json = await res.json();
      if (json.success) {
        setNotification({ type: 'success', text: json.message });
        setVersionModal({ open: false, router: null, targetVersion: '', isCustom: false, processing: false });
      } else {
        setNotification({ type: 'error', text: json.message || 'Proses target version gagal' });
        setVersionModal(prev => ({ ...prev, processing: false }));
      }
    } catch (e) {
      setNotification({ type: 'error', text: 'Koneksi error saat proses unduh/pasang paket' });
      setVersionModal(prev => ({ ...prev, processing: false }));
    }
  };

  // 3. Upgrade Routerboard Bootloader Firmware
  const handleUpgradeFirmware = async (router) => {
    if (isReadOnly) {
      setNotification({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Upgrade bootloader firmware dinonaktifkan.' });
      return;
    }
    setFwModal({ open: true, router, processing: false });
  };

  const confirmUpgradeFirmware = async () => {
    if (isReadOnly) {
      setNotification({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Upgrade firmware dinonaktifkan.' });
      return;
    }
    const router = fwModal.router;
    if (!router) return;
    setFwModal(prev => ({ ...prev, processing: true }));

    try {
      const res = await fetch('/api/ros_manager.php?action=upgrade_firmware', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session: router.session })
      });
      const json = await res.json();
      if (json.success) {
        setNotification({ type: 'success', text: json.message });
        setFwModal({ open: false, router: null, processing: false });
        fetchRouters(false);
      } else {
        setNotification({ type: 'error', text: json.message || 'Gagal upgrade firmware' });
        setFwModal(prev => ({ ...prev, processing: false }));
      }
    } catch (e) {
      setNotification({ type: 'error', text: 'Error jaringan' });
      setFwModal(prev => ({ ...prev, processing: false }));
    }
  };

  // 4. Open Mass Upgrade Modal
  const handleOpenMassUpgrade = () => {
    if (isReadOnly) {
      setNotification({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Upgrade massal router dinonaktifkan.' });
      return;
    }
    const initialSelected = {};
    routers.forEach(r => {
      if (r.online) {
        initialSelected[r.session] = true;
      }
    });
    setMassModal({
      open: true,
      channel: 'stable',
      selectedSessions: initialSelected,
      processing: false,
      results: null
    });
  };

  // 5. Execute Mass Upgrade
  const handleExecuteMassUpgrade = async () => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Upgrade massal router dinonaktifkan.');
      return;
    }
    const activeSessions = Object.keys(massModal.selectedSessions).filter(k => massModal.selectedSessions[k]);
    if (activeSessions.length === 0) {
      alert('Pilih setidaknya satu router untuk diupgrade.');
      return;
    }

    if (!window.confirm(`PERINGATAN: Anda akan melakukan Mass Upgrade pada ${activeSessions.length} router MikroTik secara bersamaan. Seluruh router terpilih akan mengunduh paket dan reboot otomatis. Lanjutkan?`)) {
      return;
    }

    setMassModal(prev => ({ ...prev, processing: true }));

    try {
      const res = await fetch('/api/ros_manager.php?action=mass_upgrade', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          sessions: activeSessions,
          channel: massModal.channel
        })
      });
      const json = await res.json();
      if (json.success) {
        setMassModal(prev => ({ ...prev, processing: false, results: json.data }));
        setNotification({ type: 'success', text: json.message });
      } else {
        alert(json.message || 'Gagal menjalankan mass upgrade');
        setMassModal(prev => ({ ...prev, processing: false }));
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan');
      setMassModal(prev => ({ ...prev, processing: false }));
    }
  };

  // Calculate Metrics
  const onlineCount = routers.filter(r => r.online).length;
  const updateCount = routers.filter(r => r.update_available).length;
  const fwCount = routers.filter(r => r.firmware_upgrade_available).length;
  const architectures = Array.from(new Set(routers.map(r => r.architecture).filter(a => a && a !== 'unknown')));

  const getArchBadgeClass = (arch) => {
    switch (arch) {
      case 'arm64': return 'tag-cyan';
      case 'arm': return 'tag-purple';
      case 'tile': return 'tag-amber';
      case 'x86': return 'tag-emerald';
      case 'mmips': return 'tag-cyan';
      default: return 'tag-purple';
    }
  };

  return (
    <div>
      {/* Top Banner */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: '14px',
        marginBottom: '20px',
        padding: '18px 24px',
        background: 'linear-gradient(90deg, rgba(0, 210, 211, 0.12) 0%, rgba(139, 92, 246, 0.08) 100%)',
        border: '1px solid var(--border-active)',
        borderRadius: 'var(--radius-lg)'
      }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <Zap size={22} color="var(--accent-cyan)" />
            <h2 style={{ fontSize: '19px', fontWeight: 800, color: '#fff' }}>
              Smart RouterOS Lifecycle & Fleet Upgrade Center
            </h2>
          </div>
          <p style={{ fontSize: '13px', color: 'var(--text-secondary)', marginTop: '4px' }}>
            Manajemen cerdas upgrade & downgrade RouterOS lintas arsitektur hardware (CCR Tile, ARM, ARM64, x86, MMIPS) baik satu persatu maupun massal.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
          <button 
            className="btn btn-secondary"
            onClick={handleCheckAllUpdates}
            disabled={checkingAll || loading}
          >
            <RefreshCw size={15} className={checkingAll ? 'spin-anim' : ''} />
            <span>Periksa Semua Pembaruan</span>
          </button>

          <button 
            className="btn btn-primary"
            onClick={handleOpenMassUpgrade}
            disabled={loading || onlineCount === 0}
            style={{
              background: 'linear-gradient(135deg, #f59e0b 0%, #ef4444 100%)',
              boxShadow: '0 0 15px rgba(245, 158, 11, 0.3)'
            }}
          >
            <ArrowUpCircle size={16} />
            <span>Upgrade Massal Seluruh Router ({onlineCount})</span>
          </button>
        </div>
      </div>

      {/* Notification Banner */}
      {notification && (
        <div style={{
          padding: '12px 18px',
          marginBottom: '20px',
          borderRadius: 'var(--radius-md)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          background: notification.type === 'error' ? 'rgba(244, 63, 94, 0.15)' :
                      notification.type === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(0, 210, 211, 0.15)',
          border: `1px solid ${notification.type === 'error' ? 'var(--accent-rose)' :
                               notification.type === 'success' ? 'var(--accent-emerald)' : 'var(--accent-cyan)'}`,
          color: notification.type === 'error' ? 'var(--accent-rose)' :
                 notification.type === 'success' ? 'var(--accent-emerald)' : 'var(--accent-cyan)',
          fontSize: '13px'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            {notification.type === 'error' ? <AlertCircle size={18} /> :
             notification.type === 'success' ? <CheckCircle2 size={18} /> : <RefreshCw size={18} className="spin-anim" />}
            <span>{notification.text}</span>
          </div>
          <button 
            style={{ color: 'inherit', fontWeight: 700, fontSize: '16px' }}
            onClick={() => setNotification(null)}
          >
            &times;
          </button>
        </div>
      )}

      {/* KPI Cards */}
      <div className="kpi-grid">
        <div className="glass-card kpi-card">
          <div className="kpi-info">
            <h3>Router Online</h3>
            <div className="kpi-value">{onlineCount} / {routers.length}</div>
            <div className="kpi-sub">Node aktif terhubung</div>
          </div>
          <div className="kpi-icon">
            <Server size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card emerald">
          <div className="kpi-info">
            <h3>Pembaruan Tersedia</h3>
            <div className="kpi-value" style={{ color: updateCount > 0 ? 'var(--accent-amber)' : 'var(--accent-emerald)' }}>
              {updateCount} Node
            </div>
            <div className="kpi-sub">{updateCount > 0 ? 'Versi baru siap diinstal' : 'Semua router up-to-date'}</div>
          </div>
          <div className="kpi-icon" style={{ color: updateCount > 0 ? 'var(--accent-amber)' : 'var(--accent-emerald)' }}>
            <ArrowUpCircle size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card amber">
          <div className="kpi-info">
            <h3>Bootloader Firmware Outdated</h3>
            <div className="kpi-value" style={{ color: fwCount > 0 ? 'var(--accent-amber)' : 'var(--accent-cyan)' }}>
              {fwCount} Node
            </div>
            <div className="kpi-sub">RouterBOARD bootloader upgrade</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-amber)' }}>
            <ShieldCheck size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card purple">
          <div className="kpi-info">
            <h3>Arsitektur Armada</h3>
            <div className="kpi-value" style={{ fontSize: '18px', textTransform: 'uppercase' }}>
              {architectures.join(', ') || 'Auto-Detect'}
            </div>
            <div className="kpi-sub">CCR Tile, ARM, ARM64, x86</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-purple)' }}>
            <Cpu size={24} />
          </div>
        </div>
      </div>

      {/* Fleet Router Table */}
      <div className="glass-card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{
          padding: '16px 20px',
          borderBottom: '1px solid var(--border-subtle)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '10px'
        }}>
          <div>
            <h3 style={{ fontSize: '16px', fontWeight: 700, color: '#fff' }}>
              Daftar Node RouterBOARD & Paket RouterOS
            </h3>
            <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
              Deteksi otomatis arsitektur CPU dan versi firmware untuk upgrade & downgrade yang aman
            </p>
          </div>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => fetchRouters(false)}
            disabled={loading}
          >
            <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
            <span>Segarkan</span>
          </button>
        </div>

        <div className="table-container" style={{ border: 'none' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Router / IP Node</th>
                <th>Hardware & Arsitektur</th>
                <th>Versi ROS Terpasang</th>
                <th>Status Pembaruan</th>
                <th>Bootloader Firmware</th>
                <th style={{ textAlign: 'right' }}>Aksi Smart ROS</th>
              </tr>
            </thead>
            <tbody>
              {loading && routers.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '40px' }}>
                    <RefreshCw size={24} className="spin-anim" style={{ margin: '0 auto 10px', color: 'var(--accent-cyan)' }} />
                    <p style={{ color: 'var(--text-muted)' }}>Memeriksa firmware dan arsitektur seluruh router...</p>
                  </td>
                </tr>
              ) : routers.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '40px', color: 'var(--text-muted)' }}>
                    Tidak ada router yang terdaftar di konfigurasi.
                  </td>
                </tr>
              ) : (
                routers.map((r, idx) => (
                  <tr key={idx} style={{ opacity: r.online ? 1 : 0.6 }}>
                    {/* Router / IP */}
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className={`pulse-dot ${r.online ? '' : 'offline'}`}></span>
                        <div>
                          <div style={{ fontWeight: 700, color: '#fff' }}>{r.hotspot_name}</div>
                          <div style={{ fontSize: '11px', fontFamily: 'var(--font-mono)', color: 'var(--text-muted)' }}>
                            {r.ip} • {r.session}
                          </div>
                        </div>
                      </div>
                    </td>

                    {/* Hardware & Arch */}
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className={`tag ${getArchBadgeClass(r.architecture)}`}>
                          {r.architecture.toUpperCase()}
                        </span>
                        <div>
                          <div style={{ fontSize: '13px', fontWeight: 600 }}>{r.model}</div>
                          <div style={{ fontSize: '10.5px', color: 'var(--text-muted)' }}>Board: {r.board_name}</div>
                        </div>
                      </div>
                    </td>

                    {/* Current ROS */}
                    <td>
                      <div style={{ fontFamily: 'var(--font-mono)', fontWeight: 700, fontSize: '14px', color: 'var(--text-primary)' }}>
                        v{r.installed_version}
                      </div>
                      <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                        Channel: <strong>{r.channel}</strong>
                      </div>
                    </td>

                    {/* Update Status */}
                    <td>
                      {r.update_available ? (
                        <div>
                          <span className="tag tag-amber" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                            <ArrowUpCircle size={12} /> Update: v{r.latest_version}
                          </span>
                          <div style={{ fontSize: '10px', color: 'var(--accent-amber)', marginTop: '2px' }}>
                            {r.update_status || 'New version available'}
                          </div>
                        </div>
                      ) : (
                        <span className="tag tag-emerald" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
                          <CheckCircle2 size={12} /> Up-to-Date
                        </span>
                      )}
                    </td>

                    {/* Bootloader Firmware */}
                    <td>
                      <div style={{ fontSize: '12px', fontFamily: 'var(--font-mono)' }}>
                        Cur: {r.current_firmware} | Up: {r.upgrade_firmware}
                      </div>
                      {r.firmware_upgrade_available && (
                        <button
                          className="btn btn-secondary btn-sm"
                          style={{
                            marginTop: '4px',
                            padding: '2px 8px',
                            fontSize: '10.5px',
                            color: 'var(--accent-amber)',
                            borderColor: 'rgba(245, 158, 11, 0.4)'
                          }}
                          onClick={() => handleUpgradeFirmware(r)}
                        >
                          Upgrade Bootloader
                        </button>
                      )}
                    </td>

                    {/* Actions */}
                    <td style={{ textAlign: 'right' }}>
                      <div style={{ display: 'inline-flex', gap: '6px' }}>
                        {/* Smart Upgrade Button */}
                        <button
                          className="btn btn-primary btn-sm"
                          onClick={() => handleSmartUpgrade(r)}
                          disabled={!r.online}
                          title="Smart Upgrade ke versi terbaru"
                          style={{ padding: '6px 10px' }}
                        >
                          <ArrowUpCircle size={14} />
                          <span>Smart Upgrade</span>
                        </button>

                        {/* Specific Version Upgrade / Downgrade */}
                        <button
                          className="btn btn-secondary btn-sm"
                          onClick={() => {
                            setVersionModal({
                              open: true,
                              router: r,
                              targetVersion: commonVersions[0] || '7.14.3',
                              isCustom: false,
                              processing: false
                            });
                          }}
                          disabled={!r.online}
                          title="Pilih versi khusus (Upgrade atau Downgrade)"
                          style={{ padding: '6px 10px' }}
                        >
                          <ArrowDownCircle size={14} color="var(--accent-cyan)" />
                          <span>Pilih Versi</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* MODAL: TARGET VERSION (UPGRADE / DOWNGRADE) */}
      {versionModal.open && versionModal.router && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          backdropFilter: 'blur(6px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '20px',
          zIndex: 60
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '520px',
            border: '1px solid var(--border-active)',
            boxShadow: '0 0 40px rgba(0, 210, 211, 0.2)'
          }}>
            <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff', marginBottom: '8px' }}>
              Target Versi RouterOS (Smart Upgrade / Downgrade)
            </h3>
            <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)', marginBottom: '18px' }}>
              Menginstal versi spesifik pada <strong>{versionModal.router.hotspot_name}</strong>.
            </p>

            {/* Hardware Arch Info */}
            <div style={{
              padding: '12px 14px',
              background: 'var(--bg-surface)',
              border: '1px solid var(--border-subtle)',
              borderRadius: 'var(--radius-md)',
              marginBottom: '18px',
              fontSize: '12px',
              display: 'grid',
              gridTemplateColumns: '1fr 1fr',
              gap: '8px'
            }}>
              <div><strong>Model:</strong> {versionModal.router.model}</div>
              <div><strong>Arsitektur:</strong> <span className={`tag ${getArchBadgeClass(versionModal.router.architecture)}`}>{versionModal.router.architecture}</span></div>
              <div><strong>Versi Saat Ini:</strong> v{versionModal.router.installed_version}</div>
              <div><strong>Disk Tersedia:</strong> {versionModal.router.free_hdd_space}</div>
            </div>

            <form onSubmit={handleSubmitTargetVersion}>
              <div style={{ marginBottom: '16px' }}>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Pilih Target Versi RouterOS
                </label>
                <select
                  className="filter-select"
                  style={{ width: '100%', marginBottom: '8px' }}
                  value={versionModal.isCustom ? 'custom' : versionModal.targetVersion}
                  onChange={e => {
                    if (e.target.value === 'custom') {
                      setVersionModal(prev => ({ ...prev, isCustom: true, targetVersion: '' }));
                    } else {
                      setVersionModal(prev => ({ ...prev, isCustom: false, targetVersion: e.target.value }));
                    }
                  }}
                >
                  {commonVersions.map(v => (
                    <option key={v} value={v}>RouterOS v{v} {v.startsWith('6.') ? '(Legacy v6)' : '(v7)'}</option>
                  ))}
                  <option value="custom">-- Ketik Versi Kustom Lainnya --</option>
                </select>

                {versionModal.isCustom && (
                  <input
                    type="text"
                    placeholder="Contoh: 7.13.3 atau 6.49.10"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={versionModal.targetVersion}
                    onChange={e => setVersionModal(prev => ({ ...prev, targetVersion: e.target.value }))}
                    required
                  />
                )}
              </div>

              {/* Notice */}
              <div style={{
                padding: '10px 14px',
                background: 'rgba(245, 158, 11, 0.1)',
                border: '1px solid rgba(245, 158, 11, 0.3)',
                borderRadius: 'var(--radius-sm)',
                fontSize: '11.5px',
                color: 'var(--accent-amber)',
                marginBottom: '20px'
              }}>
                <AlertTriangle size={14} style={{ display: 'inline', marginRight: '6px' }} />
                Sistem akan secara cerdas mengunduh paket resmi MikroTik untuk arsitektur <strong>{versionModal.router.architecture}</strong>, mengunggah ke router, dan memicu <em>downgrade/reboot</em> secara otomatis.
              </div>

              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button
                  type="button"
                  className="btn btn-secondary"
                  onClick={() => setVersionModal({ open: false, router: null, targetVersion: '', isCustom: false, processing: false })}
                  disabled={versionModal.processing}
                >
                  Batal
                </button>

                <button
                  type="submit"
                  className="btn btn-primary"
                  disabled={versionModal.processing || !versionModal.targetVersion}
                >
                  {versionModal.processing ? (
                    <>
                      <RefreshCw size={15} className="spin-anim" />
                      <span>Mengunduh & Memasang...</span>
                    </>
                  ) : (
                    <>
                      <Download size={15} />
                      <span>Pasang Versi {versionModal.targetVersion}</span>
                    </>
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL: MASS UPGRADE */}
      {massModal.open && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          backdropFilter: 'blur(6px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '20px',
          zIndex: 60
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '560px',
            border: '1px solid var(--accent-amber)',
            boxShadow: '0 0 40px rgba(245, 158, 11, 0.2)'
          }}>
            <h3 style={{ fontSize: '18px', fontWeight: 800, color: '#fff', marginBottom: '6px', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <ArrowUpCircle size={22} color="var(--accent-amber)" />
              Upgrade Massal Seluruh Router (Mass Fleet Upgrade)
            </h3>
            <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)', marginBottom: '16px' }}>
              Pembaruan serentak paket RouterOS di seluruh node MikroTik (CCR Tile, ARM, ARM64, x86).
            </p>

            {!massModal.results ? (
              <div>
                <div style={{ marginBottom: '14px' }}>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Channel Pembaruan
                  </label>
                  <select
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={massModal.channel}
                    onChange={e => setMassModal(prev => ({ ...prev, channel: e.target.value }))}
                  >
                    <option value="stable">Stable (Disarankan)</option>
                    <option value="long-term">Long-term (Paling Stabil)</option>
                    <option value="testing">Testing (Fitur Eksperimental)</option>
                  </select>
                </div>

                <div style={{ marginBottom: '16px' }}>
                  <div style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', marginBottom: '8px' }}>
                    Pilih Router yang Akan Diupgrade:
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', maxHeight: '180px', overflowY: 'auto' }}>
                    {routers.map((r, idx) => (
                      <div 
                        key={idx}
                        onClick={() => {
                          if (!r.online) return;
                          setMassModal(prev => ({
                            ...prev,
                            selectedSessions: {
                              ...prev.selectedSessions,
                              [r.session]: !prev.selectedSessions[r.session]
                            }
                          }));
                        }}
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'space-between',
                          padding: '10px 14px',
                          background: 'var(--bg-surface)',
                          border: '1px solid var(--border-subtle)',
                          borderRadius: 'var(--radius-sm)',
                          cursor: r.online ? 'pointer' : 'not-allowed',
                          opacity: r.online ? 1 : 0.4
                        }}
                      >
                        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                          {massModal.selectedSessions[r.session] ? (
                            <CheckSquare size={16} color="var(--accent-cyan)" />
                          ) : (
                            <Square size={16} color="var(--text-muted)" />
                          )}
                          <div>
                            <div style={{ fontSize: '13px', fontWeight: 600, color: '#fff' }}>{r.hotspot_name}</div>
                            <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>{r.model} • {r.architecture}</div>
                          </div>
                        </div>

                        <div style={{ fontSize: '12px', fontFamily: 'var(--font-mono)' }}>
                          v{r.installed_version} → {r.latest_version !== 'N/A' ? `v${r.latest_version}` : 'Cek'}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                <div style={{
                  padding: '10px 14px',
                  background: 'rgba(239, 68, 68, 0.1)',
                  border: '1px solid rgba(239, 68, 68, 0.3)',
                  borderRadius: 'var(--radius-sm)',
                  fontSize: '11.5px',
                  color: 'var(--accent-rose)',
                  marginBottom: '20px'
                }}>
                  <AlertTriangle size={14} style={{ display: 'inline', marginRight: '6px' }} />
                  Router yang memerlukan pembaruan akan mengunduh paket resmi sesuai arsitekturnya masing-masing dan melakukan restart otomatis secara bersamaan.
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                  <button
                    className="btn btn-secondary"
                    onClick={() => setMassModal(prev => ({ ...prev, open: false }))}
                    disabled={massModal.processing}
                  >
                    Batal
                  </button>

                  <button
                    className="btn btn-primary"
                    onClick={handleExecuteMassUpgrade}
                    disabled={massModal.processing}
                    style={{ background: 'linear-gradient(135deg, #f59e0b 0%, #ef4444 100%)' }}
                  >
                    {massModal.processing ? (
                      <>
                        <RefreshCw size={15} className="spin-anim" />
                        <span>Mengeksekusi Mass Upgrade...</span>
                      </>
                    ) : (
                      <>
                        <ArrowUpCircle size={15} />
                        <span>Mulai Mass Upgrade Sekarang</span>
                      </>
                    )}
                  </button>
                </div>
              </div>
            ) : (
              <div>
                <div style={{
                  padding: '12px 14px',
                  background: 'rgba(16, 185, 129, 0.1)',
                  border: '1px solid var(--accent-emerald)',
                  borderRadius: 'var(--radius-sm)',
                  marginBottom: '16px',
                  color: 'var(--accent-emerald)',
                  fontSize: '13px'
                }}>
                  <CheckCircle2 size={16} style={{ display: 'inline', marginRight: '6px' }} />
                  Mass Upgrade berhasil dipicu pada <strong>{massModal.results.upgrading_count} router</strong>!
                </div>

                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '20px' }}>
                  {massModal.results.details?.map((res, idx) => (
                    <div key={idx} style={{
                      padding: '8px 12px',
                      background: 'var(--bg-surface)',
                      borderRadius: 'var(--radius-sm)',
                      fontSize: '12.5px',
                      display: 'flex',
                      justifyContent: 'space-between'
                    }}>
                      <span><strong>{res.session}</strong></span>
                      <span className={`tag ${res.status === 'upgrading' ? 'tag-emerald' : 'tag-cyan'}`}>
                        {res.status === 'upgrading' ? `Upgrading ke v${res.to_version}` : res.message}
                      </span>
                    </div>
                  ))}
                </div>

                <div style={{ textAlign: 'right' }}>
                  <button
                    className="btn btn-primary"
                    onClick={() => {
                      setMassModal({ open: false, channel: 'stable', selectedSessions: {}, processing: false, results: null });
                      fetchRouters(false);
                    }}
                  >
                    Tutup & Pantau Router
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL: BOOTLOADER UPGRADE CONFIRM */}
      {fwModal.open && fwModal.router && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.8)',
          backdropFilter: 'blur(6px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '20px',
          zIndex: 60
        }}>
          <div className="glass-card" style={{ width: '100%', maxWidth: '460px' }}>
            <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff', marginBottom: '8px' }}>
              Upgrade Bootloader RouterBOARD
            </h3>
            <p style={{ fontSize: '13px', color: 'var(--text-secondary)', marginBottom: '14px' }}>
              Anda akan memperbarui firmware bootloader pada <strong>{fwModal.router.hotspot_name}</strong>:
            </p>

            <div style={{
              padding: '12px',
              background: 'var(--bg-surface)',
              borderRadius: 'var(--radius-sm)',
              marginBottom: '16px',
              fontFamily: 'var(--font-mono)',
              fontSize: '12px'
            }}>
              <div>Firmware Saat Ini: <strong>{fwModal.router.current_firmware}</strong></div>
              <div style={{ color: 'var(--accent-emerald)' }}>Firmware Pembaruan: <strong>{fwModal.router.upgrade_firmware}</strong></div>
            </div>

            <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginBottom: '20px' }}>
              Setelah perintah dijalankan, firmware baru akan aktif setelah router direboot berikutnya.
            </p>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
              <button
                className="btn btn-secondary"
                onClick={() => setFwModal({ open: false, router: null, processing: false })}
                disabled={fwModal.processing}
              >
                Batal
              </button>

              <button
                className="btn btn-primary"
                onClick={confirmUpgradeFirmware}
                disabled={fwModal.processing}
              >
                {fwModal.processing ? (
                  <>
                    <RefreshCw size={14} className="spin-anim" />
                    <span>Memperbarui Firmware...</span>
                  </>
                ) : (
                  <span>Upgrade Bootloader Sekarang</span>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
