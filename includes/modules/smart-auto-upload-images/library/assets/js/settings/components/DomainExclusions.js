/**
 * Domain Exclusions Component
 *
 * @package SmartAutoUploadImages
 */

import { useState, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	TextareaControl,
	Button,
	__experimentalVStack as VStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';

/**
 * Domain Exclusions Component.
 *
 * @param {object} props - The component props.
 * @param {string} props.domains - The domains to exclude.
 * @param {Function} props.onChange - The function to call when the domains change.
 *
 * @returns {WPElement} The domain exclusions component.
 */
const DomainExclusions = ({ domains, onChange }) => {
	const [localDomains, setLocalDomains] = useState(domains);
	const [showHelp, setShowHelp] = useState(false);

	/**
	 * Handle textarea change
	 *
	 * @param {string} value - The value to set.
	 */
	const handleChange = (value) => {
		setLocalDomains(value);
		onChange(value);
	};

	/**
	 * Add example domain
	 */
	const addExampleDomain = () => {
		const exampleDomain = 'https://example.com';
		const currentDomains = localDomains.trim();
		const newDomains = currentDomains ? `${currentDomains}\n${exampleDomain}` : exampleDomain;

		handleChange(newDomains);
	};

	/**
	 * Clear all domains
	 */
	const clearDomains = () => {
		if (
			// eslint-disable-next-line no-alert, no-restricted-globals
			confirm(
				__(
					'Are you sure you want to clear all excluded domains?',
					'smart-auto-upload-images',
				),
			)
		) {
			handleChange('');
		}
	};

	return (
		<div className="smart-aui-domain-exclusions">
			<VStack spacing={3}>
				<div>
					<h4>{__('Exclude Domains', 'smart-auto-upload-images')}</h4>
					<p className="description">
						{__(
							'Enter domains to exclude from image uploading (one per line).',
							'smart-auto-upload-images',
						)}
					</p>
				</div>

				<TextareaControl
					value={localDomains}
					onChange={handleChange}
					placeholder={__(
						'https://example.com\nhttps://cdn.example.com',
						'smart-auto-upload-images',
					)}
					rows={6}
					help={__(
						'Enter one domain per line. You can use full URLs or just domain names.',
						'smart-auto-upload-images',
					)}
				/>

				<HStack justify="flex-start" spacing={2}>
					<Button variant="secondary" size="small" onClick={() => setShowHelp(!showHelp)}>
						{showHelp
							? __('Hide Help', 'smart-auto-upload-images')
							: __('Show Help', 'smart-auto-upload-images')}
					</Button>
					<Button variant="secondary" size="small" onClick={addExampleDomain}>
						{__('Add Example', 'smart-auto-upload-images')}
					</Button>
					{localDomains && (
						<Button
							variant="secondary"
							size="small"
							onClick={clearDomains}
							isDestructive
						>
							{__('Clear All', 'smart-auto-upload-images')}
						</Button>
					)}
				</HStack>

				{showHelp && (
					<div className="smart-aui-help-panel">
						<h5>{__('Domain Exclusion Examples:', 'smart-auto-upload-images')}</h5>
						<ul>
							<li>
								<code>https://example.com</code> -{' '}
								{__(
									'Exclude all images from example.com',
									'smart-auto-upload-images',
								)}
							</li>
							<li>
								<code>https://cdn.example.com</code> -{' '}
								{__('Exclude CDN images', 'smart-auto-upload-images')}
							</li>
							<li>
								<code>https://images.unsplash.com</code> -{' '}
								{__('Exclude Unsplash images', 'smart-auto-upload-images')}
							</li>
							<li>
								<code>https://via.placeholder.com</code> -{' '}
								{__('Exclude placeholder images', 'smart-auto-upload-images')}
							</li>
						</ul>
						<p className="description">
							{__(
								'The plugin will automatically ignore images from these domains when processing posts.',
								'smart-auto-upload-images',
							)}
						</p>
					</div>
				)}
			</VStack>
		</div>
	);
};

export default DomainExclusions;
