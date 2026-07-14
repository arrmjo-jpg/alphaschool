export type CurrentUser = {
  public_id: string
  username: string
  email: string
  is_super_admin: boolean
  name: { en: string; ar: string } | null
}

export type MeResponse = {
  user: CurrentUser
  permissions: string[]
}

export type WorkspaceAccess = {
  key: string
  required_permission: string
}
