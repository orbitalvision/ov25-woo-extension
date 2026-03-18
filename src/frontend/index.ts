import * as OV25 from 'ov25-ui-react18';

declare global {
    interface Window {
        ov25Settings: {
            // Legacy flat settings
            logoURL: string;
            mobileLogoURL: string;
            autoCarousel: boolean;
            deferThreeD: boolean;
            showOptional: boolean;
            hideAr: boolean;
            useInlineVariantControls: boolean;
            useSimpleConfigureButton: boolean;
            images: string[];
            gallerySelector: string;
            variantsSelector: string;
            configureButtonSelector: string;
            swatchesSelector: string;
            priceSelector: string;
            customCSS: string;
            swatchProductId?: string;
            restBase?: string;
            createSwatchCartUrl?: string;
            // New JSON config
            configuratorConfig?: Record<string, SerializableInjectConfig>;
            productConfig?: Record<string, SerializableInjectConfig> | null;
            useCustomConfig?: boolean;
        };
        ov25GenerateThumbnail: () => Promise<string>;
        ov25OpenConfigurator?: () => void;
    }
}

interface SerializableInjectConfig {
    selectors?: Record<string, string | { selector: string; replace: boolean }>;
    carousel?: { desktop: string; mobile: string; maxImages?: number | { desktop: number; mobile: number } };
    configurator?: {
        displayMode: { desktop: string; mobile: string };
        triggerStyle?: { desktop: string; mobile: string };
        variants?: { displayMode: { desktop: string; mobile: string }; useSimpleVariantsSelector?: boolean };
    };
    flags?: Record<string, boolean>;
    branding?: { logoURL?: string; mobileLogoURL?: string; cssString?: string };
    [key: string]: unknown;
}

interface PricePayload {
    formattedPrice: string;
    formattedSubtotal: string;
    totalPrice: number;
    subtotal: number;
    discount: { amount: number; formattedAmount: string; percentage: number };
    [key: string]: unknown;
}

interface SkuPayload {
    skuString: string;
    skuMap?: Record<string, string>;
    [key: string]: unknown;
}

export interface Swatch {
    name: string;
    option: string;
    manufacturerId: string;
    description: string;
    thumbnail: {
        blurHash: string;
        thumbnail: string;
        miniThumbnails: { large: string; medium: string; small: string };
    };
}

export type SwatchRulesData = {
    freeSwatchLimit: number;
    canExeedFreeLimit: boolean;
    pricePerSwatch: number;
    minSwatches: number;
    maxSwatches: number;
    enabled: boolean;
};

/**
 * Deep merge source into target. Arrays are replaced, not merged.
 */
function deepMerge<T extends Record<string, unknown>>(target: T, source: Partial<T>): T {
    const result = { ...target };
    for (const key of Object.keys(source) as (keyof T)[]) {
        const srcVal = source[key];
        const tgtVal = target[key];
        if (srcVal && typeof srcVal === 'object' && !Array.isArray(srcVal) && tgtVal && typeof tgtVal === 'object' && !Array.isArray(tgtVal)) {
            (result as Record<string, unknown>)[key as string] = deepMerge(tgtVal as Record<string, unknown>, srcVal as Record<string, unknown>);
        } else if (srcVal !== undefined) {
            (result as Record<string, unknown>)[key as string] = srcVal;
        }
    }
    return result;
}

/**
 * Determine layout type from product link string.
 */
function getLayoutType(productLink: string): 'snap2' | 'standard' {
    return productLink.startsWith('snap2/') ? 'snap2' : 'standard';
}

/**
 * Build inject config from the new JSON config, falling back to legacy flat settings.
 */
