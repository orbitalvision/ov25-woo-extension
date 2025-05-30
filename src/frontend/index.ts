import * as OV25 from 'ov25-ui';

// Declare global variables for TypeScript
declare global {
    interface Window {
        ov25Settings: {
            logoURL: string;
            autoCarousel: boolean;
            deferThreeD: boolean;
            images: string[];
        };
    }
}

interface PricePayload {
  formattedPrice: string;
  totalPrice: number;
  [key: string]: any;
}

interface SkuPayload {
  skuString: string;
  [key: string]: any;
}

// ov25Ui.injectConfigurator({
//     apiKey: () => { return window.ov25ConfiguratorApiKey },
//     productLink: () => { return window.productMetafields.ov25.configuratorID }, 
//     galleryId: { id: window.ov25ConfiguratorGalleryQuerySelector ?? '.product__media-wrapper', replace: true }, 
//     carouselId: window.ov25ConfiguratorCarouselQuerySelector === 'auto-carousel-true' ? true : { id: window.ov25ConfiguratorCarouselQuerySelector ?? '', replace: true },
//     variantsId: window.ov25ConfiguratorVariantsQuerySelector ?? '#ov25-configurator-controls-container',
//     priceId: {id: window.ov25ConfiguratorPriceQuerySelector ?? '.price__regular .price-item', replace: true },
//     nameId: {id: window.ov25ConfiguratorNameQuerySelector ?? '.product__title', replace: true },
//     images: window.ov25ShopifyImages,
//     buyNowFunction: () => {
//       
//     },
//     addToBasketFunction: () => {
//       document.querySelector('.form[action="/cart/add"] button[type="submit"]').click();
//     },
//     logoURL: window.ov25LogoURL,
//     deferThreeD: window.ov25DeferThreeD,
//   });


// Initialize the configurator

OV25.injectConfigurator({
    apiKey: () => {
        const element = document.querySelector('[data-ov25-iframe]');
        if (!element) return '';
        const data = element.getAttribute('data-ov25-iframe');
        return data ? data.split('/')[0] : '';
    },
    productLink: () => {
        const element = document.querySelector('[data-ov25-iframe]');
        if (!element) return '';
        const data = element.getAttribute('data-ov25-iframe');
        if (!data) return '';
        
        // Split only on the first forward slash to separate API key from the rest of the path
        const firstSlashIndex = data.indexOf('/');
        return firstSlashIndex !== -1 ? data.substring(firstSlashIndex + 1) : '';
    },
    addToBasketFunction: () => {
            const form = document.querySelector('form.cart') as HTMLFormElement;
            if (form) {
                form.submit();
            }
    },
    galleryId: {id: '[data-ov25-iframe]', replace: false},
    variantsId: '[data-ov25-variants]',
    images: window.ov25Settings?.images || [],
    logoURL: window.ov25Settings?.logoURL || '',
    carouselId: window.ov25Settings?.autoCarousel ? true : false,
    deferThreeD: window.ov25Settings?.deferThreeD || false,
});




