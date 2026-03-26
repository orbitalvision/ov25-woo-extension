import * as OV25 from 'ov25-ui-react18';
import type { OnChangePayload } from 'ov25-ui-react18';
import { normalizePricePayload, normalizeSkuPayload } from 'ov25-ui-react18';

declare global {
    interface Window {
        ov25Settings: {
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
            useSimpleConfigureButton?: boolean;
            configuratorConfig?: Record<string, SerializableInjectConfig>;
            productConfig?: Record<string, SerializableInjectConfig> | null;
            useCustomConfig?: boolean;
            ajaxUrl?: string;
            addToCartNonce?: string;
            wcProductId?: number;
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
        variants?: {
            displayMode: { desktop: string; mobile: string };
            useSimpleVariantsSelector?: boolean;
            hideOptions?: string[];
        };
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

interface SkuLinePayload {
    skuString: string;
    skuMap?: Record<string, string>;
    quantity?: number;
}

interface SkuPayload {
    skuString?: string;
    skuMap?: Record<string, string>;
    [key: string]: unknown;
}

const OV25_WOO_LOG = '[ov25-woo]';

/** Integer pence only — matches OV25 `productBreakdowns` / normalized price lines. */
function commerceAmountToWooMinorNumber(n: number): number {
    if (!Number.isFinite(n) || n <= 0) return 0;
    return Math.max(0, Math.round(n));
}

function commerceAmountToWooMinorString(n: number): string {
    return String(commerceAmountToWooMinorNumber(n));
}

/**
 * Woo cart quantity: form qty × configurator line qty (when not a multi-SKU split add).
 */
function resolveWooCartItemQuantity(payload: OnChangePayload | undefined, formQuantity: number): number {
    const fq = Math.max(1, formQuantity);
    if (!payload) return fq;
    if (payload.skus?.mode === 'multi' && (payload.skus.lines?.length ?? 0) > 1) {
        return fq;
    }
    const pLine = payload.price?.lines?.[0];
    const sLine = payload.skus?.lines?.[0];
    const configUnits =
        pLine && pLine.quantity > 0
            ? pLine.quantity
            : sLine && sLine.quantity > 0
              ? sLine.quantity
              : 1;
    return fq * Math.max(1, configUnits);
}

function previewStr(value: string, maxLen: number): string {
    if (value.length <= maxLen) return value;
    return `${value.slice(0, maxLen)}… (${value.length} chars)`;
}

function summarizeCommercePayload(payload?: OnChangePayload): Record<string, unknown> {
    if (!payload) return { skus: null, price: null };
    const summary: Record<string, unknown> = {};
    if (payload.price) {
        summary.price = {
            mode: payload.price.mode,
            totalPrice: payload.price.totalPrice,
            lineCount: payload.price.lines?.length ?? 0,
        };
    } else {
        summary.price = null;
    }
    if (payload.skus) {
        const s = payload.skus;
        if (s.mode === 'single') {
            summary.skus = {
                mode: 'single',
                lineCount: s.lines?.length ?? 0,
                skuStringPreview: previewStr(String(s.skuString ?? ''), 120),
            };
        } else {
            summary.skus = {
                mode: 'multi',
                lineCount: s.lines?.length ?? 0,
                linesPreview: (s.lines ?? []).slice(0, 4).map(l => ({
                    id: l.id,
                    qty: l.quantity,
                    sku: previewStr(l.skuString ?? '', 80),
                })),
            };
        }
    } else {
        summary.skus = null;
    }
    return summary;
}

function logFormCartSnapshot(form: HTMLFormElement, phase: string): void {
    const names = [
        'cfg_price',
        'cfg_payload',
        'cfg_commerce_mode',
        'cfg_sku',
        'cfg_skumap',
        'ov25_redirect_checkout',
        'ov25-thumbnail',
    ] as const;
    const snap: Record<string, unknown> = {};
    for (const n of names) {
        const el = form.querySelector(`input[name="${n}"]`) as HTMLInputElement | null;
        const raw = el?.value ?? '';
        if (n === 'cfg_payload' || n === 'cfg_sku' || n === 'cfg_skumap' || n === 'ov25-thumbnail') {
            snap[n] = raw ? previewStr(raw, 100) : '';
        } else {
            snap[n] = raw;
        }
    }
    console.log(OV25_WOO_LOG, `form.cart snapshot (${phase})`, snap);
}

/** Raw iframe CURRENT_SKU: map of line id → { skuString, skuMap, quantity } (multi-item), vs single-object shape. */
function isMultiItemSkuWirePayload(p: SkuPayload): boolean {
    if (!p || typeof p !== 'object' || Array.isArray(p)) return false;
    if (typeof p.skuString === 'string') return false;
    const keys = Object.keys(p);
    if (keys.length === 0) return false;
    return keys.every(k => {
        const v = p[k];
        return v && typeof v === 'object' && !Array.isArray(v) && typeof (v as SkuLinePayload).skuString === 'string';
    });
}

function encodeMultiItemSkuLinesJson(p: SkuPayload): string {
    const lines: Array<{ id: string } & SkuLinePayload> = [];
    for (const id of Object.keys(p)) {
        const v = p[id];
        if (!v || typeof v !== 'object' || Array.isArray(v)) continue;
        const line = v as SkuLinePayload;
        if (typeof line.skuString !== 'string') continue;
        lines.push({
            id,
            skuString: line.skuString,
            skuMap: line.skuMap,
            quantity: typeof line.quantity === 'number' ? line.quantity : 1,
        });
    }
    return JSON.stringify(lines);
}

function ensureCartField(form: HTMLFormElement, name: string): HTMLInputElement {
    let f = form.querySelector(`input[name="${name}"]`) as HTMLInputElement | null;
    if (!f) {
        f = Object.assign(document.createElement('input'), { type: 'hidden', name }) as HTMLInputElement;
        form.appendChild(f);
    }
    return f;
}

/**
 * Maps ov25-ui onChange payload to WooCommerce cart item data keys (same as hidden inputs / PHP filter).
 */
function buildCartFieldsFromPayload(payload?: OnChangePayload): Record<string, string> {
    const out: Record<string, string> = {};
    if (!payload) return out;

    if (payload.price) {
        const lines = payload.price.lines;
        let cfgMinor = commerceAmountToWooMinorNumber(payload.price.totalPrice);
        if (lines?.length === 1) {
            const ln = lines[0];
            const q = ln.quantity > 0 ? ln.quantity : 1;
            const linePriceRaw = typeof ln.price === 'number' ? ln.price : 0;
            const subMinor = commerceAmountToWooMinorNumber(ln.subtotal);
            let lineTotalMinor: number;
            if (linePriceRaw > 0) {
                lineTotalMinor = commerceAmountToWooMinorNumber(linePriceRaw);
            } else if (subMinor > 0) {
                lineTotalMinor = subMinor * q;
            } else {
                lineTotalMinor = commerceAmountToWooMinorNumber(payload.price.totalPrice);
            }
            cfgMinor = Math.max(1, Math.round(lineTotalMinor / q));
        } else if ((!lines || lines.length === 0) && payload.skus?.lines?.length === 1) {
            const q = payload.skus.lines[0].quantity > 0 ? payload.skus.lines[0].quantity : 1;
            const totMinor = commerceAmountToWooMinorNumber(payload.price.totalPrice);
            cfgMinor = Math.max(1, Math.round(totMinor / Math.max(1, q)));
        }
        out.cfg_price = String(cfgMinor);
        out.cfg_payload = JSON.stringify({
            normalized: true,
            price: payload.price,
            skus: payload.skus,
        });
    }

    if (payload.skus) {
        out.cfg_commerce_mode = payload.skus.mode;
        if (payload.skus.mode === 'single') {
            out.cfg_sku = payload.skus.skuString;
            out.cfg_skumap =
                payload.skus.skuMap && Object.keys(payload.skus.skuMap).length > 0
                    ? JSON.stringify(payload.skus.skuMap)
                    : '';
        } else {
            const linesJson = JSON.stringify(payload.skus.lines);
            out.cfg_sku = linesJson;
            out.cfg_skumap = linesJson;
        }
    }
    return out;
}

interface ScrapedFormCart {
    quantity: number;
    variation_id: number;
    variation: Record<string, string>;
    itemFields: Record<string, string>;
}

/** Reads qty, variation, and hidden cfg_* fields from form.cart when present (postMessage / legacy DOM). */
function scrapeFormCartOverrides(form: HTMLFormElement | null): ScrapedFormCart {
    const empty: ScrapedFormCart = { quantity: 1, variation_id: 0, variation: {}, itemFields: {} };
    if (!form) return empty;

    const qtyEl =
        (form.querySelector('input[name="quantity"]') as HTMLInputElement | null) ||
        (form.querySelector('input.qty') as HTMLInputElement | null);
    let quantity = 1;
    if (qtyEl?.value) {
        const q = parseInt(qtyEl.value, 10);
        if (!Number.isNaN(q) && q > 0) quantity = q;
    }

    const varEl = form.querySelector('input[name="variation_id"]') as HTMLInputElement | null;
    const variation_id = varEl?.value ? parseInt(varEl.value, 10) || 0 : 0;

    const variation: Record<string, string> = {};
    form.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[name^="attribute_"]').forEach(el => {
        const name = el.name;
        if (el instanceof HTMLSelectElement) {
            if (el.value) variation[name] = el.value;
        } else if (el instanceof HTMLInputElement) {
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (el.checked && el.value) variation[name] = el.value;
            } else if (el.value) {
                variation[name] = el.value;
            }
        }
    });

    const itemNames = [
        'cfg_price',
        'cfg_payload',
        'cfg_sku',
        'cfg_skumap',
        'cfg_commerce_mode',
        'ov25-thumbnail',
    ] as const;
    const itemFields: Record<string, string> = {};
    for (const n of itemNames) {
        const el = form.querySelector(`input[name="${n}"]`) as HTMLInputElement | null;
        if (el?.value) itemFields[n] = el.value;
    }

    return { quantity, variation_id, variation, itemFields };
}

