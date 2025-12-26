/**
 * Info Panel Component
 *
 * @package SmartAutoUploadImages
 */

import { __ } from '@wordpress/i18n';
import { WPElement } from '@wordpress/element';
import {
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	__experimentalVStack as VStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalDivider as Divider, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';

/**
 * Info Panel Component
 *
 * @returns {WPElement} The info panel component.
 */
const InfoPanel = () => {
	const patternVariables = [
		{ pattern: '%filename%', description: __('Original filename', 'smart-auto-upload-images') },
		{
			pattern: '%image_alt%',
			description: __('Original alt text', 'smart-auto-upload-images'),
		},
		{
			pattern: '%image_title%',
			description: __('Original image title', 'smart-auto-upload-images'),
		},
		{ pattern: '%post_title%', description: __('Post title', 'smart-auto-upload-images') },
		{ pattern: '%post_name%', description: __('Post slug', 'smart-auto-upload-images') },
		{ pattern: '%year%', description: __('Current year', 'smart-auto-upload-images') },
		{ pattern: '%month%', description: __('Current month', 'smart-auto-upload-images') },
		{ pattern: '%day%', description: __('Current day', 'smart-auto-upload-images') },
		{
			pattern: '%today_date%',
			description: __("Today's date (Y-m-d)", 'smart-auto-upload-images'),
		},
		{
			pattern: '%post_date%',
			description: __('Post date (Y-m-d)', 'smart-auto-upload-images'),
		},
		{ pattern: '%post_year%', description: __('Post year', 'smart-auto-upload-images') },
		{ pattern: '%post_month%', description: __('Post month', 'smart-auto-upload-images') },
		{ pattern: '%post_day%', description: __('Post day', 'smart-auto-upload-images') },
		{ pattern: '%random%', description: __('Random string', 'smart-auto-upload-images') },
		{
			pattern: '%timestamp%',
			description: __('Current timestamp', 'smart-auto-upload-images'),
		},
		{ pattern: '%url%', description: __('Site URL', 'smart-auto-upload-images') },
	];

	return (
		<VStack spacing={4}>
			<Card>
				<CardHeader>
					<h3>{__('Pattern Variables', 'smart-auto-upload-images')}</h3>
				</CardHeader>
				<CardBody>
					<p className="description">
						{__(
							'Use these variables in your filename and alt text patterns:',
							'smart-auto-upload-images',
						)}
					</p>
					<div className="smart-aui-pattern-variables">
						{patternVariables.map((variable) => (
							<div key={variable.pattern} className="smart-aui-pattern-variable">
								<code>{variable.pattern}</code>
								<span>{variable.description}</span>
							</div>
						))}
					</div>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h3>{__('Examples', 'smart-auto-upload-images')}</h3>
				</CardHeader>
				<CardBody>
					<VStack spacing={3}>
						<div>
							<h4>{__('Filename Patterns', 'smart-auto-upload-images')}</h4>
							<div className="smart-aui-examples">
								<div className="smart-aui-example">
									<code>%post_title%-%year%-%month%-%random%</code>
									<small>
										{__(
											'my-blog-post-2025-01-img_abc123',
											'smart-auto-upload-images',
										)}
									</small>
								</div>
								<div className="smart-aui-example">
									<code>%year%/%month%/%filename%</code>
									<small>
										{__('2025/01/original-image', 'smart-auto-upload-images')}
									</small>
								</div>
							</div>
						</div>

						<Divider />

						<div>
							<h4>{__('Alt Text Patterns', 'smart-auto-upload-images')}</h4>
							<div className="smart-aui-examples">
								<div className="smart-aui-example">
									<code>%post_title% - %image_alt%</code>
									<small>
										{__(
											'My Blog Post - Original Alt Text',
											'smart-auto-upload-images',
										)}
									</small>
								</div>
								<div className="smart-aui-example">
									<code>%image_alt% | %post_title%</code>
									<small>
										{__(
											'Original Alt Text | My Blog Post',
											'smart-auto-upload-images',
										)}
									</small>
								</div>
							</div>
						</div>
					</VStack>
				</CardBody>
			</Card>

			{/* <Card>
				<CardHeader>
					<h3>{__('Plugin Information', 'smart-auto-upload-images')}</h3>
				</CardHeader>
				<CardBody>
					<VStack spacing={3}>
						<div>
							<strong>{__('Version:', 'smart-auto-upload-images')}</strong> 1.2.1
						</div>
						<div>
							<strong>{__('Author:', 'smart-auto-upload-images')}</strong> Burhan
							Nasir
						</div>
						<Divider />
						<div>
							<h4>{__('Support & Links', 'smart-auto-upload-images')}</h4>
							<VStack spacing={2}>
								<ExternalLink href="https://wordpress.org/support/plugin/smart-auto-upload-images/">
									{__('Report Issues', 'smart-auto-upload-images')}
								</ExternalLink>
							</VStack>
						</div>
					</VStack>
				</CardBody>
			</Card> */}
		</VStack>
	);
};

export default InfoPanel;
