<script setup>
import { mainNav } from './nav'
</script>

<template>
  <nav class="mnav" aria-label="التنقل">
    <RouterLink
      v-for="item in mainNav"
      :key="item.to"
      :to="item.to"
      class="mitem"
      :exact-active-class="item.to === '/' ? 'active' : ''"
      :active-class="item.to === '/' ? '' : 'active'"
    >
      <i class="ti" :class="item.icon" aria-hidden="true"></i>
      <span>{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>

<style scoped>
.mnav { display: none; }

@media (max-width: 900px) {
  .mnav {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    height: var(--mobile-nav-h);
    padding-bottom: env(safe-area-inset-bottom);
    background: var(--bg);
    border-top: 1px solid var(--border);
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    z-index: 30;
  }
  .mitem {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    font-size: 11px;
    color: var(--text-3);
  }
  .mitem .ti { font-size: 22px; }
  .mitem.active { color: var(--primary); font-weight: 500; }
}
</style>
