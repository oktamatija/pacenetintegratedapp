import React, { useState, useEffect, useCallback, useRef } from 'react';
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
import UsersManagement from './pages/UsersManagement';
import ResellerKiosk from './pages/ResellerKiosk';
import Login from './pages/Login';

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [currentUser, setCurrentUser] = useState(null);
  const [userRole, setUserRole] = useState('admin');
  const [userProfile, setUserProfile] = useState({});
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
        const role = json.data.role || 'admin';
        setUserRole(role);
        setUserProfile(json.data);
        setIsReadOnly(Boolean(json.data.is_readonly || role === 'demo' || json.data.user === 'demo'));

        // Default initial page by role
        if (role === 'reseller') {
          setCurrentPage('reseller');
        } else if (role === 'finance') {
          setCurrentPage('reports');
        }
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

  const isFetchingRef = useRef(false);

  // 2. Fetch Multi-Router Live Data
  const fetchRouterStats = useCallback(async () => {
    if (!isAuthenticated) return;
    // Reseller or Finance do not need periodic live router stats polling
    if (userRole === 'reseller' || userRole === 'finance') return;
    if (isFetchingRef.current) return; // Prevent concurrent requests when high traffic causes longer response

    isFetchingRef.current = true;
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
      isFetchingRef.current = false;
      setIsRefreshing(false);
    }
  }, [isAuthenticated, userRole]);

  // Initial & periodic polling (10s interval to prevent API saturation during heavy 300+ Mbps traffic)
  useEffect(() => {
    if (isAuthenticated && userRole !== 'reseller' && userRole !== 'finance') {
      fetchRouterStats();
      const interval = setInterval(fetchRouterStats, 10000); // 10s live polling
      return () => clearInterval(interval);
    }
  }, [isAuthenticated, userRole, fetchRouterStats]);

  // Route Guard: enforce role boundaries
  useEffect(() => {
    if (!isAuthenticated) return;
    if (userRole === 'reseller' && currentPage !== 'reseller') {
      setCurrentPage('reseller');
    } else if (userRole === 'finance' && currentPage !== 'reports') {
      setCurrentPage('reports');
    } else if (userRole === 'staff_noc' && ['user_profiles', 'vouchers', 'generate', 'print', 'reports', 'users_management'].includes(currentPage)) {
      setCurrentPage('dashboard');
    }
  }, [isAuthenticated, userRole, currentPage]);

  // Logout handler
  const handleLogout = async () => {
    try {
      await fetch('/api/auth.php?action=logout');
    } catch (e) {
      console.error(e);
    }
    setIsAuthenticated(false);
    setCurrentUser(null);
    setUserRole('admin');
    setUserProfile({});
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
          const role = typeof authData === 'object' ? (authData.role || 'admin') : 'admin';
          const isRo = typeof authData === 'object' 
            ? Boolean(authData.is_readonly || role === 'demo' || uname === 'demo') 
            : (uname === 'demo');
          setCurrentUser(uname);
          setUserRole(role);
          setUserProfile(authData);
          setIsReadOnly(isRo);

          if (role === 'reseller') {
            setCurrentPage('reseller');
          } else if (role === 'finance') {
            setCurrentPage('reports');
          } else {
            setCurrentPage('dashboard');
          }
          fetchRouterStats();
        }} 
      />
    );
  }

  return (
    <div className="app-container">
      {/* Responsive Sidebar with RBAC */}
      <Sidebar 
        currentPage={currentPage}
        onNavigate={setCurrentPage}
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        currentUser={currentUser}
        userRole={userRole}
        userProfile={userProfile}
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

          {currentPage === 'reseller' && (
            <ResellerKiosk 
              currentUser={currentUser}
              userProfile={userProfile}
              isReadOnly={isReadOnly}
            />
          )}

          {currentPage === 'users_management' && (
            <UsersManagement 
              currentUser={currentUser}
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
