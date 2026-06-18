/**
 * Tests for getHiddenFieldValue utility.
 *
 * Verifies reading values from GF hidden input fields
 * with both _gform_setting_ and _gaddon_setting_ prefixes.
 */

import { getHiddenFieldValue } from '../utils/getHiddenFieldValue';

describe('getHiddenFieldValue', () => {
	let querySelectorSpy: jest.SpyInstance;

	afterEach(() => {
		querySelectorSpy?.mockRestore();
	});

	it('reads value from existing hidden field', () => {
		const mockField = document.createElement('input');
		mockField.type = 'hidden';
		mockField.value = 'my_value';

		querySelectorSpy = jest
			.spyOn(document, 'querySelector')
			.mockReturnValue(mockField);

		const result = getHiddenFieldValue(
			'_gform_setting_',
			'pow_enabled',
			'fallback'
		);

		expect(result).toBe('my_value');
		expect(querySelectorSpy).toHaveBeenCalledWith(
			'input[name="_gform_setting_pow_enabled"]'
		);
	});

	it('returns fallback when field missing', () => {
		querySelectorSpy = jest
			.spyOn(document, 'querySelector')
			.mockReturnValue(null);

		const result = getHiddenFieldValue(
			'_gform_setting_',
			'missing_field',
			'default_val'
		);

		expect(result).toBe('default_val');
	});

	it('returns fallback when field value is empty', () => {
		const mockField = document.createElement('input');
		mockField.type = 'hidden';
		mockField.value = '';

		querySelectorSpy = jest
			.spyOn(document, 'querySelector')
			.mockReturnValue(mockField);

		const result = getHiddenFieldValue(
			'_gform_setting_',
			'empty_field',
			'fallback'
		);

		expect(result).toBe('fallback');
	});

	it('works with _gform_setting_ prefix', () => {
		const mockField = document.createElement('input');
		mockField.value = 'gform_value';

		querySelectorSpy = jest
			.spyOn(document, 'querySelector')
			.mockReturnValue(mockField);

		getHiddenFieldValue('_gform_setting_', 'test_key', '');

		expect(querySelectorSpy).toHaveBeenCalledWith(
			'input[name="_gform_setting_test_key"]'
		);
	});

	it('works with _gaddon_setting_ prefix', () => {
		const mockField = document.createElement('input');
		mockField.value = 'addon_value';

		querySelectorSpy = jest
			.spyOn(document, 'querySelector')
			.mockReturnValue(mockField);

		const result = getHiddenFieldValue('_gaddon_setting_', 'api_key', '');

		expect(result).toBe('addon_value');
		expect(querySelectorSpy).toHaveBeenCalledWith(
			'input[name="_gaddon_setting_api_key"]'
		);
	});
});