function getOv25AjaxCartUrl(): string {
    const base = window.ov25Settings?.ajaxUrl || '/wp-admin/admin-ajax.php';
    const sep = base.includes('?') ? '&' : '?';
    return `${base}${sep}action=ov25_add_to_cart`;
}

/** Resolves WooCommerce product ID when localized id is absent (themes / blocks). */
function getWooCommerceProductId(): number | null {
    const localized = window.ov25Settings?.wcProductId;
    if (typeof localized === 'number' && localized > 0) return localized;

    const hiddenInput = document.querySelector('input[name="add-to-cart"]') as HTMLInputElement | null;
    if (hiddenInput?.value) {
        const id = parseInt(hiddenInput.value, 10);
        if (!Number.isNaN(id) && id > 0) return id;
    }

    const bodyClassMatch = document.body.className.match(/(?:^|\s)postid-(\d+)(?:\s|$)/);
    if (bodyClassMatch?.[1]) {
        const id = parseInt(bodyClassMatch[1], 10);
        if (!Number.isNaN(id) && id > 0) return id;
    }

    const dataDiv = document.getElementById('ov25-product-data');
    if (dataDiv?.dataset.productId) {
        const id = parseInt(dataDiv.dataset.productId, 10);
        if (!Number.isNaN(id) && id > 0) return id;
    }
    return null;
}

