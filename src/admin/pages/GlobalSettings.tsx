import { useState, useRef, useCallback } from 'react';
import { useSettingsContext } from '../context/SettingsContext';

const OV25_ORIGIN = (() => {
  try {
    return new URL((window as any).ov25Admin?.ov25LinkBaseUrl || 'https://woocommerce.ov25.ai').origin;
  } catch {
    return 'https://woocommerce.ov25.ai';
  }
})();

export function GlobalSettings() {
  const { settings, loading, saving, error, saveSettings, refetch } = useSettingsContext();
  const [localSettings, setLocalSettings] = useState<Record<string, unknown>>({});
  const [linkLoading, setLinkLoading] = useState(false);
  const [disconnectLoading, setDisconnectLoading] = useState(false);
  const linkStateRef = useRef<string | null>(null);

  const merged = { ...(settings ?? {}), ...localSettings };
  const apiKey = String(merged.apiKey ?? '').trim();
  const privateApiKey = String(merged.privateApiKey ?? '').trim();
  const orgName = String(merged.orgName ?? '').trim();
  const isLinked = !!(apiKey && privateApiKey);

  const handleChange = (key: string, value: string | boolean) => {
    setLocalSettings((prev) => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    try {
      await saveSettings(localSettings);
      setLocalSettings({});
    } catch { /* error displayed by hook */ }
  };

  const handleLinkToOv25 = useCallback(() => {
    const admin = (window as any).ov25Admin;
    const baseUrl = admin?.ov25LinkBaseUrl || 'https://woocommerce.ov25.ai';
    const storeUrl = admin?.ov25StoreUrl || '';
    const state = admin?.ov25LinkState || '';
    if (!storeUrl) return;
    linkStateRef.current = state;
    const url = `${baseUrl}/woocommerce/link?${new URLSearchParams({ store_url: storeUrl, state }).toString()}`;
    const w = window.open(url, 'ov25-woo-link', 'width=600,height=700');
    if (!w) {
      alert('Popup blocked. Please allow popups for this site.');
      return;
    }
    setLinkLoading(true);
    const listener = (event: MessageEvent) => {
      if (event.origin !== OV25_ORIGIN) return;
      const d = event.data;
      if (d?.type !== 'ov25-link-complete') return;
      if (d.error) {
        setLinkLoading(false);
        window.removeEventListener('message', listener);
        return;
      }
      const expectedState = linkStateRef.current;
      if (expectedState != null && d.state !== expectedState) {
        setLinkLoading(false);
        window.removeEventListener('message', listener);
        return;
      }
      saveSettings({
        apiKey: d.apiKey ?? '',
        privateApiKey: d.privateApiKey ?? '',
        orgName: d.orgName ?? '',
      }).then(() => {
        w.close();
        refetch();
      }).finally(() => {
        setLinkLoading(false);
        window.removeEventListener('message', listener);
      });
    };
    window.addEventListener('message', listener);
    const checkClosed = setInterval(() => {
      if (w.closed) {
        clearInterval(checkClosed);
        setLinkLoading(false);
        window.removeEventListener('message', listener);
      }
    }, 500);
  }, [saveSettings, refetch]);

  const handleDisconnect = useCallback(async () => {
    const admin = (window as any).ov25Admin;
    const baseUrl = admin?.ov25LinkBaseUrl || 'https://woocommerce.ov25.ai';
    const key = (merged.privateApiKey as string) || (admin?.privateApiKey as string);
    if (!key) return;
    setDisconnectLoading(true);
    try {
      await fetch(`${baseUrl}/api/woocommerce/link/disconnect`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${key}` },
      });
      await saveSettings({ apiKey: '', privateApiKey: '', orgName: '' });
      refetch();
    } catch {
      // still clear locally
      await saveSettings({ apiKey: '', privateApiKey: '', orgName: '' });
      refetch();
    } finally {
      setDisconnectLoading(false);
    }
  }, [merged.privateApiKey, saveSettings, refetch]);

  const fields = [
    { key: 'apiKey', label: 'Public API Key', type: 'text', placeholder: '' },
    { key: 'privateApiKey', label: 'Private API Key', type: 'password', placeholder: '' },
    { key: 'gallerySelector', label: 'Gallery Selector', type: 'text', placeholder: '.woocommerce-product-gallery' },
    { key: 'variantsSelector', label: 'Variants Selector', type: 'text', placeholder: '[data-ov25-variants]' },
    { key: 'priceSelector', label: 'Price Selector', type: 'text', placeholder: '[data-ov25-price]' },
    { key: 'swatchesSelector', label: 'Swatches Selector', type: 'text', placeholder: '[data-ov25-swatches]' },
    { key: 'configureButtonSelector', label: 'Configure Button Selector', type: 'text', placeholder: '[data-ov25-configure-button]' },
  ];

  const useSimpleConfigure = merged.useSimpleConfigureButton === true || merged.useSimpleConfigureButton === 'yes';
  const disableCartFormHiding = merged.disableCartFormHiding === true || merged.disableCartFormHiding === 'yes';
  const useNativeCartSubmit = merged.useNativeCartSubmit === true || merged.useNativeCartSubmit === 'yes';

  return (
    <div className="ov25-page">
      <h2>Global Settings</h2>
      {loading && <p className="ov25-muted">Loading settings…</p>}
      {error && <div className="ov25-error">{error}</div>}
      <p className="ov25-muted" style={{ marginBottom: '1rem' }}>Or enter your API keys manually below.</p>
      <div className="ov25-form ov25-link-section" style={{ marginBottom: '1.5rem' }}>
        {isLinked ? (
          <>
            <p className="ov25-connected">
              Connected as <strong>{orgName || 'OV25'}</strong>
            </p>
            <button
              type="button"
              className="button"
              onClick={handleDisconnect}
              disabled={disconnectLoading}
            >
              {disconnectLoading ? 'Disconnecting…' : 'Disconnect'}
            </button>
          </>
        ) : (
          <>
            <p className="ov25-muted">Connect your store to OV25 to get API keys.</p>
            <button
              type="button"
              className="button button-primary"
              onClick={handleLinkToOv25}
              disabled={linkLoading}
            >
              {linkLoading ? 'Opening…' : 'Link to OV25'}
            </button>
          </>
        )}
      </div>
      <div className="ov25-form">
        <div className="ov25-field">
          <label htmlFor="ov25-use-simple-configure" style={{ display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 600 }}>
            <input
              id="ov25-use-simple-configure"
              type="checkbox"
              checked={useSimpleConfigure}
              onChange={(e) => handleChange('useSimpleConfigureButton', e.target.checked)}
            />
            Use simple configure button (single CONFIGURE control instead of inline variants)
          </label>
        </div>
        <div className="ov25-field">
          <label htmlFor="ov25-disable-cart-form-hiding" style={{ display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 600 }}>
            <input
              id="ov25-disable-cart-form-hiding"
              type="checkbox"
              checked={disableCartFormHiding}
              onChange={(e) => handleChange('disableCartFormHiding', e.target.checked)}
            />
            Disable cart form hiding
          </label>
        </div>
        <div className="ov25-field">
          <label htmlFor="ov25-native-cart-submit" style={{ display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 600 }}>
            <input
              id="ov25-native-cart-submit"
              type="checkbox"
              checked={useNativeCartSubmit}
              onChange={(e) => handleChange('useNativeCartSubmit', e.target.checked)}
            />
            Submit via WooCommerce cart form (native variations)
          </label>
          <p className="ov25-muted" style={{ margin: '0.35rem 0 0 1.5rem', fontSize: '0.9em' }}>
            Add to cart uses a real <code>form.cart</code> POST with OV25 cfg fields and the customer&apos;s chosen{' '}
            <code>attribute_*</code> / variation. Keeps theme and third-party variation UX. Implies showing the cart form (same as disabling form hiding).
          </p>
        </div>
        {fields.map(({ key, label, type, placeholder }) => (
          <div key={key} className="ov25-field">
            <label htmlFor={`ov25-${key}`}>{label}</label>
            {type === 'textarea' ? (
              <textarea
                id={`ov25-${key}`}
                value={typeof merged[key] === 'string' ? merged[key] as string : ''}
                onChange={(e) => handleChange(key, e.target.value)}
                placeholder={placeholder}
                rows={4}
              />
            ) : (
              <input
                id={`ov25-${key}`}
                type={type}
                value={typeof merged[key] === 'string' ? merged[key] as string : ''}
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
