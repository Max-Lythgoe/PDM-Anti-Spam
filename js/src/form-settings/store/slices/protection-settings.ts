/**
 * Protection Settings Slice
 *
 * Previously managed scoring thresholds. Thresholds are now derived from
 * per-technique action settings, so this slice only exports the
 * EnabledState type and helper function used by other parts of the app.
 */

export type EnabledState = 'global' | 'enabled' | 'disabled';

/**
 * Read the current value of the GF-rendered gfsh_enabled select field.
 *
 * Since gfsh_enabled is a visible GF select (not a hidden input), we read
 * from the select element directly.
 */
export function getGpshEnabledValue(): EnabledState {
	const select = document.querySelector(
		'select[name="_gform_setting_gfsh_enabled"]'
	) as HTMLSelectElement | null;
	return (select?.value as EnabledState) || 'global';
}
