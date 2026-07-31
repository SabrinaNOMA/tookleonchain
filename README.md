# Tookle Onchain

Tookle Onchain is a comprehensive Web3 platform designed for token distribution, investor management, and protocol governance. It provides a secure, automated, and seamless experience for founders and investors by integrating fiat payments, KYC compliance, and on-chain wallet generation.

## 🌟 Key Features

- **Automated Investor Onboarding:** Users can purchase memberships and subscriptions via Stripe with automatic webhook processing.
- **Identity Verification (KYC):** Seamless integration with Sumsub for instant identity verification and compliance.
- **Embedded Wallets:** Automatic non-custodial wallet creation for users via Coinbase Developer Platform (CDP) API.
- **Token Management:** Smart contract interactions, balance checking, and utility token governance directly on-chain.

## 🛠 Tech Stack

- **Backend:** PHP 8+ (Vanilla PHP with PDO)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **External Services:** 
  - Stripe (Payments & Subscriptions)
  - Sumsub (KYC & AML)
  - Coinbase CDP (Embedded Wallets)

## 🚀 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/SabrinaNOMA/tookleonchain.git
   cd tookleonchain
   ```

2. **Database Configuration:**
   - Import the provided SQL dump into your MySQL server.
   - Rename `config.example.php` to `config.php` and update the database credentials.

3. **Environment Variables:**
   - The application relies on external APIs. Configure the required secrets in your `config.php`:
     - `STRIPE_WEBHOOK_SECRET`
     - Sumsub API keys
     - Coinbase CDP keys

4. **Deployment:**
   - Upload the project to your Apache/Nginx server (e.g., OVH).
   - Ensure the `.htaccess` and routing logic (`index.php`) are configured to redirect traffic appropriately.

## 🔒 Security Notes

- **Never commit `config.php` or `.env` files to Git.** These files are ignored by default via `.gitignore`.
- Ensure your Stripe Webhook uses HTTPS in production to securely receive events.
- API keys (Coinbase, Sumsub) must be stored securely and restricted to server-side usage only.

## 📄 License

Proprietary Software - All rights reserved to NOMA Services / Tookle.
