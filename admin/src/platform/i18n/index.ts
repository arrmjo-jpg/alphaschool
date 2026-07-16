import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import shellEn from '@/platform/i18n/locales/en/shell.json'
import shellAr from '@/platform/i18n/locales/ar/shell.json'

export const RTL_LOCALES = ['ar']

export const SUPPORTED_LOCALES = [
  { code: 'en', labelKey: 'English', dir: 'ltr' as const },
  { code: 'ar', labelKey: 'العربية', dir: 'rtl' as const },
]

const initialLocale = localStorage.getItem('admin-platform-locale') ?? 'en'

i18n.use(initReactI18next).init({
  resources: {
    en: { shell: shellEn },
    ar: { shell: shellAr },
  },
  lng: initialLocale,
  fallbackLng: 'en',
  defaultNS: 'shell',
  interpolation: { escapeValue: false },
})

function applyDocumentDirection(locale: string): void {
  document.documentElement.lang = locale
  document.documentElement.dir = RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr'
}

// index.html ships a static lang="en"/no-dir baseline -- i18next's own
// `lng` init option only sets its internal state, it never touches the
// DOM. Without this call, a persisted Arabic locale renders `dir="ltr"`
// on every fresh load/reload until the user manually re-toggles the
// language switcher, a real RTL bug found by checking what actually
// happens on initial mount rather than assuming `init({ lng })` was
// enough (design doc §1.7/§19 "verify everything" discipline).
applyDocumentDirection(initialLocale)

export function setLocale(locale: string): void {
  i18n.changeLanguage(locale)
  localStorage.setItem('admin-platform-locale', locale)
  applyDocumentDirection(locale)
}

/**
 * A future workspace registers its own translations under its own
 * namespace without touching this file -- e.g.
 * registerWorkspaceTranslations('students', 'en', {...}).
 */
export function registerWorkspaceTranslations(
  namespace: string,
  locale: string,
  resources: Record<string, string>,
): void {
  i18n.addResourceBundle(locale, namespace, resources, true, true)
}

export default i18n
