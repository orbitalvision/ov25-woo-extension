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
    { key: 'apiKey', label: 'Public API Key', type: 'text', placeholder: '' },
    { key: 'privateApiKey', label: 'Private API Key', type: 'password', placeholder: '' },
    { key: 'gallerySelector', label: 'Gallery Selector', type: 'text', placeholder: '.woocommerce-product-gallery' },
    { key: 'variantsSelector', label: 'Variants Selector', type: 'text', placeholder: '[data-ov25-variants]' },
    { key: 'priceSelector', label: 'Price Selector', type: 'text', placeholder: '[data-ov25-price]' },
    { key: 'swatchesSelector', label: 'Swatches Selector', type: 'text', placeholder: '[data-ov25-swatches]' },
    { key: 'configureButtonSelector', label: 'Configure Button Selector', type: 'text', placeholder: '[data-ov25-configure-button]' },
    { key: 'logoURL', label: 'Logo URL', type: 'text', placeholder: 'https://...' },
    { key: 'mobileLogoURL', label: 'Mobile Logo URL', type: 'text', placeholder: 'https://...' },
    { key: 'customCSS', label: 'Custom CSS', type: 'textarea', placeholder: '' },
  ];

  return (
    <div className="ov25-page">
      <h2>Global Settings</h2>
      {error && <div className="ov25-error">{error}</div>}
      <div className="ov25-form">
        {fields.map(({ key, label, type, placeholder }) => (
          <div key={key} className="ov25-field">
            <label htmlFor={`ov25-${key}`}>{label}</label>
            {type === 'textarea' ? (
              <textarea
                id={`ov25-${key}`}
                value={(merged[key] as string) || ''}
                onChange={(e) => handleChange(key, e.target.value)}
                placeholder={placeholder}
                rows={4}
              />
            ) : (
              <input
                id={`ov25-${key}`}
                type={type}
                value={(merged[key] as string) || ''}
                onChange={(e) => handleChange(key, e.target.value)}
                placeholder={placeholder}
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
