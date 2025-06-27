import { initTranslationButton } from './actions';
import { initTranslationProgress } from './progress';
import './style.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const translateBtn = document.getElementById( 'ai-translate-btn' );
	const progressElement = document.getElementById( 'ai-translate-progress' );

	if ( ! translateBtn || ! progressElement ) {
		return;
	}

	if ( ! window.gpAiTranslation ) {
		return;
	}

	// Pass the global gpAiTranslation to the classes
	const translationHandler = initTranslationButton(
		translateBtn as HTMLButtonElement,
		window.gpAiTranslation
	);
	const translationProgress = initTranslationProgress(
		progressElement,
		translationHandler,
		window.gpAiTranslation
	);
	// translationHandler.addListener( () => translationProgress.clearHTML() );

	translationProgress.startPolling();
} );
