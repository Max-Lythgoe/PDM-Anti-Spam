/**
 * Shared Settings Types
 *
 * Common type definitions used across both plugin-settings (global)
 * and form-settings (per-form) React apps.
 */

/**
 * Action to take when a technique detects spam.
 * - 'spam':   Flag as spam (entry goes to spam folder for review)
 * - 'reject': Silent reject (entry discarded, bot sees fake confirmation)
 * - 'fail':   Validation error (form shows an error, entry not created)
 */
export type TechniqueAction = 'spam' | 'reject' | 'fail';

/**
 * AI provider mode.
 * - 'auto': Use WP AI Client (WP 7.0+), auto-routes to configured providers
 * - 'openrouter': Direct HTTP to OpenRouter (fallback for WP < 7.0)
 */
export type AiProviderMode = 'auto' | 'openrouter';
