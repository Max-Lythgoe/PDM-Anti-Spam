/**
 * GF Spam Hexer — Comment PoW Collector
 *
 * Lightweight entry point for WordPress comment forms. Reads the
 * challenge config from `window.gfshCommentConfig` (injected by
 * Comment_Frontend::inject_pow_fields()) and solves the PoW puzzle
 * in the background using the shared PoWManager.
 *
 * Solving starts lazily when the user first interacts with the comment
 * form (typing in name, email, or comment fields), so the CPU work
 * only happens when someone is actually writing a comment.
 *
 * On comment form submit, writes the solution to the hidden
 * `gfsh_payload` field so the server can verify it.
 *
 * @module comment
 */

import { PoWManager } from '../collector/pow-manager';
import type { PoWChallenge, PoWSolution } from '../collector/pow-manager';
import { assemblePayload } from '../collector/payload';
import { solveAndWrite } from '../collector/init';

/** Shape of the config injected by Comment_Frontend::inject_pow_fields(). */
interface CommentConfig {
	formId: number;
	payloadFieldId: string;
	challenge: string;
	challengeSig: string;
	difficulty: number;
	challengeEndpoint: string;
	nonce: string;
	isFallback: boolean;
}

declare global {
	interface Window {
		gfshCommentConfig?: CommentConfig;
	}
}

/**
 * Writes the PoW solution to the hidden payload field.
 */
function writePayload(fieldId: string, solution: PoWSolution): void {
	const field = document.getElementById(fieldId) as HTMLInputElement | null;
	if (!field) {
		return;
	}

	const payload = assemblePayload(solution);
	field.value = btoa(JSON.stringify(payload));
}

/**
 * Submits a form, working around the common WordPress issue where
 * the submit button has name="submit" which shadows form.submit().
 */
function submitForm(form: HTMLFormElement): void {
	HTMLFormElement.prototype.submit.call(form);
}

/**
 * Shows a brief "Verifying..." indicator near the submit button.
 * Returns a cleanup function to remove it.
 */
function showSolvingIndicator(form: HTMLFormElement): () => void {
	const submitBtn = form.querySelector<HTMLElement>(
		'input[type="submit"], button[type="submit"]'
	);

	if (!submitBtn) {
		return () => {};
	}

	// Disable the button to prevent double-clicks.
	if (
		submitBtn instanceof HTMLInputElement ||
		submitBtn instanceof HTMLButtonElement
	) {
		submitBtn.disabled = true;
	}

	const indicator = document.createElement('span');
	indicator.textContent = ' Verifying…';
	indicator.style.cssText =
		'color: #666; font-style: italic; margin-left: 8px;';
	indicator.className = 'gfsh-solving-indicator';
	submitBtn.parentNode?.insertBefore(indicator, submitBtn.nextSibling);

	return () => {
		indicator.remove();
		if (
			submitBtn instanceof HTMLInputElement ||
			submitBtn instanceof HTMLButtonElement
		) {
			submitBtn.disabled = false;
		}
	};
}

/**
 * Builds the PoW manager config from the comment config.
 */
function buildFallbackChallenge(config: CommentConfig): PoWChallenge {
	return {
		challenge: config.challenge,
		signature: config.challengeSig,
		difficulty: config.difficulty,
		expires: Math.floor(Date.now() / 1000) + 300, // 5 min from now.
	};
}

/**
 * Initializes the comment PoW collector.
 */