/**
 * Writes OV25 commerce fields from the normalized payload supplied by ov25-ui (add to cart / buy now).
 * Avoids races with the postMessage listener; optional fields are skipped when null.
 */
function applyOnChangePayloadToForm(form: HTMLFormElement, payload?: OnChangePayload): void {
    if (!payload) return;
    const fields = buildCartFieldsFromPayload(payload);
    for (const [name, value] of Object.entries(fields)) {
        ensureCartField(form, name).value = value;
    }
}

async function submitProductCartWithThumbnail(payload: OnChangePayload | undefined, redirectToCheckout: boolean): Promise<void> {
    const action = redirectToCheckout ? 'buyNow' : 'addToBasket';
    console.log(OV25_WOO_LOG, `${action}: submitProductCartWithThumbnail start`, {
        redirectToCheckout,
        ...summarizeCommercePayload(payload),
    });

    const form = document.querySelector('form.cart') as HTMLFormElement | null;
    const scraped = scrapeFormCartOverrides(form);
    const fromPayload = buildCartFieldsFromPayload(payload);
    const itemFields: Record<string, string> = { ...scraped.itemFields, ...fromPayload };

    if (form && payload) {
        applyOnChangePayloadToForm(form, payload);
        logFormCartSnapshot(form, 'after applyOnChangePayload (AJAX submit)');
    }

    try {
        const screenshotUrl = await window.ov25GenerateThumbnail();
        itemFields['ov25-thumbnail'] = screenshotUrl;
        if (form) {
            ensureCartField(form, 'ov25-thumbnail').value = screenshotUrl;
        }
        console.log(OV25_WOO_LOG, `${action}: thumbnail set`, previewStr(screenshotUrl, 80));
    } catch (error) {
        console.error(OV25_WOO_LOG, `${action}: thumbnail failed`, error);
    }

    const productId = getWooCommerceProductId();
    if (!productId) {
        console.warn(OV25_WOO_LOG, `${action}: could not resolve WooCommerce product ID`);
        return;
    }

    const nonce = window.ov25Settings?.addToCartNonce || '';
    if (!nonce) {
        console.warn(OV25_WOO_LOG, `${action}: missing addToCart nonce (is ov25-frontend script localized?)`);
    }

    const wooQty = resolveWooCartItemQuantity(payload, scraped.quantity);

    const body: Record<string, unknown> = {
        nonce,
        product_id: productId,
        quantity: wooQty,
        ov25_redirect_checkout: redirectToCheckout,
        ...itemFields,
    };

    if (scraped.variation_id > 0) {
        body.variation_id = scraped.variation_id;
    }
    if (Object.keys(scraped.variation).length > 0) {
        body.variation = scraped.variation;
    }

    console.log(OV25_WOO_LOG, `${action}: POST ov25_add_to_cart`, {
        productId,
        quantity: wooQty,
        formQuantity: scraped.quantity,
        redirectToCheckout,
        variation_id: scraped.variation_id,
        ...summarizeCommercePayload(payload),
    });

    try {
        const response = await fetch(getOv25AjaxCartUrl(), {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(body),
        });

        const result = (await response.json().catch(() => null)) as
            | { success?: boolean; data?: { redirect_url?: string; message?: string } }
            | null;

        if (result?.success && result.data?.redirect_url) {
            window.location.href = result.data.redirect_url;
            return;
        }

        const message =
            (result && typeof result.data === 'object' && result.data && 'message' in result.data
                ? String((result.data as { message?: string }).message || '')
                : '') || `HTTP ${response.status}`;
        console.error(OV25_WOO_LOG, `${action}: cart AJAX failed`, message);
        window.alert(`Could not add to cart: ${message}`);
    } catch (error) {
        console.error(OV25_WOO_LOG, `${action}: cart AJAX network error`, error);
    }
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
 * Configurator layout bucket for merged JSON (still keyed as `snap2` | `standard` in stored config).
 */
function getConfiguratorLayoutBucket(productLink: string): 'snap2' | 'standard' {
    return productLink.startsWith('snap2/') ? 'snap2' : 'standard';
}

/**
 * Build inject config from global + optional product Configurator Setup (ov25-setup) JSON.
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

    const layoutType = getConfiguratorLayoutBucket(productLink);
    const base =
        (s.configuratorConfig?.[layoutType] || s.configuratorConfig?.['standard'] || {}) as SerializableInjectConfig;
    let config = { ...base } as SerializableInjectConfig;

    if (s.useCustomConfig && s.productConfig) {
        const productOverride = s.productConfig[layoutType] || s.productConfig['standard'];
        if (productOverride && typeof productOverride === 'object') {
            config = deepMerge(
                config as Record<string, unknown>,
                productOverride as Record<string, unknown>,
            ) as SerializableInjectConfig;
        }
    }

    const selectorOverrides: Record<string, unknown> = {
        gallerySelector: { id: s.gallerySelector || '.woocommerce-product-gallery', replace: true },
        variantsSelector: s.variantsSelector || '[data-ov25-variants]',
        priceSelector: s.priceSelector || '[data-ov25-price]',
        swatchesSelector: s.swatchesSelector || '[data-ov25-swatches]',
    };
    if (s.configureButtonSelector?.trim()) {
        selectorOverrides.configureButtonSelector = { id: s.configureButtonSelector.trim(), replace: true };
    }

    const out: Record<string, unknown> = {
        apiKey,
        productLink,
        images: s.images || [],
        ...flatSelectorsFromSerializable(config),
        ...serializableToLegacyInjectFields(config),
        ...selectorOverrides,
    };

    const extraCss = s.customCSS?.trim();
    if (extraCss && !out.cssString && !(config.branding?.cssString && String(config.branding.cssString).trim())) {
        out.cssString = extraCss;
    }

    if (s.useSimpleConfigureButton) {
        out.useSimpleVariantsSelector = true;
        out.configureButtonSelector =
            selectorOverrides.configureButtonSelector || { id: '[data-ov25-configure-button]', replace: true };
    }

    return out;
}

function flatSelectorsFromSerializable(config: SerializableInjectConfig): Record<string, unknown> {
    const result: Record<string, unknown> = {};
    if (!config.selectors) return result;

    for (const [key, val] of Object.entries(config.selectors)) {
        if (typeof val === 'object' && val !== null && 'selector' in val) {
            const v = val as { selector: string; replace?: boolean };
            result[key === 'gallery' ? 'gallerySelector' : `${key}Selector`] = { id: v.selector, replace: v.replace };
        } else if (typeof val === 'string') {
            result[key === 'gallery' ? 'gallerySelector' : `${key}Selector`] = val;
        }
    }
    return result;
}

/**
 * Maps ov25-setup / Configurator Setup JSON into legacy flat inject fields read by
 * normalizeInjectConfig when options are not grouped (no `callbacks` wrapper).
 */
function serializableToLegacyInjectFields(config: SerializableInjectConfig): Record<string, unknown> {
    const result: Record<string, unknown> = {};

    const car = config.carousel;
    if (car?.desktop) {
        result.carouselDisplayMode = car.desktop;
    }
    if (car?.mobile) {
        result.carouselDisplayModeMobile = car.mobile;
    }

    const conf = config.configurator;
    if (conf?.displayMode) {
        const dm = conf.displayMode;
        if (dm.desktop) {
            result.configuratorDisplayMode = dm.desktop;
        }
        if (dm.mobile) {
            result.configuratorDisplayModeMobile = dm.mobile;
        }
    }
    if (conf?.triggerStyle) {
        if (conf.triggerStyle.desktop) {
            result.configuratorTriggerStyle = conf.triggerStyle.desktop;
        }
        if (conf.triggerStyle.mobile) {
            result.configuratorTriggerStyleMobile = conf.triggerStyle.mobile;
        }
    }
    if (conf?.variants?.displayMode) {
        const vdm = conf.variants.displayMode;
        if (vdm.desktop) {
            result.variantDisplayMode = vdm.desktop;
        }
        if (vdm.mobile) {
            result.variantDisplayModeMobile = vdm.mobile;
        }
    }
    if (conf?.variants && conf.variants.useSimpleVariantsSelector !== undefined) {
        result.useSimpleVariantsSelector = conf.variants.useSimpleVariantsSelector;
    }
    const vHide = conf?.variants?.hideOptions;
    if (Array.isArray(vHide) && vHide.length > 0) {
        result.hideOptions = vHide.filter((x): x is string => typeof x === 'string' && x.trim() !== '');
    }

    if (config.flags) {
        Object.assign(result, config.flags);
    }

    if (config.branding) {
        if (config.branding.logoURL) {
            result.logoURL = config.branding.logoURL;
        }
        if (config.branding.mobileLogoURL) {
            result.mobileLogoURL = config.branding.mobileLogoURL;
        }
        if (config.branding.cssString) {
            result.cssString = config.branding.cssString;
        }
    }

    return result;
}

const doInject = () => {
    const config = buildConfig();
    if (!config.apiKey) return;

    OV25.injectConfigurator({
        ...config,
        addToBasketFunction: async (payload?: OnChangePayload) => {
            try {
                await submitProductCartWithThumbnail(payload, false);
            } catch (e) {
                console.error(OV25_WOO_LOG, 'addToBasketFunction rejected', e);
            }
        },
        buyNowFunction: async (payload?: OnChangePayload) => {
            try {
                await submitProductCartWithThumbnail(payload, true);
            } catch (e) {
                console.error(OV25_WOO_LOG, 'buyNowFunction rejected', e);
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

/**
 * Server output also hides native ATC (see ov25_get_hide_native_add_to_cart_css); this mirrors it using
 * body.postid-{id} so it still applies if body_class is stripped, and runs as soon as the bundle loads.
 */
function ensureOv25NativeAtcHideStyles(): void {
    const id = window.ov25Settings?.wcProductId;
    if (!id || document.head.querySelector('style[data-ov25-native-atc-hide]')) return;

    const postid = `body.single-product.postid-${id}`;
    const ov25Body = 'body.ov25-product';

    const hideRows = [
        `${postid} div.product form.cart button[type="submit"]:not(.ov25-replacement-button)`,
        `${postid} div.product form.cart input[type="submit"]:not(.ov25-replacement-button)`,
        `${postid} div.product form.cart .single_add_to_cart_button:not(.ov25-replacement-button)`,
        `${postid} div.product div.summary .single_add_to_cart_button:not(.ov25-replacement-button)`,
        `${postid} form.cart button[type="submit"]:not(.ov25-replacement-button)`,
        `${postid} .wp-block-woocommerce-product-button .wc-block-components-product-button__button:not(.ov25-replacement-button)`,
        `${ov25Body} div.product form.cart button[type="submit"]:not(.ov25-replacement-button)`,
        `${ov25Body} div.product form.cart input[type="submit"]:not(.ov25-replacement-button)`,
        `${ov25Body} div.product form.cart .single_add_to_cart_button:not(.ov25-replacement-button)`,
        `${ov25Body} form.cart button[type="submit"]:not(.ov25-replacement-button)`,
        `${ov25Body} .wp-block-woocommerce-product-button .wc-block-components-product-button__button:not(.ov25-replacement-button)`,
    ];

    const style = document.createElement('style');
    style.setAttribute('data-ov25-native-atc-hide', '');
    style.textContent = `${hideRows.join(',')}{display:none!important;}
${postid} .ov25-replacement-button,${ov25Body} .ov25-replacement-button{display:inline-flex!important;}`;
    document.head.appendChild(style);
}

function removeNativeWooProductForms(): void {
    if (!window.ov25Settings?.wcProductId) return;
    const removeSelectors = [
        'form.cart',
        'form.variations_form',
        'div.woocommerce-variation-add-to-cart',
        '.woocommerce div.product form.cart',
        '.single-product form.wp-block-woocommerce-add-to-cart-form',
        '.single-product .wp-block-woocommerce-product-add-to-cart',
        '.single-product .wp-block-woocommerce-product-button',
        '.single-product .wc-block-add-to-cart-form',
        '.single-product .wc-block-components-product-button',
    ];

    for (const selector of removeSelectors) {
        const nodes = document.querySelectorAll(selector);
        nodes.forEach(node => {
            if (node instanceof Element && node.closest('[data-ov25-root]')) {
                return;
            }
            node.parentNode?.removeChild(node);
        });
    }
}

function scheduleOv25NativeProductFormRemoval(): void {
    if (!window.ov25Settings?.wcProductId) return;

    const tryRemove = () => removeNativeWooProductForms();
    for (const ms of [0, 100, 400, 1200, 3000]) {
        window.setTimeout(tryRemove, ms);
    }
    window.addEventListener('load', tryRemove);

    if (!document.body) return;
    let moIdle: number | undefined;
    const obs = new MutationObserver(() => {
        window.clearTimeout(moIdle);
        moIdle = window.setTimeout(tryRemove, 50);
    });
    obs.observe(document.body, { childList: true, subtree: true });
    window.setTimeout(() => obs.disconnect(), 15000);
}

if (window.ov25Settings?.wcProductId) {
    ensureOv25NativeAtcHideStyles();
    removeNativeWooProductForms();
    if (document.readyState !== 'loading') scheduleOv25NativeProductFormRemoval();
    else document.addEventListener('DOMContentLoaded', scheduleOv25NativeProductFormRemoval);
}

/**
 * Optional bridge for themes still posting CURRENT_PRICE/CURRENT_SKU into a form.cart.
 * If the native Woo form is removed, this path naturally no-ops.
 */
document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('message', (ev: MessageEvent) => {
        let { type, payload } = ev.data ?? {};
        if (!payload) return;

        if (typeof payload === 'string') {
            try { payload = JSON.parse(payload); }
            catch { return; }
        }

        if (type === 'CURRENT_PRICE' || type === 'CURRENT_SKU') {
            const form = document.querySelector('form.cart') as HTMLFormElement | null;
            if (!form) return;

            if (type === 'CURRENT_PRICE') {
                const normalized = normalizePricePayload(payload);
                if (normalized) {
                    applyOnChangePayloadToForm(form, { skus: null, price: normalized });
                } else {
                    const pricePayload = payload as PricePayload;
                    ensureCartField(form, 'cfg_price').value = commerceAmountToWooMinorString(
                        typeof pricePayload.totalPrice === 'number' ? pricePayload.totalPrice : 0,
                    );
                    ensureCartField(form, 'cfg_payload').value = JSON.stringify(payload);
                }
            }

            if (type === 'CURRENT_SKU') {
                const normalized = normalizeSkuPayload(payload);
                if (normalized) {
                    applyOnChangePayloadToForm(form, { skus: normalized, price: null });
                } else {
                    const skuPayload = payload as SkuPayload;
                    if (typeof skuPayload.skuString === 'string' && skuPayload.skuString) {
                        ensureCartField(form, 'cfg_sku').value = skuPayload.skuString;
                        ensureCartField(form, 'cfg_commerce_mode').value = 'single';
                    } else if (isMultiItemSkuWirePayload(skuPayload)) {
                        const json = encodeMultiItemSkuLinesJson(skuPayload);
                        ensureCartField(form, 'cfg_sku').value = json;
                        ensureCartField(form, 'cfg_skumap').value = json;
                        ensureCartField(form, 'cfg_commerce_mode').value = 'multi';
                    }
                    if (skuPayload.skuMap && typeof skuPayload.skuString === 'string') {
                        ensureCartField(form, 'cfg_skumap').value = JSON.stringify(skuPayload.skuMap);
                    }
                }
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
