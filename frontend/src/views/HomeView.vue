<template>
  <section class="hero rise">
    <div class="hero-copy">
      <p class="eyebrow">Настольная игра</p>
      <h1>Шпионские<br />игры</h1>
      <p class="tagline">У каждого за столом тайная личность. Раскройте других шпионов, пока они не раскрыли вас.</p>
      <router-link class="btn btn-primary hero-link" :to="{ name: 'lobbies' }">
        Играть <span aria-hidden="true">↗</span>
      </router-link>
      <dl class="hero-meta">
        <div><dt>Игроков</dt><dd>3–6, 8 или 9</dd></div>
        <div><dt>Длительность</dt><dd>Около 30 мин</dd></div>
      </dl>
    </div>
    <div class="hero-art" aria-hidden="true">
      <img :src="coverUrl" alt="" fetchpriority="high" width="1200" height="800" />
    </div>
  </section>
</template>

<script setup>
const coverUrl = `${import.meta.env.BASE_URL}images/noir-cover.webp`
</script>

<style scoped>
.hero {
  display: grid;
  grid-template-columns: minmax(0, 0.84fr) minmax(0, 1.16fr);
  min-height: clamp(32rem, 72vh, 43rem);
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  background: #211917;
  box-shadow: var(--shadow);
}

.hero-copy {
  align-self: center;
  container-type: inline-size;
  padding: clamp(2rem, 5vw, 5rem);
}

.hero h1 {
  margin: 0.9rem 0 1.3rem;
  font-size: clamp(2rem, 4.5vw, 4.4rem);
  font-size: clamp(2rem, 18cqi, 5rem);
  line-height: 1.02;
  letter-spacing: -0.075em;
  overflow-wrap: normal;
  white-space: nowrap;
}

.tagline {
  max-width: 24rem;
  margin: 0 0 2rem;
  color: var(--fog);
  font-size: clamp(1rem, 1.4vw, 1.2rem);
}

.hero-link {
  justify-content: space-between;
  min-width: min(100%, 11rem);
  min-height: 3.35rem;
  font-size: 1rem;
}

.hero-link span {
  font-size: 1.3rem;
  line-height: 1;
}

.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 1.75rem;
  padding-top: 1.2rem;
  margin: 2rem 0 0;
  border-top: 1px solid var(--line);
}

.hero-meta dt {
  color: var(--fog);
  font-size: 0.78rem;
}

.hero-meta dd {
  margin: 0.2rem 0 0;
  font-weight: 600;
}

.hero-art {
  position: relative;
  min-width: 0;
  background: linear-gradient(140deg, #745345, #462421 55%, #291915);
}

.hero-art img {
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 61% center;
}

@media (max-width: 720px) {
  .hero { grid-template-columns: minmax(0, 1fr); min-height: 0; }
  .hero-art { grid-row: 1; aspect-ratio: 16 / 9; }
  .hero-art img { object-position: 60% 38%; }
  .hero-copy { padding: 1.5rem clamp(1.2rem, 5vw, 2.25rem); }
  .hero h1 { margin-block: 0.55rem 0.85rem; font-size: clamp(2.65rem, 10vw, 4.7rem); }
  .tagline { margin-bottom: 1.25rem; }
  .hero-link { width: 100%; }
  .hero-meta { margin-top: 1.25rem; padding-top: 0.9rem; }
}
</style>
