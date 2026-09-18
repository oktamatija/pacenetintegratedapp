import React, { useState, useEffect } from 'react';
import { 
  Layers, 
  PlusCircle, 
  Printer, 
  Edit3, 
  Trash2, 
  Zap, 
  Clock, 
  Users, 
  DollarSign, 
  Shield, 
  RefreshCw, 
  Check, 
  X, 
  AlertCircle,
  Wifi,
  ChevronRight,
  TrendingUp
} from 'lucide-react';
import { parseBilingualDuration } from '../utils/durationParser';
import { authFetch, getAuthHeaders } from '../utils/api';

export default function UserProfiles({ onNavigate, onQuickPrintProfile, isReadOnly }) {

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedRouter, setSelectedRouter] = useState('');
  const [options, setOptions] = useState({ pools: [], parent_queues: [] });

  // Modals state
  const [showModal, setShowModal] = useState(false); // Add or Edit modal
  const [modalMode, setModalMode] = useState('create'); // 'create' or 'edit'
  const [formData, setFormData] = useState({
    id: '',
    name: '',
    shared_users: '1',
    rate_limit: '5M/5M',
    expmode: 'remc',
    validity: '1d',
    price: '5000',
    sprice: '5000',
    lock: 'Disable',
    address_pool: 'none',
    parent_queue: 'none'
  });

  const [feedback, setFeedback] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Quick print modal/dialog
  const [quickPrintLoading, setQuickPrintLoading] = useState(null);

  const fetchProfiles = async (router = selectedRouter) => {
    try {
      setLoading(true);
      const url = router ? `/api/profiles.php?router=${encodeURIComponent(router)}` : '/api/profiles.php';
      const res = await authFetch(url);
      const json = await res.json();
      if (json.data) {
        setData(json.data);
        if (!selectedRouter && json.data?.current_router) {
          setSelectedRouter(json.data.current_router);
        } else if (!selectedRouter && json.data?.routers?.length > 0) {
          setSelectedRouter(json.data.routers[0].session);
        }
      }
      if (json.message && !json.success) {
        setFeedback({ type: 'error', text: json.message });
      }
    } catch (e) {
      console.error('Failed to load user profiles', e);
      setFeedback({ type: 'error', text: 'Gagal menghubungi server untuk membaca profil.' });
    } finally {
      setLoading(false);
    }
  };

  const fetchOptions = async (router) => {
    if (!router) return;
    try {
      const res = await authFetch(`/api/profiles.php?action=get_options&router=${encodeURIComponent(router)}`);
      const json = await res.json();
      if (json.success && json.data) {
        setOptions(json.data);
      }
    } catch (e) {
      console.error('Failed to load pool options', e);
    }
  };

  useEffect(() => {
    fetchProfiles();
  }, [selectedRouter]);

  useEffect(() => {
    if (selectedRouter) {
      fetchOptions(selectedRouter);
    }
  }, [selectedRouter]);

  // Open Create Modal
  const handleOpenCreate = () => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo hanya memiliki hak akses lihat (Read-Only). Penambahan profil dinonaktifkan.' });
      return;
    }
    setModalMode('create');
    setFormData({
      id: '',
      name: '',
      shared_users: '1',
      rate_limit: '5M/5M',
      expmode: 'remc',
      validity: '1d',
      price: '5000',
      sprice: '5000',
      lock: 'Disable',
      address_pool: options.pools[0] || 'none',
      parent_queue: options.parent_queues[0] || 'none'
    });
    setShowModal(true);
  };

  // Open Edit Modal
  const handleOpenEdit = (p) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo hanya memiliki hak akses lihat (Read-Only). Pengeditan profil dinonaktifkan.' });
      return;
    }
    setModalMode('edit');
    setFormData({
      id: p.id,
      name: p.name,
      shared_users: p.shared_users || '1',
      rate_limit: p.rate_limit || '',
      expmode: p.expmode || 'remc',
      validity: p.validity || '1d',
      price: String(p.price || 0),
      sprice: String(p.sprice || p.price || 0),
      lock: p.lock || 'Disable',
      address_pool: p.address_pool || 'none',
      parent_queue: p.parent_queue || 'none'
    });
    setShowModal(true);
  };

  // Submit Modal (Create or Update)
  const handleSubmit = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penyimpanan profil dinonaktifkan.' });
      return;
    }
    setActionLoading(true);
    setFeedback(null);

    const endpoint = modalMode === 'create' 
      ? `/api/profiles.php?action=create&router=${encodeURIComponent(selectedRouter)}`
      : `/api/profiles.php?action=update&router=${encodeURIComponent(selectedRouter)}`;

    try {
      const res = await authFetch(endpoint, {
        method: 'POST',
        body: JSON.stringify(formData)
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message || 'Profil berhasil disimpan.' });
        setShowModal(false);
        fetchProfiles(selectedRouter);
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal menyimpan profil.' });
      }
    } catch (err) {
      setFeedback({ type: 'error', text: 'Terjadi kesalahan jaringan saat menyimpan profil.' });
    } finally {
      setActionLoading(false);
    }
  };

  // Delete Profile
  const handleDelete = async (p) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan profil dinonaktifkan.' });
      return;
    }
    if (!window.confirm(`Apakah Anda yakin ingin menghapus profil "${p.name}" dari router?`)) return;

    setActionLoading(true);
    setFeedback(null);

    try {
      const res = await authFetch(`/api/profiles.php?action=delete&router=${encodeURIComponent(selectedRouter)}`, {
        method: 'POST',
        body: JSON.stringify({ id: p.id, name: p.name })
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message || `Profil ${p.name} berhasil dihapus.` });
        fetchProfiles(selectedRouter);
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal menghapus profil.' });
      }
    } catch (err) {
      setFeedback({ type: 'error', text: 'Terjadi kesalahan jaringan saat menghapus profil.' });
    } finally {
      setActionLoading(false);
    }
  };

  // Quick Print direct from Profile Card
  const handleQuickPrint = async (p) => {
    setQuickPrintLoading(p.name);
    try {
      const res = await authFetch(`/api/profiles.php?action=quick_vouchers&router=${encodeURIComponent(selectedRouter)}&profile=${encodeURIComponent(p.name)}&limit=55`);
      const json = await res.json();
      if (json.success && json.data?.vouchers?.length > 0) {
        if (onQuickPrintProfile) {
          onQuickPrintProfile(json.data.vouchers, p.name);
        } else {
          onNavigate('print');
        }
      } else {
        alert(`Belum ada voucher aktif yang siap cetak untuk profil "${p.name}". Anda dapat membuat batch voucher baru terlebih dahulu.`);
      }
    } catch (e) {
      alert('Gagal memuat voucher untuk cetak cepat.');
    } finally {
      setQuickPrintLoading(null);
    }
  };

  const profiles = data?.profiles || [];
  const routers = data?.routers || [];

  return (
    <div>
      {/* Header & Router Selector Bar */}
      <div className="glass-card" style={{ marginBottom: '20px', padding: '16px 20px' }}>
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '12px'
        }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Layers size={20} color="var(--accent-cyan)" />
              <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff' }}>
                Pengelolaan Hotspot User Profiles
              </h2>
            </div>
            <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
              Kelola paket bandwidth, masa aktif (validity), harga modal/jual, serta cetak cepat voucher per profil.
            </p>
          </div>

          <div className="profiles-header-controls">
            {/* Router selector */}
            <div className="router-select-wrap" style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Router:</span>
              <select
                className="filter-select"
                value={selectedRouter}
                onChange={e => setSelectedRouter(e.target.value)}
                style={{ padding: '6px 12px', fontSize: '13px', flex: 1 }}
              >
                {routers.map(r => (
                  <option key={r.session} value={r.session}>
                    {r.name} ({r.ip})
                  </option>
                ))}
              </select>
            </div>

            <button 
              className="btn btn-secondary btn-sm"
              onClick={() => fetchProfiles()}
              disabled={loading}
              title="Refresh Profiles"
            >
              <RefreshCw size={14} className={loading ? 'spin' : ''} />
              <span>Refresh</span>
            </button>

            <button 
              className="btn btn-primary btn-sm"
              onClick={handleOpenCreate}
              style={{
                background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                boxShadow: '0 0 12px var(--accent-cyan-glow)'
              }}
            >
              <PlusCircle size={15} />
              <span>Tambah Profil</span>
            </button>
          </div>
        </div>
      </div>

      {/* Feedback Banner */}
      {feedback && (
        <div style={{
          marginBottom: '16px',
          padding: '12px 16px',
          borderRadius: 'var(--radius-md)',
          background: feedback.type === 'success' ? 'rgba(16, 185, 129, 0.12)' : 'rgba(244, 63, 94, 0.12)',
          border: `1px solid ${feedback.type === 'success' ? 'var(--accent-emerald)' : 'var(--accent-rose)'}`,
          color: feedback.type === 'success' ? '#34d399' : '#fb7185',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          fontSize: '13px'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            {feedback.type === 'success' ? <Check size={16} /> : <AlertCircle size={16} />}
            <span>{feedback.text}</span>
          </div>
          <button 
            onClick={() => setFeedback(null)} 
            style={{ background: 'none', border: 'none', color: 'inherit', cursor: 'pointer' }}
          >
            <X size={16} />
          </button>
        </div>
      )}

      {/* Profile Cards Grid */}
      {loading ? (
        <div className="glass-card" style={{ padding: '40px', textAlign: 'center' }}>
          <RefreshCw size={24} className="spin" style={{ color: 'var(--accent-cyan)', marginBottom: '10px' }} />
          <p style={{ color: 'var(--text-muted)', fontSize: '13px' }}>Memuat user profiles dari MikroTik...</p>
        </div>
      ) : profiles.length === 0 ? (
        <div className="glass-card" style={{ padding: '50px 20px', textAlign: 'center' }}>
          <Layers size={36} color="var(--text-muted)" style={{ margin: '0 auto 12px', opacity: 0.5 }} />
          <h4 style={{ color: '#fff', fontSize: '16px', fontWeight: 700 }}>Belum Ada User Profile</h4>
          <p style={{ color: 'var(--text-muted)', fontSize: '12.5px', maxWidth: '400px', margin: '6px auto 16px' }}>
            Buat profil paket internet hotspot pertama Anda untuk menentukan batas kecepatan, durasi, dan harga voucher.
          </p>
          <button className="btn btn-primary" onClick={handleOpenCreate}>
            <PlusCircle size={15} />
            <span>Buat Profil Pertama</span>
          </button>
        </div>
      ) : (
        <div className="profiles-cards-grid">
          {profiles.map(p => {
            const isDefault = p.name.toLowerCase() === 'default';
            const profit = (p.sprice || p.price || 0) - (p.price || 0);

            return (
              <div 
                key={p.name} 
                className="glass-card" 
                style={{
                  padding: '18px',
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                  border: isDefault ? '1px solid var(--border-subtle)' : '1px solid rgba(0, 210, 211, 0.25)',
                  transition: 'all 0.2s ease',
                  position: 'relative',
                  overflow: 'hidden'
                }}
              >
                {/* Card Top / Header */}
                <div>
                  <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '12px' }}>
                    <div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <h3 style={{ fontSize: '16px', fontWeight: 800, color: '#fff' }}>
                          {p.name}
                        </h3>
                        {isDefault && (
                          <span className="tag" style={{ background: 'rgba(255,255,255,0.1)', color: 'var(--text-muted)', fontSize: '10px' }}>
                            Default
                          </span>
                        )}
                      </div>
                      <div style={{ fontSize: '11.5px', color: 'var(--accent-cyan)', fontWeight: 600, marginTop: '2px', display: 'flex', alignItems: 'center', gap: '4px' }}>
                        <Zap size={12} />
                        <span>Rate Limit: {p.rate_limit || 'Unlimited'}</span>
                      </div>
                    </div>

                    <div style={{ textAlign: 'right' }}>
                      <div style={{ fontSize: '15px', fontWeight: 800, color: 'var(--accent-emerald)' }}>
                        Rp {Number(p.sprice || p.price || 0).toLocaleString('id-ID')}
                      </div>
                      {p.price > 0 && p.sprice > p.price && (
                        <div style={{ fontSize: '10.5px', color: 'var(--text-muted)' }}>
                          Modal: Rp {Number(p.price).toLocaleString('id-ID')}
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Attributes Grid */}
                  <div style={{
                    display: 'grid',
                    gridTemplateColumns: '1fr 1fr',
                    gap: '8px',
                    padding: '10px 12px',
                    background: 'rgba(0,0,0,0.25)',
                    borderRadius: 'var(--radius-sm)',
                    marginBottom: '14px',
                    fontSize: '11.5px'
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', color: 'var(--text-secondary)' }}>
                      <Clock size={13} color="var(--accent-amber)" />
                      <span>Masa Aktif: <strong style={{ color: '#fff' }}>{p.validity_display || p.validity || 'Tanpa Batas'}</strong></span>
                    </div>


                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', color: 'var(--text-secondary)' }}>
                      <Users size={13} color="var(--accent-blue)" />
                      <span>Shared: <strong style={{ color: '#fff' }}>{p.shared_users || 1} User</strong></span>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', color: 'var(--text-secondary)' }}>
                      <Shield size={13} color={p.lock === 'Enable' ? 'var(--accent-emerald)' : 'var(--text-muted)'} />
                      <span>Lock MAC: <strong style={{ color: p.lock === 'Enable' ? 'var(--accent-emerald)' : '#fff' }}>{p.lock}</strong></span>
                    </div>

                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', color: 'var(--text-secondary)' }}>
                      <Wifi size={13} color="var(--accent-cyan)" />
                      <span>Mode: <strong style={{ color: '#fff' }}>{p.expmode || 'remc'}</strong></span>
                    </div>
                  </div>

                  {/* Metrics: Registered Users & Active Now */}
                  <div style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    marginBottom: '16px',
                    padding: '0 4px',
                    fontSize: '11.5px',
                    color: 'var(--text-muted)'
                  }}>
                    <span>User Terdaftar: <strong style={{ color: '#fff' }}>{p.total_users || 0}</strong></span>
                    <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                      <span style={{ width: '6px', height: '6px', borderRadius: '50%', background: p.active_users > 0 ? 'var(--accent-emerald)' : 'var(--text-muted)' }} />
                      User Aktif: <strong style={{ color: p.active_users > 0 ? 'var(--accent-emerald)' : '#fff' }}>{p.active_users || 0}</strong>
                    </span>
                  </div>
                </div>

                {/* Card Actions */}
                <div style={{
                  display: 'flex',
                  gap: '8px',
                  borderTop: '1px solid var(--border-subtle)',
                  paddingTop: '12px'
                }}>
                  {/* Quick Print Button */}
                  <button 
                    className="btn btn-primary btn-sm"
                    onClick={() => handleQuickPrint(p)}
                    disabled={quickPrintLoading === p.name}
                    style={{
                      flex: 1,
                      background: 'linear-gradient(135deg, rgba(0, 210, 211, 0.25) 0%, rgba(9, 132, 227, 0.3) 100%)',
                      borderColor: 'var(--accent-cyan)',
                      color: 'var(--accent-cyan)',
                      fontWeight: 700
                    }}
                    title="Cetak Cepat Batch Voucher untuk Profil Ini"
                  >
                    <Printer size={14} className={quickPrintLoading === p.name ? 'spin' : ''} />
                    <span>{quickPrintLoading === p.name ? 'Memuat...' : 'Cetak F4 (55 Slip)'}</span>
                  </button>

                  <button 
                    className="btn btn-secondary btn-sm"
                    onClick={() => handleOpenEdit(p)}
                    style={{ padding: '6px 10px' }}
                    title="Edit Profil"
                  >
                    <Edit3 size={14} />
                  </button>

                  {!isDefault && (
                    <button 
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleDelete(p)}
                      style={{ padding: '6px 10px', color: 'var(--accent-rose)', borderColor: 'rgba(244, 63, 94, 0.3)' }}
                      title="Hapus Profil"
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Modal: Tambah / Edit User Profile */}
      {showModal && (
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
                <Layers size={18} color="var(--accent-cyan)" />
                <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff' }}>
                  {modalMode === 'create' ? 'Tambah User Profile Baru' : `Edit Profile: ${formData.name}`}
                </h3>
              </div>
              <button 
                onClick={() => setShowModal(false)}
                style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                {/* Profile Name */}
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                    Nama Profil * (Contoh: 5K-1HARI)
                  </label>
                  <input
                    type="text"
                    className="input-field"
                    value={formData.name}
                    onChange={e => setFormData({ ...formData, name: e.target.value })}
                    placeholder="misal: 1HARI-5RB"
                    required
                    disabled={modalMode === 'edit'}
                    style={{ width: '100%' }}
                  />
                </div>

                {/* Rate Limit & Shared Users */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Rate Limit (Tx/Rx)
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={formData.rate_limit}
                      onChange={e => setFormData({ ...formData, rate_limit: e.target.value })}
                      placeholder="misal: 5M/5M"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Shared Users
                    </label>
                    <input
                      type="number"
                      min="1"
                      className="input-field"
                      value={formData.shared_users}
                      onChange={e => setFormData({ ...formData, shared_users: e.target.value })}
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                {/* Expired Mode & Validity */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Expired Mode
                    </label>
                    <select
                      className="filter-select"
                      value={formData.expmode}
                      onChange={e => setFormData({ ...formData, expmode: e.target.value })}
                      style={{ width: '100%' }}
                    >
                      <option value="remc">Remove & Record (Rekomendasi)</option>
                      <option value="ntfc">Notice & Record</option>
                      <option value="rem">Remove</option>
                      <option value="ntf">Notice</option>
                      <option value="0">None (Tanpa Expire)</option>
                    </select>
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Masa Aktif / Validity (Contoh: 12 jam, 1 hari, 1d, 7d)
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={formData.validity}
                      onChange={e => setFormData({ ...formData, validity: e.target.value })}
                      placeholder="12 jam / 12h / 1 hari / 1d"
                      style={{ width: '100%' }}
                    />
                    {formData.validity && (() => {
                      const p = parseBilingualDuration(formData.validity);
                      return p ? (
                        <div style={{ marginTop: '5px', fontSize: '11px', color: 'var(--accent-emerald)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <span>⏱️ <strong>{p.humanId}</strong> ({p.mikrotik})</span>
                        </div>
                      ) : (
                        <div style={{ marginTop: '5px', fontSize: '11px', color: 'var(--accent-amber)' }}>
                          <span>Format: 12 jam / 12h, 1 hari / 1d, 30 menit</span>
                        </div>
                      );
                    })()}
                  </div>
                </div>


                {/* Price (Modal) & Selling Price (Jual) */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Harga Modal (Rp)
                    </label>
                    <input
                      type="number"
                      className="input-field"
                      value={formData.price}
                      onChange={e => setFormData({ ...formData, price: e.target.value })}
                      placeholder="0"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Harga Jual (Rp)
                    </label>
                    <input
                      type="number"
                      className="input-field"
                      value={formData.sprice}
                      onChange={e => setFormData({ ...formData, sprice: e.target.value })}
                      placeholder="5000"
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                {/* Lock MAC & Address Pool */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Lock User ke MAC Address
                    </label>
                    <select
                      className="filter-select"
                      value={formData.lock}
                      onChange={e => setFormData({ ...formData, lock: e.target.value })}
                      style={{ width: '100%' }}
                    >
                      <option value="Disable">Disable (Bisa Ganti Perangkat)</option>
                      <option value="Enable">Enable (Terkunci ke 1 HP/Laptop)</option>
                    </select>
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Address Pool
                    </label>
                    <select
                      className="filter-select"
                      value={formData.address_pool}
                      onChange={e => setFormData({ ...formData, address_pool: e.target.value })}
                      style={{ width: '100%' }}
                    >
                      {options.pools.map(pl => (
                        <option key={pl} value={pl}>{pl}</option>
                      ))}
                    </select>
                  </div>
                </div>

                {/* Modal Actions */}
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '10px' }}>
                  <button 
                    type="button" 
                    className="btn btn-secondary"
                    onClick={() => setShowModal(false)}
                    disabled={actionLoading}
                  >
                    Batal
                  </button>
                  <button 
                    type="submit" 
                    className="btn btn-primary"
                    disabled={actionLoading}
                    style={{
                      background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)'
                    }}
                  >
                    {actionLoading ? 'Menyimpan...' : (modalMode === 'create' ? 'Buat Profil' : 'Simpan Perubahan')}
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
