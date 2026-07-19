import { useTranslation } from 'react-i18next'
import type { SettingFieldValue } from '@/platform/administration/configuration-provider'
import { Input } from '@/platform/components/ui/input'
import { Label } from '@/platform/components/ui/label'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/platform/components/ui/select'
import { Badge } from '@/platform/components/ui/badge'

/**
 * The generic field renderer (§26.5) -- keyed off `dataType`, never a
 * bespoke component per setting. Every future settings-shaped page
 * (§26.8) that needs a new data type extends this switch, not a
 * parallel field system. `canEdit === false` disables with an
 * explanatory note (§26.6), never hides the field -- a permission gap
 * is not the same thing as the field not existing.
 */
export function SettingField({
  field,
  onChange,
}: {
  field: SettingFieldValue
  onChange: (value: unknown) => void
}) {
  const { t } = useTranslation('administration-configuration')
  const label = t(field.labelKey, field.labelKey)

  const resolvedFromNote =
    field.resolvedFrom === 'global'
      ? t('detail.usingGlobalDefault')
      : field.resolvedFrom === 'branch'
        ? t('detail.resolvedFromBranch')
        : t('detail.resolvedFromUser')

  return (
    <div className="flex flex-col gap-1.5">
      <div className="flex flex-wrap items-center gap-2">
        <Label htmlFor={field.key}>{label}</Label>
        <Badge variant="muted" className="text-[10px]">
          {resolvedFromNote}
        </Badge>
        {field.approvalRequired && (
          <Badge variant="warning" className="text-[10px]">
            {t('detail.approvalRequiredNote')}
          </Badge>
        )}
      </div>

      {field.dataType === 'boolean' ? (
        <input
          id={field.key}
          type="checkbox"
          checked={Boolean(field.value)}
          disabled={!field.canEdit}
          onChange={(event) => onChange(event.target.checked)}
          className="size-5 rounded-sm border-input accent-primary disabled:cursor-not-allowed disabled:opacity-50"
        />
      ) : field.dataType === 'select' ? (
        <Select
          value={String(field.value ?? '')}
          disabled={!field.canEdit}
          onValueChange={(value) => onChange(value)}
        >
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {(field.options ?? []).map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {t(option.labelKey, option.labelKey)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      ) : (
        <Input
          id={field.key}
          type={field.dataType === 'number' ? 'number' : 'text'}
          value={String(field.value ?? '')}
          disabled={!field.canEdit}
          onChange={(event) => onChange(field.dataType === 'number' ? Number(event.target.value) : event.target.value)}
        />
      )}

      {!field.canEdit && <p className="text-xs text-muted-foreground">{t('detail.viewOnlyNote')}</p>}
    </div>
  )
}
