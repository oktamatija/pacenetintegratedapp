import React, { useState, useEffect } from 'react';
import { Server, Cpu, HardDrive, Clock, ShieldCheck, RefreshCw, Activity, Terminal } from 'lucide-react';

export default function VpsResource() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  const fetchSystem = async () => {
    setLoading(true);
    try {
      const res = await fetch('/api/system.php');
      const json = await res.json();
      if (json.success) {
        setData(json.data);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSystem();
    const timer = setInterval(fetchSystem, 6000);
    return () => clearInterval(timer);
  }, []);

  const cpu = data?.cpu || { '1m': 0, '5m': 0, '15m': 0 };
  const ram = data?.ram || { total: '0 B', used: '0 B', free: '0 B', percent: 0 };
  const disk = data?.disk || { total: '0 B', used: '0 B', free: '0 B', percent: 0 };
  const peers = data?.wireguard_peers || [];

  return (
    <div>
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
            VPS Cloud Health & WireGuard Mesh
          </h2>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>
            Status sumber daya server pusat (202.10.46.222) dan link VPN MikroTik terenkripsi.
          </p>
        </div>

        <button 
          className="btn btn-secondary btn-sm"
          onClick={fetchSystem}
          disabled={loading}
        >
          <RefreshCw size={13} className={loading ? 'spin-anim' : ''} />
          <span>Sync</span>
        </button>
      </div>

      {/* KPI Cards */}
      <div className="kpi-grid">
        {/* CPU Load */}
        <div className="glass-card kpi-card">
          <div className="kpi-info">
            <h3>Beban CPU (1m / 5m / 15m)</h3>
            <div className="kpi-value" style={{ fontSize: '20px' }}>
              {cpu['1m']} / {cpu['5m']} / {cpu['15m']}
            </div>
            <div className="kpi-sub">Load Average Linux Kernel</div>
          </div>
          <div className="kpi-icon">
            <Cpu size={24} />
          </div>
        </div>

        {/* RAM Usage */}
        <div className="glass-card kpi-card emerald">
          <div className="kpi-info">
            <h3>Penggunaan RAM ({ram.percent}%)</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-emerald)', fontSize: '20px' }}>
              {ram.used} / {ram.total}
            </div>
            <div className="kpi-sub">Free: {ram.free}</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-emerald)' }}>
            <Activity size={24} />
          </div>
        </div>

        {/* Disk Space */}
        <div className="glass-card kpi-card blue">
          <div className="kpi-info">
            <h3>Kapasitas Penyimpanan Disk ({disk.percent}%)</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-blue)', fontSize: '20px' }}>
              {disk.used} / {disk.total}
            </div>
            <div className="kpi-sub">Free: {disk.free}</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-blue)' }}>
            <HardDrive size={24} />
          </div>
        </div>

        {/* VPS Uptime */}
        <div className="glass-card kpi-card purple">
          <div className="kpi-info">
            <h3>Uptime Server VPS</h3>
            <div className="kpi-value" style={{ fontSize: '20px' }}>
              {data?.uptime || '-'}
            </div>
            <div className="kpi-sub">{data?.os || 'Linux'}</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-purple)' }}>
            <Clock size={24} />
          </div>
        </div>
      </div>

      {/* WireGuard VPN Tunnel Nodes */}
      <div className="glass-card">
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '14px' }}>
          <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <ShieldCheck size={18} color="var(--accent-cyan)" />
            Terkoneksi Melalui WireGuard Mesh VPN ({peers.length} Node)
          </h3>
          <span className="tag tag-emerald">ENC256 SECURED</span>
        </div>

        <div className="table-container">
          <table className="data-table">
            <thead>
              <tr>
                <th>Interface</th>
                <th>Public Key Peer</th>
                <th>Endpoint IP Pelanggan</th>
                <th>Allowed Subnet VPN</th>
                <th>Handshake Terakhir</th>
                <th>Throughput Transfer</th>
              </tr>
            </thead>
            <tbody>
              {peers.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '30px', color: 'var(--text-muted)' }}>
                    Memeriksa status WireGuard tunnel...
                  </td>
                </tr>
              ) : (
                peers.map((p, idx) => (
                  <tr key={idx}>
                    <td style={{ fontWeight: 700, color: 'var(--accent-cyan)' }}>{p.interface}</td>
                    <td style={{ fontFamily: 'var(--font-mono)', fontSize: '11px', color: 'var(--text-muted)' }}>{p.public_key}</td>
                    <td style={{ fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)' }}>{p.endpoint}</td>
                    <td style={{ fontFamily: 'var(--font-mono)', fontWeight: 600 }}>{p.allowed_ips}</td>
                    <td style={{ fontSize: '12px', color: p.latest_handshake.includes('Never') ? 'var(--accent-rose)' : 'var(--accent-emerald)' }}>
                      {p.latest_handshake}
                    </td>
                    <td style={{ fontFamily: 'var(--font-mono)', fontSize: '11.5px' }}>
                      ↓ {p.rx_bytes} &nbsp;|&nbsp; ↑ {p.tx_bytes}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
