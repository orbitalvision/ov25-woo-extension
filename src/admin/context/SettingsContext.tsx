import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { api } from '../lib/api';

type SettingsContextValue = {
  settings: Record<string, unknown> | null;
  loading: boolean;
  saving: boolean;
  error: string | null;
  saveSettings: (data: Record<string, unknown>) => Promise<void>;
  refetch: () => Promise<void>;
};

const SettingsContext = createContext<SettingsContextValue | null>(null);

export function SettingsProvider({ children }: { children: ReactNode }) {
  const [settings, setSettings] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchSettings = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await api.getSettings();
      const normalized = data != null && typeof data === 'object' && !Array.isArray(data)
        ? (data as Record<string, unknown>)
        : {};
      setSettings(normalized);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to load settings');
      setSettings(null);
    } finally {
      setLoading(false);
    }
  }, []);

  const saveSettings = useCallback(async (data: Record<string, unknown>) => {
    try {
      setSaving(true);
      setError(null);
      await api.saveSettings(data);
      setSettings((prev) => ({ ...(prev ?? {}), ...data }));
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to save settings');
      throw err;
    } finally {
      setSaving(false);
    }
  }, []);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const value: SettingsContextValue = {
    settings,
    loading,
    saving,
    error,
    saveSettings,
    refetch: fetchSettings,
  };

  return (
    <SettingsContext.Provider value={value}>
      {children}
    </SettingsContext.Provider>
  );
}

export function useSettingsContext(): SettingsContextValue {
  const ctx = useContext(SettingsContext);
  if (!ctx) throw new Error('useSettingsContext must be used within SettingsProvider');
  return ctx;
}
