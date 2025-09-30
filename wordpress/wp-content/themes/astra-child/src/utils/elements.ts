export function getWpAdminBarTopOffset(): number {
  const adminBar = typeof document !== 'undefined' ? document.getElementById('wpadminbar') : null;
  let rawAdminHeight = 0;
  let adminBarVisible = false;
  if (adminBar) {
    const style = getComputedStyle(adminBar);
    const rect = adminBar.getBoundingClientRect();
    const displayed = style.display !== 'none' && style.visibility !== 'hidden' && parseFloat(style.opacity || '1') > 0;
    adminBarVisible = displayed && rect.bottom > 0;
    rawAdminHeight = adminBarVisible ? adminBar.offsetHeight : 0;
  }

  const htmlMarginTop = typeof document !== 'undefined' ? parseFloat(getComputedStyle(document.documentElement).marginTop) || 0 : 0;
  const topOffset = adminBarVisible ? Math.max(rawAdminHeight, htmlMarginTop, 0) : 0;
  return topOffset;
}