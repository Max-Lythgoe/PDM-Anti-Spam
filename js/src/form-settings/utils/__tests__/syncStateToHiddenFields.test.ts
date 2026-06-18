/**
 * Tests for syncStateToHiddenFields utility
 *
 * These tests verify that:
 * 1. Transform functions correctly convert values to strings
 * 2. State is properly synced to hidden form fields
 */

import {
	syncStateToHiddenFields,
	transforms,
} from '../syncStateToHiddenFields';
import { setDebugEnabled } from '../../../shared/logger';

describe('transforms', () => {
	describe('arrayToJson', () => {
		it('should convert an empty array to JSON string', () => {
			expect(transforms.arrayToJson([])).toBe('[]');
		});

		it('should convert an array of primitives to JSON string', () => {
			expect(transforms.arrayToJson([1, 2, 3])).toBe('[1,2,3]');
			expect(transforms.arrayToJson(['a', 'b', 'c'])).toBe(
				'["a","b","c"]'
			);
		});

		it('should convert an array of objects to JSON string', () => {
			const input = [
				{ id: '1', name: 'test' },
				{ id: '2', name: 'test2' },
			];
			expect(transforms.arrayToJson(input)).toBe(JSON.stringify(input));
		});

		it('should handle null/undefined by returning empty array JSON', () => {
			expect(transforms.arrayToJson(null as any)).toBe('[]');
			expect(transforms.arrayToJson(undefined as any)).toBe('[]');
		});

		it('should handle nested arrays', () => {
			const input = [
				[1, 2],
				[3, 4],
			];
			expect(transforms.arrayToJson(input)).toBe('[[1,2],[3,4]]');
		});
	});

	describe('objectToJson', () => {
		it('should convert an empty object to JSON string', () => {
			expect(transforms.objectToJson({})).toBe('{}');
		});

		it('should convert a simple object to JSON string', () => {
			const input = { key: 'value', num: 42 };
			expect(transforms.objectToJson(input)).toBe(JSON.stringify(input));
		});

		it('should handle null/undefined by returning empty object JSON', () => {
			expect(transforms.objectToJson(null as any)).toBe('{}');
			expect(transforms.objectToJson(undefined as any)).toBe('{}');
		});

		it('should handle nested objects', () => {
			const input = { outer: { inner: 'value' } };
			expect(transforms.objectToJson(input)).toBe(JSON.stringify(input));
		});
	});

	describe('booleanToString', () => {
		it('should convert true to "1"', () => {
			expect(transforms.booleanToString(true)).toBe('1');
		});

		it('should convert false to "0"', () => {
			expect(transforms.booleanToString(false)).toBe('0');
		});

		it('should handle truthy values as true', () => {
			expect(transforms.booleanToString(1 as any)).toBe('1');
			expect(transforms.booleanToString('yes' as any)).toBe('1');
		});

		it('should handle falsy values as false', () => {
			expect(transforms.booleanToString(0 as any)).toBe('0');
			expect(transforms.booleanToString('' as any)).toBe('0');
			expect(transforms.booleanToString(null as any)).toBe('0');
			expect(transforms.booleanToString(undefined as any)).toBe('0');
		});
	});

	describe('numberToString', () => {
		it('should convert positive numbers to string', () => {
			expect(transforms.numberToString(42)).toBe('42');
			expect(transforms.numberToString(3.14)).toBe('3.14');
		});

		it('should convert zero to string', () => {
			expect(transforms.numberToString(0)).toBe('0');
		});

		it('should convert negative numbers to string', () => {
			expect(transforms.numberToString(-10)).toBe('-10');
		});

		it('should handle null/undefined by returning empty string', () => {
			expect(transforms.numberToString(null as any)).toBe('');
			expect(transforms.numberToString(undefined as any)).toBe('');
		});

		it('should handle NaN', () => {
			expect(transforms.numberToString(NaN)).toBe('NaN');
		});

		it('should handle Infinity', () => {
			expect(transforms.numberToString(Infinity)).toBe('Infinity');
			expect(transforms.numberToString(-Infinity)).toBe('-Infinity');
		});
	});
});

