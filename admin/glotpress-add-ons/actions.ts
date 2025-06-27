import van, { State } from 'vanjs-core';
import type GpAiTranslation from '../global';

const { button } = van.tags;

interface TranslationButtonConfig {
	setId: string;
	targetLang: string;
	nonce: string;
	gpAiTranslation: GpAiTranslation;
	originalText: string;
	className?: string;
}

interface TranslationButtonStates {
	buttonText: State< string >;
	isDisabled: State< boolean >;
	isTranslating: State< boolean >;
	isSuccess: State< boolean >;
}

interface TranslationButtonComponent {
	element: HTMLElement;
	states: TranslationButtonStates;
	addListener: ( listener: () => void ) => void;
	startTranslation: () => Promise< void >;
	restoreButton: () => void;
}

/**
 * Creates a translation button component using VanJS
 * @param config
 */
export const createTranslationButton = (
	config: TranslationButtonConfig
): TranslationButtonComponent => {
	const {
		setId,
		targetLang,
		nonce,
		gpAiTranslation,
		originalText,
		className,
	} = config;

	// Reactive states
	const buttonText = van.state( originalText );
	const isDisabled = van.state( false );
	const isTranslating = van.state( false );
	const isSuccess = van.state( false );

	// Event listeners array
	const listeners: Array< () => void > = [];

	/**
	 * Adds a listener to be called when translation starts
	 * @param listener
	 */
	const addListener = ( listener: () => void ): void => {
		listeners.push( listener );
	};

	/**
	 * Builds the form data for the AJAX POST request
	 */
	const buildFormData = (): FormData => {
		const formData = new FormData();
		formData.append( 'action', 'gp_ai_translate' );
		formData.append( 'set_id', setId );
		formData.append( 'target_language', targetLang );
		formData.append( 'nonce', nonce );
		return formData;
	};

	/**
	 * Sends the AJAX request using the Fetch API
	 * @param formData
	 */
	const sendRequest = async ( formData: FormData ): Promise< any > => {
		const response = await fetch( gpAiTranslation.ajaxurl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} );

		if ( ! response.ok ) {
			throw new Error( `HTTP error! status: ${ response.status }` );
		}

		return response.json();
	};

	/**
	 * Handles a successful translation response
	 * @param message
	 */
	const handleSuccess = ( message: string ): void => {
		buttonText.val = message;
		isDisabled.val = true;
		isSuccess.val = true;
		isTranslating.val = false;
	};

	/**
	 * Handles a failure response
	 * @param message
	 */
	const handleFailure = ( message: string ): void => {
		alert( message );
		restoreButton();
	};

	/**
	 * Restores the button to its original state
	 */
	const restoreButton = (): void => {
		buttonText.val = originalText;
		isDisabled.val = false;
		isTranslating.val = false;
		isSuccess.val = false;
	};

	/**
	 * Starts the translation process
	 */
	const startTranslation = async (): Promise< void > => {
		// Update button state
		isDisabled.val = true;
		isTranslating.val = true;
		buttonText.val = gpAiTranslation.labels.translating;

		// Notify listeners
		listeners.forEach( ( listener ) => listener?.() );

		const formData = buildFormData();

		try {
			const result = await sendRequest( formData );

			if ( result.success ) {
				handleSuccess( result.data.message );
			} else {
				handleFailure(
					result.data.message ||
						gpAiTranslation.labels.translationFailed
				);
			}
		} catch ( error ) {
			console.error( 'Translation error:', error );
			alert( gpAiTranslation.labels.errorOccurred );
			restoreButton();
		}
	};

	/**
	 * Handles the click event
	 * @param event
	 */
	const handleClick = async ( event: Event ): Promise< void > => {
		event.preventDefault();
		await startTranslation();
	};

	// Create the button element
	const buttonElement = button(
		{
			class: van.derive( () => {
				const classes = [ className || '' ].filter( Boolean );
				if ( isSuccess.val ) {
					classes.push( 'translation-success' );
				}
				return classes.join( ' ' );
			} ),
			disabled: () => isDisabled.val,
			onclick: handleClick,
			'data-set-id': setId,
			'data-target-lang': targetLang,
			'data-nonce': nonce,
		},
		() => buttonText.val
	);

	// Return the component API
	return {
		element: buttonElement,
		states: {
			buttonText,
			isDisabled,
			isTranslating,
			isSuccess,
		},
		addListener,
		startTranslation,
		restoreButton,
	};
};

/**
 * Transforms an existing button element into a VanJS translation button
 * @param existingButton
 * @param gpAiTranslation
 */
export const initTranslationButton = (
	existingButton: HTMLButtonElement,
	gpAiTranslation: GpAiTranslation
): TranslationButtonComponent => {
	if ( ! existingButton ) {
		throw new Error( gpAiTranslation.labels.missingButton );
	}

	// Extract configuration from existing button
	const config: TranslationButtonConfig = {
		setId: existingButton.getAttribute( 'data-set-id' ) || '',
		targetLang: existingButton.getAttribute( 'data-target-lang' ) || '',
		nonce: existingButton.getAttribute( 'data-nonce' ) || '',
		gpAiTranslation,
		originalText: existingButton.textContent || '',
		className: existingButton.className,
	};

	// Create the VanJS component
	const component = createTranslationButton( config );

	// Replace the existing button with the VanJS component
	existingButton.replaceWith( component.element );

	return component;
};
