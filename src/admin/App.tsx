import { useState } from 'react';
import { OV25Logo } from './components/OV25Logo';
import { TabNav } from './components/TabNav';
import { Dashboard } from './pages/Dashboard';
import { GlobalSettings } from './pages/GlobalSettings';
import { ConfiguratorSetupPage } from './pages/ConfiguratorSetup';
import { SwatchSettings } from './pages/SwatchSettings';
import { SettingsProvider } from './context/SettingsContext';

declare global {
  interface Window {
    ov25Admin: {
      restBase: string;
      nonce: string;
      apiKey: string;
      privateApiKey: string;
      orgName: string;
      ov25LinkBaseUrl: string;
      ov25StoreUrl: string;
      ov25LinkState: string;
      configuratorConfig: Record<string, unknown>;
      version: string;
      settings: Record<string, string>;
    };
  }
}

const TABS = [
  { id: 'dashboard', label: 'Dashboard' },
  { id: 'settings', label: 'Settings' },
  { id: 'configurator', label: 'Configurator' },
  { id: 'swatches', label: 'Swatches' },
] as const;

type TabId = typeof TABS[number]['id'];

export default function App() {
  const [activeTab, setActiveTab] = useState<TabId>('dashboard');

  return (
    <SettingsProvider>
      <div className="ov25-admin">
        <OV25Logo />
        <TabNav tabs={TABS} activeTab={activeTab} onTabChange={setActiveTab} />
        <div className="ov25-admin-content">
          {activeTab === 'dashboard' && <Dashboard />}
          {activeTab === 'settings' && <GlobalSettings />}
          {activeTab === 'configurator' && <ConfiguratorSetupPage />}
          {activeTab === 'swatches' && <SwatchSettings />}
        </div>
      </div>
    </SettingsProvider>
  );
}
