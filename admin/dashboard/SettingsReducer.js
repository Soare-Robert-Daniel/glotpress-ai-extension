export const initialSettings = {
	open_ai_key: '',
	open_ai_model: '',
	open_ai_key_masked: 'sk_afvttfasdfmlasXXXXXXXXXXXXXXXXXX',
};

export const SETTINGS_ACTION = {
	set: 'set',
	applyPatch: 'applyPatch',
};

export function settingsReducer( settings, action ) {
	switch ( action.type ) {
		case SETTINGS_ACTION.set: {
			return {
				...settings,
				[ action.key ]: action.value,
			};
		}
		case SETTINGS_ACTION.applyPatch: {
			console.log( action );
			return {
				...settings,
				...action.value,
			};
		}
		default: {
			throw Error( 'Unknown action: ' + action.type );
		}
	}
}
