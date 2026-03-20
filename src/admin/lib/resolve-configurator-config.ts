import type { ConfiguratorSetupPayload } from 'ov25-setup';

/** Empty `{}` / `[]` from the REST response is truthy in JS but should not mask a populated `window.ov25Admin.configuratorConfig`. */
function isEmptyConfiguratorRecord(value: unknown): boolean {
  if (value == null) return true;
  if (typeof value !== 'object') return true;
  return Object.keys(value as object).length === 0;
}

/**
 * Prefer settings from the REST bundle when non-empty; otherwise fall back to the PHP-localized admin object
 * (and updates applied there after save until the next full reload).
 */
export function resolveConfiguratorConfig(
  fromSettings: unknown,
  fromAdmin: unknown,
): ConfiguratorSetupPayload {
  if (!isEmptyConfiguratorRecord(fromSettings)) {
    return fromSettings as ConfiguratorSetupPayload;
  }
  if (!isEmptyConfiguratorRecord(fromAdmin)) {
    return fromAdmin as ConfiguratorSetupPayload;
  }
  return {} as ConfiguratorSetupPayload;
}
