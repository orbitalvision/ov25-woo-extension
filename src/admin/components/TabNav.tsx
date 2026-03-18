interface Tab {
  id: string;
  label: string;
}

interface TabNavProps {
  tabs: readonly Tab[];
  activeTab: string;
  onTabChange: (id: string) => void;
}

export function TabNav({ tabs, activeTab, onTabChange }: TabNavProps) {
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
