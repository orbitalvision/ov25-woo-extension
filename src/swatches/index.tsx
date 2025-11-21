import React, { useState, useEffect, useRef, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import { Plus, Minus, Trash, ZoomIn, X, ChevronDown, ChevronRight, Search, Info } from 'lucide-react';
import { toast, Toaster } from 'sonner';
import './swatches.css';

type Swatch = {
  id: number;
  name: string;
  organizationId: number;
  manufacturerId: number;
  option: string;
  description: string | null;
  tags: string[];
  sku: string;
  thumbnail: {
    blurHash?: string;
    thumbnail?: string;
    miniThumbnails?: { large?: string; medium?: string; small?: string };
  };
};

type SwatchRulesData = {
  freeSwatchLimit: number; 
  canExeedFreeLimit: boolean;
  pricePerSwatch: number; 
  minSwatches: number;
  maxSwatches: number;
  enabled: boolean; 
}

const ImageDialog: React.FC<{ 
  isOpen: boolean; 
  onClose: () => void; 
  imageSrc: string; 
  imageAlt: string; 
  description?: string; 
}> = ({ isOpen, onClose, imageSrc, imageAlt, description }) => {

  // Prevent body scrolling when dialog is open
  React.useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = '';
      };
    }
  }, [isOpen]);

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      onClose();
    }
  };

  if (!isOpen) return null;

  return (
    <div 
      className="ov25-image-dialog-overlay"
      onClick={handleBackdropClick}
      onKeyDown={handleKeyDown}
      tabIndex={-1}
    >
      <div className="ov25-image-dialog">
        <button
          className="ov25-image-dialog-close"
          onClick={onClose}
          aria-label="Close dialog"
        >
          <X size={24} />
        </button>
        <img 
          src={imageSrc}
          alt={imageAlt}
          className="ov25-image-dialog-image"
        />
        {description && (
          <div className="ov25-image-dialog-description">
            {description}
          </div>
        )}
      </div>
    </div>
  );
};

declare global {
  interface Window {
    ov25SwatchesPage?: { 
      restBase: string; 
      swatchesUrl: string;
      swatchRulesUrl: string;
      nonce: string;
      customCSS?: string;
    };
  }
}

