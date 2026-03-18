import { useState, useEffect, useCallback } from 'react';
import { api } from '../lib/api';

export function useSettings() {
  const [settings, setSettings] = useState<Record<string, unknown> | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchSettings = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const data = await api.getSettings();
      setSettings(data as Record<string, unknown>);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to load settings');
    } finally {
      setLoading(false);
    }
  }, []);

  const saveSettings = useCallback(async (data: Record<string, unknown>) => {
    try {
      setSaving(true);
      setError(null);
      await api.saveSettings(data);
      setSettings((prev) => ({ ...prev, ...data }));
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

  return { settings, loading, saving, error, saveSettings, refetch: fetchSettings };
}
