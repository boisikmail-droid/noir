/**
 * Описания портретов для генерации арт-паков.
 * id подозреваемого → краткая визуальная роль в тематике пака.
 */
export const SUSPECT_IDS = [
  'alistair', 'barrin', 'clive', 'deirdre', 'elisse', 'ernest', 'geneva', 'harold',
  'isolde', 'jack', 'kasim', 'lillian', 'marion', 'nigel', 'ophelia', 'pedro',
  'quinton', 'ramon', 'simon', 'suzanne', 'ulysses', 'vincent', 'wanda', 'yvonne',
  'zachary', 'bruno', 'celeste', 'diego', 'elena', 'felix', 'gretchen', 'hugo',
  'iris', 'julian', 'kira', 'lorenzo', 'marco', 'nadia', 'oscar', 'petra',
  'quincy', 'rosa', 'stefan', 'tara', 'umar', 'vera', 'wade', 'xenia', 'yuri', 'xavier',
]

/** @type {Record<string, Record<string, string>>} */
export const PACK_ROLES = {
  'sao-anime': {
    alistair: 'black-haired dual-blade swordsman in black coat, determined eyes',
    barrin: 'burly bearded frontline tank with heavy armor and shield',
    clive: 'scarred rogue dual-dagger assassin in dark leather',
    deirdre: 'red-haired spearwoman with fierce expression',
    elisse: 'gentle healer girl with staff and soft smile',
    ernest: 'serious older guild leader with neat hair and coat',
    geneva: 'elegant female fencer with long hair and rapier vibe',
    harold: 'balding merchant-type NPC with glasses and ledger',
    isolde: 'white-haired ice mage girl with cold expression',
    jack: 'cocky blond speed-type swordsman with grin',
    kasim: 'dark-skinned scimitar fighter with headband',
    lillian: 'pink-haired support mage with ribbons',
    marion: 'quiet brown-haired information broker girl with glasses',
    nigel: 'polite blond knight with neat armor',
    ophelia: 'mysterious long-braided sorceress with flower hairpin',
    pedro: 'cheerful mustached axeman from southern guild',
    quinton: 'aristocratic monocle strategist in fancy coat',
    ramon: 'curly-haired dual-wield Latino swordsman',
    simon: 'straight-laced black-haired swordsman with necktie energy',
    suzanne: 'fashionable huntress with cape and hat',
    ulysses: 'gray-haired veteran clearers commander with pipe vibe',
    vincent: 'sharp dark-coat antihero with piercing stare',
    wanda: 'glamorous songstress-type girl with feather accessory',
    yvonne: 'short-haired tomboy dagger girl with earrings',
    zachary: 'crew-cut assault teammate with battle scar',
    bruno: 'stubbled heavy hitter with knuckle weapons',
    celeste: 'starry-eyed astral mage girl with glowing motifs',
    diego: 'wavy-haired young swordsman with glasses',
    elena: 'composed dark-haired lady with necklace and dagger',
    felix: 'cheerful fringe-haired scout boy',
    gretchen: 'braided German-looking shieldmaiden',
    hugo: 'bearded axe berserker with beret-like bandana',
    iris: 'bob-cut girl with ribbon, bow user',
    julian: 'soft-curled pretty-boy mage with scarf',
    kira: 'asymmetrical-hair dual-blade girl with single earring',
    lorenzo: 'mustached Italian-looking musketeer fencer',
    marco: 'young chain-wearing street fighter type',
    nadia: 'ponytail desert scout girl with scarf',
    oscar: 'polished bow-tied noble swordsman',
    petra: 'wavy-haired glasses analyst girl',
    quincy: 'felt-hat gambler duelist',
    rosa: 'flower-haired romantic flower mage',
    stefan: 'stubbled scarfed mid-liner',
    tara: 'bun-hair glasses tactician girl',
    umar: 'bearded turbaned desert warrior',
    vera: 'bob-cut brooch noblewoman mage',
    wade: 'capped scarred mercenary',
    xenia: 'long-haired wide-hat witch',
    yuri: 'crew-cut glasses engineer type',
    xavier: 'bald monocle floor boss aura villain',
  },
}

export const PACK_STYLE = {
  'sao-anime':
    'Japanese anime style character portrait bust, Sword Art Online visual aesthetic, clean cel shading, black-red-teal accents, detailed eyes, game HUD fantasy vibe, square composition, no text, no watermark',
}
