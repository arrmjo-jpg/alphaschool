import { useEffect } from 'react'
import { useNavigate } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { Tabs, TabsList, TabsTrigger } from '@/platform/components/ui/tabs'
import { useWorkspaceSubPath } from '@/platform/navigation/workspace-sub-path'
import { entityLinkProps, entityPathString } from '@/platform/entity-workspace/entity-route'
import { EntityListPage } from '@/platform/entity-workspace/entity-list-page'
import { EntityDetailPage } from '@/platform/entity-workspace/entity-detail-page'
import { EntityFormPage } from '@/platform/entity-workspace/entity-form-page'
import type { EntityDefinition, EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'

/**
 * Flat Tab Switcher (docs/ADMIN_DESIGN_SYSTEM.md §28.2) -- an
 * in-workspace-scoped concern, deliberately NOT registered through the
 * global WorkspaceDefinition registry (§28.15's Registration
 * Principle: that registry governs top-level workspace nav, a
 * different concern). Dispatches to List/Detail/Form (§28.3) by
 * parsing the workspace's own sub-path (useWorkspaceSubPath) --
 * router.tsx and WorkspaceRoutePage never learn about this shape.
 */
export function EntityWorkspaceShell({
  workspaceKey,
  entities,
  providers,
}: {
  workspaceKey: string
  entities: EntityDefinition<any>[]
  providers: Record<string, EntityDataProvider<any>>
}) {
  const { t } = useTranslation('entity-workspace')
  const navigate = useNavigate()
  const subPath = useWorkspaceSubPath()
  const segments = subPath.split('/').filter(Boolean)
  const [activeKey, ...rest] = segments

  const entity = entities.find((candidate) => candidate.key === activeKey)

  useEffect(() => {
    if (!entity && entities[0]) {
      navigate({ ...entityLinkProps(workspaceKey, entities[0].key), replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeKey])

  if (!entity) return null

  const provider = providers[entity.key]

  const goList = () => navigate(entityLinkProps(workspaceKey, entity.key))
  const goCreate = () => navigate(entityLinkProps(workspaceKey, entity.key, 'new'))
  const goDetail = (row: { id: number | string }) => navigate(entityLinkProps(workspaceKey, entity.key, row.id))
  const goEdit = (rowId: number | string) => navigate(entityLinkProps(workspaceKey, entity.key, rowId, 'edit'))

  return (
    <div className="flex flex-col gap-6">
      <Tabs value={entity.key} onValueChange={(key) => navigate(entityLinkProps(workspaceKey, key))}>
        <TabsList aria-label={t('tabs.ariaLabel')}>
          {entities.map((candidate) => (
            <TabsTrigger key={candidate.key} value={candidate.key}>
              {candidate.labelPlural}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {rest.length === 0 && (
        <EntityListPage entity={entity} provider={provider} onView={goDetail} onEdit={(row) => goEdit(row.id)} onCreate={goCreate} />
      )}

      {rest.length === 1 && rest[0] === 'new' && (
        <EntityFormPage
          entity={entity}
          provider={provider}
          mode="create"
          onSaved={goList}
          onCancel={goList}
          listHref={entityPathString(workspaceKey, entity.key)}
        />
      )}

      {rest.length === 1 && rest[0] !== 'new' && (
        <EntityDetailPage
          entity={entity}
          provider={provider}
          id={rest[0]!}
          backTo={entityPathString(workspaceKey, entity.key)}
          onEdit={() => goEdit(rest[0]!)}
          onBack={goList}
        />
      )}

      {rest.length === 2 && rest[1] === 'edit' && (
        <EntityFormPage
          entity={entity}
          provider={provider}
          mode="edit"
          id={rest[0]!}
          onSaved={(row) => navigate(entityLinkProps(workspaceKey, entity.key, row.id))}
          onCancel={goList}
          listHref={entityPathString(workspaceKey, entity.key)}
        />
      )}
    </div>
  )
}