function init(): void {
	const config = window.gfshCommentConfig;
	if (!config) {
		return; // No config = feature not enabled or not a comment page.
	}

	// Determine the worker URL — same worker as the GF collector.
	// The worker script is loaded from the same directory as this script.
	const currentScript = document.currentScript as HTMLScriptElement | null;
	const workerUrl = currentScript
		? currentScript.src.replace(/[^/]+$/, 'gf-spam-hexer-pow-worker.js')
		: '';

	// Build the fallback challenge from the inline config.
	const fallbackChallenge = buildFallbackChallenge(config);

	let manager = new PoWManager({
		formId: config.formId,
		workerUrl,
		fallbackChallenge,
		fetchTimeout: 5000,
	});

	let solveStarted = false;
	let isSubmitting = false;

	/**
	 * Starts solving the PoW challenge. Called once on first user interaction
	 * (or on submit as a safety net, or after bfcache restore).
	 */
	function startSolving(): void {
		if (solveStarted) {
			return;
		}
		solveStarted = true;

		// eslint-disable-next-line @typescript-eslint/no-non-null-assertion
		const payloadFieldId = config!.payloadFieldId;
		solveAndWrite(manager, (solution) => {
			writePayload(payloadFieldId, solution);
		});
	}

	/**
	 * Re-initializes the PoW manager after a bfcache restore.
	 *
	 * When the browser restores a page from the back-forward cache, the
	 * Web Worker is dead and the manager's internal state is stale.
	 * We destroy the old manager, create a fresh one, and restart solving
	 * immediately so the solution is ready before the user submits.
	 */
	function reinitAfterBfcache(): void {
		manager.destroy();
		manager = new PoWManager({
			formId: config!.formId,
			workerUrl,
			fallbackChallenge: buildFallbackChallenge(config!),
			fetchTimeout: 5000,
		});
		solveStarted = false;
		isSubmitting = false;
		// Start solving immediately — user is already on the page.
		startSolving();
	}

	// Find the comment form.
	const commentForm =
		(document.getElementById('commentform') as HTMLFormElement | null) ??
		document.querySelector<HTMLFormElement>(
			'form.comment-form, form[action*="wp-comments-post"]'
		);

	if (!commentForm) {
		return;
	}

	// Start solving when the user first interacts with comment fields.
	// This defers CPU work until someone is actually writing a comment.
	const triggerFields = commentForm.querySelectorAll<HTMLElement>(
		'input[name="author"], input[name="email"], textarea[name="comment"], #author, #email, #comment'
	);

	const startOnInteraction = (): void => {
		startSolving();
		// Remove listeners after first trigger.
		triggerFields.forEach((field) => {
			field.removeEventListener('focus', startOnInteraction);
			field.removeEventListener('input', startOnInteraction);
		});
	};

	triggerFields.forEach((field) => {
		field.addEventListener('focus', startOnInteraction);
		field.addEventListener('input', startOnInteraction);
	});

	// Handle bfcache restore (browser back button).
	//
	// When the browser restores a page from the back-forward cache, the
	// pageshow event fires with e.persisted === true. At this point the
	// Web Worker is terminated and the PoWManager is in a broken state.
	// We re-initialize so the solution is ready for the next submission.
	window.addEventListener('pageshow', (e: PageTransitionEvent) => {
		if (e.persisted) {
			reinitAfterBfcache();
		}
	});

	// Hook into form submit to ensure the payload is written.
	commentForm.addEventListener('submit', async (e) => {
		// Prevent double-submission.
		if (isSubmitting) {
			e.preventDefault();
			return;
		}

		// Ensure solving has started (in case user submits without
		// interacting with monitored fields, e.g. keyboard shortcut).
		startSolving();

		const solution = manager.getSolution();

		if (solution) {
			// Solution ready — write it and let the form submit normally.
			writePayload(config.payloadFieldId, solution);
			return;
		}

		// Solution not ready yet — wait for it with a visual indicator.
		// This covers two cases:
		//   1. Still solving (user submitted very quickly).
		//   2. Not solving and no solution (bfcache restore race, init failure).
		//      In case 2, startSolving() above will have kicked off solving,
		//      so waitForSolution() will resolve once it completes.
		e.preventDefault();
		isSubmitting = true;

		const cleanup = showSolvingIndicator(commentForm);

		const awaited = await manager.waitForSolution(5000);
		cleanup();

		if (awaited) {
			writePayload(config.payloadFieldId, awaited);
		}

		isSubmitting = false;

		// Re-submit the form using the prototype method to avoid
		// the shadowed submit button issue.
		submitForm(commentForm);
	});
}

// Run on DOMContentLoaded or immediately if already loaded.
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
