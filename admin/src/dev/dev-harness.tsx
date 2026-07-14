import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createColumnHelper } from '@tanstack/react-table'
import { fetchMockRoles, createMockRole, type MockRole } from '@/dev/mock-data'
import { useServerDataTable } from '@/platform/data-table/use-server-data-table'
import { DataTable } from '@/platform/data-table/data-table'
import { TextField } from '@/platform/forms/text-field'
import { BilingualNameField } from '@/platform/forms/bilingual-name-field'
import { mapServerErrors } from '@/platform/forms/map-server-errors'
import { Button } from '@/platform/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/platform/components/ui/dialog'
import { useConfirm } from '@/platform/modals/confirm-dialog'
import { Dashboard } from '@/platform/dashboard/dashboard'
import { createKpiWidget } from '@/platform/widgets/kpi-widget'
import { useState } from 'react'

const columnHelper = createColumnHelper<MockRole>()
const columns = [
  columnHelper.accessor('name', { header: 'Name' }),
  columnHelper.accessor('display_name_en', { header: 'Display name (EN)' }),
  columnHelper.accessor('display_name_ar', { header: 'Display name (AR)' }),
  columnHelper.accessor('is_active', { header: 'Active', cell: (info) => (info.getValue() ? 'Yes' : 'No') }),
]

const widgets = [
  createKpiWidget({ id: 'dev-total-roles', titleKey: 'Total roles (mock)', dataSource: async () => ({ value: 42, trend: 'up' as const }) }),
  createKpiWidget({ id: 'dev-active-roles', titleKey: 'Active roles (mock)', dataSource: async () => ({ value: 34, trend: 'flat' as const }) }),
]

const schema = z.object({
  name: z.string().min(1),
  display_name_en: z.string().min(1),
  display_name_ar: z.string().min(1),
})

/**
 * Dev-only proving ground for DataTable/Form/Modal/Widget (ADR-0015
 * Decision 3) -- mounted only at /dev/harness, only in development
 * builds (see router.tsx's import.meta.env.DEV guard), never
 * registered in workspaces/registry.ts. Uses fixture data, not a real
 * Identity endpoint -- no Roles/Branches/Permissions list API exists
 * on the backend yet, so this proves the frameworks without inventing
 * new backend surface beyond ADR-0015's agreed prerequisite slice.
 */
export default function DevHarness() {
  const [modalOpen, setModalOpen] = useState(false)
  const queryClient = useQueryClient()
  const confirm = useConfirm()

  const { table, query, page, setPage, meta } = useServerDataTable({
    queryKey: ['dev-harness-roles'],
    queryFn: fetchMockRoles,
    columns,
  })

  const { control, handleSubmit, setError, reset } = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', display_name_en: '', display_name_ar: '' },
  })

  const mutation = useMutation({
    mutationFn: createMockRole,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dev-harness-roles'] })
      setModalOpen(false)
      reset()
    },
    onError: (error) => mapServerErrors(error, setError),
  })

  return (
    <div className="flex flex-col gap-6 p-6">
      <div>
        <h1 className="text-lg font-semibold">Dev harness -- Admin Platform Foundation</h1>
        <p className="text-sm text-muted-foreground">
          Not a workspace. Proves DataTable/Form/Modal/Widget against fixture data only.
        </p>
      </div>

      <Dashboard widgets={widgets} />

      <div className="flex items-center justify-between">
        <h2 className="text-sm font-medium">Mock roles</h2>
        <div className="flex gap-2">
          <Button
            variant="destructive"
            size="sm"
            onClick={async () => {
              const confirmed = await confirm({
                title: 'Delete role?',
                description: 'This is a ConfirmDialog proof only -- nothing is actually deleted.',
                variant: 'destructive',
              })
              if (confirmed) alert('Confirmed (no-op).')
            }}
          >
            Test ConfirmDialog
          </Button>
          <Button size="sm" onClick={() => setModalOpen(true)}>
            Create role
          </Button>
        </div>
      </div>

      <DataTable
        table={table}
        isLoading={query.isLoading}
        isError={query.isError}
        page={page}
        lastPage={meta?.last_page ?? 1}
        onPageChange={setPage}
      />

      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create role</DialogTitle>
          </DialogHeader>
          <form
            className="flex flex-col gap-4"
            onSubmit={handleSubmit((values) => mutation.mutate(values))}
          >
            <TextField control={control} name="name" label="Code name" placeholder="e.g. registrar" />
            <BilingualNameField
              control={control}
              nameEnField="display_name_en"
              nameArField="display_name_ar"
              labelEn="Display name (English)"
              labelAr="الاسم المعروض (عربي)"
            />
            <DialogFooter>
              <Button type="submit" disabled={mutation.isPending}>
                Save
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
