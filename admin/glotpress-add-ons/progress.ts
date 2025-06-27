import van, { State } from 'vanjs-core';
import { clsx } from 'clsx/lite';

const { div, a, span, svg, path, circle } = van.tags;

// TypeScript type definitions
interface GpAiTranslationLabels {
	translationProgress: string;
	error: string;
	unknownError: string;
	logError: string;
	missingLogError: string;
	missingProgress: string;
}

interface GpAiTranslation {
	ajaxurl: string;
	labels: GpAiTranslationLabels;
}

interface TranslationHandler {
	restoreButton: () => void;
}

interface ProgressData {
	translated: number;
	total: number;
	completed: boolean;
	success?: boolean;
	logUrl?: string;
	started: boolean;
}

interface ApiResponse {
	success: boolean;
	data: ProgressData & {
		message?: string;
	};
}

interface TranslationProgressConfig {
	projectId: string;
	setId: string;
	nonce: string;
	gpAiTranslation: GpAiTranslation;
	translationHandler?: TranslationHandler | null;
}

interface TranslationProgressStates {
	progressText: State< string >;
	errorMessage: State< string >;
	logUrl: State< string >;
	isCompleted: State< boolean >;
	showLogButton: State< boolean >;
	progressPercent: State< number >;
	translated: State< number >;
	total: State< number >;
}

interface TranslationProgressComponent {
	element: HTMLElement;
	startPolling: ( intervalMs?: number ) => void;
	stopPolling: () => void;
	clear: () => void;
	states: TranslationProgressStates;
}

// SVG Icons as components with prefixed classes
const CheckIcon = () =>
	svg(
		{
			width: '20',
			height: '20',
			viewBox: '0 0 20 20',
			fill: 'none',
			class: 'gp_ai_ext_icon-check',
		},
		circle( {
			cx: '10',
			cy: '10',
			r: '9',
			'stroke-width': '2',
		} ),
		path( {
			d: 'M6 10l3 3 5-6',
			'stroke-width': '2',
			'stroke-linecap': 'round',
			'stroke-linejoin': 'round',
		} )
	);

const ErrorIcon = () =>
	svg(
		{
			width: '20',
			height: '20',
			viewBox: '0 0 20 20',
			fill: 'none',
			class: 'gp_ai_ext_icon-error',
		},
		circle( {
			cx: '10',
			cy: '10',
			r: '9',
			'stroke-width': '2',
		} ),
		path( {
			d: 'M10 6v4m0 4h.01',
			'stroke-width': '2',
			'stroke-linecap': 'round',
		} )
	);

const ExternalLinkIcon = () =>
	svg(
		{
			width: '16',
			height: '16',
			viewBox: '0 0 16 16',
			fill: 'none',
			class: 'gp_ai_ext_icon-external',
		},
		path( {
			d: 'M6.5 3.5h-3a1 1 0 00-1 1v8a1 1 0 001 1h8a1 1 0 001-1v-3m-9 0L13.5 2.5m0 0h-4m4 0v4',
			stroke: 'currentColor',
			'stroke-width': '1.5',
			'stroke-linecap': 'round',
			'stroke-linejoin': 'round',
		} )
	);

/**
 * Creates a translation progress component using VanJS
 * @param config
 */
