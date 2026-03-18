import { useState, useCallback } from 'react';
import { ConfiguratorSetup, type ConfiguratorSetupPayload } from 'ov25-setup';
import 'ov25-setup/index.css';
import { api } from '../lib/api';

export function ConfiguratorSetupPage() {
  const [saved, setSaved] = useState(false);
  const admin = window.ov25Admin;
  const currentConfig = (admin?.configuratorConfig || {}) as ConfiguratorSetupPayload;

  const handleSave = useCallback(async (payload: ConfiguratorSetupPayload) => {
    try {
      await api.saveSettings({ configuratorConfig: payload });
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      console.error('Failed to save configurator config:', err);
    }
  }, []);

  return (
    <div className="ov25-page">
      <h2>Configurator Setup</h2>
      {saved && <div className="ov25-success">Settings saved successfully.</div>}
      <ConfiguratorSetup
        apiKey={admin?.apiKey}
        initialConfig={currentConfig}
        onSave={handleSave}
      />
    </div>
  );
}
