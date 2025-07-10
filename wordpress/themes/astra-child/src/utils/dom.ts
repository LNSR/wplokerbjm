/**
 * Returns true if the click event should be handled as a "normal" click
 * (not a modifier/middle click and on tablet/desktop).
 */
export function isNormalDesktopClick(event: MouseEvent): boolean {
  const isTabletOrDesktop = window.matchMedia('(min-width: 768px)').matches
  if (!isTabletOrDesktop) return false
  if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return false
  return true
}