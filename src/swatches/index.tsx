import React, { useState, useEffect, useRef, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import { ZoomIn, X, ChevronDown, ChevronRight, Search, ListFilter } from 'lucide-react';
import { toast, Toaster } from 'sonner';
import { stringSimilarity } from 'string-similarity-js';
import './swatches.css';

type Swatch = {
  id: number;
  name: string;
  organizationId: number;
  manufacturerId: number;
  option: string;
  group: string;
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

  // Handle ESC key to close dialog
  React.useEffect(() => {
    if (!isOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen, onClose]);

  const handleBackdropClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  if (!isOpen) return null;

  return (
    <div 
      className="ov25-image-dialog-overlay"
      onClick={handleBackdropClick}
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

const ReplaceDialog: React.FC<{
  isOpen: boolean;
  onClose: () => void;
  cartSwatches: Swatch[];
  newSwatch: Swatch;
  onReplace: (oldSwatch: Swatch) => void;
}> = ({ isOpen, onClose, cartSwatches, newSwatch, onReplace }) => {
  // Prevent body scrolling when dialog is open
  React.useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = '';
      };
    }
  }, [isOpen]);

  // Handle ESC key to close dialog
  React.useEffect(() => {
    if (!isOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div 
      className="ov25-image-dialog-overlay"
      onClick={(e) => {
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="ov25-replace-dialog">
        <button
          className="ov25-replace-dialog-close"
          onClick={onClose}
          aria-label="Close dialog"
        >
          <X size={24} />
        </button>
        <h2 className="ov25-replace-dialog-title">Your cart is full</h2>
        <p className="ov25-replace-dialog-subtitle">
          Select a swatch to replace with <strong>{newSwatch.name}</strong>
        </p>
        <div className="ov25-replace-dialog-list">
          {cartSwatches.map((cartSwatch) => {
            const imgSrc = cartSwatch.thumbnail?.miniThumbnails?.medium;
            return (
              <div
                key={cartSwatch.id}
                className="ov25-replace-dialog-item"
                onClick={() => {
                  onReplace(cartSwatch);
                  onClose();
                }}
              >
                <div className="ov25-replace-dialog-thumb-container">
                  {imgSrc ? (
                    <img 
                      src={imgSrc} 
                      alt={cartSwatch.name}
                      className="ov25-replace-dialog-thumb"
                    />
                  ) : (
                    <div className="ov25-replace-dialog-thumb ov25-replace-dialog-thumb--placeholder">
                      No img
                    </div>
                  )}
                  <div className="ov25-cart-thumb-gradient ov25-swatch-gradient-radial"></div>
                  <div className="ov25-cart-thumb-gradient ov25-swatch-gradient-shadow"></div>
                </div>
                <div className="ov25-replace-dialog-item-name">{cartSwatch.name}</div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};

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
  const [isReplaceDialogOpen, setIsReplaceDialogOpen] = useState(false);

  const handleAddClick = (e: React.MouseEvent<HTMLElement>) => {
    e.stopPropagation();
    
    if (swatchRules) {
      const currentCount = selectedSwatches.length;
      const maxAllowed = swatchRules.canExeedFreeLimit 
        ? swatchRules.maxSwatches 
        : Math.min(swatchRules.freeSwatchLimit, swatchRules.maxSwatches);
      
      if (currentCount >= maxAllowed) {
        setIsReplaceDialogOpen(true);
        return;
      }
    }

    const updatedSwatches = [...selectedSwatches, swatch];
    setSelectedSwatches(updatedSwatches);
    localStorage.setItem('ov25-selected-swatches', JSON.stringify(updatedSwatches));
    toast.success(`${swatch.name} added to cart`);
  };

  const handleReplace = (oldSwatch: Swatch) => {
    const updatedSwatches = selectedSwatches.map(s => 
      s.manufacturerId === oldSwatch.manufacturerId && 
      s.option === oldSwatch.option && 
      s.name === oldSwatch.name
        ? swatch
        : s
    );
    setSelectedSwatches(updatedSwatches);
    localStorage.setItem('ov25-selected-swatches', JSON.stringify(updatedSwatches));
    toast.success(`${oldSwatch.name} replaced with ${swatch.name}`);
  };

  const handleRemoveClick = (e: React.MouseEvent<HTMLElement>) => {
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
            <img 
              src={imgSrc} 
              alt={swatch.name} 
              className="ov25-swatch-image"
              onClick={(e) => {
                if (isInCart) {
                  e.stopPropagation();
                  handleRemoveClick(e);
                } else {
                  e.stopPropagation();
                  handleAddClick(e);
                }
              }}
            />
          ) : (
            <div 
              className="ov25-swatch-image-placeholder"
              onClick={(e) => {
                if (isInCart) {
                  e.stopPropagation();
                  handleRemoveClick(e);
                } else {
                  e.stopPropagation();
                  handleAddClick(e);
                }
              }}
            >
              No image
            </div>
          )}
          <div 
            className={`ov25-swatch-card-border ${isInCart ? 'ov25-swatch-card-border--selected' : ''}`}
          ></div>
          <div className="ov25-swatch-card-gradient ov25-swatch-gradient-radial"></div>
          <div className="ov25-swatch-card-gradient ov25-swatch-gradient-shadow"></div>
        </div>
        <div className="ov25-swatch-content">
          <div className="ov25-swatch-header">
            <div className="ov25-swatch-name">{swatch.name}</div>
            {fullSizeImgSrc && (
              <button
                type="button"
                className="ov25-swatch-zoom-btn"
                aria-label={`View full size image of ${swatch.name}`}
                onClick={() => setIsDialogOpen(true)}
              >
                <ZoomIn size={20} />
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
      
      <ReplaceDialog
        isOpen={isReplaceDialogOpen}
        onClose={() => setIsReplaceDialogOpen(false)}
        cartSwatches={selectedSwatches}
        newSwatch={swatch}
        onReplace={handleReplace}
      />
    </>
  );
};

// Reusable cart content component
const CartContent: React.FC<{
  selectedSwatches: Swatch[];
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>;
  swatchRules: SwatchRulesData | null;
  rulesLoading: boolean;
  onBuyNow: () => void;
  isProcessing: boolean;
  buttonRef?: React.RefObject<HTMLButtonElement | null>;
}> = ({ selectedSwatches, setSelectedSwatches, swatchRules, rulesLoading, onBuyNow, isProcessing, buttonRef }) => {
  const handleRemoveSwatch = (swatch: Swatch) => {
    const updatedSwatches = selectedSwatches.filter(s => s.manufacturerId !== swatch.manufacturerId || s.option !== swatch.option || s.name !== swatch.name);
    setSelectedSwatches(updatedSwatches);
    localStorage.setItem('ov25-selected-swatches', JSON.stringify(updatedSwatches));
  };

  const maxAllowed = swatchRules?.canExeedFreeLimit
    ? swatchRules.maxSwatches
    : Math.min(swatchRules?.freeSwatchLimit || 0, swatchRules?.maxSwatches || 0);

  return (
    <>
      <h2 className="ov25-cart-title">Cart ({selectedSwatches.length}/{maxAllowed})</h2>
      
      <div className="ov25-cart-list-wrapper">
        <div className="ov25-cart-list">
          {Array.from({ length: maxAllowed }, (_, index) => {
            const swatch = selectedSwatches[index];
            if (swatch) {
              const imgSrc = swatch.thumbnail?.miniThumbnails?.medium;
              return (
                <div key={swatch.id} className="ov25-cart-item">
                  <div className="ov25-cart-thumb-container">
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
                    <div className="ov25-cart-thumb-gradient ov25-swatch-gradient-radial"></div>
                    <div className="ov25-cart-thumb-gradient ov25-swatch-gradient-shadow"></div>
                  </div>
                  <div className="ov25-cart-item-body">
                    <div className="ov25-cart-item-name">{swatch.name}</div>
                    {swatch.option && <div className="ov25-cart-item-option">{swatch.option}</div>}
                  </div>
                  <button
                    onClick={() => handleRemoveSwatch(swatch)}
                    className="ov25-cart-remove"
                  >
                    <X size={32} />
                  </button>
                </div>
              );
            } else {
              return (
                <div key={`empty-${index}`} className="ov25-cart-item-empty">
                  <div className="ov25-cart-empty-slot"></div>
                </div>
              );
            }
          })}
        </div>
      </div>
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
                const maxSwatches = swatchRules.maxSwatches;
                const maxFreeSwatches = Math.min(swatchRules.freeSwatchLimit, maxSwatches);
                const minSwatches = swatchRules.minSwatches;
                const totalPrice = paidSwatches * swatchRules.pricePerSwatch;
                if (selectedSwatches.length < minSwatches && minSwatches > 1) {
                  return <span className="ov25-cart-limits">Minimum of {minSwatches} samples required</span>;
                }
                if (paidSwatches === 0 && maxFreeSwatches < maxSwatches) {
                  return <span className="ov25-price-free">Free</span>;
                } else if (totalPrice > 0) {
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
          ref={buttonRef}
          onClick={onBuyNow}
          className="ov25-cart-buy-btn"
          disabled={(selectedSwatches.length < (swatchRules?.minSwatches || 0)) || isProcessing || rulesLoading}
        >
          {isProcessing ? 'Processing...' : rulesLoading ? 'Loading...' : 'Order Samples'}
        </button>
      </div>
    </>
  );
};

// Cart Dialog component
const CartDialog: React.FC<{
  isOpen: boolean;
  onClose: () => void;
  selectedSwatches: Swatch[];
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>;
  swatchRules: SwatchRulesData | null;
  rulesLoading: boolean;
  onBuyNow: () => void;
  isProcessing: boolean;
}> = ({ isOpen, onClose, selectedSwatches, setSelectedSwatches, swatchRules, rulesLoading, onBuyNow, isProcessing }) => {
  // Prevent body scrolling when dialog is open (mobile only)
  React.useEffect(() => {
    if (isOpen) {
      const isMobile = window.matchMedia('(max-width: 767px)').matches;
      if (isMobile) {
        document.body.style.overflow = 'hidden';
        return () => {
          document.body.style.overflow = '';
        };
      }
    }
  }, [isOpen]);

  // Handle ESC key to close dialog
  React.useEffect(() => {
    if (!isOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div 
      className="ov25-image-dialog-overlay"
      onClick={(e) => {
        if (e.target === e.currentTarget) {
          onClose();
        }
      }}
    >
      <div className="ov25-cart-dialog">
        <button
          className="ov25-image-dialog-close"
          onClick={onClose}
          aria-label="Close cart"
        >
          <X size={24} />
        </button>
        <div className="ov25-swatches-cart">
          <CartContent
            selectedSwatches={selectedSwatches}
            setSelectedSwatches={setSelectedSwatches}
            swatchRules={swatchRules}
            rulesLoading={rulesLoading}
            onBuyNow={onBuyNow}
            isProcessing={isProcessing}
          />
        </div>
      </div>
    </div>
  );
};

const CartPanel: React.FC<{ 
  selectedSwatches: Swatch[]; 
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>; 
  swatchRules: SwatchRulesData | null; 
  rulesLoading: boolean; 
}> = ({ selectedSwatches, setSelectedSwatches, swatchRules, rulesLoading }) => {
  const [isProcessing, setIsProcessing] = useState(false);
  const [dialogSwatch, setDialogSwatch] = useState<Swatch | null>(null);
  const [isNormalButtonVisible, setIsNormalButtonVisible] = useState(true);
  const [isCartDialogOpen, setIsCartDialogOpen] = useState(false);
  const normalButtonRef = useRef<HTMLButtonElement>(null);

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

  // Use IntersectionObserver to track when normal button is visible
  useEffect(() => {
    const button = normalButtonRef.current;
    if (!button) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          setIsNormalButtonVisible(entry.isIntersecting);
        });
      },
      {
        threshold: 0.1, // Trigger when 10% of button is visible
        rootMargin: '0px',
      }
    );

    observer.observe(button);

    return () => {
      observer.disconnect();
    };
  }, []);

  return (
    <div className="ov25-swatches-cart">
      <CartContent
        selectedSwatches={selectedSwatches}
        setSelectedSwatches={setSelectedSwatches}
        swatchRules={swatchRules}
        rulesLoading={rulesLoading}
        onBuyNow={handleBuyNow}
        isProcessing={isProcessing}
        buttonRef={normalButtonRef}
      />
      
      {/* Fixed button - only show when normal button is not visible */}
      <button
        onClick={() => setIsCartDialogOpen(true)}
        className={`ov25-cart-buy-btn-fixed ${!isNormalButtonVisible ? 'ov25-cart-buy-btn-fixed--visible' : ''}`}
        disabled={rulesLoading}
      >
        {rulesLoading ? 'Loading...' : (
          <>
            View Cart
            {swatchRules && (
              <span className="ov25-cart-buy-btn-count">
                {' '}({selectedSwatches.length}/{swatchRules.maxSwatches})
              </span>
            )}
          </>
        )}
      </button>
      
      {dialogSwatch && (
        <ImageDialog
          isOpen={!!dialogSwatch}
          onClose={() => setDialogSwatch(null)}
          imageSrc={dialogSwatch.thumbnail?.thumbnail || ''}
          imageAlt={dialogSwatch.name}
          description={dialogSwatch.description || undefined}
        />
      )}

      <CartDialog
        isOpen={isCartDialogOpen}
        onClose={() => setIsCartDialogOpen(false)}
        selectedSwatches={selectedSwatches}
        setSelectedSwatches={setSelectedSwatches}
        swatchRules={swatchRules}
        rulesLoading={rulesLoading}
        onBuyNow={handleBuyNow}
        isProcessing={isProcessing}
      />
    </div>
  );
};

const FilterPanel: React.FC<{
  isOpen: boolean;
  onClose: () => void;
  allGroups: string[];
  selectedFilters: Set<string>;
  onToggleGroup: (group: string) => void;
}> = ({ isOpen, onClose, allGroups, selectedFilters, onToggleGroup }) => {
  // Prevent body scrolling when dialog is open (mobile only)
  React.useEffect(() => {
    if (isOpen) {
      // Only prevent scrolling on mobile (max-width: 767px)
      const isMobile = window.matchMedia('(max-width: 767px)').matches;
      if (isMobile) {
        document.body.style.overflow = 'hidden';
        return () => {
          document.body.style.overflow = '';
        };
      }
    }
  }, [isOpen]);

  // Handle ESC key to close panel
  React.useEffect(() => {
    if (!isOpen) return;

    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen, onClose]);

  return (
    <div className={`ov25-filter-panel ${isOpen ? 'ov25-filter-panel--open' : ''}`}>
      <div className="ov25-filter-panel-header">
        <h2 className="ov25-filter-panel-title">Filters</h2>
        <button
          className="ov25-filter-panel-close"
          onClick={onClose}
          aria-label="Close filters"
        >
          <X size={24} />
        </button>
      </div>
      <div className="ov25-filter-pills-container">
        {allGroups.map((group) => {
          const isSelected = selectedFilters.has(group);
          return (
            <button
              key={group}
              className={`ov25-filter-pill ${isSelected ? 'ov25-filter-pill--selected' : ''}`}
              data-selected={isSelected ? 'true' : 'false'}
              onClick={() => onToggleGroup(group)}
            >
              {group}
            </button>
          );
        })}
      </div>
    </div>
  );
};

const SwatchesApp: React.FC<{ 
  selectedSwatches: Swatch[]; 
  setSelectedSwatches: React.Dispatch<React.SetStateAction<Swatch[]>>;
  swatchRules: SwatchRulesData | null;
  rulesLoading: boolean;
}> = ({ selectedSwatches, setSelectedSwatches, swatchRules, rulesLoading }) => {
  const [allSwatches, setAllSwatches] = useState<Swatch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const [searchText, setSearchText] = useState('');
  const [collapsedGroups, setCollapsedGroups] = useState<Set<string>>(new Set());
  const [isFilterPanelOpen, setIsFilterPanelOpen] = useState(false);
  const [selectedFilters, setSelectedFilters] = useState<Set<string>>(new Set());

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

  // Extract unique groups from all swatches, excluding empty strings
  const allGroups = useMemo(() => {
    const groups = new Set<string>();
    allSwatches.forEach((swatch) => {
      if (swatch.group && swatch.group.trim() !== '') {
        groups.add(swatch.group);
      }
    });
    return Array.from(groups).sort();
  }, [allSwatches]);

  const handleToggleGroup = (group: string) => {
    setSelectedFilters((prev) => {
      const newSet = new Set(prev);
      if (newSet.has(group)) {
        newSet.delete(group);
      } else {
        newSet.add(group);
      }
      return newSet;
    });
  };

  const handleRemoveFilter = (group: string) => {
    setSelectedFilters((prev) => {
      const newSet = new Set(prev);
      newSet.delete(group);
      return newSet;
    });
  };

  const filteredSwatches = useMemo(() => {
    let filtered = allSwatches;

    // Apply search filter using string-similarity-js
    if (searchText) {
      const searchLower = searchText.toLowerCase();
      const scored = filtered.map((swatch) => {
        const nameScore = stringSimilarity(searchLower, (swatch.name || '').toLowerCase());
        const skuScore = stringSimilarity(searchLower, (swatch.sku || '').toLowerCase());
        const maxScore = Math.max(nameScore, skuScore);
        return { swatch, score: maxScore };
      });
      
      // Filter out results with score 0 (no match) and sort by score descending
      filtered = scored
        .filter((item) => item.score > 0)
        .sort((a, b) => b.score - a.score)
        .map((item) => item.swatch);
    }

    // Apply group filter
    if (selectedFilters.size > 0) {
      filtered = filtered.filter((s) => selectedFilters.has(s.group));
    }

    return filtered;
  }, [allSwatches, searchText, selectedFilters]);

  const groupedSwatches = useMemo(() => {
    // First group by option
    const byOption = filteredSwatches.reduce((groups, swatch) => {
      const option = swatch.option || 'Uncategorized';
      if (!groups[option]) groups[option] = [];
      groups[option].push(swatch);
      return groups;
    }, {} as Record<string, Swatch[]>);
    
    // Then within each option, group by group property
    const result: Record<string, Record<string, Swatch[]>> = {};
    for (const [option, swatches] of Object.entries(byOption)) {
      result[option] = swatches.reduce((groups, swatch) => {
        const group = swatch.group || '';
        if (!groups[group]) groups[group] = [];
        groups[group].push(swatch);
        return groups;
      }, {} as Record<string, Swatch[]>);
    }
    return result;
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
      <h2 className="ov25-swatches-title">Browse Swatches</h2>
      <div className="ov25-swatches-header">
        {allGroups.length >= 0 && (
          <button
            className="ov25-filters-button"
            onClick={() => setIsFilterPanelOpen(!isFilterPanelOpen)}
            aria-label={isFilterPanelOpen ? 'Close filters' : 'Open filters'}
          >
            <ListFilter size={20} />
            <span>Filters</span>
          </button>
        )}
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

      {selectedFilters.size > 0 && (
        <div className="ov25-selected-filters-container">
          {Array.from(selectedFilters).map((group) => (
            <div key={group} className="ov25-selected-filter-pill" data-selected="true">
              <span>{group}</span>
              <button
                className="ov25-selected-filter-remove"
                onClick={() => handleRemoveFilter(group)}
                aria-label={`Remove ${group} filter`}
              >
                <X size={16} />
              </button>
            </div>
          ))}
        </div>
      )}

      <FilterPanel
        isOpen={isFilterPanelOpen}
        onClose={() => setIsFilterPanelOpen(false)}
        allGroups={allGroups}
        selectedFilters={selectedFilters}
        onToggleGroup={handleToggleGroup}
      />

      {error && <div className="ov25-swatches-error">{error}</div>}
      {(loading || rulesLoading) && (
        <div className="ov25-swatches-loading" aria-live="polite">
          Loading Swatches...
        </div>
      )}
      
      {!rulesLoading && (!swatchRules?.enabled || (!loading && filteredSwatches.length === 0)) && (
        <div className="ov25-swatches-empty">{swatchRules?.enabled ? 'No swatches found' : 'Swatch purchasing is currently disabled'}</div>
      )}

      {!loading && swatchRules?.enabled && filteredSwatches.length > 0 && (
        <div className="ov25-swatches-groups">
            {sortedGroups.map((option) => {
            const isCollapsed = collapsedGroups.has(option);
            const optionGroups = groupedSwatches[option];
            const swatchCount = Object.values(optionGroups).flat().length;
            
            // Sort groups: empty group first, then alphabetically
            const sortedGroupKeys = Object.keys(optionGroups).sort((a, b) => {
              if (a === '' && b !== '') return -1;
              if (a !== '' && b === '') return 1;
              return a.localeCompare(b);
            });
            
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
                  <div className="ov25-swatch-option-content">
                    {sortedGroupKeys.map((groupKey) => {
                      const groupSwatches = optionGroups[groupKey];
                      const hasGroup = groupKey !== '';
                      
                      return (
                        <div key={groupKey || 'ungrouped'} className="ov25-swatch-subgroup">
                          {hasGroup && (
                            <h3 className="ov25-swatch-subgroup-header">{groupKey}</h3>
                          )}
                          <div className="ov25-swatches-grid">
                            {groupSwatches.map((swatch) => {
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
                        </div>
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
          rulesLoading={rulesLoading}
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




