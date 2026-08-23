import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { AsyncSelectField } from '@/platform/forms/async-select-field'
import { Button } from '@/platform/components/ui/button'
import { assignmentsLinkProps } from '@/workspaces/assignments/assignments-route'
import { loadSectionOptions } from '@/workspaces/assignments/providers/section-options'

/**
 * §30.2's async-select landing page, not a row list -- a temporal
 * assignment record has no standalone meaning outside its one anchor
 * (§30.1), so there is nothing to browse until a Section is chosen.
 */
export function HomeroomLandingPage() {
  const { t } = useTranslation('assignments')
  const navigate = useNavigate()
  const { control, handleSubmit, watch } = useForm<{ section_id: string }>({ defaultValues: { section_id: '' } })
  const sectionId = watch('section_id')

  useBreadcrumbSegments([{ label: t('tabs.homeroom') }])

  const submit = handleSubmit((values) => {
    navigate(assignmentsLinkProps('homeroom', values.section_id))
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6">
      <WorkspaceHeader title={t('homeroom.landing.title')} description={t('homeroom.landing.description')} />
      <div className="flex max-w-md flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField
          control={control}
          name="section_id"
          label={t('homeroom.landing.sectionLabel')}
          loadOptions={loadSectionOptions}
          queryKey={['sections', 'options', 'active']}
        />
        <Button type="submit" disabled={!sectionId} className="w-fit">
          {t('homeroom.landing.viewButton')}
        </Button>
      </div>
    </form>
  )
}
