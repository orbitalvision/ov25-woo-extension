import { createRoot } from 'react-dom/client';
import App from './App';
import './admin.css';

const root = document.getElementById('ov25-admin-root');
if (root) {
  createRoot(root).render(<App />);
}
