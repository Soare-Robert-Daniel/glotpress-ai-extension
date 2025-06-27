import { String, Object } from 'runtypes';

import apiFetch from '@wordpress/api-fetch';

const SettingSchema = Object( {
	open_ai_key: String.optional(),
	open_ai_model: String.optional(),
} );

export async function fetchSettings() {
	const data = await apiFetch( {
		path: '/glotpress-ai-extension/v1/settings',
		method: 'GET',
	} );

	SettingSchema.check( data );

	return data;
}

export async function pushSettings( settingsToSave ) {
	const data = await apiFetch( {
		path: '/glotpress-ai-extension/v1/settings',
		method: 'POST',
		data: settingsToSave,
	} );

	SettingSchema.check( data );

	return data;
}
