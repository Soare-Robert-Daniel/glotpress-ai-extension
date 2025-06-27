/**
 * Global TypeScript definitions for GlotPress AI Translation
 */

/**
 * The global GlotPress AI Translation labels object containing localized strings.
 */
interface GpAiTranslationLabels {
	// Translation Button labels
	missingButton: string;
	translationFailed: string;
	errorOccurred: string;
	translating: string;
	translationComplete: string;
	
	// Translation Progress labels
	missingProgress: string;
	translationProgress: string;
	error: string;
	unknownError: string;
	logError: string;
	missingLogError: string;
    connectionError: string;
}

/**
 * The global GlotPress AI Translation configuration object.
 */
interface GpAiTranslation {
	ajaxurl: string;
	labels: GpAiTranslationLabels;
}

/**
 * Extend the global Window interface to include common properties
 * if they exist globally in your application.
 */
declare global {
	interface Window {
		gpAiTranslation?: GpAiTranslation;
	}
}

export default GpAiTranslation;