export const createTranslationProgress = (
	config: TranslationProgressConfig
): TranslationProgressComponent => {
	const { projectId, setId, nonce, gpAiTranslation, translationHandler } =
		config;

	// Reactive states
	const progressText = van.state( '' );
	const errorMessage = van.state( '' );
	const logUrl = van.state( '' );
	const isCompleted = van.state( false );
	const showLogButton = van.state( false );
	const progressPercent = van.state( 0 );
	const translated = van.state( 0 );
	const total = van.state( 0 );
	const started = van.state( false );

	let pollingInterval: NodeJS.Timeout | null = null;

	/**
	 * Builds the query string for the AJAX request
	 */
	const buildQueryString = (): string => {
		const params = new URLSearchParams( {
			action: 'gp_ai_translation_progress',
			project_id: projectId,
			set_id: setId,
			nonce,
		} );
		return params.toString();
	};

	/**
	 * Fetches the translation progress via AJAX
	 */
	const fetchProgress = async (): Promise< ApiResponse | null > => {
		try {
			const queryString = buildQueryString();
			const response = await fetch(
				`${ gpAiTranslation.ajaxurl }?${ queryString }`
			);
			return await response.json();
		} catch ( error ) {
			console.error( 'Error fetching translation progress:', error );
			return null;
		}
	};

	/**
	 * Updates the progress UI
	 * @param progressData
	 */
	const updateProgressUI = ( progressData: ProgressData ): void => {
		const {
			translated: trans,
			total: tot,
			completed,
			started: strt,
		} = progressData;

		// Update states regardless of total value
		translated.val = trans;
		total.val = tot;
		started.val = strt;

		// Hide progress if no translations
		if ( tot === 0 ) {
			progressText.val = '';
			progressPercent.val = 0;
			return;
		}

		progressPercent.val = Math.round( ( trans / tot ) * 100 );
		progressText.val = `${ gpAiTranslation.labels.translationProgress }`;
		errorMessage.val = '';

		if ( completed ) {
			console.log( progressData );
			isCompleted.val = true;
			setTimeout( () => handleCompletion( progressData ), 200 );
		}
	};

	/**
	 * Handles translation completion
	 * @param progressData
	 */
	const handleCompletion = ( progressData: ProgressData ): void => {
		if ( ! progressData.success && progressData.logUrl ) {
			logUrl.val = progressData.logUrl;
			showLogButton.val = true;
			progressText.val = '';
		}

		if ( translationHandler ) {
			translationHandler.restoreButton();
		}

		stopPolling();
	};

	/**
	 * Handles the AJAX response and updates the UI accordingly
	 */
	const handleProgress = async (): Promise< void > => {
		try {
			const data = await fetchProgress();

			if ( ! data ) {
				// Network error or timeout
				errorMessage.val = 'Failed to connect to server. Retrying...';
				return;
			}

			if ( data.success ) {
				updateProgressUI( data.data );
			} else {
				errorMessage.val =
					data.data.message ?? gpAiTranslation.labels.unknownError;
				progressText.val = '';
				// Stop polling on error
				stopPolling();
			}
		} catch ( error ) {
			console.error( 'Progress update error:', error );
			errorMessage.val = 'Connection error. Please check your network.';
		}
	};

	/**
	 * Starts polling for translation progress
	 * @param intervalMs
	 */
	const startPolling = ( intervalMs: number = 1500 ): void => {
		// Call immediately for an initial update
		handleProgress();
		pollingInterval = setInterval( () => {
			handleProgress();
		}, intervalMs );
	};

	/**
	 * Stops the polling
	 */
	const stopPolling = (): void => {
		if ( pollingInterval ) {
			clearInterval( pollingInterval );
			pollingInterval = null;
		}
	};

	/**
	 * Clear the progress display
	 */
	const clear = (): void => {
		progressText.val = '';
		errorMessage.val = '';
		showLogButton.val = false;
		logUrl.val = '';
		isCompleted.val = false;
		progressPercent.val = 0;
		translated.val = 0;
		total.val = 0;
		started.val = false;
	};

	// Create the DOM element with fixed reactive bindings and prefixed classes
	const progressElement = div(
		{
			class: van.derive( () =>
				clsx(
					'gp_ai_ext_translation-progress-container',
					! started.val && 'gp_ai_ext_hide'
				)
			),
		},

		// Progress section
		van.derive( () => {
			const showProgress =
				! isCompleted.val && ! errorMessage.val && total.val > 0;
			if ( ! showProgress ) {
				return '';
			}

			return div(
				div(
					{ class: 'gp_ai_ext_progress-header' },
					span(
						{ class: 'gp_ai_ext_progress-title' },
						() => progressText.val
					),
					span(
						{ class: 'gp_ai_ext_progress-stats' },
						() => `${ translated.val } / ${ total.val }`
					)
				),
				div(
					{ class: 'gp_ai_ext_progress-bar-container' },
					div( {
						class: van.derive(
							() =>
								`gp_ai_ext_progress-bar ${
									isCompleted.val ? 'complete' : ''
								}`
						),
						style: van.derive(
							() => `width: ${ progressPercent.val }%`
						),
					} )
				),
				div(
					{ class: 'gp_ai_ext_progress-percentage' },
					() => `${ progressPercent.val }%`
				)
			);
		} ),

		// Success message
		van.derive( () => {
			const showSuccess = isCompleted.val && ! showLogButton.val;
			if ( ! showSuccess ) {
				return '';
			}

			return div(
				{ class: 'gp_ai_ext_success-message' },
				CheckIcon(),
				span( 'Translation completed successfully!' )
			);
		} ),

		// Error message
		van.derive( () => {
			if ( ! errorMessage.val ) {
				return '';
			}

			return div(
				{ class: 'gp_ai_ext_error-message' },
				ErrorIcon(),
				span( () => errorMessage.val )
			);
		} ),

		// Log button
		van.derive( () => {
			if ( ! showLogButton.val ) {
				return '';
			}

			return a(
				{
					href: logUrl.val,
					target: '_blank',
					class: 'gp_ai_ext_log-button',
				},
				span( () =>
					logUrl.val
						? gpAiTranslation.labels.logError
						: gpAiTranslation.labels.missingLogError
				),
				ExternalLinkIcon()
			);
		} )
	);

	// Return the component API
	return {
		element: progressElement,
		startPolling,
		stopPolling,
		clear,
		states: {
			progressText,
			errorMessage,
			logUrl,
			isCompleted,
			showLogButton,
			progressPercent,
			translated,
			total,
		},
	};
};

/**
 * Helper function to initialize the component from an existing DOM element
 * @param existingElement
 * @param translationHandler
 * @param gpAiTranslation
 */
export const initTranslationProgress = (
	existingElement: HTMLElement,
	translationHandler: TranslationHandler | null,
	gpAiTranslation: GpAiTranslation
): TranslationProgressComponent => {
	if ( ! existingElement ) {
		throw new Error( gpAiTranslation.labels.missingProgress );
	}

	const config: TranslationProgressConfig = {
		projectId: existingElement.getAttribute( 'data-project-id' ) ?? '',
		setId: existingElement.getAttribute( 'data-set-id' ) ?? '',
		nonce: existingElement.getAttribute( 'data-nonce' ) ?? '',
		gpAiTranslation,
		translationHandler,
	};

	const component = createTranslationProgress( config );

	// Replace the existing element with the VanJS component
	existingElement.replaceWith( component.element );

	return component;
};
