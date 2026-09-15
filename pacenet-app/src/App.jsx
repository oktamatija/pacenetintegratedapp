import React, { useState, useEffect, useCallback } from 'react';
import Navbar from './components/Navbar';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import TrafficMonitor from './pages/TrafficMonitor';
import Vouchers from './pages/Vouchers';
import GenerateVoucher from './pages/GenerateVoucher';
import QuickPrint from './pages/QuickPrint';
import Reports from './pages/Reports';
import VpsResource from './pages/VpsResource';
import RosManager from './pages/RosManager';
import RouterOnboarding from './pages/RouterOnboarding';
import UserProfiles from './pages/UserProfiles';
import OltOntTopology from './pages/OltOntTopology';
import Login from './pages/Login';

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [currentUser, setCurrentUser] = useState(null);
  const [isReadOnly, setIsReadOnly] = useState(false);
  const [authChecking, setAuthChecking] = useState(true);

  const [currentPage, setCurrentPage] = useState('dashboard');
  const [sidebarOpen, setSidebarOpen] = useState(false);

  // Live Multi-Router Data
  const [routerData, setRouterData] = useState(null);
  const [isRefreshing, setIsRefreshing] = useState(false);

  // Voucher print state transfer
  const [vouchersForPrint, setVouchersForPrint] = useState(null);

  // 1. Check Auth Status
  const checkAuth = async () => {
    try {
      const res = await fetch('/api/auth.php?action=check');
      const json = await res.json();
      if (json.success && json.data.authenticated) {
        setIsAuthenticated(true);
        setCurrentUser(json.data.user);
        setIsReadOnly(Boolean(json.data.is_readonly || json.data.role === 'demo' || json.data.user === 'demo'));
      } else {
        setIsAuthenticated(false);
        setIsReadOnly(false);
      }
    } catch (e) {
      console.error('Auth check error', e);
      setIsAuthenticated(false);
      setIsReadOnly(false);
    } finally {
      setAuthChecking(false);
    }
  };

  useEffect(() => {
    checkAuth();
  }, []);

  // 2. Fetch Multi-Router Live Data
  const fetchRouterStats = useCallback(async () => {
    if (!isAuthenticated) return;
    setIsRefreshing(true);
    try {
      const res = await fetch('/api/routers.php');
      const json = await res.json();
      if (json.success) {
        setRouterData(json.data);
      } else if (res.status === 401) {
        setIsAuthenticated(false);
      }
    } catch (e) {
      console.error('Failed to load router live stats', e);
    } finally {
      setIsRefreshing(false);
    }
  }, [isAuthenticated]);

  // Initial & periodic polling
  useEffect(() => {
    if (isAuthenticated) {
      fetchRouterStats();
      const interval = setInterval(fetchRouterStats, 5000); // 5s live polling
      return () => clearInterval(interval);
    }
  }, [isAuthenticated, fetchRouterStats]);

  // Logout handler
  const handleLogout = async () => {
    try {
      await fetch('/api/auth.php?action=logout');
    } catch (e) {
      console.error(e);
    }
    setIsAuthenticated(false);
    setCurrentUser(null);
    setIsReadOnly(false);
  };

  if (authChecking) {
    return (
      <div style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: '#090c10',
        color: '#00d2d3',
        fontFamily: 'var(--font-main)'
      }}>
        <div style={{ textAlign: 'center' }}>
          <div style={{
            width: '40px',
            height: '40px',
            border: '3px solid rgba(0, 210, 211, 0.2)',
            borderTopColor: '#00d2d3',
            borderRadius: '50%',
            animation: 'spin 0.8s linear infinite',
            margin: '0 auto 16px'
          }} />
          <div style={{ fontSize: '14px', fontWeight: 600, letterSpacing: '1px' }}>
            MEMULAI PACENET NOC CONTROLLER...
          </div>
        </div>
        <style>{`
          @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
          }
        `}</style>
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <Login 
        onLoginSuccess={(authData) => {
          setIsAuthenticated(true);
          const uname = typeof authData === 'object' ? (authData.user || 'User') : authData;
          const isRo = typeof authData === 'object' 
            ? Boolean(authData.is_readonly || authData.role === 'demo' || uname === 'demo') 
            : (uname === 'demo');
          setCurrentUser(uname);
          setIsReadOnly(isRo);
          fetchRouterStats();
        }} 
      />
    );
  }

  return (
    <div className="app-container">
      {/* Responsive Sidebar */}
      <Sidebar 
        currentPage={currentPage}
        onNavigate={setCurrentPage}
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        currentUser={currentUser}
        isReadOnly={isReadOnly}
      />

      {/* Main Content Area */}
      <div className="main-wrapper">
        {/* Read-Only Demo Notification Banner */}
        {isReadOnly && (
          <div className="demo-top-banner">
            <span className="demo-badge-tag">
              <span className="pulse-dot warning" style={{ width: '5px', height: '5px' }}></span>
              Demo Read-Only
            </span>
            <span className="demo-banner-text">
              Mode Peninjauan Aktif — Anda dapat melihat seluruh metrik, GIS, dan laporan. Modifikasi data dinonaktifkan.
            </span>
          </div>
        )}

        <Navbar 
          currentPage={currentPage}
          onToggleSidebar={() => setSidebarOpen(prev => !prev)}
          summaryData={routerData?.summary}
          onRefresh={fetchRouterStats}
          isRefreshing={isRefreshing}
          onLogout={handleLogout}
          currentUser={currentUser}
          isReadOnly={isReadOnly}
        />

        <main className="page-content">
          {currentPage === 'dashboard' && (
            <Dashboard 
              data={routerData} 
              isLoading={isRefreshing} 
              onNavigate={setCurrentPage} 
              onRefresh={fetchRouterStats}
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'user_profiles' && (
            <UserProfiles 
              onNavigate={setCurrentPage}
              onQuickPrintProfile={(vList) => {
                setVouchersForPrint(vList);
                setCurrentPage('print');
              }}
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'olt_ont' && (
            <OltOntTopology 
              onNavigate={setCurrentPage}
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'onboarding' && (
            <RouterOnboarding 
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'traffic' && (
            <TrafficMonitor />
          )}

          {currentPage === 'vouchers' && (
            <Vouchers 
              onNavigate={setCurrentPage} 
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'generate' && (
            <GenerateVoucher 
              onNavigate={setCurrentPage}
              setGeneratedForPrint={(batch) => {
                setVouchersForPrint(batch);
              }}
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'print' && (
            <QuickPrint 
              vouchersForPrint={vouchersForPrint} 
              onNavigate={setCurrentPage}
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'reports' && (
            <Reports />
          )}

          {currentPage === 'ros_manager' && (
            <RosManager 
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'system' && (
            <VpsResource />
          )}
        </main>
      </div>
    </div>
  );
}
