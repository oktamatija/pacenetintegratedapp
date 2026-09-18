import React, { useState } from 'react';
import { Zap, Shield, KeyRound, ArrowRight, RefreshCw, AlertCircle, Eye } from 'lucide-react';

export default function Login({ onLoginSuccess }) {
  const [user, setUser] = useState('');
  const [pass, setPass] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const res = await fetch('/api/auth.php?action=login', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user, pass })
      });
      const json = await res.json();
      if (json.success) {
        if (json.data?.token) {
          localStorage.setItem('pacenet_token', json.data.token);
          localStorage.setItem('pacenet_user_data', JSON.stringify(json.data));
        }
        onLoginSuccess(json.data);
      } else {
        setError(json.message || 'Username atau password salah.');
      }
    } catch (err) {
      setError('Gagal menghubungi server otentikasi.');
    } finally {
      setLoading(false);
    }
  };

  const handleDemoLogin = async () => {
    setUser('demo');
    setPass('demo');
    setLoading(true);
    setError('');

    try {
      const res = await fetch('/api/auth.php?action=login', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user: 'demo', pass: 'demo' })
      });
      const json = await res.json();
      if (json.success) {
        if (json.data?.token) {
          localStorage.setItem('pacenet_token', json.data.token);
          localStorage.setItem('pacenet_user_data', JSON.stringify(json.data));
        }
        onLoginSuccess(json.data);
      } else {
        setError(json.message || 'Gagal login akun demo.');
      }
    } catch (err) {
      setError('Gagal menghubungi server otentikasi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '20px',
      background: 'radial-gradient(circle at 50% 30%, rgba(0, 210, 211, 0.08) 0%, #090c10 70%)'
    }}>
      <div className="glass-card" style={{
        width: '100%',
        maxWidth: '420px',
        padding: '36px 30px',
        border: '1px solid rgba(0, 210, 211, 0.25)',
        boxShadow: '0 0 35px rgba(0, 210, 211, 0.12)'
      }}>
        {/* Brand */}
        <div style={{ textAlign: 'center', marginBottom: '28px' }}>
          <div style={{
            width: '56px',
            height: '56px',
            borderRadius: '16px',
            background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            boxShadow: '0 0 20px rgba(0, 210, 211, 0.35)',
            marginBottom: '16px',
            color: '#fff'
          }}>
            <Zap size={30} />
          </div>
          <h1 style={{ fontSize: '22px', fontWeight: 800, color: '#fff', letterSpacing: '0.5px' }}>
            PACENET PRO
          </h1>
          <p style={{ fontSize: '12px', color: 'var(--accent-cyan)', fontWeight: 600, letterSpacing: '1px', textTransform: 'uppercase', marginTop: '2px' }}>
            Enterprise Cloud NOC Controller
          </p>
        </div>

        {error && (
          <div style={{
            padding: '12px 14px',
            background: 'rgba(244, 63, 94, 0.15)',
            border: '1px solid rgba(244, 63, 94, 0.3)',
            borderRadius: 'var(--radius-sm)',
            color: 'var(--accent-rose)',
            fontSize: '13px',
            marginBottom: '20px',
            display: 'flex',
            alignItems: 'center',
            gap: '8px'
          }}>
            <AlertCircle size={16} />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
            <div>
              <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                Username Operator
              </label>
              <div style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                background: 'var(--bg-input)',
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-md)',
                padding: '10px 14px'
              }}>
                <Shield size={16} color="var(--text-muted)" />
                <input
                  type="text"
                  placeholder="Masukkan username..."
                  style={{ background: 'none', border: 'none', outline: 'none', width: '100%', fontSize: '13.5px' }}
                  value={user}
                  onChange={e => setUser(e.target.value)}
                  required
                  autoFocus
                />
              </div>
            </div>

            <div>
              <label style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: '6px' }}>
                Password
              </label>
              <div style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                background: 'var(--bg-input)',
                border: '1px solid var(--border-subtle)',
                borderRadius: 'var(--radius-md)',
                padding: '10px 14px'
              }}>
                <KeyRound size={16} color="var(--text-muted)" />
                <input
                  type="password"
                  placeholder="Masukkan kata sandi..."
                  style={{ background: 'none', border: 'none', outline: 'none', width: '100%', fontSize: '13.5px' }}
                  value={pass}
                  onChange={e => setPass(e.target.value)}
                  required
                />
              </div>
            </div>

            <button
              type="submit"
              className="btn btn-primary"
              disabled={loading}
              style={{
                marginTop: '10px',
                padding: '12px',
                width: '100%',
                fontSize: '14px',
                borderRadius: 'var(--radius-md)'
              }}
            >
              {loading ? (
                <>
                  <RefreshCw size={16} className="spin-anim" />
                  <span>Memverifikasi Sesi...</span>
                </>
              ) : (
                <>
                  <span>Masuk ke Controller</span>
                  <ArrowRight size={16} />
                </>
              )}
            </button>

            <div style={{ display: 'flex', alignItems: 'center', margin: '8px 0', gap: '10px' }}>
              <div style={{ flex: 1, height: '1px', background: 'var(--border-subtle)' }} />
              <span style={{ fontSize: '11px', color: 'var(--text-muted)', textTransform: 'uppercase', letterSpacing: '0.5px' }}>atau uji coba role</span>
              <div style={{ flex: 1, height: '1px', background: 'var(--border-subtle)' }} />
            </div>

            {/* Quick Role Fill Buttons */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '6px' }}>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => { setUser('owner'); setPass('1234'); }}
                style={{ fontSize: '11px', padding: '6px 4px' }}
                title="Owner: Akses Penuh + Kelola Pengguna"
              >
                👑 Owner
              </button>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => { setUser('admin'); setPass('1234'); }}
                style={{ fontSize: '11px', padding: '6px 4px' }}
                title="Admin: Akses Penuh + Kelola Pengguna"
              >
                🛡️ Admin
              </button>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => { setUser('manager'); setPass('1234'); }}
                style={{ fontSize: '11px', padding: '6px 4px' }}
                title="Manager: Seluruh Menu Operasional"
              >
                👔 Manager
              </button>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => { setUser('reseller'); setPass('1234'); }}
                style={{ fontSize: '11px', padding: '6px 4px', color: 'var(--accent-amber)' }}
                title="Reseller: Portal Cek & Scanner Kamera"
              >
                🏪 Reseller
              </button>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => { setUser('staff_noc'); setPass('1234'); }}
                style={{ fontSize: '11px', padding: '6px 4px', color: 'var(--accent-blue)' }}
                title="Staff NOC: Monitoring Core & Dashboard"
              >
                ⚡ Staff NOC
              </button>
              <button
                type="button"
                className="btn btn-secondary btn-sm"
                onClick={() => { setUser('finance'); setPass('1234'); }}
                style={{ fontSize: '11px', padding: '6px 4px', color: 'var(--accent-emerald)' }}
                title="Finance: Rekap Penjualan & Ekspor"
              >
                💼 Finance
              </button>
            </div>

            <button
              type="button"
              onClick={handleDemoLogin}
              disabled={loading}
              className="btn"
              style={{
                width: '100%',
                padding: '10px 14px',
                background: 'rgba(245, 158, 11, 0.12)',
                border: '1px solid rgba(245, 158, 11, 0.35)',
                color: '#f59e0b',
                borderRadius: 'var(--radius-md)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '8px',
                fontSize: '12.5px',
                fontWeight: 600,
                cursor: 'pointer',
                transition: 'all 0.2s',
                marginTop: '4px'
              }}
            >
              <Eye size={15} />
              <span>Masuk Akun Demo (Read-Only)</span>
            </button>
          </div>
        </form>

        <div style={{ 
          marginTop: '18px', 
          padding: '10px 14px', 
          borderRadius: 'var(--radius-sm)', 
          background: 'rgba(255, 255, 255, 0.03)', 
          border: '1px solid var(--border-subtle)',
          fontSize: '11.5px',
          color: 'var(--text-muted)',
          textAlign: 'center',
          lineHeight: '1.5'
        }}>
          💡 <strong>Info Akun Operator:</strong> Password default seluruh akun uji coba adalah <code style={{ color: 'var(--accent-cyan)' }}>1234</code>.<br />
          Role dan akun dapat dikelola melalui menu <strong>Kelola Pengguna</strong> oleh Owner & Admin.
        </div>

        <div style={{ textAlign: 'center', marginTop: '24px', fontSize: '11px', color: 'var(--text-muted)' }}>
          PACENET NETWORK OPERATIONS CENTER &copy; 2026
        </div>
      </div>
    </div>
  );
}
