/**
 * Featured Image Panel.
 *
 * @package SmartAutoUploadImages
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginPostStatusInfo } from '@wordpress/edit-post';
import PostFeaturedImagePanelWithCondition from './components/PostFeaturedImagePanel';

/**
 * Register the plugin in the WordPress editor
 */
registerPlugin('smart-auto-upload-images-editor', {
	render: () => (
		<PluginPostStatusInfo className="smart-aui-editor-panel">
			<PostFeaturedImagePanelWithCondition />
		</PluginPostStatusInfo>
	),
});
