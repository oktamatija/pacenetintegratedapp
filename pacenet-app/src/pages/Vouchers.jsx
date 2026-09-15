import React, { useState, useEffect } from 'react';
import { 
  Users, 
  Search, 
  Filter, 
  RefreshCw, 
  Trash2, 
  Power, 
  RotateCcw, 
  ChevronLeft, 
  ChevronRight, 
  CheckCircle2, 
  XCircle,
  PlusCircle,
  Printer,
  Edit3,
  X,
  CheckSquare,
  Square,
  Sparkles,
  Sliders,
  Calendar
} from 'lucide-react';

export default function Vouchers({ onNavigate, setVouchersForPrint, isReadOnly }) {
  const [users, setUsers] = useState([]);
  const [profiles, setProfiles] = useState([]);
  const [selectedStatus, setSelectedStatus] = useState('all');
  const [total, setTotal] = useState(0);
  const [totalAll, setTotalAll] = useState(0);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(25);
  const [totalPages, setTotalPages] = useState(1);
  const [search, setSearch] = useState('');
  const [selectedProfile, setSelectedProfile] = useState('all');
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(null);
  const [cleaningMikrotik, setCleaningMikrotik] = useState(false);
  const [purgingExpired, setPurgingExpired] = useState(false);

  // Multi-selection state for printing or bulk actions
  const [selectedUsernames, setSelectedUsernames] = useState([]);

  // Modals state
  const [showAddModal, setShowAddModal] = useState(false);
  const [newVoucher, setNewVoucher] = useState({
    username: '',
    profile: '12-jam',
    price: 4000,
    validity: '12h',
    comment: 'manual-entry'
  });

  const [showEditModal, setShowEditModal] = useState(false);
  const [editingVoucher, setEditingVoucher] = useState(null);

  const fetchVouchers = async (refresh = false) => {
    setLoading(true);
    try {
      const q = new URLSearchParams({
        action: 'list',
        page: page.toString(),
        limit: limit.toString(),
        search,
        profile: selectedProfile,
        status: selectedStatus,
        refresh: refresh ? '1' : '0'
      });
      const res = await fetch(`/api/vouchers.php?${q.toString()}`);
      const json = await res.json();
      if (json.success) {
        setUsers(json.data.users || []);
        setProfiles(json.data.profiles || []);
        setTotal(json.data.total || 0);
        setTotalAll(json.data.total_all || 0);
        setTotalPages(json.data.total_pages || 1);
      }
    } catch (e) {
      console.error('Failed to fetch vouchers', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVouchers();
  }, [page, limit, selectedProfile, selectedStatus]);

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1);
      fetchVouchers();
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  // Clean up offline users from MikroTik
  const handleCleanupMikrotik = async () => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only.');
      return;
    }
    const ok = window.confirm(
      'PEMBERSIHAN USER LOKAL MIKROTIK:\n\n' +
      'Aksi ini akan menghapus akun voucher offline dari memori MikroTik (/ip hotspot user) ' +
      'karena seluruh voucher telah tersimpan 100% aman di Database Pacenet Billing System (Single Source of Truth).\n\n' +
      'Pelanggan yang sedang online tidak akan terputus dan login selanjutnya langsung ditangani oleh FreeRADIUS.\n\n' +
      'Lanjutkan pembersihan?'
    );
    if (!ok) return;

    setCleaningMikrotik(true);
    try {
      const res = await fetch('/api/vouchers.php?action=cleanup_mikrotik_local_users', { method: 'POST' });
      const json = await res.json();
      if (json.success) {
        alert('Sukses: Seluruh voucher offline di memori MikroTik berhasil dibersihkan! MikroTik kini bersih, ringan, dan tidak ada lagi kesalahan pembacaan.');
        fetchVouchers(true);
      } else {
        alert(json.message || 'Gagal membersihkan user MikroTik.');
      }
    } catch (e) {
      alert('Gagal menghubungi server.');
    } finally {
      setCleaningMikrotik(false);
    }
  };

  // Purge expired vouchers older than 30 days
  const handlePurgeExpired30Days = async () => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only.');
      return;
    }
    const ok = window.confirm(
      'PEMBERSIHAN OTOMATIS VOUCHER EXPIRED (> 30 HARI):\n\n' +
      'Aksi ini akan menghapus voucher yang sudah habis masa berlakunya lebih dari 30 hari secara permanen dari database Pacenet.\n\n' +
      'Lanjutkan proses pembersihan?'
    );
    if (!ok) return;

    setPurgingExpired(true);
    try {
      const res = await fetch('/api/vouchers.php?action=purge_expired_30days', { method: 'POST' });
      const json = await res.json();
      if (json.success) {
        alert(json.message || 'Pembersihan voucher expired berhasil.');
        fetchVouchers(true);
      } else {
        alert(json.message || 'Gagal membersihkan voucher expired.');
      }
    } catch (e) {
      alert('Gagal menghubungi server.');
    } finally {
      setPurgingExpired(false);
    }
  };

  // Toggle user disabled status
  const handleToggle = async (u) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Perubahan status voucher dinonaktifkan.');
      return;
    }
    setActionLoading(u.id);
    try {
      const res = await fetch('/api/vouchers.php?action=toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: u.id, name: u.name, router: u.router_session, disabled: !u.disabled })
      });
      const json = await res.json();
      if (json.success) {
        setUsers(users.map(item => item.id === u.id ? { ...item, disabled: !item.disabled, status: !item.disabled ? 'disabled' : 'unused' } : item));
      } else {
        alert(json.message || 'Gagal mengubah status voucher.');
      }
    } catch (e) {
      console.error(e);
    } finally {
      setActionLoading(null);
    }
  };

  // Delete single user
  const handleDelete = async (u) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan voucher dinonaktifkan.');
      return;
    }
    if (!window.confirm(`Yakin ingin menghapus voucher "${u.name}" dari Pacenet?`)) return;
    setActionLoading(u.id);
    try {
      const res = await fetch('/api/vouchers.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: u.id, name: u.name, router: u.router_session })
      });
      const json = await res.json();
      if (json.success) {
        setUsers(users.filter(item => item.id !== u.id));
        setSelectedUsernames(prev => prev.filter(name => name !== u.name));
        setTotal(prev => prev - 1);
      } else {
        alert(json.message || 'Gagal menghapus voucher.');
      }
    } catch (e) {
      console.error(e);
    } finally {
      setActionLoading(null);
    }
  };

  // Reset counters
  const handleResetCounters = async (u) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Reset counter voucher dinonaktifkan.');
      return;
    }
    setActionLoading(u.id);
    try {
      const res = await fetch('/api/vouchers.php?action=reset_counters', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: u.id, name: u.name, router: u.router_session })
      });
      const json = await res.json();
      if (json.success) {
        setUsers(users.map(item => item.id === u.id ? { ...item, uptime: '0s', bytes_in: 0, bytes_out: 0, bytes_total: 0, bytes_human: '0 B', status: 'unused' } : item));
      } else {
        alert(json.message || 'Gagal me-reset counter voucher.');
      }
    } catch (e) {
      console.error(e);
    } finally {
      setActionLoading(null);
    }
  };

  // Selection handlers
  const toggleSelectAll = () => {
    const allCurrentSelected = users.length > 0 && users.every(u => selectedUsernames.includes(u.name));
    if (allCurrentSelected) {
      setSelectedUsernames(prev => prev.filter(name => !users.some(u => u.name === name)));
    } else {
      const currentNames = users.map(u => u.name);
      setSelectedUsernames(prev => Array.from(new Set([...prev, ...currentNames])));
    }
  };

  const toggleSelectUser = (uname) => {
    setSelectedUsernames(prev => 
      prev.includes(uname) ? prev.filter(n => n !== uname) : [...prev, uname]
    );
  };

  // Bulk Delete
  const handleBulkDelete = async () => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only.');
      return;
    }
    if (!window.confirm(`Yakin ingin menghapus ${selectedUsernames.length} voucher yang dipilih secara permanen dari database Pacenet?`)) return;
    try {
      const res = await fetch('/api/vouchers.php?action=bulk_delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usernames: selectedUsernames })
      });
      const json = await res.json();
      if (json.success) {
        alert(json.message || `Berhasil menghapus ${selectedUsernames.length} voucher.`);
        setSelectedUsernames([]);
        fetchVouchers();
      } else {
        alert(json.message || 'Gagal menghapus voucher.');
      }
    } catch (e) {
      alert('Gagal menghubungi server.');
    }
  };

  // Print selected vouchers
  const handlePrintSelected = () => {
    if (selectedUsernames.length === 0) return;
    const selectedList = users
      .filter(u => selectedUsernames.includes(u.name))
      .map(u => ({
        username: u.name,
        password: u.name, // unified code = username
        profile: u.profile,
        price: u.price,
        sprice: u.price,
        validity: u.limit_uptime || '12h',
        dns_name: 'hotspot.yunus',
        hotspot_name: 'PACENET HOTSPOT',
        comment: u.comment || ''
      }));

    if (setVouchersForPrint) {
      setVouchersForPrint(selectedList);
    }
    if (onNavigate) {
      onNavigate('print');
    }
  };

  // Print single voucher
  const handlePrintSingle = (u) => {
    const single = [{
      username: u.name,
      password: u.name,
      profile: u.profile,
      price: u.price,
      sprice: u.price,
      validity: u.limit_uptime || '12h',
      dns_name: 'hotspot.yunus',
      hotspot_name: 'PACENET HOTSPOT',
      comment: u.comment || ''
    }];
    if (setVouchersForPrint) {
      setVouchersForPrint(single);
    }
    if (onNavigate) {
      onNavigate('print');
    }
  };

  // Submit Add Voucher
  const handleAddVoucherSubmit = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only.');
      return;
    }
    if (!newVoucher.username.trim()) {
      alert('Kode voucher / username wajib diisi.');
      return;
    }
    try {
      const res = await fetch('/api/vouchers.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newVoucher)
      });
      const json = await res.json();
      if (json.success) {
        alert('Voucher baru berhasil disimpan ke Database Pacenet!');
        setShowAddModal(false);
        setNewVoucher({
          username: '',
          profile: '12-jam',
          price: 4000,
          validity: '12h',
          comment: 'manual-entry'
        });
        fetchVouchers();
      } else {
        alert(json.message || 'Gagal menambahkan voucher.');
      }
    } catch (err) {
      alert('Gagal menghubungi server.');
    }
  };

  // Open Edit Voucher Modal
  const handleOpenEdit = (u) => {
    setEditingVoucher({
      id: u.id,
      name: u.name,
      username: u.name,
      password: u.password || u.name,
      profile: u.profile || '12-jam',
      price: u.price || 4000,
      validity: u.limit_uptime || '12h',
      comment: u.comment === '-' ? '' : (u.comment || ''),
      status: u.status || 'unused'
    });
    setShowEditModal(true);
  };

  // Submit Edit Voucher
  const handleEditVoucherSubmit = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only.');
      return;
    }
    try {
      const res = await fetch('/api/vouchers.php?action=edit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(editingVoucher)
      });
      const json = await res.json();
      if (json.success) {
        alert('Data voucher berhasil diperbarui!');
        setShowEditModal(false);
        setEditingVoucher(null);
        fetchVouchers();
      } else {
        alert(json.message || 'Gagal mengubah data voucher.');
      }
    } catch (err) {
      alert('Gagal menghubungi server.');
    }
  };

  // Generate random 6-character voucher code helper
  const generateRandomCode = () => {
    const chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    let code = '';
    for (let i = 0; i < 6; i++) {
      code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    setNewVoucher(prev => ({ ...prev, username: code }));
  };

  const isAllCurrentPageSelected = users.length > 0 && users.every(u => selectedUsernames.includes(u.name));

  return (
    <div>
      {/* Header Bar */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: '12px',
        marginBottom: '20px'
      }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <Users size={22} color="var(--accent-cyan)" />
            <h2 style={{ fontSize: '19px', fontWeight: 800, color: '#fff' }}>
              User / Voucher List
            </h2>
          </div>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)', marginTop: '2px' }}>
            Database Pacenet Cloud (PostgreSQL FreeRADIUS): <strong>{totalAll.toLocaleString()}</strong> total voucher terdaftar.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={handlePurgeExpired30Days}
            disabled={purgingExpired}
            style={{ borderColor: 'rgba(239, 68, 68, 0.4)', color: '#f87171' }}
            title="Hapus voucher yang sudah expired lebih dari 30 hari dari database"
          >
            <Trash2 size={13} className={purgingExpired ? 'spin-anim' : ''} />
            <span>{purgingExpired ? 'Membersihkan...' : 'Hapus Expired > 30 Hari'}</span>
          </button>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={handleCleanupMikrotik}
            disabled={cleaningMikrotik}
            style={{ borderColor: 'rgba(245, 158, 11, 0.4)', color: '#fbbf24' }}
            title="Bersihkan akun voucher offline dari memori MikroTik (/ip hotspot user)"
          >
            <RefreshCw size={13} className={cleaningMikrotik ? 'spin-anim' : ''} />
            <span>{cleaningMikrotik ? 'Membersihkan...' : 'Bersihkan User MikroTik'}</span>
          </button>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('generate')}
            style={{ borderColor: 'rgba(56, 189, 248, 0.4)', color: '#38bdf8' }}
            title="Buka Batch Generator Voucher (Hingga 100.000 voucher)"
          >
            <Sparkles size={14} />
            <span>Generator Batch</span>
          </button>

          <button 
            className="btn btn-primary btn-sm"
            onClick={() => setShowAddModal(true)}
            style={{
              background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
              boxShadow: '0 0 12px rgba(0, 210, 211, 0.3)'
            }}
          >
            <PlusCircle size={15} />
            <span>Tambah Voucher</span>
          </button>
        </div>
      </div>

      {/* Filter and Search Bar (Router Filter Removed) */}
      <div className="glass-card" style={{ marginBottom: '20px', padding: '14px 18px' }}>
        <div className="filter-bar" style={{ margin: 0 }}>
          {/* Search */}
          <div className="search-box">
            <Search size={16} color="var(--text-muted)" />
            <input 
              type="text"
              placeholder="Cari voucher berdasarkan kode / komentar / batch..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>

          {/* Profile & Status Filter */}
          <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <Filter size={13} color="var(--accent-cyan)" />
              <select
                className="filter-select"
                value={selectedProfile}
                onChange={e => {
                  setSelectedProfile(e.target.value);
                  setPage(1);
                }}
              >
                <option value="all">Semua Profil Paket</option>
                {profiles.map(p => (
                  <option key={p.name} value={p.name}>{p.name}</option>
                ))}
              </select>
            </div>

            <select
              className="filter-select"
              value={selectedStatus}
              onChange={e => {
                setSelectedStatus(e.target.value);
                setPage(1);
              }}
            >
              <option value="all">Semua Status</option>
              <option value="unused">Belum Pakai</option>
              <option value="active">Sedang Online</option>
              <option value="expired">Expired</option>
              <option value="disabled">Nonaktif</option>
            </select>

            <select
              className="filter-select"
              value={limit}
              onChange={e => {
                setLimit(Number(e.target.value));
                setPage(1);
              }}
            >
              <option value={25}>25 / halaman</option>
              <option value={55}>55 / hal (1 Lembar F4)</option>
              <option value={110}>110 / hal (2 Lembar F4)</option>
              <option value={50}>50 / halaman</option>
              <option value={100}>100 / halaman</option>
            </select>

            <button 
              className="btn btn-secondary btn-sm"
              onClick={() => fetchVouchers(true)}
              disabled={loading}
              title="Perbarui data dari Database Pacenet"
            >
              <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
              <span>Refresh</span>
            </button>
          </div>
        </div>
      </div>

      {/* Floating / Sticky Selection Toolbar */}
      {selectedUsernames.length > 0 && (
        <div style={{
          position: 'sticky',
          top: '16px',
          zIndex: 90,
          background: 'linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 41, 59, 0.95) 100%)',
          border: '1.5px solid var(--accent-cyan)',
          borderRadius: 'var(--radius-md)',
          padding: '12px 18px',
          marginBottom: '16px',
          boxShadow: '0 8px 30px rgba(0, 210, 211, 0.25)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '12px',
          backdropFilter: 'blur(10px)'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <span style={{
              background: 'var(--accent-cyan)',
              color: '#000',
              fontWeight: 800,
              padding: '3px 10px',
              borderRadius: '20px',
              fontSize: '12px'
            }}>
              {selectedUsernames.length}
            </span>
            <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#fff' }}>
              Voucher Terpilih untuk Dicetak / Dikelola
            </span>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
            <button
              className="btn btn-primary btn-sm"
              onClick={handlePrintSelected}
              style={{
                background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                fontWeight: 700
              }}
              title="Cetak voucher terpilih menggunakan format Kertas F4 (55 Slip/Lembar)"
            >
              <Printer size={14} />
              <span>Cetak Terpilih (Kertas F4)</span>
            </button>

            <button
              className="btn btn-secondary btn-sm"
              onClick={handlePrintSelected}
              style={{ borderColor: 'var(--accent-cyan)', color: 'var(--accent-cyan)', fontWeight: 600 }}
              title="Cetak voucher terpilih ke Printer Thermal POS 58mm / 80mm"
            >
              <Printer size={14} />
              <span>Cetak Terpilih (Thermal POS)</span>
            </button>

            <button
              className="btn btn-danger btn-sm"
              onClick={handleBulkDelete}
              style={{ fontWeight: 600 }}
              title="Hapus seluruh voucher yang dipilih"
            >
              <Trash2 size={14} />
              <span>Hapus Terpilih</span>
            </button>

            <button
              className="btn btn-secondary btn-sm"
              onClick={() => setSelectedUsernames([])}
              style={{ color: 'var(--text-muted)' }}
              title="Batalkan pilihan"
            >
              <X size={14} />
              <span>Batal</span>
            </button>
          </div>
        </div>
      )}

      {/* Vouchers Table */}
      <div className="glass-card" style={{ padding: '0', overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: '0' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th style={{ width: '42px', textAlign: 'center' }}>
                  <input 
                    type="checkbox"
                    checked={isAllCurrentPageSelected}
                    onChange={toggleSelectAll}
                    style={{ cursor: 'pointer', transform: 'scale(1.15)' }}
                    title="Pilih semua voucher di halaman ini"
                  />
                </th>
                <th style={{ width: '110px' }}>Status</th>
                <th>Kode Voucher</th>
                <th>Profil Paket</th>
                <th>Harga</th>
                <th>Masa Aktif</th>
                <th>Uptime Terpakai</th>
                <th>Konsumsi Kuota</th>
                <th>Komentar / Batch</th>
                <th style={{ textAlign: 'right' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loading && users.length === 0 ? (
                <tr>
                  <td colSpan={10} style={{ textAlign: 'center', padding: '40px' }}>
                    <RefreshCw size={24} className="spin-anim" style={{ margin: '0 auto 10px', color: 'var(--accent-cyan)' }} />
                    <p style={{ color: 'var(--text-muted)' }}>Memuat data voucher dari Database Pacenet Cloud...</p>
                  </td>
                </tr>
              ) : users.length === 0 ? (
                <tr>
                  <td colSpan={10} style={{ textAlign: 'center', padding: '40px', color: 'var(--text-muted)' }}>
                    Tidak ada voucher yang cocok dengan kriteria pencarian / filter status.
                  </td>
                </tr>
              ) : (
                users.map((u) => {
                  const isBusy = actionLoading === u.id;
                  const isSelected = selectedUsernames.includes(u.name);
                  return (
                    <tr 
                      key={u.id} 
                      style={{ 
                        opacity: isBusy ? 0.5 : 1,
                        backgroundColor: isSelected ? 'rgba(0, 210, 211, 0.08)' : undefined
                      }}
                    >
                      <td style={{ textAlign: 'center' }}>
                        <input 
                          type="checkbox"
                          checked={isSelected}
                          onChange={() => toggleSelectUser(u.name)}
                          style={{ cursor: 'pointer', transform: 'scale(1.15)' }}
                        />
                      </td>
                      <td>
                        {u.status === 'active' ? (
                          <span className="tag tag-emerald" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '11px', padding: '2px 8px' }}>
                            <span style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: '#10b981', display: 'inline-block' }}></span>
                            Online
                          </span>
                        ) : u.status === 'disabled' || u.disabled ? (
                          <span className="tag tag-rose" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '11px', padding: '2px 8px' }}>
                            <XCircle size={11} />
                            Nonaktif
                          </span>
                        ) : u.status === 'expired' ? (
                          <span className="tag tag-gray" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '11px', padding: '2px 8px' }}>
                            Expired
                          </span>
                        ) : (
                          <span className="tag tag-blue" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', fontSize: '11px', padding: '2px 8px' }}>
                            Belum Pakai
                          </span>
                        )}
                      </td>
                      <td style={{ fontWeight: 800, fontFamily: 'var(--font-mono)', fontSize: '13.5px', color: '#fff', letterSpacing: '0.5px' }}>
                        {u.name}
                      </td>
                      <td>
                        <span className="tag tag-purple">{u.profile}</span>
                      </td>
                      <td style={{ fontWeight: 600, color: 'var(--accent-cyan)' }}>
                        {u.price_formatted}
                      </td>
                      <td style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
                        {u.limit_uptime || '12h'}
                      </td>
                      <td style={{ fontFamily: 'var(--font-mono)', fontSize: '12px' }}>
                        {u.uptime || '0s'}
                      </td>
                      <td style={{ fontFamily: 'var(--font-mono)', fontSize: '12px', color: 'var(--accent-cyan)' }}>
                        {u.bytes_human}
                      </td>
                      <td style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
                        {u.comment || '-'}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        <div style={{ display: 'inline-flex', gap: '5px' }}>
                          <button
                            onClick={() => handlePrintSingle(u)}
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 7px', color: '#38bdf8', borderColor: 'rgba(56, 189, 248, 0.3)' }}
                            title="Cetak Voucher Ini"
                          >
                            <Printer size={13} />
                          </button>
                          <button
                            onClick={() => handleOpenEdit(u)}
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 7px', color: '#fbbf24', borderColor: 'rgba(251, 191, 36, 0.3)' }}
                            title="Edit Data Voucher"
                          >
                            <Edit3 size={13} />
                          </button>
                          <button
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 7px' }}
                            onClick={() => handleToggle(u)}
                            title={u.disabled ? 'Aktifkan Voucher' : 'Nonaktifkan Voucher'}
                            disabled={isBusy}
                          >
                            <Power size={13} color={u.disabled ? 'var(--accent-emerald)' : 'var(--accent-amber)'} />
                          </button>
                          <button
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 7px' }}
                            onClick={() => handleResetCounters(u)}
                            title="Reset Uptime & Kuota"
                            disabled={isBusy}
                          >
                            <RotateCcw size={13} />
                          </button>
                          <button
                            className="btn btn-danger btn-sm"
                            style={{ padding: '4px 7px' }}
                            onClick={() => handleDelete(u)}
                            title="Hapus Voucher"
                            disabled={isBusy}
                          >
                            <Trash2 size={13} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Bar */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '14px 20px',
          borderTop: '1px solid var(--border-subtle)',
          fontSize: '13px',
          color: 'var(--text-muted)',
          flexWrap: 'wrap',
          gap: '10px'
        }}>
          <div>
            Menampilkan {users.length > 0 ? (page - 1) * limit + 1 : 0} - {Math.min(page * limit, total)} dari {total.toLocaleString()} voucher
          </div>

          <div style={{ display: 'flex', gap: '6px', alignItems: 'center' }}>
            <button
              className="btn btn-secondary btn-sm"
              disabled={page <= 1}
              onClick={() => setPage(p => Math.max(p - 1, 1))}
            >
              <ChevronLeft size={14} />
              <span>Sebelumnya</span>
            </button>
            <span style={{ padding: '0 8px', fontFamily: 'var(--font-mono)' }}>
              Hal {page} / {totalPages || 1}
            </span>
            <button
              className="btn btn-secondary btn-sm"
              disabled={page >= totalPages}
              onClick={() => setPage(p => Math.min(p + 1, totalPages))}
            >
              <span>Selanjutnya</span>
              <ChevronRight size={14} />
            </button>
          </div>
        </div>
      </div>

      {/* =========================================================================
          MODAL: TAMBAH VOUCHER MANUAL
          ========================================================================= */}
      {showAddModal && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(5px)',
          zIndex: 999,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '16px'
        }}>
          <div className="glass-card" style={{ width: '100%', maxWidth: '480px', padding: '24px', background: '#0f172a', border: '1px solid var(--border-subtle)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <PlusCircle size={18} color="var(--accent-cyan)" />
                <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff' }}>Tambah Voucher Baru</h3>
              </div>
              <button 
                onClick={() => setShowAddModal(false)}
                style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleAddVoucherSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                  Kode Voucher / Username:
                </label>
                <div style={{ display: 'flex', gap: '8px' }}>
                  <input 
                    type="text"
                    required
                    placeholder="Contoh: 7K9LP2"
                    value={newVoucher.username}
                    onChange={e => setNewVoucher({ ...newVoucher, username: e.target.value.toUpperCase() })}
                    style={{
                      flex: 1,
                      padding: '8px 12px',
                      background: 'rgba(0,0,0,0.4)',
                      border: '1px solid var(--border-subtle)',
                      borderRadius: 'var(--radius-sm)',
                      color: '#fff',
                      fontFamily: 'var(--font-mono)',
                      fontSize: '14px',
                      fontWeight: 700
                    }}
                  />
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    onClick={generateRandomCode}
                    title="Buat kode acak 6 karakter"
                  >
                    Acak Kode
                  </button>
                </div>
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                  Paket Profil:
                </label>
                <select
                  className="filter-select"
                  value={newVoucher.profile}
                  onChange={e => {
                    const prof = e.target.value;
                    let pr = 4000;
                    let val = '12h';
                    if (prof.includes('1minggu')) { pr = 40000; val = '7d'; }
                    else if (prof.includes('1bulan')) { pr = 100000; val = '30d'; }
                    else if (prof.includes('12-jam')) { pr = 4000; val = '12h'; }
                    setNewVoucher({ ...newVoucher, profile: prof, price: pr, validity: val });
                  }}
                  style={{ width: '100%', padding: '8px 12px' }}
                >
                  {profiles.length > 0 ? (
                    profiles.map(p => (
                      <option key={p.name} value={p.name}>{p.name}</option>
                    ))
                  ) : (
                    <option value="12-jam">12-jam</option>
                  )}
                </select>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                    Harga (Rp):
                  </label>
                  <input 
                    type="number"
                    value={newVoucher.price}
                    onChange={e => setNewVoucher({ ...newVoucher, price: Number(e.target.value) })}
                    style={{
                      width: '100%',
                      padding: '8px 12px',
                      background: 'rgba(0,0,0,0.4)',
                      border: '1px solid var(--border-subtle)',
                      borderRadius: 'var(--radius-sm)',
                      color: '#fff',
                      fontSize: '13px'
                    }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                    Masa Aktif:
                  </label>
                  <input 
                    type="text"
                    placeholder="12h, 1d, 7d"
                    value={newVoucher.validity}
                    onChange={e => setNewVoucher({ ...newVoucher, validity: e.target.value })}
                    style={{
                      width: '100%',
                      padding: '8px 12px',
                      background: 'rgba(0,0,0,0.4)',
                      border: '1px solid var(--border-subtle)',
                      borderRadius: 'var(--radius-sm)',
                      color: '#fff',
                      fontSize: '13px'
                    }}
                  />
                </div>
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                  Komentar / Catatan:
                </label>
                <input 
                  type="text"
                  placeholder="manual-entry"
                  value={newVoucher.comment}
                  onChange={e => setNewVoucher({ ...newVoucher, comment: e.target.value })}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'rgba(0,0,0,0.4)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-sm)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                />
              </div>

              <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end', marginTop: '12px' }}>
                <button 
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={() => setShowAddModal(false)}
                >
                  Batal
                </button>
                <button 
                  type="submit"
                  className="btn btn-primary btn-sm"
                  style={{ background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)' }}
                >
                  Simpan Voucher
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* =========================================================================
          MODAL: EDIT VOUCHER
          ========================================================================= */}
      {showEditModal && editingVoucher && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(5px)',
          zIndex: 999,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '16px'
        }}>
          <div className="glass-card" style={{ width: '100%', maxWidth: '480px', padding: '24px', background: '#0f172a', border: '1px solid var(--border-subtle)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <Edit3 size={18} color="#fbbf24" />
                <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff' }}>
                  Edit Voucher: <span style={{ fontFamily: 'var(--font-mono)', color: 'var(--accent-cyan)' }}>{editingVoucher.name}</span>
                </h3>
              </div>
              <button 
                onClick={() => setShowEditModal(false)}
                style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleEditVoucherSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                  Status Voucher:
                </label>
                <select
                  className="filter-select"
                  value={editingVoucher.status}
                  onChange={e => setEditingVoucher({ ...editingVoucher, status: e.target.value })}
                  style={{ width: '100%', padding: '8px 12px' }}
                >
                  <option value="unused">Belum Pakai (unused)</option>
                  <option value="active">Sedang Online (active)</option>
                  <option value="expired">Expired (habis waktu/kuota)</option>
                  <option value="disabled">Nonaktif (disabled)</option>
                </select>
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                  Paket Profil:
                </label>
                <select
                  className="filter-select"
                  value={editingVoucher.profile}
                  onChange={e => setEditingVoucher({ ...editingVoucher, profile: e.target.value })}
                  style={{ width: '100%', padding: '8px 12px' }}
                >
                  {profiles.map(p => (
                    <option key={p.name} value={p.name}>{p.name}</option>
                  ))}
                </select>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                    Harga (Rp):
                  </label>
                  <input 
                    type="number"
                    value={editingVoucher.price}
                    onChange={e => setEditingVoucher({ ...editingVoucher, price: Number(e.target.value) })}
                    style={{
                      width: '100%',
                      padding: '8px 12px',
                      background: 'rgba(0,0,0,0.4)',
                      border: '1px solid var(--border-subtle)',
                      borderRadius: 'var(--radius-sm)',
                      color: '#fff',
                      fontSize: '13px'
                    }}
                  />
                </div>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                    Masa Aktif:
                  </label>
                  <input 
                    type="text"
                    value={editingVoucher.validity}
                    onChange={e => setEditingVoucher({ ...editingVoucher, validity: e.target.value })}
                    style={{
                      width: '100%',
                      padding: '8px 12px',
                      background: 'rgba(0,0,0,0.4)',
                      border: '1px solid var(--border-subtle)',
                      borderRadius: 'var(--radius-sm)',
                      color: '#fff',
                      fontSize: '13px'
                    }}
                  />
                </div>
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '6px' }}>
                  Komentar / Batch:
                </label>
                <input 
                  type="text"
                  value={editingVoucher.comment}
                  onChange={e => setEditingVoucher({ ...editingVoucher, comment: e.target.value })}
                  style={{
                    width: '100%',
                    padding: '8px 12px',
                    background: 'rgba(0,0,0,0.4)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-sm)',
                    color: '#fff',
                    fontSize: '13px'
                  }}
                />
              </div>

              <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end', marginTop: '12px' }}>
                <button 
                  type="button"
                  className="btn btn-secondary btn-sm"
                  onClick={() => setShowEditModal(false)}
                >
                  Batal
                </button>
                <button 
                  type="submit"
                  className="btn btn-primary btn-sm"
                  style={{ background: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)', color: '#000', fontWeight: 700 }}
                >
                  Perbarui Voucher
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
