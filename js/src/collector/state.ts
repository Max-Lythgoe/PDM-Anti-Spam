/**
 * Shared collector state — Maps and Sets used across collector modules.
 *
 * @module collector/state
 */

import type { PoWManager } from './pow-manager';
import type { GpshFrontendConfig } from '../frontend';

/** Active PoW managers per form. */
export const powManagers = new Map<number, PoWManager>();

/** Per-form config for the submission hook. */
export const formConfigs = new Map<number, GpshFrontendConfig>();

/** Forms currently being refreshed (prevents concurrent refreshes). */
export const refreshingForms = new Set<number>();

/** Forms with page-change watchers bound. */
export const pageWatchersBound = new Set<number>();
