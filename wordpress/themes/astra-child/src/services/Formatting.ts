/**
 * Format salary range for IDR with business logic.
 */
export function formatSalary(gaji_minimal?: number, gaji_maksimal?: number): string | null {
  const has_gaji_min = !!gaji_minimal;
  const has_gaji_max = !!gaji_maksimal;

  if (!has_gaji_min && !has_gaji_max) return null;

  const formatIDR = (value: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);

  const gaji_min = has_gaji_min ? formatIDR(gaji_minimal!) : null;
  const gaji_max = has_gaji_max ? formatIDR(gaji_maksimal!) : null;

  if (has_gaji_min && has_gaji_max) {
    return `${gaji_min} - ${gaji_max}`;
  } else if (has_gaji_min) {
    return `Sekitar ${gaji_min}`;
  } else {
    return `Maksimal ${gaji_max}`;
  }
}

/**
 * Format age range.
 */
export function formatAge(umur_min?: number, umur_max?: number): string | null {
  const has_umur_min = !!umur_min;
  const has_umur_max = !!umur_max;

  if (!has_umur_min && !has_umur_max) return null;

  if (has_umur_min && has_umur_max) {
    return `${umur_min} - ${umur_max} Tahun`;
  } else if (has_umur_min) {
    return `Minimal ${umur_min} Tahun`;
  } else {
    return `Maksimal ${umur_max} Tahun`;
  }
}

/**
 * Format a phone number for display (e.g., WhatsApp).
 */
export function formatPhone(number: string): string {
  if (!number) return '';
  number = number.replace(/[^\d+]/g, '');

  const match = number.match(/^\+(\d{1,5})(\d{0,})$/);
  if (match) {
    const countryCode = '+' + match[1];
    const rest = match[2];
    const formattedRest = rest.replace(/(.{4})/g, '$1 ').trim();
    return (countryCode + ' ' + formattedRest).trim();
  } else {
    number = number.replace(/\D+/g, '');
    return number.replace(/(.{4})/g, '$1 ').trim();
  }
}

/**
 * Format "time ago" in Indonesian.
 * @param timestamp Unix timestamp (seconds)
 */
export function formatTimeAgo(timestamp: number): string {
  const now = Math.floor(Date.now() / 1000);
  const diff = now - timestamp;

  const units = [
    { name: 'tahun', seconds: 31536000 },
    { name: 'bulan', seconds: 2592000 },
    { name: 'minggu', seconds: 604800 },
    { name: 'hari', seconds: 86400 },
    { name: 'jam', seconds: 3600 },
    { name: 'menit', seconds: 60 },
    { name: 'detik', seconds: 1 },
  ];

  for (const unit of units) {
    const value = Math.floor(diff / unit.seconds);
    if (value >= 1) {
      return `${value} ${unit.name} lalu`;
    }
  }
  return 'Baru saja';
}