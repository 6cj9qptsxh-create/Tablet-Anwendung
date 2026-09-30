import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
  type PointerEvent as ReactPointerEvent,
  type ReactNode,
} from 'react'

type SwipePagerProps = {
  pageCount: number
  activeIndex: number
  onActiveIndexChange: (index: number) => void
  onDragProgress?: (index: number, offsetRatio: number) => void
  children: ReactNode
}

const SNAP_THRESHOLD = 0.22
const VELOCITY_THRESHOLD = 0.45

export function SwipePager({
  pageCount,
  activeIndex,
  onActiveIndexChange,
  onDragProgress,
  children,
}: SwipePagerProps) {
  const viewportRef = useRef<HTMLDivElement>(null)
  const trackRef = useRef<HTMLDivElement>(null)
  const widthRef = useRef(0)
  const indexRef = useRef(activeIndex)
  const dragRef = useRef<{
    pointerId: number
    startX: number
    startY: number
    lastX: number
    lastTime: number
    offset: number
    velocity: number
    axis: 'undecided' | 'horizontal' | 'vertical'
    dragging: boolean
  } | null>(null)
  const [dragOffset, setDragOffset] = useState(0)
  const [isDragging, setIsDragging] = useState(false)
  const [isAnimating, setIsAnimating] = useState(false)

  useEffect(() => {
    indexRef.current = activeIndex
  }, [activeIndex])

  const measure = useCallback(() => {
    const width = viewportRef.current?.clientWidth ?? 0
    widthRef.current = width
    return width
  }, [])

  useLayoutEffect(() => {
    measure()
    const observer = new ResizeObserver(() => {
      measure()
    })
    if (viewportRef.current) observer.observe(viewportRef.current)
    return () => observer.disconnect()
  }, [measure])

  const applyTransform = useCallback((index: number, offset: number, animate: boolean) => {
    const track = trackRef.current
    const width = widthRef.current || measure()
    if (!track || !width) return
    track.style.transition = animate
      ? 'transform 280ms cubic-bezier(0.22, 1, 0.36, 1)'
      : 'none'
    track.style.transform = `translate3d(${-index * width + offset}px, 0, 0)`
  }, [measure])

  useLayoutEffect(() => {
    if (dragRef.current?.dragging) return
    applyTransform(activeIndex, 0, isAnimating)
  }, [activeIndex, applyTransform, isAnimating])

  const finishDrag = useCallback(
    (clientX: number) => {
      const drag = dragRef.current
      if (!drag) return

      const width = widthRef.current || measure()
      const deltaX = clientX - drag.startX
      const now = performance.now()
      const dt = Math.max(now - drag.lastTime, 1)
      const instantVelocity = (clientX - drag.lastX) / dt
      const velocity = Math.abs(instantVelocity) > Math.abs(drag.velocity)
        ? instantVelocity
        : drag.velocity

      let next = indexRef.current
      if (drag.dragging && drag.axis === 'horizontal' && width > 0) {
        const progress = -deltaX / width
        if (Math.abs(velocity) > VELOCITY_THRESHOLD) {
          next = velocity < 0 ? indexRef.current + 1 : indexRef.current - 1
        } else if (Math.abs(progress) > SNAP_THRESHOLD) {
          next = progress > 0 ? indexRef.current + 1 : indexRef.current - 1
        }
        next = Math.max(0, Math.min(pageCount - 1, next))
      }

      dragRef.current = null
      setIsDragging(false)
      setDragOffset(0)
      setIsAnimating(true)
      indexRef.current = next
      applyTransform(next, 0, true)
      onActiveIndexChange(next)
      onDragProgress?.(next, 0)

      window.setTimeout(() => setIsAnimating(false), 300)
    },
    [applyTransform, measure, onActiveIndexChange, onDragProgress, pageCount],
  )

  const onPointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
    if (event.pointerType === 'mouse' && event.button !== 0) return
    measure()
    dragRef.current = {
      pointerId: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      lastX: event.clientX,
      lastTime: performance.now(),
      offset: 0,
      velocity: 0,
      axis: 'undecided',
      dragging: false,
    }
    setIsAnimating(false)
    event.currentTarget.setPointerCapture(event.pointerId)
  }

  const onPointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current
    if (!drag || drag.pointerId !== event.pointerId) return

    const dx = event.clientX - drag.startX
    const dy = event.clientY - drag.startY
    const now = performance.now()
    const dt = Math.max(now - drag.lastTime, 1)
    drag.velocity = (event.clientX - drag.lastX) / dt
    drag.lastX = event.clientX
    drag.lastTime = now

    if (drag.axis === 'undecided') {
      if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return
      drag.axis = Math.abs(dx) > Math.abs(dy) ? 'horizontal' : 'vertical'
      if (drag.axis === 'vertical') return
      drag.dragging = true
      setIsDragging(true)
    }

    if (drag.axis !== 'horizontal') return

    event.preventDefault()
    const width = widthRef.current || measure()
    const atStart = indexRef.current === 0 && dx > 0
    const atEnd = indexRef.current === pageCount - 1 && dx < 0
    const resistance = atStart || atEnd ? 0.35 : 1
    const offset = dx * resistance
    drag.offset = offset
    setDragOffset(offset)
    applyTransform(indexRef.current, offset, false)
    if (width > 0) {
      onDragProgress?.(indexRef.current, offset / width)
    }
  }

  const onPointerUp = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current
    if (!drag || drag.pointerId !== event.pointerId) return
    finishDrag(event.clientX)
  }

  const onPointerCancel = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current
    if (!drag || drag.pointerId !== event.pointerId) return
    dragRef.current = null
    setIsDragging(false)
    setDragOffset(0)
    setIsAnimating(true)
    applyTransform(indexRef.current, 0, true)
    onDragProgress?.(indexRef.current, 0)
    window.setTimeout(() => setIsAnimating(false), 300)
  }

  return (
    <div
      className={`swipe-pager${isDragging ? ' is-dragging' : ''}`}
      ref={viewportRef}
      onPointerDown={onPointerDown}
      onPointerMove={onPointerMove}
      onPointerUp={onPointerUp}
      onPointerCancel={onPointerCancel}
      style={{ touchAction: 'pan-y' }}
    >
      <div
        className="swipe-track"
        ref={trackRef}
        data-offset={dragOffset}
        aria-live="polite"
      >
        {children}
      </div>
    </div>
  )
}
