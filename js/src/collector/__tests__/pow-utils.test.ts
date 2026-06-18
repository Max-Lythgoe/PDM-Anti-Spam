/**
 * Tests for PoW utility functions.
 *
 * These are the core functions used by both the Web Worker solver
 * and the main-thread chunked fallback. Testing them here ensures
 * the SHA-256 hashing and leading-zero-bit checking work correctly
 * without needing a real Web Worker environment.
 */

import { computeSha256, hasLeadingZeroBits, arrayToHex } from '../pow-utils';

// ── computeSha256 ───────────────────────────────────────────────────

describe('computeSha256', () => {
	it('should produce a 32-byte Uint8Array', async () => {
		const result = await computeSha256('hello');
		expect(result).toBeInstanceOf(Uint8Array);
		expect(result.length).toBe(32);
	});

	it('should produce the correct SHA-256 for a known input', async () => {
		// SHA-256("hello") = 2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824
		const result = await computeSha256('hello');
		const hex = arrayToHex(result);
		expect(hex).toBe(
			'2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824'
		);
	});

	it('should produce different hashes for different inputs', async () => {
		const hash1 = arrayToHex(await computeSha256('input1'));
		const hash2 = arrayToHex(await computeSha256('input2'));
		expect(hash1).not.toBe(hash2);
	});

	it('should produce consistent results for the same input', async () => {
		const hash1 = arrayToHex(await computeSha256('consistent'));
		const hash2 = arrayToHex(await computeSha256('consistent'));
		expect(hash1).toBe(hash2);
	});

	it('should handle empty string', async () => {
		// SHA-256("") = e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
		const result = await computeSha256('');
		const hex = arrayToHex(result);
		expect(hex).toBe(
			'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
		);
	});

	it('should handle challenge-like strings', async () => {
		// Simulate what the worker actually hashes: "challenge|counter"
		const result = await computeSha256(
			'42|1711979400|1711980000|17|abc123|0'
		);
		expect(result).toBeInstanceOf(Uint8Array);
		expect(result.length).toBe(32);
	});
});

// ── hasLeadingZeroBits ──────────────────────────────────────────────

describe('hasLeadingZeroBits', () => {
	it('should return true for 0 required bits (any hash passes)', () => {
		const hash = new Uint8Array([0xff, 0xff, 0xff]);
		expect(hasLeadingZeroBits(hash, 0)).toBe(true);
	});

	it('should return true when first byte is 0x00 and 8 bits required', () => {
		const hash = new Uint8Array([0x00, 0xff, 0xff]);
		expect(hasLeadingZeroBits(hash, 8)).toBe(true);
	});

	it('should return false when first byte is 0x01 and 8 bits required', () => {
		const hash = new Uint8Array([0x01, 0xff, 0xff]);
		expect(hasLeadingZeroBits(hash, 8)).toBe(false);
	});

	it('should check partial bytes correctly (5 bits)', () => {
		// 0x04 = 00000100 — has 5 leading zeros
		const hash = new Uint8Array([0x04, 0xff]);
		expect(hasLeadingZeroBits(hash, 5)).toBe(true);
		expect(hasLeadingZeroBits(hash, 6)).toBe(false);
	});

	it('should handle 17 bits (typical base difficulty)', () => {
		// 17 bits = 2 full zero bytes + 1 zero bit in 3rd byte
		// 0x00, 0x00, 0x7f = 00000000 00000000 01111111 — 17 leading zeros
		const hash17 = new Uint8Array(32);
		hash17[2] = 0x7f; // bit 6 set = 17 leading zeros
		expect(hasLeadingZeroBits(hash17, 17)).toBe(true);
		expect(hasLeadingZeroBits(hash17, 18)).toBe(false);

		// 0x00, 0x00, 0x80 = 00000000 00000000 10000000 — 16 leading zeros
		const hash16 = new Uint8Array(32);
		hash16[2] = 0x80;
		expect(hasLeadingZeroBits(hash16, 17)).toBe(false);
		expect(hasLeadingZeroBits(hash16, 16)).toBe(true);
	});

	it('should handle all zeros (max leading zeros)', () => {
		const hash = new Uint8Array(32); // all zeros
		expect(hasLeadingZeroBits(hash, 256)).toBe(true);
	});

	it('should handle 1 required bit', () => {
		// 0x7f = 01111111 — 1 leading zero
		const hash = new Uint8Array([0x7f]);
		expect(hasLeadingZeroBits(hash, 1)).toBe(true);

		// 0x80 = 10000000 — 0 leading zeros
		const hash2 = new Uint8Array([0x80]);
		expect(hasLeadingZeroBits(hash2, 1)).toBe(false);
	});
});

// ── arrayToHex ──────────────────────────────────────────────────────

describe('arrayToHex', () => {
	it('should convert bytes to lowercase hex', () => {
		const arr = new Uint8Array([0x00, 0x0f, 0xff, 0xab]);
		expect(arrayToHex(arr)).toBe('000fffab');
	});

	it('should handle empty array', () => {
		expect(arrayToHex(new Uint8Array([]))).toBe('');
	});

	it('should pad single-digit hex values with leading zero', () => {
		const arr = new Uint8Array([0x01, 0x02, 0x0a]);
		expect(arrayToHex(arr)).toBe('01020a');
	});

	it('should produce 64-char string for 32-byte hash', () => {
		const arr = new Uint8Array(32);
		expect(arrayToHex(arr).length).toBe(64);
	});
});

// ── Integration: solve a PoW puzzle ─────────────────────────────────

describe('PoW puzzle solving (integration)', () => {
	it('should find a valid solution for difficulty 1', async () => {
		const challenge = 'test|1711979400|1711980000|1|abc123';

		for (let counter = 0; counter < 100; counter++) {
			const hash = await computeSha256(challenge + '|' + counter);

			if (hasLeadingZeroBits(hash, 1)) {
				// Found a solution — verify it's actually valid.
				const hex = arrayToHex(hash);
				// First hex char should be 0-7 (MSB is 0).
				const firstNibble = parseInt(hex[0], 16);
				expect(firstNibble).toBeLessThan(8);
				return;
			}
		}

		// With difficulty 1, ~50% of hashes pass, so 100 tries is plenty.
		throw new Error('Failed to find solution in 100 iterations');
	});

	it('should find a valid solution for difficulty 4', async () => {
		const challenge = 'test|1711979400|1711980000|4|def456';

		for (let counter = 0; counter < 1000; counter++) {
			const hash = await computeSha256(challenge + '|' + counter);

			if (hasLeadingZeroBits(hash, 4)) {
				const hex = arrayToHex(hash);
				// First hex char should be 0 (all 4 MSBs are 0).
				expect(hex[0]).toBe('0');
				return;
			}
		}

		// With difficulty 4, ~6.25% of hashes pass, so 1000 tries is plenty.
		throw new Error('Failed to find solution in 1000 iterations');
	});
});
