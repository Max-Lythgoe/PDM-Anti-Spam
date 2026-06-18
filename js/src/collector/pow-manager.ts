/**
 * GF Spam Hexer — PoW Manager (Client-Side Orchestrator)
 *
 * Manages the full lifecycle of a Proof-of-Work challenge for a single form:
 *
 * 1. **Fetch** a fresh challenge from the REST endpoint (adaptive difficulty).
 * 2. **Fallback** to the HTML-embedded challenge if the fetch fails.
 * 3. **Solve** the puzzle in a Web Worker (or main-thread chunked fallback).
 * 4. **Store** the solution so the collector can include it in the form payload.
 *
 * This runs on the main thread but delegates the expensive hashing to a
 * Web Worker (`pow-worker.ts`). The form UI stays responsive throughout.
 *
 * Uses `@wordpress/api-fetch` for the REST request, which auto-handles
 * nonce injection via the DependencyExtractionWebpackPlugin. This means
 * the nonce is set globally by WordPress and we don't need to pass it
 * through the config.
 *
 * Usage (from collector/index.ts):
 * ```ts
 * const manager = new PoWManager(formConfig);
 * await manager.init();
 *
 * // On form submit:
 * const solution = manager.getSolution();
 * ```
 *
 * @module pow-manager
 */

import apiFetch from '@wordpress/api-fetch';
import { computeSha256, hasLeadingZeroBits } from './pow-utils';
import { createLogger } from '@shared/logger';

const logger = createLogger('PoWManager');

/** Shape of a PoW challenge from the server. */
export interface PoWChallenge {
	challenge: string;
	signature: string;
	difficulty: number;
	expires: number;
}

/** Shape of a solved PoW solution, ready for the form payload. */
export interface PoWSolution {
	challenge: string;
	signature: string;
	solution: string;
	solve_time_ms: number;
	is_fallback: boolean;
	/** Client-generated nonce for fallback challenges (unique per visitor). */
	client_nonce?: string;
}

/** Shape of the debug status returned by getDebugStatus(). */
export interface PoWDebugStatus {
	hasSolution: boolean;
	isSolving: boolean;
	isExpired: boolean;
	isExpiring: boolean;
	challengeExpires: number;
	isFallback: boolean;
	hashesChecked: number;
	elapsedMs: number;
	difficulty: number;
	solution: PoWSolution | null;
}

/** Configuration for a single form's PoW manager. */
export interface PoWFormConfig {
	formId: number;
	workerUrl: string;
	fallbackChallenge: PoWChallenge | null;
	fetchTimeout: number;
}

/**
 * How many hashes to compute per chunk in the main-thread fallback solver.
 * Kept small to avoid blocking the UI for more than ~5-10ms per chunk.
 */
const CHUNK_SIZE = 5_000;

/**
 * Manages the PoW challenge lifecycle for a single Gravity Forms form.
 *
 * Instantiated by the collector when a form with PoW enabled is detected.
 * Immediately begins fetching and solving so the solution is ready before
 * the user finishes filling out the form.
 */
export class PoWManager {
	private worker: Worker | null = null;
	private solution: PoWSolution | null = null;
	private solving = false;
	private cancelled = false;
	private isFallback = false;
	private clientNonce: string | undefined;
	private config: PoWFormConfig;
	private pendingResolvers: Array<(solution: PoWSolution | null) => void> =
		[];

	/**
	 * Unix timestamp (seconds) when the current challenge expires.
	 * Stored when the challenge is solved so we can check freshness at submit time.
	 */
	private challengeExpires = 0;

	/**
	 * Number of hashes checked so far (updated via worker progress messages).
	 * Readable by the debug API for granular solving status.
	 */
	hashesChecked = 0;

	/**
	 * Timestamp (ms) when solving started. Used to compute elapsed time.
	 * Readable by the debug API for granular solving status.
	 */
	solveStartedAt = 0;

	/**
	 * The difficulty of the challenge being solved (or that was solved).
	 * Readable by the debug API.
	 */
	solveDifficulty = 0;

