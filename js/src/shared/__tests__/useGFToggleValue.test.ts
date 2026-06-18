/**
 * Tests for useGFToggleValue hook.
 *
 * Verifies that the hook watches a GF-rendered toggle element
 * and returns the correct enabled/disabled state.
 */

import { createElement } from '@wordpress/element';
import { render, cleanup, act } from '@testing-library/react';
import { useGFToggleValue } from '../hooks/useGFToggleValue';

describe('useGFToggleValue', () => {
	let mockElement: HTMLSelectElement;

	beforeEach(() => {
		mockElement = document.createElement('select');
		mockElement.id = 'test-toggle';
		mockElement.innerHTML = `
			<option value="enabled">Enabled</option>
			<option value="disabled">Disabled</option>
		`;
		document.body.appendChild(mockElement);
	});

	afterEach(() => {
		cleanup();
		document.body.innerHTML = '';
	});

	/**
	 * Helper component that renders the hook result as text.
	 */
	function TestComponent({
		selector,
		disabledValue,
		defaultEnabled,
	}: {
		selector: string;
		disabledValue: string;
		defaultEnabled?: boolean;
	}) {
		const isEnabled = useGFToggleValue({
			selector,
			disabledValue,
			defaultEnabled,
		});
		return createElement(
			'span',
			{ 'data-testid': 'result' },
			String(isEnabled)
		);
	}

	it('returns true when value !== disabledValue', () => {
		mockElement.value = 'enabled';

		const { getByTestId } = render(
			createElement(TestComponent, {
				selector: '#test-toggle',
				disabledValue: 'disabled',
			})
		);

		expect(getByTestId('result').textContent).toBe('true');
	});

	it('returns false when value === disabledValue', () => {
		mockElement.value = 'disabled';

		const { getByTestId } = render(
			createElement(TestComponent, {
				selector: '#test-toggle',
				disabledValue: 'disabled',
			})
		);

		expect(getByTestId('result').textContent).toBe('false');
	});

	it('updates on change event', () => {
		mockElement.value = 'enabled';

		const { getByTestId } = render(
			createElement(TestComponent, {
				selector: '#test-toggle',
				disabledValue: 'disabled',
			})
		);

		expect(getByTestId('result').textContent).toBe('true');

		act(() => {
			mockElement.value = 'disabled';
			mockElement.dispatchEvent(new Event('change'));
		});

		expect(getByTestId('result').textContent).toBe('false');
	});

	it('uses defaultEnabled when element not found', () => {
		const { getByTestId } = render(
			createElement(TestComponent, {
				selector: '.nonexistent-element',
				disabledValue: 'disabled',
				defaultEnabled: false,
			})
		);

		expect(getByTestId('result').textContent).toBe('false');
	});

	it('defaults to enabled when element not found and no defaultEnabled', () => {
		const { getByTestId } = render(
			createElement(TestComponent, {
				selector: '.nonexistent-element',
				disabledValue: 'disabled',
			})
		);

		expect(getByTestId('result').textContent).toBe('true');
	});
});
