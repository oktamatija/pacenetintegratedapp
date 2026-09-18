import React, { useState, useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { 
  Network, 
  Server, 
  Cpu, 
  Wifi, 
  Users, 
  ArrowUpRight, 
  AlertTriangle, 
  CheckCircle2, 
  PlusCircle, 
  RefreshCw, 
  Edit3, 
  Trash2, 
  ExternalLink, 
  X, 
  Zap, 
  TrendingUp,
  Activity,
  Radio,
  Sliders,
  MapPin,
  Compass,
  Layers,
  Download,
  Eye,
  Globe
} from 'lucide-react';
import { authFetch } from '../utils/api';

export default function OltOntTopology({ onNavigate, isReadOnly }) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filterOlt, setFilterOlt] = useState('all');
  const [filterStatus, setFilterStatus] = useState('all');

  // View mode: 'gis' (Map) or 'list' (Cards)
  const [viewMode, setViewMode] = useState('gis');

  // Map state
  const mapContainerRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const mapLayersRef = useRef({});
  const [activeTileLayer, setActiveTileLayer] = useState('google_satellite'); // 'google_satellite', 'google_roadmap', 'dark_noc'
  const [showCoverageCircles, setShowCoverageCircles] = useState(true);
  const [showFiberLines, setShowFiberLines] = useState(true);

  // Modal State
  const [showModal, setShowModal] = useState(false);
  const [modalMode, setModalMode] = useState('create');
  const [formData, setFormData] = useState({
    id: '',
    type: 'ONT',
    name: '',
    model: 'ZTE F609 v3 (100M)',
    sn: '',
    mac: '',
    vpn_ip: '10.10.10.105',
    olt_id: 'olt-01',
    pon_port: 'PON 1',
    router_session: 'Dolphin-Hamadi',
    subnet_cidr: '10.0.0.0/24',
    location: '',
    max_users_capacity: 25,
    max_bandwidth_mbps: 100,
    lat: -2.5640,
    lng: 140.7070,
    coverage_radius_meters: 100
  });

  const [feedback, setFeedback] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);

  const fetchTopology = async () => {
    try {
      setLoading(true);
      const res = await authFetch('/api/olt_ont.php');
      const json = await res.json();
      if (json.success) {
        setData(json.data);
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal memuat topologi OLT/ONT.' });
      }
    } catch (e) {
      setFeedback({ type: 'error', text: 'Koneksi ke API topologi OLT/ONT gagal.' });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTopology();
    const interval = setInterval(() => {
      if (!document.hidden) {
        fetchTopology();
      }
    }, 20000); // 20s live refresh and only when tab is active
    return () => clearInterval(interval);
  }, []);

  // Initialize and update Leaflet Map
  useEffect(() => {
    if (viewMode !== 'gis' || !mapContainerRef.current) return;

    const olts = data?.olts || [];
    const onts = data?.onts || [];

    // Calculate center coordinates from OLT or fallback to Hamadi Jayapura (-2.5645, 140.7065)
    let centerLat = -2.5645;
    let centerLng = 140.7065;
    if (olts.length > 0 && olts[0].lat && olts[0].lng) {
      centerLat = Number(olts[0].lat);
      centerLng = Number(olts[0].lng);
    }

    if (!mapInstanceRef.current) {
      const map = L.map(mapContainerRef.current, {
        center: [centerLat, centerLng],
        zoom: 16,
        zoomControl: false,
        attributionControl: false
      });

      L.control.zoom({ position: 'bottomright' }).addTo(map);

      // Tile layers
      const googleSatellite = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
      });

      const googleRoadmap = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
        maxZoom: 20,
        subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
      });

      const darkNoc = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd'
      });

      mapLayersRef.current = {
        google_satellite: googleSatellite,
        google_roadmap: googleRoadmap,
        dark_noc: darkNoc,
        features: L.layerGroup().addTo(map)
      };

      // Default to Google Satellite (Google Earth imagery)
      googleSatellite.addTo(map);
      mapInstanceRef.current = map;
    }

    // Switch active tile layer if changed
    const currentLayer = mapLayersRef.current[activeTileLayer];
    ['google_satellite', 'google_roadmap', 'dark_noc'].forEach(key => {
      if (mapInstanceRef.current.hasLayer(mapLayersRef.current[key]) && key !== activeTileLayer) {
        mapInstanceRef.current.removeLayer(mapLayersRef.current[key]);
      }
    });
    if (currentLayer && !mapInstanceRef.current.hasLayer(currentLayer)) {
      currentLayer.addTo(mapInstanceRef.current);
    }

    // Clear previous feature markers/lines
    const featureGroup = mapLayersRef.current.features;
    if (featureGroup) {
      featureGroup.clearLayers();

      // Draw OLT Markers
      olts.forEach(olt => {
        const lat = Number(olt.lat || -2.5645);
        const lng = Number(olt.lng || 140.7065);

        const oltIcon = L.divIcon({
          className: 'custom-gis-olt',
          html: `
            <div style="
              width: 38px;
              height: 38px;
              border-radius: 50%;
              background: #090c10;
              border: 2px solid #00d2d3;
              box-shadow: 0 0 15px rgba(0, 210, 211, 0.6);
              display: flex;
              align-items: center;
              justify-content: center;
              color: #00d2d3;
              cursor: pointer;
            ">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                <line x1="6" y1="6" x2="6.01" y2="6"></line>
                <line x1="6" y1="18" x2="6.01" y2="18"></line>
              </svg>
            </div>
          `,
          iconSize: [38, 38],
          iconAnchor: [19, 19]
        });

        const oltMarker = L.marker([lat, lng], { icon: oltIcon });
        oltMarker.bindPopup(`
          <div style="font-family: 'Outfit', sans-serif; color: #fff; min-width: 220px;">
            <div style="font-size: 14px; font-weight: 800; color: #00d2d3; margin-bottom: 2px;">${olt.name}</div>
            <div style="font-size: 11px; color: #94a3b8; margin-bottom: 8px;">CORE OPTICAL LINE TERMINAL</div>
            <div style="font-size: 11.5px; line-height: 1.5; margin-bottom: 10px;">
              <strong>Model:</strong> ${olt.model || '-'}<br/>
              <strong>SN:</strong> ${olt.sn}<br/>
              <strong>IP WireGuard:</strong> ${olt.vpn_ip}<br/>
              <strong>Lokasi:</strong> ${olt.location}<br/>
              <strong>Uplink:</strong> 2.5 Gbps GPON Core
            </div>
            <div style="display: flex; gap: 6px;">
              <a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" style="flex: 1; text-align: center; background: #00d2d3; color: #000; font-size: 10.5px; font-weight: 700; padding: 5px 8px; border-radius: 4px; text-decoration: none;">
                Google Maps
              </a>
              <a href="http://${olt.vpn_ip}" target="_blank" style="flex: 1; text-align: center; background: #2f3542; color: #fff; font-size: 10.5px; font-weight: 700; padding: 5px 8px; border-radius: 4px; text-decoration: none;">
                Web GUI
              </a>
            </div>
          </div>
        `);
        featureGroup.addLayer(oltMarker);
      });

      // Draw ONTs, Coverage Circles & Fiber Lines
      onts.forEach(ont => {
        const lat = Number(ont.lat || -2.5630);
        const lng = Number(ont.lng || 140.7088);
        const radius = Number(ont.coverage_radius_meters || 100);
        const isCritical = ont.status_level === 'critical';
        const isWarning = ont.status_level === 'warning';

        const colorHex = isCritical ? '#f43f5e' : isWarning ? '#f59e0b' : '#10b981';

        // 1. Coverage Area Circle
        if (showCoverageCircles) {
          const circle = L.circle([lat, lng], {
            radius: radius,
            color: colorHex,
            weight: isCritical ? 2.5 : 1.5,
            dashArray: isCritical ? '6, 4' : '4, 4',
            fillColor: colorHex,
            fillOpacity: isCritical ? 0.25 : 0.12
          });
          circle.bindTooltip(`Coverage: ${ont.name} (${radius}m) - ${ont.active_users}/${ont.max_users_capacity} User`, {
            direction: 'top',
            className: 'gis-tooltip'
          });
          featureGroup.addLayer(circle);
        }

        // 2. Fiber Optic Cable Line to Core OLT
        if (showFiberLines && olts.length > 0) {
          const oltLat = Number(olts[0].lat || -2.5645);
          const oltLng = Number(olts[0].lng || 140.7065);
          const pon = ont.pon_port || 'PON 1';
          const lineColor = pon.includes('1') ? '#00d2d3' : pon.includes('2') ? '#10b981' : '#3b82f6';

          const line = L.polyline([[oltLat, oltLng], [lat, lng]], {
            color: lineColor,
            weight: 2.5,
            opacity: 0.85,
            dashArray: '5, 5'
          });
          line.bindTooltip(`Fiber Optic: ${pon} ➔ ${ont.name}`, { sticky: true });
          featureGroup.addLayer(line);
        }

        // 3. ONT Node Marker with Active Users Badge
        const ontIcon = L.divIcon({
          className: 'custom-gis-ont',
          html: `
            <div style="
              width: 32px;
              height: 32px;
              border-radius: 50%;
              background: #090c10;
              border: 2px solid ${colorHex};
              box-shadow: 0 0 ${isCritical ? '18px rgba(244, 63, 94, 0.8)' : '10px rgba(16, 185, 129, 0.5)'};
              display: flex;
              align-items: center;
              justify-content: center;
              color: ${colorHex};
              cursor: pointer;
              position: relative;
            ">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="2"></circle>
                <path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49"></path>
              </svg>
              <div style="
                position: absolute;
                top: -8px;
                right: -10px;
                background: ${colorHex};
                color: #090c10;
                font-size: 9px;
                font-weight: 900;
                padding: 1px 5px;
                border-radius: 10px;
                box-shadow: 0 1px 4px rgba(0,0,0,0.5);
              ">
                ${ont.active_users}
              </div>
            </div>
          `,
          iconSize: [32, 32],
          iconAnchor: [16, 16]
        });

        const ontMarker = L.marker([lat, lng], { icon: ontIcon });
        ontMarker.bindPopup(`
          <div style="font-family: 'Outfit', sans-serif; color: #fff; min-width: 250px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <span style="font-size: 13.5px; font-weight: 800; color: #fff;">${ont.name}</span>
              <span style="background: ${colorHex}; color: #090c10; font-size: 9px; font-weight: 800; padding: 1px 6px; border-radius: 4px;">
                ${isCritical ? 'OVERLOAD' : isWarning ? 'WARNING' : 'NORMAL'}
              </span>
            </div>
            <div style="font-size: 11px; color: #94a3b8; margin-bottom: 8px;">
              ${ont.location} • ${ont.pon_port} • ${ont.router_session}
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 11.5px; line-height: 1.5;">
              <strong>Beban User:</strong> <span style="color: ${colorHex}; font-weight: 700;">${ont.active_users} / ${ont.max_users_capacity} User (${ont.user_utilization_percent}%)</span><br/>
              <strong>Bandwidth:</strong> ${ont.current_bandwidth_mbps} Mbps / ${ont.max_bandwidth_mbps} Mbps<br/>
              <strong>Radius Sinyal:</strong> ${radius} Meter<br/>
              <strong>IP VPN:</strong> ${ont.vpn_ip}
            </div>

            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
              <a href="https://maps.google.com/?q=${lat},${lng}" target="_blank" style="flex: 1; text-align: center; background: #00d2d3; color: #000; font-size: 10.5px; font-weight: 700; padding: 5px 8px; border-radius: 4px; text-decoration: none;">
                Google Maps
              </a>
              <a href="https://earth.google.com/web/search/${lat},${lng}" target="_blank" style="flex: 1; text-align: center; background: #3b82f6; color: #fff; font-size: 10.5px; font-weight: 700; padding: 5px 8px; border-radius: 4px; text-decoration: none;">
                Google Earth
              </a>
              <a href="http://${ont.vpn_ip}" target="_blank" style="flex: 1; text-align: center; background: #2f3542; color: #fff; font-size: 10.5px; font-weight: 700; padding: 5px 8px; border-radius: 4px; text-decoration: none;">
                Web ONT
              </a>
            </div>
          </div>
        `);
        featureGroup.addLayer(ontMarker);
      });
    }
  }, [viewMode, data, activeTileLayer, showCoverageCircles, showFiberLines]);

  const handleOpenCreate = () => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penambahan OLT/ONT dinonaktifkan.' });
      return;
    }
    setModalMode('create');
    setFormData({
      id: '',
      type: 'ONT',
      name: '',
      model: 'ZTE F609 v3 (100M Single-Band)',
      sn: '',
      mac: '',
      vpn_ip: '10.10.10.105',
      olt_id: 'olt-01',
      pon_port: 'PON 1',
      router_session: 'Dolphin-Hamadi',
      subnet_cidr: '10.0.0.0/24',
      location: '',
      max_users_capacity: 25,
      max_bandwidth_mbps: 100,
      lat: -2.5640,
      lng: 140.7070,
      coverage_radius_meters: 100
    });
    setShowModal(true);
  };

  const handleOpenEdit = (d) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Pengeditan OLT/ONT dinonaktifkan.' });
      return;
    }
    setModalMode('edit');
    setFormData({
      id: d.id,
      type: d.type || 'ONT',
      name: d.name,
      model: d.model || '',
      sn: d.sn || '',
      mac: d.mac || '',
      vpn_ip: d.vpn_ip || '',
      olt_id: d.olt_id || 'olt-01',
      pon_port: d.pon_port || 'PON 1',
      router_session: d.router_session || 'Dolphin-Hamadi',
      subnet_cidr: d.subnet_cidr || '10.0.0.0/24',
      location: d.location || '',
      max_users_capacity: d.max_users_capacity || 25,
      max_bandwidth_mbps: d.max_bandwidth_mbps || 100,
      lat: Number(d.lat || -2.5640),
      lng: Number(d.lng || 140.7070),
      coverage_radius_meters: Number(d.coverage_radius_meters || 100)
    });
    setShowModal(true);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penyimpanan OLT/ONT dinonaktifkan.' });
      return;
    }
    setActionLoading(true);
    setFeedback(null);

    try {
      const res = await authFetch('/api/olt_ont.php?action=save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message || 'Perangkat OLT/ONT berhasil disimpan.' });
        setShowModal(false);
        fetchTopology();
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal menyimpan perangkat.' });
      }
    } catch (err) {
      setFeedback({ type: 'error', text: 'Kesalahan jaringan saat menyimpan OLT/ONT.' });
    } finally {
      setActionLoading(false);
    }
  };

  const handleDelete = async (d) => {
    if (isReadOnly) {
      setFeedback({ type: 'error', text: 'Akses Ditolak: Akun Demo berstatus Read-Only. Penghapusan OLT/ONT dinonaktifkan.' });
      return;
    }
    if (!window.confirm(`Yakin ingin menghapus ${d.type} "${d.name}" (${d.id})?`)) return;

    setActionLoading(true);
    try {
      const res = await authFetch('/api/olt_ont.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: d.id })
      });
      const json = await res.json();
      if (json.success) {
        setFeedback({ type: 'success', text: json.message || 'Perangkat berhasil dihapus.' });
        fetchTopology();
      } else {
        setFeedback({ type: 'error', text: json.message || 'Gagal menghapus perangkat.' });
      }
    } catch (err) {
      setFeedback({ type: 'error', text: 'Terjadi kesalahan jaringan saat menghapus perangkat.' });
    } finally {
      setActionLoading(false);
    }
  };

  const summary = data?.summary || {
    total_olts: 0,
    total_onts: 0,
    total_users_on_ont: 0,
    upgrade_recommended_count: 0
  };

  const olts = data?.olts || [];
  const onts = data?.onts || [];

  const filteredOnts = onts.filter(ont => {
    if (filterOlt !== 'all' && ont.olt_id !== filterOlt) return false;
    if (filterStatus === 'upgrade' && !ont.upgrade_needed) return false;
    if (filterStatus === 'warning' && ont.status_level !== 'warning') return false;
    if (filterStatus === 'optimal' && ont.status_level !== 'optimal') return false;
    return true;
  });

  return (
    <div>
      {/* Top Header & View Mode Switcher */}
      <div className="glass-card" style={{ marginBottom: '20px', padding: '16px 20px' }}>
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          gap: '12px'
        }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Network size={20} color="var(--accent-cyan)" />
              <h2 style={{ fontSize: '18px', fontWeight: 800, color: '#fff' }}>
                Pemetaan FTTH: Google Maps / Earth &amp; Coverage Area
              </h2>
            </div>
            <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
              Visualisasi geografis satelit OLT, titik ONT, rute fiber optik, radius coverage area sinyal WiFi, serta ekspor KML.
            </p>
          </div>

          <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
            {/* View Mode Toggle: Map vs List */}
            <div style={{
              display: 'flex',
              background: 'rgba(0,0,0,0.35)',
              padding: '3px',
              borderRadius: 'var(--radius-sm)',
              border: '1px solid var(--border-subtle)'
            }}>
              <button
                onClick={() => setViewMode('gis')}
                style={{
                  padding: '6px 14px',
                  borderRadius: '4px',
                  fontSize: '12px',
                  fontWeight: 700,
                  display: 'flex',
                  alignItems: 'center',
                  gap: '6px',
                  background: viewMode === 'gis' ? 'var(--accent-cyan)' : 'transparent',
                  color: viewMode === 'gis' ? '#090c10' : 'var(--text-secondary)'
                }}
              >
                <Globe size={14} />
                <span>Peta Satelit GIS</span>
              </button>
              <button
                onClick={() => setViewMode('list')}
                style={{
                  padding: '6px 14px',
                  borderRadius: '4px',
                  fontSize: '12px',
                  fontWeight: 700,
                  display: 'flex',
                  alignItems: 'center',
                  gap: '6px',
                  background: viewMode === 'list' ? 'var(--accent-cyan)' : 'transparent',
                  color: viewMode === 'list' ? '#090c10' : 'var(--text-secondary)'
                }}
              >
                <Sliders size={14} />
                <span>Daftar &amp; Kapasitas</span>
              </button>
            </div>

            {/* Export Google Earth KML Button */}
            <a
              href="/api/olt_ont.php?action=export_kml&token=pacenet_session_active"
              download="pacenet_ftth_coverage.kml"
              className="btn btn-secondary btn-sm"
              style={{
                borderColor: 'rgba(59, 130, 246, 0.4)',
                color: 'var(--accent-blue)',
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px'
              }}
              title="Unduh file KML untuk dibuka di Google Earth Pro Desktop/HP"
            >
              <Download size={14} />
              <span>Ekspor Google Earth (.kml)</span>
            </a>

            <button 
              className="btn btn-secondary btn-sm"
              onClick={fetchTopology}
              disabled={loading}
            >
              <RefreshCw size={14} className={loading ? 'spin' : ''} />
              <span>Refresh</span>
            </button>

            <button 
              className="btn btn-primary btn-sm"
              onClick={handleOpenCreate}
              style={{
                background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)',
                boxShadow: '0 0 12px var(--accent-cyan-glow)'
              }}
            >
              <PlusCircle size={15} />
              <span>Tambah Perangkat</span>
            </button>
          </div>
        </div>
      </div>

      {/* Feedback Banner */}
      {feedback && (
        <div style={{
          marginBottom: '16px',
          padding: '12px 16px',
          borderRadius: 'var(--radius-md)',
          background: feedback.type === 'success' ? 'rgba(16, 185, 129, 0.12)' : 'rgba(244, 63, 94, 0.12)',
          border: `1px solid ${feedback.type === 'success' ? 'var(--accent-emerald)' : 'var(--accent-rose)'}`,
          color: feedback.type === 'success' ? '#34d399' : '#fb7185',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          fontSize: '13px'
        }}>
          <span>{feedback.text}</span>
          <button 
            onClick={() => setFeedback(null)} 
            style={{ background: 'none', border: 'none', color: 'inherit', cursor: 'pointer' }}
          >
            <X size={16} />
          </button>
        </div>
      )}

      {/* KPI Stats Grid */}
      <div className="kpi-grid" style={{ marginBottom: '20px' }}>
        <div className="glass-card kpi-card">
          <div className="kpi-info">
            <h3>Core OLT Terpasang</h3>
            <div className="kpi-value">{summary.total_olts} Unit</div>
            <div className="kpi-sub">Optical Line Terminal Utama</div>
          </div>
          <div className="kpi-icon">
            <Server size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card emerald">
          <div className="kpi-info">
            <h3>Total ONT Terpasang</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-emerald)' }}>
              {summary.total_onts} Titik
            </div>
            <div className="kpi-sub">Optical Network Terminal Klien &amp; Cluster</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-emerald)' }}>
            <Radio size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card blue">
          <div className="kpi-info">
            <h3>User Terhubung di ONT</h3>
            <div className="kpi-value" style={{ color: 'var(--accent-blue)' }}>
              {summary.total_users_on_ont} User
            </div>
            <div className="kpi-sub">Pelanggan aktif hotspot terdistribusi</div>
          </div>
          <div className="kpi-icon" style={{ color: 'var(--accent-blue)' }}>
            <Users size={24} />
          </div>
        </div>

        <div className="glass-card kpi-card" style={{
          borderColor: summary.upgrade_recommended_count > 0 ? 'var(--accent-rose)' : 'var(--border-subtle)',
          boxShadow: summary.upgrade_recommended_count > 0 ? '0 0 15px rgba(244, 63, 94, 0.2)' : 'none'
        }}>
          <div className="kpi-info">
            <h3>Rekomendasi Upgrade ONT</h3>
            <div className="kpi-value" style={{ color: summary.upgrade_recommended_count > 0 ? 'var(--accent-rose)' : 'var(--accent-emerald)' }}>
              {summary.upgrade_recommended_count} Unit
            </div>
            <div className="kpi-sub" style={{ color: summary.upgrade_recommended_count > 0 ? 'var(--accent-rose)' : 'var(--text-muted)' }}>
              {summary.upgrade_recommended_count > 0 ? 'Beban kritis (>85% kapasitas)' : 'Seluruh ONT dalam batas optimal'}
            </div>
          </div>
          <div className="kpi-icon" style={{ color: summary.upgrade_recommended_count > 0 ? 'var(--accent-rose)' : 'var(--accent-emerald)' }}>
            <AlertTriangle size={24} />
          </div>
        </div>
      </div>

      {/* GIS MAP VIEW */}
      {viewMode === 'gis' && (
        <div className="glass-card" style={{
          padding: '16px',
          marginBottom: '20px',
          border: '1px solid var(--border-active)'
        }}>
          {/* Map Controls Header */}
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: '12px',
            flexWrap: 'wrap',
            gap: '10px'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Compass size={16} color="var(--accent-cyan)" />
              <span style={{ fontSize: '13px', fontWeight: 800, color: '#fff' }}>
                Peta Geografis Sinyal FTTH &amp; Coverage Area
              </span>
              <span style={{ fontSize: '11.5px', color: 'var(--text-muted)' }}>
                (Hamadi, Jayapura Selatan)
              </span>
            </div>

            {/* Map Layer Switchers & Toggles */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
              {/* Tile layer selectors */}
              <div style={{
                display: 'flex',
                background: 'rgba(0,0,0,0.4)',
                padding: '2px',
                borderRadius: '4px',
                border: '1px solid var(--border-subtle)'
              }}>
                <button
                  onClick={() => setActiveTileLayer('google_satellite')}
                  style={{
                    padding: '4px 10px',
                    fontSize: '11px',
                    borderRadius: '3px',
                    fontWeight: 600,
                    background: activeTileLayer === 'google_satellite' ? 'var(--accent-cyan)' : 'transparent',
                    color: activeTileLayer === 'google_satellite' ? '#090c10' : 'var(--text-secondary)'
                  }}
                >
                  Satelit Google Earth
                </button>
                <button
                  onClick={() => setActiveTileLayer('google_roadmap')}
                  style={{
                    padding: '4px 10px',
                    fontSize: '11px',
                    borderRadius: '3px',
                    fontWeight: 600,
                    background: activeTileLayer === 'google_roadmap' ? 'var(--accent-cyan)' : 'transparent',
                    color: activeTileLayer === 'google_roadmap' ? '#090c10' : 'var(--text-secondary)'
                  }}
                >
                  Google Maps
                </button>
                <button
                  onClick={() => setActiveTileLayer('dark_noc')}
                  style={{
                    padding: '4px 10px',
                    fontSize: '11px',
                    borderRadius: '3px',
                    fontWeight: 600,
                    background: activeTileLayer === 'dark_noc' ? 'var(--accent-cyan)' : 'transparent',
                    color: activeTileLayer === 'dark_noc' ? '#090c10' : 'var(--text-secondary)'
                  }}
                >
                  NOC Dark
                </button>
              </div>

              {/* Toggles */}
              <button
                onClick={() => setShowCoverageCircles(!showCoverageCircles)}
                style={{
                  padding: '4px 10px',
                  fontSize: '11px',
                  borderRadius: '4px',
                  border: '1px solid var(--border-subtle)',
                  background: showCoverageCircles ? 'rgba(16, 185, 129, 0.15)' : 'rgba(0,0,0,0.3)',
                  color: showCoverageCircles ? 'var(--accent-emerald)' : 'var(--text-muted)'
                }}
              >
                ● Coverage Circles ({showCoverageCircles ? 'ON' : 'OFF'})
              </button>

              <button
                onClick={() => setShowFiberLines(!showFiberLines)}
                style={{
                  padding: '4px 10px',
                  fontSize: '11px',
                  borderRadius: '4px',
                  border: '1px solid var(--border-subtle)',
                  background: showFiberLines ? 'rgba(0, 210, 211, 0.15)' : 'rgba(0,0,0,0.3)',
                  color: showFiberLines ? 'var(--accent-cyan)' : 'var(--text-muted)'
                }}
              >
                ⎯ Kabel FO ({showFiberLines ? 'ON' : 'OFF'})
              </button>
            </div>
          </div>

          {/* Leaflet Map Canvas Container */}
          <div 
            ref={mapContainerRef} 
            style={{
              height: '520px',
              width: '100%',
              borderRadius: 'var(--radius-md)',
              overflow: 'hidden',
              border: '1px solid var(--border-subtle)',
              position: 'relative'
            }}
          />

          {/* Legend Bar */}
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginTop: '12px',
            padding: '10px 14px',
            background: 'rgba(0,0,0,0.35)',
            borderRadius: 'var(--radius-sm)',
            fontSize: '11.5px',
            color: 'var(--text-secondary)',
            flexWrap: 'wrap',
            gap: '10px'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '14px', flexWrap: 'wrap' }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                <span style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#00d2d3', border: '2px solid #fff' }} />
                <strong>Core OLT</strong>
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                <span style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#10b981' }} />
                <span>ONT Normal (&lt;60%)</span>
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                <span style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#f59e0b' }} />
                <span>ONT Warning (60-85%)</span>
              </span>
              <span style={{ display: 'flex', alignItems: 'center', gap: '5px' }}>
                <span style={{ width: '12px', height: '12px', borderRadius: '50%', background: '#f43f5e' }} />
                <span>ONT Overload (&gt;85% - Upgrade Diperlukan)</span>
              </span>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <span>Lingkaran Transparan: <strong>Radius Jangkauan Sinyal WiFi (80-140m)</strong></span>
            </div>
          </div>
        </div>
      )}

      {/* LIST / CARDS VIEW */}
      {viewMode === 'list' && (
        <>
          {devices.length === 0 && (
            <div className="glass-card" style={{ 
              textAlign: 'center', 
              padding: '48px 24px', 
              border: '1px dashed var(--border-active)',
              borderRadius: 'var(--radius-lg)',
              background: 'rgba(15, 23, 42, 0.4)',
              marginBottom: '20px'
            }}>
              <Network size={44} style={{ color: 'var(--accent-cyan)', margin: '0 auto 14px', opacity: 0.7 }} />
              <h4 style={{ fontSize: '16px', fontWeight: 700, color: '#fff', marginBottom: '8px' }}>
                Belum Ada Perangkat OLT / ONT Terpasang
              </h4>
              <p style={{ fontSize: '13px', maxWidth: '520px', margin: '0 auto 20px', color: 'var(--text-secondary)', lineHeight: '1.6' }}>
                Topologi fiber optik masih kosong. Tambahkan perangkat Core OLT dan ONT distribusi untuk memetakan jaringan FTTH, redaman optik, serta radius jangkauan WiFi.
              </p>
              <button 
                className="btn btn-primary"
                onClick={() => setAddModalOpen(true)}
                style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', padding: '10px 20px' }}
              >
                <PlusCircle size={16} />
                <span>+ Tambah Perangkat OLT / ONT Baru</span>
              </button>
            </div>
          )}

          {/* OLT Core Equipment Card */}
          {olts.map(olt => (
            <div key={olt.id} className="glass-card" style={{
              marginBottom: '20px',
              padding: '16px 20px',
              border: '1px solid rgba(0, 210, 211, 0.3)',
              background: 'linear-gradient(90deg, rgba(0, 210, 211, 0.05) 0%, rgba(9, 132, 227, 0.03) 100%)'
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '10px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                  <div style={{
                    width: '42px',
                    height: '42px',
                    borderRadius: '8px',
                    background: 'rgba(0, 210, 211, 0.15)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: 'var(--accent-cyan)'
                  }}>
                    <Server size={22} />
                  </div>
                  <div>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                      <h3 style={{ fontSize: '16px', fontWeight: 800, color: '#fff' }}>
                        {olt.name}
                      </h3>
                      <span className="tag tag-cyan" style={{ fontSize: '10px' }}>
                        CORE OLT
                      </span>
                    </div>
                    <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginTop: '2px' }}>
                      Model: <strong>{olt.model || 'GPON/EPON 8-Port'}</strong> • SN: {olt.sn} • Koordinat: {olt.lat}, {olt.lng}
                    </p>
                  </div>
                </div>

                <div style={{ display: 'flex', alignItems: 'center', gap: '14px', fontSize: '12px' }}>
                  <div style={{ textAlign: 'right' }}>
                    <span style={{ color: 'var(--text-muted)' }}>Kapasitas Uplink:</span>
                    <div style={{ color: 'var(--accent-emerald)', fontWeight: 700 }}>2.5 Gbps GPON</div>
                  </div>

                  <a
                    href={`http://${olt.vpn_ip || '10.10.10.50'}`}
                    target="_blank"
                    rel="noreferrer"
                    className="btn btn-secondary btn-sm"
                    style={{ borderColor: 'var(--accent-cyan)', color: 'var(--accent-cyan)' }}
                  >
                    <ExternalLink size={13} />
                    <span>Web GUI OLT ({olt.vpn_ip || '10.10.10.50'})</span>
                  </a>
                </div>
              </div>
            </div>
          ))}

          {/* Filter & Subheader */}
          <div style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            marginBottom: '16px',
            flexWrap: 'wrap',
            gap: '10px'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
              <Radio size={16} color="var(--accent-cyan)" />
              <h3 style={{ fontSize: '15px', fontWeight: 800, color: '#fff' }}>
                Daftar ONT Terpetakan ({filteredOnts.length} Titik)
              </h3>
            </div>

            <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap' }}>
              <select
                className="filter-select"
                value={filterStatus}
                onChange={e => setFilterStatus(e.target.value)}
                style={{ padding: '5px 10px', fontSize: '12px' }}
              >
                <option value="all">Semua Kondisi</option>
                <option value="upgrade">🔥 Perlu Upgrade Kritis (&gt;85%)</option>
                <option value="warning">⚠️ Perlu Dipantau (65-85%)</option>
                <option value="optimal">🟢 Optimal (&lt;65%)</option>
              </select>
            </div>
          </div>

          {/* ONT Cards Grid */}
          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(360px, 1fr))',
            gap: '16px'
          }}>
            {filteredOnts.map(ont => {
              const isCritical = ont.status_level === 'critical';
              const isWarning = ont.status_level === 'warning';

              const userBarColor = isCritical 
                ? 'var(--accent-rose)' 
                : isWarning 
                ? 'var(--accent-amber)' 
                : 'var(--accent-emerald)';

              return (
                <div 
                  key={ont.id}
                  className="glass-card"
                  style={{
                    padding: '18px',
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'space-between',
                    border: isCritical 
                      ? '1px solid var(--accent-rose)' 
                      : isWarning 
                      ? '1px solid var(--accent-amber)' 
                      : '1px solid var(--border-subtle)',
                    boxShadow: isCritical ? '0 0 16px rgba(244, 63, 94, 0.15)' : 'none',
                    position: 'relative'
                  }}
                >
                  <div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
                      <div>
                        <h4 style={{ fontSize: '15px', fontWeight: 800, color: '#fff' }}>
                          {ont.name}
                        </h4>
                        <p style={{ fontSize: '11.5px', color: 'var(--text-muted)', marginTop: '2px' }}>
                          {ont.location} • {ont.pon_port} • Node: <strong>{ont.router_session}</strong>
                        </p>
                      </div>

                      <span className={`tag ${isCritical ? 'tag-rose' : isWarning ? 'tag-amber' : 'tag-emerald'}`} style={{ fontSize: '10px' }}>
                        {isCritical ? 'OVERLOAD' : isWarning ? 'WARNING' : 'NORMAL'}
                      </span>
                    </div>

                    <div style={{ display: 'flex', gap: '6px', flexWrap: 'wrap', marginBottom: '14px' }}>
                      <span className="tag" style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-secondary)', fontSize: '10.5px' }}>
                        Model: {ont.model || 'ZTE F609'}
                      </span>
                      <span className="tag" style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--accent-cyan)', fontSize: '10.5px' }}>
                        Coverage: {ont.coverage_radius_meters || 100}m
                      </span>
                      <span className="tag" style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-muted)', fontSize: '10.5px' }}>
                        {ont.lat}, {ont.lng}
                      </span>
                    </div>

                    {/* Meter 1: Active Users */}
                    <div style={{
                      background: 'rgba(0,0,0,0.25)',
                      padding: '10px 12px',
                      borderRadius: 'var(--radius-sm)',
                      marginBottom: '10px'
                    }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px', fontSize: '12px' }}>
                        <span style={{ color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <Users size={13} color="var(--accent-blue)" />
                          <span>Beban Pengguna Terhubung:</span>
                        </span>
                        <span style={{ fontWeight: 800, color: userBarColor }}>
                          {ont.active_users} / {ont.max_users_capacity} User ({ont.user_utilization_percent}%)
                        </span>
                      </div>

                      <div style={{ height: '6px', background: 'rgba(255,255,255,0.1)', borderRadius: '3px', overflow: 'hidden' }}>
                        <div style={{
                          width: `${Math.min(ont.user_utilization_percent, 100)}%`,
                          height: '100%',
                          background: userBarColor,
                          transition: 'width 0.3s ease'
                        }} />
                      </div>
                    </div>

                    {/* Meter 2: Bandwidth */}
                    <div style={{
                      background: 'rgba(0,0,0,0.25)',
                      padding: '10px 12px',
                      borderRadius: 'var(--radius-sm)',
                      marginBottom: '14px'
                    }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px', fontSize: '12px' }}>
                        <span style={{ color: 'var(--text-secondary)', display: 'flex', alignItems: 'center', gap: '4px' }}>
                          <Zap size={13} color="var(--accent-cyan)" />
                          <span>Penggunaan Bandwidth:</span>
                        </span>
                        <span style={{ fontWeight: 800, color: '#fff' }}>
                          {ont.current_bandwidth_mbps} Mbps / {ont.max_bandwidth_mbps} Mbps ({ont.bw_utilization_percent}%)
                        </span>
                      </div>

                      <div style={{ height: '6px', background: 'rgba(255,255,255,0.1)', borderRadius: '3px', overflow: 'hidden' }}>
                        <div style={{
                          width: `${Math.min(ont.bw_utilization_percent, 100)}%`,
                          height: '100%',
                          background: 'linear-gradient(90deg, #00d2d3, #0984e3)',
                          transition: 'width 0.3s ease'
                        }} />
                      </div>
                    </div>

                    {/* Upgrade Advisor */}
                    <div style={{
                      padding: '8px 12px',
                      borderRadius: 'var(--radius-sm)',
                      background: isCritical 
                        ? 'rgba(244, 63, 94, 0.12)' 
                        : isWarning 
                        ? 'rgba(245, 158, 11, 0.1)' 
                        : 'rgba(16, 185, 129, 0.08)',
                      border: `1px solid ${isCritical ? 'rgba(244, 63, 94, 0.3)' : isWarning ? 'rgba(245, 158, 11, 0.25)' : 'rgba(16, 185, 129, 0.2)'}`,
                      fontSize: '11.5px',
                      lineHeight: '1.4',
                      color: isCritical ? '#fb7185' : isWarning ? '#fcd34d' : '#34d399',
                      marginBottom: '16px'
                    }}>
                      {ont.recommendation}
                    </div>
                  </div>

                  {/* Actions */}
                  <div style={{
                    display: 'flex',
                    gap: '8px',
                    borderTop: '1px solid var(--border-subtle)',
                    paddingTop: '12px'
                  }}>
                    <a
                      href={`https://maps.google.com/?q=${ont.lat},${ont.lng}`}
                      target="_blank"
                      rel="noreferrer"
                      className="btn btn-secondary btn-sm"
                      style={{ padding: '6px 10px', fontSize: '11.5px' }}
                      title="Lihat di Google Maps"
                    >
                      <MapPin size={12} color="var(--accent-cyan)" />
                      <span>Maps</span>
                    </a>

                    <a
                      href={`http://${ont.vpn_ip}`}
                      target="_blank"
                      rel="noreferrer"
                      className="btn btn-secondary btn-sm"
                      style={{ flex: 1, justifyContent: 'center', fontSize: '11.5px' }}
                      title="Buka Web GUI ONT"
                    >
                      <ExternalLink size={12} />
                      <span>Web ONT</span>
                    </a>

                    <button
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleOpenEdit(ont)}
                      style={{ padding: '6px 10px' }}
                      title="Edit Konfigurasi ONT"
                    >
                      <Edit3 size={13} />
                    </button>

                    <button
                      className="btn btn-secondary btn-sm"
                      onClick={() => handleDelete(ont)}
                      style={{ padding: '6px 10px', color: 'var(--accent-rose)', borderColor: 'rgba(244, 63, 94, 0.3)' }}
                      title="Hapus ONT"
                    >
                      <Trash2 size={13} />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </>
      )}

      {/* Modal Add / Edit ONT */}
      {showModal && (
        <div style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.75)',
          backdropFilter: 'blur(4px)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          zIndex: 10000,
          padding: '20px'
        }}>
          <div className="glass-card" style={{
            width: '100%',
            maxWidth: '560px',
            padding: '24px',
            borderRadius: 'var(--radius-lg)',
            border: '1px solid var(--border-active)',
            maxHeight: '90vh',
            overflowY: 'auto'
          }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '18px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                <Radio size={18} color="var(--accent-cyan)" />
                <h3 style={{ fontSize: '17px', fontWeight: 800, color: '#fff' }}>
                  {modalMode === 'create' ? 'Tambah Titik ONT & Koordinat Coverage' : `Edit ONT: ${formData.name}`}
                </h3>
              </div>
              <button 
                onClick={() => setShowModal(false)}
                style={{ background: 'none', border: 'none', color: 'var(--text-muted)', cursor: 'pointer' }}
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                    Nama Titik ONT / Cluster *
                  </label>
                  <input
                    type="text"
                    className="input-field"
                    value={formData.name}
                    onChange={e => setFormData({ ...formData, name: e.target.value })}
                    placeholder="misal: ONT ZTE F609 - Hamadi Timur RT 01"
                    required
                    style={{ width: '100%' }}
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Model / Tipe Hardware
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={formData.model}
                      onChange={e => setFormData({ ...formData, model: e.target.value })}
                      placeholder="misal: ZTE F609 / Huawei HG8245H"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Serial Number (SN)
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={formData.sn}
                      onChange={e => setFormData({ ...formData, sn: e.target.value })}
                      placeholder="misal: ZTEG12345678"
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                {/* Coordinates & Coverage Radius */}
                <div style={{
                  padding: '12px',
                  background: 'rgba(0, 210, 211, 0.05)',
                  border: '1px dashed rgba(0, 210, 211, 0.3)',
                  borderRadius: 'var(--radius-sm)',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '10px'
                }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 700, color: 'var(--accent-cyan)' }}>
                    <MapPin size={14} />
                    <span>Geospasial GIS (Google Maps / Earth) &amp; Coverage Radius</span>
                  </div>

                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '10px' }}>
                    <div>
                      <label style={{ display: 'block', fontSize: '11px', color: 'var(--text-secondary)', marginBottom: '3px' }}>
                        Latitude
                      </label>
                      <input
                        type="number"
                        step="0.0001"
                        className="input-field"
                        value={formData.lat}
                        onChange={e => setFormData({ ...formData, lat: parseFloat(e.target.value) })}
                        placeholder="-2.5640"
                        style={{ width: '100%', fontSize: '12px' }}
                      />
                    </div>
                    <div>
                      <label style={{ display: 'block', fontSize: '11px', color: 'var(--text-secondary)', marginBottom: '3px' }}>
                        Longitude
                      </label>
                      <input
                        type="number"
                        step="0.0001"
                        className="input-field"
                        value={formData.lng}
                        onChange={e => setFormData({ ...formData, lng: parseFloat(e.target.value) })}
                        placeholder="140.7070"
                        style={{ width: '100%', fontSize: '12px' }}
                      />
                    </div>
                    <div>
                      <label style={{ display: 'block', fontSize: '11px', color: 'var(--text-secondary)', marginBottom: '3px' }}>
                        Radius Sinyal (Meter)
                      </label>
                      <input
                        type="number"
                        className="input-field"
                        value={formData.coverage_radius_meters}
                        onChange={e => setFormData({ ...formData, coverage_radius_meters: parseInt(e.target.value) || 100 })}
                        placeholder="100"
                        style={{ width: '100%', fontSize: '12px' }}
                      />
                    </div>
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Induk OLT
                    </label>
                    <select
                      className="filter-select"
                      value={formData.olt_id}
                      onChange={e => setFormData({ ...formData, olt_id: e.target.value })}
                      style={{ width: '100%' }}
                    >
                      {olts.map(o => (
                        <option key={o.id} value={o.id}>{o.name}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      PON Port
                    </label>
                    <select
                      className="filter-select"
                      value={formData.pon_port}
                      onChange={e => setFormData({ ...formData, pon_port: e.target.value })}
                      style={{ width: '100%' }}
                    >
                      <option value="PON 1">PON 1</option>
                      <option value="PON 2">PON 2</option>
                      <option value="PON 3">PON 3</option>
                      <option value="PON 4">PON 4</option>
                      <option value="PON 5">PON 5</option>
                      <option value="PON 6">PON 6</option>
                      <option value="PON 7">PON 7</option>
                      <option value="PON 8">PON 8</option>
                    </select>
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Node Router Terkait
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={formData.router_session}
                      onChange={e => setFormData({ ...formData, router_session: e.target.value })}
                      placeholder="misal: Dolphin-Hamadi"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      IP Address VPN ONT
                    </label>
                    <input
                      type="text"
                      className="input-field"
                      value={formData.vpn_ip}
                      onChange={e => setFormData({ ...formData, vpn_ip: e.target.value })}
                      placeholder="10.10.10.101"
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Kapasitas Maks User (Rekomendasi)
                    </label>
                    <input
                      type="number"
                      className="input-field"
                      value={formData.max_users_capacity}
                      onChange={e => setFormData({ ...formData, max_users_capacity: Number(e.target.value) })}
                      placeholder="25"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                      Kapasitas Bandwidth Link (Mbps)
                    </label>
                    <input
                      type="number"
                      className="input-field"
                      value={formData.max_bandwidth_mbps}
                      onChange={e => setFormData({ ...formData, max_bandwidth_mbps: Number(e.target.value) })}
                      placeholder="100"
                      style={{ width: '100%' }}
                    />
                  </div>
                </div>

                <div>
                  <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: 'var(--text-secondary)', marginBottom: '4px' }}>
                    Lokasi Fisik / Catatan Tiang Distribusi
                  </label>
                  <input
                    type="text"
                    className="input-field"
                    value={formData.location}
                    onChange={e => setFormData({ ...formData, location: e.target.value })}
                    placeholder="misal: Tiang Distribusi RT 02 Hamadi"
                    style={{ width: '100%' }}
                  />
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '10px' }}>
                  <button 
                    type="button" 
                    className="btn btn-secondary"
                    onClick={() => setShowModal(false)}
                    disabled={actionLoading}
                  >
                    Batal
                  </button>
                  <button 
                    type="submit" 
                    className="btn btn-primary"
                    disabled={actionLoading}
                    style={{
                      background: 'linear-gradient(135deg, #00d2d3 0%, #0984e3 100%)'
                    }}
                  >
                    {actionLoading ? 'Menyimpan...' : 'Simpan Perangkat'}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Leaflet Custom Styles */}
      <style>{`
        .leaflet-popup-content-wrapper {
          background: #090c10 !important;
          border: 1px solid rgba(0, 210, 211, 0.4) !important;
          border-radius: 8px !important;
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.8) !important;
          padding: 6px !important;
        }
        .leaflet-popup-tip {
          background: #090c10 !important;
          border: 1px solid rgba(0, 210, 211, 0.4) !important;
        }
        .gis-tooltip {
          background: rgba(9, 12, 16, 0.9) !important;
          border: 1px solid rgba(0, 210, 211, 0.3) !important;
          color: #fff !important;
          font-family: 'Outfit', sans-serif !important;
          font-size: 11px !important;
          border-radius: 4px !important;
          box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
        }
      `}</style>
    </div>
  );
}
