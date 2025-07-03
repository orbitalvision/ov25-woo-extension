import * as OV25 from 'ov25-ui';

// Declare global variables for TypeScript
declare global {
    interface Window {
        ov25Settings: {
            logoURL: string;
            mobileLogoURL: string;
            autoCarousel: boolean;
            deferThreeD: boolean;
            showOptional: boolean;
            images: string[];
        };
        ov25GenerateThumbnail: () => Promise<string>;
    }
}

interface PricePayload {
  formattedPrice: string;
  formattedSubtotal: string;

  totalPrice: number;
  subtotal: number;
  
  discount: {
    amount: number,
    formattedAmount: string,
    percentage: number
  }
  [key: string]: any;
}

interface SkuPayload {
  skuString: string;
  [key: string]: any;
}



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
    addToBasketFunction: async () => {
        const form = document.querySelector('form.cart');
        if (!form) return;

        try {
            // Generate thumbnail
            const screenshotUrl = await window.ov25GenerateThumbnail();
            
            // Add thumbnail URL to form
            const ensureField = (name: string): HTMLInputElement => {
                let field = form.querySelector(`input[name="${name}"]`) as HTMLInputElement | null;
                if (!field) {
                    field = Object.assign(document.createElement('input'), {
                        type: 'hidden',
                        name,
                    }) as HTMLInputElement;
                    form.appendChild(field);
                }
                return field;
            };

            ensureField('ov25-thumbnail').value = screenshotUrl;
            // Submit the form
            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
            if (submitButton) {
                submitButton.click();
            }
        } catch (error) {
            console.error('Failed to generate thumbnail:', error);
            // Still submit the form even if thumbnail generation fails
            const submitButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
            if (submitButton) {
                submitButton.click();
            }
        }
    },
    galleryId: {id: '.woocommerce-product-gallery', replace: true},
    variantsId: '[data-ov25-variants]',
    priceId: '[data-ov25-price]',
    images: window.ov25Settings?.images || [],
    logoURL: window.ov25Settings?.logoURL || '',
    mobileLogoURL: window.ov25Settings?.mobileLogoURL !== '' && window.ov25Settings?.mobileLogoURL !== undefined ? window.ov25Settings?.mobileLogoURL : undefined,
    carouselId: window.ov25Settings?.autoCarousel ? true : false,
    deferThreeD: window.ov25Settings?.deferThreeD || false,
    showOptional: window.ov25Settings?.showOptional || false,
});



// CSS + JavaScript trick: Replace add to cart button
document.addEventListener('DOMContentLoaded', () => {
    const ov25Element = document.querySelector('[data-ov25-iframe]');
    if (!ov25Element) return; // Not an OV25 product, skip

    const form = document.querySelector('form.cart') as HTMLFormElement;
    if (!form) return;

    const originalButton = form.querySelector('button[type="submit"]') as HTMLButtonElement;
    if (!originalButton) return;

    // Add CSS to hide the original button
    const style = document.createElement('style');
    style.textContent = `
        form.cart button[type="submit"] {
            display: none !important;
        }
        .ov25-replacement-button {
            display: inline-block !important;
        }
    `;
    document.head.appendChild(style);

    // Create replacement button that looks identical
    const replacementButton = originalButton.cloneNode(true) as HTMLButtonElement;
    replacementButton.type = 'button'; // Not a submit button
    replacementButton.className = originalButton.className + ' ov25-replacement-button';
    
    // Insert replacement button right after the original
    originalButton.parentNode?.insertBefore(replacementButton, originalButton.nextSibling);

    // Add click handler to replacement button
    replacementButton.addEventListener('click', async (event) => {
        event.preventDefault();
        
        // Show loading state
        const originalText = replacementButton.textContent;
        replacementButton.disabled = true;
        replacementButton.textContent = 'Generating Preview...';

        try {
            // Generate thumbnail at this exact moment
            const screenshotUrl = await window.ov25GenerateThumbnail();
            
            // Add thumbnail to form
            const thumbnailField = document.createElement('input');
            thumbnailField.type = 'hidden';
            thumbnailField.name = 'ov25-thumbnail';
            thumbnailField.value = screenshotUrl;
            form.appendChild(thumbnailField);

            // Now trigger the original button to submit the form
            originalButton.click();
        } catch (error) {
            console.error('Failed to generate thumbnail:', error);
            // Still submit the form even if thumbnail generation fails
            originalButton.click();
        } finally {
            // Reset button state
            replacementButton.disabled = false;
            replacementButton.textContent = originalText;
        }
    });
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

    // // Replace price skeleton
    // if (type === 'CURRENT_PRICE') {
    //   const { formattedPrice, totalPrice } = payload as PricePayload;
    //   if (formattedPrice && typeof totalPrice === 'number') {
    //     document.querySelectorAll('[data-ov25-price]').forEach(el => {
    //       el.classList.remove('ov25-price-skeleton');
    //       (el as HTMLElement).innerHTML = formattedPrice;
    //     });
    //   }
    // }

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
        if (skuPayload.skuMap) {
          ensureField('cfg_skumap').value = JSON.stringify(skuPayload.skuMap);
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