	constructor(config: PoWFormConfig) {
		this.config = config;
	}

	/**
	 * Fetches a challenge and begins solving immediately.
	 *
	 * Called on form render. The solving happens in the background while
	 * the user fills out the form. By the time they click submit, the
	 * solution is almost always ready.
	 *
	 * If the REST fetch fails (ad blocker, network issue, CORS), falls
	 * back to the HTML-embedded challenge at base difficulty.
	 */
	async init(): Promise<void> {
		logger.log(
			'init() — formId:',
			this.config.formId,
			'workerUrl:',
			this.config.workerUrl
		);
		logger.log('fallbackChallenge:', this.config.fallbackChallenge);

		try {
			logger.log('Fetching fresh challenge from REST…');
			const challenge = await this.fetchChallenge();
			this.isFallback = false;
			logger.log('Challenge fetched (REST):', challenge);
			this.solve(challenge);
		} catch (err) {
			logger.warn('REST fetch failed, using fallback challenge:', err);
			// Fallback: use the embedded challenge from page HTML.
			const fallback = this.config.fallbackChallenge;

			if (fallback) {
				this.isFallback = true;

				// Generate a unique client nonce so each visitor gets their own
				// replay key — prevents false pow_replay rejections on cached pages.
				this.clientNonce =
					crypto.randomUUID?.() ??
					Array.from(crypto.getRandomValues(new Uint8Array(16)))
						.map((b) => ('0' + b.toString(16)).slice(-2))
						.join('');

				logger.log(
					'Solving fallback challenge with clientNonce:',
					this.clientNonce,
					fallback
				);
				this.solve(fallback);
			} else {
				logger.warn(
					'No fallback challenge available — PoW will be skipped.'
				);
			}
		}
	}

	/**
	 * Returns the solved PoW solution, or null if still solving.
	 *
	 * The collector calls this on form submit to include the solution
	 * in the `gfsh_payload` hidden field.
	 */
	getSolution(): PoWSolution | null {
		return this.solution;
	}

	/**
	 * Whether the puzzle is currently being solved.
	 */
	isSolving(): boolean {
		return this.solving;
	}

	/**
	 * Returns a promise that resolves when the solution is ready.
	 *
	 * Used by the collector to delay form submission if the puzzle
	 * hasn't been solved yet (e.g., user submitted very quickly).
	 * Times out after the specified ms to avoid blocking forever.
	 *
	 * @param timeoutMs - Maximum time to wait in milliseconds.
	 * @return The solution, or null if timed out.
	 */
	waitForSolution(timeoutMs = 10_000): Promise<PoWSolution | null> {
		if (this.solution) {
			return Promise.resolve(this.solution);
		}

		return new Promise((resolve) => {
			const onSolved = (sol: PoWSolution | null) => {
				clearTimeout(timeout);
				resolve(sol);
			};

			const timeout = setTimeout(() => {
				// Remove this resolver from the pending list on timeout.
				const idx = this.pendingResolvers.indexOf(onSolved);
				if (idx !== -1) {
					this.pendingResolvers.splice(idx, 1);
				}
				resolve(null);
			}, timeoutMs);

			this.pendingResolvers.push(onSolved);
		});
	}

	/**
	 * Cleans up the Web Worker if it's still running.
	 *
	 * Called when the form is removed from the DOM or the page unloads.
	 */
	destroy(): void {
		this.cancelled = true;

		if (this.worker) {
			this.worker.terminate();
			this.worker = null;
		}

		this.solving = false;

		// Resolve any pending waiters with null to signal cancellation.
		const resolvers = this.pendingResolvers.splice(0);
		for (const resolve of resolvers) {
			resolve(null);
		}
	}

