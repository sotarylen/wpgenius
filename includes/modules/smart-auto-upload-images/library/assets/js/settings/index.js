/**
 * Smart Auto Upload Images Admin Interface
 *
 * @package SmartAutoUploadImages
 */

import { createRoot, render } from '@wordpress/element';
import SettingsApp from './components/SettingsApp';

/**
 * Initialize the admin interface
 */
function initializeAdmin() {
	const rootElement = document.getElementById('smart-aui-admin-root');

	if (!rootElement) {
		return;
	}

	if (typeof createRoot === 'function') {
		const root = createRoot(rootElement);
		root.render(<SettingsApp />);
	} else {
		render(<SettingsApp />, rootElement);
	}
}

document.addEventListener('DOMContentLoaded', initializeAdmin);
