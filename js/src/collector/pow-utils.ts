/**
 * GF Spam Hexer — PoW utility functions
 *
 * Pure functions shared between the Web Worker (pow-worker.ts) and
 * the main-thread chunked fallback solver (pow-manager.ts).
 *
 * Extracted into a separate module so they can be unit-tested with Jest
 * without needing a real Web Worker environment.
 *
 * @module pow-utils
 */

/* eslint-disable no-bitwise */

import { Sha256 } from '@aws-crypto/sha256-js';

const encoder = new TextEncoder();

/**
 * Whether SubtleCrypto is available (requires HTTPS or localhost).
 * Falls back to @aws-crypto/sha256-js on plain HTTP.
 */
export const hasSubtleCrypto =
	typeof crypto !== 'undefined' &&
	typeof crypto.subtle !== 'undefined' &&
	typeof crypto.subtle.digest === 'function';

/**
 * Computes SHA-256 of a string, returning the hash as a Uint8Array.
 *
 * Uses SubtleCrypto when available (HTTPS), falls back to
 * `@aws-crypto/sha256-js` on plain HTTP.
 *
 * @param input - The string to hash.
 * @return The 32-byte SHA-256 hash.
 */
export async function computeSha256(input: string): Promise<Uint8Array> {
	if (hasSubtleCrypto) {
		const buffer = await crypto.subtle.digest(
			'SHA-256',
			encoder.encode(input)
		);
		return new Uint8Array(buffer);
	}

	// Fallback: pure-JS SHA-256 from @aws-crypto/sha256-js.
	const hash = new Sha256();
	hash.update(input);
	return new Uint8Array(await hash.digest());
}

/**
 * Checks if a SHA-256 hash (as Uint8Array) has the required number of
 * leading zero bits.
 *
 * Works byte-by-byte, bit-by-bit from the most significant bit (MSB).
 * Each byte has 8 bits, checked from bit 7 (MSB) down to bit 0 (LSB).
 *
 * Example: difficulty=17 means the first 17 bits must all be 0.
 * That's 2 full zero bytes (16 bits) + the MSB of the 3rd byte must be 0.
 *
 * @param hash     - The SHA-256 hash as a Uint8Array (32 bytes).
 * @param required - Number of leading zero bits required.
 * @return True if the hash has enough leading zeros.
 */
export function hasLeadingZeroBits(
	hash: Uint8Array,
	required: number
): boolean {
	let bitsChecked = 0;

	for (let i = 0; i < hash.length && bitsChecked < required; i++) {
		for (let bit = 7; bit >= 0 && bitsChecked < required; bit--) {
			if (hash[i] & (1 << bit)) {
				return false; // Found a 1-bit before reaching required zeros.
			}
			bitsChecked++;
		}
	}

	return bitsChecked >= required;
}

/**
 * Converts a Uint8Array to a lowercase hex string.
 *
 * @param arr - The byte array to convert.
 * @return Lowercase hex string.
 */
export function arrayToHex(arr: Uint8Array): string {
	return Array.from(arr)
		.map((b) => b.toString(16).padStart(2, '0'))
		.join('');
}
