/**
 * React compatibility utilities to support both React 17 and React 18
 */
import { createRoot, render, unmountComponentAtNode } from '@wordpress/element';

/**
 * Renders a React component into a DOM node, using createRoot (React 18) or
 * render (React 17) depending on what's available.
 *
 * @param {React.ReactNode} element The React element to render
 * @param {HTMLElement} container The DOM element to render into
 * @return {Object|void} The root instance or void
 */
export function renderCompatible(element, container) {
	// Use createRoot if available (React 18+)
	if (typeof createRoot === 'function') {
		const root = createRoot(container);
		root.render(element);
		return root;
	}
	
	// Fall back to legacy render method (React 17)
	return render(element, container);
}

/**
 * Unmounts a React component from a DOM node, using root.unmount() (React 18)
 * or unmountComponentAtNode (React 17) depending on what's available.
 *
 * @param {Object|HTMLElement} rootOrContainer The root object or DOM container
 */
export function unmountCompatible(rootOrContainer) {
	// If a root object with unmount method is provided (from React 18 createRoot)
	if (rootOrContainer && typeof rootOrContainer.unmount === 'function') {
		rootOrContainer.unmount();
		return;
	}
	
	// Fall back to legacy unmountComponentAtNode (React 17)
	if (typeof unmountComponentAtNode === 'function') {
		unmountComponentAtNode(rootOrContainer);
	}
} 