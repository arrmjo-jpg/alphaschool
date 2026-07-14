import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import shellEn from '@/platform/i18n/locales/en/shell.json'
import shellAr from '@/platform/i18n/locales/ar/shell.json'

export const RTL_LOCALES = ['ar']

export const SUPPORTED_LOCALES = [
  { code: 'en', labelKey: 'English', dir: 'ltr' as const },
  { code: 'ar', labelKey: 'العربية', dir: 'rtl' as const },
]

i18n.use(initReactI18next).init({
  resources: {
    en: { shell: shellEn },
    ar: { shell: shellAr },
  },
  lng: localStorage.getItem('admin-platform-locale') ?? 'en',
  fallbackLng: 'en',
  defaultNS: 'shell',
  interpolation: { escapeValue: false },
})

export function setLocale(locale: string): void {
  i18n.changeLanguage(locale)
  localStorage.setItem('admin-platform-locale', locale)
  document.documentElement.lang = locale
  document.documentElement.dir = RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr'
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
