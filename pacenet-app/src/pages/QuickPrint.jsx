import React, { useState, useEffect } from 'react';
import { 
  Printer, 
  ArrowLeft, 
  Wifi, 
  Download, 
  Layers, 
  RefreshCw, 
  Sparkles, 
  Filter, 
  QrCode, 
  Sliders, 
  Scissors,
  CheckCircle2
} from 'lucide-react';
import { QRCodeSVG } from 'qrcode.react';

export default function QuickPrint({ vouchersForPrint, onNavigate, initialProfile }) {
  // Paper Size: 'f4' (55 Vouchers / Sheet), 'grid' (A4 3-Cols), '58mm', '80mm'
  const [paperSize, setPaperSize] = useState('f4');
  const [vouchers, setVouchers] = useState([]);
  const [routers, setRouters] = useState([]);
  const [selectedRouter, setSelectedRouter] = useState('');
  const [profiles, setProfiles] = useState([]);
  const [selectedProfile, setSelectedProfile] = useState(initialProfile || '');
  const [voucherLimit, setVoucherLimit] = useState(55);
  const [loading, setLoading] = useState(false);

  // Print custom controls
  const [borderStyle, setBorderStyle] = useState('dashed'); // 'dashed', 'solid', 'dotted', 'none'
  const [showQr, setShowQr] = useState(false);
  const [printScale, setPrintScale] = useState(1.0);
  const [contactPhone, setContactPhone] = useState('+62 813-4401-0045');

  // If vouchers are passed from parent (e.g. from Batch Generator or User Profiles)
  useEffect(() => {
    if (vouchersForPrint && vouchersForPrint.length > 0) {
      setVouchers(vouchersForPrint);
      setVoucherLimit(vouchersForPrint.length);
    }
  }, [vouchersForPrint]);

  // Load available routers
  useEffect(() => {
    const loadRouters = async () => {
      try {
        const res = await fetch('/api/routers.php');
        const json = await res.json();
        if (json.success && json.data?.routers) {
          setRouters(json.data.routers);
          if (!selectedRouter && json.data.routers.length > 0) {
            setSelectedRouter(json.data.routers[0].session);
          }
        }
      } catch (e) {
        console.error('Failed to load routers', e);
      }
    };
    loadRouters();
  }, []);

  // Load profiles when router changes
  useEffect(() => {
    if (!selectedRouter) return;
    const loadProfiles = async () => {
      try {
        const res = await fetch(`/api/profiles.php?router=${encodeURIComponent(selectedRouter)}`);
        const json = await res.json();
        if (json.success && json.data?.profiles) {
          setProfiles(json.data.profiles);
          if (!selectedProfile && json.data.profiles.length > 0) {
            setSelectedProfile(json.data.profiles[0].name);
          }
        }
      } catch (e) {
        console.error('Failed to load profiles', e);
      }
    };
    loadProfiles();
  }, [selectedRouter]);

  // Fetch vouchers based on selected profile
  const fetchVouchersByProfile = async () => {
    if (!selectedRouter || !selectedProfile) return;
    setLoading(true);
    try {
      const res = await fetch(`/api/profiles.php?action=quick_vouchers&router=${encodeURIComponent(selectedRouter)}&profile=${encodeURIComponent(selectedProfile)}&limit=${voucherLimit}`);
      const json = await res.json();
      if (json.success && json.data?.vouchers) {
        setVouchers(json.data.vouchers);
      } else {
        alert(json.message || 'Tidak ada voucher aktif untuk profil ini.');
      }
    } catch (e) {
      alert('Gagal mengambil voucher untuk profil.');
    } finally {
      setLoading(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  // Fallback demo vouchers if none loaded yet
  const displayList = vouchers.length > 0 ? vouchers : Array.from({ length: 55 }, (_, i) => ({
    username: `pn-${(i + 1).toString().padStart(4, '0')}`,
    password: `pn-${(i + 1).toString().padStart(4, '0')}`,
    profile: '5K-1HARI',
    price: 5000,
    validity: '1d',
    hotspot_name: 'PACENET HAMADI',
    dns_name: 'hotspot.yunus',
    comment: 'batch-demo'
  }));

  // Chunk displayList into exact groups of 55 for F4 pages
  const chunkArray = (arr, size) => {
    const result = [];
    for (let i = 0; i < arr.length; i += size) {
      result.push(arr.slice(i, i + size));
    }
    return result;
  };

  const f4Pages = chunkArray(displayList, 55);
  const detectedBatch = displayList[0]?.comment || '';

  return (
    <div className="quick-print-page">
      {/* =========================================================================
          CONTROL HEADER & PRINT CONFIGURATION (SCREEN ONLY)
          ========================================================================= */}
      <div className="no-print glass-card" style={{ marginBottom: '20px', padding: '18px 22px' }}>
        {/* Top Row: Title, Navigation & Print Button */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '14px',
          marginBottom: '16px'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
            <button 
              className="btn btn-secondary btn-sm"
              onClick={() => onNavigate('user_profiles')}
              title="Kembali ke User Profiles"
            >
              <ArrowLeft size={14} />
              <span>User Profiles</span>
            </button>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <Printer size={20} color="var(--accent-cyan)" />
                <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff' }}>
                  Pencetakan Voucher: Kertas F4 (55 Slip/Lembar) &amp; Thermal
                </h2>
                <span className="badge badge-cyan" style={{ fontSize: '11px', fontWeight: 700 }}>
                  {paperSize === 'f4' ? `${displayList.length} Voucher (${f4Pages.length} Lembar F4)` : `${displayList.length} Voucher`}
                </span>
              </div>
              <p style={{ fontSize: '12.5px', color: 'var(--text-muted)', marginTop: '2px' }}>
                Format presisi F4 Folio 215mm x 330mm (5 Kolom x 11 Baris), Thermal POS 58mm/80mm, atau A4.
              </p>
            </div>
          </div>

          <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
            <button 
              className="btn btn-primary"
              onClick={handlePrint}
              style={{
                background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                boxShadow: '0 0 18px rgba(0, 210, 211, 0.4)',
                padding: '10px 20px',
                fontSize: '13.5px',
                fontWeight: 800
              }}
            >
              <Printer size={16} />
              <span>Cetak Sekarang (Print)</span>
            </button>
          </div>
        </div>

        {/* Second Row: Print Styling Controls (Paper Size, Borders, QR, Scale) */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          gap: '12px',
          flexWrap: 'wrap',
          background: 'rgba(0,0,0,0.3)',
          padding: '12px 16px',
          borderRadius: 'var(--radius-sm)',
          border: '1px solid var(--border-subtle)',
          marginBottom: '12px'
        }}>
          {/* Paper Size Select */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ fontSize: '12px', fontWeight: 700, color: 'var(--accent-cyan)' }}>Ukuran Kertas:</span>
            <select
              className="filter-select"
              value={paperSize}
              onChange={e => setPaperSize(e.target.value)}
              style={{ padding: '6px 12px', fontSize: '12.5px', fontWeight: 700, borderColor: 'var(--accent-cyan)' }}
            >
              <option value="f4">📄 Kertas F4 / Folio (55 Voucher / Lembar - 5x11)</option>
              <option value="grid">📑 Lembar A4 (Grid 3 Kolom - 220px)</option>
              <option value="58mm">🧾 Thermal POS 58mm (Kecil)</option>
              <option value="80mm">🧾 Thermal POS 80mm (Standar POS)</option>
            </select>
          </div>

          {/* Cut Border Select */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <Scissors size={13} color="var(--text-muted)" />
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Garis Potong:</span>
            <select
              className="filter-select"
              value={borderStyle}
              onChange={e => setBorderStyle(e.target.value)}
              style={{ padding: '5px 10px', fontSize: '12px' }}
            >
              <option value="dashed">Garis Putus (Dashed)</option>
              <option value="solid">Garis Lurus (Solid)</option>
              <option value="dotted">Titik-titik (Dotted)</option>
              <option value="none">Tanpa Garis (None)</option>
            </select>
          </div>

          {/* QR Code Toggle */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <QrCode size={13} color="var(--text-muted)" />
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>QR Code:</span>
            <select
              className="filter-select"
              value={showQr ? 'yes' : 'no'}
              onChange={e => setShowQr(e.target.value === 'yes')}
              style={{ padding: '5px 10px', fontSize: '12px' }}
            >
              <option value="no">Tanpa QR (Kode Besar)</option>
              <option value="yes">Pakai QR Code (Auto-Login)</option>
            </select>
          </div>

          {/* Scale Control */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <Sliders size={13} color="var(--text-muted)" />
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Skala:</span>
            <div style={{ display: 'flex', alignItems: 'center', background: 'rgba(0,0,0,0.4)', borderRadius: '4px', border: '1px solid var(--border-subtle)' }}>
              <button 
                type="button"
                onClick={() => setPrintScale(prev => Math.max(0.85, Number((prev - 0.02).toFixed(2))))}
                style={{ background: 'none', border: 'none', color: '#fff', padding: '3px 8px', cursor: 'pointer', fontWeight: 800 }}
              >-</button>
              <span style={{ fontSize: '12px', fontWeight: 700, minWidth: '42px', textAlign: 'center', color: 'var(--accent-cyan)' }}>
                {Math.round(printScale * 100)}%
              </span>
              <button 
                type="button"
                onClick={() => setPrintScale(prev => Math.min(1.15, Number((prev + 0.02).toFixed(2))))}
                style={{ background: 'none', border: 'none', color: '#fff', padding: '3px 8px', cursor: 'pointer', fontWeight: 800 }}
              >+</button>
            </div>
          </div>

          {/* Contact phone */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>CS / No. HP:</span>
            <input 
              type="text" 
              className="filter-select"
              value={contactPhone}
              onChange={e => setContactPhone(e.target.value)}
              style={{ padding: '4px 8px', fontSize: '11.5px', width: '135px' }}
            />
          </div>
        </div>

        {/* Third Row: Router & Profile Filter Bar */}
        <div style={{
          display: 'flex',
          alignItems: 'center',
          gap: '12px',
          flexWrap: 'wrap',
          background: 'rgba(0,0,0,0.18)',
          padding: '8px 14px',
          borderRadius: 'var(--radius-sm)'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <Filter size={13} color="var(--accent-cyan)" />
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Router:</span>
            <select
              className="filter-select"
              value={selectedRouter}
              onChange={e => setSelectedRouter(e.target.value)}
              style={{ padding: '4px 10px', fontSize: '12px' }}
            >
              {routers.map(r => (
                <option key={r.session} value={r.session}>
                  {r.name || r.session}
                </option>
              ))}
            </select>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Paket Profile:</span>
            <select
              className="filter-select"
              value={selectedProfile}
              onChange={e => setSelectedProfile(e.target.value)}
              style={{ padding: '4px 10px', fontSize: '12px', minWidth: '140px' }}
            >
              {profiles.map(p => (
                <option key={p.name} value={p.name}>
                  {p.name} (Rp {Number(p.sprice || p.price || 0).toLocaleString('id-ID')})
                </option>
              ))}
            </select>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>Jumlah Tarik:</span>
            <select
              className="filter-select"
              value={voucherLimit}
              onChange={e => setVoucherLimit(Number(e.target.value))}
              style={{ padding: '4px 10px', fontSize: '12px', fontWeight: 600 }}
            >
              <option value={55}>55 Slip (1 Lembar F4)</option>
              <option value={110}>110 Slip (2 Lembar F4)</option>
              <option value={165}>165 Slip (3 Lembar F4)</option>
              <option value={220}>220 Slip (4 Lembar F4)</option>
              <option value={275}>275 Slip (5 Lembar F4)</option>
              <option value={10}>10 Slip (Thermal)</option>
              <option value={25}>25 Slip (Thermal)</option>
              <option value={50}>50 Slip (Thermal)</option>
            </select>
          </div>

          <button 
            className="btn btn-secondary btn-sm"
            onClick={fetchVouchersByProfile}
            disabled={loading}
            style={{ padding: '5px 12px', fontSize: '12px', borderColor: 'var(--accent-cyan)', color: 'var(--accent-cyan)' }}
          >
            <RefreshCw size={13} className={loading ? 'spin' : ''} />
            <span>{loading ? 'Memuat Voucher...' : 'Tarik Voucher Baru'}</span>
          </button>
        </div>
      </div>

      {/* =========================================================================
          PRINT PREVIEW & SHEET RENDERING
          ========================================================================= */}
      {paperSize === 'f4' ? (
        /* =====================================================================
           MODE 1: EXACT F4 (FOLIO) 55 VOUCHERS PER SHEET (5x11 GRID)
           ===================================================================== */
        <div className="f4-preview-wrapper" style={{ transform: `scale(${printScale})`, transformOrigin: 'top center' }}>
          {f4Pages.map((pageVouchers, pageIndex) => (
            <div key={pageIndex} className="f4-sheet-container">
              <div className="no-print f4-sheet-badge">
                <span>📄 Lembar F4 ke-{pageIndex + 1} ({pageVouchers.length} / 55 Voucher)</span>
                <span>Ukuran Folio Indonesia: 215mm x 330mm (5 Kolom x 11 Baris)</span>
              </div>

              <div className={`f4-page border-${borderStyle}`}>
                {pageVouchers.map((v, idx) => {
                  const globalNum = pageIndex * 55 + idx + 1;
                  const isSingleCode = (v.username === v.password) || !v.password;
                  const dnsName = v.dns_name || 'hotspot.yunus';
                  const loginUrl = `http://${dnsName}/login?username=${encodeURIComponent(v.username)}&password=${encodeURIComponent(v.password || v.username)}`;

                  return (
                    <div key={idx} className="v-f4">
                      {/* Header */}
                      <div className="v-f4-header">
                        <div style={{ width: '100%' }}>
                          <div className="v-f4-title">
                            {v.hotspot_name || 'PACENET HOTSPOT'} <span className="v-f4-num">#{globalNum}</span>
                          </div>
                          <div className="v-f4-phone">{contactPhone}</div>
                        </div>
                      </div>

                      {/* Body */}
                      <div className="v-f4-body">
                        {showQr ? (
                          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '1mm', width: '100%' }}>
                            <div style={{ flex: 1, minWidth: 0, textAlign: 'left' }}>
                              <div className="v-f4-label">KODE VOUCHER</div>
                              <div className="v-f4-code v-f4-code-qr">{v.username}</div>
                              {!isSingleCode && (
                                <div style={{ fontSize: '6pt', fontWeight: 'bold', marginTop: '0.4mm' }}>
                                  Pass: <b>{v.password}</b>
                                </div>
                              )}
                            </div>
                            <div style={{ width: '18mm', height: '18mm', flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                              <QRCodeSVG 
                                value={loginUrl} 
                                size={58} 
                                level="M" 
                                marginSize={0}
                              />
                            </div>
                          </div>
                        ) : (
                          isSingleCode ? (
                            <div style={{ width: '100%', textAlign: 'center' }}>
                              <div className="v-f4-label">KODE VOUCHER</div>
                              <div className="v-f4-code">{v.username}</div>
                            </div>
                          ) : (
                            <div className="v-f4-up">
                              <div className="v-f4-up-box">
                                <div className="v-f4-label">Username</div>
                                <div className="v-f4-val">{v.username}</div>
                              </div>
                              <div className="v-f4-up-box">
                                <div className="v-f4-label">Password</div>
                                <div className="v-f4-val">{v.password}</div>
                              </div>
                            </div>
                          )
                        )}
                      </div>

                      {/* Footer */}
                      <div className="v-f4-footer">
                        <div className="v-f4-meta">
                          <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: '62%' }}>
                            {v.profile || 'Hotspot'} {v.validity || v.limit_uptime || ''}
                          </span>
                          <span className="v-f4-price">
                            Rp {Number(v.sprice || v.price || 5000).toLocaleString('id-ID')}
                          </span>
                        </div>
                        <div className="v-f4-dns">Login: {dnsName}</div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      ) : (
        /* =====================================================================
           MODE 2: THERMAL POS 58mm / 80mm / A4 GRID 3-COLS
           ===================================================================== */
        <div className={`print-container size-${paperSize}`} style={{ transform: `scale(${printScale})`, transformOrigin: 'top center' }}>
          {displayList.map((v, idx) => {
            const isSingleCode = (v.username === v.password) || !v.password;
            const dnsName = v.dns_name || 'hotspot.yunus';
            const loginUrl = `http://${dnsName}/login?username=${encodeURIComponent(v.username)}&password=${encodeURIComponent(v.password || v.username)}`;

            return (
              <div key={idx} className={`voucher-slip border-${borderStyle}`}>
                <div className="slip-header">
                  <div className="slip-brand">{v.hotspot_name || 'PACENET HOTSPOT'} #{idx + 1}</div>
                  <div className="slip-dns">http://{dnsName}</div>
                </div>

                <div className="slip-body">
                  {showQr && (
                    <div style={{ margin: '6px auto', display: 'flex', justifyContent: 'center' }}>
                      <QRCodeSVG value={loginUrl} size={76} level="M" />
                    </div>
                  )}

                  <div className="slip-label">KODE VOUCHER / USERNAME</div>
                  <div className="slip-code">{v.username}</div>
                  {!isSingleCode && (
                    <div className="slip-pass">Password: <strong>{v.password}</strong></div>
                  )}
                </div>

                <div className="slip-footer">
                  <div className="slip-info">
                    <span>Paket: <strong>{v.profile}</strong></span>
                    <span>Masa Aktif: <strong>{v.validity || v.limit_uptime || '1h'}</strong></span>
                  </div>
                  <div className="slip-price">
                    Rp {Number(v.sprice || v.price || 3000).toLocaleString('id-ID')}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* =========================================================================
          EXACT CSS STYLES FOR F4 (FOLIO) 55 VOUCHERS AND THERMAL PRINTING
          ========================================================================= */}
      <style>{`
        /* ========================================================
           SCREEN PREVIEW STYLES
           ======================================================== */
        .f4-preview-wrapper {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 25px;
          margin-bottom: 40px;
        }

        .f4-sheet-container {
          background: transparent;
        }

        .f4-sheet-badge {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 8px 14px;
          background: #1e293b;
          color: #94a3b8;
          font-size: 11.5px;
          font-weight: 700;
          border-radius: 6px 6px 0 0;
          border: 1px solid #334155;
          border-bottom: none;
          width: 215mm;
          margin: 0 auto;
          box-sizing: border-box;
        }

        /* EXACT F4 (FOLIO) GRID LAYOUT - 55 VOUCHERS (5 COL x 11 ROW) */
        .f4-page {
          width: 215mm;
          height: 330mm;
          max-height: 330mm;
          padding: 3.5mm 4mm;
          overflow: hidden;
          display: grid;
          grid-template-columns: repeat(5, 40.2mm);
          grid-template-rows: repeat(11, 28.6mm);
          grid-gap: 1.2mm 1.2mm;
          box-sizing: border-box;
          background: #fff;
          color: #000;
          box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
          margin: 0 auto;
        }

        /* INDIVIDUAL F4 VOUCHER CARD (40.2mm x 28.6mm) */
        .v-f4 {
          width: 100%;
          height: 100%;
          padding: 1.5mm 1.5mm 1mm 1.5mm;
          background: #fff;
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          overflow: hidden;
          position: relative;
          page-break-inside: avoid;
          break-inside: avoid;
          box-sizing: border-box;
          font-family: Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Border Options */
        .f4-page.border-dashed .v-f4, .voucher-slip.border-dashed { border: 1px dashed #333; }
        .f4-page.border-solid .v-f4, .voucher-slip.border-solid { border: 1px solid #222; }
        .f4-page.border-dotted .v-f4, .voucher-slip.border-dotted { border: 1px dotted #444; }
        .f4-page.border-none .v-f4, .voucher-slip.border-none { border: 1px solid transparent; }

        .v-f4-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          border-bottom: 0.6px solid #222;
          padding-bottom: 0.8mm;
          line-height: 1.1;
        }
        .v-f4-title {
          font-size: 7.2pt;
          font-weight: 800;
          color: #000;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          letter-spacing: -0.2px;
        }
        .v-f4-phone {
          font-size: 5.2pt;
          font-weight: 700;
          color: #111;
          white-space: nowrap;
          line-height: 1;
        }
        .v-f4-num {
          font-size: 5.5pt;
          color: #555;
          font-weight: normal;
        }

        .v-f4-body {
          text-align: center;
          padding: 0.4mm 0;
          display: flex;
          flex-direction: column;
          justify-content: center;
          align-items: center;
          flex: 1;
        }
        .v-f4-label {
          font-size: 5.8pt;
          font-weight: 800;
          text-transform: uppercase;
          color: #000;
          letter-spacing: 0.4px;
          margin-bottom: 0.3mm;
          line-height: 1;
        }
        .v-f4-code {
          display: block;
          width: 100%;
          border: 1.5px solid #000;
          background: #fff;
          font-size: 12.5pt;
          font-weight: 900;
          letter-spacing: 1.2px;
          font-family: Arial, Consolas, monospace;
          padding: 1mm 0;
          line-height: 1.1;
          border-radius: 2px;
          color: #000;
          box-sizing: border-box;
        }
        .v-f4-code-qr {
          font-size: 10pt;
          padding: 0.6mm 0;
          letter-spacing: 0.8px;
        }

        .v-f4-up {
          display: flex;
          justify-content: space-between;
          gap: 1mm;
          width: 100%;
        }
        .v-f4-up-box {
          flex: 1;
          border: 1.2px solid #000;
          font-family: Arial, sans-serif;
          padding: 0.6mm 0.4mm;
          border-radius: 2px;
        }
        .v-f4-val {
          font-size: 9pt;
          font-weight: 900;
          color: #000;
          letter-spacing: 0.4px;
        }

        .v-f4-footer {
          border-top: 0.6px solid #222;
          padding-top: 0.5mm;
          line-height: 1.1;
        }
        .v-f4-meta {
          display: flex;
          justify-content: space-between;
          align-items: center;
          font-size: 6.2pt;
          font-weight: 800;
        }
        .v-f4-price {
          color: #000;
        }
        .v-f4-dns {
          font-size: 5pt;
          color: #333;
          text-align: center;
          margin-top: 0.2mm;
          font-weight: 600;
        }

        /* ========================================================
           NON-F4 (THERMAL & A4) STYLES
           ======================================================== */
        .print-container {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
          justify-content: flex-start;
          background: rgba(0, 0, 0, 0.2);
          padding: 16px;
          border-radius: var(--radius-md);
        }

        .voucher-slip {
          background: #fff;
          color: #000;
          border-radius: 4px;
          padding: 10px 12px;
          box-sizing: border-box;
          font-family: 'JetBrains Mono', Arial, monospace;
          page-break-inside: avoid;
        }

        .size-58mm .voucher-slip { width: 54mm; min-height: 48mm; margin-bottom: 6px; }
        .size-80mm .voucher-slip { width: 76mm; min-height: 52mm; margin-bottom: 8px; }

        .size-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 12px;
        }
        .size-grid .voucher-slip { width: 100%; min-height: 50mm; }

        .slip-header { text-align: center; border-bottom: 1px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .slip-brand { font-size: 13px; font-weight: 800; }
        .slip-dns { font-size: 9px; color: #444; }
        .slip-body { text-align: center; margin: 8px 0; }
        .slip-label { font-size: 9px; color: #666; margin-bottom: 2px; }
        .slip-code { font-size: 16px; font-weight: 900; letter-spacing: 1px; padding: 2px 4px; background: #f0f0f0; border-radius: 3px; display: inline-block; }
        .slip-pass { font-size: 11px; margin-top: 3px; }
        .slip-footer { border-top: 1px dashed #666; padding-top: 6px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 10px; }
        .slip-info { display: flex; flex-direction: column; gap: 1px; }
        .slip-price { font-size: 13px; font-weight: 900; color: #000; }

        /* ========================================================
           PRINT MEDIA SPECIFIC RULES (PRINT DIALOG)
           ======================================================== */
        @media print {
          /* Exact page dimensions for F4 vs Auto */
          ${paperSize === 'f4' ? `
            @page {
              size: 215mm 330mm;
              margin: 0;
            }
          ` : `
            @page {
              size: auto;
              margin-left: 7mm;
              margin-right: 3mm;
              margin-top: 9mm;
              margin-bottom: 3mm;
            }
          `}

          html, body {
            background: #fff !important;
            color: #000 !important;
            padding: 0 !important;
            margin: 0 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }

          /* Hide everything except the printable container */
          body * {
            visibility: hidden;
          }

          .no-print, .main-navbar, .sidebar, .app-sidebar, header {
            display: none !important;
          }

          .quick-print-page, .quick-print-page * {
            visibility: visible;
          }

          .f4-preview-wrapper {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0 !important;
            gap: 0 !important;
            transform: none !important;
          }

          .f4-sheet-container {
            margin: 0 !important;
            padding: 0 !important;
            page-break-after: always;
            break-after: page;
          }

          .f4-page {
            margin: 0 !important;
            box-shadow: none !important;
            page-break-inside: avoid;
            page-break-after: always;
            break-after: page;
          }

          .print-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            background: none !important;
            padding: 0 !important;
            margin: 0 !important;
          }
        }
      `}</style>
    </div>
  );
}
