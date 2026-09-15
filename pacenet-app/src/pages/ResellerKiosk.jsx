import React, { useState, useEffect, useRef } from 'react';
import { 
  Camera, 
  Search, 
  CheckCircle2, 
  XCircle, 
  Clock, 
  Wifi, 
  Tag, 
  Store, 
  DollarSign, 
  Check, 
  RefreshCw, 
  AlertCircle, 
  X, 
  Upload, 
  Volume2,
  Calendar,
  Layers,
  HardDrive
} from 'lucide-react';

export default function ResellerKiosk({ currentUser, userProfile, isReadOnly }) {
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');

  // Marking as sold state
  const [markingSold, setMarkingSold] = useState(false);
  const [markSuccess, setMarkSuccess] = useState('');

  // Kiosk sales history
  const [mySales, setMySales] = useState([]);
  const [salesSummary, setSalesSummary] = useState({});
  const [loadingSales, setLoadingSales] = useState(false);

  // Camera Scanner State
  const [scannerOpen, setScannerOpen] = useState(false);
  const [cameraError, setCameraError] = useState('');
  const videoRef = useRef(null);
  const canvasRef = useRef(null);
  const streamRef = useRef(null);
  const scanIntervalRef = useRef(null);

  // Beep Audio Feedback
  const playBeep = () => {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.15);
    } catch (e) {
      // Audio not supported or allowed
    }
  };

  const fetchMySales = async () => {
    setLoadingSales(true);
    try {
      const res = await fetch('/api/reseller.php?action=my_sales');
      const json = await res.json();
      if (json.success) {
        setMySales(json.data?.sales || []);
        setSalesSummary(json.data?.summary || {});
      }
    } catch (e) {
      console.error('Failed to load sales history', e);
    } finally {
      setLoadingSales(false);
    }
  };

  useEffect(() => {
    fetchMySales();
  }, []);

  const handleCheckCode = async (searchCode) => {
    const targetCode = (searchCode || code).trim();
    if (!targetCode) return;

    setLoading(true);
    setError('');
    setMarkSuccess('');
    setResult(null);

    try {
      const res = await fetch(`/api/reseller.php?action=check&code=${encodeURIComponent(targetCode)}`);
      const json = await res.json();
      if (json.success && json.data) {
        setResult(json.data);
      } else {
        setError(json.message || 'Voucher tidak ditemukan.');
      }
    } catch (e) {
      setError('Gagal memeriksa kode voucher.');
    } finally {
      setLoading(false);
    }
  };

  const handleMarkSold = async () => {
    if (!result || isReadOnly) return;
    setMarkingSold(true);
    setMarkSuccess('');

    try {
      const res = await fetch('/api/reseller.php?action=mark_sold', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          code: result.code,
          router: result.router_session,
          kiosk_name: userProfile?.kiosk_name || 'Kios Reseller',
          price: result.price || 0
        })
      });
      const json = await res.json();
      if (json.success) {
        setMarkSuccess('Voucher berhasil ditandai sebagai TERJUAL.');
        handleCheckCode(result.code);
        fetchMySales();
      } else {
        alert(json.message || 'Gagal menandai voucher.');
      }
    } catch (e) {
      alert('Gagal menghubungi server.');
    } finally {
      setMarkingSold(false);
    }
  };

  // CAMERA SCANNER LOGIC
  const startCamera = async () => {
    setScannerOpen(true);
    setCameraError('');

    try {
      const constraints = {
        video: {
          facingMode: { ideal: 'environment' },
          width: { ideal: 1280 },
          height: { ideal: 720 }
        }
      };
      const stream = await navigator.mediaDevices.getUserMedia(constraints);
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        videoRef.current.play();
      }

      // Start Barcode Detection Loop
      if ('BarcodeDetector' in window) {
        const detector = new window.BarcodeDetector({
          formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'upc_a']
        });

        scanIntervalRef.current = setInterval(async () => {
          if (videoRef.current && videoRef.current.readyState === 4) {
            try {
              const barcodes = await detector.detect(videoRef.current);
              if (barcodes.length > 0) {
                const detectedVal = barcodes[0].rawValue;
                handleCodeDetected(detectedVal);
              }
            } catch (err) {
              // ignore frame read error
            }
          }
        }, 200);
      }
    } catch (err) {
      console.error('Camera access error', err);
      setCameraError('Tidak dapat mengakses kamera ponsel/laptop. Pastikan izin kamera telah diberikan atau gunakan input manual.');
    }
  };

  const stopCamera = () => {
    if (scanIntervalRef.current) {
      clearInterval(scanIntervalRef.current);
      scanIntervalRef.current = null;
    }
    if (streamRef.current) {
      streamRef.current.getTracks().forEach(track => track.stop());
      streamRef.current = null;
    }
    setScannerOpen(false);
  };

  const handleCodeDetected = (rawCode) => {
    let clean = rawCode.trim();
    // If URL like http://.../login?username=XXXX, extract username
    if (clean.includes('username=')) {
      const m = clean.match(/[?&]username=([^&]+)/i);
      if (m) clean = decodeURIComponent(m[1]);
    }

    playBeep();
    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);

    stopCamera();
    setCode(clean);
    handleCheckCode(clean);
  };

  // Image Upload Fallback Scanner
  const handleImageUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if ('BarcodeDetector' in window) {
      try {
        const detector = new window.BarcodeDetector({
          formats: ['qr_code', 'code_128', 'code_39', 'ean_13']
        });
        const img = new Image();
        img.src = URL.createObjectURL(file);
        img.onload = async () => {
          const barcodes = await detector.detect(img);
          if (barcodes.length > 0) {
            handleCodeDetected(barcodes[0].rawValue);
          } else {
            alert('Tidak dapat mendeteksi barcode/QR code pada gambar ini. Silakan ketik kode voucher secara manual.');
          }
        };
      } catch (err) {
        alert('Gagal memproses gambar barcode.');
      }
    } else {
      alert('Perangkat Anda tidak mendukung pemindaian langsung dari foto. Silakan ketik kode voucher secara manual.');
    }
  };

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
            <Store size={20} color="var(--accent-amber)" />
            Portal Reseller Kios Voucher
          </h2>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>
            Pemeriksaan validitas voucher, pemindaian kamera cepat, penandaan penjualan kios, dan pemantauan masa aktif pelanggan.
          </p>
        </div>

        {/* Kiosk Identity Badge */}
        <div style={{
          background: 'rgba(245, 158, 11, 0.12)',
          border: '1px solid rgba(245, 158, 11, 0.3)',
          borderRadius: 'var(--radius-md)',
          padding: '6px 14px',
          display: 'flex',
          alignItems: 'center',
          gap: '8px'
        }}>
          <Store size={16} color="var(--accent-amber)" />
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Kios Aktif:</div>
            <div style={{ fontSize: '13px', fontWeight: 700, color: 'var(--accent-amber)' }}>
              {userProfile?.kiosk_name || 'Kios Reseller'} ({currentUser})
            </div>
          </div>
        </div>
      </div>

      {/* Overview Metric Cards for Kiosk */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
        gap: '14px',
        marginBottom: '20px'
      }}>
        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(16, 185, 129, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-emerald)' }}>
            <CheckCircle2 size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Terjual Hari Ini
            </div>
            <div style={{ fontSize: '20px', fontWeight: 800, color: 'var(--accent-emerald)' }}>
              {salesSummary.today_count || 0} <span style={{ fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)' }}>voucher</span>
            </div>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(0, 210, 211, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-cyan)' }}>
            <DollarSign size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Omzet Kios Hari Ini
            </div>
            <div style={{ fontSize: '20px', fontWeight: 800, color: 'var(--accent-cyan)' }}>
              {salesSummary.today_revenue_formatted || 'Rp 0'}
            </div>
          </div>
        </div>

        <div className="glass-card" style={{ padding: '16px', display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div style={{ width: '42px', height: '42px', borderRadius: '10px', background: 'rgba(168, 85, 247, 0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--accent-purple)' }}>
            <Tag size={22} />
          </div>
          <div>
            <div style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', fontWeight: 700 }}>
              Total Akumulasi Terjual
            </div>
            <div style={{ fontSize: '20px', fontWeight: 800, color: '#fff' }}>
              {salesSummary.total_count || 0} <span style={{ fontSize: '12px', fontWeight: 500, color: 'var(--text-muted)' }}>({salesSummary.total_revenue_formatted || 'Rp 0'})</span>
            </div>
          </div>
        </div>
      </div>

      {/* Main Voucher Scanner & Input Section */}
      <div className="glass-card" style={{ marginBottom: '24px', padding: '22px 24px' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '10px', marginBottom: '14px' }}>
          <div>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Search size={16} color="var(--accent-cyan)" />
              Periksa Status & Keaslian Voucher
            </h3>
            <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
              Pindai QR code/barcode voucher menggunakan kamera atau ketik kode voucher di bawah ini.
            </p>
          </div>

          <div style={{ display: 'flex', gap: '8px' }}>
            {/* Camera Scan Button */}
            <button
              className="btn btn-primary btn-sm"
              onClick={startCamera}
              style={{
                background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                boxShadow: '0 0 15px rgba(0, 210, 211, 0.3)',
                padding: '8px 14px',
                fontSize: '13px'
              }}
            >
              <Camera size={15} />
              <span>Buka Scanner Kamera Ponsel</span>
            </button>

            {/* Photo Upload Scanner */}
            <label 
              className="btn btn-secondary btn-sm"
              style={{ cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '6px' }}
              title="Pindai gambar barcode dari galeri/file"
            >
              <Upload size={14} />
              <span>Foto Barcode</span>
              <input 
                type="file" 
                accept="image/*" 
                style={{ display: 'none' }}
                onChange={handleImageUpload}
              />
            </label>
          </div>
        </div>

        {/* Input Form */}
        <form onSubmit={(e) => { e.preventDefault(); handleCheckCode(); }} style={{ display: 'flex', gap: '10px', maxWidth: '650px' }}>
          <div style={{
            flex: 1,
            display: 'flex',
            alignItems: 'center',
            background: 'var(--bg-input)',
            border: '1px solid var(--border-subtle)',
            borderRadius: 'var(--radius-md)',
            padding: '8px 14px',
            gap: '10px'
          }}>
            <Tag size={16} color="var(--text-muted)" />
            <input 
              type="text"
              placeholder="Ketik kode voucher (contoh: 751EAC, KV4)..."
              value={code}
              onChange={e => setCode(e.target.value.toUpperCase())}
              style={{
                background: 'none',
                border: 'none',
                outline: 'none',
                width: '100%',
                color: '#fff',
                fontFamily: 'var(--font-mono)',
                fontSize: '15px',
                fontWeight: 700,
                letterSpacing: '1px'
              }}
              autoFocus
            />
          </div>
          <button 
            type="submit" 
            className="btn btn-primary"
            disabled={loading || !code.trim()}
            style={{ padding: '0 20px', fontWeight: 700 }}
          >
            {loading ? <RefreshCw size={15} className="spin-anim" /> : 'Periksa Voucher'}
          </button>
        </form>

        {error && (
          <div style={{ marginTop: '14px', padding: '10px 14px', background: 'rgba(244, 63, 94, 0.15)', border: '1px solid rgba(244, 63, 94, 0.3)', borderRadius: '6px', color: 'var(--accent-rose)', fontSize: '13px', display: 'flex', alignItems: 'center', gap: '8px' }}>
            <AlertCircle size={16} />
            <span>{error}</span>
          </div>
        )}
      </div>

      {/* INSPECTION RESULT CARD */}
      {result && (
        <div className="glass-card" style={{ 
          marginBottom: '24px', 
          padding: '24px',
          borderColor: result.status === 'unused' ? 'var(--accent-emerald)' : (result.status === 'active' ? 'var(--accent-cyan)' : 'var(--border-subtle)'),
          boxShadow: result.status === 'unused' ? '0 0 25px rgba(16, 185, 129, 0.15)' : (result.status === 'active' ? '0 0 25px rgba(0, 210, 211, 0.15)' : 'none')
        }}>
          {/* Status Header Banner */}
          <div style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            flexWrap: 'wrap',
            gap: '12px',
            paddingBottom: '18px',
            borderBottom: '1px solid var(--border-subtle)',
            marginBottom: '18px'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
              <div style={{
                width: '48px',
                height: '48px',
                borderRadius: '12px',
                background: result.status === 'unused' ? 'rgba(16, 185, 129, 0.2)' : (result.status === 'active' ? 'rgba(0, 210, 211, 0.2)' : (result.status === 'expired' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(148, 163, 184, 0.2)')),
                color: result.status === 'unused' ? 'var(--accent-emerald)' : (result.status === 'active' ? 'var(--accent-cyan)' : (result.status === 'expired' ? 'var(--accent-rose)' : '#94a3b8')),
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
              }}>
                {result.status === 'unused' && <CheckCircle2 size={26} />}
                {result.status === 'active' && <Wifi size={26} />}
                {result.status === 'expired' && <XCircle size={26} />}
                {result.status === 'not_found' && <XCircle size={26} />}
              </div>
              <div>
                <div style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.8px', color: 'var(--text-muted)', fontWeight: 700 }}>
                  Hasil Pemeriksaan Kode Voucher
                </div>
                <div style={{ fontSize: '20px', fontWeight: 800, color: '#fff', fontFamily: 'var(--font-mono)' }}>
                  {result.code}
                </div>
              </div>
            </div>

            {/* Status Badge */}
            <div>
              <span style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
                fontSize: '13px',
                fontWeight: 800,
                padding: '6px 14px',
                borderRadius: '20px',
                background: result.status === 'unused' ? 'rgba(16, 185, 129, 0.2)' : (result.status === 'active' ? 'rgba(0, 210, 211, 0.2)' : (result.status === 'expired' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(148, 163, 184, 0.2)')),
                color: result.status === 'unused' ? 'var(--accent-emerald)' : (result.status === 'active' ? 'var(--accent-cyan)' : (result.status === 'expired' ? 'var(--accent-rose)' : '#94a3b8')),
                border: `1px solid ${result.status === 'unused' ? 'var(--accent-emerald)' : (result.status === 'active' ? 'var(--accent-cyan)' : (result.status === 'expired' ? 'var(--accent-rose)' : '#94a3b8'))}44`
              }}>
                {result.status === 'active' && <span className="pulse-dot-green"></span>}
                {result.status === 'expired' && <XCircle size={14} color="var(--accent-rose)" />}
                {result.status_label}
              </span>
            </div>
          </div>

          {markSuccess && (
            <div style={{ padding: '10px 14px', background: 'rgba(16, 185, 129, 0.15)', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: '6px', color: 'var(--accent-emerald)', fontSize: '13px', marginBottom: '18px', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <CheckCircle2 size={16} />
              <span>{markSuccess}</span>
            </div>
          )}

          {/* Details Grid */}
          {result.status !== 'not_found' ? (
            <div style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
              gap: '16px',
              marginBottom: '20px'
            }}>
              {/* Paket & Harga */}
              <div style={{ background: 'var(--bg-surface)', padding: '14px', borderRadius: '8px', border: '1px solid var(--border-subtle)' }}>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase' }}>Paket & Harga Jual</div>
                <div style={{ fontSize: '16px', fontWeight: 700, color: 'var(--accent-cyan)', marginTop: '4px' }}>
                  {result.profile}
                </div>
                <div style={{ fontSize: '14px', fontWeight: 800, color: 'var(--accent-emerald)', fontFamily: 'var(--font-mono)', marginTop: '2px' }}>
                  {result.price_formatted}
                </div>
              </div>

              {/* Masa Aktif & Sisa Waktu */}
              <div style={{ background: 'var(--bg-surface)', padding: '14px', borderRadius: '8px', border: '1px solid var(--border-subtle)' }}>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase' }}>Durasi Masa Aktif</div>
                <div style={{ fontSize: '15px', fontWeight: 700, color: '#fff', marginTop: '4px' }}>
                  {result.validity}
                </div>
                <div style={{ fontSize: '12px', color: 'var(--text-secondary)', marginTop: '2px' }}>
                  Sisa: <strong style={{ color: result.status === 'active' ? 'var(--accent-cyan)' : 'var(--text-primary)' }}>{result.session_left}</strong> (Uptime: {result.uptime})
                </div>
              </div>

              {/* Kapan Mulai Digunakan */}
              <div style={{ background: 'var(--bg-surface)', padding: '14px', borderRadius: '8px', border: '1px solid var(--border-subtle)' }}>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase' }}>Waktu Mulai Digunakan</div>
                <div style={{ fontSize: '13.5px', fontWeight: 700, color: result.status === 'unused' ? 'var(--accent-amber)' : 'var(--text-primary)', marginTop: '4px' }}>
                  {result.first_login_at}
                </div>
                <div style={{ fontSize: '11.5px', color: 'var(--text-muted)', marginTop: '2px' }}>
                  {result.status === 'unused' ? 'Pelanggan belum melakukan login' : (result.ip !== '-' ? `IP: ${result.ip} • MAC: ${result.mac}` : 'Sesi tersimpan')}
                </div>
              </div>

              {/* Lokasi Router */}
              <div style={{ background: 'var(--bg-surface)', padding: '14px', borderRadius: '8px', border: '1px solid var(--border-subtle)' }}>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600, textTransform: 'uppercase' }}>Node Pemancar / Router</div>
                <div style={{ fontSize: '14px', fontWeight: 700, color: '#fff', marginTop: '4px' }}>
                  ⚡ {result.router_name || result.router_session}
                </div>
                <div style={{ fontSize: '11.5px', color: 'var(--text-muted)', marginTop: '2px' }}>
                  Terhubung via hotspot server
                </div>
              </div>
            </div>
          ) : (
            <div style={{ padding: '16px', background: 'rgba(244, 63, 94, 0.1)', borderRadius: '8px', color: 'var(--accent-rose)', fontSize: '13px', marginBottom: '18px' }}>
              {result.message}
            </div>
          )}

          {/* Action: Mark as Sold Section */}
          {result.status !== 'not_found' && (
            <div style={{
              background: 'rgba(255, 255, 255, 0.02)',
              border: '1px solid var(--border-subtle)',
              borderRadius: '8px',
              padding: '16px 18px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
              flexWrap: 'wrap',
              gap: '12px'
            }}>
              <div>
                <div style={{ fontSize: '12.5px', fontWeight: 700, color: '#fff' }}>
                  Status Penjualan Kios:
                </div>
                {result.sold_info ? (
                  <div style={{ fontSize: '12px', color: 'var(--accent-emerald)', marginTop: '2px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <CheckCircle2 size={14} />
                    <span>
                      Telah ditandai terjual pada: <strong>{result.sold_info.sold_at}</strong> oleh <strong>{result.sold_info.kiosk_name || result.sold_info.kiosk_user}</strong>
                    </span>
                  </div>
                ) : result.status === 'expired' ? (
                  <div style={{ fontSize: '12px', color: 'var(--accent-rose)', marginTop: '2px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                    <XCircle size={14} />
                    <span>Masa berlaku voucher ini telah habis. Voucher tidak dapat digunakan atau dijual lagi.</span>
                  </div>
                ) : (
                  <div style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
                    Voucher ini belum ditandai terjual oleh kios. Klik tombol di samping saat pelanggan membeli.
                  </div>
                )}
              </div>

              {!isReadOnly && result.status !== 'expired' && (
                <button
                  className={`btn ${result.sold_info ? 'btn-secondary' : 'btn-primary'} btn-sm`}
                  onClick={handleMarkSold}
                  disabled={markingSold}
                  style={{ fontWeight: 700, minWidth: '160px' }}
                >
                  {markingSold ? (
                    <>
                      <RefreshCw size={14} className="spin-anim" />
                      <span>Menyimpan...</span>
                    </>
                  ) : result.sold_info ? (
                    <>
                      <Check size={14} color="var(--accent-emerald)" />
                      <span>Perbarui Tanda Terjual</span>
                    </>
                  ) : (
                    <>
                      <CheckCircle2 size={14} />
                      <span>Tandai Sebagai Terjual</span>
                    </>
                  )}
                </button>
              )}
            </div>
          )}
        </div>
      )}

      {/* RECENT KIOSK SALES TABLE */}
      <div className="glass-card" style={{ padding: 0, overflow: 'hidden' }}>
        <div style={{
          padding: '16px 20px',
          borderBottom: '1px solid var(--border-subtle)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '10px'
        }}>
          <div>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff' }}>
              Riwayat Voucher Terjual di Kios Ini
            </h3>
            <div style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
              Daftar voucher yang telah Anda tandai sebagai terjual
            </div>
          </div>
          <button
            className="btn btn-secondary btn-sm"
            onClick={fetchMySales}
            disabled={loadingSales}
          >
            <RefreshCw size={13} className={loadingSales ? 'spin-anim' : ''} />
            <span>Segarkan Riwayat</span>
          </button>
        </div>

        <div className="table-container" style={{ border: 'none' }}>
          <table className="data-table">
            <thead>
              <tr>
                <th>Kode Voucher</th>
                <th>Waktu Penjualan</th>
                <th>Router Node</th>
                <th>Nama Kios / Petugas</th>
                <th style={{ textAlign: 'right' }}>Harga</th>
                <th style={{ textAlign: 'center' }}>Aksi</th>
              </tr>
            </thead>
            <tbody>
              {loadingSales && mySales.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '30px' }}>
                    <RefreshCw size={20} className="spin-anim" style={{ margin: '0 auto 8px', color: 'var(--accent-cyan)' }} />
                    <p style={{ color: 'var(--text-muted)', fontSize: '12.5px' }}>Memuat riwayat penjualan...</p>
                  </td>
                </tr>
              ) : mySales.length === 0 ? (
                <tr>
                  <td colSpan={6} style={{ textAlign: 'center', padding: '30px', color: 'var(--text-muted)', fontSize: '13px' }}>
                    Belum ada voucher yang ditandai terjual hari ini. Pindai dan tandai voucher saat pelanggan membeli!
                  </td>
                </tr>
              ) : (
                mySales.map((s, idx) => (
                  <tr key={idx}>
                    <td>
                      <span style={{ fontFamily: 'var(--font-mono)', fontWeight: 700, color: '#fff' }}>
                        {s.code}
                      </span>
                    </td>
                    <td>
                      <span style={{ color: 'var(--text-primary)', fontSize: '12.5px' }}>
                        {s.sold_at}
                      </span>
                    </td>
                    <td>
                      <span className="tag tag-cyan" style={{ fontSize: '11px' }}>
                        {s.router || '-'}
                      </span>
                    </td>
                    <td>
                      <span style={{ color: 'var(--accent-amber)', fontSize: '12.5px', fontWeight: 600 }}>
                        {s.kiosk_name || s.kiosk_user}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right', fontFamily: 'var(--font-mono)', fontWeight: 700, color: 'var(--accent-emerald)' }}>
                      {s.price_formatted || `Rp ${(s.price || 0).toLocaleString('id-ID')}`}
                    </td>
                    <td style={{ textAlign: 'center' }}>
                      <button
                        className="btn btn-secondary btn-sm"
                        style={{ padding: '3px 8px', fontSize: '11.5px' }}
                        onClick={() => {
                          setCode(s.code);
                          handleCheckCode(s.code);
                        }}
                      >
                        Periksa Lagi
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* CAMERA SCANNER MODAL */}
      {scannerOpen && (
        <div style={{
          position: 'fixed',
          inset: 0,
          background: 'rgba(0, 0, 0, 0.85)',
          backdropFilter: 'blur(8px)',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          padding: '20px',
          zIndex: 80
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '480px',
            padding: '20px',
            position: 'relative',
            textAlign: 'center'
          }}>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: 'var(--accent-cyan)', fontWeight: 700, fontSize: '14.5px' }}>
                <Camera size={18} />
                <span>Scanner Kamera Voucher</span>
              </div>
              <button
                className="btn btn-icon btn-sm"
                onClick={stopCamera}
                style={{ background: 'transparent', border: 'none', color: '#fff' }}
              >
                <X size={20} />
              </button>
            </div>

            {cameraError ? (
              <div style={{ padding: '20px', color: 'var(--accent-rose)', fontSize: '13px', background: 'rgba(244, 63, 94, 0.1)', borderRadius: '8px', lineHeight: '1.5' }}>
                {cameraError}
              </div>
            ) : (
              <div style={{
                position: 'relative',
                width: '100%',
                height: '320px',
                borderRadius: '12px',
                overflow: 'hidden',
                background: '#000',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
              }}>
                <video
                  ref={videoRef}
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                  playsInline
                  muted
                />

                {/* Viewfinder Overlay */}
                <div style={{
                  position: 'absolute',
                  width: '220px',
                  height: '220px',
                  border: '2px solid var(--accent-cyan)',
                  borderRadius: '16px',
                  boxShadow: '0 0 0 4000px rgba(0, 0, 0, 0.45)',
                  pointerEvents: 'none'
                }}>
                  {/* Corner accents */}
                  <div style={{ position: 'absolute', top: '-2px', left: '-2px', width: '20px', height: '20px', borderTop: '4px solid #fff', borderLeft: '4px solid #fff', borderRadius: '4px 0 0 0' }} />
                  <div style={{ position: 'absolute', top: '-2px', right: '-2px', width: '20px', height: '20px', borderTop: '4px solid #fff', borderRight: '4px solid #fff', borderRadius: '0 4px 0 0' }} />
                  <div style={{ position: 'absolute', bottom: '-2px', left: '-2px', width: '20px', height: '20px', borderBottom: '4px solid #fff', borderLeft: '4px solid #fff', borderRadius: '0 0 0 4px' }} />
                  <div style={{ position: 'absolute', bottom: '-2px', right: '-2px', width: '20px', height: '20px', borderBottom: '4px solid #fff', borderRight: '4px solid #fff', borderRadius: '0 0 4px 0' }} />
                  
                  {/* Animated laser scan line */}
                  <div style={{
                    position: 'absolute',
                    left: 0,
                    right: 0,
                    height: '2px',
                    background: 'linear-gradient(90deg, transparent, #00d2d3, #fff, #00d2d3, transparent)',
                    boxShadow: '0 0 10px #00d2d3',
                    animation: 'scan-laser 2s linear infinite'
                  }} />
                </div>
              </div>
            )}

            <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '14px' }}>
              Arahkan kamera ponsel tepat ke kode QR atau Barcode pada tiket voucher. Sistem akan memverifikasi secara otomatis.
            </p>

            <button
              className="btn btn-secondary btn-sm"
              onClick={stopCamera}
              style={{ marginTop: '12px', width: '100%' }}
            >
              Tutup Scanner
            </button>
          </div>
        </div>
      )}

      {/* Laser scan animation CSS */}
      <style>{`
        @keyframes scan-laser {
          0% { top: 10px; }
          50% { top: 200px; }
          100% { top: 10px; }
        }
      `}</style>
    </div>
  );
}
