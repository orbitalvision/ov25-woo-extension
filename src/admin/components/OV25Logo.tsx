export function OV25Logo() {
  return (
    <div className="ov25-gradient" style={{ padding: '16px 24px', marginBottom: '24px', borderRadius: '8px', display: 'flex', alignItems: 'center', gap: '12px' }}>
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" fill="white"/>
        <path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z" fill="white"/>
      </svg>
      <span style={{ color: 'white', fontSize: '20px', fontWeight: 700, letterSpacing: '0.05em' }}>OV25</span>
      <span style={{ color: 'rgba(255,255,255,0.7)', fontSize: '12px', marginLeft: 'auto' }}>
        v{window.ov25Admin?.version || ''}
      </span>
    </div>
  );
}
