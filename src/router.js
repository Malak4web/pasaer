import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  { path: '/', name: 'home', component: () => import('./views/HomeView.vue'), meta: { title: 'الرئيسية' } },
  { path: '/videos', name: 'videos', component: () => import('./views/VideosView.vue'), meta: { title: 'المرئيات' } },
  { path: '/videos/:id', name: 'video', component: () => import('./views/VideoDetail.vue') },
  { path: '/audio', name: 'audio', component: () => import('./views/AudioView.vue'), meta: { title: 'الصوتيات' } },
  { path: '/audio/:id', name: 'audio-item', component: () => import('./views/AudioDetail.vue') },
  { path: '/lessons', name: 'lessons', component: () => import('./views/LessonsView.vue'), meta: { title: 'الدروس والسلاسل' } },
  { path: '/lessons/:id', name: 'lesson', component: () => import('./views/ReadView.vue'), props: { kind: 'lesson' } },
  { path: '/series/:id', name: 'series', component: () => import('./views/SeriesDetail.vue') },
  { path: '/posts', name: 'posts', component: () => import('./views/PostsView.vue'), meta: { title: 'المنشورات' } },
  { path: '/posts/:id', name: 'post', component: () => import('./views/ReadView.vue'), props: { kind: 'post' } },
  { path: '/topics/:id', name: 'topic', component: () => import('./views/TopicView.vue') },
  { path: '/search', name: 'search', component: () => import('./views/SearchView.vue'), meta: { title: 'البحث' } },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('./views/NotFound.vue'), meta: { title: 'الصفحة غير موجودة' } }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, saved) {
    return saved || { top: 0 }
  }
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · بصائر` : 'بصائر'
})

export default router
