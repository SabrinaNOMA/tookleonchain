/**
 * EIP-6963 Wallet Connector
 *
 * This script implements the EIP-6963 standard for discovering multiple wallet providers
 * in a browser environment. It provides a clean, event-driven interface for a web
 * application to interact with available wallets, solving common timing and conflict issues.
 */
const WalletConnector = (() => {
    // Private state to store discovered wallet providers.
    const _providers = new Map();
    let _isInitialized = false;

    /**
     * Dispatches a custom event to notify the main application that the list of
     * available wallet providers has been updated.
     */
    const dispatchProvidersUpdate = () => {
        window.dispatchEvent(new CustomEvent('wallet-providers-updated', {
            detail: { providers: Array.from(_providers.values()) }
        }));
    };

    /**
     * Handles the 'eip6963:announceProvider' event which is broadcasted by wallet extensions.
     * @param {Event & {detail: object}} event The announcement event from the wallet.
     */
    const onAnnounceProvider = (event) => {
        const providerDetail = event.detail;
        // Add the provider to our store if it's not already there.
        if (!_providers.has(providerDetail.info.uuid)) {
            _providers.set(providerDetail.info.uuid, providerDetail);
            // Notify the application of the update.
            dispatchProvidersUpdate();
        }
    };

    /**
     * Initializes the connector by adding the event listener and requesting providers.
     * This should be called once when the application starts.
     */
    const initialize = () => {
        if (_isInitialized) return;
        
        // Listen for wallets announcing themselves.
        window.addEventListener('eip6963:announceProvider', onAnnounceProvider);
        
        // Request that all installed wallets announce themselves.
        window.dispatchEvent(new Event('eip6963:requestProvider'));
        
        _isInitialized = true;
    };

    // Public API exposed by the WalletConnector object.
    return {
        /**
         * Starts the wallet discovery process.
         */
        init: initialize,

        /**
         * Returns an array of all currently discovered wallet providers.
         * @returns {Array<object>} An array of EIP-6963 provider details.
         */
        getProviders: () => Array.from(_providers.values()),

        /**
         * Returns a specific provider detail by its unique identifier (UUID).
         * @param {string} uuid The UUID of the wallet provider.
         * @returns {object|undefined} The provider detail or undefined if not found.
         */
        getProviderByUUID: (uuid) => _providers.get(uuid),
    };
})();

// Automatically initialize the connector when this script is loaded.
WalletConnector.init();