	/**
	 * Whether the current challenge has expired or will expire within the buffer period.
	 *
	 * Used by the submission hook to decide whether to fetch a fresh challenge
	 * before allowing the form to submit. The buffer accounts for the time
	 * needed to fetch + solve a new challenge (~1-3 seconds).
	 *
	 * @param bufferSeconds - How many seconds before actual expiry to consider it "expired".
	 *                      Default 30 seconds to account for network + solve time.
	 */
	isExpiredOrExpiring(bufferSeconds = 30): boolean {
		if (!this.challengeExpires) {
			return false;
		}
		const now = Date.now() / 1000;
		return this.challengeExpires - now < bufferSeconds;
	}

	/**
	 * Returns a snapshot of the manager's internal state for debugging.
	 *
	 * Used by the debug API (window.gfshDebug.getStatus) and the dev tools
	 * live status panel. Provides a proper public interface instead of
	 * requiring (manager as any) casts to access private fields.
	 */
	getDebugStatus(): PoWDebugStatus {
		return {
			hasSolution: !!this.solution,
			isSolving: this.solving,
			isExpired: this.isExpiredOrExpiring(0),
			isExpiring: this.isExpiredOrExpiring(60),
			challengeExpires: this.challengeExpires,
			isFallback: this.isFallback,
			hashesChecked: this.hashesChecked,
			elapsedMs: this.solving
				? Math.round(performance.now() - this.solveStartedAt)
				: (this.solution?.solve_time_ms ?? 0),
			difficulty: this.solveDifficulty,
			solution: this.solution,
		};
	}

	/**
	 * Forces the challenge to appear expired. Used by the debug API.
	 */
	forceExpire(): void {
		this.challengeExpires = 1;
	}

	// ── Private methods ─────────────────────────────────────────────

	/**
	 * Fetches a fresh challenge from the REST endpoint.
	 *
	 * Uses @wordpress/api-fetch which auto-injects the WP REST nonce.
	 * The DependencyExtractionWebpackPlugin externalizes this so it
	 * uses the global wp.apiFetch at runtime.
	 *
	 * Respects `config.fetchTimeout` — an AbortController cancels the
	 * request if it takes longer than the configured timeout. This is
	 * used by the debug API's `forceFallback()` (fetchTimeout=1) to
	 * guarantee the REST fetch fails so the fallback challenge is used.
	 */
	private async fetchChallenge(): Promise<PoWChallenge> {
		const controller = new AbortController();
		// The timeout must start before the fetch; clearTimeout is in `finally`.
		// eslint-disable-next-line @wordpress/no-unused-vars-before-return
		const timeoutId = setTimeout(
			() => controller.abort(),
			this.config.fetchTimeout
		);
		try {
			return await apiFetch<PoWChallenge>({
				path: '/gfsh/v1/challenge',
				method: 'POST',
				data: {
					form_id: this.config.formId,
				},
				signal: controller.signal,
			});
		} finally {
			clearTimeout(timeoutId);
		}
	}

	/**
	 * Begins solving the challenge.
	 *
	 * Prefers Web Worker (off main thread). Falls back to main-thread
	 * chunked solving if Workers are unavailable.
	 */
	private solve(challenge: PoWChallenge): void {
		if (this.solving) {
			logger.warn('solve() called while already solving — ignoring.');
			return;
		}

		this.solving = true;
		this.hashesChecked = 0;
		this.solveStartedAt = performance.now();
		this.solveDifficulty = challenge.difficulty;
		logger.log(
			'Starting solve — difficulty:',
			challenge.difficulty,
			'| Worker available:',
			typeof Worker !== 'undefined'
		);

		if (typeof Worker !== 'undefined') {
			this.solveInWorker(challenge);
		} else {
			logger.log(
				'Web Worker unavailable — using main-thread chunked solver.'
			);
			this.solveChunked(challenge);
		}
	}

