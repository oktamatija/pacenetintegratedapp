import React, { useState, useEffect, useMemo } from 'react';
import { 
  DollarSign, 
  TrendingUp, 
  Search, 
  Download, 
  RefreshCw, 
  ChevronLeft, 
  ChevronRight, 
  Wifi, 
  Ticket, 
  Clock, 
  Copy, 
  Check, 
  Filter,
  Server,
  Calendar,
  CalendarDays,
  BarChart3
} from 'lucide-react';

export default function Reports() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [statusTab, setStatusTab] = useState('all'); // 'all', 'active', 'unused', 'expired'
  const [selectedRouter, setSelectedRouter] = useState('all');
  const [copiedUser, setCopiedUser] = useState(null);

  // Time-based filtering state
  const [periodType, setPeriodType] = useState('daily'); // 'daily', 'weekly', 'monthly', 'yearly', 'all'
  
  // Datepicker values
  const today = useMemo(() => new Date().toISOString().slice(0, 10), []);
  const currentMonth = useMemo(() => new Date().toISOString().slice(0, 7), []);
  const currentYear = useMemo(() => new Date().getFullYear().toString(), []);

  const [selectedDate, setSelectedDate] = useState(today);
  const [selectedMonth, setSelectedMonth] = useState(currentMonth);
  const [selectedYear, setSelectedYear] = useState(currentYear);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  const fetchReports = async (forceRefresh = false) => {
    setLoading(true);
    try {
      const q = new URLSearchParams({
        page: page.toString(),
        limit: '25',
        router: selectedRouter,
        status: statusTab,
        search,
        period_type: periodType
      });

      if (periodType === 'daily') {
        q.set('date', selectedDate || today);
      } else if (periodType === 'weekly') {
        if (startDate && endDate) {
          q.set('start_date', startDate);
          q.set('end_date', endDate);
        } else {
          q.set('date', selectedDate || today);
        }
      } else if (periodType === 'monthly') {
        q.set('month', selectedMonth || currentMonth);
      } else if (periodType === 'yearly') {
        q.set('year', selectedYear || currentYear);
      }

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
  }, [page, statusTab, selectedRouter, periodType, selectedDate, selectedMonth, selectedYear, startDate, endDate]);

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
  const chartData = data?.chart || null;

  // Quick action helpers
  const handleSetToday = () => {
    setSelectedDate(today);
    setStartDate('');
    setEndDate('');
    setPage(1);
  };

  const handleSetYesterday = () => {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    setSelectedDate(d.toISOString().slice(0, 10));
    setStartDate('');
    setEndDate('');
    setPage(1);
  };

  const handleSetThisMonth = () => {
    setSelectedMonth(currentMonth);
    setPage(1);
  };

  const handleSetLastMonth = () => {
    const d = new Date();
    d.setMonth(d.getMonth() - 1);
    setSelectedMonth(d.toISOString().slice(0, 7));
    setPage(1);
  };

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
    const periodTag = summary.period_label ? summary.period_label.replace(/\s+/g, '_') : periodType;
    a.download = `pacenet-sales-${selectedRouter}-${periodTag}-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
  };

  const handleExportExcel = () => {
    if (!vouchers.length) return;
    const periodTag = summary.period_label || periodType;
    let xml = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Header">
   <Font ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#0984E3" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Currency">
   <NumberFormat ss:Format="Rp #,##0"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Rekap Penjualan">
  <Table>
   <Row ss:StyleID="Header">
    <Cell><Data ss:Type="String">Router</Data></Cell>
    <Cell><Data ss:Type="String">Status</Data></Cell>
    <Cell><Data ss:Type="String">Kode Voucher</Data></Cell>
    <Cell><Data ss:Type="String">Paket / Profil</Data></Cell>
    <Cell><Data ss:Type="String">Harga (Rp)</Data></Cell>
    <Cell><Data ss:Type="String">Masa Aktif / Sisa</Data></Cell>
    <Cell><Data ss:Type="String">Uptime</Data></Cell>
    <Cell><Data ss:Type="String">IP Address</Data></Cell>
    <Cell><Data ss:Type="String">MAC Address</Data></Cell>
    <Cell><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell><Data ss:Type="String">Waktu</Data></Cell>
   </Row>`;
    vouchers.forEach(t => {
      xml += `
   <Row>
    <Cell><Data ss:Type="String">${t.router_name || t.router_session || ''}</Data></Cell>
    <Cell><Data ss:Type="String">${t.status_label || t.status}</Data></Cell>
    <Cell><Data ss:Type="String">${t.username}</Data></Cell>
    <Cell><Data ss:Type="String">${t.profile}</Data></Cell>
    <Cell ss:StyleID="Currency"><Data ss:Type="Number">${t.price || 0}</Data></Cell>
    <Cell><Data ss:Type="String">${t.session_left || t.validity || ''}</Data></Cell>
    <Cell><Data ss:Type="String">${t.uptime || ''}</Data></Cell>
    <Cell><Data ss:Type="String">${t.ip || ''}</Data></Cell>
    <Cell><Data ss:Type="String">${t.mac || ''}</Data></Cell>
    <Cell><Data ss:Type="String">${t.date || ''}</Data></Cell>
    <Cell><Data ss:Type="String">${t.time || ''}</Data></Cell>
   </Row>`;
    });
    xml += `
  </Table>
 </Worksheet>
</Workbook>`;
    const blob = new Blob([xml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `pacenet-report-${selectedRouter}-${periodTag.replace(/\s+/g, '_')}-${new Date().toISOString().slice(0, 10)}.xls`;
    a.click();
  };

  const handleExportPDF = () => {
    window.print();
  };

  // Calculate max for chart scale
  const maxRevenue = useMemo(() => {
    if (!chartData?.revenue_series || chartData.revenue_series.length === 0) return 1;
    return Math.max(...chartData.revenue_series, 1);
  }, [chartData]);

  return (
    <div className="reports-page-container">
      {/* Printable PDF Header (only visible during print) */}
      <div className="print-only" style={{ display: 'none', marginBottom: '20px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', borderBottom: '2px solid #000', paddingBottom: '10px' }}>
          <div>
            <h1 style={{ fontSize: '20px', fontWeight: 800, margin: 0 }}>PACENET PRO | CLOUD NOC & BILLING</h1>
            <p style={{ fontSize: '12px', margin: '4px 0 0' }}>Laporan Rekap Penjualan & Voucher Hotspot</p>
          </div>
          <div style={{ textAlign: 'right', fontSize: '11px' }}>
            <div>Periode: <strong>{summary.period_label || 'Semua Waktu'}</strong></div>
            <div>Router: <strong>{selectedRouter === 'all' ? 'Semua Router' : selectedRouter}</strong></div>
            <div>Dicetak: {new Date().toLocaleString('id-ID')}</div>
          </div>
        </div>
      </div>

      {/* Top Header */}
      <div className="no-print" style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: '12px',
        marginBottom: '16px'
      }}>
        <div>
          <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
            Rekap Penjualan & Laporan
          </h2>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>
            Monitoring multi-router real-time: omzet penjualan per waktu, voucher sedang terpakai, stok siap pakai, dan riwayat voucher kedaluwarsa.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap', alignItems: 'center' }}>
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
            <Download size={13} />
            <span>CSV</span>
          </button>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={handleExportExcel}
            disabled={vouchers.length === 0}
            title="Download laporan dalam format Microsoft Excel (.xls)"
            style={{ color: 'var(--accent-emerald)', borderColor: 'rgba(16, 185, 129, 0.3)' }}
          >
            <Download size={13} />
            <span>Excel</span>
          </button>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={handleExportPDF}
            disabled={vouchers.length === 0}
            title="Cetak atau Simpan Laporan sebagai Dokumen PDF"
            style={{ color: 'var(--accent-rose)', borderColor: 'rgba(244, 63, 94, 0.3)' }}
          >
            <Download size={13} />
            <span>Cetak / PDF</span>
          </button>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={() => fetchReports(true)}
            disabled={loading}
            title="Sinkronisasi data langsung dari semua router MikroTik"
          >
            <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
            <span>Sync</span>
          </button>
        </div>
      </div>

      {/* Time-Based Period Selector & Datepicker Control Card */}
      <div className="glass-card" style={{ marginBottom: '20px', padding: '14px 18px' }}>
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '14px'
        }}>
          {/* Period Tabs: Per-Hari, Per-Minggu, Per-Bulan, Per-Tahun, Semua */}
          <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', alignItems: 'center' }}>
            <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '5px', marginRight: '4px' }}>
              <Calendar size={14} color="var(--accent-cyan)" /> Periode:
            </span>
            <button
              className={`btn btn-sm ${periodType === 'daily' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => { setPeriodType('daily'); setPage(1); }}
            >
              <span>Per-Hari</span>
            </button>
            <button
              className={`btn btn-sm ${periodType === 'weekly' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => { setPeriodType('weekly'); setPage(1); }}
            >
              <span>Per-Minggu</span>
            </button>
            <button
              className={`btn btn-sm ${periodType === 'monthly' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => { setPeriodType('monthly'); setPage(1); }}
            >
              <span>Per-Bulan</span>
            </button>
            <button
              className={`btn btn-sm ${periodType === 'yearly' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => { setPeriodType('yearly'); setPage(1); }}
            >
              <span>Per-Tahun</span>
            </button>
            <button
              className={`btn btn-sm ${periodType === 'all' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => { setPeriodType('all'); setPage(1); }}
            >
              <span>Semua Waktu</span>
            </button>
          </div>

          {/* Interactive Datepicker Controls based on selected period */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
            {periodType === 'daily' && (
              <>
                <input 
                  type="date" 
                  value={selectedDate}
                  onChange={e => { setSelectedDate(e.target.value); setPage(1); }}
                  style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    padding: '5px 10px',
                    fontSize: '12.5px',
                    fontFamily: 'var(--font-mono)',
                    outline: 'none',
                    cursor: 'pointer'
                  }}
                  title="Pilih tanggal penjualan"
                />
                <button className="btn btn-secondary btn-sm" onClick={handleSetToday} title="Pilih Hari Ini">
                  Hari Ini
                </button>
                <button className="btn btn-secondary btn-sm" onClick={handleSetYesterday} title="Pilih Kemarin">
                  Kemarin
                </button>
              </>
            )}

            {periodType === 'weekly' && (
              <>
                <span style={{ fontSize: '12px', color: 'var(--text-muted)' }}>Pilih Tanggal Acuan:</span>
                <input 
                  type="date" 
                  value={selectedDate}
                  onChange={e => { setSelectedDate(e.target.value); setStartDate(''); setEndDate(''); setPage(1); }}
                  style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    padding: '5px 10px',
                    fontSize: '12.5px',
                    fontFamily: 'var(--font-mono)',
                    outline: 'none',
                    cursor: 'pointer'
                  }}
                  title="Pilih tanggal dalam minggu yang diinginkan"
                />
                <button className="btn btn-secondary btn-sm" onClick={handleSetToday} title="Minggu Berjalan">
                  Minggu Ini
                </button>
              </>
            )}

            {periodType === 'monthly' && (
              <>
                <input 
                  type="month" 
                  value={selectedMonth}
                  onChange={e => { setSelectedMonth(e.target.value); setPage(1); }}
                  style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    padding: '5px 10px',
                    fontSize: '12.5px',
                    fontFamily: 'var(--font-mono)',
                    outline: 'none',
                    cursor: 'pointer'
                  }}
                  title="Pilih bulan penjualan"
                />
                <button className="btn btn-secondary btn-sm" onClick={handleSetThisMonth} title="Bulan Berjalan">
                  Bulan Ini
                </button>
                <button className="btn btn-secondary btn-sm" onClick={handleSetLastMonth} title="Bulan Lalu">
                  Bulan Lalu
                </button>
              </>
            )}

            {periodType === 'yearly' && (
              <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                <span style={{ fontSize: '12px', color: 'var(--text-muted)' }}>Tahun:</span>
                <select 
                  value={selectedYear}
                  onChange={e => { setSelectedYear(e.target.value); setPage(1); }}
                  style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-md)',
                    color: '#fff',
                    padding: '5px 12px',
                    fontSize: '12.5px',
                    fontWeight: 700,
                    outline: 'none',
                    cursor: 'pointer'
                  }}
                >
                  {['2027', '2026', '2025', '2024', '2023'].map(y => (
                    <option key={y} value={y} style={{ background: '#111722', color: '#fff' }}>
                      {y}
                    </option>
                  ))}
                </select>
              </div>
            )}

            {periodType === 'all' && (
              <span style={{ fontSize: '12px', color: 'var(--accent-cyan)', fontWeight: 600 }}>
                ✓ Akumulasi Sepanjang Waktu
              </span>
            )}
          </div>
        </div>

        {/* Active Period Label Tag */}
        <div style={{ 
          marginTop: '10px', 
          paddingTop: '10px', 
          borderTop: '1px solid rgba(255, 255, 255, 0.05)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          fontSize: '12px',
          color: 'var(--text-secondary)'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span>Menampilkan data untuk periode:</span>
            <strong style={{ color: 'var(--accent-cyan)', fontFamily: 'var(--font-mono)' }}>
              {summary.period_label || 'Semua Waktu'}
            </strong>
          </div>
          {periodType !== 'all' && summary.all_time_revenue_formatted && (
            <div style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
              Akumulasi All-Time: <strong style={{ color: '#fff' }}>{summary.all_time_revenue_formatted}</strong> ({summary.all_time_sales_count || 0}x)
            </div>
          )}
        </div>
      </div>

      {/* 4 Status KPI Cards */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: '16px',
        marginBottom: '20px'
      }}>
        {/* KPI 1: Omzet Penjualan (sesuai periode terpilih) */}
        <div className="glass-card kpi-card emerald" style={{ margin: 0 }}>
          <div className="kpi-info">
            <h3 style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', fontWeight: 700 }}>
              {periodType === 'daily' && 'Omzet Hari Ini'}
              {periodType === 'weekly' && 'Omzet Minggu Ini'}
              {periodType === 'monthly' && 'Omzet Bulan Ini'}
              {periodType === 'yearly' && 'Omzet Tahun Ini'}
              {periodType === 'all' && 'Total Omzet Terjual'}
            </h3>
            <div className="kpi-value" style={{ color: 'var(--accent-emerald)', fontSize: '22px', fontWeight: 800 }}>
              {summary.total_revenue_formatted || 'Rp 0'}
            </div>
            <div className="kpi-sub" style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
              {summary.period_sales_count || 0} voucher terjual pada periode ini
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
              Sesi aktif real-time di router
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
              Stok siap jual: {summary.potential_unused_revenue_formatted || 'Rp 0'}
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
              {periodType !== 'all' ? 'Kedaluwarsa pada periode ini' : 'Masa aktif tuntas & kedaluwarsa'}
            </div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-purple)' }}>
            <Clock size={24} />
          </div>
        </div>
      </div>

      {/* Visual Sales Trend Bar Chart (if chart data exists and period is not all) */}
      {chartData && chartData.categories && chartData.categories.length > 0 && periodType !== 'all' && (
        <div className="glass-card" style={{ marginBottom: '20px', padding: '18px 20px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px', flexWrap: 'wrap', gap: '8px' }}>
            <div>
              <h3 style={{ fontSize: '14.5px', fontWeight: 700, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <BarChart3 size={16} color="var(--accent-emerald)" />
                Distribusi Pendapatan Penjualan ({summary.period_label})
              </h3>
              <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
                Tren volume penjualan voucher per interval waktu dalam periode terpilih
              </p>
            </div>
            <div style={{ fontSize: '12px', color: 'var(--accent-emerald)', fontWeight: 700, fontFamily: 'var(--font-mono)' }}>
              Total: {summary.total_revenue_formatted}
            </div>
          </div>

          <div style={{ 
            height: '140px', 
            width: '100%', 
            overflowX: 'auto', 
            display: 'flex', 
            alignItems: 'flex-end', 
            gap: '8px', 
            paddingBottom: '22px', 
            paddingTop: '10px' 
          }}>
            {chartData.categories.map((cat, idx) => {
              const rev = chartData.revenue_series[idx] || 0;
              const count = chartData.count_series[idx] || 0;
              const barHeight = Math.max(Math.round((rev / maxRevenue) * 90), rev > 0 ? 6 : 2);
              const isZero = rev === 0;

              return (
                <div 
                  key={idx} 
                  style={{ 
                    flex: 1, 
                    minWidth: '20px', 
                    display: 'flex', 
                    flexDirection: 'column', 
                    alignItems: 'center', 
                    height: '100%', 
                    justifyContent: 'flex-end',
                    position: 'relative'
                  }}
                  title={`${cat}: Rp ${rev.toLocaleString('id-ID')} (${count} voucher)`}
                >
                  <div style={{ 
                    width: '100%', 
                    maxWidth: '32px',
                    height: `${barHeight}px`, 
                    background: isZero ? 'rgba(255, 255, 255, 0.06)' : 'linear-gradient(180deg, var(--accent-emerald) 0%, #059669 100%)', 
                    borderRadius: '3px 3px 0 0',
                    transition: 'all 0.3s ease',
                    boxShadow: isZero ? 'none' : '0 0 8px rgba(16, 185, 129, 0.3)'
                  }} />
                  <div style={{ 
                    fontSize: '9.5px', 
                    color: isZero ? 'var(--text-muted)' : '#fff', 
                    marginTop: '6px', 
                    whiteSpace: 'nowrap',
                    fontFamily: 'var(--font-mono)',
                    fontWeight: isZero ? 400 : 600
                  }}>
                    {cat}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Segmented Filter Pills (All, Active, Unused, Expired) */}
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
            {(pagination.total || 0).toLocaleString()}
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
      {profileBreakdown.length > 0 && (
        <div className="glass-card" style={{ marginBottom: '20px', padding: '14px 18px' }}>
          <div style={{ fontSize: '11.5px', fontWeight: 700, color: 'var(--text-muted)', marginBottom: '10px', textTransform: 'uppercase', letterSpacing: '0.5px', display: 'flex', justifyContent: 'space-between' }}>
            <span>Distribusi Paket Terjual ({summary.period_label || 'Semua Waktu'})</span>
            <span style={{ color: 'var(--accent-emerald)' }}>
              {summary.total_revenue_formatted}
            </span>
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
              {statusTab === 'expired' && `Daftar Voucher Habis Terpakai (${summary.period_label || 'Semua Waktu'})`}
              {statusTab === 'all' && `Semua Transaksi & Status Voucher (${summary.period_label || 'Semua Waktu'})`}
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
                    Tidak ada voucher dengan filter "{statusTab}" pada periode ini {search ? `dan kata kunci "${search}"` : ''}.
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
