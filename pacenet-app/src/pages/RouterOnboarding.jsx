import React, { useState, useEffect } from 'react';
import { 
  Sparkles, 
  Copy, 
  Check, 
  Terminal, 
  Server, 
  CheckCircle2, 
  XCircle, 
  RefreshCw, 
  Trash2, 
  AlertTriangle, 
  ExternalLink,
  ShieldCheck,
  Zap,
  ArrowRight,
  Edit3,
  Key,
  Eye,
  EyeOff,
  X
} from 'lucide-react';
import { authFetch, getAuthHeaders } from '../utils/api';

export default function RouterOnboarding({ isReadOnly }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [copied, setCopied] = useState(false);

  // Form states for pending routers: { [identity]: { user, pass, hs_iface } }
  const [formData, setFormData] = useState({});
  const [actionLoading, setActionLoading] = useState(null);
  const [feedback, setFeedback] = useState(null);

  // Edit Router Modal State
  const [editModalOpen, setEditModalOpen] = useState(false);
  const [editLoading, setEditLoading] = useState(false);
  const [editFeedback, setEditFeedback] = useState(null);
  const [showPass, setShowPass] = useState(false);
  const [testResult, setTestResult] = useState(null);
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

  const handleOpenEdit = (reg) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Pengubahan kredensial router dinonaktifkan.');
      return;
    }
    setEditForm({
      session: reg.session,
      new_session: reg.session,
      ip: reg.ip,
      user: reg.user || 'admin',
      pass: '',
      hotspot_name: reg.hotspot_name || reg.session,
      dns_name: reg.dns_name || 'hotspot.yunus',
      currency: reg.currency || 'Rp'
    });
    setTestResult(null);
    setEditFeedback(null);
    setShowPass(false);
    setEditModalOpen(true);
  };

  const handleTestCredentials = async () => {
    setTestResult({ loading: true });
    try {
      const res = await authFetch('/api/routers.php?action=test_credentials', {
        method: 'POST',
        body: JSON.stringify({
          session: editForm.session,
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

  const handleSaveEdit = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setEditFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only.' });
      return;
    }
    setEditLoading(true);
    setEditFeedback(null);

    try {
      const res = await authFetch('/api/routers.php?action=update_credentials', {
        method: 'POST',
        body: JSON.stringify(editForm)
      });
      const json = await res.json();
      if (json.success) {
        setEditModalOpen(false);
        fetchOnboarding();
        setFeedback({ type: 'success', text: json.message || 'Kredensial dan informasi router berhasil disimpan.' });
      } else {
        setEditFeedback({ type: 'error', text: json.message || 'Gagal menyimpan perubahan router.' });
      }
    } catch (e) {
      setEditFeedback({ type: 'error', text: 'Terjadi kesalahan jaringan saat menyimpan perubahan.' });
    } finally {
      setEditLoading(false);
    }
  };

  const fetchOnboarding = async () => {
    try {
      const res = await fetch('/api/onboarding.php', {
        credentials: 'include',
        headers: getAuthHeaders()
      });
      const json = await res.json();
      if (json.success) {
        setData(json.data);
      }
    } catch (e) {
      console.error('Failed to load onboarding info', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOnboarding();
    // Auto-poll pending routers every 4 seconds
    const interval = setInterval(fetchOnboarding, 4000);
    return () => clearInterval(interval);
  }, []);

  const serverHost = window.location.hostname || 'hi1271.my.id';
  const bootstrapCmd = data?.bootstrap_command || 
    `/tool fetch url="http://${serverHost}:8080/join.php" mode=http dst-path=join.rsc; :delay 2s; /import join.rsc; /file remove join.rsc`;

  const handleCopy = () => {
    navigator.clipboard.writeText(bootstrapCmd);
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  };

  const getFormVal = (identity, field, defaultVal) => {
    if (formData[identity] && formData[identity][field] !== undefined) {
      return formData[identity][field];
    }
    return defaultVal;
  };

  const setFormVal = (identity, field, value) => {
    setFormData(prev => ({
      ...prev,
      [identity]: {
        ...(prev[identity] || {}),
        [field]: value
      }
    }));
  };

  // Accept pending router
  const handleAccept = async (router) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penerimaan router baru dinonaktifkan.' });
      return;
    }
    const user = getFormVal(router.identity, 'user', 'admin');
    const pass = getFormVal(router.identity, 'pass', '');
    const hsIface = getFormVal(router.identity, 'hs_iface', 'Vlan1');

    setActionLoading(router.identity);
    setFeedback(null);

    try {
      const res = await fetch('/api/onboarding.php?action=accept', {
        method: 'POST',
        credentials: 'include',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          identity: router.identity,
          vpn_ip: router.vpn_ip,
          winbox_port: router.winbox_port,
          user,
          pass,
          hs_iface: hsIface
        })
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message || `Router ${router.identity} berhasil diterima dan dikonfigurasi otomatis!` });
        fetchOnboarding();
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal menerima router. Periksa kredensial.' });
      }
    } catch (e) {
      setFeedback({ type: 'error', text: 'Terjadi kesalahan koneksi saat menerima router.' });
    } finally {
      setActionLoading(null);
    }
  };

  // Reject pending router
  const handleReject = async (router) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penolakan router dinonaktifkan.' });
      return;
    }
    if (!window.confirm(`Yakin ingin menolak dan menghapus permintaan router "${router.identity}"?`)) return;

    setActionLoading(router.identity);
    try {
      const res = await fetch('/api/onboarding.php?action=reject', {
        method: 'POST',
        credentials: 'include',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          identity: router.identity,
          vpn_ip: router.vpn_ip
        })
      });
      const json = await res.json();
      setFeedback({ type: 'info', text: json.message });
      fetchOnboarding();
    } catch (e) {
      setFeedback({ type: 'error', text: 'Gagal menolak router.' });
    } finally {
      setActionLoading(null);
    }
  };

  // Delete registered router
  const handleDeleteRegistered = async (sessName) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan router dinonaktifkan.' });
      return;
    }
    if (!window.confirm(`PERINGATAN: Yakin ingin menghapus sesi router "${sessName}" dari sistem Pacenet?`)) return;

    try {
      const res = await fetch('/api/onboarding.php?action=delete_registered', {
        method: 'POST',
        credentials: 'include',
        headers: getAuthHeaders(),
        body: JSON.stringify({ session: sessName })
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message });
        fetchOnboarding();
      } else {
        setFeedback({ type: 'error', text: json.message });
      }
    } catch (e) {
      setFeedback({ type: 'error', text: 'Gagal menghapus router.' });
    }
  };

  const pendingList = data?.pending_routers || [];
  const registeredList = data?.registered_routers || [];

  return (
    <div>
      {/* Top Header */}
      <div style={{ marginBottom: '20px' }}>
        <h2 style={{ fontSize: '19px', fontWeight: 800, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
          <Sparkles size={22} color="var(--accent-cyan)" />
          Zero-Touch Router Onboarding
        </h2>
        <p style={{ fontSize: '13px', color: 'var(--text-secondary)' }}>
          Hubungkan router MikroTik baru ke Pacenet Billing System secara otomatis tanpa konfigurasi VPN manual.
        </p>
      </div>

      {/* Feedback Banner */}
      {feedback && (
        <div style={{
          padding: '12px 18px',
          marginBottom: '20px',
          borderRadius: 'var(--radius-md)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          background: feedback.type === 'error' ? 'rgba(244, 63, 94, 0.15)' :
                      feedback.type === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(0, 210, 211, 0.15)',
          border: `1px solid ${feedback.type === 'error' ? 'var(--accent-rose)' :
                               feedback.type === 'success' ? 'var(--accent-emerald)' : 'var(--accent-cyan)'}`,
          color: feedback.type === 'error' ? 'var(--accent-rose)' :
                 feedback.type === 'success' ? 'var(--accent-emerald)' : 'var(--accent-cyan)',
          fontSize: '13px'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            {feedback.type === 'error' ? <XCircle size={18} /> : <CheckCircle2 size={18} />}
            <span>{feedback.text}</span>
          </div>
          <button onClick={() => setFeedback(null)} style={{ color: 'inherit', fontWeight: 700 }}>&times;</button>
        </div>
      )}

      {/* Prominent Zero-Touch Onboarding CLI Box (Matching screenshot) */}
      <div className="glass-card highlight" style={{
        marginBottom: '24px',
        padding: '22px 24px',
        background: 'linear-gradient(135deg, rgba(19, 26, 38, 0.9) 0%, rgba(13, 20, 32, 0.95) 100%)',
        border: '1px solid var(--border-active)'
      }}>
        <div style={{
          display: 'flex',
          alignItems: 'flex-start',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '16px',
          marginBottom: '14px'
        }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--accent-cyan)', fontWeight: 700, fontSize: '15px' }}>
              <Zap size={18} />
              <span>Hubungkan Router Baru Otomatis (Zero-Touch Onboarding)</span>
            </div>
            <p style={{ fontSize: '13px', color: 'var(--text-secondary)', marginTop: '4px' }}>
              Untuk menghubungkan router MikroTik baru ke <strong>Pacenet Billing System</strong>, jalankan perintah CLI berikut di <strong>New Terminal MikroTik</strong>:
            </p>
          </div>

          <button
            className="btn btn-primary"
            onClick={handleCopy}
            style={{
              padding: '10px 18px',
              fontSize: '13px',
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              boxShadow: copied ? '0 0 15px var(--accent-emerald-glow)' : '0 0 15px var(--accent-cyan-glow)'
            }}
          >
            {copied ? <Check size={16} /> : <Copy size={16} />}
            <span>{copied ? 'Perintah Tersalin!' : 'Salin Perintah CLI'}</span>
          </button>
        </div>

        {/* CLI Command Box */}
        <div style={{
          background: '#090d14',
          border: '1px solid rgba(0, 210, 211, 0.25)',
          borderRadius: 'var(--radius-md)',
          padding: '14px 18px',
          fontFamily: 'var(--font-mono)',
          fontSize: '13px',
          color: 'var(--accent-cyan)',
          overflowX: 'auto',
          wordBreak: 'break-all',
          lineHeight: '1.6'
        }}>
          {bootstrapCmd}
        </div>

        <div style={{ fontSize: '11.5px', color: 'var(--text-muted)', marginTop: '10px' }}>
          * Router akan otomatis membuat interface WireGuard, terhubung ke cloud VPN (10.10.10.x), dan muncul di daftar persetujuan ini secara instan.
        </div>
      </div>

      {/* Visual Step-by-Step Guide */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: '14px',
        marginBottom: '26px'
      }}>
        <div className="glass-card" style={{ padding: '16px' }}>
          <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--accent-cyan)', marginBottom: '4px' }}>01</div>
          <h4 style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff', marginBottom: '4px' }}>Salin Skrip</h4>
          <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
            Klik tombol "Salin Perintah CLI" di atas untuk menyalin skrip bootstrap.
          </p>
        </div>

        <div className="glass-card" style={{ padding: '16px' }}>
          <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--accent-emerald)', marginBottom: '4px' }}>02</div>
          <h4 style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff', marginBottom: '4px' }}>Buka New Terminal</h4>
          <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
            Buka Winbox di router MikroTik target, klik menu <strong>New Terminal</strong>.
          </p>
        </div>

        <div className="glass-card" style={{ padding: '16px' }}>
          <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--accent-blue)', marginBottom: '4px' }}>03</div>
          <h4 style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff', marginBottom: '4px' }}>Paste & Jalankan</h4>
          <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
            Paste perintah ke terminal lalu tekan Enter. VPN WireGuard otomatis aktif dalam 2 detik.
          </p>
        </div>

        <div className="glass-card" style={{ padding: '16px' }}>
          <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--accent-purple)', marginBottom: '4px' }}>04</div>
          <h4 style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff', marginBottom: '4px' }}>Terima & Auto-Push</h4>
          <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
            Router muncul di daftar persetujuan bawah. Klik "Terima" untuk auto-konfigurasi DNS & RADIUS.
          </p>
        </div>
      </div>

      {/* PENDING APPROVAL SECTION */}
      <div className="glass-card" style={{ marginBottom: '26px', padding: '0', overflow: 'hidden' }}>
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
            <h3 style={{ fontSize: '16px', fontWeight: 700, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Server size={18} color="var(--accent-amber)" />
              Router Menunggu Persetujuan ({pendingList.length})
            </h3>
            <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
              Router baru yang baru saja menjalankan skrip bootstrap dan menunggu konfirmasi operator
            </p>
          </div>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={fetchOnboarding}
            disabled={loading}
          >
            <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
            <span>Refresh</span>
          </button>
        </div>

        {pendingList.length === 0 ? (
          <div style={{
            textAlign: 'center',
            padding: '40px 20px',
            color: 'var(--text-muted)'
          }}>
            <CheckCircle2 size={36} style={{ color: 'var(--accent-emerald)', margin: '0 auto 10px', opacity: 0.6 }} />
            <p style={{ fontSize: '14px', fontWeight: 600, color: '#fff' }}>Tidak ada router baru yang menunggu persetujuan</p>
            <p style={{ fontSize: '12px', marginTop: '4px' }}>
              Jalankan perintah CLI bootstrap pada router MikroTik baru agar muncul di sini secara otomatis.
            </p>
          </div>
        ) : (
          <div style={{ padding: '16px' }}>
            {pendingList.map((r, idx) => {
              const isBusy = actionLoading === r.identity;
              return (
                <div 
                  key={idx}
                  style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-active)',
                    borderRadius: 'var(--radius-md)',
                    padding: '18px 20px',
                    marginBottom: idx < pendingList.length - 1 ? '14px' : '0',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: '14px'
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                      <span className={`pulse-dot ${r.online ? '' : 'offline'}`} />
                      <div>
                        <div style={{ fontSize: '16px', fontWeight: 800, color: '#fff' }}>{r.identity}</div>
                        <div style={{ fontSize: '12px', color: 'var(--text-muted)', fontFamily: 'var(--font-mono)' }}>
                          IP VPN: <strong style={{ color: 'var(--accent-cyan)' }}>{r.vpn_ip}</strong> • Winbox: <strong>{r.winbox_addr}</strong>
                        </div>
                      </div>
                    </div>

                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                      <span className="tag tag-purple">{r.model || 'MikroTik'}</span>
                      <span className="tag tag-cyan">{r.version || 'RouterOS'}</span>
                      <span className={`tag ${r.online ? 'tag-emerald' : 'tag-rose'}`}>
                        {r.online ? 'ONLINE (SIAP DIHUBUNGKAN)' : (r.expires_in !== undefined && r.expires_in > 0 ? `MENUNGGU (${r.expires_in}s)` : 'OFFLINE')}
                      </span>
                    </div>
                  </div>

                  {/* Helper note for password & interface */}
                  <div style={{ fontSize: '11px', color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <Sparkles size={13} color="var(--accent-cyan)" />
                    <span>Kosongkan kata sandi jika router baru/default pabrik. Konfigurasi RADIUS Client & Hotspot Profile akan disetup otomatis.</span>
                  </div>

                  {/* Input Form for Router Credentials */}
                  <div style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
                    gap: '10px',
                    padding: '12px 14px',
                    background: 'var(--bg-input)',
                    borderRadius: 'var(--radius-sm)',
                    border: '1px solid var(--border-subtle)'
                  }}>
                    <div>
                      <label style={{ fontSize: '11px', color: 'var(--text-muted)', display: 'block', marginBottom: '4px' }}>
                        Username Router
                      </label>
                      <input
                        type="text"
                        className="filter-select"
                        style={{ width: '100%', padding: '6px 10px', fontSize: '12px' }}
                        value={getFormVal(r.identity, 'user', 'admin')}
                        onChange={e => setFormVal(r.identity, 'user', e.target.value)}
                      />
                    </div>

                    <div>
                      <label style={{ fontSize: '11px', color: 'var(--text-muted)', display: 'block', marginBottom: '4px' }}>
                        Password Router (Kosong = Default)
                      </label>
                      <input
                        type="password"
                        placeholder="Kosong jika baru reset..."
                        className="filter-select"
                        style={{ width: '100%', padding: '6px 10px', fontSize: '12px' }}
                        value={getFormVal(r.identity, 'pass', '')}
                        onChange={e => setFormVal(r.identity, 'pass', e.target.value)}
                      />
                    </div>

                    <div>
                      <label style={{ fontSize: '11px', color: 'var(--text-muted)', display: 'block', marginBottom: '4px' }}>
                        Interface Hotspot
                      </label>
                      <input
                        type="text"
                        placeholder="Contoh: Vlan1 atau ether2"
                        className="filter-select"
                        style={{ width: '100%', padding: '6px 10px', fontSize: '12px' }}
                        value={getFormVal(r.identity, 'hs_iface', 'Vlan1')}
                        onChange={e => setFormVal(r.identity, 'hs_iface', e.target.value)}
                      />
                    </div>
                  </div>

                  {/* Actions */}
                  <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                    <button
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleReject(r)}
                      disabled={isBusy}
                    >
                      <XCircle size={14} color="var(--accent-rose)" />
                      <span>Tolak Permintaan</span>
                    </button>

                    <button
                      className="btn btn-primary btn-sm"
                      onClick={() => handleAccept(r)}
                      disabled={isBusy}
                      style={{
                        background: r.online 
                          ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' 
                          : 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
                        borderColor: r.online ? '#10b981' : '#0ea5e9',
                        fontWeight: 700,
                        boxShadow: r.online ? '0 0 12px rgba(16, 185, 129, 0.4)' : 'none'
                      }}
                    >
                      {isBusy ? (
                        <>
                          <RefreshCw size={14} className="spin-anim" />
                          <span>Menerima & Auto-Push Konfigurasi...</span>
                        </>
                      ) : (
                        <>
                          <CheckCircle2 size={14} />
                          <span>Terima & Konfigurasi Otomatis (Accept)</span>
                        </>
                      )}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* REGISTERED ROUTERS TABLE */}
      <div className="glass-card" style={{ padding: '0', overflow: 'hidden' }}>
        <div style={{ padding: '16px 20px', borderBottom: '1px solid var(--border-subtle)' }}>
          <h3 style={{ fontSize: '16px', fontWeight: 700, color: '#fff' }}>
            Router Terdaftar & Aktif di Sistem ({registeredList.length})
          </h3>
          <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
            Daftar sesi router yang telah terhubung ke Pacenet Billing System
          </p>
        </div>

        <div className="table-container" style={{ border: 'none' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Status</th>
                <th>Nama Router / Hotspot</th>
                <th>Session ID</th>
                <th>IP VPN WireGuard</th>
                <th style={{ textAlign: 'right' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {registeredList.length === 0 ? (
                <tr>
                  <td colSpan="5" style={{ textAlign: 'center', padding: '36px 20px', color: 'var(--text-muted)' }}>
                    <Server size={32} style={{ color: 'var(--accent-cyan)', margin: '0 auto 8px', opacity: 0.5 }} />
                    <div style={{ fontWeight: 600, color: '#fff', fontSize: '13.5px' }}>Belum ada router yang terdaftar di sistem</div>
                    <div style={{ fontSize: '12px', marginTop: '4px', color: 'var(--text-secondary)' }}>
                      Jalankan perintah CLI Zero-Touch di atas pada New Terminal MikroTik untuk menghubungkan router baru secara otomatis.
                    </div>
                  </td>
                </tr>
              ) : (
                registeredList.map((reg, idx) => (
                  <tr key={idx}>
                    <td>
                      <span className={`pulse-dot ${reg.online ? '' : 'offline'}`} />
                    </td>
                    <td style={{ fontWeight: 700, color: '#fff' }}>
                      {reg.hotspot_name}
                    </td>
                    <td style={{ fontFamily: 'var(--font-mono)', fontSize: '12px', color: 'var(--text-muted)' }}>
                      {reg.session}
                    </td>
                    <td style={{ fontFamily: 'var(--font-mono)', color: 'var(--accent-cyan)' }}>
                      {reg.ip}
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <div style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                        <button
                          className="btn btn-secondary btn-sm"
                          onClick={() => handleOpenEdit(reg)}
                          style={{ padding: '6px 12px', color: 'var(--accent-cyan)', borderColor: 'rgba(0, 210, 211, 0.3)' }}
                          title="Edit Informasi & Kredensial Router"
                        >
                          <Edit3 size={13} />
                          <span>Edit Router</span>
                        </button>
                        <button
                          className="btn btn-danger btn-sm"
                          onClick={() => handleDeleteRegistered(reg.session)}
                          title="Hapus Sesi Router"
                        >
                          <Trash2 size={13} />
                          <span>Hapus Sesi</span>
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

      {/* MODAL: EDIT ROUTER */}
      {editModalOpen && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(4px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 60,
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
                  Edit Informasi Router: {editForm.session}
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
                      placeholder="10.10.10.4"
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
                      placeholder="misal: Hotspot Sentral"
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
                      <RefreshCw size={12} className={testResult?.loading ? 'spin-anim' : ''} />
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
                    style={{
                      background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                      boxShadow: '0 0 12px var(--accent-cyan-glow)'
                    }}
                  >
                    {editLoading ? 'Menyimpan...' : 'Simpan Kredensial'}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
