# E2E Test Suite — Tookle On-Chain (Tokenomics to Distribution)

**Scope:** Tokenomics design (DePIN, RWA, Gaming, AI), Token Sale Page creation, Web3/Escrow wallet integration (Gnosis Safe), Investor flow (KYC, E-Signature, Payment), and Token Distribution (TGE, Vesting, Refunds).

**Environments:** Staging with a seeded founder account and test wallets (e.g., Sepolia testnet or local EVM) for on-chain transactions.

**Conventions:** All fiat monetary values in USD. Blockchain interactions are non-custodial.

**Core testing angles:**
1. **Simplicity** — Web3 complexity (gas, smart contracts, RPCs) must be abstracted for non-crypto-native founders and investors.
2. **Compliance** — Strict adherence to KYC/AML (Sumsub), legal signatures for Token Sale Agreements, and soft-cap/hard-cap escrow rules.
3. **On-Chain Reliability** — Atomicity of escrow, verifiable token minting, and accurate vesting schedules (Sablier/streaming).

---

## Module 1 — Tokenomics Design & Sale Setup

### TC-TOK-001 — Tokenomics Wizard: AI-assisted design and compliant sale creation
- **Persona:** Founder (Admin) — first-time Web3 creator
- **Objective:** Verify a founder can design a token model and launch a Token Sale Page without needing Solidity or smart contract knowledge. *(Angle: Simplicity & AI)*
- **Pre-conditions:**
  - Fresh founder account with zero active projects.
- **Test Steps:**
  - **Given** the founder starts the "Tokenomics Design" wizard
  - **When** they select an industry focus (e.g., "DePIN") and input a rough business model
  - **Then** the AI assistant suggests a standard token allocation (e.g., 20% Team, 30% Ecosystem, 10% Public Sale) and generates a visual pie chart
  - **When** they finalize the tokenomics and proceed to "Setup Token Sale"
  - **Then** they can define a sale name (e.g., "Seed Round"), Unit Price (e.g., $0.05), Soft Cap ($50k), and Hard Cap ($500k)
  - **When** they link a Gnosis Safe address to act as the non-custodial vault for the raised funds
  - **Then** the system validates the wallet address on-chain
  - **When** the founder publishes the sale
  - **Then** a unique public URL (`/sale?id=...`) is generated and the sale status transitions to "Live"
- **Expected Result:**
  - End-to-end completion in ≤ 15 minutes. AI suggestions must total 100% supply. The Gnosis Safe address must be verified as a valid smart contract wallet on the selected network before publishing.

---

## Module 2 — Investor Contribution Flow

### TC-INV-001 — Investor KYC and On-Chain Contribution
- **Persona:** Investor (Stakeholder) — non-technical user
- **Objective:** Verify an investor can pass KYC, sign the Token Sale Agreement (TSA), and commit funds securely to the escrow vault. *(Angle: Compliance & Simplicity)*
- **Pre-conditions:**
  - Founder has a "Live" token sale with a Soft Cap of $50k.
  - Sumsub sandbox configured to auto-approve test identities.
- **Test Steps:**
  - **Given** the investor opens the public Token Sale Page link
  - **When** they enter a contribution amount (e.g., $5,000)
  - **Then** the platform computes the equivalent token allocation based on the unit price
  - **When** they click "Invest", they are prompted to complete KYC via the Sumsub integration
  - **Then** upon KYC approval, the platform generates a personalized Token Sale Agreement (TSA) PDF containing their details, allocation, and vesting terms
  - **When** the investor signs the TSA via the integrated e-signature provider
  - **Then** they are presented with payment options (Crypto via WalletConnect or Fiat via On-Ramp)
  - **When** the payment transaction is confirmed on-chain
  - **Then** the investor's status in the founder dashboard changes to "Fulfilled" (or "Pending" if awaiting block confirmations), and the raised amount on the sale page increments by $5,000
- **Expected Result:**
  - Zero manual intervention by the founder. Investor cannot sign the TSA without passing KYC. Funds are directed exactly to the founder's Gnosis Safe (or the designated escrow contract), never to a Tookle-controlled custodial wallet.

