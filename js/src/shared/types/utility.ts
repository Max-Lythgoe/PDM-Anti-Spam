/**
 * Utility Types
 *
 * Generic TypeScript utility types for type transformations.
 * These are reusable across the entire codebase.
 */

// ============================================================================
// Case Conversion Types
// ============================================================================

/**
 * Convert a camelCase string to snake_case
 *
 * @example
 * type Result = CamelToSnakeCase<'triggerType'>; // 'trigger_type'
 */
export type CamelToSnakeCase<S extends string> =
	S extends `${infer T}${infer U}`
		? `${T extends Capitalize<T> ? '_' : ''}${Lowercase<T>}${CamelToSnakeCase<U>}`
		: S;

/**
 * Convert all keys in an object type from camelCase to snake_case
 *
 * @example
 * type Result = KeysToSnakeCase<{ triggerType: string }>; // { trigger_type: string }
 */
export type KeysToSnakeCase<T> = {
	[K in keyof T as CamelToSnakeCase<string & K>]: T[K];
};

// ============================================================================
// Value Transformation Types
// ============================================================================

/**
 * Convert all values in an object type to strings.
 * Useful for WordPress meta storage where everything is stored as strings.
 *
 * @example
 * type Result = ValuesToString<{ count: number; active: boolean }>; // { count: string; active: string }
 */
export type ValuesToString<T> = {
	[K in keyof T]: string;
};
