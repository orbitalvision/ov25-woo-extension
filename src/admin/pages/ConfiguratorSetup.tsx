import { useState, useCallback, useMemo, useEffect } from 'react';
import { ConfiguratorSetup, type ConfiguratorSetupPayload } from 'ov25-setup';
import 'ov25-setup/index.css';
import { useSettingsContext } from '../context/SettingsContext';
import { resolveConfiguratorConfig } from '../lib/resolve-configurator-config';
import {
  mergeConfiguratorPayloadWithStoredFormState,
  syncConfiguratorFormStateFromSavedJson,
} from '../lib/configurator-setup-local-storage';

export function ConfiguratorSetupPage() {
  const [open, setOpen] = useState(false);
  const [saved, setSaved] = useState(false);
  const [editorMountKey, setEditorMountKey] = useState<string | null>(null);
  const admin = window.ov25Admin;
  const { settings, saveSettings } = useSettingsContext();
  const rawSavedConfig = useMemo((): Record<string, unknown> => {
    const resolved = resolveConfiguratorConfig(settings?.configuratorConfig, admin?.configuratorConfig);
    return resolved as Record<string, unknown>;
  }, [settings?.configuratorConfig, admin?.configuratorConfig]);

  useEffect(() => {
    if (!open) {
      setEditorMountKey(null);
      return;
    }
    syncConfiguratorFormStateFromSavedJson(rawSavedConfig);
    const id = requestAnimationFrame(() => {
      setEditorMountKey(`ov25-configurator-setup-${Date.now()}`);
    });
    return () => cancelAnimationFrame(id);
  }, [open, rawSavedConfig]);

  const handleSave = useCallback(async (payload: ConfiguratorSetupPayload) => {
    try {
      const merged = mergeConfiguratorPayloadWithStoredFormState(payload);
      await saveSettings({ configuratorConfig: merged });
      admin.configuratorConfig = merged;
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      console.error('Failed to save configurator config:', err);
    }
  }, [admin, saveSettings]);

  return (
    <div className="ov25-page">
      <h2>Configurator Setup</h2>
      <p style={{ color: '#666', marginBottom: '16px' }}>
        Customise the look and feel of your OV25 configurator. These settings apply globally to all products unless overridden per-product.
      </p>
      {saved && <div className="ov25-success">Settings saved successfully.</div>}
      <button type="button" className="button button-primary button-hero" onClick={() => setOpen(true)}>
        Open Configurator Editor
      </button>

      {open && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 100000, background: '#f0f0f1', display: 'flex', flexDirection: 'column' }}>
          <div style={{
            display: 'flex', alignItems: 'center', gap: '12px',
            padding: '10px 20px', background: '#fff', borderBottom: '1px solid #ddd',
            boxShadow: '0 1px 3px rgba(0,0,0,.08)', flexShrink: 0,
          }}>
            <h2 style={{ margin: 0, fontSize: '16px', flex: 1 }}>Global Configurator Settings</h2>
            {saved && <span style={{ fontSize: '13px', color: '#00a32a', fontWeight: 600 }}>Saved!</span>}
            <button type="button" className="button" onClick={() => setOpen(false)}>Close</button>
          </div>
          <div style={{ flex: 1, overflow: 'hidden', minHeight: 0 }}>
            {editorMountKey && (
              <ConfiguratorSetup
                key={editorMountKey}
                onSave={handleSave}
              />
            )}
          </div>
        </div>
      )}
    </div>
  );
}
