import { useTranslation } from 'react-i18next'
import type { ProviderCredentialFieldDefinition } from '@/platform/administration/provider-registry-provider'
import { Input } from '@/platform/components/ui/input'
import { Label } from '@/platform/components/ui/label'

/**
 * §27.5's write-only field renderer -- structurally new, not a reuse of
 * Configuration Platform's SettingField, because there is no `value`
 * prop to give it (credentials are never returned by the backend,
 * §27.2). Every field renders empty regardless of whether a credential
 * is already configured; a placeholder communicates "configured"/"not
 * set" instead of a real value that doesn't exist client-side.
 *
 * `type` is read directly from the backend-declared
 * ProviderCredentialFieldDefinition -- never inferred from `name`
 * (§27.4/§27.5's own rejection of a name-based heuristic that would
 * only ever grow as new field names appeared).
 */
export function ProviderCredentialField({
  field,
  value,
  configured,
  disabled,
  onChange,
}: {
  field: ProviderCredentialFieldDefinition
  value: string
  /** Whether this field already has a stored value server-side -- the placeholder's only source of truth, since the value itself is never sent. */
  configured: boolean
  disabled: boolean
  onChange: (value: string) => void
}) {
  const { t } = useTranslation('administration-provider-registry')
  const isMasked = field.type === 'password' || field.type === 'secret'

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={field.name}>{t(`field.${field.name}`, field.name)}</Label>
      <Input
        id={field.name}
        type={isMasked ? 'password' : 'text'}
        value={value}
        disabled={disabled}
        autoComplete={field.type === 'password' ? 'new-password' : 'off'}
        placeholder={configured ? t('form.configured') : t('form.notSet')}
        onChange={(event) => onChange(event.target.value)}
      />
    </div>
  )
}