async function fetchSwatches(): Promise<Swatch[]> {
  try {
    // Use full URL from PHP which handles both pretty and plain permalinks
    const swatchesUrl = window.ov25SwatchesPage?.swatchesUrl || 
      `${window.ov25SwatchesPage?.restBase || '/wp-json'}/ov25/v1/swatches`;
    
    const response = await fetch(swatchesUrl, {
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();
    return data.swatches[0];
  } catch (error) {
    console.error('fetchSwatches: Error fetching swatches:', error);
    return [];
  }
}

async function getSwatchRules(): Promise<SwatchRulesData | null> {
  try {
    // Use full URL from PHP which handles both pretty and plain permalinks
    const rulesUrl = window.ov25SwatchesPage?.swatchRulesUrl || 
      `${window.ov25SwatchesPage?.restBase || '/wp-json'}/ov25/v1/swatch-rules`;
    
    const response = await fetch(rulesUrl, {
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const result = await response.json();
    return result.swatchRules;
  } catch (error) {
    console.error('getSwatchRules: Error fetching swatch rules:', error);
    return null;
  }
}

async function createSwatchOnlyCart(swatches: Swatch[], rules: SwatchRulesData): Promise<string | undefined> {
  try {
    const swatchCartData = {
      swatches: swatches,
      rules: rules,
      timestamp: Date.now()
    };

    const response = await fetch(window.location.origin + '/wp-admin/admin-ajax.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'action=ov25_create_swatch_cart&swatch_data=' + encodeURIComponent(JSON.stringify(swatchCartData))
    });

    if (!response.ok) {
      throw new Error('Network error creating swatch cart');
    }

    const result = await response.json();
    if (!result?.success) {
      throw new Error(result?.data || 'Failed to create swatch cart');
    }

    const checkoutUrl = result.data?.checkout_url as string | undefined;
    if (checkoutUrl) { (window as any).ov25CheckoutUrl = checkoutUrl; }
    return checkoutUrl;
  } catch (error) {
    throw error;
  }
}

const SwatchCard: React.FC<{ 
  swatch: Swatch; 
  isInCart: boolean; 
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>;
  selectedSwatches: Swatch[];
  swatchRules: SwatchRulesData | null;
}> = ({ swatch, isInCart, setSelectedSwatches, selectedSwatches, swatchRules }) => {
  const imgSrc = swatch.thumbnail?.miniThumbnails?.medium;
  const fullSizeImgSrc = swatch.thumbnail?.thumbnail;
  const [isDialogOpen, setIsDialogOpen] = useState(false);

  const handleAddClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.stopPropagation();
    
    if (swatchRules) {
      const currentCount = selectedSwatches.length;
      const maxAllowed = swatchRules.canExeedFreeLimit 
        ? swatchRules.maxSwatches 
        : Math.min(swatchRules.freeSwatchLimit, swatchRules.maxSwatches);
      
      if (currentCount >= maxAllowed) {
        const maxSwatches = swatchRules.canExeedFreeLimit ? swatchRules.maxSwatches : swatchRules.freeSwatchLimit;
        toast.error(`Cart is full! You can only have ${maxSwatches} samples.`);
        return;
      }
    }

    const updatedSwatches = [...selectedSwatches, swatch];
    setSelectedSwatches(updatedSwatches);
    localStorage.setItem('ov25-selected-swatches', JSON.stringify(updatedSwatches));
    toast.success(`${swatch.name} added to cart`);
  };

  const handleRemoveClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.stopPropagation();
    
    const updatedSwatches = selectedSwatches.filter(s => 
      s.manufacturerId !== swatch.manufacturerId || 
      s.option !== swatch.option || 
      s.name !== swatch.name
    );
    setSelectedSwatches(updatedSwatches);
    localStorage.setItem('ov25-selected-swatches', JSON.stringify(updatedSwatches));
  };

  return (
    <>
      <div className="ov25-swatch-card">
        <div className="ov25-swatch-image-container">
          {imgSrc ? (
            <img src={imgSrc} alt={swatch.name} className="ov25-swatch-image" />
          ) : (
            <div className="ov25-swatch-image-placeholder">No image</div>
          )}
          {fullSizeImgSrc && (
            <button
              type="button"
              className="ov25-swatch-zoom-overlay"
              onClick={(e) => {
                e.stopPropagation();
                setIsDialogOpen(true);
              }}
              aria-label={`View full size image of ${swatch.name}`}
            >
              <ZoomIn size={24} />
            </button>
          )}
        </div>
        <div className="ov25-swatch-content">
          <div className="ov25-swatch-header">
            <div className="ov25-swatch-name">{swatch.name}</div>
            {isInCart ? (
              <button
                type="button"
                className="ov25-swatch-remove-btn"
                aria-label={`Remove ${swatch.name}`}
                onClick={handleRemoveClick}
              >
                <Minus size={18} />
              </button>
            ) : (
              <button
                type="button"
                className="ov25-swatch-add-btn"
                aria-label={`Add ${swatch.name}`}
                onClick={handleAddClick}
              >
                <Plus size={18} />
              </button>
            )}
          </div>
          {swatch.description && <div className="ov25-swatch-description">{swatch.description}</div>}
        </div>
      </div>
      
      <ImageDialog
        isOpen={isDialogOpen}
        onClose={() => setIsDialogOpen(false)}
        imageSrc={fullSizeImgSrc || ''}
        imageAlt={swatch.name}
        description={swatch.description || undefined}
      />
    </>
  );
};

const CartPanel: React.FC<{ 
  selectedSwatches: Swatch[]; 
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>; 
  swatchRules: SwatchRulesData | null; 
  rulesLoading: boolean; 
}> = ({ selectedSwatches, setSelectedSwatches, swatchRules, rulesLoading }) => {
  const [isProcessing, setIsProcessing] = useState(false);

  const handleBuyNow = async () => {
    if (selectedSwatches.length === 0) {
      toast.error('No swatches selected');
      return;
    }

    if (!swatchRules) {
      toast.error('Swatch rules not loaded. Please refresh the page.');
      return;
    }

    if (!swatchRules.enabled) {
      toast.error('Swatch purchasing is currently disabled');
      return;
    }

    setIsProcessing(true);
    try {
      const checkoutUrl = await createSwatchOnlyCart(selectedSwatches, swatchRules);
      const redirectUrl = checkoutUrl || (window as any).ov25CheckoutUrl || window.location.origin + '/checkout/';
      window.location.href = redirectUrl;
    } catch (error) {
      console.error('Failed to process swatch purchase:', error);
      toast.error('Failed to process purchase. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleRemoveSwatch = (swatch: Swatch) => {
    const updatedSwatches = selectedSwatches.filter(s => s.manufacturerId !== swatch.manufacturerId || s.option !== swatch.option || s.name !== swatch.name);
    setSelectedSwatches(updatedSwatches);
    localStorage.setItem('ov25-selected-swatches', JSON.stringify(updatedSwatches));
  };

  const maxAllowed = swatchRules?.canExeedFreeLimit
    ? swatchRules.maxSwatches
    : Math.min(swatchRules?.freeSwatchLimit || 0, swatchRules?.maxSwatches || 0);
  return (
    <div className="ov25-swatches-cart">
      <h2 className="ov25-cart-title">Cart ({selectedSwatches.length}/{maxAllowed})</h2>
      
      <div style={{ flex: 1, overflowY: 'auto' }}>
        {selectedSwatches.length === 0 ? (
          <p className="ov25-cart-empty">No swatches selected</p>
        ) : (
          <div className="ov25-cart-list">
            {selectedSwatches.map((swatch) => {
              const imgSrc = swatch.thumbnail?.miniThumbnails?.small;
              return (
                <div key={swatch.id} className="ov25-cart-item">
                  {imgSrc ? (
                    <img 
                      src={imgSrc} 
                      alt={swatch.name}
                      className="ov25-cart-thumb"
                    />
                  ) : (
                    <div className="ov25-cart-thumb ov25-cart-thumb--placeholder">
                      No img
                    </div>
                  )}
                  <div className="ov25-cart-item-body">
                    <div className="ov25-cart-item-name">{swatch.name}</div>
                    {swatch.option && <div className="ov25-cart-item-option">{swatch.option}</div>}
                  </div>
                  <button
                    onClick={() => handleRemoveSwatch(swatch)}
                    className="ov25-cart-remove"
                  >
                    <Trash size={18} />
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {selectedSwatches.length > 0 && (
        <div className="ov25-cart-footer">
          {swatchRules && (
            <div className="ov25-cart-info">
              <div className="ov25-cart-limits">
                <span>First {swatchRules.freeSwatchLimit} samples are free</span>
              </div>
              <div className="ov25-cart-price">
                {(() => {
                  const totalSwatches = selectedSwatches.length;
                  const freeSwatches = Math.min(swatchRules.freeSwatchLimit, totalSwatches);
                  const paidSwatches = Math.max(0, totalSwatches - freeSwatches);
                  const totalPrice = paidSwatches * swatchRules.pricePerSwatch;
                  
                  if (paidSwatches === 0) {
                    return <span className="ov25-price-free">Free</span>;
                  } else {
                    return (
                      <span className="ov25-price-total">
                        {paidSwatches > 0 && (
                          <span className="ov25-price-breakdown">
                            ({paidSwatches} × £{swatchRules.pricePerSwatch.toFixed(2)})
                          </span>
                        )}
                        Total: £{totalPrice.toFixed(2)}
                      </span>
                    );
                  }
                })()}
              </div>
            </div>
          )}
          <button
            onClick={handleBuyNow}
            className="ov25-cart-buy-btn"
            disabled={isProcessing || rulesLoading}
          >
            {isProcessing ? 'Processing...' : rulesLoading ? 'Loading...' : 'Order Samples'}
          </button>
        </div>
      )}
    </div>
  );
};

const SwatchesApp: React.FC<{ 
  selectedSwatches: Swatch[]; 
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>;
  swatchRules: SwatchRulesData | null;
}> = ({ selectedSwatches, setSelectedSwatches, swatchRules }) => {
  const [allSwatches, setAllSwatches] = useState<Swatch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const [searchText, setSearchText] = useState('');
  const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set());

  const loadSwatches = async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchSwatches();
      setAllSwatches(data);
    } catch (err) {
      setError('Failed to load swatches');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSwatches();
  }, []);

  const handleSearch = () => {
    const query = (searchInputRef.current?.value || '').trim();
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    searchTimeoutRef.current = setTimeout(() => {
      setSearchText(query);
    }, 150);
  };

  const filteredSwatches = useMemo(() => {
    if (!searchText) return allSwatches;
    const q = searchText.toLowerCase();
    return allSwatches.filter((s) => {
      const hay = [
        s.name,
        s.option,
        s.description || '',
        ...(Array.isArray(s.tags) ? s.tags : [])
      ].join(' ').toLowerCase();
      return hay.includes(q);
    });
  }, [allSwatches, searchText]);

  const groupedSwatches = useMemo(() => {
    return filteredSwatches.reduce((groups, swatch) => {
      const option = swatch.option || 'Uncategorized';
      if (!groups[option]) groups[option] = [];
      groups[option].push(swatch);
      return groups;
    }, {} as Record<string, Swatch[]>);
  }, [filteredSwatches]);

  const sortedGroups = useMemo(() => Object.keys(groupedSwatches).sort(), [groupedSwatches]);

  const toggleGroup = (option: string) => {
    setCollapsedGroups(prev => {
      const newSet = new Set(prev);
      if (newSet.has(option)) {
        newSet.delete(option);
      } else {
        newSet.add(option);
      }
      return newSet;
    });
  };

  return (
    <div className="ov25-swatches-container">
      <div className="ov25-swatches-header">
        <div className="ov25-search-container">
          <Search size={20} className="ov25-search-icon" />
          <input
            id="ov25-swatches-search"
            ref={searchInputRef}
            type="search"
            placeholder="Search swatches..."
            onChange={handleSearch}
          />
        </div>
      </div>

      {error && <div className="ov25-swatches-error">{error}</div>}
      {loading && <div className="ov25-swatches-loading" aria-live="polite">Loading swatches...</div>}
      
      {(!swatchRules?.enabled || (!loading && filteredSwatches.length === 0)) && (
        <div className="ov25-swatches-empty">{swatchRules?.enabled ? 'No swatches found' : 'Swatch purchasing is currently disabled'}</div>
      )}

      {!loading && swatchRules?.enabled && filteredSwatches.length > 0 && (
        <div className="ov25-swatches-groups">
          {sortedGroups.map((option) => {
            const isCollapsed = collapsedGroups.has(option);
            const swatchCount = groupedSwatches[option].length;
            
            return (
              <div key={option} className="ov25-swatch-group">
                <button
                  className="ov25-swatch-group-header"
                  onClick={() => toggleGroup(option)}
                  aria-expanded={!isCollapsed}
                >
                  <div className="ov25-swatch-group-title">
                    {isCollapsed ? <ChevronRight size={20} /> : <ChevronDown size={20} />}
                    <span>{option}</span>
                    <span className="ov25-swatch-group-count">({swatchCount})</span>
                  </div>
                </button>
                {!isCollapsed && (
                  <div className="ov25-swatches-grid">
                    {groupedSwatches[option].map((swatch) => {
                      const isInCart = selectedSwatches.some((s: Swatch) => 
                        s.manufacturerId === swatch.manufacturerId && 
                        s.option === swatch.option && 
                        s.name === swatch.name
                      );
                      return (
                        <SwatchCard 
                          key={swatch.id} 
                          swatch={swatch} 
                          isInCart={isInCart}
                          setSelectedSwatches={setSelectedSwatches}
                          selectedSwatches={selectedSwatches}
                          swatchRules={swatchRules}
                        />
                      );
                    })}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

const App: React.FC = () => {
  const [selectedSwatches, setSelectedSwatches] = React.useState<Swatch[]>([]);
  const [swatchRules, setSwatchRules] = React.useState<SwatchRulesData | null>(null);
  const [rulesLoading, setRulesLoading] = React.useState(false);

  // Load initial selected swatches from localstorage
  React.useEffect(() => {
    const loadSelectedSwatches = () => {
      try {
        const stored = localStorage.getItem('ov25-selected-swatches');
        if (stored) {
          const swatches = JSON.parse(stored);
          setSelectedSwatches(Array.isArray(swatches) ? swatches : []);
        }
      } catch (error) {
        console.error('Error loading selected swatches:', error);
        setSelectedSwatches([]);
      }
    };

    loadSelectedSwatches();

    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'ov25-selected-swatches') {
        loadSelectedSwatches();
      }
    };

    window.addEventListener('storage', handleStorageChange);
    return () => window.removeEventListener('storage', handleStorageChange);
  }, []);

  // Load swatch rules once on mount
  React.useEffect(() => {
    const loadRules = async () => {
      setRulesLoading(true);
      try {
        const rules = await getSwatchRules();
        setSwatchRules(rules);
      } catch (error) {
        console.error('App: Failed to load swatch rules:', error);
      } finally {
        setRulesLoading(false);
      }
    };

    loadRules();
  }, []);

  return (
    <>
      <Toaster 
        position="top-center"
        richColors
      />
        <SwatchesApp 
          selectedSwatches={selectedSwatches} 
          setSelectedSwatches={setSelectedSwatches} 
          swatchRules={swatchRules}
        />
      <CartPanel 
        selectedSwatches={selectedSwatches} 
        setSelectedSwatches={setSelectedSwatches}
        swatchRules={swatchRules}
        rulesLoading={rulesLoading}
      />
    </>
  );
};

document.addEventListener('DOMContentLoaded', () => {
  const mount = document.getElementById('ov25-swatches-app');
  if (!mount) return;

  // Inject custom CSS if available
  const customCSS = window.ov25SwatchesPage?.customCSS;
  if (customCSS) {
    const styleId = 'ov25-swatches-custom-css';
    let styleElement = document.getElementById(styleId) as HTMLStyleElement;
    if (!styleElement) {
      styleElement = document.createElement('style');
      styleElement.id = styleId;
      document.head.appendChild(styleElement);
    }
    styleElement.textContent = customCSS;
  }

  const root = createRoot(mount);
  root.render(<App />);
});




