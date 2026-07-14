export type Command = {
  id: string
  labelKey: string
  run: () => void
  shortcut?: string
}

const commands: Command[] = []

export function registerCommand(command: Command): void {
  commands.push(command)
}

export function getCommands(): Command[] {
  return commands
}
