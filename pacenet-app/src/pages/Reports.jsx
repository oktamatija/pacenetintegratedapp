import React, { useState, useEffect } from 'react';
import { 
  DollarSign, 
  TrendingUp, 
  Search, 
  Download, 
  RefreshCw, 
  ChevronLeft, 
  ChevronRight, 
  PieChart, 
  Wifi, 
  Ticket, 
  CheckCircle2, 
  Clock, 
  Copy, 
  Check, 
  Filter,
  Server
} from 'lucide-react';

export default function Reports() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [statusTab, setStatusTab] = useState('all'); // 'all', 'active', 'unused', 'expired'
  const [selectedRouter, setSelectedRouter] = useState('all');
  const [copiedUser, setCopiedUser] = useState(null);

  const fetchReports = async (forceRefresh = false) => {
    setLoading(true);
    try {
      const q = new URLSearchParams({
        page: page.toString(),
        limit: '25',
        router: selectedRouter,
        status: statusTab,
        search
      });
      if (forceRefresh) {
        q.set('refresh', '1');
      }
      const res = await fetch(`/api/reports.php?${q.toString()}`);
      const json = await res.json();
      if (json.success) {
        setData(json.data);
      }
    } catch (e) {
      console.error('Failed to fetch reports', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReports(false);
  }, [page, statusTab, selectedRouter]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1);
      fetchReports(false);
    }, 300);
    return () => clearTimeout(timer);
  }, [search]);

  const summary = data?.summary || {};
  const vouchers = data?.vouchers || data?.transactions || [];
  const pagination = data?.pagination || {};
  const profileBreakdown = data?.profile_breakdown || [];
  const routers = data?.routers || [];

  const handleCopy = (text) => {
    navigator.clipboard.writeText(text);
    setCopiedUser(text);
    setTimeout(() => setCopiedUser(null), 1500);
  };

  const handleExportCSV = () => {
    if (!vouchers.length) return;
    let csv = 'Router,Status,Username,Password,Profil,Harga,Sisa Waktu,Uptime,IP Address,MAC Address,Tanggal,Waktu,Batch\n';
    vouchers.forEach(t => {
      csv += `"${t.router_name || ''}","${t.status_label || t.status}","${t.username}","${t.password || ''}","${t.profile}","${t.price}","${t.session_left || ''}","${t.uptime || ''}","${t.ip || ''}","${t.mac || ''}","${t.date || ''}","${t.time || ''}","${t.batch || ''}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `pacenet-report-${selectedRouter}-${statusTab}-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
  };

  return (
    <div>
      {/* Top Header */}
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
            Laporan Voucher & Penjualan
          </h2>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>
            Monitoring multi-router real-time: omzet penjualan, voucher sedang terpakai, stok siap pakai, dan riwayat voucher kedaluwarsa.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap', alignItems: 'center' }}>
          {/* Router Selector Dropdown */}
          <div style={{
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            background: 'var(--bg-surface)',
            border: '1px solid var(--border-subtle)',
            borderRadius: 'var(--radius-md)',
            padding: '5px 12px'
          }}>
            <Server size={14} color="var(--accent-cyan)" />
            <select 
              value={selectedRouter}
              onChange={e => { setSelectedRouter(e.target.value); setPage(1); }}
              style={{
                background: 'transparent',
                border: 'none',
                color: '#fff',
                fontSize: '12.5px',
                fontWeight: 600,
                outline: 'none',
                cursor: 'pointer'
              }}
              title="Pilih Router untuk memfilter laporan per router atau gabungan semua router"
            >
              {routers.map(r => (
                <option key={r.session} value={r.session} style={{ background: '#111722', color: '#fff' }}>
                  {r.session === 'all' ? '🌐 ' : '⚡ '}
                  {r.name} ({r.active_count || 0} Aktif)
                </option>
              ))}
            </select>
          </div>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={handleExportCSV}
            disabled={vouchers.length === 0}
            title="Download data laporan dalam format spreadsheet CSV"
          >
            <Download size={14} />
            <span>Ekspor CSV</span>
          </button>
          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => fetchReports(true)}
            disabled={loading}
            title="Sinkronisasi data langsung dari semua router MikroTik"
          >
            <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
            <span>Sync Real-time</span>
          </button>
        </div>
      </div>

      {/* 4 Status KPI Cards */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: '16px',
        marginBottom: '20px'
      }}>
        {/* KPI 1: Omzet Penjualan */}
        <div className="glass-card kpi-card emerald" style={{ margin: 0 }}>
          <div className="kpi-info">
            <h3 style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', fontWeight: 700 }}>
              Total Omzet Terjual
            </h3>
            <div className="kpi-value" style={{ color: 'var(--accent-emerald)', fontSize: '22px', fontWeight: 800 }}>
              {summary.total_revenue_formatted || 'Rp 0'}
            </div>
            <div className="kpi-sub" style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
              Akumulasi voucher aktif & selesai
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-emerald)' }}>
            <DollarSign size={24} />
          </div>
        </div>

        {/* KPI 2: Sementara Terpakai */}
        <div 
          className="glass-card kpi-card cyan" 
          style={{ 
            margin: 0, 
            cursor: 'pointer',
            borderColor: statusTab === 'active' ? 'var(--accent-cyan)' : 'var(--border-subtle)',
            background: statusTab === 'active' ? 'rgba(0, 210, 211, 0.08)' : undefined
          }}
          onClick={() => { setStatusTab('active'); setPage(1); }}
        >
          <div className="kpi-info">
            <h3 style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', fontWeight: 700, display: 'flex', alignItems: 'center', gap: '6px' }}>
              <span className="pulse-dot-green"></span>
              Sementara Terpakai
            </h3>
            <div className="kpi-value" style={{ color: 'var(--accent-cyan)', fontSize: '22px', fontWeight: 800 }}>
              {(summary.count_active || 0).toLocaleString()} <span style={{ fontSize: '13px', fontWeight: 500, color: 'var(--text-muted)' }}>user</span>
            </div>
            <div className="kpi-sub" style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
              Sinkron dengan status bar controller
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-cyan)' }}>
            <Wifi size={24} />
          </div>
        </div>

        {/* KPI 3: Belum Terpakai */}
        <div 
          className="glass-card kpi-card amber" 
          style={{ 
            margin: 0, 
            cursor: 'pointer',
            borderColor: statusTab === 'unused' ? 'var(--accent-amber)' : 'var(--border-subtle)',
            background: statusTab === 'unused' ? 'rgba(245, 158, 11, 0.08)' : undefined
          }}
          onClick={() => { setStatusTab('unused'); setPage(1); }}
        >
          <div className="kpi-info">
            <h3 style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', fontWeight: 700 }}>
              Belum Terpakai
            </h3>
            <div className="kpi-value" style={{ color: 'var(--accent-amber)', fontSize: '22px', fontWeight: 800 }}>
              {(summary.count_unused || 0).toLocaleString()} <span style={{ fontSize: '13px', fontWeight: 500, color: 'var(--text-muted)' }}>voucher</span>
            </div>
            <div className="kpi-sub" style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
              Potensi: {summary.potential_unused_revenue_formatted || 'Rp 0'}
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-amber)' }}>
            <Ticket size={24} />
          </div>
        </div>

        {/* KPI 4: Habis Terpakai */}
        <div 
          className="glass-card kpi-card purple" 
          style={{ 
            margin: 0, 
            cursor: 'pointer',
            borderColor: statusTab === 'expired' ? 'var(--accent-purple)' : 'var(--border-subtle)',
            background: statusTab === 'expired' ? 'rgba(167, 139, 250, 0.08)' : undefined
          }}
          onClick={() => { setStatusTab('expired'); setPage(1); }}
        >
          <div className="kpi-info">
            <h3 style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', fontWeight: 700 }}>
              Habis Terpakai
            </h3>
            <div className="kpi-value" style={{ color: 'var(--accent-purple)', fontSize: '22px', fontWeight: 800 }}>
              {(summary.count_expired || 0).toLocaleString()} <span style={{ fontSize: '13px', fontWeight: 500, color: 'var(--text-muted)' }}>voucher</span>
            </div>
            <div className="kpi-sub" style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
              Masa aktif tuntas & kedaluwarsa
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-purple)' }}>
            <Clock size={24} />
          </div>
        </div>
      </div>

      {/* Segmented Filter Pills */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        marginBottom: '16px',
        overflowX: 'auto',
        paddingBottom: '4px'
      }}>
        <button
          className={`btn btn-sm ${statusTab === 'all' ? 'btn-primary' : 'btn-secondary'}`}
          style={{ borderRadius: '20px', padding: '6px 14px', fontSize: '12.5px', whiteSpace: 'nowrap' }}
          onClick={() => { setStatusTab('all'); setPage(1); }}
        >
          <span>Semua Voucher</span>
          <span style={{ 
            background: statusTab === 'all' ? 'rgba(0,0,0,0.25)' : 'rgba(255,255,255,0.1)', 
            padding: '1px 7px', 
            borderRadius: '10px', 
            fontSize: '11px',
            fontFamily: 'var(--font-mono)' 
          }}>
            {(summary.total_vouchers || 0).toLocaleString()}
          </span>
        </button>

        <button
          className={`btn btn-sm ${statusTab === 'active' ? 'btn-primary' : 'btn-secondary'}`}
          style={{ 
            borderRadius: '20px', 
            padding: '6px 14px', 
            fontSize: '12.5px', 
            whiteSpace: 'nowrap',
            borderColor: statusTab === 'active' ? undefined : 'rgba(0, 210, 211, 0.3)'
          }}
          onClick={() => { setStatusTab('active'); setPage(1); }}
        >
          <span className="pulse-dot-green"></span>
          <span>Sementara Terpakai</span>
          <span style={{ 
            background: statusTab === 'active' ? 'rgba(0,0,0,0.25)' : 'rgba(0, 210, 211, 0.15)', 
            color: statusTab === 'active' ? '#fff' : 'var(--accent-cyan)',
            padding: '1px 7px', 
            borderRadius: '10px', 
            fontSize: '11px',
            fontFamily: 'var(--font-mono)' 
          }}>
            {(summary.count_active || 0).toLocaleString()}
          </span>
        </button>

        <button
          className={`btn btn-sm ${statusTab === 'unused' ? 'btn-primary' : 'btn-secondary'}`}
          style={{ 
            borderRadius: '20px', 
            padding: '6px 14px', 
            fontSize: '12.5px', 
            whiteSpace: 'nowrap',
            borderColor: statusTab === 'unused' ? undefined : 'rgba(245, 158, 11, 0.3)'
          }}
          onClick={() => { setStatusTab('unused'); setPage(1); }}
        >
          <span>Belum Terpakai</span>
          <span style={{ 
            background: statusTab === 'unused' ? 'rgba(0,0,0,0.25)' : 'rgba(245, 158, 11, 0.15)', 
            color: statusTab === 'unused' ? '#fff' : 'var(--accent-amber)',
            padding: '1px 7px', 
            borderRadius: '10px', 
            fontSize: '11px',
            fontFamily: 'var(--font-mono)' 
          }}>
            {(summary.count_unused || 0).toLocaleString()}
          </span>
        </button>

        <button
          className={`btn btn-sm ${statusTab === 'expired' ? 'btn-primary' : 'btn-secondary'}`}
          style={{ 
            borderRadius: '20px', 
            padding: '6px 14px', 
            fontSize: '12.5px', 
            whiteSpace: 'nowrap',
            borderColor: statusTab === 'expired' ? undefined : 'rgba(148, 163, 184, 0.3)'
          }}
          onClick={() => { setStatusTab('expired'); setPage(1); }}
        >
          <span>Habis Terpakai</span>
          <span style={{ 
            background: statusTab === 'expired' ? 'rgba(0,0,0,0.25)' : 'rgba(148, 163, 184, 0.15)', 
            color: statusTab === 'expired' ? '#fff' : '#94a3b8',
            padding: '1px 7px', 
            borderRadius: '10px', 
            fontSize: '11px',
            fontFamily: 'var(--font-mono)' 
          }}>
            {(summary.count_expired || 0).toLocaleString()}
          </span>
        </button>
      </div>

      {/* Profile Breakdown Pills */}
      {profileBreakdown.length > 0 && statusTab === 'all' && (
        <div className="glass-card" style={{ marginBottom: '20px', padding: '14px 18px' }}>
          <div style={{ fontSize: '11.5px', fontWeight: 700, color: 'var(--text-muted)', marginBottom: '10px', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
            Distribusi Paket Terjual
          </div>
          <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
            {profileBreakdown.map((p, idx) => (
              <div 
                key={idx} 
                style={{
                  background: 'var(--bg-surface)',
                  border: '1px solid var(--border-subtle)',
                  borderRadius: 'var(--radius-md)',
                  padding: '6px 12px',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  fontSize: '12.5px'
                }}
              >
                <span style={{ fontWeight: 600, color: 'var(--accent-cyan)' }}>{p.profile}</span>
                <span style={{ color: 'var(--text-muted)' }}>•</span>
                <span>{p.count}x terjual</span>
                <span style={{ fontWeight: 700, color: 'var(--accent-emerald)', fontFamily: 'var(--font-mono)' }}>
                  Rp {p.revenue.toLocaleString('id-ID')}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

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
              {statusTab === 'active' && 'Daftar Voucher Sementara Terpakai (Sesi Aktif)'}
              {statusTab === 'unused' && 'Daftar Stok Voucher Belum Terpakai (Siap Jual)'}
              {statusTab === 'expired' && 'Daftar Voucher Habis Terpakai (Kedaluwarsa)'}
              {statusTab === 'all' && 'Semua Transaksi & Status Voucher'}
            </h3>
            <div style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
              Menampilkan {pagination.total ? ((page - 1) * 25 + 1) : 0} - {Math.min(page * 25, pagination.total || 0)} dari {pagination.total || 0} item
            </div>
          </div>

          <div className="search-box" style={{ maxWidth: '300px', width: '100%' }}>
            <Search size={14} color="var(--text-muted)" />
            <input 
              type="text" 
              placeholder="Cari voucher, router, paket, IP..."
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>
        </div>

        <div className="table-container" style={{ border: 'none' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Kode Voucher</th>
                <th>Status</th>
                {selectedRouter === 'all' && <th>Router</th>}
                <th>Paket / Masa Aktif</th>
                <th>Sisa Waktu / Uptime</th>
                <th>Koneksi / IP & Kuota</th>
                <th>Tanggal</th>
                <th style={{ textAlign: 'right' }}>Harga</th>
              </tr>
            </thead>
            <tbody>
              {loading && vouchers.length === 0 ? (
                <tr>
                  <td colSpan={selectedRouter === 'all' ? 8 : 7} style={{ textAlign: 'center', padding: '50px 20px' }}>
                    <RefreshCw size={26} className="spin-anim" style={{ margin: '0 auto 12px', color: 'var(--accent-cyan)' }} />
                    <p style={{ color: 'var(--text-muted)', fontSize: '13.5px' }}>Memuat data voucher dari router...</p>
                  </td>
                </tr>
              ) : vouchers.length === 0 ? (
                <tr>
                  <td colSpan={selectedRouter === 'all' ? 8 : 7} style={{ textAlign: 'center', padding: '50px 20px', color: 'var(--text-muted)' }}>
                    Tidak ada voucher dengan filter "{statusTab}" {search ? `dan kata kunci "${search}"` : ''}.
                  </td>
                </tr>
              ) : (
                vouchers.map((t, idx) => {
                  const isCopied = copiedUser === t.username;
                  return (
                    <tr key={idx}>
                      {/* Kode Voucher / User */}
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                          <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 700, color: '#fff', fontSize: '13.5px' }}>
                            {t.username}
                          </span>
                          <button
                            className="btn btn-icon btn-sm"
                            style={{ padding: '3px 5px', height: 'auto', background: 'transparent' }}
                            onClick={() => handleCopy(t.username)}
                            title="Salin kode voucher"
                          >
                            {isCopied ? <Check size={12} color="var(--accent-emerald)" /> : <Copy size={12} color="var(--text-muted)" />}
                          </button>
                        </div>
                        {t.password && t.password !== t.username && t.status === 'unused' && (
                          <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontFamily: 'var(--font-mono)' }}>
                            Pass: {t.password}
                          </div>
                        )}
                      </td>

                      {/* Status Badge */}
                      <td>
                        {t.status === 'active' && (
                          <span className="tag tag-emerald" style={{ display: 'inline-flex', alignItems: 'center', gap: '6px' }}>
                            <span className="pulse-dot-green"></span>
                            Sementara Terpakai
                          </span>
                        )}
                        {t.status === 'unused' && (
                          <span className="tag tag-amber">
                            Belum Terpakai
                          </span>
                        )}
                        {t.status === 'expired' && (
                          <span className="tag tag-slate">
                            Habis Terpakai
                          </span>
                        )}
                      </td>

                      {/* Router Column (if all selected) */}
                      {selectedRouter === 'all' && (
                        <td>
                          <span className="tag tag-cyan" style={{ fontSize: '11px', fontWeight: 600 }}>
                            {t.router_name || t.router_session}
                          </span>
                        </td>
                      )}

                      {/* Paket / Profil */}
                      <td>
                        <span className="tag tag-purple">{t.profile || 'Standar'}</span>
                        {t.batch && t.batch !== '-' && (
                          <div style={{ fontSize: '11px', color: 'var(--text-muted)', marginTop: '3px', fontFamily: 'var(--font-mono)' }}>
                            {t.batch}
                          </div>
                        )}
                      </td>

                      {/* Sisa Waktu / Uptime */}
                      <td>
                        {t.status === 'active' ? (
                          <div>
                            <div style={{ fontWeight: 600, color: 'var(--accent-emerald)', fontSize: '12.5px' }}>
                              Sisa: {t.session_left || '-'}
                            </div>
                            <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontFamily: 'var(--font-mono)' }}>
                              Uptime: {t.uptime}
                            </div>
                          </div>
                        ) : t.status === 'unused' ? (
                          <div>
                            <div style={{ color: 'var(--text-primary)', fontSize: '12.5px' }}>
                              Limit: {t.session_left || t.validity || '-'}
                            </div>
                            <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                              Belum Login
                            </div>
                          </div>
                        ) : (
                          <div>
                            <div style={{ color: 'var(--text-muted)', fontSize: '12.5px' }}>
                              Waktu Habis
                            </div>
                            <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                              Durasi: {t.validity || '-'}
                            </div>
                          </div>
                        )}
                      </td>

                      {/* Koneksi / IP & Kuota */}
                      <td>
                        {t.ip && t.ip !== '-' ? (
                          <div>
                            <div style={{ fontFamily: 'var(--font-mono)', fontSize: '12px', color: 'var(--text-primary)' }}>
                              {t.ip}
                            </div>
                            <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontFamily: 'var(--font-mono)' }}>
                              {t.mac !== '-' ? t.mac : ''} {t.bytes_human && t.bytes_human !== '0 B' && t.bytes_human !== '-' ? `• ${t.bytes_human}` : ''}
                            </div>
                          </div>
                        ) : (
                          <span style={{ color: 'var(--text-muted)' }}>-</span>
                        )}
                      </td>

                      {/* Tanggal & Waktu */}
                      <td>
                        <div style={{ fontWeight: 500, color: 'var(--text-primary)', fontSize: '12.5px' }}>
                          {t.date || '-'}
                        </div>
                        {t.time && t.time !== '-' && (
                          <div style={{ fontFamily: 'var(--font-mono)', fontSize: '11px', color: 'var(--text-muted)' }}>
                            {t.time}
                          </div>
                        )}
                      </td>

                      {/* Harga */}
                      <td style={{ textAlign: 'right', fontWeight: 700, color: t.price > 0 ? 'var(--accent-emerald)' : 'var(--text-muted)', fontFamily: 'var(--font-mono)', fontSize: '13px' }}>
                        {t.price_formatted || `Rp ${(t.price || 0).toLocaleString('id-ID')}`}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination Footer */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '14px 20px',
          borderTop: '1px solid var(--border-subtle)',
          fontSize: '13px',
          color: 'var(--text-muted)',
          flexWrap: 'wrap',
          gap: '12px'
        }}>
          <div>
            Total <strong style={{ color: '#fff' }}>{pagination.total || 0}</strong> voucher ditemukan
          </div>

          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <button
              className="btn btn-secondary btn-sm"
              disabled={page <= 1}
              onClick={() => setPage(p => Math.max(p - 1, 1))}
            >
              <ChevronLeft size={14} />
              <span>Sebelumnya</span>
            </button>
            <span style={{ padding: '0 8px', fontFamily: 'var(--font-mono)', fontSize: '12.5px' }}>
              Hal {page} dari {pagination.total_pages || 1}
            </span>
            <button
              className="btn btn-secondary btn-sm"
              disabled={page >= (pagination.total_pages || 1)}
              onClick={() => setPage(p => Math.min(p + 1, pagination.total_pages || 1))}
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
