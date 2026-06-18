/**
 * Form Field Component (Backward Compatibility)
 *
 * Re-exports SettingsField as FormField for existing consumers.
 * New code should import SettingsField from './ui/SettingsField' directly.
 *
 * @deprecated Use `SettingsField` from `./ui/SettingsField` instead.
 */

export { SettingsField as FormField } from './ui/SettingsField';
export type { SettingsFieldProps as FormFieldProps } from './ui/SettingsField';
