import React, { useState, useEffect } from 'react';
import { 
  Globe, 
  Smartphone, 
  Monitor, 
  ExternalLink, 
  RefreshCw, 
  CheckCircle2, 
  AlertTriangle, 
  Zap, 
  Save, 
  Settings, 
  Plus, 
  Trash2, 
  Radio, 
  ShieldCheck, 
  Wifi, 
  Layers,
  ArrowRight,
  Info
} from 'lucide-react';
import { authFetch } from '../utils/api';

export default function HotspotPortal({ isReadOnly }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [selectedRouter, setSelectedRouter] = useState('');
  const [previewDevice, setPreviewDevice] = useState('mobile'); // 'mobile' | 'desktop'
  const [feedback, setFeedback] = useState(null);
  const [isSaving, setIsSaving] = useState(false);
  const [isDeploying, setIsDeploying] = useState(false);
  const [showDeployModal, setShowDeployModal] = useState(false);
  const [deployLogs, setDeployLogs] = useState([]);

  // Deploy form
  const [deployForm, setDeployForm] = useState({
    interface: 'bridge1',
    ip: '10.0.0.1/22',
    dns_name: 'wifi.papua.net'
  });

  // Portal customize form
  const [portalForm, setPortalForm] = useState({
    brand_name: 'Cibi Cibi Hotspot',
    brand_subtitle: 'WiFi Cepat, Stabil & Terjangkau',
    whatsapp_number: '+62 813-4401-0045',
    prices: [
      { name: '12 Jam', cost: 'Rp 4.000' },
      { name: '1 Minggu', cost: 'Rp 40.000' },
      { name: '1 Bulan', cost: 'Rp 100.000' },
      { name: 'Reseller', cost: 'Paket Khusus' }
    ]
  });

  const fetchConfig = async (router = '') => {
    try {
      setLoading(true);
      const url = router ? `/api/hotspot.php?action=get_config&router=${encodeURIComponent(router)}` : '/api/hotspot.php?action=get_config';
      const res = await authFetch(url);
      const json = await res.json();
      if (json.success && json.data) {
        setData(json.data);
        if (json.data.portal) {
          setPortalForm({
            brand_name: json.data.portal.brand_name || 'Hotspot Yunus',
            brand_subtitle: json.data.portal.brand_subtitle || 'WiFi Cepat, Stabil & Terjangkau',
            whatsapp_number: json.data.portal.whatsapp_number || '+62 813-4401-0045',
            prices: json.data.portal.prices || []
          });
        }
        if (json.data.router_status?.session) {
          setSelectedRouter(json.data.router_status.session);
          setDeployForm(prev => ({
            ...prev,
            dns_name: json.data.available_routers?.find(r => r.session === json.data.router_status.session)?.dns_name || 'wifi.papua.net'
          }));
        }
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal memuat konfigurasi portal hotspot.' });
      }
    } catch (err) {
      setFeedback({ type: 'error', text: 'Kesalahan jaringan saat memuat konfigurasi portal.' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchConfig();
  }, []);

  const handleSavePortal = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo hanya memiliki hak akses lihat.' });
      return;
    }
    setIsSaving(true);
    setFeedback(null);
    try {
      const res = await authFetch('/api/hotspot.php?action=save_config', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(portalForm)
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: 'Kustomisasi tampilan portal hotspot berhasil disimpan!' });
        fetchConfig(selectedRouter);
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal menyimpan pengaturan.' });
      }
    } catch (e) {
      setFeedback({ type: 'error', text: 'Gagal menghubungi server untuk menyimpan kustomisasi.' });
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeployRouter = async () => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo hanya memiliki hak akses lihat.' });
      return;
    }
    setIsDeploying(true);
    setFeedback(null);
    setDeployLogs([]);
    try {
      const res = await authFetch('/api/hotspot.php?action=deploy_router', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session: selectedRouter,
          interface: deployForm.interface,
          ip: deployForm.ip,
          dns_name: deployForm.dns_name
        })
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message || 'Hotspot berhasil dipasang dan diaktifkan di MikroTik!' });
        if (json.data?.logs) {
          setDeployLogs(json.data.logs);
        }
        setShowDeployModal(false);
        fetchConfig(selectedRouter);
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal memasang hotspot ke router.' });
      }
    } catch (e) {
      setFeedback({ type: 'error', text: 'Kesalahan jaringan saat mengeksekusi pemasangan hotspot ke MikroTik.' });
    } finally {
      setIsDeploying(false);
    }
  };

  const handlePriceChange = (index, field, value) => {
    setPortalForm(prev => {
      const updated = [...prev.prices];
      updated[index] = { ...updated[index], [field]: value };
      return { ...prev, prices: updated };
    });
  };

  const addPriceRow = () => {
    setPortalForm(prev => ({
      ...prev,
      prices: [...prev.prices, { name: 'Paket Baru', cost: 'Rp 10.000' }]
    }));
  };

  const removePriceRow = (index) => {
    setPortalForm(prev => ({
      ...prev,
      prices: prev.prices.filter((_, i) => i !== index)
    }));
  };

  const routerStatus = data?.router_status;
  const availableRouters = data?.available_routers || [];
  const interfaces = routerStatus?.interfaces || [];

  return (
    <div className="hotspot-portal-page" style={{ paddingBottom: '50px' }}>
      {/* Top Banner Header */}
      <div className="section-header" style={{ marginBottom: '24px' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '16px' }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
              <div style={{
                background: 'linear-gradient(135deg, rgba(0, 210, 211, 0.2), rgba(10, 189, 227, 0.1))',
                padding: '10px',
                borderRadius: '12px',
                border: '1px solid rgba(0, 210, 211, 0.3)',
                color: '#00d2d3'
              }}>
                <Globe size={24} />
              </div>
              <div>
                <h2 style={{ margin: 0, fontSize: '20px', fontWeight: 800, color: '#f1f5f9' }}>
                  Halaman Login Hotspot & Captive Portal
                </h2>
                <p style={{ margin: 0, fontSize: '13px', color: '#94a3b8' }}>
                  Pratinjau langsung, kustomisasi tampilan voucher, dan aktivasi otomatis hotspot MikroTik
                </p>
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
            <a 
              href="https://hi1271.my.id/hotspot-login/" 
              target="_blank" 
              rel="noreferrer"
              className="btn-glass"
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontSize: '13px', textDecoration: 'none' }}
            >
              <ExternalLink size={15} /> Buka Portal Publik
            </a>
            <button 
              className="btn-glass" 
              onClick={() => fetchConfig(selectedRouter)} 
              disabled={loading}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '6px', fontSize: '13px' }}
            >
              <RefreshCw size={15} className={loading ? 'spin' : ''} /> Segarkan
            </button>
          </div>
        </div>
      </div>

      {/* Notification Feedback */}
      {feedback && (
        <div className={`alert-box ${feedback.type}`} style={{ marginBottom: '20px' }}>
          {feedback.type === 'success' ? <CheckCircle2 size={18} /> : <AlertTriangle size={18} />}
          <span>{feedback.text}</span>
          <button onClick={() => setFeedback(null)} style={{ marginLeft: 'auto', background: 'transparent', border: 'none', color: 'inherit', cursor: 'pointer' }}>&times;</button>
        </div>
      )}

      {/* Router Hotspot Status Bar */}
      <div className="glass-panel" style={{ padding: '20px', marginBottom: '24px' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '16px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '14px', flexWrap: 'wrap' }}>
            <label style={{ fontSize: '13px', fontWeight: 700, color: '#94a3b8' }}>Pilih Router MikroTik:</label>
            <select
              value={selectedRouter}
              onChange={(e) => {
                setSelectedRouter(e.target.value);
                fetchConfig(e.target.value);
              }}
              style={{
                background: '#0f172a',
                border: '1px solid rgba(255, 255, 255, 0.15)',
                color: '#f8fafc',
                padding: '8px 14px',
                borderRadius: '8px',
                fontSize: '13px',
                fontWeight: 600,
                outline: 'none'
              }}
            >
              {availableRouters.map(r => (
                <option key={r.session} value={r.session}>
                  {r.name} ({r.session}) — {r.ip}
                </option>
              ))}
            </select>

            {/* Status Pills */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
              <span className={`badge ${routerStatus?.online ? 'success' : 'danger'}`}>
                <Radio size={12} /> {routerStatus?.online ? 'Router Online' : 'Router Offline'}
              </span>

              <span className={`badge ${routerStatus?.hotspot_server ? 'success' : 'warning'}`}>
                <Wifi size={12} /> {routerStatus?.hotspot_server ? `Hotspot Server: ${routerStatus.hotspot_server.interface || 'Aktif'}` : 'Hotspot Server: Belum Aktif'}
              </span>

              <span className={`badge ${routerStatus?.walled_garden_ok ? 'success' : 'warning'}`}>
                <ShieldCheck size={12} /> {routerStatus?.walled_garden_ok ? 'Walled Garden: Terpasang' : 'Walled Garden: Belum Ada'}
              </span>

              <span className={`badge ${routerStatus?.login_files_ok ? 'success' : 'warning'}`}>
                <Layers size={12} /> {routerStatus?.login_files_ok ? 'Template Login: Siap' : 'Template: Belum Terpasang'}
              </span>
            </div>
          </div>

          <button
            onClick={() => setShowDeployModal(true)}
            disabled={!routerStatus?.online || isDeploying}
            className="btn-primary"
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '8px',
              padding: '10px 18px',
              background: 'linear-gradient(135deg, #00d2d3, #0984e3)',
              boxShadow: '0 4px 14px rgba(0, 210, 211, 0.35)',
              fontWeight: 800,
              fontSize: '13px'
            }}
          >
            <Zap size={16} /> Pasang / Sinkronkan Hotspot ke MikroTik
          </button>
        </div>

        {deployLogs.length > 0 && (
          <div style={{ marginTop: '16px', padding: '14px', background: 'rgba(0, 0, 0, 0.4)', borderRadius: '8px', border: '1px solid rgba(0, 210, 211, 0.2)' }}>
            <div style={{ fontSize: '12px', fontWeight: 800, color: '#00d2d3', marginBottom: '8px' }}>
              Catatan Eksekusi Pemasangan Terakhir:
            </div>
            <ul style={{ margin: 0, paddingLeft: '18px', fontSize: '12px', color: '#cbd5e1' }}>
              {deployLogs.map((log, i) => (
                <li key={i} style={{ marginBottom: '4px' }}>{log}</li>
              ))}
            </ul>
          </div>
        )}
      </div>

      {/* Main Split Layout: Preview on Left, Customizer on Right */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(420px, 1fr))', gap: '24px' }}>
        
        {/* LEFT: Live Interactive Preview */}
        <div className="glass-panel" style={{ padding: '24px', display: 'flex', flexDirection: 'column' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '16px' }}>
            <div>
              <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 700, color: '#f1f5f9' }}>
                Pratinjau Halaman Login (Live)
              </h3>
              <p style={{ margin: 0, fontSize: '12px', color: '#94a3b8' }}>
                Tampilan persis seperti yang dilihat pelanggan saat terhubung ke WiFi
              </p>
            </div>

            <div style={{ display: 'flex', background: 'rgba(255,255,255,0.05)', borderRadius: '8px', padding: '3px' }}>
              <button
                onClick={() => setPreviewDevice('mobile')}
                style={{
                  background: previewDevice === 'mobile' ? '#00d2d3' : 'transparent',
                  color: previewDevice === 'mobile' ? '#090c10' : '#94a3b8',
                  border: 'none',
                  padding: '6px 12px',
                  borderRadius: '6px',
                  fontSize: '12px',
                  fontWeight: 700,
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '4px'
                }}
              >
                <Smartphone size={14} /> Mobile
              </button>
              <button
                onClick={() => setPreviewDevice('desktop')}
                style={{
                  background: previewDevice === 'desktop' ? '#00d2d3' : 'transparent',
                  color: previewDevice === 'desktop' ? '#090c10' : '#94a3b8',
                  border: 'none',
                  padding: '6px 12px',
                  borderRadius: '6px',
                  fontSize: '12px',
                  fontWeight: 700,
                  cursor: 'pointer',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '4px'
                }}
              >
                <Monitor size={14} /> Desktop
              </button>
            </div>
          </div>

          {/* Device Frame */}
          <div style={{
            flex: 1,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'rgba(0, 0, 0, 0.5)',
            borderRadius: '16px',
            padding: '20px',
            minHeight: '580px'
          }}>
            <div style={{
              width: previewDevice === 'mobile' ? '375px' : '100%',
              maxWidth: previewDevice === 'mobile' ? '375px' : '650px',
              height: '560px',
              borderRadius: previewDevice === 'mobile' ? '28px' : '12px',
              border: previewDevice === 'mobile' ? '8px solid #1e293b' : '2px solid rgba(255,255,255,0.1)',
              boxShadow: '0 20px 40px rgba(0,0,0,0.6)',
              overflow: 'hidden',
              background: '#fff',
              position: 'relative'
            }}>
              {previewDevice === 'mobile' && (
                <div style={{
                  height: '20px',
                  background: '#1e293b',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center'
                }}>
                  <div style={{ width: '50px', height: '4px', background: '#334155', borderRadius: '2px' }}></div>
                </div>
              )}
              <iframe 
                src="/hotspot-login/?preview=true"
                title="Pratinjau Portal Hotspot"
                style={{
                  width: '100%',
                  height: previewDevice === 'mobile' ? 'calc(100% - 20px)' : '100%',
                  border: 'none'
                }}
              />
            </div>
          </div>
        </div>

        {/* RIGHT: Customizer Form */}
        <div className="glass-panel" style={{ padding: '24px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '20px' }}>
            <div style={{ color: '#00d2d3' }}><Settings size={20} /></div>
            <div>
              <h3 style={{ margin: 0, fontSize: '16px', fontWeight: 700, color: '#f1f5f9' }}>
                Kustomisasi Teks & Branding Portal
              </h3>
              <p style={{ margin: 0, fontSize: '12px', color: '#94a3b8' }}>
                Perubahan langsung diterapkan pada halaman login hotspot tanpa merestart router
              </p>
            </div>
          </div>

          <form onSubmit={handleSavePortal}>
            <div className="form-group" style={{ marginBottom: '16px' }}>
              <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#94a3b8', marginBottom: '6px' }}>
                Nama Brand / Judul Hotspot
              </label>
              <input 
                type="text"
                className="input-field"
                value={portalForm.brand_name}
                onChange={(e) => setPortalForm({ ...portalForm, brand_name: e.target.value })}
                placeholder="Contoh: Hotspot Yunus / PACENET Hotspot"
                required
                style={{ width: '100%' }}
              />
            </div>

            <div className="form-group" style={{ marginBottom: '16px' }}>
              <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#94a3b8', marginBottom: '6px' }}>
                Slogan / Subtitle
              </label>
              <input 
                type="text"
                className="input-field"
                value={portalForm.brand_subtitle}
                onChange={(e) => setPortalForm({ ...portalForm, brand_subtitle: e.target.value })}
                placeholder="Contoh: WiFi Cepat, Stabil & Terjangkau"
                required
                style={{ width: '100%' }}
              />
            </div>

            <div className="form-group" style={{ marginBottom: '20px' }}>
              <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#94a3b8', marginBottom: '6px' }}>
                Nomor WhatsApp Bantuan / Pembelian Voucher
              </label>
              <input 
                type="text"
                className="input-field"
                value={portalForm.whatsapp_number}
                onChange={(e) => setPortalForm({ ...portalForm, whatsapp_number: e.target.value })}
                placeholder="Contoh: +62 813-4401-0045"
                required
                style={{ width: '100%' }}
              />
            </div>

            {/* Pricing Table Customizer */}
            <div style={{ marginBottom: '24px' }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '10px' }}>
                <label style={{ fontSize: '12px', fontWeight: 700, color: '#94a3b8' }}>
                  Daftar Tarif Voucher yang Tampil di Halaman Login:
                </label>
                <button
                  type="button"
                  onClick={addPriceRow}
                  className="btn-glass"
                  style={{ fontSize: '11px', padding: '4px 10px', display: 'flex', alignItems: 'center', gap: '4px' }}
                >
                  <Plus size={12} /> Tambah Baris
                </button>
              </div>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {portalForm.prices.map((p, idx) => (
                  <div key={idx} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <input 
                      type="text"
                      className="input-field"
                      placeholder="Nama Paket (e.g. 12 Jam)"
                      value={p.name}
                      onChange={(e) => handlePriceChange(idx, 'name', e.target.value)}
                      style={{ flex: 1, fontSize: '12px' }}
                    />
                    <input 
                      type="text"
                      className="input-field"
                      placeholder="Harga (e.g. Rp 5.000)"
                      value={p.cost}
                      onChange={(e) => handlePriceChange(idx, 'cost', e.target.value)}
                      style={{ flex: 1, fontSize: '12px' }}
                    />
                    <button
                      type="button"
                      onClick={() => removePriceRow(idx)}
                      style={{
                        background: 'rgba(239, 68, 68, 0.15)',
                        border: '1px solid rgba(239, 68, 68, 0.3)',
                        color: '#ef4444',
                        padding: '8px',
                        borderRadius: '6px',
                        cursor: 'pointer'
                      }}
                    >
                      <Trash2 size={14} />
                    </button>
                  </div>
                ))}
              </div>
            </div>

            <button
              type="submit"
              disabled={isSaving}
              className="btn-primary"
              style={{
                width: '100%',
                padding: '12px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px',
                fontWeight: 700
              }}
            >
              <Save size={16} /> {isSaving ? 'Menyimpan Pengaturan...' : 'Simpan Kustomisasi Tampilan'}
            </button>
          </form>
        </div>

      </div>

      {/* 1-Click Deploy Modal */}
      {showDeployModal && (
        <div className="modal-backdrop" style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0, 0, 0, 0.8)',
          backdropFilter: 'blur(8px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 1000,
          padding: '20px'
        }}>
          <div className="glass-panel" style={{
            maxWidth: '520px',
            width: '100%',
            padding: '28px',
            borderRadius: '16px',
            border: '1px solid rgba(0, 210, 211, 0.3)',
            boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.7)'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '16px' }}>
              <div style={{
                background: 'rgba(0, 210, 211, 0.15)',
                color: '#00d2d3',
                padding: '10px',
                borderRadius: '10px'
              }}>
                <Zap size={22} />
              </div>
              <div>
                <h3 style={{ margin: 0, fontSize: '18px', fontWeight: 800, color: '#f8fafc' }}>
                  Pasang Hotspot ke Router
                </h3>
                <p style={{ margin: 0, fontSize: '12px', color: '#94a3b8' }}>
                  Otomatisasi konfigurasi Hotspot, IP, DHCP Option 114, dan template login
                </p>
              </div>
            </div>

            <div style={{ background: 'rgba(255,255,255,0.03)', padding: '14px', borderRadius: '10px', marginBottom: '20px', fontSize: '12px', color: '#cbd5e1' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontWeight: 700, color: '#00d2d3', marginBottom: '4px' }}>
                <Info size={14} /> Otomatisasi ini akan melakukan:
              </div>
              <ul style={{ margin: 0, paddingLeft: '18px' }}>
                <li>Konfigurasi IP Gateway & Pool pada interface terpilih</li>
                <li>Konfigurasi DHCP Server & DHCP Option 114 (Auto-Popup di HP pelanggan)</li>
                <li>Aktivasi Server Hotspot & Profile RADIUS terintegrasi FreeRADIUS Cloud</li>
                <li>Pemasangan Walled Garden ke domain cloud <code>hi1271.my.id</code></li>
                <li>Pengunggahan file <code>login.html</code> & <code>alogin.html</code> ke folder <code>hotspot/</code> di MikroTik</li>
              </ul>
            </div>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '14px', marginBottom: '24px' }}>
              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#94a3b8', marginBottom: '4px' }}>
                  Interface Hotspot MikroTik:
                </label>
                <select
                  value={deployForm.interface}
                  onChange={(e) => setDeployForm({ ...deployForm, interface: e.target.value })}
                  className="input-field"
                  style={{ width: '100%' }}
                >
                  <option value="bridge1">bridge1 (Semua Port LAN ether2-ether5)</option>
                  {interfaces.map(iface => (
                    <option key={iface.name} value={iface.name}>{iface.name} ({iface.type})</option>
                  ))}
                </select>
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#94a3b8', marginBottom: '4px' }}>
                  IP Gateway & Subnet Hotspot:
                </label>
                <input 
                  type="text"
                  className="input-field"
                  value={deployForm.ip}
                  onChange={(e) => setDeployForm({ ...deployForm, ip: e.target.value })}
                  placeholder="10.0.0.1/22 atau 192.168.88.1/24"
                  style={{ width: '100%' }}
                />
              </div>

              <div>
                <label style={{ display: 'block', fontSize: '12px', fontWeight: 700, color: '#94a3b8', marginBottom: '4px' }}>
                  DNS Name Hotspot:
                </label>
                <input 
                  type="text"
                  className="input-field"
                  value={deployForm.dns_name}
                  onChange={(e) => setDeployForm({ ...deployForm, dns_name: e.target.value })}
                  placeholder="wifi.papua.net atau hotspot.yunus"
                  style={{ width: '100%' }}
                />
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
              <button
                type="button"
                onClick={() => setShowDeployModal(false)}
                className="btn-glass"
                disabled={isDeploying}
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleDeployRouter}
                className="btn-primary"
                disabled={isDeploying}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', background: 'linear-gradient(135deg, #00d2d3, #0984e3)' }}
              >
                <Zap size={16} className={isDeploying ? 'spin' : ''} /> {isDeploying ? 'Sedang Memasang ke MikroTik...' : 'Jalankan Pemasangan Sekarang'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
