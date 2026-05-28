declare module 'ov25-ui-react18' {
  export interface CommerceLineItemSku {
    id: string;
    skuString: string;
    skuMap: Record<string, string>;
    quantity: number;
  }

  export interface UnifiedSkuPayloadSingle {
    mode: 'single';
    lines: CommerceLineItemSku[];
    skuString: string;
    skuMap?: Record<string, string>;
  }

  export interface UnifiedSkuPayloadMulti {
    mode: 'multi';
    lines: CommerceLineItemSku[];
  }

  export type UnifiedSkuPayload = UnifiedSkuPayloadSingle | UnifiedSkuPayloadMulti;

  export interface CommerceLineItemPrice {
    id: string;
    name: string;
    quantity: number;
    price: number;
    formattedPrice: string;
    subtotal: number;
    formattedSubtotal: string;
    discountedAmount: number;
    formattedDiscountAmount: string;
    discountPercentage: number;
    selections: Array<{
      category?: string;
      name: string;
      sku?: string;
      price: number;
      formattedPrice: string;
      thumbnail?: string;
    }>;
    modelId?: string;
  }

  export interface UnifiedPricePayload {
    mode: 'single' | 'multi';
    totalPrice: number;
    subtotal: number;
    formattedPrice: string;
    formattedSubtotal: string;
    discount: { amount: number; formattedAmount: string; percentage: number };
    lines: CommerceLineItemPrice[];
    priceBreakdown?: unknown[];
    productBreakdowns?: unknown[];
  }

  export type OnChangePayload = {
    skus: UnifiedSkuPayload | null;
    price: UnifiedPricePayload | null;
  };

  export type StringReplacementRule = {
    trigger?: { name: string; value: string };
    template: string;
  };

  export type StringReplacementsConfig = Record<string, StringReplacementRule[]>;
  export type BedPartFlags = { headboard: boolean; base: boolean; mattress: boolean };
  export type BedEmbedConfig = {
    allowNone?: BedPartFlags;
    filterSelectionsByCurrentSize?: BedPartFlags;
  };

  export function normalizeSkuPayload(data: unknown): UnifiedSkuPayload | null;
  export function normalizePricePayload(data: unknown): UnifiedPricePayload | null;
  export function parseIframeJsonPayload(data: unknown): unknown;

  export function injectConfigurator(config: {
    apiKey: string | (() => string);
    productLink: string | (() => string);
    gallerySelector?: string | { id: string; replace: boolean };
    variantsSelector?: string;
    swatchesSelector?: string;
    priceSelector?: string;
    configureButtonSelector?: string | { id: string; replace: boolean };
    useSimpleVariantsSelector?: boolean;
    images?: string[];
    logoURL?: string;
    mobileLogoURL?: string;
    carouselSelector?: boolean;
    configuratorDisplayMode?: string;
    deferThreeD?: boolean;
    showOptional?: boolean;
    hideAr?: boolean;
    hidePricing?: boolean;
    forceMobile?: boolean;
    autoOpen?: boolean;
    cssString?: string;
    hideLogo?: boolean;
    hideOptions?: string[];
    bed?: BedEmbedConfig;
    stringReplacements?: StringReplacementsConfig;
    addToBasketFunction?: (payload?: OnChangePayload) => void | Promise<void>;
    buyNowFunction?: (payload?: OnChangePayload) => void | Promise<void>;
    buySwatchesFunction?: (swatches: unknown[], rules: unknown) => Promise<void>;
    onChange?: (data: OnChangePayload) => void;
    [key: string]: unknown;
  }): void;
}

declare module 'ov25-ui-react18/styles.css';

declare module 'ov25-setup' {
  import type { ComponentType } from 'react';

  export type LayoutType = 'standard' | 'snap2' | 'bedConfigurator';

  export interface SerializableInjectConfig {
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
    branding?: { logoURL?: string; mobileLogoURL?: string; cssString?: string; hideLogo?: boolean };
    bed?: {
      allowNone?: { headboard: boolean; base: boolean; mattress: boolean };
      filterSelectionsByCurrentSize?: { headboard: boolean; base: boolean; mattress: boolean };
    };
    stringReplacements?: StringReplacementsConfig;
    [key: string]: unknown;
  }

  export type ConfiguratorSetupPayload = Record<LayoutType, SerializableInjectConfig>;

  export interface ConfiguratorSetupProps {
    apiKey?: string;
    productLink?: string;
    previewBaseUrl?: string;
    initialConfig?: ConfiguratorSetupPayload;
    onSave?: (payload: ConfiguratorSetupPayload) => void;
    hidePreview?: boolean;
    hideSaveButton?: boolean;
  }

  export const ConfiguratorSetup: ComponentType<ConfiguratorSetupProps>;
  export const ConfigPanel: ComponentType<unknown>;
  export const PreviewArea: ComponentType<unknown>;
}

declare module 'ov25-setup/index.css';
