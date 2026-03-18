export function Dashboard() {
  const admin = window.ov25Admin;
  const hasApiKey = !!admin?.apiKey;
  const hasPrivateKey = !!admin?.privateApiKey;

  return (
    <div className="ov25-page">
      <h2>Dashboard</h2>
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
    </div>
  );
}