describe('syncStateToHiddenFields', () => {
	let mockField: HTMLInputElement;
	let querySelectorSpy: jest.SpyInstance;
	let consoleWarnSpy: jest.SpyInstance;

	beforeEach(() => {
		// Create a mock input element
		mockField = document.createElement('input');
		mockField.type = 'hidden';

		// Mock querySelector to return our mock field
		querySelectorSpy = jest
			.spyOn(document, 'querySelector')
			.mockReturnValue(mockField);

		// Spy on console.warn
		consoleWarnSpy = jest
			.spyOn(console, 'warn')
			.mockImplementation(() => {});
	});

	afterEach(() => {
		querySelectorSpy.mockRestore();
		consoleWarnSpy.mockRestore();
	});

	it('should sync a simple string value to hidden field', () => {
		const state = { triggerType: 'button' };
		const mappings = {
			triggerType: { fieldName: 'trigger_type' },
		};

		syncStateToHiddenFields(state, mappings, '_gform_setting_');

		expect(querySelectorSpy).toHaveBeenCalledWith(
			'input[name="_gform_setting_trigger_type"]'
		);
		expect(mockField.value).toBe('button');
	});

	it('should apply transform function when provided', () => {
		const state = { backdropBlur: true };
		const mappings = {
			backdropBlur: {
				fieldName: 'backdrop_blur',
				transform: transforms.booleanToString,
			},
		};

		syncStateToHiddenFields(state, mappings, '_gform_setting_');

		expect(mockField.value).toBe('1');
	});

	it('should sync multiple fields at once', () => {
		const fields: Record<string, HTMLInputElement> = {};
		querySelectorSpy.mockImplementation((selector: string) => {
			const match = selector.match(/input\[name="_gform_setting_(.+)"\]/);
			if (match) {
				const fieldName = match[1];
				if (!fields[fieldName]) {
					fields[fieldName] = document.createElement('input');
				}
				return fields[fieldName];
			}
			return null;
		});

		const state = {
			triggerType: 'timeout',
			triggerDelay: 5000,
			backdropBlur: false,
		};
		const mappings = {
			triggerType: { fieldName: 'trigger_type' },
			triggerDelay: {
				fieldName: 'trigger_delay',
				transform: transforms.numberToString,
			},
			backdropBlur: {
				fieldName: 'backdrop_blur',
				transform: transforms.booleanToString,
			},
		};

		syncStateToHiddenFields(state, mappings, '_gform_setting_');

		expect(fields.trigger_type.value).toBe('timeout');
		expect(fields.trigger_delay.value).toBe('5000');
		expect(fields.backdrop_blur.value).toBe('0');
	});

	it('should warn when hidden field is not found', () => {
		// Enable debug mode so logger.warn actually outputs
		setDebugEnabled(true);

		querySelectorSpy.mockReturnValue(null);

		const state = { missingField: 'value' };
		const mappings = {
			missingField: { fieldName: 'missing_field' },
		};

		syncStateToHiddenFields(state, mappings, '_gform_setting_');

		expect(consoleWarnSpy).toHaveBeenCalledWith(
			'[GF Spam Hexer] [SyncStateToHiddenFields]',
			'Hidden field not found: _gform_setting_missing_field'
		);

		// Reset debug mode
		setDebugEnabled(false);
	});

	it('should dispatch change event on field update', () => {
		const changeHandler = jest.fn();
		mockField.addEventListener('change', changeHandler);

		const state = { triggerType: 'exit_intent' };
		const mappings = {
			triggerType: { fieldName: 'trigger_type' },
		};

		syncStateToHiddenFields(state, mappings, '_gform_setting_');

		expect(changeHandler).toHaveBeenCalled();
	});

	it('should handle empty/null values gracefully', () => {
		const state = {
			emptyString: '',
			nullValue: null,
			undefinedValue: undefined,
		};
		const mappings = {
			emptyString: { fieldName: 'empty_string' },
			nullValue: { fieldName: 'null_value' },
			undefinedValue: { fieldName: 'undefined_value' },
		};

		const fields: Record<string, HTMLInputElement> = {};
		querySelectorSpy.mockImplementation((selector: string) => {
			const match = selector.match(/input\[name="_gform_setting_(.+)"\]/);
			if (match) {
				const fieldName = match[1];
				if (!fields[fieldName]) {
					fields[fieldName] = document.createElement('input');
				}
				return fields[fieldName];
			}
			return null;
		});

		syncStateToHiddenFields(state, mappings, '_gform_setting_');

		expect(fields.empty_string.value).toBe('');
		expect(fields.null_value.value).toBe('');
		expect(fields.undefined_value.value).toBe('');
	});
});
