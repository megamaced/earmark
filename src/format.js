/** Small date/number formatting helpers shared by the views. */

export function formatDateTime(unixSeconds) {
  if (!unixSeconds) return ''
  return new Date(unixSeconds * 1000).toLocaleString()
}

export function relativeTime(unixSeconds) {
  if (!unixSeconds) return ''
  const seconds = Math.floor(Date.now() / 1000 - unixSeconds)
  if (seconds < 60) return 'just now'
  const units = [
    ['year', 31536000],
    ['month', 2592000],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  for (const [name, size] of units) {
    const value = Math.floor(seconds / size)
    if (value >= 1) return `${value} ${name}${value === 1 ? '' : 's'} ago`
  }
  return 'just now'
}

export function formatNumber(n) {
  return Number(n || 0).toLocaleString()
}
