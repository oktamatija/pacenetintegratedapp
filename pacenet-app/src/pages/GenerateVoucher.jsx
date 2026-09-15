import React, { useState, useEffect } from 'react';
import { PlusCircle, Printer, Download, CheckCircle, RefreshCw, Ticket, Sparkles } from 'lucide-react';
import { parseBilingualDuration } from '../utils/durationParser';

export default function GenerateVoucher({ onNavigate, setGeneratedForPrint, isReadOnly }) {
  const [profiles, setProfiles] = useState([]);
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


  // Fetch available profiles
  useEffect(() => {
    fetch('/api/vouchers.php?action=list&limit=1')
      .then(res => res.json())
      .then(json => {
        if (json.success && json.data.profiles) {
          setProfiles(json.data.profiles);
          if (json.data.profiles.length > 0 && !profile) {
            setProfile(json.data.profiles[0].name);
          }
        }
      })
      .catch(console.error)
      .finally(() => setLoadingProfiles(false));
  }, []);

  const handleGenerate = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setError('Akses Ditolak: Akun Demo berstatus Read-Only. Pembuatan voucher MikroTik dinonaktifkan.');
      return;
    }
    setGenerating(true);
    setError('');
    setResult(null);

    try {
      const res = await fetch('/api/generate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          qty: Number(qty),
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
      } else {
        setError(json.message || 'Gagal membuat batch voucher');
      }
    } catch (err) {
      setError('Terjadi kesalahan jaringan atau server timeout');
    } finally {
      setGenerating(false);
    }
  };

  return (
    <div>
      <div style={{ marginBottom: '20px' }}>
        <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff' }}>
          Batch Voucher Generator
        </h2>
        <p style={{ fontSize: '12.5px', color: 'var(--text-secondary)' }}>
          Buat voucher massal untuk pelanggan hotspot dengan format kode fleksibel dan pencetakan instan lembar F4 / Thermal.
        </p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(300px, 1fr) minmax(320px, 1.2fr)', gap: '20px' }}>
        {/* Generator Form */}
        <div className="glass-card">
          <form onSubmit={handleGenerate}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
              {/* Jumlah Voucher */}
              <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)' }}>
                    Jumlah Voucher (Qty)
                  </label>
                  <span style={{ fontSize: '11px', color: 'var(--accent-cyan)', fontWeight: 700 }}>
                    {qty % 55 === 0 ? `${qty / 55} Lembar F4` : ''}
                  </span>
                </div>
                <input
                  type="number"
                  min="1"
                  max="500"
                  className="filter-select"
                  style={{ width: '100%' }}
                  value={qty}
                  onChange={e => setQty(e.target.value)}
                  required
                />
                {/* Quick Presets */}
                <div style={{ display: 'flex', gap: '6px', marginTop: '8px', flexWrap: 'wrap' }}>
                  {[
                    { label: '55 (1 Lembar F4)', val: 55 },
                    { label: '110 (2 Lembar F4)', val: 110 },
                    { label: '165 (3 Lembar F4)', val: 165 },
                    { label: '20 (Thermal)', val: 20 },
                    { label: '50 (Thermal)', val: 50 }
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
              </div>

              {/* Mode User */}
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
                  <option value="up">Username = Password (Satu Kode)</option>
                  <option value="vc">Username & Password Berbeda</option>
                </select>
              </div>

              {/* Profil Paket */}
              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Profil Paket Hotspot
                </label>
                <select
                  className="filter-select"
                  style={{ width: '100%' }}
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
                    Panjang Kode
                  </label>
                  <input
                    type="number"
                    min="3"
                    max="8"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={nameLength}
                    onChange={e => setNameLength(e.target.value)}
                  />
                </div>
                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Prefix (Awalan)
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

              <div>
                <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                  Kombinasi Karakter
                </label>
                <select
                  className="filter-select"
                  style={{ width: '100%' }}
                  value={charType}
                  onChange={e => setCharType(e.target.value)}
                >
                  <option value="lower">Huruf Kecil & Angka (abcd234)</option>
                  <option value="upper">Huruf Besar & Angka (ABCD234)</option>
                  <option value="num">Hanya Angka (123456)</option>
                  <option value="mix">Campuran Acak (aB3dE7)</option>
                </select>
              </div>

              {/* Time Limit & Data Limit */}
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                <div>
                  <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                    Batas Waktu / Time Limit (Opsional)
                  </label>
                  <input
                    type="text"
                    placeholder="Contoh: 12 jam, 12h, 1 hari, 1d"
                    className="filter-select"
                    style={{ width: '100%' }}
                    value={timelimit}
                    onChange={e => setTimelimit(e.target.value)}
                  />
                  {timelimit && (() => {
                    const p = parseBilingualDuration(timelimit);
                    return p ? (
                      <div style={{ marginTop: '5px', fontSize: '11px', color: 'var(--accent-emerald)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                        <span>⏱️ <strong>{p.humanId}</strong> ({p.mikrotik})</span>
                      </div>
                    ) : (
                      <div style={{ marginTop: '5px', fontSize: '11px', color: 'var(--accent-amber)' }}>
                        <span>Format: 12 jam / 12h, 1 hari / 1d, 30 menit</span>
                      </div>
                    );
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

              {/* Komentar Batch */}
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
                <div style={{ padding: '10px', background: 'rgba(244, 63, 94, 0.15)', color: 'var(--accent-rose)', borderRadius: 'var(--radius-sm)', fontSize: '12px' }}>
                  {error}
                </div>
              )}

              {isReadOnly && (
                <div style={{
                  padding: '10px 12px',
                  borderRadius: 'var(--radius-sm)',
                  background: 'rgba(245, 158, 11, 0.12)',
                  border: '1px solid rgba(245, 158, 11, 0.3)',
                  color: '#f59e0b',
                  fontSize: '12px',
                  lineHeight: '1.4'
                }}>
                  🔒 <strong>Mode Demo Aktif:</strong> Anda dapat mengonfigurasi formulir untuk simulasi, tetapi pembuatan voucher baru ke MikroTik dinonaktifkan.
                </div>
              )}

              <button
                type="submit"
                className="btn btn-primary"
                disabled={generating || isReadOnly}
                title={isReadOnly ? 'Fitur ini dinonaktifkan pada akun demo read-only' : ''}
                style={{ 
                  marginTop: '8px', 
                  padding: '12px', 
                  width: '100%',
                  opacity: isReadOnly ? 0.6 : 1,
                  cursor: isReadOnly ? 'not-allowed' : 'pointer'
                }}
              >
                {generating ? (
                  <>
                    <RefreshCw size={16} className="spin-anim" />
                    <span>Membuat Batch di MikroTik...</span>
                  </>
                ) : (
                  <>
                    <Sparkles size={16} />
                    <span>Generate {qty} Voucher {isReadOnly ? '(Read-Only)' : 'Sekarang'}</span>
                  </>
                )}
              </button>
            </div>
          </form>
        </div>

        {/* Results & Thermal Slips Preview */}
        <div className="glass-card" style={{ display: 'flex', flexDirection: 'column' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '14px' }}>
            <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#fff', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Ticket size={18} color="var(--accent-cyan)" />
              Hasil Pembuatan Voucher
            </h3>

            {result && result.vouchers && (
              <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                <button
                  className="btn btn-primary btn-sm"
                  onClick={() => onNavigate('print')}
                  style={{
                    background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                    fontWeight: 700
                  }}
                >
                  <Printer size={14} />
                  <span>Cetak Lembar F4 ({result.count})</span>
                </button>

                {result.batch_comment && (
                  <a
                    href={`/voucher/print.php?id=${encodeURIComponent(result.batch_comment)}&session=${encodeURIComponent(server === 'all' ? 'Rumah-DOLPHIN' : server)}&paper=f4`}
                    target="_blank"
                    rel="noreferrer"
                    className="btn btn-secondary btn-sm"
                    style={{ fontSize: '11.5px', color: '#38bdf8', borderColor: 'rgba(56, 189, 248, 0.4)' }}
                    title="Buka format print klasik Mikhmon di tab baru"
                  >
                    <span>Mikhmon F4</span>
                  </a>
                )}
              </div>
            )}
          </div>

          {!result ? (
            <div style={{
              flex: 1,
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              justifyContent: 'center',
              color: 'var(--text-muted)',
              padding: '40px 20px',
              textAlign: 'center',
              border: '2px dashed var(--border-subtle)',
              borderRadius: 'var(--radius-md)'
            }}>
              <Ticket size={40} style={{ marginBottom: '12px', opacity: 0.4 }} />
              <p style={{ fontSize: '14px', fontWeight: 600 }}>Belum ada batch voucher yang digenerate</p>
              <p style={{ fontSize: '12px', marginTop: '4px' }}>
                Atur parameter di formulir kiri lalu tekan tombol generate.
              </p>
            </div>
          ) : (
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
              <div style={{
                padding: '10px 14px',
                background: 'rgba(16, 185, 129, 0.1)',
                border: '1px solid rgba(16, 185, 129, 0.25)',
                borderRadius: 'var(--radius-sm)',
                marginBottom: '14px',
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                fontSize: '12.5px',
                color: 'var(--accent-emerald)'
              }}>
                <CheckCircle size={16} />
                <span>Berhasil membuat <strong>{result.count} voucher</strong> (Profil: {result.profile}, Batch: {result.batch_comment})</span>
              </div>

              <div style={{
                flex: 1,
                maxHeight: '400px',
                overflowY: 'auto',
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(130px, 1fr))',
                gap: '10px',
                paddingRight: '6px'
              }}>
                {result.vouchers.map((v, idx) => (
                  <div key={idx} style={{
                    background: 'var(--bg-surface)',
                    border: '1px solid var(--border-subtle)',
                    borderRadius: 'var(--radius-sm)',
                    padding: '10px',
                    textAlign: 'center'
                  }}>
                    <div style={{ fontSize: '10px', color: 'var(--text-muted)' }}>KODE VOUCHER</div>
                    <div style={{
                      fontSize: '15px',
                      fontWeight: 800,
                      fontFamily: 'var(--font-mono)',
                      color: 'var(--accent-cyan)',
                      margin: '3px 0'
                    }}>
                      {v.name}
                    </div>
                    {v.name !== v.password && (
                      <div style={{ fontSize: '11px', fontFamily: 'var(--font-mono)', color: 'var(--text-secondary)' }}>
                        Pass: {v.password}
                      </div>
                    )}
                    <div style={{ fontSize: '10px', color: 'var(--text-muted)', marginTop: '4px' }}>
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
