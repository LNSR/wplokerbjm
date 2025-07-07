/**
 * Format a phone number for display (e.g., WhatsApp).
 */
export function formatPhone(number: string): string {
  if (!number) return ''
  // Remove all non-digit and non-plus characters
  number = number.replace(/[^\d+]/g, '')

  // If starts with +, extract country code (up to 5 digits after +)
  const match = number.match(/^\+(\d{1,5})(\d{0,})$/)
  if (match) {
    const countryCode = '+' + match[1]
    const rest = match[2]
    // Group the rest every 4 digits
    const formattedRest = rest.replace(/(.{4})/g, '$1 ').trim()
    return (countryCode + ' ' + formattedRest).trim()
  } else {
    // No country code, just group every 4 digits
    number = number.replace(/\D+/g, '')
    return number.replace(/(.{4})/g, '$1 ').trim()
  }
}