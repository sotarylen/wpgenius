/**
 * Post Featured Image Panel Component
 *
 * @package SmartAutoUploadImages
 */

import { useState, useEffect, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	TextControl,
	Button,
	Notice,
	__experimentalVStack as VStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { ifCondition } from '@wordpress/compose';

/**
 * Post Featured Image Panel Component
 *
 * @returns {WPElement} The post featured image panel component.
 */
const PostFeaturedImagePanel = () => {
	const [imageUrl, setImageUrl] = useState('');
	const [notice, setNotice] = useState(null);
	const [enableUrlInput, setEnableUrlInput] = useState(false);

	const { featuredImageId, featuredImageUrl } = useSelect((select) => {
		const { getCurrentPostId } = select(editorStore);
		const { getEditedPostAttribute } = select(editorStore);

		return {
			postId: getCurrentPostId(),
			featuredImageId: getEditedPostAttribute('featured_media'),
		};
	}, []);

	const { editPost } = useDispatch(editorStore);

	/**
	 * Initialize imageUrl and checkbox state from post meta
	 */
	useEffect(() => {
		if (featuredImageUrl && !imageUrl) {
			setImageUrl(featuredImageUrl);
			setEnableUrlInput(true);
		}
	}, [featuredImageUrl, imageUrl]);

	/**
	 * Validate URL when user enters it.
	 *
	 * @param {string} url - The URL to validate.
	 * @returns {void}
	 */
	const validateUrl = (url) => {
		if (!url.trim()) {
			setNotice(null);
			return;
		}

		try {
			new URL(url); // eslint-disable-line no-new
			setNotice({
				type: 'success',
				message: __(
					'✓ Valid URL - will be processed when post is saved.',
					'smart-auto-upload-images',
				),
			});
		} catch {
			setNotice({
				type: 'error',
				message: __('Please enter a valid URL.', 'smart-auto-upload-images'),
			});
		}
	};

	/**
	 * Handle URL input change
	 *
	 * @param {string} value - The new URL value.
	 */
	const handleUrlChange = (value) => {
		setImageUrl(value);

		// Save the URL to post meta
		editPost({
			smart_aui_featured_image_url: value,
		});

		// Validate the URL and show feedback
		validateUrl(value);
	};

	/**
	 * Handle checkbox change
	 *
	 * @param {boolean} checked - Whether the checkbox is checked.
	 */
	const handleCheckboxChange = (checked) => {
		setEnableUrlInput(checked);

		if (!checked) {
			// Clear URL when unchecking
			setImageUrl('');
			setNotice(null);
			editPost({
				smart_aui_featured_image_url: '',
			});
		}
	};

	/**
	 * Handle clearing the URL field
	 */
	const handleClear = () => {
		setImageUrl('');
		setNotice(null);

		// Clear the URL from the custom field
		editPost({
			smart_aui_featured_image_url: '',
		});
	};

	return (
		<VStack spacing={3}>
			<CheckboxControl
				label={__('Set Featured Image from URL', 'smart-auto-upload-images')}
				help={__(
					'Enable this option to set a featured image by providing a URL.',
					'smart-auto-upload-images',
				)}
				checked={enableUrlInput}
				onChange={handleCheckboxChange}
			/>

			{enableUrlInput && (
				<>
					{notice && (
						<Notice status={notice.type} isDismissible onRemove={() => setNotice(null)}>
							{notice.message}
						</Notice>
					)}

					<TextControl
						label={__('Image URL', 'smart-auto-upload-images')}
						help={__(
							'Enter the URL of an image. The image will be downloaded and set as the featured image when you save the post.',
							'smart-auto-upload-images',
						)}
						value={imageUrl}
						onChange={handleUrlChange}
						placeholder={__(
							'https://example.com/image.jpg',
							'smart-auto-upload-images',
						)}
					/>

					{imageUrl && (
						<div className="smart-aui-featured-image-actions">
							<Button variant="secondary" onClick={handleClear}>
								{__('Clear', 'smart-auto-upload-images')}
							</Button>
						</div>
					)}

					{imageUrl && !notice && (
						<p className="description">
							{__(
								'💡 The image will be automatically downloaded and set as the featured image when you save this post.',
								'smart-auto-upload-images',
							)}
						</p>
					)}

					{featuredImageId !== 0 && (
						<p className="description">
							{__('Current featured image ID: ', 'smart-auto-upload-images')}
							<strong>{featuredImageId}</strong>
						</p>
					)}
				</>
			)}
		</VStack>
	);
};

const PostFeaturedImagePanelWithCondition = ifCondition(() => {
	const supportsFeaturedImage = useSelect((select) => {
		const postType = select('core/editor').getCurrentPostType();
		return select('core').getPostType(postType)?.supports?.thumbnail || false;
	}, []);

	return supportsFeaturedImage;
})(PostFeaturedImagePanel);

export default PostFeaturedImagePanelWithCondition;
