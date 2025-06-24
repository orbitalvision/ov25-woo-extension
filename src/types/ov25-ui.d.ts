declare module 'ov25-ui' {
  export function injectConfigurator(config: {
    apiKey: string | (() => string);
    productLink: string | (() => string);
    galleryId: string | { id: string; replace: boolean };
    variantsId?: string;
    showOptional?: boolean;
    [key: string]: any;
  }): void;
}

declare module 'ov25-ui/styles.css'; 