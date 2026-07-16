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

const schema = z.object({
  login: z.string().min(1),
  password: z.string().min(1),
})

type FormValues = z.infer<typeof schema>

type LoginResponse = { token: string; user: { public_id: string; username: string; email: string } }

export function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const setToken = useAuthStore((state) => state.setToken)
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
      if (!mapServerErrors(error, setError) && error instanceof ApiError) {
        setError('password', { type: 'server', message: t('shell.login.error') })
      }
    },
  })

  return (
    <div className="flex min-h-screen items-center justify-center bg-background p-4">
      <form
        onSubmit={handleSubmit((values) => mutation.mutate(values))}
        className="flex w-full max-w-sm flex-col gap-4 rounded-md border bg-card p-6 text-card-foreground shadow-sm"
      >
        <h1 className="text-lg font-semibold">{t('shell.login.title')}</h1>
        <TextField control={control} name="login" label={t('shell.login.username')} />
        <TextField control={control} name="password" label={t('shell.login.password')} type="password" />
        <Button type="submit" disabled={formState.isSubmitting || mutation.isPending} className="mt-2">
          {t('shell.login.submit')}
        </Button>
      </form>
    </div>
  )
}
