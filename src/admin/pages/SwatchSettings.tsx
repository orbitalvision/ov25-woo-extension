import { useState } from 'react';
import { useSettingsContext } from '../context/SettingsContext';

export function SwatchSettings() {
  const { settings, loading, saving, error, saveSettings } = useSettingsContext();
  const [local, setLocal] = useState<Record<string, unknown>>({});

  const merged = { ...(settings ?? {}), ...local };

  const handleChange = (key: string, value: string | boolean) => {
    setLocal((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    try {
      await saveSettings(local);
      setLocal({});
    } catch { /* error displayed by hook */ }
  };

  return (
    <div className="ov25-page">
      <h2>Swatch Settings</h2>
      {loading && <p className="ov25-muted">Loading…</p>}
      {error && <div className="ov25-error">{error}</div>}
      {!loading && <div className="ov25-form">
        <div className="ov25-field ov25-field--checkbox">
          <label>
            <input
              type="checkbox"
              checked={(merged.showSwatchesPage as string) === 'yes'}
              onChange={(e) => handleChange('showSwatchesPage', e.target.checked ? 'yes' : 'no')}
            />
            Enable Swatches Page
          </label>
        </div>
        <div className="ov25-field">
          <label htmlFor="ov25-swatchSlug">Page Slug</label>
          <input
            id="ov25-swatchSlug"
            type="text"
            value={(merged.swatchesPageSlug as string) || 'swatches'}
            onChange={(e) => handleChange('swatchesPageSlug', e.target.value)}
          />
        </div>
        <div className="ov25-field">
          <label htmlFor="ov25-swatchTitle">Page Title</label>
          <input
            id="ov25-swatchTitle"
            type="text"
            value={(merged.swatchesPageTitle as string) || 'Swatches'}
            onChange={(e) => handleChange('swatchesPageTitle', e.target.value)}
          />
        </div>
        <div className="ov25-field ov25-field--checkbox">
          <label>
            <input
              type="checkbox"
              checked={(merged.swatchesShowInNav as string) === 'yes'}
              onChange={(e) => handleChange('swatchesShowInNav', e.target.checked ? 'yes' : 'no')}
            />
            Show in Navigation
          </label>
        </div>
        <div className="ov25-field ov25-field--checkbox">
          <label>
            <input
              type="checkbox"
              checked={(merged.swatchesTestMode as string) === 'yes'}
              onChange={(e) => handleChange('swatchesTestMode', e.target.checked ? 'yes' : 'no')}
            />
            Test Mode
          </label>
        </div>
        <button
          type="button"
          className="button button-primary"
          onClick={handleSave}
          disabled={saving || Object.keys(local).length === 0}
        >
          {saving ? 'Saving...' : 'Save Swatch Settings'}
        </button>
      </div>}
    </div>
  );
}
