/**
 * useHashTab Hook
 *
 * Manages tab state with URL hash deep-linking.
 * Syncs the active tab to the URL hash and listens for browser back/forward.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';

/**
 * @param validIds   - Set of valid tab IDs
 * @param defaultTab - Default tab ID when hash is missing or invalid
 */
export function useHashTab(validIds: Set<string>, defaultTab: string) {
	const getFromHash = useCallback(() => {
		const hash = window.location.hash.replace('#', '');
		return validIds.has(hash) ? hash : defaultTab;
	}, [validIds, defaultTab]);

	const [activeTab, setActiveTab] = useState(getFromHash);

	const handleTabChange = useCallback((tabId: string) => {
		setActiveTab(tabId);
		window.history.replaceState(null, '', `#${tabId}`);
	}, []);

	useEffect(() => {
		const onHashChange = () => setActiveTab(getFromHash());
		window.addEventListener('hashchange', onHashChange);
		return () => window.removeEventListener('hashchange', onHashChange);
	}, [getFromHash]);

	return { activeTab, handleTabChange };
}
