import React, { useState, useEffect } from 'react';
import { 
  Users, 
  UserPlus, 
  Shield, 
  Edit3, 
  Trash2, 
  KeyRound, 
  CheckCircle2, 
  XCircle, 
  Phone, 
  Store, 
  Clock, 
  Search, 
  RefreshCw, 
  AlertCircle,
  X,
  Lock,
  Crown,
  Briefcase,
  Layers,
  BarChart3
} from 'lucide-react';

export default function UsersManagement({ currentUser, isReadOnly }) {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [modalMode, setModalMode] = useState('create'); // 'create' or 'edit'
  const [selectedUser, setSelectedUser] = useState(null);

  // Form State
  const [formData, setFormData] = useState({
    username: '',
    password: '',
    name: '',
    role: 'reseller',
    kiosk_name: '',
    phone: '',
    status: 'active'
  });
  const [formError, setFormError] = useState('');
  const [formSuccess, setFormSuccess] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchUsers = async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/users.php?action=list');
      const json = await res.json();
      if (json.success) {
        setUsers(json.data || []);
      }
    } catch (e) {
      console.error('Failed to load users', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  const handleOpenCreate = () => {
    setModalMode('create');
    setSelectedUser(null);
    setFormData({
      username: '',
      password: '',
      name: '',
      role: 'reseller',
      kiosk_name: '',
      phone: '',
      status: 'active'
    });
    setFormError('');
    setFormSuccess('');
    setModalOpen(true);
  };

  const handleOpenEdit = (user) => {
    setModalMode('edit');
    setSelectedUser(user);
    setFormData({
      id: user.id,
      username: user.username,
      password: '', // blank unless changing
      name: user.name || '',
      role: user.role || 'reseller',
      kiosk_name: user.kiosk_name || '',
      phone: user.phone || '',
      status: user.status || 'active'
    });
    setFormError('');
    setFormSuccess('');
    setModalOpen(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError('');
    setFormSuccess('');
    setSubmitting(true);

    try {
      const action = modalMode === 'create' ? 'create' : 'update';
      const res = await fetch(`/api/users.php?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });
      const json = await res.json();

      if (json.success) {
        setFormSuccess(json.message || 'Operasi berhasil.');
        setTimeout(() => {
          setModalOpen(false);
          fetchUsers();
        }, 800);
      } else {
        setFormError(json.message || 'Terjadi kesalahan.');
      }
    } catch (err) {
      setFormError('Gagal menghubungi server.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (user) => {
    if (!window.confirm(`Yakin ingin menghapus pengguna '${user.username}'? Tindakan ini tidak dapat dibatalkan.`)) {
      return;
    }

    try {
      const res = await fetch(`/api/users.php?action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: user.id })
      });
      const json = await res.json();
      if (json.success) {
        fetchUsers();
      } else {
        alert(json.message || 'Gagal menghapus pengguna.');
      }
    } catch (e) {
      alert('Gagal menghubungi server.');
    }
  };

  const rolesConfig = {
    owner: { label: 'Owner', color: '#ec4899', bg: 'rgba(236, 72, 153, 0.15)', icon: Crown },
    admin: { label: 'Administrator', color: '#00d2d3', bg: 'rgba(0, 210, 211, 0.15)', icon: Shield },
    manager: { label: 'Manager Operasional', color: '#a855f7', bg: 'rgba(168, 85, 247, 0.15)', icon: Briefcase },
    reseller: { label: 'Reseller Kios', color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.15)', icon: Store },
    staff_noc: { label: 'Staff NOC', color: '#3b82f6', bg: 'rgba(59, 130, 246, 0.15)', icon: Layers },
    finance: { label: 'Finance', color: '#10b981', bg: 'rgba(16, 185, 129, 0.15)', icon: BarChart3 }
  };

  const filteredUsers = users.filter(u => {
    if (!search) return true;
    const s = search.toLowerCase();
    return (
      (u.username && u.username.toLowerCase().includes(s)) ||
      (u.name && u.name.toLowerCase().includes(s)) ||
      (u.kiosk_name && u.kiosk_name.toLowerCase().includes(s)) ||
      (u.role && u.role.toLowerCase().includes(s)) ||
      (u.phone && u.phone.includes(s))
    );
  });

  return (
    <div>
      {/* Header */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: '12px',
        marginBottom: '20px'
      }}>
        <div>
          <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <Users size={20} color="var(--accent-cyan)" />
            Pengelolaan Pengguna Sistem (RBAC)
          </h2>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>
            Manajemen akun operator, pengaturan hak akses role, serta registrasi kios reseller dan staf operasional.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '10px' }}>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={fetchUsers}
            disabled={loading}
            title="Muat ulang data pengguna"
          >
            <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
            <span>Segarkan</span>
          </button>
          {!isReadOnly && (
            <button 
              className="btn btn-primary btn-sm"
              onClick={handleOpenCreate}
            >
              <UserPlus size={14} />
              <span>Tambah Pengguna Baru</span>
            </button>
          )}
        </div>
      </div>

      {/* Overview Stat Cards */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
        gap: '14px',
        marginBottom: '20px'
      }}>
        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(0, 210, 211, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-cyan)' }}>
            <Users size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Total Akun Terdaftar
            </div>
            <div style={{ fontSize: '20px', fontWeight: 800, color: '#fff' }}>
              {users.length} <span style={{ fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)' }}>user</span>
            </div>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(245, 158, 11, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-amber)' }}>
            <Store size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Reseller Kios Aktif
            </div>
            <div style={{ fontSize: '20px', fontWeight: 800, color: 'var(--accent-amber)' }}>
              {users.filter(u => u.role === 'reseller' && u.status === 'active').length} <span style={{ fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)' }}>kios</span>
            </div>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(59, 130, 246, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-blue)' }}>
            <Layers size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Staff NOC & Finance
            </div>
            <div style={{ fontSize: '20px', fontWeight: 800, color: 'var(--accent-blue)' }}>
              {users.filter(u => ['staff_noc', 'finance'].includes(u.role)).length} <span style={{ fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)' }}>staf</span>
            </div>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(16, 185, 129, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-emerald)' }}>
            <CheckCircle2 size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Status Operasional
            </div>
            <div style={{ fontSize: '14px', fontWeight: 700, color: 'var(--accent-emerald)' }}>
              Sistem Aktif & Terlindungi
            </div>
          </div>
        </div>
      </div>

      {/* Main Table Card */}
      <div className="glass-card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{
          padding: '16px 20px',
          borderBottom: '1px solid var(--border-subtle)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '12px'
        }}>
          <div>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff' }}>
              Daftar Pengguna & Hak Akses
            </h3>
            <div style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
              Menampilkan {filteredUsers.length} dari {users.length} akun pengguna
            </div>
          </div>

          <div className="search-box" style={{ maxWidth: '280px', width: '100%' }}>
            <Search size={14} color="var(--text-muted)" />
            <input 
              type="text"
              placeholder="Cari user, nama, kios, role..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>
        </div>

        <div className="table-container" style={{ border: 'none' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Username</th>
                <th>Nama / Kios</th>
                <th>Role Akses</th>
                <th>Status</th>
                <th>Kontak</th>
                <th>Terakhir Login</th>
                <th style={{ textAlign: 'right' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loading && users.length === 0 ? (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '40px 20px' }}>
                    <RefreshCw size={24} className="spin-anim" style={{ margin: '0 auto 10px', color: 'var(--accent-cyan)' }} />
                    <p style={{ color: 'var(--text-muted)', fontSize: '13px' }}>Memuat daftar pengguna...</p>
                  </td>
                </tr>
              ) : filteredUsers.length === 0 ? (
                <tr>
                  <td colSpan={7} style={{ textAlign: 'center', padding: '40px 20px', color: 'var(--text-muted)' }}>
                    Tidak ada pengguna ditemukan{search ? ` dengan pencarian "${search}"` : ''}.
                  </td>
                </tr>
              ) : (
                filteredUsers.map((u, idx) => {
                  const roleMeta = rolesConfig[u.role] || { label: u.role, color: '#94a3b8', bg: 'rgba(148, 163, 184, 0.15)', icon: Shield };
                  const RoleIcon = roleMeta.icon;
                  const isCurrent = (u.username === currentUser);

                  return (
                    <tr key={idx}>
                      {/* Username */}
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 700, color: '#fff', fontSize: '13.5px' }}>
                            {u.username}
                          </span>
                          {isCurrent && (
                            <span style={{ fontSize: '10px', padding: '1px 5px', borderRadius: '4px', background: 'rgba(0, 210, 211, 0.2)', color: 'var(--accent-cyan)', fontWeight: 600 }}>
                              Anda
                            </span>
                          )}
                        </div>
                      </td>

                      {/* Nama & Kios */}
                      <td>
                        <div style={{ fontWeight: 600, color: 'var(--text-primary)' }}>
                          {u.name || '-'}
                        </div>
                        {u.kiosk_name && (
                          <div style={{ fontSize: '11px', color: 'var(--accent-amber)', display: 'flex', alignItems: 'center', gap: '4px', marginTop: '2px' }}>
                            <Store size={11} /> {u.kiosk_name}
                          </div>
                        )}
                      </td>

                      {/* Role Akses */}
                      <td>
                        <span style={{
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: '6px',
                          fontSize: '11.5px',
                          fontWeight: 700,
                          padding: '3px 10px',
                          borderRadius: '12px',
                          background: roleMeta.bg,
                          color: roleMeta.color,
                          border: `1px solid ${roleMeta.color}33`
                        }}>
                          <RoleIcon size={12} />
                          {roleMeta.label}
                        </span>
                      </td>

                      {/* Status */}
                      <td>
                        {u.status === 'active' ? (
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', color: 'var(--accent-emerald)', fontSize: '12px', fontWeight: 600 }}>
                            <span className="pulse-dot-green"></span>
                            Aktif
                          </span>
                        ) : (
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', color: 'var(--accent-rose)', fontSize: '12px', fontWeight: 600 }}>
                            <XCircle size={12} />
                            Nonaktif
                          </span>
                        )}
                      </td>

                      {/* Kontak */}
                      <td>
                        {u.phone ? (
                          <span style={{ fontFamily: 'var(--font-mono)', fontSize: '12px', color: 'var(--text-secondary)' }}>
                            {u.phone}
                          </span>
                        ) : (
                          <span style={{ color: 'var(--text-muted)' }}>-</span>
                        )}
                      </td>

                      {/* Terakhir Login */}
                      <td>
                        <div style={{ fontSize: '11.5px', color: u.last_login ? 'var(--text-primary)' : 'var(--text-muted)' }}>
                          {u.last_login || 'Belum pernah'}
                        </div>
                      </td>

                      {/* Aksi */}
                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'inline-flex', gap: '6px' }}>
                          <button
                            className="btn btn-icon btn-sm btn-secondary"
                            onClick={() => handleOpenEdit(u)}
                            title="Edit pengguna / ubah password"
                            style={{ padding: '5px' }}
                          >
                            <Edit3 size={13} />
                          </button>
                          {!['owner', 'admin'].includes(u.username) && !isCurrent && !isReadOnly && (
                            <button
                              className="btn btn-icon btn-sm btn-secondary"
                              onClick={() => handleDelete(u)}
                              title="Hapus pengguna"
                              style={{ padding: '5px', color: 'var(--accent-rose)' }}
                            >
                              <Trash2 size={13} />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Modal Tambah / Edit Pengguna */}
      {modalOpen && (
        <div style={{
          position: 'fixed',
          inset: 0,
          background: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(5px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '20px',
          zIndex: 60
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '500px',
            padding: '24px 26px',
            maxHeight: '90vh',
            overflowY: 'auto'
          }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <h3 style={{ fontSize: '16px', fontWeight: 800, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
                {modalMode === 'create' ? <UserPlus size={18} color="var(--accent-cyan)" /> : <Edit3 size={18} color="var(--accent-cyan)" />}
                {modalMode === 'create' ? 'Tambah Pengguna Baru' : `Edit Pengguna: ${selectedUser?.username}`}
              </h3>
              <button 
                className="btn btn-icon btn-sm"
                onClick={() => setModalOpen(false)}
                style={{ background: 'transparent', border: 'none', color: 'var(--text-muted)' }}
              >
                <X size={18} />
              </button>
            </div>

            {formError && (
              <div style={{ padding: '10px 14px', background: 'rgba(244, 63, 94, 0.15)', border: '1px solid rgba(244, 63, 94, 0.3)', borderRadius: '6px', color: 'var(--accent-rose)', fontSize: '12.5px', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <AlertCircle size={15} />
                <span>{formError}</span>
              </div>
            )}

            {formSuccess && (
              <div style={{ padding: '10px 14px', background: 'rgba(16, 185, 129, 0.15)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '6px', color: 'var(--accent-emerald)', fontSize: '12.5px', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <CheckCircle2 size={15} />
                <span>{formSuccess}</span>
              </div>
            )}

            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
              {/* Username */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '5px' }}>
                  Username Login
                </label>
                <input
                  type="text"
                  value={formData.username}
                  onChange={e => setFormData({ ...formData, username: e.target.value })}
                  disabled={modalMode === 'edit'}
                  placeholder="e.g. kios_hamadi, noc_andi..."
                  required
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                />
              </div>

              {/* Password */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '5px' }}>
                  {modalMode === 'create' ? 'Password' : 'Password Baru (Kosongkan jika tidak diubah)'}
                </label>
                <input
                  type="password"
                  value={formData.password}
                  onChange={e => setFormData({ ...formData, password: e.target.value })}
                  placeholder={modalMode === 'create' ? 'Minimal 4 karakter...' : 'Ketik password baru...'}
                  required={modalMode === 'create'}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                />
              </div>

              {/* Nama Lengkap */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '5px' }}>
                  Nama Lengkap Operator / Pemilik Kios
                </label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={e => setFormData({ ...formData, name: e.target.value })}
                  placeholder="e.g. Bpk. Hamadi, Ahmad..."
                  required
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                />
              </div>

              {/* Role Dropdown */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '5px' }}>
                  Hak Akses (Role)
                </label>
                <select
                  value={formData.role}
                  onChange={e => setFormData({ ...formData, role: e.target.value })}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    fontSize: '13px',
                    fontWeight: 600
                  }}
                >
                  <option value="owner" style={{ background: '#111722' }}>👑 Owner (Akses Penuh Semua Menu & Pengelolaan User)</option>
                  <option value="admin" style={{ background: '#111722' }}>🛡️ Administrator (Akses Penuh Semua Menu & Pengelolaan User)</option>
                  <option value="manager" style={{ background: '#111722' }}>👔 Manager Operasional (Akses Semua Menu Operasional)</option>
                  <option value="reseller" style={{ background: '#111722' }}>🏪 Reseller Kios (Portal Cek Voucher & Scanner Kamera)</option>
                  <option value="staff_noc" style={{ background: '#111722' }}>⚡ Staff NOC (NOC Dashboard & Core Monitoring)</option>
                  <option value="finance" style={{ background: '#111722' }}>💼 Finance (Rekap Penjualan & Ekspor PDF/Excel/CSV)</option>
                </select>
              </div>

              {/* Nama Kios (jika reseller) */}
              {formData.role === 'reseller' && (
                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--accent-amber)', display: 'block', marginBottom: '5px' }}>
                    Nama Kios / Lokasi Penjualan
                  </label>
                  <input
                    type="text"
                    value={formData.kiosk_name}
                    onChange={e => setFormData({ ...formData, kiosk_name: e.target.value })}
                    placeholder="e.g. Kios Berkah Hamadi, Kios Pelabuhan..."
                    required={formData.role === 'reseller'}
                    style={{
                      width: '100%',
                      padding: '8px 12px',
                      background: 'var(--bg-surface)',
                      border: '1px solid var(--border-subtle)',
                      borderRadius: 'var(--radius-md)',
                      color: '#fff',
                      fontSize: '13px'
                    }}
                  />
                </div>
              )}

              {/* Kontak HP */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '5px' }}>
                  Nomor HP / WhatsApp (Opsional)
                </label>
                <input
                  type="text"
                  value={formData.phone}
                  onChange={e => setFormData({ ...formData, phone: e.target.value })}
                  placeholder="e.g. 081234567890"
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                />
              </div>

              {/* Status */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '5px' }}>
                  Status Akun
                </label>
                <select
                  value={formData.status}
                  onChange={e => setFormData({ ...formData, status: e.target.value })}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                >
                  <option value="active" style={{ background: '#111722' }}>Aktif (Dapat Login)</option>
                  <option value="inactive" style={{ background: '#111722' }}>Nonaktif (Diblokir)</option>
                </select>
              </div>

              {/* Buttons */}
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '12px' }}>
                <button
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={() => setModalOpen(false)}
                  disabled={submitting}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="btn btn-primary btn-sm"
                  disabled={submitting}
                >
                  {submitting ? 'Menyimpan...' : (modalMode === 'create' ? 'Tambah Pengguna' : 'Simpan Perubahan')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
