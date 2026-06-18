/**
 * Payload field management — DOM helpers for the hidden gfsh_payload field.
 *
 * @module collector/payload
 */

import type { PoWManager, PoWSolution } from './pow-manager';
import { createLogger } from '@shared/logger';

const logger = createLogger('Collector');

/**
 * Ensures the hidden payload field exists in the form.
 *
 * Creates it if it doesn't exist (e.g., if the form was loaded via AJAX).
 */
export function ensurePayloadField(formId: number, fieldName: string): void {
	const form = document.getElementById(`gform_${formId}`);

	if (!form) {
		logger.warn(
			`formId ${formId} — form element #gform_${formId} not found in DOM`
		);
		return;
	}

	const existing = form.querySelector<HTMLInputElement>(
		`input[name="${fieldName}"]`
	);

	if (existing) {
		logger.log(
			`formId ${formId} — payload field "${fieldName}" already exists`
		);
		return;
	}

	logger.log(
		`formId ${formId} — creating hidden payload field "${fieldName}"`
	);
	const input = document.createElement('input');
	input.type = 'hidden';
	input.name = fieldName;
	input.value = '';
	form.appendChild(input);
}

/**
 * Writes a PoW solution to the hidden payload field for a form.
 */
export function writePayloadField(
	formId: number,
	solution: PoWSolution,
	fieldName: string
): void {
	const payload = assemblePayload(solution);
	const encoded = btoa(JSON.stringify(payload));

	const field = document.querySelector<HTMLInputElement>(
		`#gform_${formId} input[name="${fieldName}"]`
	);

	if (field) {
		field.value = encoded;
		logger.log(`formId ${formId} — payload field populated`);
	} else {
		logger.warn(
			`formId ${formId} — payload field not found: #gform_${formId} input[name="${fieldName}"]`
		);
	}
}

/**
 * Assembles the collector payload from a PoW solution.
 *
 * @return The payload object to be JSON-encoded and base64'd.
 */
export function assemblePayload(
	solution: NonNullable<ReturnType<PoWManager['getSolution']>>
): Record<string, unknown> {
	return {
		v: 1, // Payload version for future compatibility.
		t: Date.now(), // Client timestamp.
		pow: solution,
	};
}
