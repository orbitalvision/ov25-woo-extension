import { useState, useEffect, useCallback, useMemo } from 'react';
import { ConfiguratorSetup, type ConfiguratorSetupPayload } from 'ov25-setup';
import 'ov25-setup/index.css';
import { api } from '../lib/api';
import { useSettingsContext } from '../context/SettingsContext';
import { resolveConfiguratorConfig } from '../lib/resolve-configurator-config';
import {
  mergeConfiguratorPayloadWithStoredFormState,
  syncConfiguratorFormStateFromSavedJson,
} from '../lib/configurator-setup-local-storage';

interface Range {
  id: number;
  name: string;
  manufacturerName: string;
  hasSnap2: boolean;
  snap2Active: boolean;
  products: Product[];
}

interface Product {
  id: number;
  name: string;
  thumbnail: string | null;
  category: string | null;
  hasConfigurator: boolean;
  status: boolean;
}

interface ProductFieldProps {
  wooProductId: string;
  currentLink: string;
  useCustomConfig: string;
  customConfig: string;
}

type LinkType = 'product' | 'range' | 'snap2';

function parseLinkType(link: string): { type: LinkType; id: string } | null {
  if (!link) return null;
  if (link.startsWith('snap2/')) return { type: 'snap2', id: link.slice(6) };
  if (link.startsWith('range/')) return { type: 'range', id: link.slice(6) };
  return { type: 'product', id: link };
}

function formatLink(type: LinkType, id: number): string {
  if (type === 'snap2') return `snap2/${id}`;
  if (type === 'range') return `range/${id}`;
  return String(id);
}

const BADGE_COLORS: Record<string, string> = {
  product: '#2271b1',
  range: '#00a32a',
  snap2: '#9333ea',
};