/*  ov25-price-bridge.js  */
/* ov25-price-and-sku-bridge.js */
document.addEventListener('DOMContentLoaded', () => {

  window.addEventListener('message', (ev: MessageEvent) => {
    let { type, payload } = ev.data ?? {};
    if (!payload) return;

    // Parse if string
    if (typeof payload === 'string') {
      try { payload = JSON.parse(payload); }
      catch { return; }
    }

    // Replace price skeleton
    if (type === 'CURRENT_PRICE') {
      const { formattedPrice, totalPrice } = payload as PricePayload;
      if (formattedPrice && typeof totalPrice === 'number') {
        document.querySelectorAll('[data-ov25-price]').forEach(el => {
          el.classList.remove('ov25-price-skeleton');
          (el as HTMLElement).innerHTML = formattedPrice;
        });
      }
    }

    // Now stash hidden fields for price, payload, and sku
    if (type === 'CURRENT_PRICE' || type === 'CURRENT_SKU') {
      const form = document.querySelector('form.cart');
      if (!form) return;

      const ensureField = (name: string): HTMLInputElement => {
        let f = form.querySelector(`input[name="${name}"]`) as HTMLInputElement | null;
        if (!f) {
          f = Object.assign(document.createElement('input'), {
            type: 'hidden',
            name,
          }) as HTMLInputElement;
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
        if (skuPayload.skuString) {
          ensureField('cfg_sku').value = skuPayload.skuString;
        }
      }
    }
  });
});

// /*************************************************************************
//  * OV25 – Ultra‑simple Variant Picker (sends payload as JSON string)
//  *************************************************************************/
// document.addEventListener('DOMContentLoaded', () => {

//   /* ------------------------------------------------------------------
//    * Post helper – always stringifies payload as the spec requires
//    * ----------------------------------------------------------------*/
//   function postToConfigurator(type: string, payload: any): void {
//     const iframe = document.querySelector('iframe[data-ov25-iframe]') ||
//                    document.querySelector('iframe');
//     if (!iframe) return;
    
//     (iframe as HTMLIFrameElement).contentWindow?.postMessage(
//       { type, payload: JSON.stringify(payload) },
//       '*'
//     );
//   }

//   /* ------------------------------------------------------------------
//    * Render simple radios into <div data-ov25-variants>
//    * ----------------------------------------------------------------*/
//   function render(state: ConfiguratorState): void {
//     const mount = document.querySelector('[data-ov25-variants]');
//     if (!mount) {
//       return;
//     }
//     mount.innerHTML = '';

//     state.options.forEach(opt => {
//       opt.groups.forEach(grp => {

//         const fs = document.createElement('fieldset');
//         const lgd = document.createElement('legend');
//         lgd.textContent =
//           (grp.name === 'default' || grp.name === 'Default')
//             ? opt.name
//             : `${opt.name} – ${grp.name}`;
//         fs.appendChild(lgd);

//         grp.selections.forEach(sel => {
//           const label = document.createElement('label');
//           label.style.cssText = 'display:block;margin:.25em 0;';

//           const radio = Object.assign(document.createElement('input'), {
//             type: 'radio',
//             name: `ov25_opt_${opt.id}_${grp.id}`,
//             value: sel.id,
//           }) as HTMLInputElement;
          
//           label.append(radio, ' ', sel.name);
//           fs.appendChild(label);

//           /* pre‑select current */
//           const current = state.selectedSelections?.find(
//             s => s.optionId === opt.id && s.groupId === grp.id
//           );
//           if (current && current.selectionId === sel.id) radio.checked = true;

//           radio.addEventListener('change', () => {
//             if (!radio.checked) return;
//             const payload: SelectedSelection = {
//               optionId: opt.id,
//               groupId: grp.id,
//               selectionId: sel.id,
//             };
//             postToConfigurator('SELECT_SELECTION', payload);
//           });
//         });

//         mount.appendChild(fs);
//       });
//     });
//   }

  /* ------------------------------------------------------------------
//    * Listen for CONFIGURATOR_STATE
//    * ----------------------------------------------------------------*/
//   let latestState: ConfiguratorState | null = null;

//   window.addEventListener('message', (ev: MessageEvent) => {
//     if (ev.data?.type !== 'CONFIGURATOR_STATE') return;

//     let payload = ev.data.payload;
//     if (typeof payload === 'string') {
//       try { payload = JSON.parse(payload); }
//       catch (e) {
//         console.error('[OV25] Failed parsing CONFIGURATOR_STATE:', e);
//         return;
//       }
//     }
//     latestState = payload as ConfiguratorState;
//     render(latestState);
//   });

//   /* Re‑render after blocks hydrate (placeholder may appear late) */
//   document.addEventListener('wc-blocks-rendered', () => {
//     if (latestState) render(latestState);
//   });
// }); 