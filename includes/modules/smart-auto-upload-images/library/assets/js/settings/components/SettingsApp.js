/**
 * Main Settings App Component
 *
 * @package SmartAutoUploadImages
 */

import { useState, useEffect, WPElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	SnackbarList,
	Spinner,
	Button,
	__experimentalSpacer as Spacer, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import SettingsForm from './SettingsForm';

/**
 * Settings App Component
 *
 * @returns {WPElement|null} The settings app component.
 */
const SettingsApp = () => {
	const [settings, setSettings] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isSaving, setIsSaving] = useState(false);
	const [snackbars, setSnackbars] = useState([]);
	const [hasChanges, setHasChanges] = useState(false);

	/**
	 * Add snackbar
	 *
	 * @param {string} message - The message to add.
	 * @param {string} type - The type of snackbar.
	 */
	const addSnackbar = (message, type = 'info') => {
		const snackbar = {
			id: Date.now(),
			message,
			type,
		};
		setSnackbars((prev) => [...prev, snackbar]);
	};

	/**
	 * Remove snackbar
	 *
	 * @param {string} id - The ID of the snackbar to remove.
	 */
	const removeSnackbar = (id) => {
		setSnackbars((prev) => prev.filter((s) => s.id !== id));
	};

	/**
	 * Load settings from API
	 */
	const loadSettings = async () => {
		try {
			setIsLoading(true);
			const response = await apiFetch({ path: '/smart-aui/v1/settings' });
			setSettings(response);
		} catch (error) {
			addSnackbar(__('Failed to load settings.', 'smart-auto-upload-images'), 'error');
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Save settings to API
	 *
	 * @param {object} newSettings - The new settings to save.
	 */
	const saveSettings = async (newSettings) => {
		try {
			setIsSaving(true);
			const response = await apiFetch({
				path: '/smart-aui/v1/settings',
				method: 'POST',
				data: newSettings,
			});

			setSettings(response);
			setHasChanges(false);
			addSnackbar(__('Settings saved successfully!', 'smart-auto-upload-images'), 'success');
		} catch (error) {
			const errorMessage =
				error?.message || __('Failed to save settings.', 'smart-auto-upload-images');
			addSnackbar(errorMessage, 'error');
		} finally {
			setIsSaving(false);
		}
	};

	/**
	 * Reset settings to defaults
	 */
	const resetSettings = async () => {
		if (
			// eslint-disable-next-line no-restricted-globals, no-alert
			!confirm(
				__(
					'Are you sure you want to reset all settings to defaults?',
					'smart-auto-upload-images',
				),
			)
		) {
			return;
		}

		try {
			setIsSaving(true);
			const response = await apiFetch({
				path: '/smart-aui/v1/settings/reset',
				method: 'POST',
			});

			setSettings(response);
			setHasChanges(false);
			addSnackbar(
				__('Settings reset to defaults successfully!', 'smart-auto-upload-images'),
				'success',
			);
		} catch (error) {
			addSnackbar(__('Failed to reset settings.', 'smart-auto-upload-images'), 'error');
		} finally {
			setIsSaving(false);
		}
	};

	/**
	 * Handle settings change.
	 *
	 * @param {object} newSettings - The new settings.
	 */
	const handleSettingsChange = (newSettings) => {
		setSettings(newSettings);
		setHasChanges(true);
	};

	/**
	 * Load settings on mount.
	 */
	useEffect(() => {
		loadSettings();
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	if (isLoading) {
		return (
			<div className="smart-aui-admin-loading">
				<Spinner />
				<p>{__('Loading settings...', 'smart-auto-upload-images')}</p>
			</div>
		);
	}

	return (
		<div className="smart-aui-admin-wrapper">
			<VStack spacing={4}>
				<div className="smart-aui-admin-header">
					<h1>{__('Smart Auto Upload Images Settings', 'smart-auto-upload-images')}</h1>
					{/* <p className="description">
						{__(
							'Configure how external images are automatically uploaded and processed.',
							'smart-auto-upload-images',
						)}
					</p> */}
				</div>

				<div className="smart-aui-admin-content">
					<Card>
						<CardHeader>
							<h2>{__('Plugin Settings', 'smart-auto-upload-images')}</h2>
						</CardHeader>
						<CardBody>
							<SettingsForm
								settings={settings}
								onSettingsChange={handleSettingsChange}
							/>
						</CardBody>
					</Card>

					<Spacer marginTop={4} />

					<HStack justify="flex-start" spacing={2}>
						<Button
							variant="primary"
							onClick={() => saveSettings(settings)}
							isBusy={isSaving}
							disabled={!hasChanges || isSaving}
						>
							{__('Save Settings', 'smart-auto-upload-images')}
						</Button>
						<Button variant="secondary" onClick={resetSettings} disabled={isSaving}>
							{__('Reset to Defaults', 'smart-auto-upload-images')}
						</Button>
					</HStack>
					<SnackbarList
						className="smart-aui-admin-snackbar-list"
						notices={snackbars.map((snackbar) => ({
							id: snackbar.id,
							content: snackbar.message,
							status: snackbar.type,
						}))}
						onRemove={removeSnackbar}
					/>
				</div>
			</VStack>
		</div>
	);
};

export default SettingsApp;
