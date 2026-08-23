import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useTranslation } from 'react-i18next'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments, type BreadcrumbSegment } from '@/platform/shell/breadcrumb-store'
import { AsyncSelectField } from '@/platform/forms/async-select-field'
import { DateField } from '@/platform/forms/date-field'
import { mapServerErrors } from '@/platform/forms/map-server-errors'
import { useEntityMutation } from '@/platform/forms/use-entity-mutation'
import { useConfirm } from '@/platform/modals/confirm-dialog'
import { StickyActionBar } from '@/platform/components/ui/sticky-action-bar'
import { Button } from '@/platform/components/ui/button'

const closeSchema = z.object({ reason_code: z.string().min(1), effective_until: z.string().optional() })
const cancelSchema = z.object({ reason_code: z.string().min(1) })

type CloseValues = z.infer<typeof closeSchema>
type CancelValues = z.infer<typeof cancelSchema>

/**
 * The reusable Close/Cancel full-page template (docs/ADMIN_DESIGN_SYSTEM.md
 * §30.4/§30.7) -- only the reason-code `loadOptions`/`queryKey` (the
 * `context` string) and the confirm copy change per entity; built once
 * here for HomeroomAssignment, reused verbatim by SectionAssignment/
 * TeacherAssignment. Two distinct exports rather than one component with
 * a type toggle -- Close and Cancel are semantically different
 * operations (closeAssignment() vs cancelAssignment() are different
 * trait methods), and the UI states that difference plainly rather than
 * collapsing it for convenience (§30.4).
 */
export function CloseAssignmentForm({
  breadcrumb,
  title,
  loadReasonOptions,
  reasonQueryKey,
  onSubmit,
  successMessage,
  invalidateQueryKey,
  onDone,
  onCancel,
  confirmTitle,
  confirmDescription,
}: {
  breadcrumb: BreadcrumbSegment[]
  title: string
  loadReasonOptions: () => Promise<{ value: string; label: string }[]>
  reasonQueryKey: unknown[]
  onSubmit: (values: CloseValues) => Promise<unknown>
  successMessage: string
  invalidateQueryKey: unknown[]
  onDone: () => void
  onCancel: () => void
  confirmTitle: string
  confirmDescription: string
}) {
  const { t } = useTranslation('timeline')
  const confirm = useConfirm()

  const { control, handleSubmit, setError } = useForm<CloseValues>({
    resolver: zodResolver(closeSchema),
    defaultValues: { reason_code: '', effective_until: new Date().toISOString().slice(0, 10) },
  })

  const mutation = useEntityMutation({
    mutationFn: onSubmit,
    successMessage,
    invalidateQueryKey,
    onSuccess: onDone,
    onError: (error) => {
      mapServerErrors(error, setError)
    },
  })

  useBreadcrumbSegments(breadcrumb)

  const submit = handleSubmit(async (values) => {
    // §30.4: "a deliberately heavier visual gate than Close's" -- that
    // sentence only holds if Close's own gate is NOT the destructive
    // (red-button) variant. Cancel's own form below correctly uses
    // 'destructive'; Close uses the default variant so the two are
    // visually distinguishable, not both rendering an identical red
    // button.
    const confirmed = await confirm({ title: confirmTitle, description: confirmDescription, confirmLabel: t('form.submitButton'), variant: 'default' })
    if (!confirmed) return
    mutation.mutate(values)
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6">
      <WorkspaceHeader title={title} />
      <div className="flex flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField control={control} name="reason_code" label={t('form.reasonLabel')} loadOptions={loadReasonOptions} queryKey={reasonQueryKey} />
        <DateField control={control} name="effective_until" label={t('form.effectiveUntilLabel')} />
      </div>
      <StickyActionBar>
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('form.cancelButton')}
        </Button>
        <Button type="submit" disabled={mutation.isPending}>
          {t('form.submitButton')}
        </Button>
      </StickyActionBar>
    </form>
  )
}

export function CancelAssignmentForm({
  breadcrumb,
  title,
  loadReasonOptions,
  reasonQueryKey,
  onSubmit,
  successMessage,
  invalidateQueryKey,
  onDone,
  onCancel,
  confirmTitle,
  confirmDescription,
}: {
  breadcrumb: BreadcrumbSegment[]
  title: string
  loadReasonOptions: () => Promise<{ value: string; label: string }[]>
  reasonQueryKey: unknown[]
  onSubmit: (values: CancelValues) => Promise<unknown>
  successMessage: string
  invalidateQueryKey: unknown[]
  onDone: () => void
  onCancel: () => void
  confirmTitle: string
  confirmDescription: string
}) {
  const { t } = useTranslation('timeline')
  const confirm = useConfirm()

  const { control, handleSubmit, setError } = useForm<CancelValues>({
    resolver: zodResolver(cancelSchema),
    defaultValues: { reason_code: '' },
  })

  const mutation = useEntityMutation({
    mutationFn: onSubmit,
    successMessage,
    invalidateQueryKey,
    onSuccess: onDone,
    onError: (error) => {
      mapServerErrors(error, setError)
    },
  })

  useBreadcrumbSegments(breadcrumb)

  const submit = handleSubmit(async (values) => {
    // A deliberately heavier visual gate than Close's -- cancelling
    // invalidates a record rather than concluding it (§30.4). Close uses
    // 'default'; Cancel uses 'destructive' so the two read as visually
    // distinct actions, not the same red confirm button twice.
    const confirmed = await confirm({ title: confirmTitle, description: confirmDescription, confirmLabel: t('form.submitButton'), variant: 'destructive' })
    if (!confirmed) return
    mutation.mutate(values)
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6">
      <WorkspaceHeader title={title} />
      <div className="flex flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField control={control} name="reason_code" label={t('form.reasonLabel')} loadOptions={loadReasonOptions} queryKey={reasonQueryKey} />
      </div>
      <StickyActionBar>
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('form.cancelButton')}
        </Button>
        <Button type="submit" disabled={mutation.isPending}>
          {t('form.submitButton')}
        </Button>
      </StickyActionBar>
    </form>
  )
}
