/**
 * Post Type Selector Component
 *
 * @package SmartAutoUploadImages
 */

import { WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, __experimentalVStack as VStack } from '@wordpress/components'; // eslint-disable-line @wordpress/no-unsafe-wp-apis

/**
 * Post Type Selector Component.
 *
 * @param {object} props - The component props.
 * @param {object} props.selectedTypes - The selected post types.
 * @param {Function} props.onChange - The function to call when the selected post types change.
 *
 * @returns {WPElement|null} The post type selector component.
 */
const PostTypeSelector = ({ selectedTypes, onChange }) => {
	// Get post types from localized data
	const postTypes = window.smartAuiAdmin?.postTypes || [];

	/**
	 * Handle checkbox change.
	 *
	 * @param {string} postType - The post type.
	 * @param {boolean} checked - Whether the checkbox is checked.
	 *
	 * @returns {void}
	 */
	const handleCheckboxChange = (postType, checked) => {
		let newSelectedTypes = [...selectedTypes];

		if (checked) {
			if (!newSelectedTypes.includes(postType)) {
				newSelectedTypes.push(postType);
			}
		} else {
			newSelectedTypes = newSelectedTypes.filter((type) => type !== postType);
		}

		onChange(newSelectedTypes);
	};

	// If no post types available, show message
	if (!postTypes || postTypes.length === 0) {
		return (
			<div className="smart-aui-post-type-selector">
				<h4>{__('Exclude Post Types', 'smart-auto-upload-images')}</h4>
				<p className="description">
					{__('No post types available.', 'smart-auto-upload-images')}
				</p>
			</div>
		);
	}

	return (
		<div className="smart-aui-post-type-selector">
			<h4>{__('Exclude Post Types', 'smart-auto-upload-images')}</h4>
			<p className="description">
				{__(
					'Select post types to exclude from automatic image uploading.',
					'smart-auto-upload-images',
				)}
			</p>

			<VStack spacing={2}>
				{postTypes.map((postType) => (
					<CheckboxControl
						key={postType.value}
						label={postType.label}
						checked={selectedTypes.includes(postType.value)}
						onChange={(checked) => handleCheckboxChange(postType.value, checked)}
					/>
				))}
			</VStack>
		</div>
	);
};

export default PostTypeSelector;
