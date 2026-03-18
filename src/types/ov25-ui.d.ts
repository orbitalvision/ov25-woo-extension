declare module 'ov25-ui-react18' {
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
    addToBasketFunction?: () => Promise<void>;
    buySwatchesFunction?: (swatches: unknown[], rules: unknown) => Promise<void>;
    onChange?: (data: { skus: string[]; price: number }) => void;
    [key: string]: unknown;
  }): void;
}

declare module 'ov25-ui-react18/styles.css';

declare module 'ov25-setup' {
  import type { ComponentType } from 'react';

  export type LayoutType = 'standard' | 'snap2';

  export interface SerializableInjectConfig {
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
