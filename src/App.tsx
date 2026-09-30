import { useCallback, useState } from 'react'
import { SwipePager } from './SwipePager'
import { TABS, type TabId } from './tabs'
import './App.css'

function App() {
  const [activeIndex, setActiveIndex] = useState(0)
  const [highlightIndex, setHighlightIndex] = useState(0)
  const activeTab = TABS[activeIndex]

  const selectTab = useCallback((index: number) => {
    setActiveIndex(index)
    setHighlightIndex(index)
  }, [])

  const handleActiveIndexChange = useCallback((index: number) => {
    setActiveIndex(index)
    setHighlightIndex(index)
  }, [])

  const handleDragProgress = useCallback((index: number, offsetRatio: number) => {
    const preview = offsetRatio < -0.5
      ? Math.min(TABS.length - 1, index + 1)
      : offsetRatio > 0.5
        ? Math.max(0, index - 1)
        : index
    setHighlightIndex(preview)
  }, [])

  return (
    <div className="app-shell">
      <header className="app-topbar">
        <div className="brand-block">
          <p className="brand">Tablet</p>
          <h1 className="screen-title">{activeTab.title}</h1>
        </div>
        <p className="top-hint">Wischen oder Tippen</p>
      </header>

      <SwipePager
        pageCount={TABS.length}
        activeIndex={activeIndex}
        onActiveIndexChange={handleActiveIndexChange}
        onDragProgress={handleDragProgress}
      >
        {TABS.map((tab) => (
          <section className="page" key={tab.id} aria-label={tab.label}>
            <div className="page-panel">
              <p className="page-kicker">{tab.label}</p>
              <h2>{tab.title}</h2>
              <p className="page-copy">{tab.description}</p>
              <ul className="page-list">
                {tab.points.map((point) => (
                  <li key={point}>{point}</li>
                ))}
              </ul>
            </div>
          </section>
        ))}
      </SwipePager>

      <nav className="tab-bar" aria-label="Hauptnavigation">
        <div
          className="tab-indicator"
          style={{
            width: `${100 / TABS.length}%`,
            transform: `translate3d(${highlightIndex * 100}%, 0, 0)`,
          }}
        />
        {TABS.map((tab, index) => {
          const isActive = index === highlightIndex
          return (
            <button
              key={tab.id}
              type="button"
              className={`tab-button${isActive ? ' is-active' : ''}`}
              aria-current={isActive ? 'page' : undefined}
              aria-label={tab.label}
              onClick={() => selectTab(index)}
            >
              <TabIcon id={tab.id} active={isActive} />
              <span>{tab.label}</span>
            </button>
          )
        })}
      </nav>
    </div>
  )
}

function TabIcon({ id, active }: { id: TabId; active: boolean }) {
  const stroke = active ? 'currentColor' : 'currentColor'
  switch (id) {
    case 'start':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"
            fill="none"
            stroke={stroke}
            strokeWidth="1.8"
            strokeLinejoin="round"
          />
        </svg>
      )
    case 'uebersicht':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <rect x="4" y="4" width="7" height="7" rx="1.5" fill="none" stroke={stroke} strokeWidth="1.8" />
          <rect x="13" y="4" width="7" height="7" rx="1.5" fill="none" stroke={stroke} strokeWidth="1.8" />
          <rect x="4" y="13" width="7" height="7" rx="1.5" fill="none" stroke={stroke} strokeWidth="1.8" />
          <rect x="13" y="13" width="7" height="7" rx="1.5" fill="none" stroke={stroke} strokeWidth="1.8" />
        </svg>
      )
    case 'aufgaben':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path
            d="M8 7h11M8 12h11M8 17h11M5 7h.01M5 12h.01M5 17h.01"
            fill="none"
            stroke={stroke}
            strokeWidth="1.8"
            strokeLinecap="round"
          />
        </svg>
      )
    case 'profil':
      return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="8" r="3.2" fill="none" stroke={stroke} strokeWidth="1.8" />
          <path
            d="M5 19c1.8-3.2 4.1-4.8 7-4.8s5.2 1.6 7 4.8"
            fill="none"
            stroke={stroke}
            strokeWidth="1.8"
            strokeLinecap="round"
          />
        </svg>
      )
  }
}

export default App
