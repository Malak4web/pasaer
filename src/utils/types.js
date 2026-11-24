// أنواع المحتوى: التسمية والأيقونة ومسار صفحة التفاصيل
export const typeMeta = {
  video: { label: 'مرئي', icon: 'ti-player-play', route: 'video' },
  audio: { label: 'صوتي', icon: 'ti-headphones', route: 'audio-item' },
  lesson: { label: 'درس', icon: 'ti-book', route: 'lesson' },
  post: { label: 'منشور', icon: 'ti-notes', route: 'post' }
}

export const itemLink = (item) => ({ name: typeMeta[item.type].route, params: { id: item.id } })

export const seriesKind = {
  video: { label: 'مرئي', icon: 'ti-player-play', cls: 'video' },
  audio: { label: 'صوتي', icon: 'ti-headphones', cls: 'audio' },
  text: { label: 'مكتوب', icon: 'ti-book', cls: 'lesson' }
}
