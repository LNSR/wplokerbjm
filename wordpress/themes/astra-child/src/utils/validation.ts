export const validation = {
  isValidQuery(query: string, minLength = 2): boolean {
    return typeof query === 'string' && query.trim().length >= minLength
  },

  sanitizeString(str: string): string {
    return str.trim().replace(/[<>]/g, '')
  },

  isValidFilters(filters: Record<string, any>): boolean {
    return Object.values(filters).some(value => value && String(value).trim() !== '')
  }
}