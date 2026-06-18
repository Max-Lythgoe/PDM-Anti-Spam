/**
 * GF Spam Hexer — PoW Web Worker Solver
 *
 * This file runs inside a Web Worker — it has NO access to the DOM, window,
 * or any main-thread APIs. Its sole job is to brute-force a SHA-256 puzzle:
 *
 * Given a challenge string and a difficulty (number of leading zero bits),
 * find a counter value where SHA-256(challenge + "|" + counter) has at least
 * `difficulty` leading zero bits.
 *
 * The worker prefers SubtleCrypto for hardware-accelerated hashing, but
 * falls back to @aws-crypto/sha256-js when SubtleCrypto is unavailable
 * (plain HTTP, older browsers). See pow-utils.ts for details.
 *
 * Communication protocol:
 *
 * Main thread → Worker:
 *   { challenge: string, difficulty: number, clientNonce?: string }
 *
 * Worker → Main thread (on success):
 *   { counter: number, hash: string }
 *
 * Worker → Main thread (progress, every batch):
 *   { progress: true, counter: number }
 *
 * @module pow-worker
 */

import { computeSha256, hasLeadingZeroBits, arrayToHex } from './pow-utils';

/** Message sent from the main thread to start solving. */
interface SolveMessage {
	challenge: string;
	difficulty: number;
	/** Client-generated nonce for fallback challenges (4-field, no server nonce). */
	clientNonce?: string;
}

/** Message sent back to the main thread when a solution is found. */
interface SolutionMessage {
	counter: number;
	hash: string;
}

/** Progress message sent periodically during solving. */
interface ProgressMessage {
	progress: true;
	counter: number;
}

/**
 * How many hashes to compute before yielding to the event loop.
 *
 * Yielding allows the worker to receive termination messages if the
 * main thread decides to abort (e.g., user navigated away). 50K is
 * a good balance — small enough to be responsive, large enough to
 * avoid excessive overhead from setTimeout(0).
 */
const BATCH_SIZE = 50_000;

/**
 * Listens for a solve message from the main thread and begins brute-forcing.
 */
// eslint-disable-next-line no-restricted-globals
self.onmessage = async (e: MessageEvent<SolveMessage>): Promise<void> => {
	const { challenge, difficulty, clientNonce } = e.data;
	let counter = 0;

	// For fallback challenges, the hash input includes the client nonce:
	//   SHA-256(challenge|clientNonce|counter)
	// For REST challenges, it's just:
	//   SHA-256(challenge|counter)
	const prefix = clientNonce
		? challenge + '|' + clientNonce + '|'
		: challenge + '|';

	// eslint-disable-next-line no-constant-condition
	while (true) {
		const end = counter + BATCH_SIZE;

		while (counter < end) {
			const hashArray = await computeSha256(prefix + counter);

			if (hasLeadingZeroBits(hashArray, difficulty)) {
				const hash = arrayToHex(hashArray);

				// eslint-disable-next-line no-restricted-globals
				(self as unknown as Worker).postMessage({
					counter,
					hash,
				} as SolutionMessage);

				return;
			}

			counter++;
		}

		// Send progress update so the main thread knows we're alive.
		// eslint-disable-next-line no-restricted-globals
		(self as unknown as Worker).postMessage({
			progress: true,
			counter,
		} as ProgressMessage);

		// Yield to the event loop so the worker can receive messages
		// (e.g., termination). Without this, the while(true) loop would
		// block the worker's message queue indefinitely.
		await yieldToEventLoop();
	}
};

/**
 * Yields to the event loop via setTimeout(0).
 */
function yieldToEventLoop(): Promise<void> {
	return new Promise((resolve) => setTimeout(resolve, 0));
}
