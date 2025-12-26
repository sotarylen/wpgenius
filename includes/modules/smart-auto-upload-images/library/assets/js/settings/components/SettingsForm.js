/**
 * Settings Form Component
 *
 * @package SmartAutoUploadImages
 */

import { useState, useEffect, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	RangeControl,
	CheckboxControl,
	Panel,
	PanelBody,
	PanelRow,
	__experimentalVStack as VStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalDivider as Divider, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';

import PatternPreview from './PatternPreview';
import PostTypeSelector from './PostTypeSelector';
import DomainExclusions from './DomainExclusions';

/**
 * Settings Form Component
 *
 * @param {object} props - The component props.
 * @param {object} props.settings - The settings object.
 * @param {Function} props.onSettingsChange - The function to call when settings change.
 *
 * @returns {WPElement|null} The settings form component.
 */
const SettingsForm = ({ settings, onSettingsChange }) => {
	const [localSettings, setLocalSettings] = useState(settings);

	// Update local settings when props change
	useEffect(() => {
		setLocalSettings(settings);
	}, [settings]);

	/**
	 * Handle input change
	 *
	 * @param {string} key - The key of the setting that changed.
	 * @param {string} value - The new value of the setting.
	 */
	const handleChange = (key, value) => {
		const newSettings = {
			...localSettings,
			[key]: value,
		};
		setLocalSettings(newSettings);
		onSettingsChange(newSettings);
	};

	if (!localSettings) {
		return null;
	}

	return (
		<VStack spacing={4}>
			<Panel>
				<PanelBody title={__('Basic Settings', 'smart-auto-upload-images')} initialOpen>
					<VStack spacing={4}>
						<PanelRow>
							<CheckboxControl
								label={__('Auto Set Featured Image', 'wp-genius')}
								help={__(
									'Automatically set the first image as featured image if none exists.',
									'wp-genius',
								)}
								checked={localSettings.auto_set_featured_image || false}
								onChange={(value) => handleChange('auto_set_featured_image', value)}
							/>
						</PanelRow>

						<PanelRow>
							<CheckboxControl
								label={__('Show Upload Progress', 'wp-genius')}
								help={__(
									'Display a progress bar when saving posts with external images.',
									'wp-genius',
								)}
								checked={localSettings.show_progress_ui || false}
								onChange={(value) => handleChange('show_progress_ui', value)}
							/>
						</PanelRow>

						<Divider />

						<PanelRow>
							<TextControl
								label={__('Base URL', 'smart-auto-upload-images')}
								help={__(
									'The base URL for uploaded images. Leave empty to use your site URL.',
									'smart-auto-upload-images',
								)}
								value={localSettings.base_url || ''}
								onChange={(value) => handleChange('base_url', value)}
								placeholder={__('https://example.com', 'smart-auto-upload-images')}
							/>
						</PanelRow>

						<Divider />

						<PanelRow>
							<div className="smart-aui-pattern-field">
								<TextControl
									label={__('Image Name Pattern', 'smart-auto-upload-images')}
									help={__(
										'Pattern for naming uploaded images. Use placeholders like %filename%, %post_title%, %year%, etc.',
										'smart-auto-upload-images',
									)}
									value={localSettings.image_name_pattern || ''}
									onChange={(value) => handleChange('image_name_pattern', value)}
									placeholder={__('%filename%', 'smart-auto-upload-images')}
								/>
								<PatternPreview
									pattern={localSettings.image_name_pattern || ''}
									type="filename"
								/>
							</div>
						</PanelRow>

						<PanelRow>
							<div className="smart-aui-pattern-field">
								<TextControl
									label={__('Alt Text Pattern', 'smart-auto-upload-images')}
									help={__(
										'Pattern for image alt text. Use placeholders like %image_alt%, %post_title%, etc.',
										'smart-auto-upload-images',
									)}
									value={localSettings.alt_text_pattern || ''}
									onChange={(value) => handleChange('alt_text_pattern', value)}
									placeholder={__('%image_alt%', 'smart-auto-upload-images')}
								/>
								<PatternPreview
									pattern={localSettings.alt_text_pattern || ''}
									type="alt"
								/>
							</div>
						</PanelRow>
					</VStack>
				</PanelBody>
			</Panel>

			<Panel>
				<PanelBody
					title={__('Image Processing', 'smart-auto-upload-images')}
					initialOpen={false}
				>
					<VStack spacing={4}>
						<PanelRow>
							<RangeControl
								label={__('Maximum Width', 'smart-auto-upload-images')}
								help={__(
									'Maximum width for uploaded images in pixels. Set to 0 to disable.',
									'smart-auto-upload-images',
								)}
								value={localSettings.max_width || 0}
								onChange={(value) => handleChange('max_width', value)}
								min={0}
								max={2000}
								step={50}
							/>
						</PanelRow>

						<PanelRow>
							<RangeControl
								label={__('Maximum Height', 'smart-auto-upload-images')}
								help={__(
									'Maximum height for uploaded images in pixels. Set to 0 to disable.',
									'smart-auto-upload-images',
								)}
								value={localSettings.max_height || 0}
								onChange={(value) => handleChange('max_height', value)}
								min={0}
								max={2000}
								step={50}
							/>
						</PanelRow>
					</VStack>
				</PanelBody>
			</Panel>

			<Panel>
				<PanelBody title={__('Exclusions', 'smart-auto-upload-images')} initialOpen={false}>
					<VStack spacing={4}>
						<PanelRow>
							<PostTypeSelector
								selectedTypes={localSettings.exclude_post_types || []}
								onChange={(value) => handleChange('exclude_post_types', value)}
							/>
						</PanelRow>

						<PanelRow>
							<DomainExclusions
								domains={localSettings.exclude_domains || ''}
								onChange={(value) => handleChange('exclude_domains', value)}
							/>
						</PanelRow>
					</VStack>
				</PanelBody>
			</Panel>
		</VStack>
	);
};

export default SettingsForm;