### TC-INV-002 — Direct Gnosis Routing (Escrow Bypass)
- **Persona:** Investor (Stakeholder)
- **Objective:** Verify that for sales configured with Direct Gnosis Routing, the investor's payment goes directly to the founder's Gnosis Safe, bypassing the escrow smart contract entirely, and immediately updates the token quantity. *(Angle: Simplicity & On-Chain Reliability)*
- **Pre-conditions:**
  - Founder has a "Live" token sale with `gnosis_safe_address` configured.
  - The investor has already signed the Token Sale Agreement (TSA) and is at the `initiated` status.
- **Test Steps:**
  - **Given** the investor elects to pay via crypto (stablecoin)
  - **When** the transaction confirms on-chain directly to the founder's Gnosis Safe
  - **Then** the platform records the `payment_tx_hash`
  - **And** the investment status immediately transitions from `initiated` to `released_to_creator` (bypassing `in_escrow`)
  - **And** the system automatically calculates the exact `token_quantity` based on the `amount_usd` and the sale's `round_price` JSON term
- **Expected Result:**
  - The payment avoids the escrow lockup. The founder receives the funds instantly. The investor's portfolio reflects the completed direct investment accurately.

---

## Module 3 — Escrow Resolution (Soft Cap)

### TC-ESC-001 — Soft Cap Threshold Detection and Refunds
- **Persona:** System / Automated Escrow
- **Objective:** Verify that funds are securely locked in escrow and only distributed if the soft cap is met; otherwise, investors can claim refunds. *(Angle: On-Chain Reliability)*
- **Pre-conditions:**
  - Sale "Seed Round" with Soft Cap $50,000.
  - Current raised amount is $10,000 (below soft cap).
  - Sale end date is reached (simulated time-travel or manual trigger).
- **Test Steps:**
  - **Given** the sale deadline passes
  - **When** the system evaluates the total raised amount
  - **Then** the sale status transitions to "Failed / Unsuccessful"
  - **And** the founder cannot withdraw the $10,000 from the escrow contract
  - **When** an investor logs into their portfolio dashboard
  - **Then** their investment card displays "Failed - Manage Refund"
  - **When** the investor clicks "Manage Refund" and submits a transaction to the escrow contract
  - **Then** their original $5,000 contribution is returned to their wallet minus standard network gas fees
- **Expected Result:**
  - Strict enforcement of escrow rules. No unauthorized withdrawal of funds by the founder if the soft cap is missed. Refund UX must clearly state gas fee implications.

### TC-ESC-002 — Successful Round and Token Distribution (TGE)
- **Persona:** Founder (Admin)
- **Objective:** Verify that reaching the soft cap unlocks funds for the founder and initiates the Token Generation Event (TGE) / vesting streams for investors. *(Angle: On-Chain Reliability)*
- **Pre-conditions:**
  - Sale "Seed Round" with Soft Cap $50,000.
  - Current raised amount is $60,000 (soft cap met).
- **Test Steps:**
  - **Given** the sale concludes successfully
  - **When** the founder triggers the "Finalize Sale" action
  - **Then** the escrow contract unlocks the $60,000 USDC/USDT for withdrawal to the founder's Gnosis Safe
  - **And** the Token Minting / Distribution module is activated
  - **When** the founder configures the distribution (e.g., 10% TGE unlock, 6 months cliff, 24 months vesting)
  - **Then** the system deploys a vesting contract (e.g., via Sablier) mapping investor wallet addresses to their respective allocations
  - **When** an investor checks their portfolio
  - **Then** they see their active vesting schedule, the 10% TGE tokens available to claim immediately, and a real-time visual progress bar of their streaming tokens
- **Expected Result:**
  - Token allocations perfectly match the math established in the Token Sale Agreement. TGE claims and streaming mechanics must be verifiable on block explorers.

---

## Suite-Wide Notes for Implementation

- **Web3 Integrations:** Use tools like Cypress-Synpress or Playwright with Metamask extensions to automate wallet interactions and transaction signing.
- **Mocking External Services:** KYC (Sumsub) and e-signatures (Skribble/DocuSign) must be mocked or pointed to dedicated sandbox environments during automated CI/CD runs to prevent rate-limiting and costs.
- **Idempotency:** On-chain tests should be designed to run against fresh local forks (e.g., Anvil / Hardhat Network) to ensure isolated, repeatable states for escrow and vesting assertions.
