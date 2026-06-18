/**
 * Hidden Field Value Reader
 *
 * Reads initial values from Gravity Forms hidden fields.
 * Supports both GF form settings (`_gform_setting_`) and
 * GF addon/plugin settings (`_gaddon_setting_`) prefixes.
 */

/** GF hidden field prefixes. */
export type GFFieldPrefix = '_gform_setting_' | '_gaddon_setting_' | '';

/**
 * Read a value from a GF hidden input field.
 *
 * @param prefix    - The GF field name prefix
 * @param fieldName - The setting field name (without prefix)
 * @param fallback  - Default value if the field is missing or empty
 */
export function getHiddenFieldValue(
	prefix: GFFieldPrefix,
	fieldName: string,
	fallback: string
): string {
	const field = document.querySelector(
		`input[name="${prefix}${fieldName}"]`
	) as HTMLInputElement | null;
	return field?.value || fallback;
}
