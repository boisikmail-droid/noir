export const PACK_PREVIEWS = {
  'ink-minimal': ['ernest', 'geneva', 'isolde', 'vincent', 'wanda'],
  'brush-flat': ['ernest', 'geneva', 'isolde', 'vincent', 'wanda'],
  'sao-anime': ['kirito', 'asuna', 'klein', 'sinon', 'yuuki'],
}

/** URL портрета по арт-паку и id подозреваемого. */
export function portraitUrl(artPack, suspectId, ext = null) {
  if (!artPack || !suspectId) return null
  const format = ext || (artPack === 'ink-minimal' ? 'svg' : 'png')
  return `${import.meta.env.BASE_URL}art-packs/${artPack}/${suspectId}.${format}`
}

export function onPortraitError(event, artPack, suspectId) {
  const img = event?.target
  if (!img) return
  if (!img.dataset.triedSvg && artPack && suspectId) {
    img.dataset.triedSvg = '1'
    img.src = portraitUrl(artPack, suspectId, 'svg')
    return
  }
  if (img.dataset.fallback === '1') return
  img.dataset.fallback = '1'
  img.src =
    'data:image/svg+xml,' +
    encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 220"><rect width="200" height="220" fill="#1c1916"/><circle cx="100" cy="100" r="40" fill="#2a2622"/><text x="100" y="180" text-anchor="middle" fill="#6e675e" font-size="14">?</text></svg>`
    )
}
