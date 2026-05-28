interface Tab<TId extends string = string> {
  id: TId;
  label: string;
}

interface TabNavProps<TId extends string> {
  tabs: readonly Tab<TId>[];
  activeTab: TId;
  onTabChange: (id: TId) => void;
}

export function TabNav<TId extends string>({ tabs, activeTab, onTabChange }: TabNavProps<TId>) {
  return (
    <div className="ov25-tab-nav">
      {tabs.map((tab) => (
        <button
          key={tab.id}
          type="button"
          className={`ov25-tab-btn ${activeTab === tab.id ? 'ov25-tab-btn--active' : ''}`}
          onClick={() => onTabChange(tab.id)}
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}
