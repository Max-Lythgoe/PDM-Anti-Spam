/**
 * GF Spam Hexer — Debug API
 *
 * Exposes window.gfshDebug when debug mode or Dev Tools is active.
 * Extracted from the collector to keep debug-only code out of the
 * main collector module and make it independently testable.
 *
 * @module collector/debug-api
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

import type { PoWManager, PoWFormConfig, PoWSolution } from './pow-manager';
import type { GpshFrontendConfig } from '../frontend';
import { createLogger, isDebugEnabled } from '@shared/logger';

const logger = createLogger('Collector');

/** Dependencies injected from the collector module. */
export interface CollectorState {
	powManagers: Map<number, PoWManager>;
	formConfigs: Map<number, GpshFrontendConfig>;
	refreshingForms: Set<number>;
	isOnLastPage: (formId: number) => boolean;
	initPoW: (formId: number, config: GpshFrontendConfig) => void;
	writePayloadField: (
		formId: number,
		solution: PoWSolution,
		fieldName: string
	) => void;
	createManager: (
		formId: number,
		config: GpshFrontendConfig,
		overrides?: Partial<PoWFormConfig>
	) => PoWManager;
}

/**
 * Exposes `window.gfshDebug` when debug mode or Dev Tools is active.
 *
 * Provides programmatic access to PoW managers, configs, and testing
 * utilities for the GF Dev Tools QM panel action buttons.
 */
export function maybeExposeDebugApi(state: CollectorState): void {
	const {
		powManagers,
		formConfigs,
		refreshingForms,
		isOnLastPage,
		initPoW,
		writePayloadField,
		createManager,
	} = state;

	setTimeout(() => {
		const shouldExpose =
			isDebugEnabled() ||
			!!(window as any).gfDebugData?.extensions?.['spam-hexer'];

		if (!shouldExpose) {
			return;
		}

		(window as any).gfshDebug = {
			getManagers: () => {
				const result: Record<number, PoWManager> = {};
				powManagers.forEach((v, k) => {
					result[k] = v;
				});
				return result;
			},
			getConfigs: () => {
				const result: Record<number, GpshFrontendConfig> = {};
				formConfigs.forEach((v, k) => {
					result[k] = v;
				});
				return result;
			},
			getStatus: (formId: number) => {
				const manager = powManagers.get(formId);
				if (!manager) {
					return { status: 'no_manager' as const };
				}
				const cfg = formConfigs.get(formId);
				const debugStatus = manager.getDebugStatus();
				return {
					...debugStatus,
					isRefreshing: refreshingForms.has(formId),
					isDeferredForPage:
						!!cfg?.hasPages &&
						!debugStatus.hasSolution &&
						!debugStatus.isSolving &&
						!cfg?.powOnSubmit &&
						!isOnLastPage(formId),
				};
			},
			forceFallback: (formId: number) => {
				const config = formConfigs.get(formId);
				if (!config) {
					return;
				}
				const existing = powManagers.get(formId);
				if (existing) {
					existing.destroy();
				}
				const manager = createManager(formId, config, {
					fetchTimeout: 1,
				});
				powManagers.set(formId, manager);
				manager
					.init()
					.then(() => manager.waitForSolution())
					.then((sol) => {
						if (sol) {
							writePayloadField(formId, sol, config.payloadField);
						}
					});
			},
			forceExpire: (formId: number) => {
				const manager = powManagers.get(formId);
				if (manager) {
					manager.forceExpire();
				}
			},
			clearSolution: (formId: number) => {
				const manager = powManagers.get(formId);
				const config = formConfigs.get(formId);
				if (manager) {
					manager.destroy();
					powManagers.delete(formId);
				}
				if (config) {
					const field = document.querySelector<HTMLInputElement>(
						`#gform_${formId} input[name="${config.payloadField}"]`
					);
					if (field) {
						field.value = '';
					}
				}
				logger.log(
					`formId ${formId} — solution cleared (PoW will be missing on submit)`
				);
			},
			setPowOnSubmit: (formId: number) => {
				const config = formConfigs.get(formId);
				if (!config) {
					return;
				}
				// Destroy existing manager (which may already have a solution).
				const existing = powManagers.get(formId);
				if (existing) {
					existing.destroy();
					powManagers.delete(formId);
				}
				// Clear the payload field so the user sees it go empty.
				const field = document.querySelector<HTMLInputElement>(
					`#gform_${formId} input[name="${config.payloadField}"]`
				);
				if (field) {
					field.value = '';
				}
				// Re-init in powOnSubmit mode.
				config.powOnSubmit = true;
				initPoW(formId, config);
				logger.log(`formId ${formId} — switched to powOnSubmit mode`);
			},
		};
		logger.log('Debug API exposed as window.gfshDebug');
	}, 100);
}
