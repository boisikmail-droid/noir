/**
 * Генератор набора «ink-minimal»: плоские SVG-портреты подозреваемых.
 * Запуск: node scripts/generate-art-pack.mjs
 */
import { mkdirSync, writeFileSync } from 'fs'
import { dirname, join } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const OUT = join(__dirname, '../public/art-packs/ink-minimal')

const suspects = [
  ['alistair', 'М', 'шляпа', 'усы'],
  ['barrin', 'М', 'очки', 'борода'],
  ['clive', 'М', 'кепка', 'шрам'],
  ['deirdre', 'Ж', 'волны', 'серьги'],
  ['elisse', 'Ж', 'пучок', 'очки'],
  ['ernest', 'М', 'козырёк', 'усы'],
  ['geneva', 'Ж', 'каре', 'шарф'],
  ['harold', 'М', 'лысина', 'очки'],
  ['isolde', 'Ж', 'длинные', 'шляпа'],
  ['jack', 'М', 'ежик', 'сигара'],
  ['kasim', 'М', 'тюрбан', 'борода'],
  ['lillian', 'Ж', 'локоны', 'брошь'],
  ['marion', 'Ж', 'чёлка', 'очки'],
  ['nigel', 'М', 'пробор', 'бабочка'],
  ['ophelia', 'Ж', 'коса', 'цветок'],
  ['pedro', 'М', 'усатый', 'сомбреро'],
  ['quinton', 'М', 'фетр', 'монокль'],
  ['ramon', 'М', 'кудри', 'цепочка'],
  ['simon', 'М', 'ровный', 'галстук'],
  ['suzanne', 'Ж', 'волны', 'шляпка'],
  ['ulysses', 'М', 'седой', 'труба'],
  ['vincent', 'М', 'короткие', 'плащ'],
  ['wanda', 'Ж', 'укладка', 'перья'],
  ['yvonne', 'Ж', 'пикси', 'серьги'],
  ['zachary', 'М', 'бокс', 'шрам'],
  ['bruno', 'М', 'щетина', 'кепка'],
  ['celeste', 'Ж', 'пучок', 'звёзды'],
  ['diego', 'М', 'волны', 'очки'],
  ['elena', 'Ж', 'прямые', 'колье'],
  ['felix', 'М', 'чёлка', 'улыбка'],
  ['gretchen', 'Ж', 'косы', 'очки'],
  ['hugo', 'М', 'борода', 'берет'],
  ['iris', 'Ж', 'каре', 'бант'],
  ['julian', 'М', 'локоны', 'шарф'],
  ['kira', 'Ж', 'асимметрия', 'серьга'],
  ['lorenzo', 'М', 'усы', 'шляпа'],
  ['marco', 'М', 'короткие', 'цепочка'],
  ['nadia', 'Ж', 'хвост', 'платок'],
  ['oscar', 'М', 'пробор', 'бабочка'],
  ['petra', 'Ж', 'волны', 'очки'],
  ['quincy', 'М', 'фетр', 'усы'],
  ['rosa', 'Ж', 'цветы', 'серьги'],
  ['stefan', 'М', 'щетина', 'шарф'],
  ['tara', 'Ж', 'пучок', 'очки'],
  ['umar', 'М', 'борода', 'тюрбан'],
  ['vera', 'Ж', 'каре', 'брошь'],
  ['wade', 'М', 'кепка', 'шрам'],
  ['xenia', 'Ж', 'длинные', 'шляпа'],
  ['yuri', 'М', 'ежик', 'очки'],
  ['xavier', 'М', 'лысина', 'монокль'],
]

const accents = ['#c4a35a', '#8b2e2e', '#3d6b4f', '#6e675e', '#4a6fa5', '#a67c52']

function hash(s) {
  let h = 0
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) | 0
  return Math.abs(h)
}

