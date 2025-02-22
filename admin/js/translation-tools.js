/**
 * The global GlotPress AI Translation object containing configuration and translations.
 * @global
 * 
 * @typedef {Object} GpAiTranslation
 * @property {string} ajaxurl - The URL to the WordPress admin-ajax.php file
 * @property {Object} labels - Translatable text labels
 * @property {string} labels.missingButton - Error message when the translate button is not found
 * @property {string} labels.missingProgress - Error message when the progress element is not found
 * @property {string} labels.translationFailed - Message displayed when translation fails
 * @property {string} labels.errorOccurred - General error message for translation processing
 * @property {string} labels.translating - Text displayed while translation is in progress
 * @property {string} labels.translationProgress - Label for translation progress indicator
 * @property {string} labels.translationComplete - Success message when translation completes
 * @property {string} labels.error - General error label
 * @property {string} labels.unknownError - Message for unknown errors
 */

class TranslationHandler {
    /**
     * Creates an instance of TranslationHandler.
     *
     * @param {HTMLButtonElement} translateBtn - The translation button element.
     * @param {GpAiTranslation} gpAiTranslation - The GlotPress AI Translation configuration object.
     */
    constructor(translateBtn, gpAiTranslation) {
        if (!translateBtn) {
            throw new Error( gpAiTranslation.labels.missingButton );
        }
        this.translateBtn = translateBtn;
        this.originalText = translateBtn.textContent;
        this.gpAiTranslation = gpAiTranslation;
        this.addClickListener();
    }

    /**
     * Adds the click event listener to the translation button.
     */
    addClickListener() {
        this.translateBtn.addEventListener('click', (event) => this.handleClick(event));
    }

    /**
     * Handles the click event.
     *
     * @param {Event} event - The click event.
     */
    async handleClick(event) {
        event.preventDefault();
        this.disableButton();

        const { setId, targetLang, nonce } = this.getDataAttributes();
        const formData = this.buildFormData(setId, targetLang, nonce);

        try {
            const result = await this.sendRequest(formData);

            if (result.success) {
                this.handleSuccess(result.data.message);
            } else {
                this.handleFailure( result.data.message || this.gpAiTranslation.labels.translationFailed );
            }
        } catch (error) {
            console.error('Translation error:', error);
            alert(this.gpAiTranslation.labels.errorOccurred);
            this.restoreButton();
        }
    }

    /**
     * Disables the button and updates its text.
     */
    disableButton() {
        this.translateBtn.disabled = true;
        this.translateBtn.textContent = this.gpAiTranslation.labels.translating;
    }

    /**
     * Restores the button state.
     */
    restoreButton() {
        this.translateBtn.disabled = false;
        this.translateBtn.textContent = this.originalText;
    }

    /**
     * Retrieves data attributes from the translation button.
     *
     * @returns {Object} An object containing setId, targetLang, and nonce.
     */
    getDataAttributes() {
        return {
            setId: this.translateBtn.getAttribute('data-set-id'),
            targetLang: this.translateBtn.getAttribute('data-target-lang'),
            nonce: this.translateBtn.getAttribute('data-nonce')
        };
    }

    /**
     * Prepares the form data for the AJAX POST request.
     *
     * @param {string} setId - The translation set ID.
     * @param {string} targetLang - The target language.
     * @param {string} nonce - The nonce value.
     * @returns {FormData} The form data.
     */
    buildFormData(setId, targetLang, nonce) {
        const formData = new FormData();
        formData.append('action', 'gp_ai_translate');
        formData.append('set_id', setId);
        formData.append('target_language', targetLang);
        formData.append('nonce', nonce);
        return formData;
    }

