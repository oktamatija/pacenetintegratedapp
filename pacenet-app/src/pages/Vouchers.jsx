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
  Printer
} from 'lucide-react';

export default function Vouchers({ onNavigate, isReadOnly }) {
  const [users, setUsers] = useState([]);
  const [profiles, setProfiles] = useState([]);
  const [routers, setRouters] = useState([]);
  const [selectedRouter, setSelectedRouter] = useState('all');
  const [total, setTotal] = useState(0);
  const [totalAll, setTotalAll] = useState(0);
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(25);
  const [totalPages, setTotalPages] = useState(1);
  const [search, setSearch] = useState('');
  const [selectedProfile, setSelectedProfile] = useState('all');
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(null);

  const fetchVouchers = async (refresh = false) => {
    setLoading(true);
    try {
      const q = new URLSearchParams({
        action: 'list',
        page: page.toString(),
        limit: limit.toString(),
        search,
        profile: selectedProfile,
        router: selectedRouter,
        refresh: refresh ? '1' : '0'
      });
      const res = await fetch(`/api/vouchers.php?${q.toString()}`);
      const json = await res.json();
      if (json.success) {
        setUsers(json.data.users || []);
        setProfiles(json.data.profiles || []);
        setRouters(json.data.routers || []);
        setTotal(json.data.total || 0);
        setTotalAll(json.data.total_all || 0);
        setTotalPages(json.data.total_pages || 1);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVouchers();
  }, [page, limit, selectedProfile, selectedRouter]);

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1);
      fetchVouchers();
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

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
        body: JSON.stringify({ id: u.id, router: u.router_session, disabled: !u.disabled })
      });
      const json = await res.json();
      if (json.success) {
        setUsers(users.map(item => item.id === u.id ? { ...item, disabled: !item.disabled } : item));
      } else {
        alert(json.message || 'Gagal mengubah status voucher.');
      }
    } catch (e) {
      console.error(e);
    } finally {
      setActionLoading(null);
    }
  };

  // Delete user
  const handleDelete = async (u) => {
    if (isReadOnly) {
      alert('Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan voucher dinonaktifkan.');
      return;
    }
    if (!window.confirm(`Yakin ingin menghapus voucher "${u.name}"?`)) return;
    setActionLoading(u.id);
    try {
      const res = await fetch('/api/vouchers.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: u.id, router: u.router_session })
      });
      const json = await res.json();
      if (json.success) {
        setUsers(users.filter(item => item.id !== u.id));
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
        body: JSON.stringify({ id: u.id, router: u.router_session })
      });
      const json = await res.json();
      if (json.success) {
        setUsers(users.map(item => item.id === u.id ? { ...item, uptime: '0s', bytes_in: 0, bytes_out: 0 } : item));
      } else {
        alert(json.message || 'Gagal me-reset counter voucher.');
      }
    } catch (e) {
      console.error(e);
    } finally {
      setActionLoading(null);
    }
  };

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
          <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff' }}>
            Manajemen Voucher & User Terpusat
          </h2>
          <p style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>
            Database Master MikroTik: <strong>{totalAll.toLocaleString()}</strong> total voucher terdaftar.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => onNavigate('print')}
            style={{ borderColor: 'var(--accent-cyan)', color: 'var(--accent-cyan)' }}
            title="Buka modul pencetakan 55 voucher per lembar F4"
          >
            <Printer size={15} />
            <span>Cetak Lembar F4 (55 Slip)</span>
          </button>

          <button 
            className="btn btn-primary btn-sm"
            onClick={() => onNavigate('generate')}
          >
            <PlusCircle size={15} />
            <span>Buat Voucher Baru</span>
          </button>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="glass-card" style={{ marginBottom: '20px', padding: '14px 18px' }}>
        <div className="filter-bar" style={{ margin: 0 }}>
          {/* Search */}
          <div className="search-box">
            <Search size={16} color="var(--text-muted)" />
            <input 
              type="text"
              placeholder="Cari voucher berdasarkan nama / batch / komentar..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>

          {/* Router & Profile Filter */}
          <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
            <select
              className="filter-select"
              value={selectedRouter}
              onChange={e => {
                setSelectedRouter(e.target.value);
                setPage(1);
              }}
              style={{ fontWeight: 600, color: 'var(--accent-cyan)' }}
            >
              <option value="all">Semua Router MikroTik</option>
              {routers.map(r => (
                <option key={r.session} value={r.session}>
                  {r.name} ({r.user_count ? r.user_count.toLocaleString() : 0} voucher)
                </option>
              ))}
            </select>

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
              title="Paksa Sinkronkan dengan MikroTik"
            >
              <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
              <span>Sync</span>
            </button>
          </div>
        </div>
      </div>

      {/* Vouchers Table */}
      <div className="glass-card" style={{ padding: '0', overflow: 'hidden' }}>
        <div className="table-container" style={{ border: 'none', borderRadius: '0' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th style={{ width: '50px' }}>Status</th>
                <th>Router</th>
                <th>Username / Voucher</th>
                <th>Password</th>
                <th>Profil</th>
                <th>Uptime Terpakai</th>
                <th>Konsumsi Kuota</th>
                <th>Komentar / Batch</th>
                <th style={{ textAlign: 'right' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loading && users.length === 0 ? (
                <tr>
                  <td colSpan={9} style={{ textAlign: 'center', padding: '40px' }}>
                    <RefreshCw size={24} className="spin-anim" style={{ margin: '0 auto 10px', color: 'var(--accent-cyan)' }} />
                    <p style={{ color: 'var(--text-muted)' }}>Memuat data ribuan voucher dari seluruh router MikroTik...</p>
                  </td>
                </tr>
              ) : users.length === 0 ? (
                <tr>
                  <td colSpan={9} style={{ textAlign: 'center', padding: '40px', color: 'var(--text-muted)' }}>
                    Tidak ada voucher yang cocok dengan filter router / profil.
                  </td>
                </tr>
              ) : (
                users.map((u) => {
                  const isBusy = actionLoading === u.id;
                  return (
                    <tr key={`${u.router_session || 'r'}_${u.id}`} style={{ opacity: isBusy ? 0.5 : 1 }}>
                      <td>
                        {u.disabled ? (
                          <XCircle size={17} color="var(--accent-rose)" title="Nonaktif (Disabled)" />
                        ) : (
                          <CheckCircle2 size={17} color="var(--accent-emerald)" title="Aktif" />
                        )}
                      </td>
                      <td>
                        <span className="tag tag-blue" style={{ fontSize: '11px', textTransform: 'capitalize' }}>
                          {u.router_name || u.router_session || 'Master'}
                        </span>
                      </td>
                      <td style={{ fontWeight: 700, fontFamily: 'var(--font-mono)', color: '#fff' }}>
                        {u.name}
                      </td>
                      <td style={{ fontFamily: 'var(--font-mono)', color: 'var(--text-muted)' }}>
                        {u.password || '-'}
                      </td>
                      <td>
                        <span className="tag tag-purple">{u.profile}</span>
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
                        <div style={{ display: 'inline-flex', gap: '6px' }}>
                          <button
                            onClick={() => onNavigate && onNavigate('print')}
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 8px', color: '#38bdf8', borderColor: 'rgba(56, 189, 248, 0.3)' }}
                            title="Buka Lembar Pencetakan F4 (55 Slip/Lembar)"
                          >
                            <Printer size={13} />
                          </button>
                          <button
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 8px' }}
                            onClick={() => handleToggle(u)}
                            title={u.disabled ? 'Aktifkan Voucher' : 'Nonaktifkan Voucher'}
                            disabled={isBusy}
                          >
                            <Power size={13} color={u.disabled ? 'var(--accent-emerald)' : 'var(--accent-amber)'} />
                          </button>
                          <button
                            className="btn btn-secondary btn-sm"
                            style={{ padding: '4px 8px' }}
                            onClick={() => handleResetCounters(u)}
                            title="Reset Uptime & Kuota"
                            disabled={isBusy}
                          >
                            <RotateCcw size={13} />
                          </button>
                          <button
                            className="btn btn-danger btn-sm"
                            style={{ padding: '4px 8px' }}
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
    </div>
  );
}
