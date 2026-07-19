import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { apiFetch, ApiError } from '@/lib/api-client'
import { useAuthStore } from '@/platform/auth/auth-store'
import { TextField } from '@/platform/forms/text-field'
import { mapServerErrors } from '@/platform/forms/map-server-errors'
import { Button } from '@/platform/components/ui/button'
import { Skeleton } from '@/platform/components/ui/skeleton'
import { LoginBrandPanel } from '@/platform/shell/login-brand-panel'
import { useMaintenanceCheck } from '@/platform/shell/use-maintenance-check'

const schema = z.object({
  login: z.string().min(1),
  password: z.string().min(1),
})

type FormValues = z.infer<typeof schema>

type LoginResponse = { token: string; user: { public_id: string; username: string; email: string } }

/**
 * The split-screen structure (docs/ADMIN_DESIGN_SYSTEM.md §20.1): a
 * brand column and a form column on `lg` and up; below that the brand
 * column never disappears (§1.1/§3's confirmed legacy failure) --
 * it collapses into a compact header band instead of vanishing.
 */
export function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const setToken = useAuthStore((state) => state.setToken)
  const { maintenanceMode, isChecking } = useMaintenanceCheck()
  const { control, handleSubmit, setError, formState } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { login: '', password: '' },
  })

  const mutation = useMutation({
    mutationFn: (values: FormValues) => apiFetch<LoginResponse>('/login', { method: 'POST', body: values }),
    onSuccess: (data) => {
      setToken(data.token)
      navigate({ to: '/' })
    },
    onError: (error) => {
      if (error instanceof ApiError && error.status === 503) return
      if (!mapServerErrors(error, setError) && error instanceof ApiError) {
        setError('password', { type: 'server', message: t('shell.login.error') })
      }
    },
  })

  return (
    <div className="flex min-h-screen flex-col lg:flex-row">
      <div className="lg:hidden">
        <LoginBrandPanel maintenanceMode={maintenanceMode} compact />
      </div>
      <div className="hidden lg:block lg:flex-1">
        <LoginBrandPanel maintenanceMode={maintenanceMode} />
      </div>

      <div className="flex flex-1 items-center justify-center p-6 sm:p-10">
        {isChecking ? (
          <div className="flex w-full max-w-sm flex-col gap-4" aria-live="polite" aria-busy="true">
            <span className="sr-only">{t('shell.login.loading')}</span>
            <Skeleton className="h-6 w-32" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
          </div>
        ) : maintenanceMode ? (
          <div className="flex w-full max-w-sm flex-col gap-2 text-center">
            <h2 className="text-lg font-semibold">{t('shell.login.maintenanceTitle')}</h2>
            <p className="text-sm text-muted-foreground">{t('shell.login.maintenanceFormHint')}</p>
          </div>
        ) : (
          <form
            onSubmit={handleSubmit((values) => mutation.mutate(values))}
            className="flex w-full max-w-sm flex-col gap-4"
          >
            <h1 className="text-lg font-semibold">{t('shell.login.title')}</h1>
            <TextField control={control} name="login" label={t('shell.login.username')} />
            <TextField control={control} name="password" label={t('shell.login.password')} type="password" />
            <Button type="submit" disabled={formState.isSubmitting || mutation.isPending} className="mt-2">
              {t('shell.login.submit')}
            </Button>
          </form>
        )}
      </div>
    </div>
  )
}
