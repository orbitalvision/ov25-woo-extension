import { useState } from 'react';
import { useSettings } from '../hooks/useSettings';

export function GlobalSettings() {
  const { settings, loading, saving, error, saveSettings } = useSettings();
  const [localSettings, setLocalSettings] = useState<Record<string, unknown>>({});

  if (loading) return <div className="ov25-page"><p>Loading settings...</p></div>;

  const merged = { ...settings, ...localSettings };

  const handleChange = (key: string, value: string) => {
    setLocalSettings((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    try {
      await saveSettings(localSettings);
      setLocalSettings({});
    } catch { /* error displayed by hook */ }
  };

  const fields = [
    { key: 'apiKey', label: 'Public API Key', type: 'text' },
    { key: 'privateApiKey', label: 'Private API Key', type: 'password' },
    { key: 'gallerySelector', label: 'Gallery Selector', type: 'text' },
    { key: 'variantsSelector', label: 'Variants Selector', type: 'text' },
    { key: 'priceSelector', label: 'Price Selector', type: 'text' },
    { key: 'swatchesSelector', label: 'Swatches Selector', type: 'text' },
    { key: 'configureButtonSelector', label: 'Configure Button Selector', type: 'text' },
    { key: 'logoURL', label: 'Logo URL', type: 'text' },
    { key: 'mobileLogoURL', label: 'Mobile Logo URL', type: 'text' },
    { key: 'customCSS', label: 'Custom CSS', type: 'textarea' },
  ];

  return (
    <div className="ov25-page">
      <h2>Global Settings</h2>
      {error && <div className="ov25-error">{error}</div>}
      <div className="ov25-form">
        {fields.map(({ key, label, type }) => (
          <div key={key} className="ov25-field">
            <label htmlFor={`ov25-${key}`}>{label}</label>
            {type === 'textarea' ? (
              <textarea
                id={`ov25-${key}`}
                value={(merged[key] as string) || ''}
                onChange={(e) => handleChange(key, e.target.value)}
                rows={4}
              />
            ) : (
              <input
                id={`ov25-${key}`}
                type={type}
                value={(merged[key] as string) || ''}
                onChange={(e) => handleChange(key, e.target.value)}
              />
            )}
          </div>
        ))}
        <button
          type="button"
          className="button button-primary"
          onClick={handleSave}
          disabled={saving || Object.keys(localSettings).length === 0}
        >
          {saving ? 'Saving...' : 'Save Settings'}
        </button>
      </div>
    </div>
  );
}
