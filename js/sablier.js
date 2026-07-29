/**
 * SablierStreamFetcher Class
 *
 * This module provides a class to fetch and process data for Sablier V2 streams.
 * It uses ethers.js to interact with the Sablier smart contracts on a given network.
 */

// A minimal ABI for fetching stream details.
const VESTING_CONTRACT_ABI = [
    "function getUnderlyingToken(uint256 streamId) view returns (address)",
    "function getDepositedAmount(uint256 streamId) view returns (uint128)",
    "function streamedAmountOf(uint256 streamId) view returns (uint128)",
    "function getStartTime(uint256 streamId) view returns (uint40)",
    "function getEndTime(uint256 streamId) view returns (uint40)",
    "function getRecipient(uint256 streamId) view returns (address)",
    "function getSender(uint256 streamId) view returns (address)"
];

const ERC20_ABI = [
    "function symbol() view returns (string)",
    "function decimals() view returns (uint8)"
];

// The specific Sablier V2 LockupLinear contract address on Base Mainnet.
const VESTING_CONTRACT_ADDRESS = "0xb5d78dd3276325f5faf3106cc4acc56e28e0fe3b";

export class SablierStreamFetcher {
    /**
     * Initializes the fetcher with a provider for the specified network.
     * @param {string} rpcUrl The JSON-RPC endpoint for the network (e.g., Base).
     */
    constructor(rpcUrl) {
        if (!rpcUrl) {
            throw new Error("An RPC URL must be provided to the constructor.");
        }
        if (typeof ethers === 'undefined') {
            throw new Error("Ethers.js is not loaded. Please include it in your HTML.");
        }
        this.provider = new ethers.providers.JsonRpcProvider(rpcUrl);
        this.vestingContract = new ethers.Contract(VESTING_CONTRACT_ADDRESS, VESTING_CONTRACT_ABI, this.provider);
    }

    /**
     * Fetches comprehensive data for a given Sablier stream ID.
     * @param {string | number} streamId The ID of the Sablier stream (e.g., "LK-8453-14769").
     * @returns {Promise<object>} A promise that resolves to an object containing formatted stream data.
     */
    async fetchStreamData(streamId) {
        let numericStreamId = streamId;
        if (typeof streamId === 'string' && streamId.includes('-')) {
            numericStreamId = streamId.split('-').pop();
        }
        if (isNaN(numericStreamId)) {
             throw new Error(`Invalid Stream ID format: ${streamId}. Could not extract a numeric ID.`);
        }

        console.log(`Fetching data for stream ID: ${numericStreamId} from contract ${VESTING_CONTRACT_ADDRESS}`);

        try {
            // Fetch all stream details in parallel
            const [
                tokenAddress,
                totalAmountBigNum,
                streamedAmountBigNum,
                startTimeSec,
                endTimeSec,
                recipient,
                sender
            ] = await Promise.all([
                this.vestingContract.getUnderlyingToken(numericStreamId),
                this.vestingContract.getDepositedAmount(numericStreamId),
                this.vestingContract.streamedAmountOf(numericStreamId),
                this.vestingContract.getStartTime(numericStreamId),
                this.vestingContract.getEndTime(numericStreamId),
                this.vestingContract.getRecipient(numericStreamId),
                this.vestingContract.getSender(numericStreamId),
            ]);

            // Fetch token details
            const tokenContract = new ethers.Contract(tokenAddress, ERC20_ABI, this.provider);
            const [tokenSymbol, tokenDecimals] = await Promise.all([
                tokenContract.symbol(),
                tokenContract.decimals()
            ]);

            // Format amounts
            const totalAmount = ethers.utils.formatUnits(totalAmountBigNum, tokenDecimals);
            const streamedAmount = ethers.utils.formatUnits(streamedAmountBigNum, tokenDecimals);

            // Calculate progress
            let progress = 0;
            const now = Date.now() / 1000;
            const startTime = Number(startTimeSec);
            const endTime = Number(endTimeSec);
            if (now >= endTime) {
                progress = 100;
            } else if (now > startTime) {
                progress = ((now - startTime) / (endTime - startTime)) * 100;
            }
            progress = Math.min(progress, 100);


            return {
                streamId: numericStreamId,
                recipient: recipient,
                sender: sender,
                totalAmount: totalAmount,
                streamedAmount: streamedAmount,
                startTime: new Date(startTime * 1000),
                endTime: new Date(endTime * 1000),
                progress: progress,
                token: {
                    address: tokenAddress,
                    symbol: tokenSymbol,
                    decimals: tokenDecimals
                }
            };

        } catch (error) {
             console.error(`Error fetching data for stream ${numericStreamId} from contract ${VESTING_CONTRACT_ADDRESS}:`, error);
             throw new Error(`Failed to fetch Sablier stream data for ID ${numericStreamId}.`);
        }
    }
}
