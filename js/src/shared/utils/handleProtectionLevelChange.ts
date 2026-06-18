/**
 * Protection Level Change Handler
 *
 * Updates the protection level preset. Difficulty is derived server-side
 * from the preset — no base/max difficulty fields needed.
 */

/**
 * Handle a protection level change.
 */
export function handleProtectionLevelChange(
	levelId: string,
	setters: { setPowProtectionLevel: (level: string) => void }
): void {
	setters.setPowProtectionLevel(levelId);
}
