<?php
/**
 * Privacy Policy Page
 * Publicly accessible legal document.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Logic for links removed as header is removed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Tookle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        /* Typography overrides for legal text readability */
        .prose h1 { color: #111827; font-weight: 700; margin-bottom: 1.5rem; }
        .prose h2 { color: #374151; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; font-size: 1.25rem; }
        .prose h3 { color: #4b5563; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; font-size: 1.1rem; }
        .prose p { color: #4b5563; line-height: 1.6; margin-bottom: 1rem; }
        .prose ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1rem; color: #4b5563; }
        .prose li { margin-bottom: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <main class="max-w-4xl mx-auto px-6 py-12">
        <div class="bg-white shadow-sm rounded-xl p-8 md:p-12 border border-gray-100">
            <article class="prose max-w-none">
                <h1 class="text-3xl tracking-tight">Privacy Policy</h1>
                <p class="text-sm text-gray-500 mb-8">Effective date: January 2026</p>

                <h2>1. Introduction</h2>
                <p>This Privacy Policy explains how Tookle (“we”, “us”, “our”) collects, uses, and protects personal data when you use the Tookle application and website (the “Service”). We are committed to processing personal data in accordance with applicable data protection laws, including the Swiss Federal Act on Data Protection (nLPD) and the EU General Data Protection Regulation (GDPR) where applicable.</p>

                <h2>2. Data Controller</h2>
                <p>The data controller is:<br>
                <strong>Tookle</strong><br>
                📧 Contact: <a href="mailto:contact@tookle.io" class="text-blue-600 hover:underline">contact@tookle.io</a></p>

                <h2>3. Personal Data We Collect</h2>
                
                <h3>3.1 Account Information</h3>
                <p>When you create an account, we may collect:</p>
                <ul>
                    <li>Name</li>
                    <li>Email address</li>
                    <li>Encrypted password</li>
                    <li>Authentication method (email/password, Google OAuth, or wallet login)</li>
                </ul>

                <h3>3.2 Web3 & Blockchain Data</h3>
                <p>When using blockchain-related features, we may process:</p>
                <ul>
                    <li>Wallet addresses</li>
                    <li>Blockchain network identifiers</li>
                    <li>Transaction hashes and on-chain interactions</li>
                </ul>
                <p class="text-sm italic text-gray-500 bg-gray-50 p-3 rounded border-l-4 border-gray-300">Note: Blockchain data is public by nature but may still be considered personal data under applicable law.</p>

                <h3>3.3 Technical & Security Data</h3>
                <p>We may collect:</p>
                <ul>
                    <li>IP address</li>
                    <li>Device and browser information</li>
                    <li>Login timestamps</li>
                    <li>reCAPTCHA verification data</li>
                </ul>

                <h3>3.4 Cookies</h3>
                <p>We use essential cookies only, necessary for:</p>
                <ul>
                    <li>User authentication</li>
                    <li>Session management</li>
                    <li>Security</li>
                </ul>
                <p>We do not use advertising cookies. If analytics cookies are added in the future, this policy will be updated accordingly.</p>

                <h2>4. How We Use Your Data</h2>
                <p>We use personal data to:</p>
                <ul>
                    <li>Provide and operate the Service</li>
                    <li>Create and manage user accounts</li>
                    <li>Secure the platform and prevent abuse</li>
                    <li>Comply with legal obligations</li>
                    <li>Communicate with users regarding their account</li>
                </ul>
                <p>We do not sell personal data.</p>

                <h2>5. Sharing of Data</h2>
                <p>We may share data with trusted third-party service providers, strictly for operating the Service, including:</p>
                <ul>
                    <li>Hosting and infrastructure providers</li>
                    <li>Authentication providers (e.g. Google OAuth)</li>
                    <li>Security services (e.g. reCAPTCHA)</li>
                </ul>
                <p>All providers are bound by confidentiality and data protection obligations.</p>

                <h2>6. Data Retention</h2>
                <p>We retain personal data:</p>
                <ul>
                    <li>As long as your account is active</li>
                    <li>As required to comply with legal obligations</li>
                    <li>Or until you request deletion, subject to legal constraints</li>
                </ul>
                <p>Blockchain data cannot be deleted due to its immutable nature.</p>

                <h2>7. Your Rights</h2>
                <p>Depending on your location, you have the right to:</p>
                <ul>
                    <li>Access your personal data</li>
                    <li>Request correction or deletion</li>
                    <li>Object to or restrict processing</li>
                    <li>Request data portability</li>
                </ul>
                <p>Requests can be sent to: <a href="mailto:contact@tookle.io" class="text-blue-600 hover:underline">contact@tookle.io</a></p>

                <h2>8. Data Security</h2>
                <p>We implement appropriate technical and organizational measures to protect personal data against unauthorized access, loss, or misuse. However, no system is entirely secure, and we cannot guarantee absolute security.</p>

                <h2>9. International Transfers</h2>
                <p>Personal data may be processed outside your country of residence. Where applicable, we ensure appropriate safeguards are in place.</p>

                <h2>10. Changes to This Policy</h2>
                <p>We may update this Privacy Policy from time to time. Material changes will require renewed acceptance where legally required.</p>

                <h2>11. Contact</h2>
                <p>For any privacy-related questions or requests, contact:<br>
                📧 <a href="mailto:contact@tookle.io" class="text-blue-600 hover:underline">contact@tookle.io</a></p>

            </article>
        </div>
        
        <footer class="mt-12 text-center text-sm text-gray-400">
            &copy; <?php echo date('Y'); ?> Tookle. All rights reserved. <a href="<?= get_url('terms') ?>" class="hover:text-gray-600 ml-2">Terms of Service</a>
        </footer>
    </main>
</body>
</html>