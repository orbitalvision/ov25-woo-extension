# Ov25 Woo Extension

A WooCommmerce Extension inspired by [Create Woo Extension](https://github.com/woocommerce/woocommerce/blob/trunk/packages/js/create-woo-extension/README.md).

This extension uses React 18 and is compatible with the latest WooCommerce versions.

## Getting Started

### Prerequisites

-   [NPM](https://www.npmjs.com/)
-   [Composer](https://getcomposer.org/download/)
-   [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)

### Installation and Build

```
npm install --legacy-peer-deps
npm run build
wp-env start
```

Visit the added page at http://localhost:8888/wp-admin/admin.php?page=wc-admin&path=%2Fexample.

## Update checks

The plugin checks GitHub for updates. No token is required: the plugin works without one. Without a token, GitHub’s API rate limit can cause “update check temporarily unavailable”; you can then install updates from the [releases](https://github.com/orbitalvision/ov25-woo-extension/releases) page.

Optional: to avoid rate limits on your server, copy `github-token.example.php` to `github-token.php`, add a [GitHub token](https://github.com/settings/tokens) with `public_repo`, and keep `github-token.php` out of version control (it is gitignored).

## React 18 Compatibility

This extension includes React 18 compatibility utilities in `src/utils/react-compat.js`. When rendering React components, use the `renderCompatible` function instead of directly using `ReactDOM.render` or `createRoot`:

```javascript
import { renderCompatible } from './utils/react-compat';

// Render a component
const root = renderCompatible(<MyComponent />, document.getElementById('root'));

// Later, to unmount if needed
import { unmountCompatible } from './utils/react-compat';
unmountCompatible(root);
```

This ensures compatibility with both React 17 and React 18 rendering methods.