function buildConfig(): Record<string, unknown> {
    const s = window.ov25Settings;
    if (!s) return {};

    const element = document.querySelector('[data-ov25-iframe]');
    if (!element) return {};

    const data = element.getAttribute('data-ov25-iframe') || '';
    const firstSlash = data.indexOf('/');
    const apiKey = firstSlash !== -1 ? data.substring(0, firstSlash) : data;
    const productLink = firstSlash !== -1 ? data.substring(firstSlash + 1) : '';

    const hasJsonConfig = s.configuratorConfig && Object.keys(s.configuratorConfig).length > 0;

    // WooCommerce selectors: manual Global Settings value, or WooCommerce defaults.
    // These always override JSON config selectors (which use platform defaults like .configurator-container).
    const selectorOverrides: Record<string, unknown> = {
        gallerySelector: { id: s.gallerySelector || '.woocommerce-product-gallery', replace: true },
        variantsSelector: s.variantsSelector || '[data-ov25-variants]',
        priceSelector: s.priceSelector || '[data-ov25-price]',
        swatchesSelector: s.swatchesSelector || '[data-ov25-swatches]',
    };
    if (s.configureButtonSelector?.trim()) {
        selectorOverrides.configureButtonSelector = { id: s.configureButtonSelector.trim(), replace: true };
    }

    if (hasJsonConfig && s.configuratorConfig) {
        const layoutType = getLayoutType(productLink);
        let config = s.configuratorConfig[layoutType] || s.configuratorConfig['standard'] || {};

        if (s.useCustomConfig && s.productConfig) {
            const productOverride = s.productConfig[layoutType] || s.productConfig['standard'];
            if (productOverride) {
                config = deepMerge(config as Record<string, unknown>, productOverride as Record<string, unknown>) as SerializableInjectConfig;
            }
        }

        return {
            apiKey,
            productLink,
            images: s.images || [],
            ...buildInjectFromSerializable(config),
            ...selectorOverrides,
        };
    }

    return {
        apiKey,
        productLink,
        gallerySelector: selectorOverrides.gallerySelector || { id: '.woocommerce-product-gallery', replace: true },
        variantsSelector: selectorOverrides.variantsSelector || '[data-ov25-variants]',
        ...(s.useSimpleConfigureButton && {
            useSimpleVariantsSelector: true,
            configureButtonSelector: selectorOverrides.configureButtonSelector || { id: '[data-ov25-configure-button]', replace: true },
        }),
        swatchesSelector: selectorOverrides.swatchesSelector || '[data-ov25-swatches]',
        priceSelector: selectorOverrides.priceSelector || '[data-ov25-price]',
        images: s.images || [],
        logoURL: s.logoURL || '',
        mobileLogoURL: s.mobileLogoURL || undefined,
        carouselSelector: s.autoCarousel || false,
        deferThreeD: s.deferThreeD || false,
        showOptional: s.showOptional || false,
        hideAr: s.hideAr || false,
        configuratorDisplayMode: s.useInlineVariantControls ? 'inline' : 'drawer',
        cssString: s.customCSS || '',
    };
}

/**
 * Convert SerializableInjectConfig to the flat shape expected by OV25.injectConfigurator.
 */
function buildInjectFromSerializable(config: SerializableInjectConfig): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    if (config.selectors) {
        for (const [key, val] of Object.entries(config.selectors)) {
            if (typeof val === 'object' && val !== null && 'selector' in val) {
                result[key === 'gallery' ? 'gallerySelector' : `${key}Selector`] = { id: val.selector, replace: val.replace };
            } else if (typeof val === 'string') {
                result[key === 'gallery' ? 'gallerySelector' : `${key}Selector`] = val;
            }
        }
    }

    if (config.carousel) {
        result['carouselSelector'] = config.carousel.desktop !== 'none';
    }

    if (config.configurator) {
        const dm = config.configurator.displayMode;
        if (dm) {
            result['configuratorDisplayMode'] = dm.desktop === 'inline' ? 'inline' : dm.desktop === 'sheet' ? 'drawer' : dm.desktop;
        }
        if (config.configurator.variants?.useSimpleVariantsSelector) {
            result['useSimpleVariantsSelector'] = true;
        }
    }

    if (config.flags) {
        for (const [key, val] of Object.entries(config.flags)) {
            result[key] = val;
        }
    }

    if (config.branding) {
        if (config.branding.logoURL) result['logoURL'] = config.branding.logoURL;
        if (config.branding.mobileLogoURL) result['mobileLogoURL'] = config.branding.mobileLogoURL;
        if (config.branding.cssString) result['cssString'] = config.branding.cssString;
    }

    return result;
}

const doInject = () => {
    const config = buildConfig();
    if (!config.apiKey) return;

    OV25.injectConfigurator({
        ...config,
        addToBasketFunction: async () => {
            const form = document.querySelector('form.cart');
            if (!form) return;
            try {
                const screenshotUrl = await window.ov25GenerateThumbnail();
                const ensureField = (name: string): HTMLInputElement => {
                    let field = form.querySelector(`input[name="${name}"]`) as HTMLInputElement | null;
                    if (!field) {
                        field = Object.assign(document.createElement('input'), { type: 'hidden', name }) as HTMLInputElement;
                        form.appendChild(field);
                    }
                    return field;
                };
                ensureField('ov25-thumbnail').value = screenshotUrl;
                const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
                if (submitButton) submitButton.click();
            } catch (error) {
                console.error('Failed to generate thumbnail:', error);
                const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
                if (submitButton) submitButton.click();
            }
        },
        buySwatchesFunction: async (swatches: Swatch[], rules: SwatchRulesData) => {
            if (!swatches.length || !rules.enabled) return;
            try {
                const checkoutUrl = await createSwatchOnlyCart(swatches, rules);
                window.location.href = checkoutUrl || window.location.origin + '/checkout/';
            } catch (error) {
                console.error('Failed to create swatch cart:', error);
            }
        },
    } as Parameters<typeof OV25.injectConfigurator>[0]);
};

if (window.ov25Settings?.useSimpleConfigureButton) {
    if (document.readyState !== 'loading') doInject();
    else document.addEventListener('DOMContentLoaded', () => doInject());
} else {
    doInject();
}

