export const QURAN_RECITERS = [
  { id: 'ar.husary', name: 'محمود خليل الحصري (المرتّل)', mp3Base: 'https://server13.mp3quran.net/husr/' },
  { id: 'ar.minshawi', name: 'محمد صديق المنشاوي (المرتّل)', mp3Base: 'https://server10.mp3quran.net/minsh/' },
  { id: 'ar.abdulbasitmurattal', name: 'عبد الباسط عبد الصمد (المرتّل)', mp3Base: 'https://server7.mp3quran.net/basit/' },
  { id: 'ar.alafasy', name: 'مشاري راشد العفاسي', mp3Base: 'https://server8.mp3quran.net/afs/' },
  { id: 'ar.mahermuaiqly', name: 'ماهر المعيقلي', mp3Base: 'https://server12.mp3quran.net/maher/' },
  { id: 'ar.abdurrahmaansudais', name: 'عبد الرحمن السديس', mp3Base: 'https://server11.mp3quran.net/sds/' },
  { id: 'ar.shaatree', name: 'أبو بكر الشاطري', mp3Base: 'https://server11.mp3quran.net/shatri/' },
  { id: 'ar.ahmedajamy', name: 'أحمد بن علي العجمي', mp3Base: 'https://server10.mp3quran.net/ajm/' },
  { id: 'ar.hudhaify', name: 'علي بن عبد الرحمن الحذيفي', mp3Base: 'https://server9.mp3quran.net/hthfi/' }
]

export const QURAN_TAFSIRS = [
  { id: 'ar.muyassar', name: 'التفسير الميسّر' },
  { id: 'ar.jalalayn', name: 'تفسير الجلالين' },
  { id: 'ar.waseet', name: 'التفسير الوسيط' },
  { id: 'ar.qurtubi', name: 'تفسير القرطبي' },
  { id: 'ar.baghawi', name: 'تفسير البغوي' }
]

// فهرس الأجزاء الثلاثين: رقم الجزء، السورة والآية الابتدائية، ونطاق السور للتحميل السريع
export const QURAN_JUZS = [
  { juz: 1, name: 'الجزء الأول', startSurah: 1, startAyah: 1, surahs: [1, 2] },
  { juz: 2, name: 'الجزء الثاني', startSurah: 2, startAyah: 142, surahs: [2] },
  { juz: 3, name: 'الجزء الثالث', startSurah: 2, startAyah: 253, surahs: [2, 3] },
  { juz: 4, name: 'الجزء الرابع', startSurah: 3, startAyah: 93, surahs: [3, 4] },
  { juz: 5, name: 'الجزء الخامس', startSurah: 4, startAyah: 24, surahs: [4] },
  { juz: 6, name: 'الجزء السادس', startSurah: 4, startAyah: 148, surahs: [4, 5] },
  { juz: 7, name: 'الجزء السابع', startSurah: 5, startAyah: 82, surahs: [5, 6] },
  { juz: 8, name: 'الجزء الثامن', startSurah: 6, startAyah: 111, surahs: [6, 7] },
  { juz: 9, name: 'الجزء التاسع', startSurah: 7, startAyah: 88, surahs: [7, 8] },
  { juz: 10, name: 'الجزء العاشر', startSurah: 8, startAyah: 41, surahs: [8, 9] },
  { juz: 11, name: 'الجزء الحادي عشر', startSurah: 9, startAyah: 93, surahs: [9, 10, 11] },
  { juz: 12, name: 'الجزء الثاني عشر', startSurah: 11, startAyah: 6, surahs: [11, 12] },
  { juz: 13, name: 'الجزء الثالث عشر', startSurah: 12, startAyah: 53, surahs: [12, 13, 14] },
  { juz: 14, name: 'الجزء الرابع عشر', startSurah: 15, startAyah: 1, surahs: [15, 16] },
  { juz: 15, name: 'الجزء الخامس عشر', startSurah: 17, startAyah: 1, surahs: [17, 18] },
  { juz: 16, name: 'الجزء السادس عشر', startSurah: 18, startAyah: 75, surahs: [18, 19, 20] },
  { juz: 17, name: 'الجزء السابع عشر', startSurah: 21, startAyah: 1, surahs: [21, 22] },
  { juz: 18, name: 'الجزء الثامن عشر', startSurah: 23, startAyah: 1, surahs: [23, 24, 25] },
  { juz: 19, name: 'الجزء التاسع عشر', startSurah: 25, startAyah: 21, surahs: [25, 26, 27] },
  { juz: 20, name: 'الجزء العشرون', startSurah: 27, startAyah: 56, surahs: [27, 28, 29] },
  { juz: 21, name: 'الجزء الحادي والعشرون', startSurah: 29, startAyah: 46, surahs: [29, 30, 31, 32, 33] },
  { juz: 22, name: 'الجزء الثاني والعشرون', startSurah: 33, startAyah: 31, surahs: [33, 34, 35, 36] },
  { juz: 23, name: 'الجزء الثالث والعشرون', startSurah: 36, startAyah: 28, surahs: [36, 37, 38, 39] },
  { juz: 24, name: 'الجزء الرابع والعشرون', startSurah: 39, startAyah: 32, surahs: [39, 40, 41] },
  { juz: 25, name: 'الجزء الخامس والعشرون', startSurah: 41, startAyah: 47, surahs: [41, 42, 43, 44, 45] },
  { juz: 26, name: 'الجزء السادس والعشرون', startSurah: 46, startAyah: 1, surahs: [46, 47, 48, 49, 50, 51] },
  { juz: 27, name: 'الجزء السابع والعشرون', startSurah: 51, startAyah: 31, surahs: [51, 52, 53, 54, 55, 56, 57] },
  { juz: 28, name: 'الجزء الثامن والعشرون', startSurah: 58, startAyah: 1, surahs: [58, 59, 60, 61, 62, 63, 64, 65, 66] },
  { juz: 29, name: 'الجزء التاسع والعشرون (تبارك)', startSurah: 67, startAyah: 1, surahs: [67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77] },
  { juz: 30, name: 'الجزء الثلاثون (عمّ)', startSurah: 78, startAyah: 1, surahs: Array.from({ length: 37 }, (_, i) => 78 + i) }
]

export function surahMp3Url(reciterId, surahNumber) {
  const rec = QURAN_RECITERS.find((r) => r.id === reciterId) || QURAN_RECITERS[0]
  return `${rec.mp3Base}${String(surahNumber).padStart(3, '0')}.mp3`
}

export function normalizeAr(str) {
  return String(str || '')
    .replace(/[\u064B-\u065F\u0670\u06D6-\u06ED]/g, '')
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .trim()
    .toLowerCase()
}