	/**
	 * Solves the puzzle in a Web Worker — zero main-thread blocking.
	 *
	 * The worker URL is provided by PHP via wp_localize_script.
	 * On solution, stores the result and terminates the worker.
	 */
	private solveInWorker(challenge: PoWChallenge): void {
		logger.log('Spawning Web Worker from URL:', this.config.workerUrl);

		try {
			this.worker = new Worker(this.config.workerUrl);
		} catch (err) {
			// Worker creation can fail (CSP, file:// protocol, etc.)
			logger.warn(
				'Worker creation failed, falling back to chunked solver:',
				err
			);
			this.solveChunked(challenge);
			return;
		}

		const startTime = performance.now();

		this.worker.onmessage = (e: MessageEvent) => {
			// Track progress for the debug API.
			if (e.data.progress) {
				this.hashesChecked = e.data.counter;
				logger.log('Worker progress — hashes tried:', e.data.counter);
				return;
			}

			this.hashesChecked = e.data.counter;

			const solveTime = performance.now() - startTime;

			this.solution = {
				challenge: challenge.challenge,
				signature: challenge.signature,
				solution: String(e.data.counter),
				solve_time_ms: Math.round(solveTime),
				is_fallback: this.isFallback,
				client_nonce: this.isFallback ? this.clientNonce : undefined,
			};
			this.challengeExpires = challenge.expires;

			logger.log('Worker solved! solution:', this.solution);

			this.solving = false;
			this.notifyResolvers();
			this.worker?.terminate();
			this.worker = null;
		};

		this.worker.onerror = (err) => {
			// Worker crashed — fall back to main-thread solving.
			logger.warn('Worker error — falling back to chunked solver:', err);
			this.worker?.terminate();
			this.worker = null;
			this.solveChunked(challenge);
		};

		logger.log('Posting challenge to worker:', {
			challenge: challenge.challenge,
			difficulty: challenge.difficulty,
		});
		this.worker.postMessage({
			challenge: challenge.challenge,
			difficulty: challenge.difficulty,
			clientNonce: this.isFallback ? this.clientNonce : undefined,
		});
	}

	/**
	 * Fallback: solves the puzzle on the main thread using chunked execution.
	 *
	 * Processes CHUNK_SIZE hashes per frame, then yields to the browser
	 * via requestIdleCallback (or setTimeout) to keep the UI responsive.
	 * Slower than the Web Worker path but works everywhere.
	 */
	private solveChunked(challenge: PoWChallenge): void {
		logger.log(
			'solveChunked() started — difficulty:',
			challenge.difficulty
		);
		let counter = 0;
		const startTime = performance.now();

		const processChunk = async (): Promise<void> => {
			// Check cancellation before each chunk.
			if (this.cancelled) {
				logger.log('solveChunked() cancelled at counter:', counter);
				return;
			}

			const end = counter + CHUNK_SIZE;

			while (counter < end) {
				if (this.cancelled) {
					logger.log(
						'solveChunked() cancelled mid-chunk at counter:',
						counter
					);
					return;
				}

				const prefix = this.clientNonce
					? challenge.challenge + '|' + this.clientNonce + '|'
					: challenge.challenge + '|';
				const hashArray = await computeSha256(prefix + counter);

				if (hasLeadingZeroBits(hashArray, challenge.difficulty)) {
					const solveTime = performance.now() - startTime;

					this.solution = {
						challenge: challenge.challenge,
						signature: challenge.signature,
						solution: String(counter),
						solve_time_ms: Math.round(solveTime),
						is_fallback: this.isFallback,
						client_nonce: this.isFallback
							? this.clientNonce
							: undefined,
					};
					this.challengeExpires = challenge.expires;

					logger.log('Chunked solver found solution:', this.solution);

					this.solving = false;
					this.notifyResolvers();
					return;
				}

				counter++;
			}

			logger.log('Chunked solver — chunk done, counter:', counter);

			// Schedule next chunk — yield to the browser between chunks.
			if ('requestIdleCallback' in globalThis) {
				(globalThis as any).requestIdleCallback(processChunk, {
					timeout: 100,
				});
			} else {
				setTimeout(processChunk, 0);
			}
		};

		processChunk();
	}

	/**
	 * Notifies all pending waitForSolution() callers that the solution is ready.
	 */
	private notifyResolvers(): void {
		if (!this.solution) {
			return;
		}
		const resolvers = this.pendingResolvers.splice(0);
		for (const resolve of resolvers) {
			resolve(this.solution);
		}
	}
}
