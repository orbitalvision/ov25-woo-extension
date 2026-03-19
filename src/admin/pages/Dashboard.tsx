import { useState, useRef, useCallback } from 'react';
import { useSettingsContext } from '../context/SettingsContext';

const OV25_ORIGIN = (() => {
  try {
    return new URL((window as any).ov25Admin?.ov25LinkBaseUrl || 'https://woocommerce.ov25.ai').origin;
  } catch {
    return 'https://woocommerce.ov25.ai';
  }
})();

export function Dashboard() {
  const admin = window.ov25Admin;
  const { settings, loading, saveSettings, refetch } = useSettingsContext();
  const [linkLoading, setLinkLoading] = useState(false);
  const [disconnectLoading, setDisconnectLoading] = useState(false);
  const linkStateRef = useRef<string | null>(null);

  const apiKey = (settings?.apiKey as string) || '';
  const privateApiKey = (settings?.privateApiKey as string) || '';
  const orgName = (settings?.orgName as string) || '';
  const isLinked = !!(apiKey && privateApiKey);
  const hasApiKey = !!admin?.apiKey || !!apiKey;
  const hasPrivateKey = !!admin?.privateApiKey || !!privateApiKey;

  const handleLinkToOv25 = useCallback(() => {
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
  }, [admin, saveSettings, refetch]);

  const handleDisconnect = useCallback(async () => {
    const baseUrl = admin?.ov25LinkBaseUrl || 'https://woocommerce.ov25.ai';
    const key = privateApiKey || admin?.privateApiKey;
    if (!key) return;
    setDisconnectLoading(true);
    try {
      await fetch(`${baseUrl}/api/woocommerce/link/disconnect`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${key}` },
      });
    } catch {
      // ignore
    }
    try {
      await saveSettings({ apiKey: '', privateApiKey: '', orgName: '' });
      refetch();
    } finally {
      setDisconnectLoading(false);
    }
  }, [admin, privateApiKey, saveSettings, refetch]);

  return (
    <div className="ov25-page">
      <h2>Dashboard</h2>
      {loading && <p className="ov25-muted">Loading…</p>}
      {!loading && (
        <>
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
                <p className="ov25-muted">Connect your store to OV25 to get your API keys.</p>
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
          <div className="ov25-cards">
            <div className="ov25-card">
              <h3>API Key</h3>
              <p className={hasApiKey ? 'ov25-status--ok' : 'ov25-status--warn'}>
                {hasApiKey ? 'Configured' : 'Not set'}
              </p>
            </div>
            <div className="ov25-card">
              <h3>Private API Key</h3>
              <p className={hasPrivateKey ? 'ov25-status--ok' : 'ov25-status--warn'}>
                {hasPrivateKey ? 'Configured' : 'Not set'}
              </p>
            </div>
            <div className="ov25-card">
              <h3>Version</h3>
              <p>{admin?.version || 'Unknown'}</p>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
