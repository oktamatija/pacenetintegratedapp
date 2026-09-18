import React, { useState, useEffect } from 'react';
import { 
  PlusCircle, 
  Printer, 
  Download, 
  CheckCircle, 
  RefreshCw, 
  Ticket, 
  Sparkles, 
  Copy, 
  FileText, 
  ExternalLink, 
  ArrowRight,
  Receipt,
  Check,
  Layers,
  Database
} from 'lucide-react';
import { parseBilingualDuration } from '../utils/durationParser';
import { authFetch } from '../utils/api';

export default function GenerateVoucher({ onNavigate, setGeneratedForPrint, isReadOnly }) {
  const [profiles, setProfiles] = useState([]);
  const [routers, setRouters] = useState([]);
  const [targetRouter, setTargetRouter] = useState('all');
  const [loadingProfiles, setLoadingProfiles] = useState(true);

  // Form State
  const [qty, setQty] = useState(55);
  const [server, setServer] = useState('all');
  const [userMode, setUserMode] = useState('up');
  const [nameLength, setNameLength] = useState(4);
  const [prefix, setPrefix] = useState('');
  const [charType, setCharType] = useState('lower');
  const [profile, setProfile] = useState('');
  const [timelimit, setTimelimit] = useState('');
  const [datalimit, setDatalimit] = useState('');
  const [comment, setComment] = useState(`up-${new Date().toISOString().slice(2,10).replace(/-/g,'')}`);

  const [generating, setGenerating] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  // Fetch available profiles & routers
  useEffect(() => {
    authFetch('/api/vouchers.php?action=list&limit=1')
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          if (json.data.profiles) {
            setProfiles(json.data.profiles);
            if (json.data.profiles.length > 0 && !profile) {
              setProfile(json.data.profiles[0].name);
            }
          }
          if (json.data.routers) {
            setRouters(json.data.routers);
          }
        }
      })
      .catch(console.error)
      .finally(() => setLoadingProfiles(false));
  }, []);

  const handleGenerate = async (e, directPrint = false) => {
    if (e) e.preventDefault();
    if (isReadOnly) {
      setError('Akses Ditolak: Akun Demo berstatus Read-Only. Pembuatan voucher dinonaktifkan.');
      return;
    }

    const numQty = Number(qty);
    if (!numQty || numQty < 1 || numQty > 100000) {
      setError('Jumlah voucher harus antara 1 sampai 100.000 voucher.');
      return;
    }

    setGenerating(true);
    setError('');
    setResult(null);

    try {
      const res = await authFetch('/api/generate.php', {
        method: 'POST',
        body: JSON.stringify({
          target_router: targetRouter,
          qty: numQty,
          server,
          user_mode: userMode,
          name_length: Number(nameLength),
          prefix,
          char_type: charType,
          profile,
          timelimit,
          datalimit,
          comment
        })
      });
      const json = await res.json();
      if (json.success) {
        setResult(json.data);
        if (setGeneratedForPrint && json.data.vouchers) {
          setGeneratedForPrint(json.data.vouchers);
        }
        if (directPrint && onNavigate) {
          onNavigate('print');
        }
      } else {
        setError(json.message || 'Gagal membuat batch voucher');
      }
    } catch (err) {
      setError('Terjadi kesalahan jaringan atau waktu proses habis.');
    } finally {
      setGenerating(false);
    }
  };

  const handleCopyCodes = () => {
    if (!result || !result.vouchers) return;
    const text = result.vouchers.map(v => `${v.name} / ${v.password}`).join('\n');
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div>
      {/* Top Header with Workflow Tab Switcher */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: '14px',
        marginBottom: '20px'
      }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <Sparkles size={20} color="var(--accent-cyan)" />
            <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff' }}>
              Batch Voucher Generator &amp; Print Engine
            </h2>
            <span className="badge badge-cyan" style={{ fontSize: '11px', fontWeight: 700 }}>
              Kapasitas Hingga 100.000 Voucher
            </span>
          </div>
          <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)', marginTop: '2px' }}>
            Buat voucher massal ke Database Pacenet Cloud (Single Source of Truth) atau langsung cetak ke Lembar F4 / Thermal POS.
          </p>
        </div>

        {/* Action Tabs: Generator vs Print */}
        <div style={{
          display: 'flex',
          background: 'rgba(255,255,255,0.05)',
          padding: '4px',
          borderRadius: 'var(--radius-md)',
          border: '1px solid var(--border-subtle)',
          gap: '4px'
        }}>
          <button
            className="btn btn-sm btn-primary"
            style={{ fontWeight: 700 }}
          >
            <Sparkles size={14} />
            <span>1. Generator Voucher</span>
          </button>
          <button
            className="btn btn-sm btn-secondary"
            onClick={() => onNavigate('print')}
            style={{ color: 'var(--accent-cyan)' }}
            title="Buka modul pencetakan lembar F4 atau Thermal"
          >
            <Printer size={14} />
            <span>2. Modul Cetak (Print)</span>
          </button>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(320px, 1.1fr) minmax(340px, 1.3fr)', gap: '20px' }}>
        {/* =========================================================================
            LEFT COLUMN: GENERATOR CONFIGURATION FORM
            ========================================================================= */}
        <div className="glass-card" style={{ padding: '22px' }}>
          <div style={{ 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'space-between', 
            paddingBottom: '14px', 
            borderBottom: '1px solid var(--border-subtle)',
            marginBottom: '16px'
          }}>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Database size={16} color="var(--accent-cyan)" />
              Form Parameter Pembuatan Voucher
            </h3>
            <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Maks. 100.000 / Batch</span>
          </div>

          <form onSubmit={e => handleGenerate(e, false)}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
              {/* Jumlah Voucher (Qty) */}
              <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                  <label style={{ fontSize: '12px', fontWeight: 700, color: '#fff' }}>
                    Jumlah Voucher yang Dibuat (Qty):
                  </label>
                  <span style={{ fontSize: '11px', color: 'var(--accent-cyan)', fontWeight: 800 }}>
                    {Number(qty) % 55 === 0 && Number(qty) <= 5500 ? `${Number(qty) / 55} Lembar Kertas F4` : `${Number(qty).toLocaleString()} Voucher`}
                  </span>
                </div>
                <input
                  type="number"
                  min="1"
                  max="100000"
                  className="filter-select"
                  style={{ width: '100%', fontSize: '15px', fontWeight: 800, fontFamily: 'var(--font-mono)', color: 'var(--accent-cyan)' }}
                  value={qty}
                  onChange={e => setQty(e.target.value)}
                  required
                />

                {/* Preset Categories */}
                <div style={{ marginTop: '10px' }}>
                  <div style={{ fontSize: '10.5px', color: 'var(--text-muted)', fontWeight: 600, marginBottom: '5px' }}>
                    PILIHAN CEPAT (PRESET):
                  </div>

                  {/* F4 Sheets */}
                  <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', marginBottom: '6px' }}>
                    <span style={{ fontSize: '10px', color: '#94a3b8', display: 'flex', alignItems: 'center', minWidth: '45px' }}>F4:</span>
                    {[
                      { label: '55 (1 F4)', val: 55 },
                      { label: '110 (2 F4)', val: 110 },
                      { label: '275 (5 F4)', val: 275 },
                      { label: '550 (10 F4)', val: 550 }
                    ].map(item => (
                      <button
                        key={item.val}
                        type="button"
                        onClick={() => setQty(item.val)}
                        className={`btn btn-xs ${Number(qty) === item.val ? 'btn-primary' : 'btn-secondary'}`}
                        style={{ fontSize: '11px', padding: '3px 8px' }}
                      >
                        {item.label}
                      </button>
                    ))}
                  </div>

                  {/* Thermal POS */}
                  <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', marginBottom: '6px' }}>
                    <span style={{ fontSize: '10px', color: '#94a3b8', display: 'flex', alignItems: 'center', minWidth: '45px' }}>Thermal:</span>
                    {[
                      { label: '20 Struk', val: 20 },
                      { label: '50 Struk', val: 50 },
                      { label: '100 Struk', val: 100 }
                    ].map(item => (
                      <button
                        key={item.val}
                        type="button"
                        onClick={() => setQty(item.val)}
                        className={`btn btn-xs ${Number(qty) === item.val ? 'btn-primary' : 'btn-secondary'}`}
                        style={{ fontSize: '11px', padding: '3px 8px' }}
                      >
                        {item.label}
                      </button>
                    ))}
                  </div>

                  {/* High Volume Stock Presets */}
                  <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap' }}>
                    <span style={{ fontSize: '10px', color: 'var(--accent-amber)', display: 'flex', alignItems: 'center', minWidth: '45px', fontWeight: 700 }}>Massal:</span>
                    {[
                      { label: '1.000', val: 1000 },
                      { label: '5.000', val: 5000 },
                      { label: '10.000', val: 10000 },
                      { label: '50.000', val: 50000 },
                      { label: '100.000 (Maks)', val: 100000 }
                    ].map(item => (
                      <button
                        key={item.val}
                        type="button"
                        onClick={() => setQty(item.val)}
                        className={`btn btn-xs ${Number(qty) === item.val ? 'btn-primary' : 'btn-secondary'}`}
                        style={{ 
                          fontSize: '11px', 
                          padding: '3px 8px',
                          borderColor: Number(qty) === item.val ? 'var(--accent-amber)' : 'rgba(245, 158, 11, 0.3)',
                          color: Number(qty) === item.val ? '#000' : '#fbbf24',
                          background: Number(qty) === item.val ? '#fbbf24' : 'transparent'
                        }}
                      >
                        {item.label}
                      </button>
                    ))}
                  </div>
                </div>
              </div>

              {/* Mode Login Pelanggan */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Format Login Pelanggan
                </label>
                <select
                  className="filter-select"
                  style={{ width: '100%' }}
                  value={userMode}
                  onChange={e => setUserMode(e.target.value)}
                >
                  <option value="up">Username = Password (Satu Kode - Praktis)</option>
                  <option value="vc">Username &amp; Password Berbeda (Dua Kode)</option>
                </select>
              </div>

              {/* Profil Paket Hotspot */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Profil Paket Hotspot
                </label>
                <select
                  className="filter-select"
                  style={{ width: '100%', fontWeight: 700, color: 'var(--accent-cyan)' }}
                  value={profile}
                  onChange={e => setProfile(e.target.value)}
                  required
                >
                  {profiles.map(p => (
                    <option key={p.name} value={p.name}>
                      {p.name} {p.rate_limit !== '-' ? `(${p.rate_limit})` : ''}
                    </option>
                  ))}
                </select>
              </div>

              {/* Karakter & Panjang */}
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Panjang Kode (Karakter)
                  </label>
                  <input
                    type="number"
                    min="3"
                    max="16"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={nameLength}
                    onChange={e => setNameLength(e.target.value)}
                  />
                  <span style={{ fontSize: '10.5px', color: 'var(--text-muted)', display: 'block', marginTop: '3px' }}>
                    {Number(qty) > 5000 ? 'Otomatis dioptimalkan untuk batch besar' : 'Rekomendasi: 4-6 karakter'}
                  </span>
                </div>
                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Prefix (Awalan Opsional)
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: PN-"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={prefix}
                    onChange={e => setPrefix(e.target.value)}
                  />
                </div>
              </div>

              {/* Kombinasi Karakter */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Kombinasi Karakter Kode
                </label>
                <select
                  className="filter-select"
                  style={{ width: '100%' }}
                  value={charType}
                  onChange={e => setCharType(e.target.value)}
                >
                  <option value="lower">Huruf Kecil &amp; Angka (abcd234 - Mudah Dibaca)</option>
                  <option value="upper">Huruf Besar &amp; Angka (ABCD234)</option>
                  <option value="num">Hanya Angka Saja (123456 - PIN Numerik)</option>
                  <option value="mix">Campuran Huruf &amp; Angka (aB3dE7)</option>
                </select>
              </div>

              {/* Batas Waktu & Kuota */}
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Batas Waktu (Opsional)
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: 12 jam, 1d"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={timelimit}
                    onChange={e => setTimelimit(e.target.value)}
                  />
                  {timelimit && (() => {
                    const p = parseBilingualDuration(timelimit);
                    return p ? (
                      <div style={{ marginTop: '4px', fontSize: '11px', color: 'var(--accent-emerald)' }}>
                        ⏱️ {p.humanId} ({p.mikrotik})
                      </div>
                    ) : null;
                  })()}
                </div>

                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Data Limit (Opsional)
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: 1G, 500M"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={datalimit}
                    onChange={e => setDatalimit(e.target.value)}
                  />
                </div>
              </div>

              {/* Tag Batch / Komentar */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Tag Batch / Komentar
                </label>
                <input
                  type="text"
                  className="filter-select"
                  style={{ width: '100%' }}
                  value={comment}
                  onChange={e => setComment(e.target.value)}
                />
              </div>

              {error && (
                <div style={{ padding: '10px 14px', background: 'rgba(244, 63, 94, 0.15)', color: 'var(--accent-rose)', borderRadius: 'var(--radius-sm)', fontSize: '12px' }}>
                  {error}
                </div>
              )}

              {/* =========================================================================
                  ACTION BUTTONS SECTION (CLEARLY SEPARATED)
                  ========================================================================= */}
              <div style={{ 
                marginTop: '10px', 
                padding: '16px', 
                background: 'rgba(0,0,0,0.25)', 
                border: '1px solid var(--border-subtle)', 
                borderRadius: 'var(--radius-md)',
                display: 'flex',
                flexDirection: 'column',
                gap: '10px'
              }}>
                <div style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', color: 'var(--text-muted)', fontWeight: 700 }}>
                  PILIH AKSI PEMBUATAN VOUCHER:
                </div>

                {/* Button A: Generate Only */}
                <button
                  type="button"
                  onClick={e => handleGenerate(e, false)}
                  className="btn btn-primary"
                  disabled={generating || isReadOnly}
                  style={{
                    padding: '13px 18px',
                    fontSize: '13.5px',
                    fontWeight: 800,
                    width: '100%',
                    justifyContent: 'center',
                    background: 'linear-gradient(135deg, #0984e3 0%, #00cec9 100%)',
                    boxShadow: '0 4px 15px rgba(9, 132, 227, 0.3)'
                  }}
                >
                  {generating ? (
                    <>
                      <RefreshCw size={16} className="spin-anim" />
                      <span>Sedang Memproses {Number(qty).toLocaleString()} Voucher...</span>
                    </>
                  ) : (
                    <>
                      <Sparkles size={16} />
                      <span>⚡ 1. GENERATE &amp; SIMPAN KE DATABASE</span>
                    </>
                  )}
                </button>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', textAlign: 'center' }}>
                  Menyimpan {Number(qty).toLocaleString()} voucher ke Database Cloud tanpa membuka lembar cetak.
                </div>

                <div style={{ height: '1px', background: 'var(--border-subtle)', margin: '4px 0' }}></div>

                {/* Button B: Generate & Direct Print */}
                <button
                  type="button"
                  onClick={e => handleGenerate(e, true)}
                  className="btn btn-primary"
                  disabled={generating || isReadOnly}
                  style={{
                    padding: '13px 18px',
                    fontSize: '13.5px',
                    fontWeight: 800,
                    width: '100%',
                    justifyContent: 'center',
                    background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                    boxShadow: '0 4px 15px rgba(16, 185, 129, 0.3)'
                  }}
                >
                  {generating ? (
                    <>
                      <RefreshCw size={16} className="spin-anim" />
                      <span>Memproses &amp; Menyiapkan Lembar Cetak...</span>
                    </>
                  ) : (
                    <>
                      <Printer size={16} />
                      <span>🖨️ 2. GENERATE &amp; LANGSUNG CETAK (F4 / THERMAL)</span>
                    </>
                  )}
                </button>
                <div style={{ fontSize: '11px', color: 'var(--text-muted)', textAlign: 'center' }}>
                  Membuat voucher dan otomatis membuka modul pencetakan (55 slip/lembar F4 atau Thermal POS).
                </div>
              </div>
            </div>
          </form>
        </div>

        {/* =========================================================================
            RIGHT COLUMN: PUSAT PENCETAKAN & HASIL VOUCHER
            ========================================================================= */}
        <div className="glass-card" style={{ padding: '22px', display: 'flex', flexDirection: 'column' }}>
          <div style={{ 
            display: 'flex', 
            alignItems: 'center', 
            justifyContent: 'space-between', 
            paddingBottom: '14px', 
            borderBottom: '1px solid var(--border-subtle)',
            marginBottom: '16px',
            flexWrap: 'wrap',
            gap: '10px'
          }}>
            <div>
              <h3 style={{ fontSize: '15px', fontWeight: 800, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <Printer size={18} color="var(--accent-emerald)" />
                Pusat Cetak &amp; Hasil Batch Voucher
              </h3>
              <p style={{ fontSize: '11.5px', color: 'var(--text-muted)', marginTop: '2px' }}>
                Cetak ke kertas F4, cetak struk kasir Thermal POS, atau download file CSV.
              </p>
            </div>

            {/* Quick Print Navigation Button */}
            <button
              className="btn btn-secondary btn-sm"
              onClick={() => onNavigate('print')}
              style={{ borderColor: 'rgba(56, 189, 248, 0.4)', color: 'var(--accent-cyan)' }}
              title="Cetak voucher yang sudah ada dari database"
            >
              <Printer size={13} />
              <span>Buka Modul Cetak Bebas</span>
            </button>
          </div>

          {!result ? (
            /* Empty State Guide */
            <div style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              color: 'var(--text-muted)',
              padding: '40px 24px',
              textAlign: 'center',
              border: '2px dashed var(--border-subtle)',
              borderRadius: 'var(--radius-md)',
              background: 'rgba(255,255,255,0.01)'
            }}>
              <div style={{
                width: '60px',
                height: '60px',
                borderRadius: '50%',
                background: 'rgba(0, 210, 211, 0.1)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: 'var(--accent-cyan)',
                marginBottom: '14px'
              }}>
                <Ticket size={28} />
              </div>
              <h4 style={{ fontSize: '15px', fontWeight: 700, color: '#fff' }}>Belum Ada Batch Voucher yang Digenerate</h4>
              <p style={{ fontSize: '12.5px', maxWidth: '380px', marginTop: '6px', lineHeight: '1.5' }}>
                Pilih jumlah voucher di formulir kiri, lalu klik salah satu tombol:
              </p>
              <div style={{ margin: '14px 0', textAlign: 'left', fontSize: '12px', background: 'rgba(0,0,0,0.3)', padding: '12px 16px', borderRadius: '8px', border: '1px solid var(--border-subtle)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px', color: '#38bdf8' }}>
                  <Sparkles size={14} />
                  <span><strong>Tombol 1:</strong> Simpan voucher ke database tanpa mencetak</span>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#34d399' }}>
                  <Printer size={14} />
                  <span><strong>Tombol 2:</strong> Simpan voucher dan langsung buka halaman cetak</span>
                </div>
              </div>
              <p style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
                Atau ingin mencetak voucher yang sudah ada sebelumnya?
              </p>
              <button
                className="btn btn-secondary btn-sm"
                onClick={() => onNavigate('print')}
                style={{ marginTop: '10px' }}
              >
                <Printer size={14} />
                <span>Cetak Voucher dari Database</span>
              </button>
            </div>
          ) : (
            /* Results & Print Toolbar */
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
              {/* Success Notification Banner */}
              <div style={{
                padding: '12px 16px',
                background: 'rgba(16, 185, 129, 0.15)',
                border: '1px solid rgba(16, 185, 129, 0.3)',
                borderRadius: 'var(--radius-md)',
                marginBottom: '16px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                flexWrap: 'wrap',
                gap: '10px'
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                  <CheckCircle size={20} color="var(--accent-emerald)" />
                  <div>
                    <div style={{ fontSize: '13.5px', fontWeight: 800, color: '#fff' }}>
                      Berhasil Membuat {result.count.toLocaleString()} Voucher!
                    </div>
                    <div style={{ fontSize: '11.5px', color: 'var(--text-secondary)' }}>
                      Paket: <strong>{result.profile}</strong> • Batch: <strong>{result.batch_comment}</strong>
                    </div>
                  </div>
                </div>

                <div style={{ fontSize: '12px', fontWeight: 800, color: 'var(--accent-emerald)' }}>
                  100% Tersimpan di Cloud
                </div>
              </div>

              {/* =========================================================================
                  PRINT & EXPORT TOOLBAR (THE BIG PROMINENT BUTTONS)
                  ========================================================================= */}
              <div style={{
                background: 'rgba(0,0,0,0.3)',
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-md)',
                padding: '14px',
                marginBottom: '16px'
              }}>
                <div style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.6px', color: 'var(--accent-cyan)', fontWeight: 700, marginBottom: '10px' }}>
                  PILIH FORMAT CETAK &amp; EXPORT:
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', marginBottom: '10px' }}>
                  {/* Print F4 Button */}
                  <button
                    className="btn btn-primary"
                    onClick={() => onNavigate('print')}
                    style={{
                      background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                      boxShadow: '0 4px 14px rgba(16, 185, 129, 0.25)',
                      padding: '10px 14px',
                      fontSize: '13px',
                      fontWeight: 800,
                      justifyContent: 'center'
                    }}
                  >
                    <Printer size={16} />
                    <span>Cetak Lembar F4 ({result.count % 55 === 0 ? `${result.count / 55} Lbr` : `${result.count} Slip`})</span>
                  </button>

                  {/* Print Thermal Button */}
                  <button
                    className="btn btn-secondary"
                    onClick={() => onNavigate('print')}
                    style={{
                      borderColor: 'rgba(245, 158, 11, 0.4)',
                      color: '#fbbf24',
                      padding: '10px 14px',
                      fontSize: '13px',
                      fontWeight: 800,
                      justifyContent: 'center'
                    }}
                  >
                    <Receipt size={16} />
                    <span>Cetak Struk Thermal (POS)</span>
                  </button>
                </div>

                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                  {/* Download CSV Button */}
                  <a
                    href={result.csv_export_url || `/api/generate.php?action=export_csv&batch=${encodeURIComponent(result.batch_comment)}`}
                    download
                    className="btn btn-secondary btn-sm"
                    style={{ flex: 1, justifyContent: 'center', borderColor: 'rgba(56, 189, 248, 0.4)', color: '#38bdf8' }}
                  >
                    <Download size={14} />
                    <span>Download CSV / Excel ({result.count.toLocaleString()} Kode)</span>
                  </a>

                  {/* Copy All Codes Button */}
                  <button
                    className="btn btn-secondary btn-sm"
                    onClick={handleCopyCodes}
                    style={{ flex: 1, justifyContent: 'center' }}
                  >
                    {copied ? <Check size={14} color="var(--accent-emerald)" /> : <Copy size={14} />}
                    <span>{copied ? 'Berhasil Disalin!' : 'Salin Semua Kode'}</span>
                  </button>
                </div>
              </div>

              {/* Large Batch Notice */}
              {result.is_large_batch && (
                <div style={{
                  padding: '10px 14px',
                  background: 'rgba(56, 189, 248, 0.1)',
                  border: '1px solid rgba(56, 189, 248, 0.25)',
                  borderRadius: 'var(--radius-sm)',
                  fontSize: '11.5px',
                  color: 'var(--accent-cyan)',
                  marginBottom: '12px',
                  lineHeight: '1.4'
                }}>
                  ℹ️ <strong>Batch Massal ({result.count.toLocaleString()} voucher):</strong> Seluruh voucher telah tersimpan 100% di Database PostgreSQL Cloud. Di bawah ini menampilkan sampel 500 voucher pertama agar peramban tetap ringan. Silakan gunakan tombol <strong>Download CSV / Excel</strong> untuk arsip lengkap.
                </div>
              )}

              {/* Scrollable Preview Grid */}
              <div style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                marginBottom: '8px'
              }}>
                <span style={{ fontSize: '11.5px', fontWeight: 700, color: 'var(--text-muted)' }}>
                  DAFTAR KODE VOUCHER ({result.vouchers?.length || 0} DITAMPILKAN):
                </span>
                <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
                  Format: Username = Password
                </span>
              </div>

              <div style={{
                flex: 1,
                maxHeight: '360px',
                overflowY: 'auto',
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(135px, 1fr))',
                gap: '8px',
                paddingRight: '6px',
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-sm)',
                padding: '10px',
                background: 'rgba(0,0,0,0.2)'
              }}>
                {result.vouchers?.map((v, idx) => (
                  <div key={idx} style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: '6px',
                    padding: '8px',
                    textAlign: 'center'
                  }}>
                    <div style={{ fontSize: '9px', color: 'var(--text-muted)', fontWeight: 600 }}>KODE VOUCHER</div>
                    <div style={{
                      fontSize: '14px',
                      fontWeight: 800,
                      fontFamily: 'var(--font-mono)',
                      color: 'var(--accent-cyan)',
                      margin: '2px 0'
                    }}>
                      {v.name}
                    </div>
                    {v.name !== v.password && (
                      <div style={{ fontSize: '10px', fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)' }}>
                        Pass: {v.password}
                      </div>
                    )}
                    <div style={{ fontSize: '9.5px', color: 'var(--accent-emerald)', marginTop: '2px', fontWeight: 600 }}>
                      {v.profile}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
