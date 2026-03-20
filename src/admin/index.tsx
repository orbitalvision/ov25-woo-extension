import { createRoot } from 'react-dom/client';
import App from './App';
import { ProductField } from './components/ProductField';
import { SettingsProvider } from './context/SettingsContext';
import './admin.css';

const adminRoot = document.getElementById('ov25-admin-root');
if (adminRoot) {
  createRoot(adminRoot).render(<App />);
}

const productFieldRoot = document.getElementById('ov25-product-field-root');
if (productFieldRoot) {
  createRoot(productFieldRoot).render(
    <SettingsProvider>
      <ProductField
        wooProductId={productFieldRoot.dataset.productId || ''}
        currentLink={productFieldRoot.dataset.currentLink || ''}
        useCustomConfig={productFieldRoot.dataset.useCustomConfig || 'no'}
        customConfig={productFieldRoot.dataset.customConfig || '{}'}
      />
    </SettingsProvider>,
  );
}
