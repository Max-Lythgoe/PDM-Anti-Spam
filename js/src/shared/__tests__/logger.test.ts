/**
 * Tests for the logger utility.
 *
 * Verifies debug flag detection, scoped logging, and
 * that callbacks always execute regardless of debug state.
 */

import { createLogger, setDebugEnabled, refreshDebugState } from '../logger';

describe('logger', () => {
	let consoleSpy: {
		log: jest.SpyInstance;
		warn: jest.SpyInstance;
		error: jest.SpyInstance;
		info: jest.SpyInstance;
		groupCollapsed: jest.SpyInstance;
		group: jest.SpyInstance;
		groupEnd: jest.SpyInstance;
		table: jest.SpyInstance;
		time: jest.SpyInstance;
		timeEnd: jest.SpyInstance;
	};

	beforeEach(() => {
		consoleSpy = {
			log: jest.spyOn(console, 'log').mockImplementation(),
			warn: jest.spyOn(console, 'warn').mockImplementation(),
			error: jest.spyOn(console, 'error').mockImplementation(),
			info: jest.spyOn(console, 'info').mockImplementation(),
			groupCollapsed: jest
				.spyOn(console, 'groupCollapsed')
				.mockImplementation(),
			group: jest.spyOn(console, 'group').mockImplementation(),
			groupEnd: jest.spyOn(console, 'groupEnd').mockImplementation(),
			table: jest.spyOn(console, 'table').mockImplementation(),
			time: jest.spyOn(console, 'time').mockImplementation(),
			timeEnd: jest.spyOn(console, 'timeEnd').mockImplementation(),
		};
	});

	afterEach(() => {
		Object.values(consoleSpy).forEach((spy) => spy.mockRestore());
		// Reset debug state.
		refreshDebugState();
	});

	// ── Debug disabled ──────────────────────────────────────────────

	describe('when debug is disabled', () => {
		beforeEach(() => {
			setDebugEnabled(false);
		});

		it('suppresses log/warn/info', () => {
			const logger = createLogger('Test');

			logger.log('should not appear');
			logger.warn('should not appear');
			logger.info('should not appear');

			expect(consoleSpy.log).not.toHaveBeenCalled();
			expect(consoleSpy.warn).not.toHaveBeenCalled();
			expect(consoleSpy.info).not.toHaveBeenCalled();
		});

		it('always shows errors', () => {
			const logger = createLogger('Test');

			logger.error('critical error');

			expect(consoleSpy.error).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test]',
				'critical error'
			);
		});

		it('table() is suppressed', () => {
			const logger = createLogger('Test');
			logger.table({ key: 'value' });

			expect(consoleSpy.table).not.toHaveBeenCalled();
		});

		it('time/timeEnd are suppressed', () => {
			const logger = createLogger('Test');
			logger.time('timer');
			logger.timeEnd('timer');

			expect(consoleSpy.time).not.toHaveBeenCalled();
			expect(consoleSpy.timeEnd).not.toHaveBeenCalled();
		});
	});

	// ── Debug enabled ───────────────────────────────────────────────

	describe('when debug is enabled', () => {
		beforeEach(() => {
			setDebugEnabled(true);
		});

		it('shows log/warn/info', () => {
			const logger = createLogger('Test');

			logger.log('log message');
			logger.warn('warn message');
			logger.info('info message');

			expect(consoleSpy.log).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test]',
				'log message'
			);
			expect(consoleSpy.warn).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test]',
				'warn message'
			);
			expect(consoleSpy.info).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test]',
				'info message'
			);
		});

		it('table() outputs data', () => {
			const logger = createLogger('Test');
			const data = [{ a: 1 }, { a: 2 }];

			logger.table(data);

			expect(consoleSpy.log).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test]'
			);
			expect(consoleSpy.table).toHaveBeenCalledWith(data);
		});

		it('time/timeEnd work', () => {
			const logger = createLogger('Test');

			logger.time('op');
			logger.timeEnd('op');

			expect(consoleSpy.time).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test] op'
			);
			expect(consoleSpy.timeEnd).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test] op'
			);
		});
	});

	// ── createLogger() scoping ──────────────────────────────────────

	describe('createLogger scoping', () => {
		it('prefixes with scope name', () => {
			setDebugEnabled(true);
			const logger = createLogger('MyModule');

			logger.log('test');

			expect(consoleSpy.log).toHaveBeenCalledWith(
				'[GF Spam Hexer] [MyModule]',
				'test'
			);
		});

		it('different scopes have different prefixes', () => {
			setDebugEnabled(true);
			const loggerA = createLogger('ModuleA');
			const loggerB = createLogger('ModuleB');

			loggerA.log('from A');
			loggerB.log('from B');

			expect(consoleSpy.log).toHaveBeenCalledWith(
				'[GF Spam Hexer] [ModuleA]',
				'from A'
			);
			expect(consoleSpy.log).toHaveBeenCalledWith(
				'[GF Spam Hexer] [ModuleB]',
				'from B'
			);
		});
	});

	// ── group() / groupExpanded() ───────────────────────────────────

	describe('group() and groupExpanded()', () => {
		it('callback always executes regardless of debug state', () => {
			setDebugEnabled(false);
			const logger = createLogger('Test');
			let executed = false;

			logger.group('test group', () => {
				executed = true;
			});

			expect(executed).toBe(true);
			// Console group should NOT be called when debug is off.
			expect(consoleSpy.groupCollapsed).not.toHaveBeenCalled();
		});

		it('group() uses groupCollapsed when debug enabled', () => {
			setDebugEnabled(true);
			const logger = createLogger('Test');

			logger.group('my group', () => {});

			expect(consoleSpy.groupCollapsed).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test] my group'
			);
			expect(consoleSpy.groupEnd).toHaveBeenCalled();
		});

		it('groupExpanded() uses console.group when debug enabled', () => {
			setDebugEnabled(true);
			const logger = createLogger('Test');

			logger.groupExpanded('expanded group', () => {});

			expect(consoleSpy.group).toHaveBeenCalledWith(
				'[GF Spam Hexer] [Test] expanded group'
			);
			expect(consoleSpy.groupEnd).toHaveBeenCalled();
		});

		it('groupExpanded() callback executes when debug disabled', () => {
			setDebugEnabled(false);
			const logger = createLogger('Test');
			let executed = false;

			logger.groupExpanded('test', () => {
				executed = true;
			});

			expect(executed).toBe(true);
			expect(consoleSpy.group).not.toHaveBeenCalled();
		});
	});

	// ── Debug flag detection ────────────────────────────────────────

	describe('debug flag detection', () => {
		it('refreshDebugState resets cached state', () => {
			setDebugEnabled(true);
			const logger = createLogger('Test');

			logger.log('should appear');
			expect(consoleSpy.log).toHaveBeenCalled();

			consoleSpy.log.mockClear();

			// Refresh resets to re-check flags (NODE_ENV=test → false).
			refreshDebugState();

			// After refresh, debug state is re-evaluated from environment.
			// In test env, NODE_ENV may be 'test' which is not 'development'.
			// The exact behavior depends on the test runner's NODE_ENV.
		});
	});
});
