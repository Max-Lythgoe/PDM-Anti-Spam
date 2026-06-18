/**
 * Comment Settings Field Mappings
 *
 * Maps Zustand store keys to hidden field names.
 * Uses the shared sync utility via createStateSyncer.
 *
 * Note: comment settings use full field names (no prefix needed).
 * Pass prefix='' to createStateSyncer.
 */

import type { StateToFieldMapping } from '../../shared/utils/syncStateToHiddenFields';

export const commentSettingsMappings: StateToFieldMapping = {
	enabled: { fieldName: 'gfsh_comment_enabled' },
	powEnabled: { fieldName: 'gfsh_comment_pow_enabled' },
	aiEnabled: { fieldName: 'gfsh_comment_ai_enabled' },
	bypassLoggedIn: { fieldName: 'gfsh_comment_bypass_loggedin' },
	powProtectionLevel: { fieldName: 'gfsh_comment_pow_protection_level' },
	aiCustomContext: { fieldName: 'gfsh_comment_ai_custom_context' },
	aiConfidenceThreshold: {
		fieldName: 'gfsh_comment_ai_confidence_threshold',
	},
	powAction: { fieldName: 'gfsh_comment_pow_action' },
	aiAction: { fieldName: 'gfsh_comment_ai_action' },
	powFailMessage: { fieldName: 'gfsh_comment_pow_fail_message' },
	aiFailMessage: { fieldName: 'gfsh_comment_ai_fail_message' },
};