function runSimpleConfigureButton() {
    if (!window.ov25Settings?.useSimpleConfigureButton) return;
    const selector = window.ov25Settings?.configureButtonSelector?.trim() || '[data-ov25-configure-button]';
    const container = document.querySelector(selector);
    if (!container) return;

    const style = document.createElement('style');
    style.setAttribute('data-ov25-configure-button-styles', '');
    style.textContent = `
        .ov25-configure-button {
            background: #22c55e !important;
            color: #fff !important;
            border: none;
            padding: 0.6em 1.2em;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            border-radius: 4px;
        }
        .ov25-configure-button:hover {
            background: #16a34a !important;
        }
    `;
    document.head.appendChild(style);

    const customCSS = (window.ov25Settings?.customCSS || '').trim();
    if (customCSS) {
        const customStyle = document.createElement('style');
        customStyle.setAttribute('data-ov25-custom-css', '');
        customStyle.textContent = customCSS;
        document.head.appendChild(customStyle);
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ov25-configure-button';
    button.textContent = 'CONFIGURE';
    button.onclick = () => window.ov25OpenConfigurator?.();
    container.innerHTML = '';
    container.appendChild(button);
}

if (document.readyState !== 'loading') runSimpleConfigureButton();
else document.addEventListener('DOMContentLoaded', runSimpleConfigureButton);

// Replace add to cart button
document.addEventListener('DOMContentLoaded', () => {
    const ov25Element = document.querySelector('[data-ov25-iframe]');
    if (!ov25Element) return;

    const form = document.querySelector('form.cart') as HTMLFormElement;
    if (!form) return;

    const originalButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
    if (!originalButton) return;

    const style = document.createElement('style');
    style.textContent = `
        form.cart button[type="submit"] { display: none !important; }
        .ov25-replacement-button { display: inline-block !important; }
    `;
    document.head.appendChild(style);

    const replacementButton = originalButton.cloneNode(true) as HTMLButtonElement;
    replacementButton.type = 'button';
    replacementButton.className = originalButton.className + ' ov25-replacement-button';
    originalButton.parentNode?.insertBefore(replacementButton, originalButton.nextSibling);

    replacementButton.addEventListener('click', async (event) => {
        event.preventDefault();
        const originalText = replacementButton.textContent;
        replacementButton.disabled = true;
        replacementButton.textContent = 'Generating Preview...';

        try {
            const screenshotUrl = await window.ov25GenerateThumbnail();
            const thumbnailField = document.createElement('input');
            thumbnailField.type = 'hidden';
            thumbnailField.name = 'ov25-thumbnail';
            thumbnailField.value = screenshotUrl;
            form.appendChild(thumbnailField);
            originalButton.click();
        } catch (error) {
            console.error('Failed to generate thumbnail:', error);
            originalButton.click();
        } finally {
            replacementButton.disabled = false;
            replacementButton.textContent = originalText;
        }
    });
});

// Price and SKU bridge via postMessage
document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('message', (ev: MessageEvent) => {
        let { type, payload } = ev.data ?? {};
        if (!payload) return;

        if (typeof payload === 'string') {
            try { payload = JSON.parse(payload); }
            catch { return; }
        }

        if (type === 'CURRENT_PRICE' || type === 'CURRENT_SKU') {
            const form = document.querySelector('form.cart');
            if (!form) return;

            const ensureField = (name: string): HTMLInputElement => {
                let f = form.querySelector(`input[name="${name}"]`) as HTMLInputElement | null;
                if (!f) {
                    f = Object.assign(document.createElement('input'), { type: 'hidden', name }) as HTMLInputElement;
                    form.appendChild(f);
                }
                return f;
            };

            if (type === 'CURRENT_PRICE') {
                const pricePayload = payload as PricePayload;
                ensureField('cfg_price').value = String(pricePayload.totalPrice);
                ensureField('cfg_payload').value = JSON.stringify(payload);
            }

            if (type === 'CURRENT_SKU') {
                const skuPayload = payload as SkuPayload;
                if (skuPayload.skuString) ensureField('cfg_sku').value = skuPayload.skuString;
                if (skuPayload.skuMap) ensureField('cfg_skumap').value = JSON.stringify(skuPayload.skuMap);
            }
        }
    });
});

async function createSwatchOnlyCart(swatches: Swatch[], rules: SwatchRulesData): Promise<string | undefined> {
    const swatchCartData = { swatches, rules, timestamp: Date.now() };
    const restUrl = window.ov25Settings?.createSwatchCartUrl ||
        (window.ov25Settings?.restBase ? `${window.ov25Settings.restBase}ov25/v1/create-swatch-cart` : null) ||
        window.location.origin + '/?rest_route=/ov25/v1/create-swatch-cart';

    const response = await fetch(restUrl, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ swatch_data: JSON.stringify(swatchCartData) }),
    });

    if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || `Failed to create swatch cart: ${response.status}`);
    }

    const result = await response.json();
    return result.checkout_url as string | undefined;
}
