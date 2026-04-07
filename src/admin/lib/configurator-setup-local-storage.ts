import type { ConfiguratorSetupPayload } from 'ov25-setup';

/** Same key as `ov25-setup` — editor hydrates from this; do not pass server layout as `initialConfig`. */
export const CONFIGURATOR_SETUP_FORM_STATE_STORAGE_KEY = 'ov25-configurator-setup';

/** Seed or clear localStorage from the REST / meta payload before mounting ConfiguratorSetup. */
export function syncConfiguratorFormStateFromSavedJson(data: unknown): void {
  try {
    if (data && typeof data === 'object' && (data as { _formState?: unknown })._formState) {
      localStorage.setItem(
        CONFIGURATOR_SETUP_FORM_STATE_STORAGE_KEY,
        JSON.stringify((data as { _formState: unknown })._formState),
      );
    } else {
      localStorage.removeItem(CONFIGURATOR_SETUP_FORM_STATE_STORAGE_KEY);
    }
  } catch {
    /* quota / private mode */
  }
}

/** Append live form state from localStorage to the inject payload on save. */
export function mergeConfiguratorPayloadWithStoredFormState(
  payload: ConfiguratorSetupPayload,
): Record<string, unknown> {
  let formState: unknown = null;
  try {
    const raw = localStorage.getItem(CONFIGURATOR_SETUP_FORM_STATE_STORAGE_KEY);
    if (raw) formState = JSON.parse(raw);
  } catch {
    /* ignore */
  }
  return {
    ...(payload as Record<string, unknown>),
    ...(formState ? { _formState: formState } : {}),
  };
}
