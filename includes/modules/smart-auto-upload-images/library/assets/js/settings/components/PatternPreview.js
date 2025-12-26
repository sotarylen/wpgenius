/**
 * Pattern Preview Component
 *
 * @package SmartAutoUploadImages
 */

import { useState, useEffect, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Pattern Preview Component
 *
 * @param {object} props - The component props.
 * @param {string} props.pattern - The pattern to preview.
 * @param {string} props.type - The type of pattern to preview.
 *
 * @returns {WPElement|null} The pattern preview component.
 */
const PatternPreview = ({ pattern, type = 'filename' }) => {
	const [preview, setPreview] = useState('');
	const [isLoading, setIsLoading] = useState(false);

	/**
	 * Generate preview
	 *
	 * @param {string} patternText - The pattern to preview.
	 */
	const generatePreview = async (patternText) => {
		if (!patternText || patternText.trim() === '') {
			setPreview('');
			return;
		}

		try {
			setIsLoading(true);
			const response = await apiFetch({
				path: '/smart-aui/v1/preview-pattern',
				method: 'POST',
				data: { pattern: patternText },
			});

			setPreview(response.preview);
		} catch (error) {
			setPreview(__('Preview unavailable', 'smart-auto-upload-images'));
		} finally {
			setIsLoading(false);
		}
	};

	// Generate preview when pattern changes
	useEffect(() => {
		const timeoutId = setTimeout(() => {
			generatePreview(pattern);
		}, 500); // Debounce for 500ms

		return () => clearTimeout(timeoutId);
	}, [pattern]);

	return (
		pattern && (
			<Card className="smart-aui-pattern-preview" size="small">
				<CardBody>
					<div className="smart-aui-pattern-preview-content">
						<div className="smart-aui-pattern-preview-label">
							{type === 'filename'
								? __('Filename Preview:', 'smart-auto-upload-images')
								: __('Alt Text Preview:', 'smart-auto-upload-images')}
						</div>
						<div className="smart-aui-pattern-preview-output">
							{isLoading ? (
								<Spinner />
							) : (
								<code>
									{preview ||
										__('No preview available', 'smart-auto-upload-images')}
								</code>
							)}
						</div>
					</div>
				</CardBody>
			</Card>
		)
	);
};

export default PatternPreview;