    /**
     * Sends the AJAX request using the Fetch API.
     *
     * @param {FormData} formData - The form data to send.
     * @returns {Promise<Object>} The JSON-parsed response.
     */
    async sendRequest(formData) {
        const response = await fetch(this.gpAiTranslation.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }

    /**
     * Handles a successful translation response.
     *
     * @param {string} message - The success message from the server.
     */
    handleSuccess(message) {
        this.translateBtn.textContent = message;
        this.translateBtn.disabled = true;
        this.translateBtn.classList.add('translation-success');
    }

    /**
     * Handles a failure response.
     *
     * @param {string} message - The error message.
     */
    handleFailure(message) {
        alert(message);
        this.restoreButton();
    }
}

class TranslationProgress {
    /**
     * Creates a TranslationProgress instance.
     *
     * @param {HTMLElement} progressElement The element that displays progress.
     * @param {TranslationHandler} translationHandler The translation handler instance.
     * @param {GpAiTranslation} gpAiTranslation - The GlotPress AI Translation configuration object.
     */
    constructor(progressElement, translationHandler, gpAiTranslation) {
        if (!progressElement) {
            throw new Error(gpAiTranslation.labels.missingProgress);
        }
        this.progressElement = progressElement;
        this.translationHandler = translationHandler;
        this.gpAiTranslation = gpAiTranslation;
        this.projectId = progressElement.getAttribute('data-project-id') ?? '';
        this.setId = progressElement.getAttribute('data-set-id') ?? '';
        this.nonce = progressElement.getAttribute('data-nonce') ?? '';
        this.pollingInterval = undefined;
    }

    /**
     * Builds the query string for the AJAX request.
     *
     * @return {string} The query string.
     */
    buildQueryString() {
        const params = new URLSearchParams({
            action: 'gp_ai_translation_progress',
            project_id: this.projectId,
            set_id: this.setId,
            nonce: this.nonce
        });
        return params.toString();
    }

    /**
     * Fetches the translation progress via AJAX.
     *
     * @return {Promise<Object|null>} The parsed JSON response or null on error.
     */
    async fetchProgress() {
        try {
            const queryString = this.buildQueryString();
            const response = await fetch(`${this.gpAiTranslation.ajaxurl}?${queryString}`);
            return await response.json();
        } catch (error) {
            console.error('Error fetching translation progress:', error);
            return null;
        }
    }

    /**
     * Updates the progress UI.
     *
     * If the total is 0, nothing will be displayed.
     *
     * @param {Object} progressData An object containing translated and total values.
     */
    updateProgressUI(progressData) {
        const { translated, total, completed } = progressData;

        if (total === 0) {
            // Do not display any progress if no translations exist.
            this.progressElement.textContent = '';
            return;
        }

        const progressPercent = Math.round((translated / total) * 100);
        this.progressElement.textContent = `${this.gpAiTranslation.labels.translationProgress}: ${translated} / ${total} (${progressPercent}%)`;

        if ( completed ) {
            setTimeout(() => this.handleCompletion(), 200);
        }
    }

    /**
     * Handles translation completion
     */
    handleCompletion() {
        alert(this.gpAiTranslation.labels.translationComplete);
        if (this.translationHandler) {
            this.translationHandler.restoreButton();
        }
    }

    /**
     * Handles the AJAX response and updates the UI accordingly.
     */
    async handleProgress() {
        const data = await this.fetchProgress();
        if (data) {
            if (data.success) {
                this.updateProgressUI(data.data);
            } else {
                this.progressElement.textContent = `${this.gpAiTranslation.labels.error}: ${data.data.message ?? this.gpAiTranslation.labels.unknownError }`;
            }
        }
    }

    /**
     * Starts polling for translation progress.
     *
     * @param {number} [intervalMs=2000] The polling interval in milliseconds.
     */
    startPolling(intervalMs = 2000) {
        // Call immediately for an initial update.
        this.handleProgress();
        this.pollingInterval = setInterval(() => {
            this.handleProgress();
        }, intervalMs);
    }

    /**
     * Stops the polling.
     */
    stopPolling() {
        clearInterval(this.pollingInterval);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const translateBtn = document.getElementById('ai-translate-btn');
    const progressElement = document.getElementById('ai-translate-progress');

    if (!translateBtn || !progressElement) {
        return;
    }

    // Pass the global gpAiTranslation to the classes
    const translationHandler = new TranslationHandler(translateBtn, window.gpAiTranslation);
    const translationProgress = new TranslationProgress(progressElement, translationHandler, window.gpAiTranslation);

    translationProgress.startPolling();
});