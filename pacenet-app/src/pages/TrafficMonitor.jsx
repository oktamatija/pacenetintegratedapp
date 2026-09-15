import React, { useState, useEffect } from 'react';
import { BarChart3, ArrowDown, ArrowUp, Filter, RefreshCw, Calendar, HardDrive } from 'lucide-react';

export default function TrafficMonitor() {
  const [period, setPeriod] = useState('hourly');
  const [selectedRouter, setSelectedRouter] = useState('all');
  const [trafficData, setTrafficData] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchTraffic = async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/traffic.php?period=${period}&router=${selectedRouter}`);
      const json = await res.json();
      if (json.success) {
        setTrafficData(json.data);
      }
    } catch (e) {
      console.error('Failed to load traffic', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTraffic();
  }, [period, selectedRouter]);

  const periods = [
    { id: 'hourly', label: '24 Jam' },
    { id: 'daily', label: '30 Hari' },
    { id: 'weekly', label: '12 Minggu' },
    { id: 'monthly', label: '12 Bulan' },
    { id: 'yearly', label: '5 Tahun' }
  ];

  const configuredRouters = trafficData?.configuredRouters || {
    'Rumah-DOLPHIN': 'Rumah Dolphin',
    'Dolphin-Hamadi': 'dolphin hamadi',
    'MikroTik-New': 'MikroTik-New'
  };

  const totals = trafficData?.totals || {};
  const categories = trafficData?.categories || [];
  const rxSeries = trafficData?.rxSeries || [];
  const txSeries = trafficData?.txSeries || [];
  const unit = trafficData?.unit || 'MB';

  // Calculate max for SVG bar chart scaling
  const maxVal = Math.max(...rxSeries, ...txSeries, 1);

  return (
    <div>
      {/* Control Bar */}
      <div className="glass-card" style={{ marginBottom: '20px', padding: '16px 20px' }}>
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '14px'
        }}>
          {/* Period Selector Tabs */}
          <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
            {periods.map(p => (
              <button
                key={p.id}
                className={`btn btn-sm ${period === p.id ? 'btn-primary' : 'btn-secondary'}`}
                onClick={() => setPeriod(p.id)}
              >
                <Calendar size={13} />
                <span>{p.label}</span>
              </button>
            ))}
          </div>

          {/* Router Filter Pills */}
          <div style={{ display: 'flex', gap: '6px', alignItems: 'center', flexWrap: 'wrap' }}>
            <span style={{ fontSize: '12px', color: 'var(--text-muted)', display: 'flex', alignItems: 'center', gap: '4px' }}>
              <Filter size={13} /> Filter Node:
            </span>
            <button
              className={`btn btn-sm ${selectedRouter === 'all' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setSelectedRouter('all')}
            >
              Semua Router
            </button>
            {Object.entries(configuredRouters).map(([key, name]) => (
              <button
                key={key}
                className={`btn btn-sm ${selectedRouter === key ? 'btn-primary' : 'btn-secondary'}`}
                onClick={() => setSelectedRouter(key)}
              >
                {name}
              </button>
            ))}
            <button 
              className="btn btn-secondary btn-sm"
              onClick={fetchTraffic}
              disabled={loading}
              title="Refresh Data"
            >
              <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
            </button>
          </div>
        </div>
      </div>

      {/* Aggregate Metrics Cards */}
      <div className="kpi-grid">
        <div className="glass-card kpi-card emerald">
          <div className="kpi-info">
            <h3>Total Download (RX)</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-emerald)' }}>
              {totals.rx_formatted || '0 B'}
            </div>
            <div className="kpi-sub">Total data masuk dalam periode ini</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-emerald)' }}>
            <ArrowDown size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card blue">
          <div className="kpi-info">
            <h3>Total Upload (TX)</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-blue)' }}>
              {totals.tx_formatted || '0 B'}
            </div>
            <div className="kpi-sub">Total data keluar dalam periode ini</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-blue)' }}>
            <ArrowUp size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card purple">
          <div className="kpi-info">
            <h3>Total Konsumsi Kuota</h3>
            <div className="kpi-value">
              {totals.combined_formatted || '0 B'}
            </div>
            <div className="kpi-sub">Kombinasi RX + TX seluruh node</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-purple)' }}>
            <HardDrive size={24} />
          </div>
        </div>
      </div>

      {/* Interactive Bar Chart */}
      <div className="glass-card" style={{ marginBottom: '24px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
          <div>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff' }}>
              Grafik Throughput Bandwidth ({unit})
            </h3>
            <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
              Distribusi volume traffic antar interval waktu
            </p>
          </div>

          <div style={{ display: 'flex', gap: '14px', fontSize: '12px' }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <span style={{ width: '10px', height: '10px', background: 'var(--accent-emerald)', borderRadius: '2px' }} />
              Download (RX)
            </span>
            <span style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
              <span style={{ width: '10px', height: '10px', background: 'var(--accent-blue)', borderRadius: '2px' }} />
              Upload (TX)
            </span>
          </div>
        </div>

        {/* Visual Bar Chart */}
        <div style={{ height: '220px', width: '100%', overflowX: 'auto', display: 'flex', alignItems: 'flex-end', gap: '10px', paddingBottom: '24px', position: 'relative' }}>
          {categories.map((cat, idx) => {
            const rxH = Math.round((rxSeries[idx] / maxVal) * 160);
            const txH = Math.round((txSeries[idx] / maxVal) * 160);
            return (
              <div 
                key={idx} 
                style={{ 
                  flex: 1, 
                  minWidth: '24px', 
                  display: 'flex', 
                  flexDirection: 'column', 
                  alignItems: 'center', 
                  height: '100%', 
                  justifyContent: 'flex-end' 
                }}
                title={`${cat}: RX ${rxSeries[idx]} ${unit} | TX ${txSeries[idx]} ${unit}`}
              >
                <div style={{ display: 'flex', gap: '2px', alignItems: 'flex-end', width: '100%' }}>
                  <div style={{ 
                    flex: 1, 
                    height: `${Math.max(rxH, 3)}px`, 
                    background: 'var(--accent-emerald)', 
                    borderRadius: '2px 2px 0 0',
                    transition: 'height 0.3s ease'
                  }} />
                  <div style={{ 
                    flex: 1, 
                    height: `${Math.max(txH, 3)}px`, 
                    background: 'var(--accent-blue)', 
                    borderRadius: '2px 2px 0 0',
                    transition: 'height 0.3s ease'
                  }} />
                </div>
                <div style={{ 
                  fontSize: '10px', 
                  color: 'var(--text-muted)', 
                  marginTop: '6px', 
                  transform: 'rotate(-45deg)',
                  whiteSpace: 'nowrap'
                }}>
                  {cat}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Detail Table */}
      <div className="glass-card">
        <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff', marginBottom: '14px' }}>
          Rincian Data Traffic Per Interval
        </h3>

        <div className="table-container">
          <table className="data-table">
            <thead>
              <tr>
                <th>Periode Interval</th>
                <th>Download (RX)</th>
                <th>Upload (TX)</th>
                <th>Total Volume</th>
              </tr>
            </thead>
            <tbody>
              {trafficData?.tableRows?.map((row, idx) => (
                <tr key={idx}>
                  <td style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{row.period_label}</td>
                  <td style={{ color: 'var(--accent-emerald)', fontFamily: 'var(--font-mono)' }}>↓ {row.rx_formatted}</td>
                  <td style={{ color: 'var(--accent-blue)', fontFamily: 'var(--font-mono)' }}>↑ {row.tx_formatted}</td>
                  <td style={{ fontWeight: 700, fontFamily: 'var(--font-mono)' }}>{row.total_formatted}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