export function ProductField({ wooProductId, currentLink, useCustomConfig, customConfig }: ProductFieldProps) {
  const [link, setLink] = useState(currentLink);
  const [useCustom, setUseCustom] = useState(useCustomConfig === 'yes');
  const [config, setConfig] = useState<ConfiguratorSetupPayload>(() => {
    try { return JSON.parse(customConfig || '{}'); } catch { return {}; }
  });
  const [ranges, setRanges] = useState<Range[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [search, setSearch] = useState('');
  const [pickerOpen, setPickerOpen] = useState(false);
  const [manualMode, setManualMode] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editorMountKey, setEditorMountKey] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveStatus, setSaveStatus] = useState<'idle' | 'saved' | 'error'>('idle');

  const admin = (window as any).ov25Admin;
  const { settings } = useSettingsContext();
  const globalConfiguratorConfig = useMemo(
    () => resolveConfiguratorConfig(settings?.configuratorConfig, admin?.configuratorConfig),
    [settings?.configuratorConfig, admin?.configuratorConfig],
  );

  const rawEditorConfig = useMemo((): Record<string, unknown> => {
    const hasCustom =
      config &&
      typeof config === 'object' &&
      Object.keys(config as object).length > 0;
    const base = hasCustom ? config : globalConfiguratorConfig;
    return base as Record<string, unknown>;
  }, [config, globalConfiguratorConfig]);

  useEffect(() => {
    if (!modalOpen) {
      setEditorMountKey(null);
      return;
    }
    syncConfiguratorFormStateFromSavedJson(rawEditorConfig);
    const id = requestAnimationFrame(() => {
      setEditorMountKey(`ov25-configurator-product-${Date.now()}`);
    });
    return () => cancelAnimationFrame(id);
  }, [modalOpen, rawEditorConfig]);

  useEffect(() => {
    api.getProductsList()
      .then((data: any) => {
        setRanges(data?.ranges || []);
        setLoading(false);
      })
      .catch((err: Error) => {
        setError(err.message);
        setLoading(false);
        setManualMode(true);
      });
  }, []);

  const syncHidden = useCallback((field: string, value: string) => {
    const el = document.querySelector<HTMLInputElement>(`input[name="${field}"]`);
    if (el) el.value = value;
  }, []);

  const handleSelect = (type: LinkType, id: number) => {
    const val = formatLink(type, id);
    setLink(val);
    syncHidden('_ov25_product_id', val);
    setPickerOpen(false);
    setSearch('');
  };

  const handleUnlink = () => {
    setLink('');
    syncHidden('_ov25_product_id', '');
  };

  const handleToggleCustom = () => {
    const next = !useCustom;
    setUseCustom(next);
    syncHidden('_ov25_use_custom_config', next ? 'yes' : 'no');
    if (!next) {
      syncHidden('_ov25_configurator_config', '{}');
      setConfig({} as ConfiguratorSetupPayload);
    }
  };

  const handleConfigSave = async (payload: ConfiguratorSetupPayload) => {
    setSaving(true);
    setSaveStatus('idle');
    try {
      const merged = mergeConfiguratorPayloadWithStoredFormState(payload);
      const json = JSON.stringify(merged);
      syncHidden('_ov25_configurator_config', json);
      setConfig(merged as ConfiguratorSetupPayload);

      if (wooProductId) {
        await api.saveProductSettings(parseInt(wooProductId), {
          configuratorConfig: merged,
          useCustomConfig: true,
        });
      }
      setSaveStatus('saved');
      setTimeout(() => setSaveStatus('idle'), 3000);
    } catch (err) {
      console.error('Failed to save product config:', err);
      setSaveStatus('error');
      setTimeout(() => setSaveStatus('idle'), 5000);
    } finally {
      setSaving(false);
    }
  };

  const parsed = parseLinkType(link);

  const linkedInfo = useMemo(() => {
    if (!parsed) return null;
    for (const range of ranges) {
      if (parsed.type === 'range' && String(range.id) === parsed.id) {
        return { name: range.name, rangeName: range.manufacturerName, type: 'range' as LinkType, thumbnail: null };
      }
      if (parsed.type === 'snap2' && String(range.id) === parsed.id) {
        return { name: `${range.name} (Snap2)`, rangeName: range.manufacturerName, type: 'snap2' as LinkType, thumbnail: null };
      }
      for (const p of range.products) {
        if (parsed.type === 'product' && String(p.id) === parsed.id) {
          return { name: p.name, rangeName: range.name, type: 'product' as LinkType, thumbnail: p.thumbnail };
        }
      }
    }
    return { name: `ID: ${link}`, rangeName: 'Not found in catalog', type: parsed.type, thumbnail: null };
  }, [parsed, ranges, link]);

  /** Sort ranges so the one containing the currently linked product/range comes first */
  const sortedRanges = useMemo(() => {
    if (!parsed) return filteredSearch();
    return filteredSearch().sort((a, b) => {
      const aHas = isLinkedRange(a);
      const bHas = isLinkedRange(b);
      if (aHas && !bHas) return -1;
      if (!aHas && bHas) return 1;
      return 0;
    });

    function isLinkedRange(r: Range): boolean {
      if (!parsed) return false;
      if ((parsed.type === 'range' || parsed.type === 'snap2') && String(r.id) === parsed.id) return true;
      if (parsed.type === 'product') return r.products.some((p) => String(p.id) === parsed!.id);
      return false;
    }

    function filteredSearch(): Range[] {
      if (!search) return [...ranges];
      const q = search.toLowerCase();
      return ranges
        .map((r) => {
          const rangeMatches = r.name.toLowerCase().includes(q) || r.manufacturerName.toLowerCase().includes(q);
          return {
            ...r,
            products: rangeMatches
              ? r.products
              : r.products.filter(
                  (p) => p.name.toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(q)
                ),
          };
        })
        .filter((r) => r.name.toLowerCase().includes(q) || r.manufacturerName.toLowerCase().includes(q) || r.products.length > 0);
    }
  }, [ranges, search, parsed]);

  const isCurrentProduct = (productId: number) =>
    parsed?.type === 'product' && parsed.id === String(productId);

  const isCurrentRange = (rangeId: number) =>
    parsed && (parsed.type === 'range' || parsed.type === 'snap2') && parsed.id === String(rangeId);

  if (manualMode) {
    return (
      <div style={{ padding: '12px 0' }}>
        <label htmlFor="ov25-manual-link" style={{ display: 'block', marginBottom: '6px', fontWeight: 600 }}>
          OV25 Product ID
        </label>
        <input
          id="ov25-manual-link"
          type="text"
          value={link}
          onChange={(e) => { setLink(e.target.value); syncHidden('_ov25_product_id', e.target.value); }}
          placeholder="e.g. 97, range/16, snap2/16"
          style={{ width: '100%', maxWidth: '400px' }}
        />
        {error && <p style={{ color: '#b32d2e', marginTop: '6px', fontSize: '12px' }}>Could not load products: {error}</p>}
      </div>
    );
  }

  return (
    <div style={{ padding: '12px 0' }}>
      {/* Linked state - loading */}
      {link && !pickerOpen && loading && (
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 14px', background: '#f0f0f1', borderRadius: '6px', border: '1px solid #ddd' }}>
          <div style={{ flex: 1, fontSize: '13px', color: '#666' }}>Loading product info...</div>
          <button type="button" className="button button-small" onClick={handleUnlink} style={{ color: '#b32d2e' }}>Unlink</button>
        </div>
      )}

      {/* Linked state - loaded */}
      {link && linkedInfo && !pickerOpen && !loading && (
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 14px', background: '#f0f0f1', borderRadius: '6px', border: '1px solid #ddd' }}>
          {linkedInfo.thumbnail && (
            <img src={linkedInfo.thumbnail} alt="" style={{ width: '40px', height: '40px', borderRadius: '4px', objectFit: 'cover' }} />
          )}
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontWeight: 600, fontSize: '13px' }}>{linkedInfo.name}</div>
            {linkedInfo.rangeName && <div style={{ fontSize: '12px', color: '#666' }}>{linkedInfo.rangeName}</div>}
          </div>
          <span style={{
            padding: '2px 8px', borderRadius: '10px', fontSize: '11px', fontWeight: 600,
            color: '#fff', background: BADGE_COLORS[linkedInfo.type] || '#666',
          }}>
            {linkedInfo.type}
          </span>
          <button type="button" className="button button-small" onClick={() => setPickerOpen(true)}>Change</button>
          <button type="button" className="button button-small" onClick={handleUnlink} style={{ color: '#b32d2e' }}>Unlink</button>
        </div>
      )}

      {/* Empty state */}
      {!link && !pickerOpen && (
        <button type="button" className="button button-primary" onClick={() => setPickerOpen(true)}>
          Link OV25 Product
        </button>
      )}

      {/* Picker */}
      {pickerOpen && (
        <div style={{ border: '1px solid #ddd', borderRadius: '6px', background: '#fff', maxHeight: '400px', display: 'flex', flexDirection: 'column' }}>
          <div style={{ padding: '8px', borderBottom: '1px solid #eee', display: 'flex', gap: '8px' }}>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search products, ranges..."
              style={{ flex: 1 }}
              autoFocus
            />
            <button type="button" className="button button-small" onClick={() => { setPickerOpen(false); setSearch(''); }}>
              Cancel
            </button>
          </div>
          <div style={{ overflowY: 'auto', flex: 1 }}>
            {loading && <div style={{ padding: '20px', textAlign: 'center', color: '#666' }}>Loading products...</div>}
            {!loading && sortedRanges.length === 0 && (
              <div style={{ padding: '20px', textAlign: 'center', color: '#666' }}>
                {search ? 'No matching products found.' : 'No products available. Check your private API key in OV25 settings.'}
              </div>
            )}
            {sortedRanges.map((range) => (
              <div key={range.id}>
                <div style={{
                  padding: '6px 12px', borderBottom: '1px solid #eee',
                  display: 'flex', alignItems: 'center', gap: '8px',
                  background: isCurrentRange(range.id) ? '#e6f0ff' : '#f9f9f9',
                }}>
                  <strong style={{ flex: 1, fontSize: '12px' }}>{range.name}</strong>
                  <span style={{ fontSize: '11px', color: '#888' }}>{range.manufacturerName}</span>
                  <button
                    type="button"
                    onClick={() => handleSelect('range', range.id)}
                    style={{ background: 'none', border: '1px solid #ccc', borderRadius: '4px', padding: '2px 8px', fontSize: '11px', cursor: 'pointer' }}
                  >
                    Link Range
                  </button>
                  {range.snap2Active && (
                    <button
                      type="button"
                      onClick={() => handleSelect('snap2', range.id)}
                      style={{ background: '#9333ea', color: '#fff', border: 'none', borderRadius: '4px', padding: '2px 8px', fontSize: '11px', cursor: 'pointer' }}
                    >
                      Link Snap2
                    </button>
                  )}
                </div>
                {range.products.map((p) => {
                  const isCurrent = isCurrentProduct(p.id);
                  return (
                    <div
                      key={p.id}
                      onClick={() => handleSelect('product', p.id)}
                      style={{
                        display: 'flex', alignItems: 'center', gap: '10px', padding: '8px 12px 8px 24px',
                        borderBottom: '1px solid #f0f0f0', cursor: 'pointer',
                        background: isCurrent ? '#e6f0ff' : '',
                        borderLeft: isCurrent ? '3px solid #2271b1' : '3px solid transparent',
                      }}
                      onMouseEnter={(e) => { if (!isCurrent) e.currentTarget.style.background = '#f0f6fc'; }}
                      onMouseLeave={(e) => { if (!isCurrent) e.currentTarget.style.background = ''; }}
                    >
                      {p.thumbnail ? (
                        <img src={p.thumbnail} alt="" style={{ width: '32px', height: '32px', borderRadius: '4px', objectFit: 'cover' }} />
                      ) : (
                        <div style={{ width: '32px', height: '32px', borderRadius: '4px', background: '#e0e0e0', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '14px', color: '#999' }}>◻</div>
                      )}
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontSize: '13px', fontWeight: 500 }}>
                          {p.name}
                          {isCurrent && <span style={{ marginLeft: '6px', fontSize: '11px', color: '#2271b1', fontWeight: 600 }}>● current</span>}
                        </div>
                        {p.category && <div style={{ fontSize: '11px', color: '#888' }}>{p.category}</div>}
                      </div>
                      <div style={{ width: '8px', height: '8px', borderRadius: '50%', background: p.hasConfigurator ? '#00a32a' : '#ccc', flexShrink: 0 }} title={p.hasConfigurator ? 'Configurator active' : 'No configurator'} />
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Override toggle */}
      {link && !pickerOpen && !loading && (
        <div style={{ marginTop: '12px' }}>
          <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize: '13px' }}>
            <input type="checkbox" checked={useCustom} onChange={handleToggleCustom} />
            Override global configurator settings for this product
          </label>
          {useCustom && (
            <div style={{ marginTop: '8px', marginLeft: '26px' }}>
              <button type="button" className="button" onClick={() => setModalOpen(true)}>
                Edit Product Configurator Settings
              </button>
            </div>
          )}
        </div>
      )}

      {/* Fullscreen ConfiguratorSetup modal */}
      {modalOpen && (
        <div
          style={{
            position: 'fixed', inset: 0, zIndex: 100000,
            background: '#f0f0f1', display: 'flex', flexDirection: 'column',
          }}
        >
          <div style={{
            display: 'flex', alignItems: 'center', gap: '12px',
            padding: '12px 20px', background: '#fff', borderBottom: '1px solid #ddd',
            boxShadow: '0 1px 3px rgba(0,0,0,.08)',
          }}>
            <h2 style={{ margin: 0, fontSize: '16px', flex: 1 }}>
              Product Configurator Settings
              {linkedInfo && <span style={{ fontWeight: 400, color: '#666', marginLeft: '8px' }}>— {linkedInfo.name}</span>}
            </h2>
            {saving && <span style={{ fontSize: '13px', color: '#666' }}>Saving...</span>}
            {saveStatus === 'saved' && <span style={{ fontSize: '13px', color: '#00a32a', fontWeight: 600 }}>Saved!</span>}
            {saveStatus === 'error' && <span style={{ fontSize: '13px', color: '#b32d2e', fontWeight: 600 }}>Save failed</span>}
            <button type="button" className="button" onClick={() => setModalOpen(false)}>Close</button>
          </div>
          <div style={{ flex: 1, overflow: 'hidden', minHeight: 0 }}>
            {editorMountKey && (
              <ConfiguratorSetup
                key={editorMountKey}
                onSave={handleConfigSave}
              />
            )}
          </div>
        </div>
      )}
    </div>
  );
}