function portrait(id, gender, hair, accessory) {
  const h = hash(id)
  const accent = accents[h % accents.length]
  const skin = '#d4c4a8'
  const ink = '#1a1510'
  const bg = h % 2 === 0 ? '#2a2622' : '#1c1916'
  const isF = gender === 'Ж'
  const faceCy = 118
  const faceR = isF ? 42 : 44

  let hairSvg = ''
  if (hair.includes('лысина')) {
    hairSvg = `<ellipse cx="100" cy="78" rx="38" ry="18" fill="${ink}" opacity="0.15"/>`
  } else if (hair.includes('пучок') || hair.includes('коса') || hair.includes('хвост')) {
    hairSvg = `
      <ellipse cx="100" cy="88" rx="48" ry="40" fill="${ink}"/>
      <circle cx="100" cy="48" r="16" fill="${ink}"/>`
  } else if (hair.includes('каре') || hair.includes('чёлк') || hair.includes('пикси')) {
    hairSvg = `
      <path d="M52 110 Q55 55 100 48 Q145 55 148 110 L140 100 Q100 70 60 100 Z" fill="${ink}"/>
      <rect x="58" y="78" width="84" height="14" fill="${ink}"/>`
  } else if (hair.includes('волн') || hair.includes('локон') || hair.includes('кудр')) {
    hairSvg = `
      <path d="M48 120 Q40 60 100 45 Q160 60 152 120 Q140 85 100 80 Q60 85 48 120 Z" fill="${ink}"/>`
  } else if (hair.includes('шляп') || hair.includes('фетр') || hair.includes('сомбреро') || hair.includes('тюрбан') || hair.includes('берет') || hair.includes('козыр') || hair.includes('кепк')) {
    hairSvg = `
      <ellipse cx="100" cy="95" rx="46" ry="38" fill="${ink}"/>
      <ellipse cx="100" cy="70" rx="52" ry="14" fill="${ink}"/>
      <rect x="70" y="48" width="60" height="28" rx="4" fill="${accent}"/>`
  } else {
    hairSvg = `<ellipse cx="100" cy="90" rx="46" ry="40" fill="${ink}"/>`
  }

  let acc = ''
  if (accessory.includes('очки') || accessory.includes('монокль')) {
    if (accessory.includes('монокль')) {
      acc += `<circle cx="118" cy="${faceCy}" r="12" fill="none" stroke="${accent}" stroke-width="3"/>
        <line x1="106" y1="${faceCy}" x2="88" y2="${faceCy}" stroke="${accent}" stroke-width="2"/>`
    } else {
      acc += `<circle cx="82" cy="${faceCy}" r="11" fill="none" stroke="${accent}" stroke-width="3"/>
        <circle cx="118" cy="${faceCy}" r="11" fill="none" stroke="${accent}" stroke-width="3"/>
        <line x1="93" y1="${faceCy}" x2="107" y2="${faceCy}" stroke="${accent}" stroke-width="2"/>`
    }
  }
  if (accessory.includes('усы') || accessory.includes('усатый')) {
    acc += `<path d="M78 142 Q100 152 122 142" fill="none" stroke="${ink}" stroke-width="4" stroke-linecap="round"/>`
  }
  if (accessory.includes('борода') || accessory.includes('щетина')) {
    acc += `<ellipse cx="100" cy="158" rx="28" ry="18" fill="${ink}"/>`
  }
  if (accessory.includes('серьг') || accessory.includes('серьга')) {
    acc += `<circle cx="58" cy="130" r="4" fill="${accent}"/>`
  }
  if (accessory.includes('шарф') || accessory.includes('платок')) {
    acc += `<path d="M60 170 Q100 190 140 170 L145 200 L55 200 Z" fill="${accent}"/>`
  }
  if (accessory.includes('шрам')) {
    acc += `<line x1="125" y1="100" x2="145" y2="125" stroke="${ink}" stroke-width="2"/>`
  }
  if (accessory.includes('галстук') || accessory.includes('бабочка')) {
    acc += `<polygon points="100,175 88,195 112,195" fill="${accent}"/>`
  }
  if (accessory.includes('цветок') || accessory.includes('цветы') || accessory.includes('перья') || accessory.includes('бант') || accessory.includes('брошь') || accessory.includes('звёзды')) {
    acc += `<circle cx="145" cy="95" r="10" fill="${accent}"/>`
  }

  // плечи / плащ
  const shoulders = `<path d="M30 200 Q100 165 170 200 L170 220 L30 220 Z" fill="${ink}"/>`

  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 220" width="200" height="220">
  <rect width="200" height="220" fill="${bg}"/>
  <rect x="8" y="8" width="184" height="204" fill="none" stroke="${accent}" stroke-width="2" opacity="0.5"/>
  ${shoulders}
  ${hairSvg}
  <circle cx="100" cy="${faceCy}" r="${faceR}" fill="${skin}"/>
  <circle cx="84" cy="${faceCy - 2}" r="4" fill="${ink}"/>
  <circle cx="116" cy="${faceCy - 2}" r="4" fill="${ink}"/>
  <path d="M88 138 Q100 146 112 138" fill="none" stroke="${ink}" stroke-width="2.5" stroke-linecap="round"/>
  ${acc}
</svg>
`
}

mkdirSync(OUT, { recursive: true })
for (const [id, gender, hair, accessory] of suspects) {
  const svg = portrait(id, gender, hair, accessory)
  writeFileSync(join(OUT, `${id}.svg`), svg)
}
writeFileSync(
  join(OUT, 'pack.json'),
  JSON.stringify(
    {
      id: 'ink-minimal',
      label: 'Чернильный минимализм',
      format: 'svg',
      count: suspects.length,
    },
    null,
    2
  )
)
console.log(`Wrote ${suspects.length} portraits → ${OUT}`)
