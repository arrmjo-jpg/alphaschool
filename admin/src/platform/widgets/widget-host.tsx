import { useQuery } from '@tanstack/react-query'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Can } from '@/platform/auth/can'
import type { WidgetDefinition } from '@/platform/widgets/widget-definition'

function WidgetBody<T>({ widget }: { widget: WidgetDefinition<T> }) {
  const { t } = useTranslation()
  const { data, isLoading, isError } = useQuery({
    queryKey: ['widget', widget.id],
    queryFn: widget.dataSource,
  })

  return (
    <div className="flex flex-col gap-2 rounded-md border bg-card p-4 text-card-foreground">
      <h3 className="text-sm font-medium text-muted-foreground">{t(widget.titleKey, widget.titleKey)}</h3>
      {isLoading ? (
        <Loader2 className="size-5 animate-spin text-muted-foreground" />
      ) : isError ? (
        <p className="text-sm text-destructive">Failed to load.</p>
      ) : (
        widget.render(data as T)
      )}
    </div>
  )
}

/** Renders a WidgetDefinition, gated by its declared permission if any. */
export function WidgetHost<T>({ widget }: { widget: WidgetDefinition<T> }) {
  if (widget.requiredPermission === null) {
    return <WidgetBody widget={widget} />
  }

  return (
    <Can permission={widget.requiredPermission}>
      <WidgetBody widget={widget} />
    </Can>
  )
}
